# Post to GitHub Markdown Plugin Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a WordPress plugin that exports published posts to Markdown files (with YAML front matter) and pushes them to a private GitHub repository via the GitHub Contents REST API, triggered from a single-post metabox or a bulk-export admin page.

**Architecture:** Small, framework-agnostic PHP classes (path building, front matter, HTML→Markdown conversion, export-status calculation, a GitHub API client) are composed by an `ExportService`. A thin WordPress integration layer (settings page, metabox, bulk admin page, AJAX handlers) adapts WordPress data (posts, meta, options) into the plain-PHP interfaces so the core logic is unit-testable without a full WordPress test harness.

**Tech Stack:** PHP 7.4+, WordPress 6.0+ plugin APIs (Settings API, Meta Boxes, admin-ajax), Composer, `league/html-to-markdown` for conversion, PHPUnit + Brain Monkey for unit tests (mocks WordPress functions, no database/WP install required).

## Global Constraints

- Post type: only `post`. Post status: only `publish`. (Spec: Scope)
- No image upload/rewriting — Markdown keeps absolute image URLs as-is. (Spec: Scope)
- Repository is pre-existing and private; the plugin never creates repositories. (Spec: Scope)
- File path is `{base_folder}/{publication_year}/{slug}.md`, year from `post_date`, not `post_modified`. (Spec: Export service, step 1)
- Front matter fields exactly: `title, slug, date, modified, wp_id, categories, tags, permalink`. (Spec: Export service, step 2)
- Commit message format: `Export post: {title} (#{wp_id})`. (Spec: Export service, step 5)
- Re-export of an already-exported post must update the existing file in place (same path/SHA), not create a duplicate. (Spec: Export service, step 4)
- On any export error, do not update post meta (`_potogh_path`, `_potogh_sha`, `_potogh_exported_at`). (Spec: Export service, step 7 / Gestione errori)
- Git commits in this repo use Conventional Commits format and never mention or credit an AI author.

---

## File Structure

```
post-to-github-md.php              # Plugin bootstrap (headers, hooks wiring)
composer.json                      # league/html-to-markdown + dev deps (phpunit, brain/monkey)
phpunit.xml.dist                   # PHPUnit config
tests/bootstrap.php                # Loads Composer autoloader for tests
includes/
  PathBuilder.php                  # POTOGH\PathBuilder — pure function: build file path
  FrontMatter.php                  # POTOGH\FrontMatter — pure function: build YAML front matter
  ExportStatus.php                 # POTOGH\ExportStatus — pure function: compute status label constant
  Converter.php                    # POTOGH\Converter — wraps league/html-to-markdown
  GithubClient.php                 # POTOGH\GithubClient — GitHub Contents API (get/put file)
  ExportService.php                # POTOGH\ExportService — orchestrates the above
  Settings.php                     # POTOGH\Settings — option storage, sanitize, settings page
  Metabox.php                      # POTOGH\Metabox — single-post metabox + AJAX handler
  BulkPage.php                     # POTOGH\BulkPage — bulk export admin page + AJAX handler
  functions.php                    # POTOGH\{build_export_service, post_to_export_data,
                                    #   export_post_by_id, enqueue_admin_assets} — WP glue
assets/js/
  metabox.js                       # AJAX call for the single-post export button
  bulk.js                          # Sequential AJAX calls for bulk export + summary rendering
tests/
  PathBuilderTest.php
  FrontMatterTest.php
  ExportStatusTest.php
  ConverterTest.php
  GithubClientTest.php
  ExportServiceTest.php
  SettingsTest.php
```

Each `includes/*.php` file is one class with one responsibility. `functions.php` is the only place that touches WordPress global functions to build/run an export, keeping every other class mockable/pure.

---

### Task 1: Project scaffold, Composer, PHPUnit + Brain Monkey harness

**Files:**
- Create: `composer.json`
- Create: `phpunit.xml.dist`
- Create: `tests/bootstrap.php`
- Create: `post-to-github-md.php`
- Test: `tests/SmokeTest.php`

