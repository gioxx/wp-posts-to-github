<?php

namespace POTOGH\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use POTOGH\TaxonomyBackup;

class TaxonomyBackupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        require_once __DIR__ . '/../includes/TaxonomyBackup.php';
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    private function stubTerms(): void
    {
        $parent = (object) ['term_id' => 1, 'slug' => 'news', 'name' => 'News'];
        $child = (object) [
            'term_id' => 2,
            'slug' => 'wordpress',
            'name' => 'WordPress',
            'description' => 'WP-related posts',
            'parent' => 1,
            'count' => 5,
        ];
        $topLevel = (object) [
            'term_id' => 1,
            'slug' => 'news',
            'name' => 'News',
            'description' => '',
            'parent' => 0,
            'count' => 3,
        ];

        Functions\when('get_categories')->justReturn([$topLevel, $child]);
        Functions\when('get_category')->alias(function ($id) use ($parent) {
            return $id === 1 ? $parent : null;
        });

        $tag = (object) ['name' => 'plugin', 'slug' => 'plugin', 'description' => '', 'count' => 7];
        Functions\when('get_tags')->justReturn([$tag]);

        Functions\when('wp_json_encode')->alias(function ($data, $options = 0) {
            return json_encode($data, $options);
        });
    }

    public function test_build_categories_data_maps_parent_slug(): void
    {
        $this->stubTerms();

        $data = TaxonomyBackup::buildCategoriesData();

        $this->assertSame([
            ['name' => 'News', 'slug' => 'news', 'description' => '', 'parent' => null, 'count' => 3],
            ['name' => 'WordPress', 'slug' => 'wordpress', 'description' => 'WP-related posts', 'parent' => 'news', 'count' => 5],
        ], $data);
    }

    public function test_build_tags_data_maps_fields(): void
    {
        $this->stubTerms();

        $data = TaxonomyBackup::buildTagsData();

        $this->assertSame([
            ['name' => 'plugin', 'slug' => 'plugin', 'description' => '', 'count' => 7],
        ], $data);
    }

    public function test_pending_summary_reports_no_changes_when_hash_matches(): void
    {
        $this->stubTerms();

        $hash = md5(TaxonomyBackup::encode(TaxonomyBackup::buildCategoriesData()) . TaxonomyBackup::encode(TaxonomyBackup::buildTagsData()));

        Functions\when('get_option')->justReturn([
            'hash' => $hash,
            'categories' => ['news', 'wordpress'],
            'tags' => ['plugin'],
            'updated_at' => '2026-07-01T00:00:00+00:00',
        ]);
        Functions\when('wp_list_pluck')->alias(function (array $items, string $field) {
            return array_map(function ($item) use ($field) {
                return $item[$field];
            }, $items);
        });

        $summary = TaxonomyBackup::pendingSummary();

        $this->assertFalse($summary['has_changes']);
        $this->assertSame(0, $summary['categories_added']);
        $this->assertSame(0, $summary['tags_added']);
    }

    public function test_pending_summary_reports_added_and_removed_terms(): void
    {
        $this->stubTerms();

        Functions\when('get_option')->justReturn([
            'hash' => 'stale-hash',
            'categories' => ['news'],
            'tags' => ['old-tag'],
            'updated_at' => '2026-07-01T00:00:00+00:00',
        ]);
        Functions\when('wp_list_pluck')->alias(function (array $items, string $field) {
            return array_map(function ($item) use ($field) {
                return $item[$field];
            }, $items);
        });

        $summary = TaxonomyBackup::pendingSummary();

        $this->assertTrue($summary['has_changes']);
        $this->assertSame(1, $summary['categories_added']);
        $this->assertSame(0, $summary['categories_removed']);
        $this->assertSame(1, $summary['tags_added']);
        $this->assertSame(1, $summary['tags_removed']);
    }

    public function test_commit_updates_option_on_success(): void
    {
        $this->stubTerms();

        Functions\when('get_option')->justReturn([
            'token' => 'ghp_test',
            'owner_repo' => 'gioxx/blog',
            'branch' => 'main',
        ]);
        Functions\when('wp_parse_args')->alias(function (array $args, array $defaults) {
            return array_merge($defaults, $args);
        });
        Functions\when('wp_list_pluck')->alias(function (array $items, string $field) {
            return array_map(function ($item) use ($field) {
                return $item[$field];
            }, $items);
        });
        Functions\when('is_wp_error')->justReturn(false);

        Functions\expect('wp_remote_get')
            ->twice()
            ->andReturn(
                ['response' => ['code' => 200], 'body_json' => ['object' => ['sha' => 'parent-sha']]],
                ['response' => ['code' => 200], 'body_json' => ['tree' => ['sha' => 'base-tree-sha']]]
            );

        Functions\expect('wp_remote_request')
            ->times(3)
            ->andReturn(
                [
                    'response' => ['code' => 201],
                    'body_json' => [
                        'sha' => 'new-tree-sha',
                        'tree' => [
                            ['path' => TaxonomyBackup::CATEGORIES_PATH, 'sha' => 'blob-categories'],
                            ['path' => TaxonomyBackup::TAGS_PATH, 'sha' => 'blob-tags'],
                        ],
                    ],
                ],
                ['response' => ['code' => 201], 'body_json' => ['sha' => 'new-commit-sha']],
                ['response' => ['code' => 200], 'body_json' => []]
            );

        Functions\when('wp_remote_retrieve_response_code')->alias(function ($response) {
            return $response['response']['code'];
        });
        Functions\when('wp_remote_retrieve_body')->alias(function ($response) {
            return json_encode($response['body_json']);
        });

        Functions\expect('update_option')->once()->with(
            TaxonomyBackup::OPTION_NAME,
            \Mockery::on(function ($value) {
                return $value['hash'] !== ''
                    && $value['categories'] === ['news', 'wordpress']
                    && $value['tags'] === ['plugin'];
            }),
            false
        );

        $result = TaxonomyBackup::commit();

        $this->assertTrue($result['success']);
        $this->assertSame('new-commit-sha', $result['commit_sha']);
    }

    public function test_commit_does_not_update_option_on_failure(): void
    {
        $this->stubTerms();

        Functions\when('get_option')->justReturn([
            'token' => 'ghp_test',
            'owner_repo' => 'gioxx/blog',
            'branch' => 'main',
        ]);
        Functions\when('wp_parse_args')->alias(function (array $args, array $defaults) {
            return array_merge($defaults, $args);
        });
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('__')->returnArg(1);

        Functions\expect('wp_remote_get')
            ->once()
            ->andReturn(['response' => ['code' => 500], 'body_json' => ['message' => 'Server error']]);
        Functions\expect('wp_remote_request')->never();
        Functions\expect('update_option')->never();

        Functions\when('wp_remote_retrieve_response_code')->alias(function ($response) {
            return $response['response']['code'];
        });
        Functions\when('wp_remote_retrieve_body')->alias(function ($response) {
            return json_encode($response['body_json']);
        });

        $result = TaxonomyBackup::commit();

        $this->assertFalse($result['success']);
    }
}
