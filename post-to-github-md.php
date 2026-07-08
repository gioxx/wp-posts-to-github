<?php
/**
 * Plugin Name: Post to GitHub Markdown
 * Description: Esporta i post pubblicati come file Markdown in un repository GitHub privato.
 * Version: 1.0.0
 * Requires PHP: 7.4
 * Requires at least: 6.0
 * Text Domain: post-to-github-md
 */

if (!defined('ABSPATH')) {
    exit;
}

define('POTOGH_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('POTOGH_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once POTOGH_PLUGIN_DIR . 'vendor/autoload.php';
require_once POTOGH_PLUGIN_DIR . 'includes/functions.php';
