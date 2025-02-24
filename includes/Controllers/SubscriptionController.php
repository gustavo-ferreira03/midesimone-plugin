<?php

namespace MidesimonePlugin\Controllers;

use MidesimonePlugin\Models\PreferenceModel;
use MidesimonePlugin\Models\SubscriptionModel;
use MidesimonePlugin\Views\SubscriptionView;

class SubscriptionController {
    public static function init() {
        add_action('woocommerce_product_options_general_product_data', [__CLASS__, 'add_preferences_field_admin']);
        add_action('woocommerce_process_product_meta', [SubscriptionModel::class, 'save_preferences_field']);
        
        add_filter('woocommerce_add_to_cart_validation', [SubscriptionModel::class, 'validate_subscription_preferences'], 10, 6);
        add_filter('woocommerce_add_cart_item_data', [SubscriptionModel::class, 'add_subscription_preferences_to_cart_item'], 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', [SubscriptionModel::class, 'add_subscription_preferences_to_order_item'], 10, 4);
        
        add_action('woocommerce_after_add_to_cart_quantity', [__CLASS__, 'display_subscription_preferences']);
        add_action('woocommerce_before_calculate_totals', [SubscriptionModel::class, 'apply_selected_preferences'], 10, 1);
        add_action('woocommerce_after_cart_item_name', [__CLASS__, 'display_subscription_preferences_in_cart'], 10, 2);
        add_filter('woocommerce_get_item_data', [SubscriptionModel::class, 'add_subscription_preferences_to_checkout'], 10, 2);

        if (!wp_next_scheduled('midesimone_subscription_cron_hook')) {
            wp_schedule_event(time(), 'daily', 'midesimone_subscription_cron_hook');
        }
        add_action('midesimone_subscription_cron_hook', [SubscriptionModel::class, 'process_subscription_orders']);
    }

    public static function add_preferences_field_admin() {
        global $post;

        $preferences = PreferenceModel::get_all_preferences();
        $selected_preferences = SubscriptionModel::get_subscription_preferences($post->ID);
        SubscriptionView::render_preference_options($preferences, $selected_preferences);
    }

    public static function display_subscription_preferences() {
        global $post;
    
        if (!is_product()) {
            return;
        }
    
        $selected_ids = SubscriptionModel::get_subscription_preferences($post->ID);
        $preferences = array_filter(array_map('get_post', $selected_ids));
        if (!empty($preferences)) {
            SubscriptionView::render_subscription_preferences($preferences);
        }
    }

    public static function display_subscription_preferences_in_cart($cart_item, $cart_item_key) {
        if (isset($cart_item['subscription_preferences']) && is_array($cart_item['subscription_preferences'])) {
            SubscriptionView::render_subscription_preferences_in_cart($cart_item, $cart_item_key);
        }
    }
}
