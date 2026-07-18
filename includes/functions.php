<?php

namespace POTOGH;

if (!defined('ABSPATH')) {
    exit;
}

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
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- reuses WordPress core's own 'the_content' filter to render content as the theme would, not a plugin-defined hook.
        'content_html' => apply_filters('the_content', $post->post_content),
        'existing_path' => get_post_meta($post->ID, '_potogh_path', true) ?: null,
        'existing_sha' => get_post_meta($post->ID, '_potogh_sha', true) ?: null,
    ];
}

function export_post_by_id(int $postId): array
{
    $post = get_post($postId);

    if (!$post instanceof \WP_Post) {
        return ['success' => false, 'error' => __('Post not found.', 'post-to-github-md'), 'trace' => []];
    }

    if ($post->post_status !== 'publish' || $post->post_type !== 'post') {
        return ['success' => false, 'error' => __('Only published posts can be exported.', 'post-to-github-md'), 'trace' => []];
    }

    $service = build_export_service();
    $result = $service->exportPost(post_to_export_data($post));

    if (!$result['success']) {
        update_post_meta($postId, '_potogh_last_error', ['message' => $result['error'], 'at' => gmdate('c')]);

        return $result;
    }

    $exportedAt = gmdate('c');
    update_post_meta($postId, '_potogh_path', $result['path']);
    update_post_meta($postId, '_potogh_sha', $result['sha']);
    update_post_meta($postId, '_potogh_exported_at', $exportedAt);
    delete_post_meta($postId, '_potogh_last_error');

    return ['success' => true, 'path' => $result['path'], 'exported_at' => $exportedAt, 'trace' => $result['trace']];
}

function prepare_export_data(int $postId): array
{
    $post = get_post($postId);

    if (!$post instanceof \WP_Post) {
        return ['success' => false, 'error' => __('Post not found.', 'post-to-github-md')];
    }

    if ($post->post_status !== 'publish' || $post->post_type !== 'post') {
        return ['success' => false, 'error' => __('Only published posts can be exported.', 'post-to-github-md')];
    }

    $settings = Settings::get();
    $service = new ExportService(new Converter(), new GithubClient($settings['token'], $settings['owner_repo'], $settings['branch']), $settings['base_folder']);
    $prepared = $service->prepareExport(post_to_export_data($post));

    return [
        'success' => true,
        'post_id' => $postId,
        'title' => get_the_title($post),
        'path' => $prepared['path'],
        'content' => $prepared['content'],
        'trace' => $prepared['trace'],
    ];
}

function clear_export_meta(int $postId): void
{
    delete_post_meta($postId, '_potogh_path');
    delete_post_meta($postId, '_potogh_sha');
    delete_post_meta($postId, '_potogh_exported_at');
    delete_post_meta($postId, '_potogh_last_error');
}

function delete_export_by_id(int $postId): array
{
    $path = get_post_meta($postId, '_potogh_path', true) ?: null;
    $sha = get_post_meta($postId, '_potogh_sha', true) ?: null;

    if (!$path || !$sha) {
        return ['success' => false, 'error' => __('No exported file recorded for this post.', 'post-to-github-md')];
    }

    $settings = Settings::get();
    $client = new GithubClient($settings['token'], $settings['owner_repo'], $settings['branch']);
    $title = get_the_title($postId) ?: (string) $postId;
    $message = sprintf('Remove exported post: %s (#%d)', $title, $postId);

    $result = $client->deleteFile($path, $sha, $message);

    if (!$result['success']) {
        update_post_meta($postId, '_potogh_last_error', ['message' => $result['error'], 'at' => gmdate('c')]);

        return $result;
    }

    clear_export_meta($postId);

    return ['success' => true];
}

function ignore_orphaned_export(int $postId): void
{
    clear_export_meta($postId);
}

function build_batch_commit_message(array $items): string
{
    $subject = sprintf('Bulk export: %d posts', count($items));

    $lines = array_map(static function (array $item): string {
        return sprintf('- %s (#%d)', $item['title'], $item['post_id']);
    }, $items);

    return $subject . "\n\n" . implode("\n", $lines);
}

function commit_batch(array $items): array
{
    $settings = Settings::get();
    $client = new GithubClient($settings['token'], $settings['owner_repo'], $settings['branch']);

    $files = array_map(static function (array $item): array {
        return ['path' => $item['path'], 'content' => $item['content']];
    }, $items);

    $result = $client->commitFiles($files, build_batch_commit_message($items));

    if (!$result['success']) {
        $failedAt = gmdate('c');

        foreach ($items as $item) {
            update_post_meta($item['post_id'], '_potogh_last_error', ['message' => $result['error'], 'at' => $failedAt]);
        }

        return $result;
    }

    $exportedAt = gmdate('c');
    $exported = [];

    foreach ($items as $item) {
        $sha = $result['blob_shas'][ltrim($item['path'], '/')] ?? null;

        update_post_meta($item['post_id'], '_potogh_path', $item['path']);
        update_post_meta($item['post_id'], '_potogh_sha', $sha);
        update_post_meta($item['post_id'], '_potogh_exported_at', $exportedAt);
        delete_post_meta($item['post_id'], '_potogh_last_error');

        $exported[] = [
            'post_id' => $item['post_id'],
            'message' => Metabox::statusLabel(ExportStatus::EXPORTED, $exportedAt),
        ];
    }

    return [
        'success' => true,
        'commit_sha' => $result['commit_sha'],
        'exported_at' => $exportedAt,
        'exported' => $exported,
    ];
}