**Interfaces:**
- Produces: Composer autoloader (`POTOGH\` → `includes/`) that every later task's tests rely on via `require_once __DIR__ . '/../vendor/autoload.php'` in `tests/bootstrap.php`.

- [ ] **Step 1: Write `composer.json`**

```json
{
    "name": "gioxx/post-to-github-md",
    "description": "Export published WordPress posts to Markdown files in a private GitHub repository.",
    "type": "wordpress-plugin",
    "license": "GPL-2.0-or-later",
    "require": {
        "php": ">=7.4",
        "league/html-to-markdown": "^5.1"
    },
    "require-dev": {
        "phpunit/phpunit": "^9.6",
        "brain/monkey": "^2.6"
    },
    "autoload": {
        "psr-4": {
            "POTOGH\\": "includes/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "POTOGH\\Tests\\": "tests/"
        }
    }
}
```

- [ ] **Step 2: Write `phpunit.xml.dist`**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="tests/bootstrap.php" colors="true">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

- [ ] **Step 3: Write `tests/bootstrap.php`**

```php
<?php

require_once __DIR__ . '/../vendor/autoload.php';
```

- [ ] **Step 4: Write a smoke test**

```php
<?php

namespace POTOGH\Tests;

use PHPUnit\Framework\TestCase;

class SmokeTest extends TestCase
{
    public function test_autoloader_is_wired(): void
    {
        $this->assertTrue(class_exists(\League\HtmlToMarkdown\HtmlConverter::class));
    }
}
```

- [ ] **Step 5: Install dependencies and run the test to verify it fails first**

Run: `composer install`
Then run: `vendor/bin/phpunit tests/SmokeTest.php`
Expected before `composer install`: command not found / autoload missing. After `composer install`: the test PASSES immediately (there is no red-green cycle for this scaffolding step — `composer install` itself is the "implementation"). Confirm PASS:

Run: `vendor/bin/phpunit tests/SmokeTest.php`
Expected: `OK (1 test, 1 assertion)`

- [ ] **Step 6: Write the plugin bootstrap file `post-to-github-md.php`**

```php
<?php
/**
 * Plugin Name: Post to GitHub Markdown
 * Description: Esporta i post pubblicati come file Markdown in un repository GitHub privato.
 * Version: 1.0.0
 * Requires PHP: 7.4
 * Requires at least: 6.0
 * Text Domain: post-to-github-md
 */

if (!defined('ABSPATH')) {
    exit;
}

define('POTOGH_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('POTOGH_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once POTOGH_PLUGIN_DIR . 'vendor/autoload.php';
require_once POTOGH_PLUGIN_DIR . 'includes/functions.php';
```

This file will be extended in Task 9 once `functions.php` and the WordPress-facing classes exist. For now it only needs to load without fatal errors when PHP-linted.

Run: `php -l post-to-github-md.php`
Expected: `No syntax errors detected`

- [ ] **Step 7: Commit**

```bash
git add composer.json phpunit.xml.dist tests/bootstrap.php tests/SmokeTest.php post-to-github-md.php composer.lock vendor
git commit -m "chore: scaffold plugin, composer deps, and phpunit harness"
```

Note: committing `vendor/` is intentional per the spec (Note tecniche: "il `vendor/` viene distribuito già incluso nel plugin").

---

### Task 2: PathBuilder

**Files:**
- Create: `includes/PathBuilder.php`
- Test: `tests/PathBuilderTest.php`

**Interfaces:**
- Produces: `POTOGH\PathBuilder::build(string $baseFolder, string $year, string $slug): string` — used by `ExportService` (Task 7).

- [ ] **Step 1: Write the failing test**

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/PathBuilderTest.php`
Expected: FAIL — `Class "POTOGH\PathBuilder" not found`

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace POTOGH;

class PathBuilder
{
    public static function build(string $baseFolder, string $year, string $slug): string
    {
        $baseFolder = trim($baseFolder, "/ \t\n\r\0\x0B");

        return sprintf('%s/%s/%s.md', $baseFolder, $year, $slug);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/PathBuilderTest.php`
Expected: `OK (2 tests, 2 assertions)`

- [ ] **Step 5: Commit**

```bash
git add includes/PathBuilder.php tests/PathBuilderTest.php
git commit -m "feat: add PathBuilder for year-partitioned export paths"
```

---

### Task 3: FrontMatter

**Files:**
- Create: `includes/FrontMatter.php`
- Test: `tests/FrontMatterTest.php`

**Interfaces:**
- Produces: `POTOGH\FrontMatter::build(array $data): string`, where `$data` has keys `title, slug, date, modified, wp_id, categories, tags, permalink` (`categories`/`tags` are `string[]`). Used by `ExportService` (Task 7).

- [ ] **Step 1: Write the failing test**

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/FrontMatterTest.php`
Expected: FAIL — `Class "POTOGH\FrontMatter" not found`

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace POTOGH;

class FrontMatter
{
    public static function build(array $data): string
    {
        $lines = ['---'];
        $lines[] = 'title: "' . self::escape($data['title']) . '"';
        $lines[] = 'slug: ' . $data['slug'];
        $lines[] = 'date: ' . $data['date'];
        $lines[] = 'modified: ' . $data['modified'];
        $lines[] = 'wp_id: ' . (int) $data['wp_id'];
        $lines[] = 'categories: ' . self::yamlList($data['categories']);
        $lines[] = 'tags: ' . self::yamlList($data['tags']);
        $lines[] = 'permalink: ' . $data['permalink'];
        $lines[] = '---';

        return implode("\n", $lines) . "\n";
    }

    private static function escape(string $value): string
    {
        return str_replace('"', '\\"', $value);
    }

    private static function yamlList(array $items): string
    {
        if (empty($items)) {
            return '[]';
        }

        $quoted = array_map(function (string $item): string {
            return '"' . str_replace('"', '\\"', $item) . '"';
        }, $items);

        return '[' . implode(', ', $quoted) . ']';
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/FrontMatterTest.php`
Expected: `OK (3 tests, 5 assertions)`

- [ ] **Step 5: Commit**

```bash
git add includes/FrontMatter.php tests/FrontMatterTest.php
git commit -m "feat: add FrontMatter YAML block builder"
```

---

### Task 4: ExportStatus

**Files:**
- Create: `includes/ExportStatus.php`
- Test: `tests/ExportStatusTest.php`

**Interfaces:**
- Produces: `POTOGH\ExportStatus::NEVER_EXPORTED`, `::EXPORTED`, `::MODIFIED_SINCE_EXPORT` string constants and `POTOGH\ExportStatus::determine(?string $exportedAtGmt, string $postModifiedGmt): string`. Used by `Metabox` and `BulkPage` (Tasks 8, 9).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace POTOGH\Tests;

use PHPUnit\Framework\TestCase;
use POTOGH\ExportStatus;

class ExportStatusTest extends TestCase
{
    public function test_never_exported_when_no_exported_at(): void
    {
        $this->assertSame(
            ExportStatus::NEVER_EXPORTED,
            ExportStatus::determine(null, '2026-07-08 10:00:00')
        );
    }

    public function test_never_exported_when_exported_at_is_empty_string(): void
    {
        $this->assertSame(
            ExportStatus::NEVER_EXPORTED,
            ExportStatus::determine('', '2026-07-08 10:00:00')
        );
    }

    public function test_exported_when_modified_before_export(): void
    {
        $this->assertSame(
            ExportStatus::EXPORTED,
            ExportStatus::determine('2026-07-08T12:00:00+00:00', '2026-07-08 10:00:00')
        );
    }

    public function test_modified_since_export_when_modified_after_export(): void
    {
        $this->assertSame(
            ExportStatus::MODIFIED_SINCE_EXPORT,
            ExportStatus::determine('2026-07-08T09:00:00+00:00', '2026-07-08 10:00:00')
        );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/ExportStatusTest.php`
Expected: FAIL — `Class "POTOGH\ExportStatus" not found`

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace POTOGH;

class ExportStatus
{
    public const NEVER_EXPORTED = 'never_exported';
    public const EXPORTED = 'exported';
    public const MODIFIED_SINCE_EXPORT = 'modified_since_export';

    public static function determine(?string $exportedAtGmt, string $postModifiedGmt): string
    {
        if (empty($exportedAtGmt)) {
            return self::NEVER_EXPORTED;
        }

        if (strtotime($postModifiedGmt) > strtotime($exportedAtGmt)) {
            return self::MODIFIED_SINCE_EXPORT;
        }

        return self::EXPORTED;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/ExportStatusTest.php`
Expected: `OK (4 tests, 4 assertions)`

- [ ] **Step 5: Commit**

```bash
git add includes/ExportStatus.php tests/ExportStatusTest.php
git commit -m "feat: add ExportStatus calculation"
```

---

### Task 5: Converter (HTML → Markdown)

**Files:**
- Create: `includes/Converter.php`
- Test: `tests/ConverterTest.php`

**Interfaces:**
- Consumes: `League\HtmlToMarkdown\HtmlConverter` (from `league/html-to-markdown`, installed in Task 1).
- Produces: `POTOGH\Converter::convert(string $html): string`. Used by `ExportService` (Task 7).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace POTOGH\Tests;

use PHPUnit\Framework\TestCase;
use POTOGH\Converter;

class ConverterTest extends TestCase
{
    public function test_converts_headings_and_bold(): void
    {
        $converter = new Converter();

        $result = $converter->convert('<h1>Titolo</h1><p>Ciao <strong>mondo</strong></p>');

        $this->assertSame("# Titolo\n\nCiao **mondo**", $result);
    }

    public function test_preserves_absolute_image_links(): void
    {
        $converter = new Converter();

        $result = $converter->convert('<img src="https://example.com/a.png" alt="Alt text">');

        $this->assertSame('![Alt text](https://example.com/a.png)', $result);
    }

    public function test_converts_lists(): void
    {
        $converter = new Converter();

        $result = $converter->convert('<ul><li>Uno</li><li>Due</li></ul>');

        $this->assertSame("-   Uno\n-   Due", $result);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/ConverterTest.php`
Expected: FAIL — `Class "POTOGH\Converter" not found`

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace POTOGH;

use League\HtmlToMarkdown\HtmlConverter;

class Converter
{
    private HtmlConverter $htmlConverter;

    public function __construct(?HtmlConverter $htmlConverter = null)
    {
        $this->htmlConverter = $htmlConverter ?? new HtmlConverter(['strip_tags' => true]);
    }

    public function convert(string $html): string
    {
        return trim($this->htmlConverter->convert($html));
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/ConverterTest.php`
Expected: `OK (3 tests, 3 assertions)`

If the list test fails because of a different (but equivalent) bullet marker, adjust the assertion to match `league/html-to-markdown`'s actual default output rather than changing the implementation — the library's default list rendering is authoritative here.

- [ ] **Step 5: Commit**

```bash
git add includes/Converter.php tests/ConverterTest.php
git commit -m "feat: add HTML to Markdown Converter wrapper"
```

---

### Task 6: GithubClient

**Files:**
- Create: `includes/GithubClient.php`
- Test: `tests/GithubClientTest.php`

**Interfaces:**
- Consumes (mocked in tests via Brain Monkey): WordPress HTTP API functions `wp_remote_get`, `wp_remote_request`, `is_wp_error`, `wp_remote_retrieve_response_code`, `wp_remote_retrieve_body`, `wp_json_encode`.
- Produces:
  - `POTOGH\GithubClient::__construct(string $token, string $ownerRepo, string $branch)`
  - `POTOGH\GithubClient::getFile(string $path): ?array` — returns `['sha' => string|null]` or `null` if the file does not exist (404).
  - `POTOGH\GithubClient::putFile(string $path, string $content, string $message, ?string $sha = null): array` — returns `['success' => true, 'sha' => string|null]` or `['success' => false, 'error' => string, 'status' => int|null]`.
  Used by `ExportService` (Task 7).

- [ ] **Step 1: Write the failing test**

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/GithubClientTest.php`
Expected: FAIL — `Class "POTOGH\GithubClient" not found`

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace POTOGH;

class GithubClient
{
    private string $token;
    private string $ownerRepo;
    private string $branch;

    public function __construct(string $token, string $ownerRepo, string $branch)
    {
        $this->token = $token;
        $this->ownerRepo = $ownerRepo;
        $this->branch = $branch;
    }

    public function getFile(string $path): ?array
    {
        $url = sprintf(
            'https://api.github.com/repos/%s/contents/%s?ref=%s',
            $this->ownerRepo,
            ltrim($path, '/'),
            $this->branch
        );

        $response = wp_remote_get($url, ['headers' => $this->headers()]);

        if (is_wp_error($response)) {
            return null;
        }

        if (wp_remote_retrieve_response_code($response) === 404) {
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        return ['sha' => $body['sha'] ?? null];
    }

    public function putFile(string $path, string $content, string $message, ?string $sha = null): array
    {
        $url = sprintf('https://api.github.com/repos/%s/contents/%s', $this->ownerRepo, ltrim($path, '/'));

        $payload = [
            'message' => $message,
            'content' => base64_encode($content),
            'branch' => $this->branch,
        ];

        if ($sha !== null) {
            $payload['sha'] = $sha;
        }

        $response = wp_remote_request($url, [
            'method' => 'PUT',
            'headers' => $this->headers(),
            'body' => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message(), 'status' => null];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 200 && $code < 300) {
            return ['success' => true, 'sha' => $body['content']['sha'] ?? null];
        }

        return [
            'success' => false,
            'error' => $body['message'] ?? ('GitHub API error, HTTP ' . $code),
            'status' => $code,
        ];
    }

    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/vnd.github+json',
            'User-Agent' => 'post-to-github-md-wp-plugin',
        ];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/GithubClientTest.php`
Expected: `OK (4 tests, 4 assertions)`

- [ ] **Step 5: Commit**

```bash
git add includes/GithubClient.php tests/GithubClientTest.php
git commit -m "feat: add GithubClient for GitHub Contents API"
```

---

### Task 7: ExportService

**Files:**
- Create: `includes/ExportService.php`
- Test: `tests/ExportServiceTest.php`

**Interfaces:**
- Consumes: `POTOGH\Converter::convert(string $html): string` (Task 5), `POTOGH\GithubClient::putFile(...)` (Task 6, mocked via PHPUnit `createMock`), `POTOGH\PathBuilder::build(...)` (Task 2), `POTOGH\FrontMatter::build(...)` (Task 3).
- Produces: `POTOGH\ExportService::__construct(Converter $converter, GithubClient $githubClient, string $baseFolder)` and `POTOGH\ExportService::exportPost(array $postData): array`, where `$postData` has keys `wp_id, title, slug, date, date_gmt, modified, categories, tags, permalink, content_html, existing_path (?string), existing_sha (?string)`. Returns `['success' => true, 'path' => string, 'sha' => string|null]` or `['success' => false, 'error' => string]`. Used by `functions.php` (Task 8).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace POTOGH\Tests;

use PHPUnit\Framework\TestCase;
use POTOGH\Converter;
use POTOGH\ExportService;
use POTOGH\GithubClient;

class ExportServiceTest extends TestCase
{
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

        $this->assertSame([
            'success' => true,
            'path' => 'posts/2026/come-configurare-wordpress.md',
            'sha' => 'new-sha',
        ], $result);
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

        $this->assertSame(['success' => false, 'error' => 'sha does not match'], $result);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/ExportServiceTest.php`
Expected: FAIL — `Class "POTOGH\ExportService" not found`

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace POTOGH;

class ExportService
{
    private Converter $converter;
    private GithubClient $githubClient;
    private string $baseFolder;

    public function __construct(Converter $converter, GithubClient $githubClient, string $baseFolder)
    {
        $this->converter = $converter;
        $this->githubClient = $githubClient;
        $this->baseFolder = $baseFolder;
    }

    public function exportPost(array $postData): array
    {
        $year = gmdate('Y', strtotime($postData['date_gmt']));
        $path = $postData['existing_path'] ?? PathBuilder::build($this->baseFolder, $year, $postData['slug']);

        $frontMatter = FrontMatter::build([
            'title' => $postData['title'],
            'slug' => $postData['slug'],
            'date' => $postData['date'],
            'modified' => $postData['modified'],
            'wp_id' => $postData['wp_id'],
            'categories' => $postData['categories'],
            'tags' => $postData['tags'],
            'permalink' => $postData['permalink'],
        ]);

        $markdown = $this->converter->convert($postData['content_html']);
        $fileContent = $frontMatter . "\n" . $markdown . "\n";

        $message = sprintf('Export post: %s (#%d)', $postData['title'], $postData['wp_id']);

        $result = $this->githubClient->putFile($path, $fileContent, $message, $postData['existing_sha'] ?? null);

        if (!$result['success']) {
            return ['success' => false, 'error' => $result['error']];
        }

        return ['success' => true, 'path' => $path, 'sha' => $result['sha']];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/ExportServiceTest.php`
Expected: `OK (3 tests, 3 assertions)`

- [ ] **Step 5: Commit**

```bash
git add includes/ExportService.php tests/ExportServiceTest.php
git commit -m "feat: add ExportService orchestrating conversion and GitHub push"
```

---

### Task 8: Settings (option storage + sanitization + settings page)

**Files:**
- Create: `includes/Settings.php`
- Test: `tests/SettingsTest.php`

**Interfaces:**
- Produces:
  - `POTOGH\Settings::OPTION_NAME` (string constant `potogh_settings`)
  - `POTOGH\Settings::defaults(): array` — `['token' => '', 'owner_repo' => '', 'branch' => 'main', 'base_folder' => 'posts']`
  - `POTOGH\Settings::sanitize(array $input): array`
  - `POTOGH\Settings::isConfigured(): bool` (uses `get_option`, not unit tested here — WordPress-only)
  - `POTOGH\Settings::get(): array` (uses `get_option`/`wp_parse_args`, not unit tested here)
  - Instance methods `registerPage()`, `registerSetting()`, `renderPage()` wired to WordPress hooks in Task 9.
  Used by `Metabox`, `BulkPage`, `functions.php` (Tasks 8-10... see note below on numbering).

- [ ] **Step 1: Write the failing test**

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/SettingsTest.php`
Expected: FAIL — `Class "POTOGH\Settings" not found`

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace POTOGH;

class Settings
{
    public const OPTION_NAME = 'potogh_settings';

    public static function defaults(): array
    {
        return [
            'token' => '',
            'owner_repo' => '',
            'branch' => 'main',
            'base_folder' => 'posts',
        ];
    }

    public static function sanitize(array $input): array
    {
        $defaults = self::defaults();

        $token = trim($input['token'] ?? '');
        $ownerRepo = trim($input['owner_repo'] ?? '');
        $branch = trim($input['branch'] ?? '') ?: $defaults['branch'];
        $baseFolder = trim($input['base_folder'] ?? '', "/ \t\n\r\0\x0B") ?: $defaults['base_folder'];

        if ($ownerRepo !== '' && !preg_match('/^[\w.-]+\/[\w.-]+$/', $ownerRepo)) {
            $ownerRepo = '';
        }

        return [
            'token' => $token,
            'owner_repo' => $ownerRepo,
            'branch' => $branch,
            'base_folder' => $baseFolder,
        ];
    }

    public static function isConfigured(): bool
    {
        $settings = get_option(self::OPTION_NAME, self::defaults());

        return !empty($settings['token']) && !empty($settings['owner_repo']);
    }

    public static function get(): array
    {
        return wp_parse_args(get_option(self::OPTION_NAME, []), self::defaults());
    }

    public function registerPage(): void
    {
        add_options_page(
            __('Post to GitHub MD', 'post-to-github-md'),
            __('Post to GitHub MD', 'post-to-github-md'),
            'manage_options',
            'potogh-settings',
            [$this, 'renderPage']
        );
    }

    public function registerSetting(): void
    {
        register_setting('potogh_settings_group', self::OPTION_NAME, [
            'sanitize_callback' => [self::class, 'sanitize'],
            'default' => self::defaults(),
        ]);
    }

    public function renderPage(): void
    {
        $settings = self::get();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Post to GitHub MD', 'post-to-github-md'); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields('potogh_settings_group'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="potogh_token"><?php esc_html_e('GitHub Personal Access Token', 'post-to-github-md'); ?></label></th>
                        <td><input type="password" id="potogh_token" name="<?php echo esc_attr(self::OPTION_NAME); ?>[token]" value="<?php echo esc_attr($settings['token']); ?>" class="regular-text" autocomplete="off"></td>
                    </tr>
                    <tr>
                        <th><label for="potogh_owner_repo"><?php esc_html_e('Owner/repo', 'post-to-github-md'); ?></label></th>
                        <td><input type="text" id="potogh_owner_repo" name="<?php echo esc_attr(self::OPTION_NAME); ?>[owner_repo]" value="<?php echo esc_attr($settings['owner_repo']); ?>" class="regular-text" placeholder="owner/repo"></td>
                    </tr>
                    <tr>
                        <th><label for="potogh_branch"><?php esc_html_e('Branch', 'post-to-github-md'); ?></label></th>
                        <td><input type="text" id="potogh_branch" name="<?php echo esc_attr(self::OPTION_NAME); ?>[branch]" value="<?php echo esc_attr($settings['branch']); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label for="potogh_base_folder"><?php esc_html_e('Base folder', 'post-to-github-md'); ?></label></th>
                        <td><input type="text" id="potogh_base_folder" name="<?php echo esc_attr(self::OPTION_NAME); ?>[base_folder]" value="<?php echo esc_attr($settings['base_folder']); ?>" class="regular-text"></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/SettingsTest.php`
Expected: `OK (4 tests, 6 assertions)`

- [ ] **Step 5: Commit**

```bash
git add includes/Settings.php tests/SettingsTest.php
git commit -m "feat: add Settings storage, sanitization, and settings page"
```

---

### Task 9: functions.php — WordPress glue (build service, map post data, export by ID)

**Files:**
- Create: `includes/functions.php`
- Test: `tests/FunctionsTest.php`

**Interfaces:**
- Consumes: `POTOGH\Settings::get()` (Task 8), `POTOGH\Converter` (Task 5), `POTOGH\GithubClient` (Task 6), `POTOGH\ExportService::exportPost()` (Task 7). Also WordPress functions mocked in tests: `get_post`, `get_the_title`, `get_the_category`, `get_the_tags`, `get_permalink`, `get_post_time`, `get_post_modified_time`, `apply_filters`, `get_post_meta`, `update_post_meta`.
- Produces: `POTOGH\build_export_service(): ExportService`, `POTOGH\post_to_export_data(\WP_Post $post): array`, `POTOGH\export_post_by_id(int $postId): array` — returns `['success' => true, 'path' => string, 'exported_at' => string]` or `['success' => false, 'error' => string]`. Used by `Metabox` and `BulkPage` (Tasks 10, 11).

This task requires a minimal `\WP_Post` stand-in class for tests, since Brain Monkey does not provide WordPress core classes. Define it once in the test file itself as a plain stdClass-like class (test-only, not shipped in the plugin).

- [ ] **Step 1: Write the failing test**

```php
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

        $result = \POTOGH\export_post_by_id(999);

        $this->assertFalse($result['success']);
    }
}
```

Add a minimal test-only `\WP_Post` stand-in so the test file loads without a full WordPress install. Create it in a separate file loaded by `tests/bootstrap.php`:

```php
<?php
// tests/wp-stubs.php

class WP_Post
{
    public $ID;
    public $post_name;
    public $post_content;
    public $post_date_gmt;
    public $post_modified_gmt;
}
```

- [ ] **Step 2: Update `tests/bootstrap.php` to load the stub and run the test to verify it fails**

```php
<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/wp-stubs.php';
```

Run: `vendor/bin/phpunit tests/FunctionsTest.php`
Expected: FAIL — `Call to undefined function POTOGH\post_to_export_data()`

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace POTOGH;

function build_export_service(): ExportService
{
    $settings = Settings::get();
    $converter = new Converter();
    $githubClient = new GithubClient($settings['token'], $settings['owner_repo'], $settings['branch']);

    return new ExportService($converter, $githubClient, $settings['base_folder']);
}

function post_to_export_data(\WP_Post $post): array
{
    $categories = wp_list_pluck(get_the_category($post->ID), 'name');
    $tags = wp_list_pluck(get_the_tags($post->ID) ?: [], 'name');

    return [
        'wp_id' => $post->ID,
        'title' => get_the_title($post),
        'slug' => $post->post_name,
        'date' => get_post_time('c', true, $post),
        'date_gmt' => $post->post_date_gmt,
        'modified' => get_post_modified_time('c', true, $post),
        'categories' => $categories,
        'tags' => $tags,
        'permalink' => get_permalink($post),
        'content_html' => apply_filters('the_content', $post->post_content),
        'existing_path' => get_post_meta($post->ID, '_potogh_path', true) ?: null,
        'existing_sha' => get_post_meta($post->ID, '_potogh_sha', true) ?: null,
    ];
}

function export_post_by_id(int $postId): array
{
    $post = get_post($postId);

    if (!$post instanceof \WP_Post) {
        return ['success' => false, 'error' => __('Post non trovato.', 'post-to-github-md')];
    }

    $service = build_export_service();
    $result = $service->exportPost(post_to_export_data($post));

    if (!$result['success']) {
        return $result;
    }

    $exportedAt = gmdate('c');
    update_post_meta($postId, '_potogh_path', $result['path']);
    update_post_meta($postId, '_potogh_sha', $result['sha']);
    update_post_meta($postId, '_potogh_exported_at', $exportedAt);

    return ['success' => true, 'path' => $result['path'], 'exported_at' => $exportedAt];
}

function enqueue_admin_assets(string $hook): void
{
    if ($hook === 'post.php' || $hook === 'post-new.php') {
        wp_enqueue_script('potogh-metabox', POTOGH_PLUGIN_URL . 'assets/js/metabox.js', ['jquery'], '1.0.0', true);
        wp_localize_script('potogh-metabox', 'potoghMetabox', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
        ]);
    }

    if ($hook === 'tools_page_potogh-bulk-export') {
        wp_enqueue_script('potogh-bulk', POTOGH_PLUGIN_URL . 'assets/js/bulk.js', ['jquery'], '1.0.0', true);
        wp_localize_script('potogh-bulk', 'potoghBulk', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
        ]);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/FunctionsTest.php`
Expected: `OK (2 tests, 6 assertions)`

Also re-run the full suite to confirm nothing regressed:

Run: `vendor/bin/phpunit`
Expected: all tests green.

- [ ] **Step 5: Commit**

```bash
git add includes/functions.php tests/FunctionsTest.php tests/wp-stubs.php tests/bootstrap.php
git commit -m "feat: wire WordPress data mapping and export-by-id glue functions"
```

---

### Task 10: Metabox (single-post export UI + AJAX handler)

**Files:**
- Create: `includes/Metabox.php`
- Test: `tests/MetaboxTest.php`

**Interfaces:**
- Consumes: `POTOGH\ExportStatus::determine()` (Task 4), `POTOGH\export_post_by_id()` (Task 9), `POTOGH\Settings::isConfigured()` (Task 8).
- Produces: `POTOGH\Metabox::statusLabel(string $status, ?string $exportedAt): string` (pure, unit tested), plus WordPress-hooked instance methods `registerMetabox()`, `render(\WP_Post $post)`, `handleAjaxExport()` (wired to hooks in Task 12, not unit tested — they call WordPress functions directly per the plugin's established pattern in `functions.php`).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace POTOGH\Tests;

use PHPUnit\Framework\TestCase;
use POTOGH\ExportStatus;
use POTOGH\Metabox;

class MetaboxTest extends TestCase
{
    public function test_status_label_never_exported(): void
    {
        $this->assertSame('Mai esportato', Metabox::statusLabel(ExportStatus::NEVER_EXPORTED, null));
    }

    public function test_status_label_exported_includes_date(): void
    {
        $label = Metabox::statusLabel(ExportStatus::EXPORTED, '2026-07-08T11:00:00+00:00');

        $this->assertStringContainsString('Esportato il', $label);
        $this->assertStringContainsString('2026-07-08T11:00:00+00:00', $label);
    }

    public function test_status_label_modified_since_export(): void
    {
        $this->assertSame(
            "Modificato dopo l'ultima esportazione",
            Metabox::statusLabel(ExportStatus::MODIFIED_SINCE_EXPORT, '2026-07-08T09:00:00+00:00')
        );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/MetaboxTest.php`
Expected: FAIL — `Class "POTOGH\Metabox" not found`

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace POTOGH;

class Metabox
{
    public function registerMetabox(): void
    {
        add_meta_box(
            'potogh_export',
            __('Export to GitHub', 'post-to-github-md'),
            [$this, 'render'],
            'post',
            'side'
        );
    }

    public function render(\WP_Post $post): void
    {
        $exportedAt = get_post_meta($post->ID, '_potogh_exported_at', true) ?: null;
        $status = ExportStatus::determine($exportedAt, $post->post_modified_gmt);

        wp_nonce_field('potogh_export_' . $post->ID, 'potogh_export_nonce');
        ?>
        <p class="potogh-status" data-post-id="<?php echo esc_attr($post->ID); ?>">
            <?php echo esc_html(self::statusLabel($status, $exportedAt)); ?>
        </p>
        <button type="button" class="button button-primary potogh-export-button" data-post-id="<?php echo esc_attr($post->ID); ?>">
            <?php esc_html_e('Esporta su GitHub', 'post-to-github-md'); ?>
        </button>
        <div class="potogh-export-message"></div>
        <?php
    }

    public static function statusLabel(string $status, ?string $exportedAt): string
    {
        switch ($status) {
            case ExportStatus::EXPORTED:
                return sprintf(__('Esportato il %s', 'post-to-github-md'), $exportedAt);
            case ExportStatus::MODIFIED_SINCE_EXPORT:
                return __("Modificato dopo l'ultima esportazione", 'post-to-github-md');
            default:
                return __('Mai esportato', 'post-to-github-md');
        }
    }

    public function handleAjaxExport(): void
    {
        $postId = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;

        check_ajax_referer('potogh_export_' . $postId, 'nonce');

        if (!current_user_can('edit_post', $postId)) {
            wp_send_json_error(['message' => __('Permessi insufficienti.', 'post-to-github-md')], 403);
        }

        if (!Settings::isConfigured()) {
            wp_send_json_error(['message' => __('Configura prima PAT e repository nelle impostazioni del plugin.', 'post-to-github-md')], 400);
        }

        $result = export_post_by_id($postId);

        if (!$result['success']) {
            wp_send_json_error(['message' => $result['error']], 500);
        }

        wp_send_json_success([
            'message' => self::statusLabel(ExportStatus::EXPORTED, $result['exported_at']),
        ]);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/MetaboxTest.php`
Expected: `OK (3 tests, 5 assertions)`

- [ ] **Step 5: Commit**

```bash
git add includes/Metabox.php tests/MetaboxTest.php
git commit -m "feat: add single-post export metabox and AJAX handler"
```

---

### Task 11: BulkPage (bulk export admin page + AJAX handler)

**Files:**
- Create: `includes/BulkPage.php`

**Interfaces:**
- Consumes: `POTOGH\ExportStatus::determine()` (Task 4), `POTOGH\Metabox::statusLabel()` (Task 10), `POTOGH\export_post_by_id()` (Task 9), `POTOGH\Settings::isConfigured()` (Task 8).
- Produces: instance methods `registerPage()`, `render()`, `handleAjaxExportOne()`, wired to hooks in Task 12. No new pure logic here (status computation and labeling are already covered by `ExportStatusTest` and `MetaboxTest`), so this task has no dedicated unit test — verification happens manually in Task 12's end-to-end check.

- [ ] **Step 1: Write `includes/BulkPage.php`**

```php
<?php

namespace POTOGH;

class BulkPage
{
    public function registerPage(): void
    {
        add_management_page(
            __('Bulk export to GitHub', 'post-to-github-md'),
            __('Export to GitHub MD', 'post-to-github-md'),
            'manage_options',
            'potogh-bulk-export',
            [$this, 'render']
        );
    }

    public function render(): void
    {
        $posts = get_posts([
            'post_type' => 'post',
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Esporta post su GitHub', 'post-to-github-md'); ?></h1>
            <?php wp_nonce_field('potogh_bulk_export', 'potogh_bulk_nonce'); ?>
            <p>
                <button type="button" class="button button-primary" id="potogh-bulk-export-selected">
                    <?php esc_html_e('Esporta selezionati', 'post-to-github-md'); ?>
                </button>
            </p>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="potogh-select-all"></th>
                        <th><?php esc_html_e('Titolo', 'post-to-github-md'); ?></th>
                        <th><?php esc_html_e('Data pubblicazione', 'post-to-github-md'); ?></th>
                        <th><?php esc_html_e('Stato export', 'post-to-github-md'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($posts as $post) :
                    $exportedAt = get_post_meta($post->ID, '_potogh_exported_at', true) ?: null;
                    $status = ExportStatus::determine($exportedAt, $post->post_modified_gmt);
                ?>
                    <tr data-post-id="<?php echo esc_attr($post->ID); ?>">
                        <td><input type="checkbox" class="potogh-post-checkbox" value="<?php echo esc_attr($post->ID); ?>"></td>
                        <td><?php echo esc_html(get_the_title($post)); ?></td>
                        <td><?php echo esc_html(get_the_date('', $post)); ?></td>
                        <td class="potogh-status-cell"><?php echo esc_html(Metabox::statusLabel($status, $exportedAt)); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div id="potogh-bulk-summary"></div>
        </div>
        <?php
    }

    public function handleAjaxExportOne(): void
    {
        check_ajax_referer('potogh_bulk_export', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permessi insufficienti.', 'post-to-github-md')], 403);
        }

        if (!Settings::isConfigured()) {
            wp_send_json_error(['message' => __('Configura prima PAT e repository nelle impostazioni del plugin.', 'post-to-github-md')], 400);
        }

        $postId = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        $result = export_post_by_id($postId);

        if (!$result['success']) {
            wp_send_json_error(['message' => $result['error'], 'post_id' => $postId], 500);
        }

        wp_send_json_success([
            'post_id' => $postId,
            'message' => Metabox::statusLabel(ExportStatus::EXPORTED, $result['exported_at']),
        ]);
    }
}
```

- [ ] **Step 2: Lint the file**

Run: `php -l includes/BulkPage.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Run the full test suite to confirm no regressions**

Run: `vendor/bin/phpunit`
Expected: all existing tests still green (this task adds no new tests, only WordPress-hooked glue).

- [ ] **Step 4: Commit**

```bash
git add includes/BulkPage.php
git commit -m "feat: add bulk export admin page and AJAX handler"
```

---

### Task 12: Wire hooks in the bootstrap file, admin JS, and manual end-to-end verification

**Files:**
- Modify: `post-to-github-md.php`
- Create: `assets/js/metabox.js`
- Create: `assets/js/bulk.js`

**Interfaces:**
- Consumes: `POTOGH\Settings`, `POTOGH\Metabox`, `POTOGH\BulkPage`, `POTOGH\enqueue_admin_assets()` (all from prior tasks).
- Produces: a fully wired plugin, activatable in a real WordPress install. This is the integration point — no new unit-testable logic, verified manually.

- [ ] **Step 1: Update `post-to-github-md.php` to wire all hooks**

```php
<?php
/**
 * Plugin Name: Post to GitHub Markdown
 * Description: Esporta i post pubblicati come file Markdown in un repository GitHub privato.
 * Version: 1.0.0
 * Requires PHP: 7.4
 * Requires at least: 6.0
 * Text Domain: post-to-github-md
 */

if (!defined('ABSPATH')) {
    exit;
}

define('POTOGH_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('POTOGH_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once POTOGH_PLUGIN_DIR . 'vendor/autoload.php';
require_once POTOGH_PLUGIN_DIR . 'includes/functions.php';

add_action('plugins_loaded', function () {
    $settings = new \POTOGH\Settings();
    add_action('admin_menu', [$settings, 'registerPage']);
    add_action('admin_init', [$settings, 'registerSetting']);

    $metabox = new \POTOGH\Metabox();
    add_action('add_meta_boxes', [$metabox, 'registerMetabox']);
    add_action('wp_ajax_potogh_export_post', [$metabox, 'handleAjaxExport']);

    $bulkPage = new \POTOGH\BulkPage();
    add_action('admin_menu', [$bulkPage, 'registerPage']);
    add_action('wp_ajax_potogh_bulk_export_one', [$bulkPage, 'handleAjaxExportOne']);

    add_action('admin_enqueue_scripts', 'POTOGH\\enqueue_admin_assets');
});
```

- [ ] **Step 2: Write `assets/js/metabox.js`**

```js
(function ($) {
    'use strict';

    $(document).on('click', '.potogh-export-button', function () {
        var $button = $(this);
        var postId = $button.data('post-id');
        var $wrapper = $button.closest('.postbox').length ? $button.closest('.postbox') : $button.parent();
        var $message = $wrapper.find('.potogh-export-message');
        var nonce = $wrapper.find('#potogh_export_nonce').val();

        $button.prop('disabled', true);
        $message.text('');

        $.post(potoghMetabox.ajaxUrl, {
            action: 'potogh_export_post',
            post_id: postId,
            nonce: nonce
        }).done(function (response) {
            if (response.success) {
                $wrapper.find('.potogh-status').text(response.data.message);
                $message.text('');
            } else {
                $message.text(response.data.message);
            }
        }).fail(function () {
            $message.text('Errore di rete durante l\'esportazione.');
        }).always(function () {
            $button.prop('disabled', false);
        });
    });
})(jQuery);
```

- [ ] **Step 3: Write `assets/js/bulk.js`**

```js
(function ($) {
    'use strict';

    $('#potogh-select-all').on('change', function () {
        $('.potogh-post-checkbox').prop('checked', $(this).is(':checked'));
    });

    function exportOne(postId, nonce) {
        return $.post(potoghBulk.ajaxUrl, {
            action: 'potogh_bulk_export_one',
            post_id: postId,
            nonce: nonce
        });
    }

    $('#potogh-bulk-export-selected').on('click', function () {
        var $button = $(this);
        var nonce = $('#potogh_bulk_nonce').val();
        var ids = $('.potogh-post-checkbox:checked').map(function () {
            return $(this).val();
        }).get();

        if (ids.length === 0) {
            return;
        }

        $button.prop('disabled', true);
        var succeeded = 0;
        var failed = [];

        function next(index) {
            if (index >= ids.length) {
                var summary = succeeded + ' post esportati con successo.';
                if (failed.length > 0) {
                    summary += ' ' + failed.length + ' falliti: ' + failed.join('; ');
                }
                $('#potogh-bulk-summary').text(summary);
                $button.prop('disabled', false);
                return;
            }

            var postId = ids[index];

            exportOne(postId, nonce).done(function (response) {
                var $row = $('tr[data-post-id="' + postId + '"]');
                if (response.success) {
                    succeeded++;
                    $row.find('.potogh-status-cell').text(response.data.message);
                } else {
                    failed.push(postId + ': ' + response.data.message);
                }
            }).fail(function () {
                failed.push(postId + ': errore di rete');
            }).always(function () {
                next(index + 1);
            });
        }

        next(0);
    });
})(jQuery);
```

- [ ] **Step 4: Lint the bootstrap file and run the full test suite**

Run: `php -l post-to-github-md.php`
Expected: `No syntax errors detected`

Run: `vendor/bin/phpunit`
Expected: all tests green (no test count regression from Task 11).

- [ ] **Step 5: Manual end-to-end verification**

This step has no automated test — it requires a real WordPress install and is the final acceptance check for the whole plan:

1. Copy the plugin directory into `wp-content/plugins/post-to-github-md/` on a local WordPress install (or symlink it).
2. Activate the plugin from **Plugins**.
3. Go to **Impostazioni → Post to GitHub MD**, fill in a real PAT (with `repo` scope), an existing private repo as `owner/repo`, branch `main`, base folder `posts`.
4. Open an existing published post, confirm the "Export to GitHub" metabox shows "Mai esportato" and a button.
5. Click the button, confirm the status updates to "Esportato il ..." and the file appears at `posts/{year}/{slug}.md` in the GitHub repo with the expected front matter and commit message `Export post: {title} (#{id})`.
6. Edit and re-save the post, reload the edit screen, confirm the metabox now shows "Modificato dopo l'ultima esportazione".
7. Re-export it, confirm the same GitHub file path is updated (not duplicated) and the commit updates the existing file (check via the repo's commit history that the file's SHA history shows one file with two commits).
8. Go to **Strumenti → Export to GitHub MD**, confirm the bulk table lists published posts with correct statuses, select two or more, click "Esporta selezionati", confirm the summary and the per-row status updates.
9. Temporarily clear the PAT in settings, retry both export paths, confirm the blocking "Configura prima PAT e repository..." message appears and no GitHub call is attempted.

- [ ] **Step 6: Commit**

```bash
git add post-to-github-md.php assets/js/metabox.js assets/js/bulk.js
git commit -m "feat: wire admin hooks and add export AJAX scripts"
```

---

## Self-Review Notes

- **Spec coverage:** settings page (Task 8), converter (Task 5), GitHub client (Task 6), export service incl. year-folder path and update-in-place (Tasks 2, 7, 9), metabox (Task 10), bulk page with status column (Task 11), error handling for missing config/API failures (Tasks 8, 10, 11), commit message format (Task 7). All spec sections have a corresponding task.
- **Placeholder scan:** no TBD/TODO markers; every step has complete, runnable code.
- **Type consistency:** `ExportService::exportPost(array $postData)` keys (`wp_id, title, slug, date, date_gmt, modified, categories, tags, permalink, content_html, existing_path, existing_sha`) are identical across Task 7's implementation, Task 7's tests, and Task 9's `post_to_export_data()`. `ExportStatus` constants are reused verbatim in `Metabox` (Task 10) and `BulkPage` (Task 11). `Settings::OPTION_NAME` and `Settings::defaults()` keys (`token, owner_repo, branch, base_folder`) are consistent everywhere they're read (`functions.php`, `Settings::renderPage()`).
