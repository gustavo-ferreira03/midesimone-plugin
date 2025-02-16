<?php

/**
 * Plugin Name: Midesimone Plugin
 * Description: Exclusive plugin for Midesimone
 * Version: 1.0.0
 * Author: Gustavo Ferreira
 * License: GPLv2 or later
 * Text Domain: midesimone-plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/includes/Init.php';

use MidesimonePlugin\Init;

Init::run();