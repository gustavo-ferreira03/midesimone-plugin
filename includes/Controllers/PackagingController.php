<?php

namespace JewelryPlugin\Controllers;

use JewelryPlugin\Models\PackagingModel;
use JewelryPlugin\Views\PackagingView;

class PackagingController {

    public static function init() {
        add_action('init', [PackagingModel::class, 'register_post_type']);
        add_filter('manage_edit-packaging_columns', [PackagingModel::class, 'add_packaging_columns']);
        add_action('manage_packaging_posts_custom_column', [__CLASS__, 'render_packaging_columns'], 10, 2);
        add_action('save_post_packaging', [__CLASS__, 'save_meta_data']);
        add_action('add_meta_boxes', [__CLASS__, 'register_meta_boxes']);
    }

    public static function render_packaging_columns($column, $post_id) {
        $meta_data = PackagingModel::get_meta_data($post_id);
        PackagingView::render_packaging_columns($column, $meta_data);
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
