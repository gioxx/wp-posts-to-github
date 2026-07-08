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