function schedule_auto_export(string $newStatus, string $oldStatus, \WP_Post $post): void
{
    if ($newStatus !== 'publish' || $oldStatus === 'publish' || $post->post_type !== 'post') {
        return;
    }

    $settings = Settings::get();

    if (empty($settings['auto_export'])) {
        return;
    }

    wp_schedule_single_event(time(), 'potogh_auto_export_event', [$post->ID]);
}

function schedule_auto_reexport(int $postId, \WP_Post $postAfter, \WP_Post $postBefore): void
{
    if ($postAfter->post_type !== 'post' || $postAfter->post_status !== 'publish' || $postBefore->post_status !== 'publish') {
        return;
    }

    if (wp_is_post_autosave($postId) || wp_is_post_revision($postId)) {
        return;
    }

    $settings = Settings::get();

    if (empty($settings['auto_reexport'])) {
        return;
    }

    wp_schedule_single_event(time(), 'potogh_auto_export_event', [$postId]);
}

function run_auto_export(int $postId): void
{
    $settings = Settings::get();

    if ((empty($settings['auto_export']) && empty($settings['auto_reexport'])) || !Settings::isConfigured()) {
        return;
    }

    export_post_by_id($postId);
}

function uninstall_cleanup(): void
{
    $settings = Settings::get();

    if (empty($settings['cleanup_on_uninstall'])) {
        return;
    }

    delete_option(Settings::OPTION_NAME);

    global $wpdb;

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time uninstall cleanup of plugin-owned meta keys; no core API bulk-deletes across all posts/users, and caching is moot on uninstall.
    $wpdb->query(
        "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_potogh_path', '_potogh_sha', '_potogh_exported_at', '_potogh_last_error')"
    );

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time uninstall cleanup of plugin-owned meta keys; no core API bulk-deletes across all posts/users, and caching is moot on uninstall.
    $wpdb->query(
        $wpdb->prepare("DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s", 'potogh_export_per_page')
    );
}

function enqueue_admin_assets(string $hook): void
{
    if ($hook === 'post.php' || $hook === 'post-new.php') {
        wp_enqueue_script('potogh-metabox', POTOGH_PLUGIN_URL . 'assets/js/metabox.js', ['jquery'], POTOGH_VERSION, true);
        wp_localize_script('potogh-metabox', 'potoghMetabox', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'networkError' => __('Network error during export.', 'post-to-github-md'),
        ]);
    }

    if ($hook === 'posts_page_potogh-export') {
        $settings = Settings::get();

        wp_enqueue_script('potogh-bulk', POTOGH_PLUGIN_URL . 'assets/js/bulk.js', ['jquery'], POTOGH_VERSION, true);
        wp_localize_script('potogh-bulk', 'potoghBulk', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'batchCommit' => !empty($settings['batch_commit']),
            'networkError' => __('Network error.', 'post-to-github-md'),
            /* translators: %d: number of successfully exported posts */
            'summarySucceeded' => __('%d posts exported successfully.', 'post-to-github-md'),
            /* translators: %d: number of failed exports */
            'summaryFailed' => __('%d failed:', 'post-to-github-md'),
            /* translators: %d: number of posts selected across all pages */
            'selectionCount' => __('%d posts selected (all pages).', 'post-to-github-md'),
            'selectedLabel' => __('selected', 'post-to-github-md'),
            /* translators: %d: total number of posts matching the current filters */
            'selectAllMatching' => __('Select all %d items matching this filter', 'post-to-github-md'),
            'stopLabel' => __('Stop', 'post-to-github-md'),
            'stopping' => __('Stopping…', 'post-to-github-md'),
            /* translators: %d: number of posts left unprocessed after stopping */
            'summaryStopped' => __('Stopped: %d posts left unprocessed.', 'post-to-github-md'),
            /* translators: %d: seconds to wait before retrying */
            'rateLimitWait' => __('GitHub rate limit reached, waiting %d seconds before retrying...', 'post-to-github-md'),
            /* translators: %d: number of prepared posts */
            'preparing' => __('Preparing #%d...', 'post-to-github-md'),
            'committing' => __('Committing to GitHub...', 'post-to-github-md'),
            /* translators: %d: number of posts included in the commit */
            'summaryCommitted' => __('%d posts committed to GitHub in a single commit.', 'post-to-github-md'),
            /* translators: %d: number of posts that could not be prepared */
            'summaryPrepareFailed' => __('%d could not be prepared:', 'post-to-github-md'),
            /* translators: %s: error message returned by GitHub */
            'commitFailed' => __('Commit failed: %s', 'post-to-github-md'),
            'confirmDeleteFromGithub' => __('Delete this file from the GitHub repository? This creates a removal commit and cannot be undone from here.', 'post-to-github-md'),
        ]);
    }

    if ($hook === 'settings_page_potogh-settings') {
        wp_enqueue_script('potogh-settings', POTOGH_PLUGIN_URL . 'assets/js/settings.js', ['jquery'], POTOGH_VERSION, true);
        wp_localize_script('potogh-settings', 'potoghSettings', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'testing' => __('Testing…', 'post-to-github-md'),
            'networkError' => __('Network error.', 'post-to-github-md'),
        ]);
    }

    if (in_array($hook, ['settings_page_potogh-settings', 'posts_page_potogh-export', 'post.php', 'post-new.php'], true)) {
        wp_enqueue_style('potogh-admin', POTOGH_PLUGIN_URL . 'assets/css/admin.css', [], POTOGH_VERSION);
    }
}
