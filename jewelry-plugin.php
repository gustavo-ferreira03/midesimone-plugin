<?php

/**
 * Plugin Name: Plugin Loja de Semijoias
 * Plugin URI: https://example.com
 * Description: Plugin para <LOJA>
 * Version: 1.0.0
 * Author: Gustavo Ferreira
 * License: GPLv2 or later
 * Text Domain: jewelry-plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/includes/Init.php';

use JewelryPlugin\Init;

Init::run();