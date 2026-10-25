<?php

// Plugin files bail out with exit() when ABSPATH is missing: define it so
// requiring them from tests does not silently end the PHPUnit run.
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/wp-stubs.php';
