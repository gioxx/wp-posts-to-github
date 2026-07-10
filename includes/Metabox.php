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
        <p class="potogh-status potogh-status-<?php echo esc_attr($status); ?>" data-post-id="<?php echo esc_attr($post->ID); ?>">
            <span class="dashicons <?php echo esc_attr(self::statusIconClass($status)); ?>"></span>
            <span class="potogh-status-text"><?php echo esc_html(self::statusLabel($status, $exportedAt)); ?></span>
        </p>
        <button type="button" class="button button-primary potogh-export-button" data-post-id="<?php echo esc_attr($post->ID); ?>">
            <span class="dashicons dashicons-cloud-upload"></span>
            <?php esc_html_e('Export to GitHub', 'post-to-github-md'); ?>
        </button>
        <p class="potogh-export-progress" hidden>
            <span class="potogh-spinner" aria-hidden="true"></span>
            <?php esc_html_e('Exporting…', 'post-to-github-md'); ?>
        </p>
        <div class="potogh-export-message"></div>
        <ul class="potogh-export-trace"></ul>
        <?php
    }

    public static function statusLabel(string $status, ?string $exportedAt): string
    {
        switch ($status) {
            case ExportStatus::EXPORTED:
                return sprintf(__('Exported on %s', 'post-to-github-md'), self::formatExportedAt($exportedAt));
            case ExportStatus::MODIFIED_SINCE_EXPORT:
                return __('Modified since last export', 'post-to-github-md');
            default:
                return __('Never exported', 'post-to-github-md');
        }
    }

    private static function formatExportedAt(?string $exportedAtGmt): string
    {
        $timestamp = $exportedAtGmt !== null ? strtotime($exportedAtGmt) : false;

        if ($timestamp === false) {
            return (string) $exportedAtGmt;
        }

        return wp_date(get_option('date_format') . ' ' . get_option('time_format'), $timestamp);
    }

    public static function statusIconClass(string $status): string
    {
        switch ($status) {
            case ExportStatus::EXPORTED:
                return 'dashicons-yes-alt';
            case ExportStatus::MODIFIED_SINCE_EXPORT:
                return 'dashicons-warning';
            default:
                return 'dashicons-clock';
        }
    }

    public function handleAjaxExport(): void
    {
        $postId = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;

        check_ajax_referer('potogh_export_' . $postId, 'nonce');

        if (!current_user_can('edit_post', $postId)) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'post-to-github-md')], 403);
        }

        if (!Settings::isConfigured()) {
            wp_send_json_error(['message' => __('Configure the PAT and repository in the plugin settings first.', 'post-to-github-md')], 400);
        }

        $result = export_post_by_id($postId);

        if (!$result['success']) {
            wp_send_json_error(['message' => $result['error'], 'trace' => $result['trace']], 500);
        }

        wp_send_json_success([
            'message' => self::statusLabel(ExportStatus::EXPORTED, $result['exported_at']),
            'trace' => $result['trace'],
        ]);
    }
}
