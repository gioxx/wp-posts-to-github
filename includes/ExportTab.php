<?php

namespace POTOGH;

class ExportTab
{
    private const PER_PAGE_OPTIONS = [10, 25, 50, 100];
    private const DEFAULT_PER_PAGE = 25;
    private const PER_PAGE_META_KEY = 'potogh_export_per_page';

    public function registerPage(): void
    {
        add_posts_page(
            __('Export to GitHub', 'post-to-github-md'),
            __('Export to GitHub', 'post-to-github-md'),
            'manage_options',
            'potogh-export',
            [$this, 'renderPage']
        );
    }

    public static function pageUrl(): string
    {
        return admin_url('edit.php?page=potogh-export');
    }

    public function renderPage(): void
    {
        ?>
        <div class="wrap potogh-settings">
            <h1 class="wp-heading-inline"><?php echo esc_html__('Export posts to GitHub', 'post-to-github-md'); ?></h1>
            <hr class="wp-header-end">
            <?php if (!Settings::isConfigured()) : ?>
                <div class="notice notice-warning inline">
                    <p>
                        <?php
                        printf(
                            /* translators: %s: link to the plugin settings screen */
                            esc_html__('Configure the GitHub Personal Access Token and repository in %s before exporting posts.', 'post-to-github-md'),
                            '<a href="' . esc_url(Settings::pageUrl()) . '">' . esc_html__('Post to GitHub MD settings', 'post-to-github-md') . '</a>'
                        );
                        ?>
                    </p>
                </div>
            <?php else : ?>
                <p>
                    <?php
                    printf(
                        /* translators: %s: link to the plugin settings screen */
                        esc_html__('Need to change the GitHub connection? Head over to %s.', 'post-to-github-md'),
                        '<a href="' . esc_url(Settings::pageUrl()) . '">' . esc_html__('Post to GitHub MD settings', 'post-to-github-md') . '</a>'
                    );
                    ?>
                </p>
                <?php $this->render(); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    public function render(): void
    {
        $perPage = $this->resolvePerPage();
        $filters = $this->filtersFrom($_GET);
        $paged = max(1, (int) ($_GET['paged'] ?? 1));

        $matching = $this->queryMatchingPosts($filters);
        $total = count($matching);
        $posts = self::paginate($matching, $paged, $perPage);
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
        $nonce = wp_create_nonce('potogh_bulk_export');
        ?>
        <div class="potogh-export-tab" data-nonce="<?php echo esc_attr($nonce); ?>" data-total="<?php echo esc_attr($total); ?>">
            <?php wp_nonce_field('potogh_bulk_export', 'potogh_bulk_nonce'); ?>

            <form method="get" action="<?php echo esc_url(admin_url('edit.php')); ?>" class="potogh-filters-form">
                <input type="hidden" name="page" value="potogh-export">

                <p class="search-box">
                    <label class="screen-reader-text" for="potogh-search-input"><?php esc_html_e('Search posts:', 'post-to-github-md'); ?></label>
                    <input type="search" id="potogh-search-input" name="s" value="<?php echo esc_attr($filters['search']); ?>" placeholder="<?php esc_attr_e('Search title…', 'post-to-github-md'); ?>">
                    <input type="submit" class="button" value="<?php esc_attr_e('Search Posts', 'post-to-github-md'); ?>">
                </p>

                <div class="tablenav top">
                    <div class="alignleft actions bulkactions">
                        <button type="button" class="button button-primary" id="potogh-bulk-export-selected" disabled>
                            <span class="dashicons dashicons-cloud-upload"></span>
                            <?php esc_html_e('Export selected', 'post-to-github-md'); ?>
                        </button>
                        <button type="button" class="button" id="potogh-bulk-stop" hidden>
                            <?php esc_html_e('Stop', 'post-to-github-md'); ?>
                        </button>
                    </div>

                    <div class="alignleft actions">
                        <select name="status">
                            <option value=""><?php esc_html_e('All statuses', 'post-to-github-md'); ?></option>
                            <option value="<?php echo esc_attr(ExportStatus::NEVER_EXPORTED); ?>" <?php selected($filters['status'], ExportStatus::NEVER_EXPORTED); ?>><?php esc_html_e('Never exported', 'post-to-github-md'); ?></option>
                            <option value="<?php echo esc_attr(ExportStatus::EXPORTED); ?>" <?php selected($filters['status'], ExportStatus::EXPORTED); ?>><?php esc_html_e('Exported', 'post-to-github-md'); ?></option>
                            <option value="<?php echo esc_attr(ExportStatus::MODIFIED_SINCE_EXPORT); ?>" <?php selected($filters['status'], ExportStatus::MODIFIED_SINCE_EXPORT); ?>><?php esc_html_e('Modified since export', 'post-to-github-md'); ?></option>
                        </select>

                        <?php
                        wp_dropdown_categories([
                            'name' => 'category',
                            'show_option_all' => __('All categories', 'post-to-github-md'),
                            'hide_empty' => false,
                            'selected' => $filters['category'],
                            'orderby' => 'name',
                            'order' => 'ASC',
                        ]);
                        ?>

                        <select name="tag">
                            <option value=""><?php esc_html_e('All tags', 'post-to-github-md'); ?></option>
                            <?php foreach (get_tags(['hide_empty' => false]) as $tag) : ?>
                                <option value="<?php echo esc_attr($tag->slug); ?>" <?php selected($filters['tag'], $tag->slug); ?>><?php echo esc_html($tag->name); ?></option>
                            <?php endforeach; ?>
                        </select>

                        <select name="m">
                            <option value=""><?php esc_html_e('All dates', 'post-to-github-md'); ?></option>
                            <?php foreach ($this->availableMonths() as $month) : ?>
                                <option value="<?php echo esc_attr($month['value']); ?>" <?php selected($filters['month'], $month['value']); ?>><?php echo esc_html($month['label']); ?></option>
                            <?php endforeach; ?>
                        </select>

                        <select name="per_page" onchange="this.form.submit()">
                            <?php foreach (self::PER_PAGE_OPTIONS as $option) : ?>
                                <option value="<?php echo esc_attr($option); ?>" <?php selected($perPage, $option); ?>>
                                    <?php echo esc_html(sprintf(__('%d per page', 'post-to-github-md'), $option)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <button type="submit" class="button"><?php esc_html_e('Filter', 'post-to-github-md'); ?></button>
                    </div>

                    <?php $this->renderPagination($paged, $totalPages, $total); ?>
                    <br class="clear">
                </div>

            <p id="potogh-selection-summary">
                <span id="potogh-selection-count"></span>
                <button type="button" class="button-link" id="potogh-select-all-matching-btn" hidden></button>
                <button type="button" class="button-link" id="potogh-clear-selection-btn" hidden><?php esc_html_e('Clear selection', 'post-to-github-md'); ?></button>
            </p>

            <table class="wp-list-table widefat fixed striped potogh-export-table">
                <thead>
                    <tr>
                        <th scope="col" id="cb" class="manage-column column-cb check-column">
                            <label class="screen-reader-text" for="potogh-select-all"><?php esc_html_e('Select all', 'post-to-github-md'); ?></label>
                            <input type="checkbox" id="potogh-select-all">
                        </th>
                        <th scope="col" class="column-title"><?php esc_html_e('Title', 'post-to-github-md'); ?></th>
                        <th scope="col" class="column-categories"><?php esc_html_e('Categories', 'post-to-github-md'); ?></th>
                        <th scope="col" class="column-tags"><?php esc_html_e('Tags', 'post-to-github-md'); ?></th>
                        <th scope="col" class="column-date"><?php esc_html_e('Publish date', 'post-to-github-md'); ?></th>
                        <th scope="col" class="column-status"><?php esc_html_e('Export status', 'post-to-github-md'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($posts)) : ?>
                    <tr><td colspan="6"><?php esc_html_e('No posts match the current filters.', 'post-to-github-md'); ?></td></tr>
                <?php endif; ?>
                <?php foreach ($posts as $post) :
                    $exportedAt = get_post_meta($post->ID, '_potogh_exported_at', true) ?: null;
                    $status = ExportStatus::determine($exportedAt, $post->post_modified_gmt);
                ?>
                    <tr data-post-id="<?php echo esc_attr($post->ID); ?>">
                        <th scope="row" class="check-column"><input type="checkbox" class="potogh-post-checkbox" value="<?php echo esc_attr($post->ID); ?>"></th>
                        <td class="column-title"><a href="<?php echo esc_url(get_edit_post_link($post)); ?>"><?php echo esc_html(get_the_title($post)); ?></a></td>
                        <td class="column-categories"><?php echo wp_kses_post($this->termLinks(get_the_category($post->ID), 'category', $filters, $perPage)); ?></td>
                        <td class="column-tags"><?php echo wp_kses_post($this->termLinks(get_the_tags($post->ID) ?: [], 'tag', $filters, $perPage)); ?></td>
                        <td class="column-date"><?php echo esc_html(get_the_date('', $post)); ?></td>
                        <td class="column-status potogh-status-cell potogh-status-<?php echo esc_attr($status); ?>">
                            <span class="dashicons <?php echo esc_attr(Metabox::statusIconClass($status)); ?>"></span>
                            <span class="potogh-status-text"><?php echo esc_html(Metabox::statusLabel($status, $exportedAt)); ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <div class="tablenav bottom">
                <?php $this->renderPagination($paged, $totalPages, $total); ?>
                <br class="clear">
            </div>
            </form>

            <div id="potogh-bulk-summary"></div>
        </div>
        <div id="potogh-bulk-footer" class="potogh-bulk-footer" hidden>
            <div id="potogh-bulk-progress" class="potogh-progress">
                <div class="potogh-progress-bar"><div class="potogh-progress-fill"></div></div>
                <span id="potogh-bulk-progress-text"></span>
            </div>
            <div class="potogh-bulk-footer-body">
                <div id="potogh-bulk-log" class="potogh-bulk-log"></div>
                <img src="https://octodex.github.com/images/hula_loop_octodex03.gif" alt="" aria-hidden="true" class="potogh-bulk-mascot">
            </div>
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

    public function handleAjaxGetFilteredIds(): void
    {
        check_ajax_referer('potogh_bulk_export', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'post-to-github-md')], 403);
        }

        $filters = $this->filtersFrom($_POST);
        $posts = $this->queryMatchingPosts($filters);
        $ids = array_map(static function ($post) {
            return $post->ID;
        }, $posts);

        wp_send_json_success(['ids' => $ids, 'total' => count($ids)]);
    }

