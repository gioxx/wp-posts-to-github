<?php

namespace POTOGH;

/**
 * Export posts to GitHub from the command line.
 */
class Cli
{
    /**
     * Export a single post to GitHub.
     *
     * ## OPTIONS
     *
     * <post_id>
     * : The ID of the published post to export.
     *
     * ## EXAMPLES
     *
     *     wp potogh export 42
     *
     * @param array $args
     * @param array $assocArgs
     */
    public function export(array $args, array $assocArgs): void
    {
        if (!Settings::isConfigured()) {
            \WP_CLI::error(__('Configure the PAT and repository in the plugin settings first.', 'post-to-github-md'));

            return;
        }

        $postId = (int) $args[0];
        $result = export_post_by_id($postId);

        if (!$result['success']) {
            \WP_CLI::error($result['error']);

            return;
        }

        \WP_CLI::success(sprintf('Exported post #%d to %s (sha: %s).', $postId, $result['path'], $result['sha']));
    }

    /**
     * Export multiple published posts to GitHub.
     *
     * ## OPTIONS
     *
     * [--status=<status>]
     * : Only export posts in this export state. Accepts: never_exported, exported, modified_since_export.
     * If omitted, all published posts are considered.
     *
     * [--dry-run]
     * : List the posts that would be exported without exporting them.
     *
     * ## EXAMPLES
     *
     *     wp potogh bulk-export
     *     wp potogh bulk-export --status=never_exported
     *     wp potogh bulk-export --status=modified_since_export --dry-run
     *
     * @param array $args
     * @param array $assocArgs
     */
    public function bulkExport(array $args, array $assocArgs): void
    {
        if (!Settings::isConfigured()) {
            \WP_CLI::error(__('Configure the PAT and repository in the plugin settings first.', 'post-to-github-md'));

            return;
        }

        $status = $assocArgs['status'] ?? '';
        $validStatuses = [ExportStatus::NEVER_EXPORTED, ExportStatus::EXPORTED, ExportStatus::MODIFIED_SINCE_EXPORT];

        if ($status !== '' && !in_array($status, $validStatuses, true)) {
            \WP_CLI::error(sprintf('Unknown status "%s". Use one of: %s.', $status, implode(', ', $validStatuses)));

            return;
        }

        $posts = $this->queryPosts($status);
        $total = count($posts);

        if ($total === 0) {
            \WP_CLI::success('No posts match the given criteria.');

            return;
        }

        if (isset($assocArgs['dry-run'])) {
            \WP_CLI::log(sprintf('%d post(s) would be exported:', $total));

            foreach ($posts as $post) {
                \WP_CLI::log(sprintf('  #%d %s', $post->ID, $post->post_title));
            }

            return;
        }

        $progress = \WP_CLI\Utils\make_progress_bar('Exporting posts', $total);
        $succeeded = 0;
        $failed = [];

        foreach ($posts as $post) {
            $result = export_post_by_id($post->ID);

            if ($result['success']) {
                $succeeded++;
            } else {
                $failed[] = sprintf('#%d: %s', $post->ID, $result['error']);
            }

            $progress->tick();
        }

        $progress->finish();

        \WP_CLI::success(sprintf('%d exported successfully.', $succeeded));

        if (!empty($failed)) {
            \WP_CLI::warning(sprintf('%d failed:', count($failed)));

            foreach ($failed as $line) {
                \WP_CLI::log('  ' . $line);
            }
        }
    }

    private function queryPosts(string $status): array
    {
        $args = [
            'post_type' => 'post',
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC',
            'numberposts' => -1,
        ];

        if ($status === ExportStatus::NEVER_EXPORTED) {
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- required to query the plugin's own export-tracking meta key; no alternative exists.
            $args['meta_query'] = [['key' => '_potogh_exported_at', 'compare' => 'NOT EXISTS']];
        } elseif (in_array($status, [ExportStatus::EXPORTED, ExportStatus::MODIFIED_SINCE_EXPORT], true)) {
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- required to query the plugin's own export-tracking meta key; no alternative exists.
            $args['meta_query'] = [['key' => '_potogh_exported_at', 'compare' => 'EXISTS']];
        }

        $posts = get_posts($args);

        if (!in_array($status, [ExportStatus::EXPORTED, ExportStatus::MODIFIED_SINCE_EXPORT], true)) {
            return $posts;
        }

        $withStatus = array_map(static function ($post) {
            $exportedAt = get_post_meta($post->ID, '_potogh_exported_at', true) ?: null;

            return ['post' => $post, 'status' => ExportStatus::determine($exportedAt, $post->post_modified_gmt)];
        }, $posts);

        return array_map(static function ($item) {
            return $item['post'];
        }, ExportTab::filterByStatus($withStatus, $status));
    }
}
