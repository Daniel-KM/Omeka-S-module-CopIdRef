<?php declare(strict_types=1);

namespace CopIdRefTest;

use CommonTest\AbstractTestCase;
use DOMDocument;
use DOMXPath;

/**
 * Tests for IdRef XML mapping functionality.
 *
 * These tests verify the XPath queries used for extracting data from IdRef records.
 */
class IdRefMappingTest extends AbstractTestCase
{
    /**
     * Sample IdRef XML for a person.
     */
    protected string $samplePersonXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<record>
    <leader>001cx  a22001573n 45  </leader>
    <controlfield tag="001">028377788</controlfield>
    <controlfield tag="003">http://www.idref.fr/028377788</controlfield>
    <controlfield tag="005">20230915121500.0</controlfield>
    <datafield tag="101" ind1=" " ind2=" ">
        <subfield code="a">fre</subfield>
    </datafield>
    <datafield tag="102" ind1=" " ind2=" ">
        <subfield code="a">FR</subfield>
    </datafield>
    <datafield tag="103" ind1=" " ind2=" ">
        <subfield code="a">19480520</subfield>
        <subfield code="b">        </subfield>
    </datafield>
    <datafield tag="200" ind1="1" ind2=" ">
        <subfield code="a">Durand</subfield>
        <subfield code="b">Jean</subfield>
        <subfield code="c">écrivain</subfield>
    </datafield>
    <datafield tag="340" ind1=" " ind2=" ">
        <subfield code="a">Écrivain français contemporain</subfield>
    </datafield>
    <datafield tag="900" ind1=" " ind2=" ">
        <subfield code="a">Jean Durand</subfield>
    </datafield>
</record>
XML;

    /**
     * Sample IdRef XML for an organization.
     */
    protected string $sampleOrganizationXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<record>
    <leader>001cx  a22001573n 45  </leader>
    <controlfield tag="001">123456789</controlfield>
    <controlfield tag="003">http://www.idref.fr/123456789</controlfield>
    <controlfield tag="005">20230915121500.0</controlfield>
    <datafield tag="101" ind1=" " ind2=" ">
        <subfield code="a">fre</subfield>
    </datafield>
    <datafield tag="102" ind1=" " ind2=" ">
        <subfield code="a">FR</subfield>
    </datafield>
    <datafield tag="103" ind1=" " ind2=" ">
        <subfield code="a">18880101</subfield>
        <subfield code="b">        </subfield>
    </datafield>
    <datafield tag="210" ind1="1" ind2=" ">
        <subfield code="a">Bibliothèque nationale de France</subfield>
    </datafield>
    <datafield tag="340" ind1=" " ind2=" ">
        <subfield code="a">Institution nationale</subfield>
    </datafield>
    <datafield tag="900" ind1=" " ind2=" ">
        <subfield code="a">BnF</subfield>
    </datafield>
</record>
XML;

    /**
     * Test XPath extraction for person name.
     */
    public function testExtractPersonName(): void
    {
        $doc = new DOMDocument();
        $doc->loadXML($this->samplePersonXml);
        $xpath = new DOMXPath($doc);

        // Test: /record/datafield[@tag='900']/subfield[@code='a']
        $nodeList = $xpath->query("/record/datafield[@tag='900']/subfield[@code='a']");
        $this->assertNotNull($nodeList);
        $this->assertGreaterThan(0, $nodeList->length);
        $this->assertSame('Jean Durand', trim($nodeList->item(0)->nodeValue));
    }

    /**
     * Test XPath extraction for family name.
     */
    public function testExtractFamilyName(): void
    {
        $doc = new DOMDocument();
        $doc->loadXML($this->samplePersonXml);
        $xpath = new DOMXPath($doc);

        // Test: /record/datafield[@tag='200']/subfield[@code='a']
        $nodeList = $xpath->query("/record/datafield[@tag='200']/subfield[@code='a']");
        $this->assertNotNull($nodeList);
        $this->assertGreaterThan(0, $nodeList->length);
        $this->assertSame('Durand', trim($nodeList->item(0)->nodeValue));
    }

    /**
     * Test XPath extraction for given name.
     */
    public function testExtractGivenName(): void
    {
        $doc = new DOMDocument();
        $doc->loadXML($this->samplePersonXml);
        $xpath = new DOMXPath($doc);

        // Test: /record/datafield[@tag='200']/subfield[@code='b']
        $nodeList = $xpath->query("/record/datafield[@tag='200']/subfield[@code='b']");
        $this->assertNotNull($nodeList);
        $this->assertGreaterThan(0, $nodeList->length);
        $this->assertSame('Jean', trim($nodeList->item(0)->nodeValue));
    }

    /**
     * Test XPath extraction for birth date in Unimarc format.
     */
    public function testExtractBirthDate(): void
    {
        $doc = new DOMDocument();
        $doc->loadXML($this->samplePersonXml);
        $xpath = new DOMXPath($doc);

        // Test: /record/datafield[@tag='103']/subfield[@code='a'][1]
        $nodeList = $xpath->query("/record/datafield[@tag='103']/subfield[@code='a'][1]");
        $this->assertNotNull($nodeList);
        $this->assertGreaterThan(0, $nodeList->length);
        $this->assertSame('19480520', trim($nodeList->item(0)->nodeValue));
    }

