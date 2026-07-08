<?php

namespace POTOGH\Tests;

use PHPUnit\Framework\TestCase;
use POTOGH\FrontMatter;

class FrontMatterTest extends TestCase
{
    public function test_builds_expected_yaml_block(): void
    {
        $result = FrontMatter::build([
            'title' => 'Come configurare WordPress',
            'slug' => 'come-configurare-wordpress',
            'date' => '2026-07-08T10:30:00+02:00',
            'modified' => '2026-07-08T11:00:00+02:00',
            'wp_id' => 1234,
            'categories' => ['WordPress', 'Tutorial'],
            'tags' => ['plugin', 'github'],
            'permalink' => 'https://tuosito.it/come-configurare-wordpress/',
        ]);

        $expected = <<<YAML
---
title: "Come configurare WordPress"
slug: come-configurare-wordpress
date: 2026-07-08T10:30:00+02:00
modified: 2026-07-08T11:00:00+02:00
wp_id: 1234
categories: ["WordPress", "Tutorial"]
tags: ["plugin", "github"]
permalink: https://tuosito.it/come-configurare-wordpress/
---

YAML;

        $this->assertSame($expected, $result);
    }

    public function test_empty_categories_and_tags_render_as_empty_list(): void
    {
        $result = FrontMatter::build([
            'title' => 'Post senza categorie',
            'slug' => 'post-senza-categorie',
            'date' => '2026-07-08T10:30:00+02:00',
            'modified' => '2026-07-08T10:30:00+02:00',
            'wp_id' => 1,
            'categories' => [],
            'tags' => [],
            'permalink' => 'https://tuosito.it/post-senza-categorie/',
        ]);

        $this->assertStringContainsString('categories: []', $result);
        $this->assertStringContainsString('tags: []', $result);
    }

    public function test_escapes_double_quotes_in_title(): void
    {
        $result = FrontMatter::build([
            'title' => 'Il post con "virgolette"',
            'slug' => 'post-virgolette',
            'date' => '2026-07-08T10:30:00+02:00',
            'modified' => '2026-07-08T10:30:00+02:00',
            'wp_id' => 2,
            'categories' => [],
            'tags' => [],
            'permalink' => 'https://tuosito.it/post-virgolette/',
        ]);

        $this->assertStringContainsString('title: "Il post con \\"virgolette\\""', $result);
    }
}
