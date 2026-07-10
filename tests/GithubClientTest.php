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
        Functions\when('__')->returnArg(1);

        $client = new GithubClient('token', 'owner/repo', 'main');
        $result = $client->putFile('posts/2026/my-post.md', '# Hello', 'Export post: Hello (#1)', 'stale-sha');

        $this->assertSame(
            [
                'success' => false,
                'error' => 'sha does not match (the file may have been modified directly on GitHub: check the repository contents before re-exporting)',
                'status' => 409,
            ],
            $result
        );
    }

    public function test_get_file_returns_null_when_response_is_wp_error(): void
    {
        Functions\expect('wp_remote_get')->once()->andReturn(new \stdClass());
        Functions\when('is_wp_error')->justReturn(true);

        $client = new GithubClient('token', 'owner/repo', 'main');

        $this->assertNull($client->getFile('posts/2026/my-post.md'));
    }

    public function test_put_file_returns_error_when_response_is_wp_error(): void
    {
        Functions\when('wp_json_encode')->alias(function ($data) {
            return json_encode($data);
        });

        $wpError = new class {
            public function get_error_message(): string
            {
                return 'Could not resolve host';
            }
        };

        Functions\expect('wp_remote_request')->once()->andReturn($wpError);
        Functions\when('is_wp_error')->justReturn(true);

        $client = new GithubClient('token', 'owner/repo', 'main');
        $result = $client->putFile('posts/2026/my-post.md', '# Hello', 'Export post: Hello (#1)');

        $this->assertSame(
            ['success' => false, 'error' => 'Could not resolve host', 'status' => null],
            $result
        );
    }

    public function test_get_file_request_uses_correct_url_and_headers(): void
    {
        Functions\expect('wp_remote_get')
            ->once()
            ->with(
                \Mockery::on(function ($url) {
                    return strpos(
                        $url,
                        'https://api.github.com/repos/owner/repo/contents/posts/2026/my-post.md'
                    ) === 0
                        && strpos($url, 'ref=main') !== false;
                }),
                \Mockery::on(function ($args) {
                    return isset($args['headers']['Authorization'])
                        && $args['headers']['Authorization'] === 'Bearer token'
                        && $args['headers']['Accept'] === 'application/vnd.github+json';
                })
            )
            ->andReturn(['response' => ['code' => 200]]);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode(['sha' => 'abc123']));

        $client = new GithubClient('token', 'owner/repo', 'main');

        $this->assertSame(['sha' => 'abc123'], $client->getFile('posts/2026/my-post.md'));
    }

    public function test_test_connection_succeeds_when_repo_and_branch_are_reachable(): void
    {
        Functions\when('__')->returnArg(1);
        Functions\expect('wp_remote_get')->twice()->andReturn(['response' => ['code' => 200]]);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->justReturn('{}');

        $client = new GithubClient('token', 'owner/repo', 'main');
        $result = $client->testConnection();

        $this->assertTrue($result['success']);
    }

    public function test_test_connection_fails_on_invalid_token(): void
    {
        Functions\when('__')->returnArg(1);
        Functions\expect('wp_remote_get')->once()->andReturn(['response' => ['code' => 401]]);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(401);

        $client = new GithubClient('bad-token', 'owner/repo', 'main');
        $result = $client->testConnection();

        $this->assertFalse($result['success']);
        $this->assertSame('Invalid GitHub token.', $result['message']);
    }

    public function test_test_connection_fails_when_branch_not_found(): void
    {
        Functions\when('__')->returnArg(1);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\expect('wp_remote_get')
            ->twice()
            ->andReturn(['response' => ['code' => 200]], ['response' => ['code' => 404]]);
        Functions\when('wp_remote_retrieve_response_code')->alias(function ($response) {
            return $response['response']['code'];
        });
        Functions\when('wp_remote_retrieve_body')->justReturn('{}');

        $client = new GithubClient('token', 'owner/repo', 'missing-branch');
        $result = $client->testConnection();

        $this->assertFalse($result['success']);
        $this->assertSame('Branch "missing-branch" not found in repository.', $result['message']);
    }

    public function test_put_file_request_uses_put_method_and_correct_url(): void
    {
        Functions\when('wp_json_encode')->alias(function ($data) {
            return json_encode($data);
        });

        Functions\expect('wp_remote_request')
            ->once()
            ->with(
                \Mockery::on(function ($url) {
                    return strpos(
                        $url,
                        'https://api.github.com/repos/owner/repo/contents/posts/2026/my-post.md'
                    ) === 0
                        && strpos($url, '?ref=') === false;
                }),
                \Mockery::on(function ($args) {
                    return isset($args['method']) && $args['method'] === 'PUT';
                })
            )
            ->andReturn(['response' => ['code' => 200]]);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode(['content' => ['sha' => 'def456']]));

        $client = new GithubClient('token', 'owner/repo', 'main');
        $result = $client->putFile('posts/2026/my-post.md', '# Hello', 'Export post: Hello (#1)');

        $this->assertSame(['success' => true, 'sha' => 'def456'], $result);
    }
}
