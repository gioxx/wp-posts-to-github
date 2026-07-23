<?php
/**
 * Plugin Name: Posts to GitHub
 * Plugin URI: https://github.com/gioxx/wp-posts-to-github
 * Description: Export published posts as Markdown files to a GitHub repository.
 * Version: 1.5.11
 * Requires PHP: 7.4
 * Requires at least: 6.0
 * Author: Gioxx
 * Author URI: https://gioxx.org
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: posts-to-github-md
 * Domain Path: /languages
 *
 * GitHub Plugin URI: gioxx/wp-posts-to-github
 * GitHub Branch: main
 * GitHub Languages: true
 * Release Asset: true
 */

if (!defined('ABSPATH')) {
    exit;
}

define('POTOGH_VERSION', '1.5.11');
define('POTOGH_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('POTOGH_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once POTOGH_PLUGIN_DIR . 'vendor/autoload.php';
require_once POTOGH_PLUGIN_DIR . 'includes/functions.php';

add_action('plugins_loaded', function () {
    $settings = new \POTOGH\Settings();
    add_action('admin_menu', [$settings, 'registerPage']);
    add_action('admin_init', [$settings, 'registerSetting']);
    add_action('wp_ajax_potogh_test_connection', [$settings, 'handleAjaxTestConnection']);
    add_action('wp_ajax_potogh_detect_branch', [$settings, 'handleAjaxDetectBranch']);

    $metabox = new \POTOGH\Metabox();
    add_action('add_meta_boxes', [$metabox, 'registerMetabox']);
    add_action('wp_ajax_potogh_export_post', [$metabox, 'handleAjaxExport']);

    $exportTab = new \POTOGH\ExportTab();
    add_action('admin_menu', [$exportTab, 'registerPage']);
    add_action('wp_ajax_potogh_bulk_export_one', [$exportTab, 'handleAjaxExportOne']);
    add_action('wp_ajax_potogh_get_filtered_ids', [$exportTab, 'handleAjaxGetFilteredIds']);
    add_action('wp_ajax_potogh_bulk_prepare_one', [$exportTab, 'handleAjaxPrepareOne']);
    add_action('wp_ajax_potogh_bulk_commit_batch', [$exportTab, 'handleAjaxCommitBatch']);
    add_action('wp_ajax_potogh_delete_from_github', [$exportTab, 'handleAjaxDeleteFromGithub']);
    add_action('wp_ajax_potogh_ignore_orphan', [$exportTab, 'handleAjaxIgnoreOrphan']);

    add_action('admin_enqueue_scripts', 'POTOGH\\enqueue_admin_assets');

    add_action('transition_post_status', 'POTOGH\\schedule_auto_export', 10, 3);
    add_action('post_updated', 'POTOGH\\schedule_auto_reexport', 10, 3);
    add_action('potogh_auto_export_event', 'POTOGH\\run_auto_export');

    if (defined('WP_CLI') && WP_CLI) {
        $cli = new \POTOGH\Cli();
        \WP_CLI::add_command('potogh export', [$cli, 'export']);
        \WP_CLI::add_command('potogh bulk-export', [$cli, 'bulkExport']);
    }

    add_filter('plugin_action_links_' . plugin_basename(__FILE__), function (array $links) {
        $settingsLink = '<a href="' . esc_url(\POTOGH\Settings::pageUrl()) . '">' . esc_html__('Settings', 'posts-to-github-md') . '</a>';
        array_unshift($links, $settingsLink);

        return $links;
    });
});
