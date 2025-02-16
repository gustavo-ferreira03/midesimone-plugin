<?php

namespace MidesimonePlugin\Models;

class SubscriptionModel {
    public static function save_preferences_field($post_id) {
        if ( isset($_POST['subscription_preferences']) && is_array($_POST['subscription_preferences']) ) {
            update_post_meta( $post_id, '_subscription_preferences', array_map('intval', $_POST['subscription_preferences']) );
        } else {
            delete_post_meta( $post_id, '_subscription_preferences' );
        }
    }

    public static function get_subscription_preferences($subscription_id) {
        return get_post_meta($subscription_id, '_subscription_preferences', true) ?: [];
    }

    public static function add_subscription_preferences_to_cart_item($cart_item_data, $product_id) {
        if (isset($_POST['subscription_preferences']) && is_array($_POST['subscription_preferences'])) {
            $preferences = [];
            foreach ($_POST['subscription_preferences'] as $pref_id => $selected_option) {
                $preferences[sanitize_text_field($pref_id)] = sanitize_text_field($selected_option);
            }
            $cart_item_data['subscription_preferences'] = $preferences;
            $cart_item_data['unique_key'] = md5(microtime() . rand());
        }
        return $cart_item_data;
    }

    public static function add_subscription_preferences_to_checkout($item_data, $cart_item) {
        if (isset($cart_item['subscription_preferences']) && is_array($cart_item['subscription_preferences'])) {
            foreach ($cart_item['subscription_preferences'] as $preference_id => $selected_option_id) {
                $preference_post = get_post($preference_id);
                if (!$preference_post) {
                    continue;
                }
                $preference_title = $preference_post->post_title;
    
                $options = get_post_meta($preference_id, '_preference_options', true);
                $option_name = '';
                if (!empty($options) && is_array($options)) {
                    foreach ($options as $option) {
                        if (isset($option['id']) && $option['id'] === $selected_option_id) {
                            $option_name = $option['name'];
                            break;
                        }
                    }
                }
    
                if (!empty($option_name)) {
                    $item_data[] = [
                        'name'   => $preference_title,
                        'value' => $option_name,
                    ];
                }
            }
        }
        return $item_data;
    }

    public static function add_subscription_preferences_to_order_item($item, $cart_item_key, $values, $order) {
        if (isset($values['subscription_preferences'])) {
            $selected_preferences = $values['subscription_preferences'];
            $preferences_text = '';
    
            foreach ($selected_preferences as $preference_id => $selected_option_id) {
                $preference_post = get_post($preference_id);
                if (!$preference_post) {
                    continue;
                }
                $preference_title = $preference_post->post_title;
                $options = get_post_meta($preference_id, '_preference_options', true);
                $option_name = '';
    
                if (!empty($options) && is_array($options)) {
                    foreach ($options as $option) {
                        if (isset($option['id']) && $option['id'] === $selected_option_id) {
                            $option_name = $option['name'];
                            break;
                        }
                    }
                }
                if (!empty($option_name)) {
                    $preferences_text .= $preference_title . ': ' . $option_name . "\n";
                }
            }
    
            if (!empty($preferences_text)) {
                $item->add_meta_data('Preferências', $preferences_text);
                $item->add_meta_data('_selected_subscription_preferences', $selected_preferences);
            }
        }
    }
    

    public static function apply_selected_preferences($cart) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }
        
        foreach ($cart->get_cart() as $cart_item) {
            if (isset($cart_item['subscription_preferences']) && !empty($cart_item['subscription_preferences'])) {
                $product_id = $cart_item['product_id'];
                $base_price = get_post_meta($product_id, '_price', true);
                $extra_cost = 0;

                foreach ($cart_item['subscription_preferences'] as $preference_id => $selected_option_id) {
                    $options = get_post_meta($preference_id, '_preference_options', true);
                    if (!empty($options) && is_array($options)) {
                        foreach ($options as $option) {
                            if (isset($option['id']) && $option['id'] === $selected_option_id) {
                                $extra_cost += floatval($option['value']);
                                break;
                            }
                        }
                    }
                }
                
                $cart_item['data']->set_price(number_format($base_price + $extra_cost, 2, '.', ''));
            }
        }
    }
}
