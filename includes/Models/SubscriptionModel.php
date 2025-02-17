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
            foreach ($cart_item['subscription_preferences'] as $preference_id => $selected_option_slug) {
                $preference_post = get_post($preference_id);
                if (!$preference_post) {
                    continue;
                }
                $preference_title = $preference_post->post_title;
    
                $options = get_post_meta($preference_id, '_preference_options', true);
                $option_name = '';
                if (!empty($options) && is_array($options)) {
                    foreach ($options as $option) {
                        if (isset($option['slug']) && $option['slug'] === $selected_option_slug) {
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
    
            foreach ($selected_preferences as $preference_id => $selected_option_slug) {
                $preference_post = get_post($preference_id);
                if (!$preference_post) {
                    continue;
                }
                $preference_title = $preference_post->post_title;
                $options = get_post_meta($preference_id, '_preference_options', true);
                $option_name = '';
    
                if (!empty($options) && is_array($options)) {
                    foreach ($options as $option) {
                        if (isset($option['slug']) && $option['slug'] === $selected_option_slug) {
                            $option_name = $option['name'];
                            break;
                        }
                    }
                }
                if (!empty($option_name)) {
                    $preferences_text .= "\n" . $preference_title . ': ' . $option_name;
                }
            }
    
            if (!empty($selected_preferences)) {
                $item->add_meta_data('_selected_subscription_preferences', $selected_preferences);
                $item->add_meta_data('Preferências', $preferences_text);
            }

            if ($order instanceof \WC_Subscription) {
                $order->update_meta_data('_selected_subscription_preferences', $selected_preferences);
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

                foreach ($cart_item['subscription_preferences'] as $preference_id => $selected_option_slug) {
                    $options = get_post_meta($preference_id, '_preference_options', true);
                    if (!empty($options) && is_array($options)) {
                        foreach ($options as $option) {
                            if (isset($option['slug']) && $option['slug'] === $selected_option_slug) {
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

    public static function process_subscription_orders() {
        $one_hour_ago = date( 'Y-m-d H:i:s', time() - 3600 );
        // TODO: ajustar query para assinaturas recem criadas/renovadas
        $args = [
            'subscription_status' => [ 'active' ],
            // 'date_modified'       => '>' . $one_hour_ago,
        ];
        $subscriptions = wcs_get_subscriptions( $args );
        if ( empty( $subscriptions ) ) {
            return;
        }

        foreach ( $subscriptions as $subscription ) {
            $selected_preferences = $subscription->get_meta('_selected_subscription_preferences');
            
            $subscription_value = floatval( $subscription->get_total() );
            if ( $subscription_value <= 0 ) {
                continue;
            }
            
            $candidate_products = self::get_candidate_products($selected_preferences);
            if ( empty( $candidate_products ) ) {
                continue;
            }
            
            $selected_products = self::maxSubsetSum( $candidate_products, $subscription_value );
            if ( empty( $selected_products ) ) {
                continue;
            }
            
            self::create_subscription_order( $subscription, $selected_products );
        }
    }

    public static function get_candidate_products( $selected_preferences ) {
        if ( empty( $selected_preferences ) || ! is_array( $selected_preferences ) ) {
            return [];
        }
        
        $tax_query = [ 'relation' => 'AND' ];
        foreach ( $selected_preferences as $preference_id => $selected_option_slug ) {
            $tax_query[] = [
                'taxonomy' => 'jewelry_preference',
                'field'    => 'slug',
                'terms'    => $selected_option_slug,
            ];
        }

        $args = [
            'limit'     => -1,
            'status'    => 'publish',
            'tax_query' => $tax_query,
        ];

        $products = wc_get_products( $args );
        $candidate_products = [];
        foreach ( $products as $product ) {
            $candidate_products[] = [
                'id'    => $product->get_id(),
                'price' => floatval( $product->get_price() ),
            ];
        }
        return $candidate_products;
    }
    
    public static function create_subscription_order( $subscription, $selected_products ) {
        if ( empty( $selected_products ) ) {
            return;
        }
        $customer_id      = $subscription->get_customer_id();
        $billing_address  = $subscription->get_address( 'billing' );
        $shipping_address = $subscription->get_address( 'shipping' );
        
        $order = wc_create_order();
        $order->set_customer_id( $customer_id );
        $order->set_address( $billing_address, 'billing' );
        $order->set_address( $shipping_address, 'shipping' );
        
        foreach ( $selected_products as $product ) {
            $order->add_product( wc_get_product( $product['id'] ), 1 );
        }

        $order->calculate_totals();
        $order->update_status( 'processing', 'Pedido criado automaticamente a partir da assinatura.');
    }
    
    // TODO: isolar em outra classe util
    public static function maxSubsetSum( $products, $target ) {
        $n = count( $products );
        $bestSubset = [];
        $bestSum    = 0;
        
        $helper = function( $index, $currentSubset, $currentSum ) use ( $products, $target, &$bestSubset, &$bestSum, $n, &$helper ) {
            if ( $currentSum > $target ) {
                return;
            }
            if ( $currentSum > $bestSum ) {
                $bestSum    = $currentSum;
                $bestSubset = $currentSubset;
            }
            for ( $i = $index; $i < $n; $i++ ) {
                $price = floatval( $products[ $i ]['price'] );
                $newSubset = $currentSubset;
                $newSubset[] = $products[ $i ];
                $helper( $i + 1, $newSubset, $currentSum + $price );
            }
        };
        
        $helper( 0, [], 0 );
        
        if ( $bestSum < $target ) {
            return $products;
        }
        
        return $bestSubset;
    }
}
    