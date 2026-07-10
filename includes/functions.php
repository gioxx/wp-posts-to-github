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
        return ['success' => false, 'error' => __('Post not found.', 'post-to-github-md'), 'trace' => []];
    }

    if ($post->post_status !== 'publish' || $post->post_type !== 'post') {
        return ['success' => false, 'error' => __('Only published posts can be exported.', 'post-to-github-md'), 'trace' => []];
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

    return ['success' => true, 'path' => $result['path'], 'exported_at' => $exportedAt, 'trace' => $result['trace']];
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
        wp_enqueue_script('potogh-bulk', POTOGH_PLUGIN_URL . 'assets/js/bulk.js', ['jquery'], POTOGH_VERSION, true);
        wp_localize_script('potogh-bulk', 'potoghBulk', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
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
