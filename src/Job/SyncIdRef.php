<?php declare(strict_types=1);

namespace CopIdRef\Job;

use DOMDocument;
use DOMXPath;
use Omeka\Job\AbstractJob;
use Omeka\Stdlib\Message;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

class SyncIdRef extends AbstractJob
{
    /**
     * Limit for the loop to avoid heavy sql requests.
     *
     * @var int
     */
    const SQL_LIMIT = 100;

    /**
     * @var \Laminas\Log\Logger
     */
    protected $logger;

    /**
     * @var \Omeka\Api\Manager
     */
    protected $api;

    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $connection;

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $entityManager;

    /**
     * @var \BulkEdit\View\Helper\CustomVocabBaseType
     */
    protected $customVocabBaseType;

    public function perform(): void
    {
        $services = $this->getServiceLocator();

        // The reference id is the job id for now.
        $referenceIdProcessor = new \Laminas\Log\Processor\ReferenceId();
        $referenceIdProcessor->setReferenceId('easy-admin/check/job_' . $this->job->getId());

        // The reference id is the job id for now.
        $referenceIdProcessor = new \Laminas\Log\Processor\ReferenceId();
        $referenceIdProcessor->setReferenceId('copidref/sync/job_' . $this->job->getId());

        $this->logger = $services->get('Omeka\Logger');
        $this->logger->addProcessor($referenceIdProcessor);

        $this->api = $services->get('Omeka\ApiManager');
        $this->connection = $services->get('Omeka\Connection');
        $this->entityManager = $services->get('Omeka\EntityManager');

        $this->customVocabBaseType = $services->get('ViewHelperManager')->has('customVocabBaseType')
            ? $services->get('ViewHelperManager')->get('customVocabBaseType')
            : null;

        $args = $this->job->getArgs() ?: [];
        if (empty($args['mode']) || !in_array($args['mode'], ['append', 'replace'])) {
            $this->logger->err(new Message(
                'Le mode de mise à jour n’est pas indiqué.' // @translate
            ));
            return;
        }

        if (empty($args['properties'])) {
            $this->logger->err(new Message(
                'Les propriétés à mettre à jour ne sont pas indiquées.'
            ));
            return;
        }

        if (empty($args['property_uri']) || empty($this->getPropertyIds()[$args['property_uri']])) {
            $this->logger->err(new Message(
                'La propriété où se trouve l’uri n’est pas indiquée.'
            ));
            return;
        }

        $managedDatatypes = [
            'literal',
            'uri',
            'valuesuggest:idref:person',
            'valuesuggest:idref:corporation',
        ];

        $datatypes = $args['datatypes'] ?? [];
        $processAllDatatypes = empty($datatypes) || in_array('all', $datatypes);

        // Flat the list of datatypes.
        $dataTypeManager = $services->get('Omeka\DataTypeManager');
        $datatypes = $processAllDatatypes
            ? array_intersect($dataTypeManager->getRegisteredNames(), $managedDatatypes)
            : array_intersect($dataTypeManager->getRegisteredNames(), $datatypes, $managedDatatypes);

        if (empty($datatypes)) {
            $this->logger->err(new Message(
                'Les types de données ne sont pas définis.'
            ));
            return;
        }

        $mappingFile = dirname(__DIR__, 2) . '/data/mappings/mappings.json';
        $mapping = file_exists($mappingFile) && is_readable($mappingFile) && filesize($mappingFile)
            ? json_decode(file_get_contents($mappingFile), true)
            : null;

        if (!$mapping) {
            $this->logger->err(new Message(
                'Le fichier d’alignement "data/mappings/mappings.json" est vide.'
            ));
            return;
        }

        $this->logger->notice(new Message(
            'Utilisation du fichier d’alignement "data/mappings/mappings.json".'
        ));

        $this->syncViaIdRef(
            $args['mode'],
            $args['query'] ?? [],
            $args['properties'],
            $datatypes,
            $args['property_uri'],
            $mapping,
            $args['mapping_key'] ?? null
        );

        $this->logger->notice(
            'Mise à jour des notices terminées.' // @translate
        );
    }

