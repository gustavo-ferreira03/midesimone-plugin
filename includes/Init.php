<?php

namespace JewelryPlugin;

class Init {
    public static function run() {
        self::load_dependencies();
    }

    private static function load_dependencies() {
        require_once __DIR__ . '/PostTypes/PackagingPostType.php';
    }
}
