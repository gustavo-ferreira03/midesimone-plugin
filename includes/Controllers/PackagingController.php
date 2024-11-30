<?php

namespace JewelryPlugin\Controllers;

use JewelryPlugin\Models\PackagingModel;
use JewelryPlugin\Views\PackagingView;

class PackagingController {

    public static function init() {
        add_action('init', [__CLASS__, 'register_post_type']);
        add_action('add_meta_boxes', [__CLASS__, 'register_meta_boxes']);
        add_action('save_post_packaging', [__CLASS__, 'save_meta_data']);
    }

    public static function register_post_type() {
        PackagingModel::register_post_type();
    }

    public static function register_meta_boxes() {
        add_meta_box(
            'packaging_meta_box',
            'Detalhes da Embalagem',
            [PackagingView::class, 'render_meta_box'],
            'packaging',
            'normal',
            'default'
        );
    }

    public static function save_meta_data($post_id) {
        if (!isset($_POST['packaging_meta_nonce']) || 
            !wp_verify_nonce($_POST['packaging_meta_nonce'], 'packaging_meta_nonce_action')) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        PackagingModel::save_meta_data($post_id, $_POST);
    }
}
