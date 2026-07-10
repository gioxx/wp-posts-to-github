<?php

namespace POTOGH\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use POTOGH\Converter;
use POTOGH\ExportService;
use POTOGH\GithubClient;

class ExportServiceTest extends TestCase
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

    private function samplePostData(array $overrides = []): array
    {
        return array_merge([
            'wp_id' => 1234,
            'title' => 'Come configurare WordPress',
            'slug' => 'come-configurare-wordpress',
            'date' => '2026-07-08T10:30:00+02:00',
            'date_gmt' => '2026-07-08 08:30:00',
            'modified' => '2026-07-08T11:00:00+02:00',
            'categories' => ['WordPress'],
            'tags' => ['plugin'],
            'permalink' => 'https://tuosito.it/come-configurare-wordpress/',
            'content_html' => '<h1>Titolo</h1><p>Corpo</p>',
            'existing_path' => null,
            'existing_sha' => null,
        ], $overrides);
    }

    public function test_exports_new_post_and_returns_path_and_sha(): void
    {
        $githubClient = $this->createMock(GithubClient::class);
        $githubClient->expects($this->once())
            ->method('putFile')
            ->with(
                'posts/2026/come-configurare-wordpress.md',
                $this->stringContains('# Titolo'),
                'Export post: Come configurare WordPress (#1234)',
                null
            )
            ->willReturn(['success' => true, 'sha' => 'new-sha']);

        $service = new ExportService(new Converter(), $githubClient, 'posts');
        $result = $service->exportPost($this->samplePostData());

        $this->assertTrue($result['success']);
        $this->assertSame('posts/2026/come-configurare-wordpress.md', $result['path']);
        $this->assertSame('new-sha', $result['sha']);
        $this->assertNotEmpty($result['trace']);
        $this->assertStringContainsString('posts/2026/come-configurare-wordpress.md', $result['trace'][0]);
        $this->assertStringContainsString('new-sha', end($result['trace']));
    }

    public function test_reexport_uses_existing_path_and_sha(): void
    {
        $githubClient = $this->createMock(GithubClient::class);
        $githubClient->expects($this->once())
            ->method('putFile')
            ->with(
                'posts/2025/old-path.md',
                $this->anything(),
                $this->anything(),
                'existing-sha'
            )
            ->willReturn(['success' => true, 'sha' => 'updated-sha']);

        $service = new ExportService(new Converter(), $githubClient, 'posts');
        $result = $service->exportPost($this->samplePostData([
            'existing_path' => 'posts/2025/old-path.md',
            'existing_sha' => 'existing-sha',
        ]));

        $this->assertSame('posts/2025/old-path.md', $result['path']);
        $this->assertSame('updated-sha', $result['sha']);
    }

    public function test_returns_error_when_github_client_fails(): void
    {
        $githubClient = $this->createMock(GithubClient::class);
        $githubClient->method('putFile')->willReturn([
            'success' => false,
            'error' => 'sha does not match',
            'status' => 409,
        ]);

        $service = new ExportService(new Converter(), $githubClient, 'posts');
        $result = $service->exportPost($this->samplePostData());

        $this->assertFalse($result['success']);
        $this->assertSame('sha does not match', $result['error']);
        $this->assertNotEmpty($result['trace']);
        $this->assertStringContainsString('sha does not match', end($result['trace']));
    }
}
