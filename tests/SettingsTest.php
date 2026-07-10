<?php

namespace POTOGH\Tests;

use PHPUnit\Framework\TestCase;
use POTOGH\Settings;

class SettingsTest extends TestCase
{
    public function test_defaults(): void
    {
        $this->assertSame([
            'token' => '',
            'owner_repo' => '',
            'branch' => 'main',
            'base_folder' => 'posts',
            'auto_export' => false,
            'auto_reexport' => false,
            'cleanup_on_uninstall' => true,
        ], Settings::defaults());
    }

    public function test_sanitize_trims_token_and_keeps_valid_owner_repo(): void
    {
        $result = Settings::sanitize([
            'token' => '  ghp_abc123  ',
            'owner_repo' => 'gioxx/blog-style-corpus',
            'branch' => 'main',
            'base_folder' => '/posts/',
        ]);

        $this->assertSame([
            'token' => 'ghp_abc123',
            'owner_repo' => 'gioxx/blog-style-corpus',
            'branch' => 'main',
            'base_folder' => 'posts',
            'auto_export' => false,
            'auto_reexport' => false,
            'cleanup_on_uninstall' => false,
        ], $result);
    }

    public function test_sanitize_enables_auto_export_when_checkbox_present(): void
    {
        $result = Settings::sanitize([
            'token' => 'ghp_abc123',
            'owner_repo' => 'gioxx/blog-style-corpus',
            'branch' => 'main',
            'base_folder' => 'posts',
            'auto_export' => '1',
        ]);

        $this->assertTrue($result['auto_export']);
    }

    public function test_sanitize_enables_auto_reexport_when_checkbox_present(): void
    {
        $result = Settings::sanitize([
            'token' => 'ghp_abc123',
            'owner_repo' => 'gioxx/blog-style-corpus',
            'branch' => 'main',
            'base_folder' => 'posts',
            'auto_reexport' => '1',
        ]);

        $this->assertTrue($result['auto_reexport']);
    }

    public function test_sanitize_disables_cleanup_on_uninstall_when_checkbox_absent(): void
    {
        $result = Settings::sanitize([
            'token' => 'ghp_abc123',
            'owner_repo' => 'gioxx/blog-style-corpus',
            'branch' => 'main',
            'base_folder' => 'posts',
        ]);

        $this->assertFalse($result['cleanup_on_uninstall']);
    }

    public function test_sanitize_rejects_invalid_owner_repo_format(): void
    {
        $result = Settings::sanitize([
            'token' => 'ghp_abc123',
            'owner_repo' => 'not-a-valid-owner-repo',
            'branch' => 'main',
            'base_folder' => 'posts',
        ]);

        $this->assertSame('', $result['owner_repo']);
    }

    public function test_sanitize_falls_back_to_defaults_for_empty_branch_and_folder(): void
    {
        $result = Settings::sanitize([
            'token' => 'ghp_abc123',
            'owner_repo' => 'gioxx/blog-style-corpus',
            'branch' => '',
            'base_folder' => '',
        ]);

        $this->assertSame('main', $result['branch']);
        $this->assertSame('posts', $result['base_folder']);
    }

    /**
     * @dataProvider githubUrlProvider
     */
    public function test_sanitize_extracts_owner_repo_from_github_url(string $input): void
    {
        $result = Settings::sanitize([
            'token' => 'ghp_abc123',
            'owner_repo' => $input,
            'branch' => 'main',
            'base_folder' => 'posts',
        ]);

        $this->assertSame('gioxx/blog-style-corpus', $result['owner_repo']);
    }

    public static function githubUrlProvider(): array
    {
        return [
            'plain owner/repo' => ['gioxx/blog-style-corpus'],
            'https url' => ['https://github.com/gioxx/blog-style-corpus'],
            'http url' => ['http://github.com/gioxx/blog-style-corpus'],
            'url with .git suffix' => ['https://github.com/gioxx/blog-style-corpus.git'],
            'url with trailing slash' => ['https://github.com/gioxx/blog-style-corpus/'],
            'url with www' => ['https://www.github.com/gioxx/blog-style-corpus'],
        ];
    }

    public function test_sanitize_rejects_non_github_url(): void
    {
        $result = Settings::sanitize([
            'token' => 'ghp_abc123',
            'owner_repo' => 'https://gitlab.com/gioxx/blog-style-corpus',
            'branch' => 'main',
            'base_folder' => 'posts',
        ]);

        $this->assertSame('', $result['owner_repo']);
    }
}
