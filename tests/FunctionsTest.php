<?php

namespace POTOGH\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class FunctionsTest extends TestCase
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
        parent::tearDown();
    }

    public function test_post_to_export_data_maps_wp_post_fields(): void
    {
        $post = new \WP_Post();
        $post->ID = 1234;
        $post->post_name = 'come-configurare-wordpress';
        $post->post_content = '<p>Corpo</p>';
        $post->post_date_gmt = '2026-07-08 08:30:00';

        Functions\when('get_the_title')->justReturn('Come configurare WordPress');
        Functions\when('get_the_category')->justReturn([(object) ['name' => 'WordPress']]);
        Functions\when('get_the_tags')->justReturn([(object) ['name' => 'plugin']]);
        Functions\when('wp_list_pluck')->alias(function (array $items, string $field) {
            return array_map(function ($item) use ($field) {
                return $item->$field;
            }, $items);
        });
        Functions\when('get_permalink')->justReturn('https://tuosito.it/come-configurare-wordpress/');
        Functions\when('get_post_time')->justReturn('2026-07-08T10:30:00+02:00');
        Functions\when('get_post_modified_time')->justReturn('2026-07-08T11:00:00+02:00');
        Functions\when('apply_filters')->justReturn('<p>Corpo</p>');
        Functions\when('get_post_meta')->justReturn('');

        $data = \POTOGH\post_to_export_data($post);

        $this->assertSame(1234, $data['wp_id']);
        $this->assertSame('come-configurare-wordpress', $data['slug']);
        $this->assertSame(['WordPress'], $data['categories']);
        $this->assertSame(['plugin'], $data['tags']);
        $this->assertNull($data['existing_path']);
        $this->assertNull($data['existing_sha']);
    }

    public function test_export_post_by_id_returns_error_when_post_not_found(): void
    {
        Functions\when('get_post')->justReturn(null);
        Functions\when('__')->returnArg(1);

        $result = \POTOGH\export_post_by_id(999);

        $this->assertFalse($result['success']);
    }
}
