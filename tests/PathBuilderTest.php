<?php

namespace POTOGH\Tests;

use PHPUnit\Framework\TestCase;
use POTOGH\PathBuilder;

class PathBuilderTest extends TestCase
{
    public function test_builds_path_with_year_folder(): void
    {
        $this->assertSame(
            'posts/2026/come-configurare-wordpress.md',
            PathBuilder::build('posts', '2026', 'come-configurare-wordpress')
        );
    }

    public function test_trims_slashes_from_base_folder(): void
    {
        $this->assertSame(
            'posts/2026/my-post.md',
            PathBuilder::build('/posts/', '2026', 'my-post')
        );
    }
}
