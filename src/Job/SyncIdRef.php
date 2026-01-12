<?php declare(strict_types=1);

namespace CopIdRef\Job;

use DOMDocument;
use Omeka\Job\AbstractJob;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

/**
 * Job to sync resources with IdRef authority data using Mapper module.
 */
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
     * @var \Mapper\Stdlib\Mapper
     */
    protected $mapper;

    /**
     * @var array
     */
    protected $geonamesCountries = [];

    /**
     * Mapping files per datatype (relative to Mapper module data/mapping/).
     *
     * @var array
     */
    protected $mappingFiles = [
        'valuesuggest:idref:person' => 'unimarc/unimarc.idref_personne.json',
        'valuesuggest:idref:corporation' => 'unimarc/unimarc.idref_collectivites.json',
    ];

    /**
     * Default mapping file for unknown datatypes.
     *
     * @var string
     */
    protected $defaultMappingFile = 'unimarc/unimarc.idref_autre.json';

    public function perform(): void
    {
        $services = $this->getServiceLocator();

        // The reference id is the job id for now.
        $referenceIdProcessor = new \Laminas\Log\Processor\ReferenceId();
        $referenceIdProcessor->setReferenceId('copidref/sync/job_' . $this->job->getId());

        $this->logger = $services->get('Omeka\Logger');
        $this->logger->addProcessor($referenceIdProcessor);

        $this->api = $services->get('Omeka\ApiManager');
        $this->easyMeta = $services->get('Common\EasyMeta');
        $this->entityManager = $services->get('Omeka\EntityManager');
        $this->mapper = $services->get('Mapper\Mapper');

        $args = $this->job->getArgs() ?: [];

        if (empty($args['mode']) || !in_array($args['mode'], ['append', 'replace'])) {
            $this->logger->err(
                'Le mode de mise à jour n’est pas indiqué.' // @translate
            );
            return;
        }

        if (empty($args['properties'])) {
            $this->logger->err(
                'Les propriétés à mettre à jour ne sont pas indiquées.' // @translate
            );
            return;
        }

        if (empty($args['property_uri']) || !$this->easyMeta->propertyId($args['property_uri'])) {
            $this->logger->err(
                'La propriété où se trouve l’uri n’est pas indiquée.' // @translate
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

        $this->syncViaIdRef(
            $args['mode'],
            $args['query'] ?? [],
            $args['properties'],
            $datatypes,
            $args['property_uri']
        );

        $this->logger->notice(
            'Mise à jour des notices terminées.' // @translate
        );
    }

    protected function syncViaIdRef($mode, $query, $properties, $datatypes, $propertyUri): void
    {
        $propertyId = $this->easyMeta->propertyId($propertyUri);

        $query['property'][] = [
            'joiner' => 'and',
            'property' => $propertyId,
            'type' => 'ex',
        ];

        $response = $this->api->search('items', $query, ['returnScalar' => 'id']);

        $totalToProcess = $response->getTotalResults();
        if (empty($totalToProcess)) {
            $this->logger->warn(
                'The results of the query is empty. You may check it and the property to update.' // @translate
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
                        $record = $this->fetchRecord($url, $datatype);
                        if ($record) {
                            $result = $this->updateResource(
                                $item,
                                $record,
                                $mode,
                                $properties,
                                $datatype,
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

    /**
     * Update resource using Mapper module.
     */
    protected function updateResource(
        AbstractResourceEntityRepresentation $resource,
        DOMDocument $record,
        string $mode,
        array $properties,
        string $datatype,
        bool $processAllProperties
    ): ?bool {
        // Get mapping file path from Mapper module.
        $mappingFile = $this->mappingFiles[$datatype] ?? $this->defaultMappingFile;
        $mapperModulePath = dirname(__DIR__, 3) . '/Mapper/data/mapping/';
        $mappingPath = $mapperModulePath . $mappingFile;

        if (!file_exists($mappingPath)) {
            $this->logger->err(
                'Mapping file "{file}" not found.', // @translate
                ['file' => $mappingFile]
            );
            return false;
        }

        // Load mapping content.
        $mappingContent = file_get_contents($mappingPath);

        // Convert DOMDocument to SimpleXMLElement for Mapper.
        $xml = simplexml_import_dom($record);
        if (!$xml) {
            $this->logger->err(
                'Could not convert record to SimpleXML.'
            );
            return false;
        }

        // Set up geonames table for lookup.
        $this->mapper->getMapperConfig()->__invoke('geonames-table', [
            'info' => ['label' => 'Geonames Countries'],
            'tables' => ['geonames' => $this->geonamesCountries],
        ]);

        // Load and set mapping.
        $this->mapper->setMapping('idref-' . $datatype, $mappingContent);

        // Convert record using Mapper.
        $converted = $this->mapper->convert($xml);

        if (empty($converted)) {
            return null;
        }

        // Prepare update data.
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
        foreach ($converted as $property => $values) {
            // Skip non-property fields.
            if (mb_strpos($property, ':') === false || mb_substr($property, 0, 2) === 'o:') {
                continue;
            }

            if (!$processAllProperties && !in_array($property, $properties)) {
                continue;
            }

            $propertyId = $this->easyMeta->propertyId($property);
            if (!$propertyId) {
                continue;
            }

            // Process each value.
            foreach ($values as $valueData) {
                if (empty($valueData)) {
                    continue;
                }

                // Build normalized value structure.
                $valueType = $valueData['type'] ?? 'literal';
                $newValue = [
                    'property_id' => $propertyId,
                    'type' => $valueType,
                    '@language' => $valueData['@language'] ?? null,
                    '@value' => $valueData['@value'] ?? null,
                    '@id' => $valueData['@id'] ?? null,
                    'o:label' => $valueData['o:label'] ?? null,
                    'is_public' => $valueData['is_public'] ?? true,
                ];

                // Handle replace mode.
                if ($mode === 'replace') {
                    if (!isset($data[$property]) || !$isNew) {
                        $data[$property] = [];
                    }
                }

                // Check for duplicates in append mode.
                if ($mode === 'append' && isset($data[$property])) {
                    $isDuplicate = false;
                    foreach ($data[$property] as $existingValue) {
                        $ex = array_replace($checkValue, $existingValue);
                        $new = array_replace($checkValue, $newValue);
                        if ($ex === $new) {
                            $isDuplicate = true;
                            break;
                        }
                    }
                    if ($isDuplicate) {
                        continue;
                    }
                }

                $isNew = true;
                $data[$property][] = $newValue;
            }
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
            'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64; rv:145.0) Gecko/20100101 Firefox/145.0',
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
        $geonames = @file_get_contents('https://download.geonames.org/export/dump/countryInfo.txt');
        if (!strlen((string) $geonames)) {
            $this->logger->warn(
                'Impossible de récupérer les données des pays geonames. Utilisation du fichier local' // @translate
            );
            $localFile = dirname(__DIR__, 3) . '/Mapper/data/mapping/tables/geonames.countries.json';
            if (file_exists($localFile)) {
                $geonames = json_decode(file_get_contents($localFile), true);
                $this->geonamesCountries = array_map(fn ($v) => 'http://www.geonames.org/' . $v, $geonames);
            }
            return $this;
        }

        $result = [];
        foreach (explode("\n", $geonames) as $row) {
            $row = trim((string) $row);
            if (!$row || mb_substr($row, 0, 1) === '#') {
                continue;
            }
            $row = str_getcsv($row, "\t");
            if (isset($row[0], $row[16])) {
                $result[$row[0]] = 'http://www.geonames.org/' . trim($row[16]);
            }
        }

        $this->geonamesCountries = $result;
        return $this;
    }
}
