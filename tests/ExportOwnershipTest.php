<?php

namespace POTOGH\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class ExportOwnershipTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        require_once __DIR__ . '/../includes/functions.php';

        Functions\when('get_post_meta')->alias(function ($postId, $key) {
            if ($key === '_potogh_path') {
                return 'posts/2025/original-post.md';
            }
            if ($key === '_potogh_sha') {
                return 'original-sha';
            }
            return '';
        });
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb']);
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_owned_export_meta_returns_nulls_when_post_has_no_export(): void
    {
        Functions\when('get_post_meta')->justReturn('');
        $GLOBALS['wpdb'] = $wpdb = new FakeMetaOwnerWpdb(null);

        $meta = \POTOGH\owned_export_meta(42);

        $this->assertSame(['path' => null, 'sha' => null], $meta);
        $this->assertSame([], $wpdb->queries);
    }

    public function test_owned_export_meta_keeps_meta_when_post_owns_the_path(): void
    {
        $GLOBALS['wpdb'] = $wpdb = new FakeMetaOwnerWpdb(42);

        $meta = \POTOGH\owned_export_meta(42);

        $this->assertSame(['path' => 'posts/2025/original-post.md', 'sha' => 'original-sha'], $meta);
        $this->assertStringContainsString("'posts/2025/original-post.md'", $wpdb->queries[0]);
    }

    public function test_owned_export_meta_ignores_meta_copied_from_an_older_post(): void
    {
        // Post 100 is a Duplicate Post copy of post 42 and carries its _potogh_* meta.
        $GLOBALS['wpdb'] = new FakeMetaOwnerWpdb(42);

        $meta = \POTOGH\owned_export_meta(100);

        $this->assertSame(['path' => null, 'sha' => null], $meta);
    }

    public function test_delete_export_by_id_refuses_to_delete_a_file_owned_by_another_post(): void
    {
        $GLOBALS['wpdb'] = new FakeMetaOwnerWpdb(42);
        Functions\when('__')->returnArg(1);
        Functions\expect('wp_remote_request')->never();

        $result = \POTOGH\delete_export_by_id(100);

        $this->assertFalse($result['success']);
    }

    public function test_exclude_export_meta_from_duplicates_appends_wildcard(): void
    {
        $this->assertSame(
            ['yasr*', '_potogh_*'],
            \POTOGH\exclude_export_meta_from_duplicates(['yasr*'])
        );
        $this->assertSame(['_potogh_*'], \POTOGH\exclude_export_meta_from_duplicates(null));
    }
}
