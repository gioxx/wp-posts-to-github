<?php

namespace POTOGH\Tests;

use PHPUnit\Framework\TestCase;
use POTOGH\ExportStatus;

class ExportStatusTest extends TestCase
{
    public function test_never_exported_when_no_exported_at(): void
    {
        $this->assertSame(
            ExportStatus::NEVER_EXPORTED,
            ExportStatus::determine(null, '2026-07-08 10:00:00')
        );
    }

    public function test_never_exported_when_exported_at_is_empty_string(): void
    {
        $this->assertSame(
            ExportStatus::NEVER_EXPORTED,
            ExportStatus::determine('', '2026-07-08 10:00:00')
        );
    }

    public function test_exported_when_modified_before_export(): void
    {
        $this->assertSame(
            ExportStatus::EXPORTED,
            ExportStatus::determine('2026-07-08T12:00:00+00:00', '2026-07-08 10:00:00')
        );
    }

    public function test_modified_since_export_when_modified_after_export(): void
    {
        $this->assertSame(
            ExportStatus::MODIFIED_SINCE_EXPORT,
            ExportStatus::determine('2026-07-08T09:00:00+00:00', '2026-07-08 10:00:00')
        );
    }
}
