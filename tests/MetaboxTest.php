<?php

namespace POTOGH\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use POTOGH\ExportStatus;
use POTOGH\Metabox;

class MetaboxTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when('__')->returnArg(1);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_status_label_never_exported(): void
    {
        $this->assertSame('Mai esportato', Metabox::statusLabel(ExportStatus::NEVER_EXPORTED, null));
    }

    public function test_status_label_exported_includes_date(): void
    {
        $label = Metabox::statusLabel(ExportStatus::EXPORTED, '2026-07-08T11:00:00+00:00');

        $this->assertStringContainsString('Esportato il', $label);
        $this->assertStringContainsString('2026-07-08T11:00:00+00:00', $label);
    }

    public function test_status_label_modified_since_export(): void
    {
        $this->assertSame(
            "Modificato dopo l'ultima esportazione",
            Metabox::statusLabel(ExportStatus::MODIFIED_SINCE_EXPORT, '2026-07-08T09:00:00+00:00')
        );
    }
}