    public static function filterByStatus(array $postsWithStatus, string $status): array
    {
        return array_values(array_filter($postsWithStatus, static function ($item) use ($status) {
            return $item['status'] === $status;
        }));
    }

    public static function paginate(array $items, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $offset = ($page - 1) * $perPage;

        return array_slice($items, $offset, $perPage);
    }

    private function filtersFrom(array $source): array
    {
        return [
            'status' => sanitize_key($source['status'] ?? ''),
            'search' => sanitize_text_field($source['s'] ?? ''),
            'category' => (int) ($source['category'] ?? 0),
            'tag' => sanitize_title($source['tag'] ?? ''),
            'month' => preg_match('/^\d{6}$/', $source['m'] ?? '') ? $source['m'] : '',
        ];
    }

    private function queryMatchingPosts(array $filters): array
    {
        $args = [
            'post_type' => 'post',
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC',
            'numberposts' => -1,
        ];

        if ($filters['search'] !== '') {
            $args['s'] = $filters['search'];
        }

        if ($filters['category'] > 0) {
            $args['category'] = $filters['category'];
        }

        if ($filters['tag'] !== '') {
            $args['tag'] = $filters['tag'];
        }

        if ($filters['month'] !== '') {
            $args['m'] = $filters['month'];
        }

        if ($filters['status'] === ExportStatus::NEVER_EXPORTED) {
            $args['meta_query'] = [['key' => '_potogh_exported_at', 'compare' => 'NOT EXISTS']];
        } elseif (in_array($filters['status'], [ExportStatus::EXPORTED, ExportStatus::MODIFIED_SINCE_EXPORT], true)) {
            $args['meta_query'] = [['key' => '_potogh_exported_at', 'compare' => 'EXISTS']];
        }

        $posts = get_posts($args);

        if (in_array($filters['status'], [ExportStatus::EXPORTED, ExportStatus::MODIFIED_SINCE_EXPORT], true)) {
            $withStatus = array_map(static function ($post) {
                $exportedAt = get_post_meta($post->ID, '_potogh_exported_at', true) ?: null;

                return ['post' => $post, 'status' => ExportStatus::determine($exportedAt, $post->post_modified_gmt)];
            }, $posts);

            $posts = array_map(static function ($item) {
                return $item['post'];
            }, self::filterByStatus($withStatus, $filters['status']));
        }

        return $posts;
    }

