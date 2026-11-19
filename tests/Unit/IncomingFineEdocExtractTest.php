<?php

namespace Tests\Unit;

use App\Support\IncomingFineAttachmentTextExtractor;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class IncomingFineEdocExtractTest extends TestCase
{
    public function test_unpacks_asic_edoc_and_reads_inner_document_text(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'edoctest_');
        $zipPath = $tmp.'.edoc.zip';
        @unlink($tmp);

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($zipPath, \ZipArchive::CREATE));
        $zip->addFromString('mimetype', 'application/vnd.etsi.asic-e+zip');
        $zip->addFromString('META-INF/manifest.xml', '<manifest/>');
        $zip->addFromString(
            'decision.txt',
            "Transportlīdzekļa reģistrācijas Nr.: MAB841\nLēmums PZ00160267"
        );
        $zip->close();

        $binary = (string) file_get_contents($zipPath);
        @unlink($zipPath);

        $extractor = new IncomingFineAttachmentTextExtractor;
        $method = new ReflectionMethod($extractor, 'textFromEdoc');
        $method->setAccessible(true);
        $result = $method->invoke($extractor, $binary, null);

        $this->assertSame('edoc', $result['source']);
        $this->assertStringContainsString('MAB841', $result['text']);
    }
}
