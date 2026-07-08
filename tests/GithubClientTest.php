<?php

namespace POTOGH\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use POTOGH\GithubClient;

class GithubClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_get_file_returns_null_on_404(): void
    {
        Functions\when('wp_remote_get')->justReturn(['response' => ['code' => 404]]);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(404);
        Functions\when('wp_remote_retrieve_body')->justReturn('{}');

        $client = new GithubClient('token', 'owner/repo', 'main');

        $this->assertNull($client->getFile('posts/2026/my-post.md'));
    }

    public function test_get_file_returns_sha_when_found(): void
    {
        Functions\when('wp_remote_get')->justReturn(['response' => ['code' => 200]]);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode(['sha' => 'abc123']));

        $client = new GithubClient('token', 'owner/repo', 'main');

        $this->assertSame(['sha' => 'abc123'], $client->getFile('posts/2026/my-post.md'));
    }

    public function test_put_file_returns_success_with_new_sha(): void
    {
        Functions\when('wp_json_encode')->alias(function ($data) {
            return json_encode($data);
        });
        Functions\when('wp_remote_request')->justReturn(['response' => ['code' => 200]]);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode(['content' => ['sha' => 'def456']]));

        $client = new GithubClient('token', 'owner/repo', 'main');
        $result = $client->putFile('posts/2026/my-post.md', '# Hello', 'Export post: Hello (#1)');

        $this->assertSame(['success' => true, 'sha' => 'def456'], $result);
    }

    public function test_put_file_returns_error_on_failure_status(): void
    {
        Functions\when('wp_json_encode')->alias(function ($data) {
            return json_encode($data);
        });
        Functions\when('wp_remote_request')->justReturn(['response' => ['code' => 409]]);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(409);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode(['message' => 'sha does not match']));

        $client = new GithubClient('token', 'owner/repo', 'main');
        $result = $client->putFile('posts/2026/my-post.md', '# Hello', 'Export post: Hello (#1)', 'stale-sha');

        $this->assertSame(
            ['success' => false, 'error' => 'sha does not match', 'status' => 409],
            $result
        );
    }
}
