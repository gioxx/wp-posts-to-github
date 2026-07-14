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

    public function test_put_file_detects_secondary_rate_limit_via_retry_after_header(): void
    {
        Functions\when('wp_json_encode')->alias(function ($data) {
            return json_encode($data);
        });
        Functions\when('wp_remote_request')->justReturn(['response' => ['code' => 403]]);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(403);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode(['message' => 'You have exceeded a secondary rate limit']));
        Functions\when('wp_remote_retrieve_header')->alias(function ($response, $header) {
            return $header === 'retry-after' ? '30' : '';
        });
        Functions\when('__')->returnArg(1);

        $client = new GithubClient('token', 'owner/repo', 'main');
        $result = $client->putFile('posts/2026/my-post.md', '# Hello', 'Export post: Hello (#1)');

        $this->assertFalse($result['success']);
        $this->assertSame(30, $result['retry_after']);
    }

    public function test_put_file_detects_primary_rate_limit_via_remaining_and_reset_headers(): void
    {
        Functions\when('wp_json_encode')->alias(function ($data) {
            return json_encode($data);
        });
        Functions\when('wp_remote_request')->justReturn(['response' => ['code' => 403]]);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(403);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode(['message' => 'API rate limit exceeded']));

        $resetAt = time() + 45;
        Functions\when('wp_remote_retrieve_header')->alias(function ($response, $header) use ($resetAt) {
            if ($header === 'x-ratelimit-remaining') {
                return '0';
            }
            if ($header === 'x-ratelimit-reset') {
                return (string) $resetAt;
            }
            return '';
        });
        Functions\when('__')->returnArg(1);

        $client = new GithubClient('token', 'owner/repo', 'main');
        $result = $client->putFile('posts/2026/my-post.md', '# Hello', 'Export post: Hello (#1)');

        $this->assertFalse($result['success']);
        $this->assertGreaterThan(0, $result['retry_after']);
        $this->assertLessThanOrEqual(45, $result['retry_after']);
    }

    public function test_put_file_does_not_treat_plain_403_as_rate_limit(): void
    {
        Functions\when('wp_json_encode')->alias(function ($data) {
            return json_encode($data);
        });
        Functions\when('wp_remote_request')->justReturn(['response' => ['code' => 403]]);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(403);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode(['message' => 'Resource not accessible by personal access token']));
        Functions\when('wp_remote_retrieve_header')->justReturn('');
        Functions\when('__')->returnArg(1);

        $client = new GithubClient('token', 'owner/repo', 'main');
        $result = $client->putFile('posts/2026/my-post.md', '# Hello', 'Export post: Hello (#1)');

        $this->assertFalse($result['success']);
        $this->assertArrayNotHasKey('retry_after', $result);
        $this->assertSame('Resource not accessible by personal access token', $result['error']);
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

    public function test_delete_file_returns_success(): void
    {
        Functions\when('__')->returnArg(1);
        Functions\when('wp_json_encode')->alias(function ($data) {
            return json_encode($data);
        });
        Functions\expect('wp_remote_request')
            ->once()
            ->with(
                'https://api.github.com/repos/owner/repo/contents/posts/2026/my-post.md',
                \Mockery::on(function ($args) {
                    $body = json_decode($args['body'], true);

                    return $args['method'] === 'DELETE'
                        && $body['sha'] === 'abc123'
                        && $body['branch'] === 'main';
                })
            )
            ->andReturn(['response' => ['code' => 200]]);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->justReturn('{}');

        $client = new GithubClient('token', 'owner/repo', 'main');
        $result = $client->deleteFile('posts/2026/my-post.md', 'abc123', 'Remove exported post: Hello (#1)');

        $this->assertSame(['success' => true], $result);
    }

    public function test_delete_file_returns_error_on_sha_mismatch(): void
    {
        Functions\when('__')->returnArg(1);
        Functions\when('wp_json_encode')->alias(function ($data) {
            return json_encode($data);
        });
        Functions\when('wp_remote_request')->justReturn(['response' => ['code' => 409]]);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(409);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode(['message' => 'sha does not match']));

        $client = new GithubClient('token', 'owner/repo', 'main');
        $result = $client->deleteFile('posts/2026/my-post.md', 'stale-sha', 'Remove exported post: Hello (#1)');

        $this->assertFalse($result['success']);
        $this->assertSame('sha does not match', $result['error']);
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

    public function test_get_default_branch_returns_branch_from_repo(): void
    {
        Functions\when('__')->returnArg(1);
        Functions\expect('wp_remote_get')->once()->andReturn(['response' => ['code' => 200]]);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode(['default_branch' => 'develop']));

        $client = new GithubClient('token', 'owner/repo', 'main');
        $result = $client->getDefaultBranch();

        $this->assertTrue($result['success']);
        $this->assertSame('develop', $result['branch']);
    }

    public function test_get_default_branch_fails_on_invalid_token(): void
    {
        Functions\when('__')->returnArg(1);
        Functions\expect('wp_remote_get')->once()->andReturn(['response' => ['code' => 401]]);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(401);
        Functions\when('wp_remote_retrieve_body')->justReturn('{}');

        $client = new GithubClient('bad-token', 'owner/repo', 'main');
        $result = $client->getDefaultBranch();

        $this->assertFalse($result['success']);
        $this->assertSame('Invalid GitHub token.', $result['message']);
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

    public function test_commit_files_creates_one_commit_for_multiple_files(): void
    {
        Functions\when('__')->returnArg(1);
        Functions\when('wp_json_encode')->alias(function ($data) {
            return json_encode($data);
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
                            ['path' => 'posts/2026/a.md', 'sha' => 'blob-a'],
                            ['path' => 'posts/2026/b.md', 'sha' => 'blob-b'],
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

        $client = new GithubClient('token', 'owner/repo', 'main');
        $result = $client->commitFiles(
            [
                ['path' => 'posts/2026/a.md', 'content' => '# A'],
                ['path' => 'posts/2026/b.md', 'content' => '# B'],
            ],
            'Bulk export: 2 posts'
        );

        $this->assertTrue($result['success']);
        $this->assertSame('new-commit-sha', $result['commit_sha']);
        $this->assertSame(
            ['posts/2026/a.md' => 'blob-a', 'posts/2026/b.md' => 'blob-b'],
            $result['blob_shas']
        );
    }

    public function test_commit_files_handles_empty_branch_with_no_prior_commits(): void
    {
        Functions\when('__')->returnArg(1);
        Functions\when('wp_json_encode')->alias(function ($data) {
            return json_encode($data);
        });
        Functions\when('is_wp_error')->justReturn(false);

        Functions\expect('wp_remote_get')
            ->once()
            ->andReturn(['response' => ['code' => 404], 'body_json' => []]);

        Functions\expect('wp_remote_request')
            ->times(3)
            ->with(\Mockery::any(), \Mockery::on(function ($args) {
                return true;
            }))
            ->andReturn(
                ['response' => ['code' => 201], 'body_json' => ['sha' => 'new-tree-sha', 'tree' => []]],
                ['response' => ['code' => 201], 'body_json' => ['sha' => 'new-commit-sha']],
                ['response' => ['code' => 201], 'body_json' => []]
            );

        Functions\when('wp_remote_retrieve_response_code')->alias(function ($response) {
            return $response['response']['code'];
        });
        Functions\when('wp_remote_retrieve_body')->alias(function ($response) {
            return json_encode($response['body_json']);
        });

        $client = new GithubClient('token', 'owner/repo', 'main');
        $result = $client->commitFiles([['path' => 'posts/2026/a.md', 'content' => '# A']], 'Bulk export: 1 post');

        $this->assertTrue($result['success']);
        $this->assertSame('new-commit-sha', $result['commit_sha']);
    }

    public function test_commit_files_stops_and_returns_error_when_get_commit_fails(): void
    {
        Functions\when('__')->returnArg(1);
        Functions\when('is_wp_error')->justReturn(false);

        Functions\expect('wp_remote_get')
            ->twice()
            ->andReturn(
                ['response' => ['code' => 200], 'body_json' => ['object' => ['sha' => 'parent-sha']]],
                ['response' => ['code' => 500], 'body_json' => ['message' => 'Server error']]
            );

        Functions\expect('wp_remote_request')->never();

        Functions\when('wp_remote_retrieve_response_code')->alias(function ($response) {
            return $response['response']['code'];
        });
        Functions\when('wp_remote_retrieve_body')->alias(function ($response) {
            return json_encode($response['body_json']);
        });

        $client = new GithubClient('token', 'owner/repo', 'main');
        $result = $client->commitFiles([['path' => 'posts/2026/a.md', 'content' => '# A']], 'Bulk export: 1 post');

        $this->assertFalse($result['success']);
        $this->assertSame('Server error', $result['error']);
    }

    public function test_commit_files_detects_rate_limit_on_tree_creation(): void
    {
        Functions\when('__')->returnArg(1);
        Functions\when('wp_json_encode')->alias(function ($data) {
            return json_encode($data);
        });
        Functions\when('is_wp_error')->justReturn(false);

        Functions\expect('wp_remote_get')
            ->twice()
            ->andReturn(
                ['response' => ['code' => 200], 'body_json' => ['object' => ['sha' => 'parent-sha']]],
                ['response' => ['code' => 200], 'body_json' => ['tree' => ['sha' => 'base-tree-sha']]]
            );

        Functions\expect('wp_remote_request')
            ->once()
            ->andReturn(['response' => ['code' => 403], 'body_json' => ['message' => 'rate limited']]);

        Functions\when('wp_remote_retrieve_response_code')->alias(function ($response) {
            return $response['response']['code'];
        });
        Functions\when('wp_remote_retrieve_body')->alias(function ($response) {
            return json_encode($response['body_json']);
        });
        Functions\when('wp_remote_retrieve_header')->alias(function ($response, $header) {
            return $header === 'retry-after' ? '20' : '';
        });

        $client = new GithubClient('token', 'owner/repo', 'main');
        $result = $client->commitFiles([['path' => 'posts/2026/a.md', 'content' => '# A']], 'Bulk export: 1 post');

        $this->assertFalse($result['success']);
        $this->assertSame(20, $result['retry_after']);
    }
}
