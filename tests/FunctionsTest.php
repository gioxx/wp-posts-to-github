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

    public function test_export_post_by_id_rejects_non_published_post(): void
    {
        $post = new \WP_Post();
        $post->ID = 42;
        $post->post_status = 'draft';
        $post->post_type = 'post';

        Functions\when('get_post')->justReturn($post);
        Functions\when('__')->returnArg(1);
        Functions\expect('update_post_meta')->never();

        $result = \POTOGH\export_post_by_id(42);

        $this->assertFalse($result['success']);
    }

    public function test_export_post_by_id_does_not_write_meta_when_export_service_fails(): void
    {
        $post = new \WP_Post();
        $post->ID = 55;
        $post->post_name = 'post-in-conflitto';
        $post->post_content = '<p>Corpo</p>';
        $post->post_date_gmt = '2026-07-08 08:30:00';
        $post->post_status = 'publish';
        $post->post_type = 'post';

        Functions\when('get_post')->justReturn($post);
        Functions\when('__')->returnArg(1);

        // Settings::get() dependencies.
        Functions\when('get_option')->justReturn([
            'token' => 'ghp_test',
            'owner_repo' => 'gioxx/blog',
            'branch' => 'main',
            'base_folder' => 'posts',
        ]);
        Functions\when('wp_parse_args')->alias(function (array $args, array $defaults) {
            return array_merge($defaults, $args);
        });

        // post_to_export_data() dependencies.
        Functions\when('get_the_title')->justReturn('Post in conflitto');
        Functions\when('get_the_category')->justReturn([]);
        Functions\when('get_the_tags')->justReturn([]);
        Functions\when('wp_list_pluck')->justReturn([]);
        Functions\when('get_permalink')->justReturn('https://tuosito.it/post-in-conflitto/');
        Functions\when('get_post_time')->justReturn('2026-07-08T10:30:00+02:00');
        Functions\when('get_post_modified_time')->justReturn('2026-07-08T11:00:00+02:00');
        Functions\when('apply_filters')->justReturn('<p>Corpo</p>');
        Functions\when('get_post_meta')->justReturn('');

        // GithubClient::putFile() dependencies: force a failure response.
        Functions\when('wp_json_encode')->alias(function ($data) {
            return json_encode($data);
        });
        Functions\when('wp_remote_request')->justReturn(['response' => ['code' => 409]]);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(409);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode(['message' => 'sha does not match']));

        Functions\expect('update_post_meta')->never();

        $result = \POTOGH\export_post_by_id(55);

        $this->assertFalse($result['success']);
    }

    public function test_schedule_auto_export_skips_when_not_a_new_publish(): void
    {
        $post = new \WP_Post();
        $post->ID = 1;
        $post->post_type = 'post';

        Functions\expect('wp_schedule_single_event')->never();

        \POTOGH\schedule_auto_export('draft', 'draft', $post);
        \POTOGH\schedule_auto_export('publish', 'publish', $post);

        $this->assertConditionsMet();
    }

    public function test_schedule_auto_export_skips_non_post_types(): void
    {
        $post = new \WP_Post();
        $post->ID = 1;
        $post->post_type = 'page';

        Functions\expect('wp_schedule_single_event')->never();

        \POTOGH\schedule_auto_export('publish', 'draft', $post);

        $this->assertConditionsMet();
    }

    public function test_schedule_auto_export_skips_when_option_disabled(): void
    {
        $post = new \WP_Post();
        $post->ID = 7;
        $post->post_type = 'post';

        Functions\when('get_option')->justReturn(['auto_export' => false]);
        Functions\when('wp_parse_args')->alias(function (array $args, array $defaults) {
            return array_merge($defaults, $args);
        });
        Functions\expect('wp_schedule_single_event')->never();

        \POTOGH\schedule_auto_export('publish', 'draft', $post);

        $this->assertConditionsMet();
    }

    public function test_schedule_auto_export_schedules_event_for_new_publish(): void
    {
        $post = new \WP_Post();
        $post->ID = 9;
        $post->post_type = 'post';

        Functions\when('get_option')->justReturn(['auto_export' => true]);
        Functions\when('wp_parse_args')->alias(function (array $args, array $defaults) {
            return array_merge($defaults, $args);
        });
        Functions\expect('wp_schedule_single_event')
            ->once()
            ->with(\Mockery::type('int'), 'potogh_auto_export_event', [9]);

        \POTOGH\schedule_auto_export('publish', 'draft', $post);

        $this->assertConditionsMet();
    }

    public function test_schedule_auto_reexport_skips_when_not_already_published(): void
    {
        $before = new \WP_Post();
        $before->post_status = 'draft';
        $after = new \WP_Post();
        $after->post_type = 'post';
        $after->post_status = 'publish';

        Functions\when('wp_is_post_autosave')->justReturn(false);
        Functions\when('wp_is_post_revision')->justReturn(false);
        Functions\expect('wp_schedule_single_event')->never();

        \POTOGH\schedule_auto_reexport(9, $after, $before);

        $this->assertConditionsMet();
    }

    public function test_schedule_auto_reexport_skips_autosaves_and_revisions(): void
    {
        $before = new \WP_Post();
        $before->post_status = 'publish';
        $after = new \WP_Post();
        $after->post_type = 'post';
        $after->post_status = 'publish';

        Functions\when('wp_is_post_autosave')->justReturn(true);
        Functions\when('wp_is_post_revision')->justReturn(false);
        Functions\expect('wp_schedule_single_event')->never();

        \POTOGH\schedule_auto_reexport(9, $after, $before);

        $this->assertConditionsMet();
    }

    public function test_schedule_auto_reexport_skips_when_option_disabled(): void
    {
        $before = new \WP_Post();
        $before->post_status = 'publish';
        $after = new \WP_Post();
        $after->post_type = 'post';
        $after->post_status = 'publish';

        Functions\when('wp_is_post_autosave')->justReturn(false);
        Functions\when('wp_is_post_revision')->justReturn(false);
        Functions\when('get_option')->justReturn(['auto_reexport' => false]);
        Functions\when('wp_parse_args')->alias(function (array $args, array $defaults) {
            return array_merge($defaults, $args);
        });
        Functions\expect('wp_schedule_single_event')->never();

        \POTOGH\schedule_auto_reexport(9, $after, $before);

        $this->assertConditionsMet();
    }

    public function test_schedule_auto_reexport_schedules_event_when_enabled(): void
    {
        $before = new \WP_Post();
        $before->post_status = 'publish';
        $after = new \WP_Post();
        $after->post_type = 'post';
        $after->post_status = 'publish';

        Functions\when('wp_is_post_autosave')->justReturn(false);
        Functions\when('wp_is_post_revision')->justReturn(false);
        Functions\when('get_option')->justReturn(['auto_reexport' => true]);
        Functions\when('wp_parse_args')->alias(function (array $args, array $defaults) {
            return array_merge($defaults, $args);
        });
        Functions\expect('wp_schedule_single_event')
            ->once()
            ->with(\Mockery::type('int'), 'potogh_auto_export_event', [9]);

        \POTOGH\schedule_auto_reexport(9, $after, $before);

        $this->assertConditionsMet();
    }

    public function test_run_auto_export_skips_when_option_disabled(): void
    {
        Functions\when('get_option')->justReturn(['auto_export' => false]);
        Functions\when('wp_parse_args')->alias(function (array $args, array $defaults) {
            return array_merge($defaults, $args);
        });
        Functions\expect('get_post')->never();

        \POTOGH\run_auto_export(9);

        $this->assertConditionsMet();
    }

    public function test_run_auto_export_skips_when_not_configured(): void
    {
        Functions\when('get_option')->justReturn(['auto_export' => true, 'token' => '', 'owner_repo' => '']);
        Functions\when('wp_parse_args')->alias(function (array $args, array $defaults) {
            return array_merge($defaults, $args);
        });
        Functions\expect('get_post')->never();

        \POTOGH\run_auto_export(9);

        $this->assertConditionsMet();
    }

    public function test_run_auto_export_exports_when_enabled_and_configured(): void
    {
        Functions\when('get_option')->justReturn([
            'auto_export' => true,
            'token' => 'ghp_test',
            'owner_repo' => 'gioxx/blog',
        ]);
        Functions\when('wp_parse_args')->alias(function (array $args, array $defaults) {
            return array_merge($defaults, $args);
        });
        Functions\when('__')->returnArg(1);
        Functions\expect('get_post')->once()->andReturn(null);

        \POTOGH\run_auto_export(9);

        $this->assertConditionsMet();
    }

    private function assertConditionsMet(): void
    {
        $this->assertTrue(true);
    }
}
