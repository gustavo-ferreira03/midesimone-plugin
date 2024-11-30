<?php

namespace JewelryPlugin;

class Init {
    public static function run() {
        self::load_dependencies();
        self::register_hooks();
    }

    private static function load_dependencies() {
        require_once __DIR__ . '/PostTypes/PackagingPostType.php';
    }

    private static function register_hooks() {
        PostTypes\PackagingPostType::register();
    }
}
