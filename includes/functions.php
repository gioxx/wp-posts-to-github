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

    if ($post->post_status !== 'publish' || $post->post_type !== 'post') {
        return ['success' => false, 'error' => __('Solo i post pubblicati possono essere esportati.', 'post-to-github-md')];
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
