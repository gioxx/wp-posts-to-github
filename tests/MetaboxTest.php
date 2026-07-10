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
        $this->assertSame('Never exported', Metabox::statusLabel(ExportStatus::NEVER_EXPORTED, null));
    }

    public function test_status_label_exported_formats_date_in_site_timezone(): void
    {
        Functions\when('get_option')->alias(function ($name) {
            return $name === 'date_format' ? 'Y-m-d' : 'H:i';
        });
        Functions\expect('wp_date')
            ->once()
            ->with('Y-m-d H:i', strtotime('2026-07-08T11:00:00+00:00'))
            ->andReturn('2026-07-08 13:00');

        $label = Metabox::statusLabel(ExportStatus::EXPORTED, '2026-07-08T11:00:00+00:00');

        $this->assertSame('Exported on 2026-07-08 13:00', $label);
    }

    public function test_status_label_exported_falls_back_to_raw_value_on_unparseable_date(): void
    {
        $label = Metabox::statusLabel(ExportStatus::EXPORTED, 'not-a-date');

        $this->assertSame('Exported on not-a-date', $label);
    }

    public function test_status_label_modified_since_export(): void
    {
        $this->assertSame(
            'Modified since last export',
            Metabox::statusLabel(ExportStatus::MODIFIED_SINCE_EXPORT, '2026-07-08T09:00:00+00:00')
        );
    }
}
