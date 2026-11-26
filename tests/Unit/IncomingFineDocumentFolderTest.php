<?php

namespace Tests\Unit;

use App\Support\IncomingFineDocumentArchiver;
use PHPUnit\Framework\TestCase;

class IncomingFineDocumentFolderTest extends TestCase
{
    private IncomingFineDocumentArchiver $archiver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->archiver = new class extends IncomingFineDocumentArchiver
        {
            public function baseFolder(): string
            {
                return 'Lithuania/Administration/Policijos administravimas';
            }
        };
    }

    public function test_folder_uses_year_and_reference(): void
    {
        $path = $this->archiver->documentFolderPath('26316319149', '2026-03-15');

        $this->assertSame(
            'Lithuania/Administration/Policijos administravimas/2026/26316319149',
            $path
        );
    }

    public function test_folder_appends_rnt_when_present(): void
    {
        $path = $this->archiver->documentFolderPath('26316319149', '2026-03-15', 'RNT-76970');

        $this->assertSame(
            'Lithuania/Administration/Policijos administravimas/2026/26316319149__RNT-76970',
            $path
        );
    }

    public function test_unsorted_fallback_uses_vehicle(): void
    {
        $path = $this->archiver->documentFolderPath(null, '2025-11-01', null, 'ABC123');

        $this->assertSame(
            'Lithuania/Administration/Policijos administravimas/2025/_unsorted/ABC123',
            $path
        );
    }

    public function test_file_name_includes_driver_reference_and_rnt(): void
    {
        $name = $this->archiver->documentFileName('Jonas Jonaitis', '26316319149', 'pdf', 'RNT-76970');

        $this->assertSame('Jonas Jonaitis 26316319149 RNT-76970.pdf', $name);
    }

    public function test_file_name_strips_path_characters(): void
    {
        $name = $this->archiver->documentFileName('A/B', 'ROIK:1', 'pdf');

        $this->assertSame('A-B ROIK-1.pdf', $name);
    }
}
