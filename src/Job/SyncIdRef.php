<?php declare(strict_types=1);

namespace CopIdRef\Job;

use DOMDocument;
use DOMXPath;
use Omeka\Job\AbstractJob;
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
     * @var \Omeka\Api\Manager
     */
    protected $api;

    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $connection;

    /**
     * @var \Common\Stdlib\EasyMeta
     */
    protected $easyMeta;

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $entityManager;

    /**
     * @var \Laminas\Log\Logger
     */
    protected $logger;

    /**
     * @var array
     */
    protected $geonamesCountries;

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
        $this->easyMeta = $services->get('EasyMeta');
        $this->connection = $services->get('Omeka\Connection');
        $this->entityManager = $services->get('Omeka\EntityManager');

        $args = $this->job->getArgs() ?: [];
        if (empty($args['mode']) || !in_array($args['mode'], ['append', 'replace'])) {
            $this->logger->err(
                'Le mode de mise à jour n’est pas indiqué.' // @translate
            );
            return;
        }

        if (empty($args['properties'])) {
            $this->logger->err(
                'Les propriétés à mettre à jour ne sont pas indiquées.'
            );
            return;
        }

        if (empty($args['property_uri']) || !$this->easyMeta->propertyId([$args['property_uri']])) {
            $this->logger->err(
                'La propriété où se trouve l’uri n’est pas indiquée.'
            );
            return;
        }

        $this->initGeonamesCountries();

        $managedDatatypes = [
            'literal',
            'uri',
            'valuesuggest:idref:person',
            'valuesuggest:idref:corporation',
            'valuesuggest:geonames:geonames',
        ];

        $datatypes = $args['datatypes'] ?? [];
        $processAllDatatypes = empty($datatypes) || in_array('all', $datatypes);

        // Flat the list of datatypes.
        $dataTypesAll = $this->easyMeta->dataTypeNames();
        $datatypes = $processAllDatatypes
            ? array_intersect($dataTypesAll, $managedDatatypes)
            : array_intersect($dataTypesAll, $datatypes, $managedDatatypes);

        if (empty($datatypes)) {
            $this->logger->err(
                'Les types de données ne sont pas définis.'
            );
            return;
        }

        $mappingFile = dirname(__DIR__, 2) . '/data/mappings/mappings.json';
        $mapping = file_exists($mappingFile) && is_readable($mappingFile) && filesize($mappingFile)
            ? json_decode(file_get_contents($mappingFile), true)
            : null;

        if (!$mapping) {
            $this->logger->err(
                'Le fichier d’alignement "data/mappings/mappings.json" est vide.'
            );
            return;
        }

        $this->logger->notice(
            'Utilisation du fichier d’alignement "data/mappings/mappings.json".'
        );

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

        $propertyId = $this->easyMeta->propertyId($propertyUri);

        $query['property'][] = [
            'joiner' => 'and',
            'property' => $propertyId,
            'type' => 'ex',
        ];

        $response = $this->api->search('items', ['limit' => 0] + $query);
        $totalToProcess = $response->getTotalResults();
        if (empty($totalToProcess)) {
            $this->logger->warn(
                'No item selected. You may check your query.' // @translate
            );
            return;
        }

        $this->logger->info(
            'Starting processing {total} items with uri.', // @translate
            ['total' => $totalToProcess]
        );

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
                    $this->logger->warn(
                        'The job was stopped: {count}/{total} resources processed.', // @translate
                        ['count' => $offset + $key, 'total' => $totalToProcess]
                    );
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
                                    $this->logger->info(
                                        'Item #{item_id}: uri "{uri}" has new data.', // @translate
                                        ['item_id' => $item->id(), 'uri' => $url]
                                    );
                                    ++$totalSucceed;
                                } elseif (is_null($result)) {
                                    ++$totalNoNewData;
                                } else {
                                    $this->logger->err(
                                        'Item #{item_id}: uri "{uri}" not updatable.', // @translate
                                        ['item_id' => $item->id(), 'uri' => $url]
                                    );
                                    ++$totalFailed;
                                }
                            } else {
                                $this->logger->err(
                                    'Item #{item_id}: uri "{uri}" not available.', // @translate
                                    ['item_id' => $item->id(), 'uri' => $url]
                                );
                                ++$totalFailed;
                            }
                        } else {
                            $this->logger->warn(
                                'Item #{item_id}: unable to determine map key from the datatype "{datatype}".', // @translate
                                ['item_id' => $item->id(), 'datatype' => $datatype]
                            );
                            ++$totalSkipped;
                        }
                    } else {
                        $this->logger->warn(
                            'Item #{item_id}: no uri in value.', // @translate
                            ['item_id' => $item->id()]
                        );
                        ++$totalSkipped;
                    }
                } else {
                    $this->logger->warn(
                        'Item #{item_id}: no value.', // @translate
                        ['item_id' => $item->id()]
                    );
                    ++$totalSkipped;
                }

                unset($item);

                ++$totalProcessed;
            }

            $this->entityManager->clear();
            $offset += self::SQL_LIMIT;
        }

        $this->logger->notice(
            'End of process: {count}/{total} items processed, {total_succeed} updated, {total_no_new} without new data, {total_failed} errors, {total_skipped} skipped.', // @translate
            [
                'count' => $totalProcessed,
                'total' => $totalToProcess,
                'total_succeed' => $totalSucceed,
                'total_no_new' => $totalNoNewData,
                'total_failed' => $totalFailed,
                'total_skipped' => $totalSkipped,
            ]
        );
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
            $propertyId = $this->easyMeata->propertyId($property);
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
                case 'code_to_geonames':
                    if (isset($this->geonamesCountries[$value])) {
                        $value = $this->geonamesCountries[$value];
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

            $datatypeMain = $this->easyMeta->dataTypeMain($datatype);
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
            $this->logger->err(
                'Item #{item_id}: {message}',
                ['item_id' => $resource->id(), 'message' => $exception->getMessage()]
            );
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
            'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64; rv:129.0) Gecko/20100101 Firefox/129.0',
            'Content-Type' => 'application/xml',
            'Accept-Encoding' => 'gzip, deflate',
        ];

        try {
            $response = \Laminas\Http\ClientStatic::get($url, [], $headers);
        } catch (\Laminas\Http\Client\Exception\ExceptionInterface $e) {
            $this->logger->err(
                'Connection error when fetching url "{url}": {exception}', // @translate
                ['url' => $url, 'exception' => $e]
            );
            return null;
        }
        if (!$response->isSuccess()) {
            $this->logger->err(
                'Connection issue when fetching url "{url}": {message}', // @translate
                ['url' => $url, 'message' => $response->getReasonPhrase()]
            );
            return null;
        }

        $xml = $response->getBody();
        if (!$xml) {
            $this->logger->err(
                'Output is not xml for url "{url}".', // @translate
                ['url' => $url]
            );
            return null;
        }

        // $simpleData = new SimpleXMLElement($xml, LIBXML_BIGLINES | LIBXML_COMPACT | LIBXML_NOBLANKS
        //     | /* LIBXML_NOCDATA | */ LIBXML_NOENT | LIBXML_PARSEHUGE);

        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        try {
            $doc->loadXML($xml);
        } catch (\Exception $e) {
            $this->logger->err(
                'Output is not xml for url "{url}".', // @translate
                ['url' => $url]
            );
            return null;
        }

        if (!$doc) {
            $this->logger->err(
                'Output is not xml for url "{url}".', // @translate
                ['url' => $url]
            );
            return null;
        }

        return $doc;
    }

    /**
     * Prepare table of iso-2 letters to geonames uri.
     */
    protected function initGeonamesCountries(): self
    {
        $geonames = file_get_contents('https://download.geonames.org/export/dump/countryInfo.txt');
        if (!strlen((string) $geonames)) {
            $this->logger->warn(
                'Impossible de récupérer les données des pays geonames. Utilisation du fichier local'
            );
            $geonames = file_get_contents(dirname(__DIR__, 2) . '/data/mappings/geonames_countries.json');
            $geonames = json_decode($geonames, true);
            $this->geonames = array_map(fn ($v) => 'http://www.geonames.org/' . $v, $geonames);
            return $this;
        }

        $result = [];
        foreach (explode("\n", $geonames) as $row) {
            $row = trim((string) $row);
            if (!$row || mb_substr($row, 0, 1) === '#') {
                continue;
            }
            $row = str_getcsv($row, "\t");
            $result[$row[0]] = 'http://www.geonames.org/' . trim($row[16]);
        }

        $this->geonames = $result;
        return $this;
    }
}
