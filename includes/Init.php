<?php

namespace JewelryPlugin;

class Init {
    public static function run() {
        self::load_dependencies();
        self::initialize_controllers();
    }

    private static function load_dependencies() {
        self::load_directory(__DIR__ . '/Controllers');
        self::load_directory(__DIR__ . '/Models');
        self::load_directory(__DIR__ . '/Views');
    }

    private static function load_directory($directory) {
        foreach (glob($directory . '/*.php') as $file) {
            require_once $file;
        }
    }

    private static function initialize_controllers() {
        foreach (get_declared_classes() as $class) {
            if (strpos($class, 'JewelryPlugin\\Controllers\\') === 0 && method_exists($class, 'init')) {
                $class::init();
            }
        }
    }
}