    private function resolvePerPage(): int
    {
        $userId = get_current_user_id();

        if (isset($_GET['per_page']) && in_array((int) $_GET['per_page'], self::PER_PAGE_OPTIONS, true)) {
            $perPage = (int) $_GET['per_page'];
            update_user_meta($userId, self::PER_PAGE_META_KEY, $perPage);

            return $perPage;
        }

        $stored = (int) get_user_meta($userId, self::PER_PAGE_META_KEY, true);

        return in_array($stored, self::PER_PAGE_OPTIONS, true) ? $stored : self::DEFAULT_PER_PAGE;
    }

    private function availableMonths(): array
    {
        global $wpdb;

        $results = $wpdb->get_results(
            "SELECT DISTINCT YEAR(post_date) AS year, MONTH(post_date) AS month FROM {$wpdb->posts}
             WHERE post_type = 'post' AND post_status = 'publish' ORDER BY post_date DESC"
        );

        $months = [];

        foreach ($results as $row) {
            $months[] = [
                'value' => sprintf('%04d%02d', $row->year, $row->month),
                'label' => date_i18n('F Y', mktime(0, 0, 0, (int) $row->month, 1, (int) $row->year)),
            ];
        }

        return $months;
    }

    private function filterUrl(array $filters, int $perPage, array $overrides = []): string
    {
        $args = array_merge([
            'page' => 'potogh-export',
            'status' => $filters['status'],
            's' => $filters['search'],
            'category' => $filters['category'] ?: null,
            'tag' => $filters['tag'],
            'm' => $filters['month'],
            'per_page' => $perPage,
            'paged' => 1,
        ], $overrides);

        $args = array_filter($args, static function ($value) {
            return $value !== null && $value !== '';
        });

        return add_query_arg($args, admin_url('edit.php'));
    }

