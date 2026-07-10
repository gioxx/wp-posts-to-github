<?php

namespace POTOGH\Tests;

use PHPUnit\Framework\TestCase;
use POTOGH\ExportStatus;
use POTOGH\ExportTab;

class ExportTabTest extends TestCase
{
    public function test_filter_by_status_keeps_only_matching_items(): void
    {
        $items = [
            ['post' => 'a', 'status' => ExportStatus::EXPORTED],
            ['post' => 'b', 'status' => ExportStatus::MODIFIED_SINCE_EXPORT],
            ['post' => 'c', 'status' => ExportStatus::EXPORTED],
        ];

        $result = ExportTab::filterByStatus($items, ExportStatus::EXPORTED);

        $this->assertSame(
            [
                ['post' => 'a', 'status' => ExportStatus::EXPORTED],
                ['post' => 'c', 'status' => ExportStatus::EXPORTED],
            ],
            $result
        );
    }

    public function test_filter_by_status_returns_empty_when_nothing_matches(): void
    {
        $items = [
            ['post' => 'a', 'status' => ExportStatus::NEVER_EXPORTED],
        ];

        $this->assertSame([], ExportTab::filterByStatus($items, ExportStatus::EXPORTED));
    }

    public function test_paginate_returns_correct_slice(): void
    {
        $items = range(1, 25);

        $this->assertSame(range(1, 10), ExportTab::paginate($items, 1, 10));
        $this->assertSame(range(11, 20), ExportTab::paginate($items, 2, 10));
        $this->assertSame(range(21, 25), ExportTab::paginate($items, 3, 10));
    }

    public function test_paginate_returns_empty_array_past_last_page(): void
    {
        $items = range(1, 5);

        $this->assertSame([], ExportTab::paginate($items, 3, 10));
    }

    public function test_paginate_clamps_page_and_per_page_to_minimum_of_one(): void
    {
        $items = range(1, 5);

        $this->assertSame([1], ExportTab::paginate($items, 0, 0));
    }
}
