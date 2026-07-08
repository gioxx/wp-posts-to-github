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
        ], $result);
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
}