    private function termLinks(array $terms, string $type, array $filters, int $perPage): string
    {
        if (empty($terms)) {
            return '&#8212;';
        }

        $links = array_map(function ($term) use ($type, $filters, $perPage) {
            $override = $type === 'category' ? ['category' => $term->term_id] : ['tag' => $term->slug];

            return sprintf(
                '<a href="%s">%s</a>',
                esc_url($this->filterUrl($filters, $perPage, $override)),
                esc_html($term->name)
            );
        }, $terms);

        return implode(', ', $links);
    }

    private static int $paginationInstance = 0;

    private function renderPagination(int $paged, int $totalPages, int $total): void
    {
        $baseUrl = remove_query_arg('paged');
        $uid = 'potogh-paging-' . ++self::$paginationInstance;

        echo '<div class="tablenav-pages">';
        printf(
            '<span class="displaying-num">%s</span>',
            esc_html(sprintf(
                /* translators: %d: number of matching posts */
                _n('%d item', '%d items', $total, 'post-to-github-md'),
                $total
            ))
        );

        if ($totalPages > 1) {
            echo '<span class="pagination-links">';

            $this->renderPaginationLink($paged <= 1, 'first-page', esc_url(add_query_arg('paged', 1, $baseUrl)), '&laquo;', __('First page', 'post-to-github-md'));
            $this->renderPaginationLink($paged <= 1, 'prev-page', esc_url(add_query_arg('paged', max(1, $paged - 1), $baseUrl)), '&lsaquo;', __('Previous page', 'post-to-github-md'));

            printf(
                '<span class="paging-input"><label for="%1$s-input" class="screen-reader-text">%2$s</label>' .
                '<input class="current-page" id="%1$s-input" type="text" name="paged" value="%3$d" size="%4$d" aria-describedby="%1$s-text">' .
                '<span class="tablenav-paging-text" id="%1$s-text"> %5$s <span class="total-pages">%6$d</span></span></span>',
                esc_attr($uid),
                esc_html__('Current Page', 'post-to-github-md'),
                $paged,
                max(1, strlen((string) $totalPages)),
                esc_html__('of', 'post-to-github-md'),
                $totalPages
            );

            $this->renderPaginationLink($paged >= $totalPages, 'next-page', esc_url(add_query_arg('paged', min($totalPages, $paged + 1), $baseUrl)), '&rsaquo;', __('Next page', 'post-to-github-md'));
            $this->renderPaginationLink($paged >= $totalPages, 'last-page', esc_url(add_query_arg('paged', $totalPages, $baseUrl)), '&raquo;', __('Last page', 'post-to-github-md'));

            echo '</span>';
        }

        echo '</div>';
    }

    private function renderPaginationLink(bool $disabled, string $class, string $url, string $glyph, string $label): void
    {
        if ($disabled) {
            printf('<span class="tablenav-pages-navspan button disabled" aria-hidden="true">%s</span>', $glyph);

            return;
        }

        printf(
            '<a class="%s button" href="%s"><span class="screen-reader-text">%s</span><span aria-hidden="true">%s</span></a>',
            esc_attr($class),
            $url,
            esc_html($label),
            $glyph
        );
    }
}