    protected function syncViaIdRef($mode, $query, $properties, $datatypes, $propertyUri, $mapping, $mappingKey)
    {
        // CopIdRef mapping doesn't use the datatype or class.
        $mappingMaps = [
            'valuesuggest:idref:person' => 'Personne',
            'valuesuggest:idref:corporation' => 'Collectivité',
        ];

        $propertyId = $this->getPropertyIds()[$propertyUri];

        $query['property'][] = [
            'joiner' => 'and',
            'property' => $propertyId,
            'type' => 'ex',
        ];

        $response = $this->api->search('items', ['limit' => 0] + $query);
        $totalToProcess = $response->getTotalResults();
        if (empty($totalToProcess)) {
            $this->logger->warn(new Message(
                'No item selected. You may check your query.' // @translate
            ));
            return;
        }

        $this->logger->info(new Message(
            'Starting processing %1$d items with uri.', // @translate
            $totalToProcess
        ));

        $processAllProperties = in_array('all', $properties);

        $offset = 0;
        $totalProcessed = 0;
        $totalSucceed = 0;
        $totalNoNewData = 0;
        $totalFailed = 0;
        $totalSkipped = 0;
        while (true) {
            /** @var \Omeka\Api\Representation\ItemRepresentation[] $items */
            $items = $this->api
                ->search('items', ['limit' => self::SQL_LIMIT, 'offset' => $offset] + $query)
                ->getContent();
            if (empty($items)) {
                break;
            }

            foreach ($items as $key => $item) {
                if ($this->shouldStop()) {
                    $this->logger->warn(new Message(
                        'The job was stopped: %1$d/%2$d resources processed.', // @translate
                        $offset + $key, $totalToProcess
                    ));
                    break 2;
                }

                $value = $item->value($propertyUri, ['type' => $datatypes]);
                if ($value) {
                    $url = $value->uri() ?: $value->value();
                    if ($url) {
                        $datatype = $value->type();
                        $mapKey = $mapping[$mappingMaps[$datatype] ?? $datatype] ?? $mappingKey;
                        if ($mapKey) {
                            $record = $this->fetchRecord($url, $datatype);
                            if ($record) {
                                $result = $this->updateResource(
                                    $item,
                                    $record,
                                    $mode,
                                    $properties,
                                    $mapping[$mappingMaps[$datatype] ?? $datatype],
                                    $processAllProperties
                                );
                                if ($result === true) {
                                    $this->logger->info(new Message(
                                        'Item #%1$d: uri "%2$s" has new data.', // @translate
                                        $item->id(), $url
                                    ));
                                    ++$totalSucceed;
                                } elseif (is_null($result)) {
                                    ++$totalNoNewData;
                                } else {
                                    $this->logger->err(new Message(
                                        'Item #%1$d: uri "%2$s" not updatable.', // @translate
                                        $item->id(), $url
                                    ));
                                    ++$totalFailed;
                                }
                            } else {
                                $this->logger->err(new Message(
                                    'Item #%1$d: uri "%2$s" not available.', // @translate
                                    $item->id(), $url
                                ));
                                ++$totalFailed;
                            }
                        } else {
                            $this->logger->warn(new Message(
                                'Item #%1$d: unable to determine map key from the datatype "%2$s".', // @translate
                                $item->id(), $datatype
                            ));
                            ++$totalSkipped;
                        }
                    } else {
                        $this->logger->warn(new Message(
                            'Item #%1$d: no uri in value.', // @translate
                            $item->id(), $datatype
                        ));
                        ++$totalSkipped;
                    }
                } else {
                    $this->logger->warn(new Message(
                        'Item #%1$d: no value.', // @translate
                        $item->id(), $datatype
                    ));
                    ++$totalSkipped;
                }

                unset($item);

                ++$totalProcessed;
            }

            $this->entityManager->clear();
            $offset += self::SQL_LIMIT;
        }

        $this->logger->notice(new Message(
            'End of process: %1$d/%2$d items processed, %3$d updated, %4$d without new data, %5$d errors, %6$d skipped.', // @translate
            $totalProcessed,
            $totalToProcess,
            $totalSucceed,
            $totalNoNewData,
            $totalFailed,
            $totalSkipped
        ));
    }

