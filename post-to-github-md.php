<?php
/**
 * Plugin Name: Post to GitHub Markdown
 * Plugin URI: https://github.com/gioxx/wp-post-to-github-md
 * Description: Export published posts as Markdown files to a private GitHub repository.
 * Version: 1.2.7
 * Requires PHP: 7.4
 * Requires at least: 6.0
 * Author: Gioxx
 * Author URI: https://gioxx.org
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: post-to-github-md
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('POTOGH_VERSION', '1.2.7');
define('POTOGH_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('POTOGH_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once POTOGH_PLUGIN_DIR . 'vendor/autoload.php';
require_once POTOGH_PLUGIN_DIR . 'includes/functions.php';

add_action('plugins_loaded', function () {
    load_plugin_textdomain('post-to-github-md', false, dirname(plugin_basename(__FILE__)) . '/languages');

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

    add_action('admin_enqueue_scripts', 'POTOGH\\enqueue_admin_assets');
});
