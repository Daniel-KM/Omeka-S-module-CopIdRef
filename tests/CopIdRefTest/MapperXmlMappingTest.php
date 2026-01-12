<?php declare(strict_types=1);

namespace CopIdRefTest;

use CommonTest\AbstractTestCase;

/**
 * Tests for Mapper module's IdRef JSON mapping files.
 */
class MapperXmlMappingTest extends AbstractTestCase
{
    /**
     * Get path to Mapper module's mapping directory.
     */
    protected function getMapperMappingPath(): string
    {
        return dirname(__DIR__, 3) . '/Mapper/data/mapping/';
    }

    /**
     * Mapping files to test (relative to Mapper module data/mapping/).
     */
    protected array $mappingFiles = [
        'unimarc/unimarc.idref_personne.json',
        'unimarc/unimarc.idref_collectivites.json',
        'unimarc/unimarc.idref_autre.json',
    ];

    /**
     * Test that all mapping files exist in Mapper module.
     */
    public function testMappingFilesExist(): void
    {
        $basePath = $this->getMapperMappingPath();

        foreach ($this->mappingFiles as $file) {
            $path = $basePath . $file;
            $this->assertFileExists($path, "Mapping file '$file' should exist in Mapper module.");
        }
    }

    /**
     * Test that Personne mapping is valid JSON.
     */
    public function testPersonneMappingValidJson(): void
    {
        $this->assertMappingValid('unimarc/unimarc.idref_personne.json');
    }

    /**
     * Test that Collectivité mapping is valid JSON.
     */
    public function testCollectiviteMappingValidJson(): void
    {
        $this->assertMappingValid('unimarc/unimarc.idref_collectivites.json');
    }

    /**
     * Test that Autre mapping is valid JSON.
     */
    public function testAutreMappingValidJson(): void
    {
        $this->assertMappingValid('unimarc/unimarc.idref_autre.json');
    }

    /**
     * Test Personne mapping has required maps.
     */
    public function testPersonneMappingHasMaps(): void
    {
        $mapping = $this->loadMapping('unimarc/unimarc.idref_personne.json');

        $this->assertArrayHasKey('Personne', $mapping);
        $maps = $mapping['Personne'];

        // Check for foaf:name map.
        $hasName = false;
        foreach ($maps as $map) {
            if (($map['to']['data']['property'] ?? null) === 'foaf:name') {
                $hasName = true;
                break;
            }
        }
        $this->assertTrue($hasName, 'Personne mapping should have foaf:name map.');

        // Check for bio:birth map.
        $hasBirth = false;
        foreach ($maps as $map) {
            if (($map['to']['data']['property'] ?? null) === 'bio:birth') {
                $hasBirth = true;
                break;
            }
        }
        $this->assertTrue($hasBirth, 'Personne mapping should have bio:birth map.');
    }

    /**
     * Test Collectivité mapping has required maps.
     */
    public function testCollectiviteMappingHasMaps(): void
    {
        $mapping = $this->loadMapping('unimarc/unimarc.idref_collectivites.json');

        $this->assertArrayHasKey('Collectivité', $mapping);
        $maps = $mapping['Collectivité'];

        // Check for foaf:name map.
        $hasName = false;
        foreach ($maps as $map) {
            if (($map['to']['data']['property'] ?? null) === 'foaf:name') {
                $hasName = true;
                break;
            }
        }
        $this->assertTrue($hasName, 'Collectivité mapping should have foaf:name map.');
    }

    /**
     * Test Autre mapping has required maps.
     */
    public function testAutreMappingHasMaps(): void
    {
        $mapping = $this->loadMapping('unimarc/unimarc.idref_autre.json');

        $this->assertArrayHasKey('Autre', $mapping);
        $maps = $mapping['Autre'];

        // Check for dcterms:title map.
        $hasTitle = false;
        foreach ($maps as $map) {
            if (($map['to']['data']['property'] ?? null) === 'dcterms:title') {
                $hasTitle = true;
                break;
            }
        }
        $this->assertTrue($hasTitle, 'Autre mapping should have dcterms:title map.');
    }

    /**
     * Test geonames countries table exists.
     */
    public function testGeonamesTableExists(): void
    {
        $path = $this->getMapperMappingPath() . 'tables/geonames.countries.json';
        $this->assertFileExists($path, 'Geonames countries table should exist.');

        $content = file_get_contents($path);
        $data = json_decode($content, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('FR', $data, 'Geonames table should have FR entry.');
    }

    /**
     * Assert that a mapping file is valid JSON with correct structure.
     */
    protected function assertMappingValid(string $filename): void
    {
        $path = $this->getMapperMappingPath() . $filename;

        $content = file_get_contents($path);
        $data = json_decode($content, true);

        $this->assertNotNull($data, "$filename should be valid JSON.");
        $this->assertIsArray($data, "$filename should decode to array.");

        // Check has at least one mapping group.
        $this->assertNotEmpty($data, "$filename should have at least one mapping group.");

        // Check first group has maps array.
        $firstGroup = reset($data);
        $this->assertIsArray($firstGroup, "$filename first group should be an array of maps.");
    }

    /**
     * Load mapping file and return decoded data.
     */
    protected function loadMapping(string $filename): array
    {
        $path = $this->getMapperMappingPath() . $filename;
        $content = file_get_contents($path);
        return json_decode($content, true);
    }
}
