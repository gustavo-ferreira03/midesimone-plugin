<?php

namespace JewelryPlugin;

class Init {
    public static function run() {
        self::load_dependencies();
        self::register_hooks();
    }

    private static function load_dependencies() {
        require_once __DIR__ . '/Controllers/PackagingController.php';
        require_once __DIR__ . '/Models/PackagingModel.php';
        require_once __DIR__ . '/Views/PackagingView.php';
    }

    private static function register_hooks() {
        Controllers\PackagingController::init();
    }
}