    protected function updateResource(
        AbstractResourceEntityRepresentation $resource,
        DOMDocument $record,
        string $mode,
        array $properties,
        array $mapping,
        bool $processAllProperties
    ): ?bool {
        // It's simpler to process data as a full array.
        $data = json_decode(json_encode($resource), true);

        $checkValue = [
            'property_id' => null,
            'type' => null,
            '@language' => null,
            '@value' => null,
            '@id' => null,
            'o:label' => null,
            'is_public' => null,
            'value_resource_id' => null,
            'value_resource_name' => null,
        ];

        $isNew = false;
        foreach ($mapping  as $map) {
            if ($map['to']['type'] !== 'property') {
                continue;
            }
            $property = $map['to']['data']['property'];
            if (!$processAllProperties && !in_array($property, $properties)) {
                continue;
            }
            $propertyId = $this->getPropertyIds()[$property] ?? null;
            if (!$propertyId) {
                continue;
            }
            if ($map['from']['type'] === 'data') {
                $query = trim($map['from']['#'], ' =');
            } elseif ($map['from']['type'] === 'xpath') {
                $query = $map['from']['path'];
            } else {
                continue;
            }
            $xpath = new DOMXPath($record);
            $nodeList = $xpath->query($query);
            if (!$nodeList || !$nodeList->length) {
                continue;
            }
            $value = trim((string) $nodeList->item(0)->nodeValue);
            if ($value === '') {
                continue;
            }
            $datatype = $map['to']['data']['type'] ?? 'literal';
            $format = $map['to']['format'] ?? null;
            switch ($format) {
                case 'concat':
                    $val = '';
                    foreach ($map['to']['args'] as $arg) {
                        $val .= $arg === '__value__' ? $value : $arg;
                    }
                    $value = $val;
                    break;
                case 'table':
                    if (isset($map['to']['args'][$value])) {
                        $value = $map['to']['args'][$value];
                    } else {
                        $datatype = 'literal';
                    }
                    break;
                case 'number_to_date':
                    if (preg_match('~^[+ -]?[\d]+$~', $value)) {
                        $sign = substr($value, 0, 1) === '-' ? '-' : '';
                        $value = str_replace(['-', '+', ' '], '', $value);
                        $value = $sign . rtrim(substr($value, 0, 4) . '-' . substr($value, 4, 2) . '-' . substr($value, 6, 2), '-');
                    } else {
                        $datatype = 'literal';
                    }
                    break;
                default:
                    // nothing.
            }

            // TODO Warning: keep values when there is no value?
            if ($mode === 'replace') {
                $data[$property] = [];
            }

            $datatypeMain = $this->mainDataType($datatype);
            switch ($datatypeMain) {
                case 'uri':
                    $newValue = [
                        'property_id' => $propertyId,
                        'type' => $datatype,
                        '@language' => null,
                        '@value' => null,
                        '@id' => $value,
                        'o:label' => null,
                        'is_public' => true,
                    ];
                    break;
                case 'resource':
                    // not possible here.
                default:
                    $newValue = [
                        'property_id' => $propertyId,
                        'type' => $datatype,
                        '@language' => null,
                        '@value' => $value,
                        'is_public' => true,
                    ];
                    break;
            }

            // Check if duplicate.
            if ($mode === 'append') {
                foreach ($data[$property] as $existingValue) {
                    $ex = array_replace($checkValue, $existingValue);
                    $new = array_replace($checkValue, $newValue);
                    if ($ex === $new) {
                        continue 2;
                    }
                }
            }

            $isNew = true;
            $data[$property][] = $newValue;
        }

        if (!$isNew) {
            return null;
        }

        try {
            $this->api->update($resource->resourceName(), $resource->id(), $data);
        } catch (\Exception $exception) {
            $this->logger->err(new Message(
                'Item #%1$s : %2$s',
                $resource->id(), $exception->getMessage()
            ));
            return false;
        }

        return true;
    }

    protected function fetchRecord(string $uri, string $datatype, array $options = []): ?DOMDocument
    {
        static $filleds = [];

        if (array_key_exists($uri, $filleds)) {
            return $filleds[$uri];
        }

        $url = $this->cleanRemoteUri($uri, $datatype);
        if (!$url) {
            $filleds[$uri] = null;
            return null;
        }

        if (array_key_exists($url, $filleds)) {
            return $filleds[$url];
        }

        $doc = $this->fetchUrlXml($url);
        if (!$doc) {
            return null;
        }

        $filleds[$uri] = $doc;
        return $doc;
    }

    protected function cleanRemoteUri(string $uri, string $datatype): ?string
    {
        if (!$uri) {
            return null;
        }

        $baseurlIdref = [
            'idref.fr/',
        ];

        $isManagedUrl = false;
        foreach ($baseurlIdref as $baseUrl) {
            foreach (['http://', 'https://', 'http://www.', 'https://www.'] as $prefix) {
                if (mb_substr($uri, 0, strlen($prefix . $baseUrl)) === $prefix . $baseUrl) {
                    $isManagedUrl = true;
                    break 2;
                }
            }
        }
        if (!$isManagedUrl) {
            return null;
        }

        // Uri to url.
        return mb_substr($uri, -4) === '.xml' ? $uri : $uri . '.xml';
    }

