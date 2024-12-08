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
    
        add_action('woocommerce_product_options_general_product_data', [__CLASS__, 'add_packaging_dropdown']);
        add_action('woocommerce_process_product_meta', [PackagingModel::class, 'save_product_packaging_meta']);
        add_action('woocommerce_checkout_order_processed', [PackagingModel::class, 'reduce_packaging_stock']);
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
    
    public static function add_packaging_dropdown() {
        $packagings = PackagingModel::get_all_packaging();
    
        ?>
        <div class="options_group">
        <?php
            woocommerce_wp_select([
                'id' => '_packaging_id',
                'label' => 'Embalagem',
                'options' => array_reduce($packagings, function ($options, $packaging) {
                    $options[$packaging->ID] = get_post_meta($packaging->ID, '_packaging_name', true);
                    return $options;
                }, ['' => 'Selecione uma embalagem']),
            ]);
        ?>
        </div>
        <?php
    }
}
