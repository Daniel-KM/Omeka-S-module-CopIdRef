<?php declare(strict_types=1);

namespace CopIdRefTest;

use CommonTest\AbstractTestCase;

/**
 * Integration test for the actual pipeline used by CopIdRef\Job\SyncIdRef:
 * resolve the bundled mapping via Mapper's "module:" reference, then run an
 * IdRef record through Mapper->convert().
 *
 * Together with MapperXmlMappingTest (which only checks JSON validity) this
 * closes the gap left by removing the hardcoded path: it proves the contract
 * with the Mapper module still holds end-to-end.
 */
class MapperIntegrationTest extends AbstractTestCase
{
    /**
     * Minimal IdRef SRU-style record for "Jean Durand", enough to trigger the
     * main extraction paths of the person mapping (200, 103, 340, 900).
     */
    protected string $samplePersonXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<record>
    <leader>001cx  a22001573n 45  </leader>
    <controlfield tag="001">028377788</controlfield>
    <controlfield tag="003">http://www.idref.fr/028377788</controlfield>
    <datafield tag="101" ind1=" " ind2=" "><subfield code="a">fre</subfield></datafield>
    <datafield tag="102" ind1=" " ind2=" "><subfield code="a">FR</subfield></datafield>
    <datafield tag="103" ind1=" " ind2=" ">
        <subfield code="a">19480520</subfield>
        <subfield code="b">        </subfield>
    </datafield>
    <datafield tag="200" ind1="1" ind2=" ">
        <subfield code="a">Durand</subfield>
        <subfield code="b">Jean</subfield>
    </datafield>
    <datafield tag="340" ind1=" " ind2=" "><subfield code="a">Écrivain français contemporain</subfield></datafield>
    <datafield tag="900" ind1=" " ind2=" "><subfield code="a">Jean Durand</subfield></datafield>
</record>
XML;

    public function testMapperResolvesModuleReference(): void
    {
        $mapper = $this->getService('Mapper\Mapper');
        $mapper->setMapping('idref-test-personne', 'module:unimarc/unimarc.idref_personne.json');

        $mapping = $mapper->getMapping();
        $this->assertIsArray($mapping, 'Mapper should load the bundled mapping via the "module:" reference, without CopIdRef knowing the filesystem path.');
        $this->assertNotEmpty($mapping, 'Resolved mapping must be non-empty.');
    }

    public function testMapperResolvesAllBundledMappings(): void
    {
        $mapper = $this->getService('Mapper\Mapper');
        foreach ([
            'unimarc/unimarc.idref_personne.json',
            'unimarc/unimarc.idref_collectivites.json',
            'unimarc/unimarc.idref_autre.json',
        ] as $file) {
            $mapper->setMapping('idref-' . $file, 'module:' . $file);
            $this->assertIsArray($mapper->getMapping(), sprintf('Mapper should resolve "module:%s".', $file));
        }
    }

    public function testMapperFlagsErrorForUnknownReference(): void
    {
        $mapper = $this->getService('Mapper\Mapper');
        $mapper->setMapping('idref-missing', 'module:unimarc/does-not-exist.json');
        $mapping = $mapper->getMapping();
        // Mapper returns a stub mapping flagged as errored rather than null;
        // SyncIdRef's error branch should rely on this flag (or on hasError()).
        $this->assertIsArray($mapping);
        $this->assertTrue(
            !empty($mapping['has_error']) || $mapper->getMapperConfig()->hasError(),
            'A missing "module:" reference must surface as a parsing error so the caller can log and skip the record.'
        );
    }

    public function testConvertRunsWithoutErrorOnPersonRecord(): void
    {
        $mapper = $this->getService('Mapper\Mapper');
        $mapper->setMapping('idref-personne', 'module:unimarc/unimarc.idref_personne.json');
        $this->assertIsArray($mapper->getMapping(), 'Mapping must be resolved before convert.');

        $xml = simplexml_load_string($this->samplePersonXml);
        $this->assertNotFalse($xml, 'XML fixture must parse.');

        // Smoke-test the pipeline: the call must not throw and must return an
        // array. We intentionally do NOT assert on the contents because the
        // unimarc.idref_personne.json layout is owned by the Mapper module and
        // may evolve; the goal here is to confirm CopIdRef -> Mapper wiring
        // (service lookup, "module:" resolution, convert() contract) still
        // holds end-to-end.
        $converted = $mapper->convert($xml);
        $this->assertIsArray($converted);
    }
}
