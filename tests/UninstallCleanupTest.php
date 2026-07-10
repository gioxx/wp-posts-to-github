<?php

namespace POTOGH\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class FakeWpdb
{
    public string $postmeta = 'wp_postmeta';
    public string $usermeta = 'wp_usermeta';

    /** @var string[] */
    public array $queries = [];

    public function query(string $sql): void
    {
        $this->queries[] = $sql;
    }

    public function prepare(string $query, ...$args): string
    {
        return vsprintf(str_replace('%s', '%s', $query), $args);
    }
}

class UninstallCleanupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        require_once __DIR__ . '/../includes/functions.php';
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        unset($GLOBALS['wpdb']);
        parent::tearDown();
    }

    public function test_uninstall_cleanup_skips_when_disabled(): void
    {
        Functions\when('get_option')->justReturn(['cleanup_on_uninstall' => false]);
        Functions\when('wp_parse_args')->alias(function (array $args, array $defaults) {
            return array_merge($defaults, $args);
        });
        Functions\expect('delete_option')->never();

        \POTOGH\uninstall_cleanup();

        $this->assertTrue(true);
    }

    public function test_uninstall_cleanup_removes_options_and_meta_when_enabled(): void
    {
        $wpdb = new FakeWpdb();
        $GLOBALS['wpdb'] = $wpdb;

        Functions\when('get_option')->justReturn(['cleanup_on_uninstall' => true]);
        Functions\when('wp_parse_args')->alias(function (array $args, array $defaults) {
            return array_merge($defaults, $args);
        });
        Functions\expect('delete_option')->once()->with('potogh_settings');

        \POTOGH\uninstall_cleanup();

        $this->assertCount(2, $wpdb->queries);
        $this->assertStringContainsString('wp_postmeta', $wpdb->queries[0]);
        $this->assertStringContainsString('_potogh_path', $wpdb->queries[0]);
        $this->assertStringContainsString('wp_usermeta', $wpdb->queries[1]);
        $this->assertStringContainsString('potogh_export_per_page', $wpdb->queries[1]);
    }
}
