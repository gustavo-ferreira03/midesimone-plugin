<?php

namespace MidesimonePlugin\Controllers;

use MidesimonePlugin\Models\PreferenceModel;
use MidesimonePlugin\Views\PreferenceView;

class PreferencesController {
    public static function init() {
        add_action('init', [PreferenceModel::class, 'register_post_type']);
        add_action('init', [PreferenceModel::class, 'register_preferences_taxonomy']);
        add_filter('manage_edit-preference_columns', [PreferenceModel::class, 'add_preference_columns']);
        add_action('save_post', [PreferenceModel::class, 'enforce_child_selection']);

        add_action('manage_preference_posts_custom_column', [__CLASS__, 'render_preferences_columns'], 10, 2);
        add_action('save_post_preference', [__CLASS__, 'save_meta_data']);
        add_action('add_meta_boxes', [__CLASS__, 'register_meta_boxes']);

        add_action('woocommerce_product_after_variable_attributes', [__CLASS__, 'add_preferences_to_variations'], 10, 3);
        add_action('woocommerce_save_product_variation', [PreferenceModel::class, 'save_variation_preferences'], 10, 2);
    }

    public static function render_preferences_columns($column, $post_id) {
        $meta_data = PreferenceModel::get_meta_data($post_id);
        PreferenceView::render_preference_columns($column, $meta_data);
    }

    public static function register_meta_boxes() {
        add_meta_box(
            'preferences_meta_box',
            'Detalhes das Preferências',
            [PreferenceView::class, 'render_meta_box'],
            'preference',
            'normal',
            'default'
        );
    }

    public static function save_meta_data($post_id) {
        if (!isset($_POST['preference_meta_nonce']) || 
            !wp_verify_nonce($_POST['preference_meta_nonce'], 'preference_meta_nonce_action')) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        PreferenceModel::save_meta_data($post_id, $_POST);
    }

    
    public static function add_preferences_to_variations($loop, $variation_data, $variation) {
        $preferences = PreferenceModel::get_all_preference_terms();
        $selected = get_post_meta($variation->ID, '_variation_preferences', true) ?: [];
        
        PreferenceView::render_variation_preferences($preferences, $selected, $variation);
    }
}
