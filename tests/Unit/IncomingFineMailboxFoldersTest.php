<?php

namespace Tests\Unit;

use App\Support\IncomingFineMailboxFolders;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class IncomingFineMailboxFoldersTest extends TestCase
{
    public function test_folder_constants_match_mailbox_display_names(): void
    {
        $this->assertSame('VIO pending', IncomingFineMailboxFolders::PENDING);
        $this->assertSame('VIO Processed', IncomingFineMailboxFolders::PROCESSED);
    }

    public function test_processed_alias_matching_is_case_insensitive(): void
    {
        $folders = new IncomingFineMailboxFolders;
        $ref = new ReflectionClass($folders);
        $method = $ref->getMethod('isProcessedFolderName');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($folders, 'VIO Processed'));
        $this->assertTrue($method->invoke($folders, 'VIO processed'));
        $this->assertTrue($method->invoke($folders, 'vio processed'));
        $this->assertFalse($method->invoke($folders, 'VIO pending'));
    }
}
