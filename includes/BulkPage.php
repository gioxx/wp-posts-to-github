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
            <h1><?php esc_html_e('Export posts to GitHub', 'post-to-github-md'); ?></h1>
            <?php wp_nonce_field('potogh_bulk_export', 'potogh_bulk_nonce'); ?>
            <p>
                <button type="button" class="button button-primary" id="potogh-bulk-export-selected">
                    <span class="dashicons dashicons-cloud-upload"></span>
                    <?php esc_html_e('Export selected', 'post-to-github-md'); ?>
                </button>
            </p>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="potogh-select-all"></th>
                        <th><?php esc_html_e('Title', 'post-to-github-md'); ?></th>
                        <th><?php esc_html_e('Publish date', 'post-to-github-md'); ?></th>
                        <th><?php esc_html_e('Export status', 'post-to-github-md'); ?></th>
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
                        <td class="potogh-status-cell potogh-status-<?php echo esc_attr($status); ?>">
                            <span class="dashicons <?php echo esc_attr(Metabox::statusIconClass($status)); ?>"></span>
                            <span class="potogh-status-text"><?php echo esc_html(Metabox::statusLabel($status, $exportedAt)); ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div id="potogh-bulk-summary"></div>
            <div id="potogh-bulk-log"></div>
        </div>
        <?php
    }

    public function handleAjaxExportOne(): void
    {
        check_ajax_referer('potogh_bulk_export', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'post-to-github-md')], 403);
        }

        if (!Settings::isConfigured()) {
            wp_send_json_error(['message' => __('Configure the PAT and repository in the plugin settings first.', 'post-to-github-md')], 400);
        }

        $postId = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        $result = export_post_by_id($postId);

        if (!$result['success']) {
            wp_send_json_error(['message' => $result['error'], 'post_id' => $postId, 'trace' => $result['trace']], 500);
        }

        wp_send_json_success([
            'post_id' => $postId,
            'message' => Metabox::statusLabel(ExportStatus::EXPORTED, $result['exported_at']),
            'trace' => $result['trace'],
        ]);
    }
}