    protected function fetchUrlXml(string $url): ?DOMDocument
    {
        $headers = [
            'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64; rv:115.0) Gecko/20100101 Firefox/115.0',
            'Content-Type' => 'application/xml',
            'Accept-Encoding' => 'gzip, deflate',
        ];

        try {
            $response = \Laminas\Http\ClientStatic::get($url, [], $headers);
        } catch (\Laminas\Http\Client\Exception\ExceptionInterface $e) {
            $this->logger->err(new Message(
                'Connection error when fetching url "%1$s": %2$s', // @translate
                $url, $e
            ));
            return null;
        }
        if (!$response->isSuccess()) {
            $this->logger->err(new Message(
                'Connection issue when fetching url "%1$s": %2$s', // @translate
                $url, $response->getReasonPhrase()
            ));
            return null;
        }

        $xml = $response->getBody();
        if (!$xml) {
            $this->logger->err(new Message(
                'Output is not xml for url "%s".', // @translate
                $url
            ));
            return null;
        }

        // $simpleData = new SimpleXMLElement($xml, LIBXML_BIGLINES | LIBXML_COMPACT | LIBXML_NOBLANKS
        //     | /* LIBXML_NOCDATA | */ LIBXML_NOENT | LIBXML_PARSEHUGE);

        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        try {
            $doc->loadXML($xml);
        } catch (\Exception $e) {
            $this->logger->err(new Message(
                'Output is not xml for url "%s".', // @translate
                $url
            ));
            return null;
        }

        if (!$doc) {
            $this->logger->err(new Message(
                'Output is not xml for url "%s".', // @translate
                $url
            ));
            return null;
        }

        return $doc;
    }

    /**
     * Get main datatype ("literal", "resource" or "uri") from any data type.
     */
    protected function mainDataType(?string $dataType): ?string
    {
        if (empty($dataType)) {
            return null;
        }
        $mainDataTypes = [
            'literal' => 'literal',
            'uri' => 'uri',
            'resource' => 'resource',
            'resource:item' => 'resource',
            'resource:itemset' => 'resource',
            'resource:media' => 'resource',
            // Module Annotate.
            'resource:annotation' => 'resource',
            'annotation' => 'resource',
            // Module DataTypeGeometry.
            // Ancienne version.
            'geometry:geography:coordinates' => 'literal',
            'geometry:geography' => 'literal',
            'geometry:geometry' => 'literal',
            // Nouvelle version.
            'geography' => 'literal',
            'geography:coordinates' => 'literal',
            'geometry' => 'literal',
            'geometry:coordinates' => 'literal',
            'geometry:position' => 'literal',
            // Module DataTypePlace.
            'place' => 'place',
            // Module DataTypeRdf.
            'html' => 'literal',
            'xml' => 'literal',
            'boolean' => 'literal',
            // Specific module.
            'email' => 'literal',
            // Module NumericDataTypes.
            'numeric:timestamp' => 'literal',
            'numeric:integer' => 'literal',
            'numeric:duration' => 'literal',
            'numeric:interval' => 'literal',
        ];
        $dataType = strtolower($dataType);
        if (array_key_exists($dataType, $mainDataTypes)) {
            return $mainDataTypes[$dataType];
        }
        // Module ValueSuggest.
        if (substr($dataType, 0, 12) === 'valuesuggest'
            // || substr($dataType, 0, 15) === 'valuesuggestall'
        ) {
            return 'uri';
        }
        if (substr($dataType, 0, 11) === 'customvocab') {
            return $this->customVocabBaseType
                ? $this->customVocabBaseType->__invoke(substr($dataType, 12))
                : 'literal';
        }
        return null;
    }

    /**
     * Get all property ids by term.
     *
     * @return array Associative array of ids by term.
     */
    protected function getPropertyIds(): array
    {
        static $properties;
        if (isset($properties)) {
            return $properties;
        }

        $qb = $this->connection->createQueryBuilder();
        $qb
            ->select(
                'DISTINCT CONCAT(vocabulary.prefix, ":", property.local_name) AS term',
                'property.id AS id',
                // Only the two first selects are needed, but some databases
                // require "order by" or "group by" value to be in the select.
                'vocabulary.id'
            )
            ->from('property', 'property')
            ->innerJoin('property', 'vocabulary', 'vocabulary', 'property.vocabulary_id = vocabulary.id')
            ->orderBy('vocabulary.id', 'asc')
            ->addOrderBy('property.id', 'asc')
            ->addGroupBy('property.id')
        ;
        return $properties
            = array_map('intval', $this->connection->executeQuery($qb)->fetchAllKeyValue());
    }
}