    /**
     * Test number_to_date format conversion.
     */
    public function testNumberToDateFormat(): void
    {
        // Test the format conversion used in Mapper
        $value = '19480520';
        $expected = '1948-05-20';

        $result = $this->convertNumberToDate($value);
        $this->assertSame($expected, $result);

        // Test with negative (BC) date
        $valueBc = '-00500101';
        $expectedBc = '-0050-01-01';
        $resultBc = $this->convertNumberToDate($valueBc);
        $this->assertSame($expectedBc, $resultBc);

        // Test with partial date
        $valuePartial = '1948';
        $expectedPartial = '1948';
        $resultPartial = $this->convertNumberToDate($valuePartial);
        $this->assertSame($expectedPartial, $resultPartial);
    }

    /**
     * Test XPath extraction for language.
     */
    public function testExtractLanguage(): void
    {
        $doc = new DOMDocument();
        $doc->loadXML($this->samplePersonXml);
        $xpath = new DOMXPath($doc);

        // Test: /record/datafield[@tag='101']/subfield[@code='a'][1]
        $nodeList = $xpath->query("/record/datafield[@tag='101']/subfield[@code='a'][1]");
        $this->assertNotNull($nodeList);
        $this->assertGreaterThan(0, $nodeList->length);
        $this->assertSame('fre', trim($nodeList->item(0)->nodeValue));
    }

    /**
     * Test concat format for language URI.
     */
    public function testConcatFormatLanguageUri(): void
    {
        $value = 'fre';
        $prefix = 'http://id.loc.gov/vocabulary/iso639-2/';
        $expected = 'http://id.loc.gov/vocabulary/iso639-2/fre';

        $result = $prefix . $value;
        $this->assertSame($expected, $result);
    }

    /**
     * Test XPath extraction for country code.
     */
    public function testExtractCountryCode(): void
    {
        $doc = new DOMDocument();
        $doc->loadXML($this->samplePersonXml);
        $xpath = new DOMXPath($doc);

        // Test: /record/datafield[@tag='102']/subfield[@code='a'][1]
        $nodeList = $xpath->query("/record/datafield[@tag='102']/subfield[@code='a'][1]");
        $this->assertNotNull($nodeList);
        $this->assertGreaterThan(0, $nodeList->length);
        $this->assertSame('FR', trim($nodeList->item(0)->nodeValue));
    }

    /**
     * Test XPath extraction for biography.
     */
    public function testExtractBiography(): void
    {
        $doc = new DOMDocument();
        $doc->loadXML($this->samplePersonXml);
        $xpath = new DOMXPath($doc);

        // Test: /record/datafield[@tag='340']/subfield[@code='a'][1]
        $nodeList = $xpath->query("/record/datafield[@tag='340']/subfield[@code='a'][1]");
        $this->assertNotNull($nodeList);
        $this->assertGreaterThan(0, $nodeList->length);
        $this->assertSame('Écrivain français contemporain', trim($nodeList->item(0)->nodeValue));
    }

    /**
     * Test XPath extraction for IdRef identifier URI.
     */
    public function testExtractIdRefUri(): void
    {
        $doc = new DOMDocument();
        $doc->loadXML($this->samplePersonXml);
        $xpath = new DOMXPath($doc);

        // Test: /record/controlfield[@tag='003']
        $nodeList = $xpath->query("/record/controlfield[@tag='003']");
        $this->assertNotNull($nodeList);
        $this->assertGreaterThan(0, $nodeList->length);
        $this->assertSame('http://www.idref.fr/028377788', trim($nodeList->item(0)->nodeValue));
    }

    /**
     * Test XPath extraction for position/function.
     */
    public function testExtractPosition(): void
    {
        $doc = new DOMDocument();
        $doc->loadXML($this->samplePersonXml);
        $xpath = new DOMXPath($doc);

        // Test: /record/datafield[@tag='200']/subfield[@code='c'][1]
        $nodeList = $xpath->query("/record/datafield[@tag='200']/subfield[@code='c'][1]");
        $this->assertNotNull($nodeList);
        $this->assertGreaterThan(0, $nodeList->length);
        $this->assertSame('écrivain', trim($nodeList->item(0)->nodeValue));
    }

    /**
     * Helper method to convert number to date (replicates Mapper logic).
     */
    protected function convertNumberToDate(string $value): string
    {
        if (!preg_match('~^[+ -]?[\d]+$~', $value)) {
            return $value;
        }

        $sign = substr($value, 0, 1) === '-' ? '-' : '';
        $value = str_replace(['-', '+', ' '], '', $value);
        $result = rtrim(substr($value, 0, 4) . '-' . substr($value, 4, 2) . '-' . substr($value, 6, 2), '-');
        return $sign . $result;
    }
}
