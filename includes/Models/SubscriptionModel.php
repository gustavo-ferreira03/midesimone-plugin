<?php

namespace MidesimonePlugin\Models;

class SubscriptionModel {
    public static function save_preferences_field($post_id) {
        if (isset($_POST['subscription_preferences']) && is_array($_POST['subscription_preferences'])) {
            update_post_meta($post_id, '_subscription_preferences', array_map('intval', $_POST['subscription_preferences']));
        } else {
            delete_post_meta($post_id, '_subscription_preferences');
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
                if (!$preference_post) continue;

                $option_name = self::get_preference_option_name($preference_id, $selected_option_slug);
                
                if ($option_name) {
                    $item_data[] = [
                        'name' => $preference_post->post_title,
                        'value' => $option_name,
                    ];
                }
            }
        }
        return $item_data;
    }

    private static function get_preference_option_name($preference_id, $selected_slug) {
        $options = get_post_meta($preference_id, '_preference_options', true);
        foreach ($options as $option) {
            if (isset($option['slug']) && $option['slug'] === $selected_slug) {
                return $option['name'];
            }
        }
        return '';
    }

    public static function add_subscription_preferences_to_order_item($item, $cart_item_key, $values, $order) {
        if (isset($values['subscription_preferences'])) {
            $selected_preferences = $values['subscription_preferences'];
            $preferences_text = [];

            foreach ($selected_preferences as $preference_id => $selected_option_slug) {
                $option_name = self::get_preference_option_name($preference_id, $selected_option_slug);
                if ($option_name) {
                    $preferences_text[] = get_the_title($preference_id) . ': ' . $option_name;
                }
            }

            if (!empty($preferences_text)) {
                $item->add_meta_data('Preferências', implode(', ', $preferences_text));
                $item->add_meta_data('_selected_subscription_preferences', $selected_preferences);
            }
        }
    }

    public static function apply_selected_preferences($cart) {
        if (is_admin() && !defined('DOING_AJAX')) return;

        foreach ($cart->get_cart() as $cart_item) {
            if (!empty($cart_item['subscription_preferences'])) {
                $product = $cart_item['data'];
                $base_price = get_post_meta($product->get_id(), '_price', true);
                $extra_cost = 0;

                foreach ($cart_item['subscription_preferences'] as $preference_id => $selected_slug) {
                    $extra_cost += self::get_preference_option_value($preference_id, $selected_slug);
                }

                $product->set_price($base_price + $extra_cost);
            }
        }
    }

    private static function get_preference_option_value($preference_id, $selected_slug) {
        $options = get_post_meta($preference_id, '_preference_options', true);
        foreach ($options as $option) {
            if (isset($option['slug']) && $option['slug'] === $selected_slug) {
                return floatval($option['value'] ?? 0);
            }
        }
        return 0;
    }

    public static function process_subscription_orders() {
        $subscriptions = wcs_get_subscriptions(['subscription_status' => 'active']);
        if (empty($subscriptions)) return;

        foreach ($subscriptions as $subscription) {
            $selected_preferences = $subscription->get_meta('_selected_subscription_preferences');
            if (empty($selected_preferences)) continue;

            $candidate_products = self::get_candidate_products($selected_preferences);
            if (empty($candidate_products)) continue;

            $selected_products = self::select_products_for_subscription($subscription, $candidate_products);
            if (!empty($selected_products)) {
                self::create_subscription_order($subscription, $selected_products);
            }
        }
    }

    public static function get_candidate_products($selected_preferences) {
        $candidates = [];
        $candidates = array_merge($candidates, self::get_products_by_taxonomy($selected_preferences));
        $candidates = array_merge($candidates, self::get_variations_by_meta($selected_preferences));
        
        return $candidates;
    }

    private static function get_products_by_taxonomy($selected_preferences) {
        $args = [
            'limit' => -1,
            'status' => 'publish',
            'type' => 'simple',
            'tax_query' => [
                [
                    'taxonomy' => 'product_visibility',
                    'field'    => 'slug',
                    'terms'    => ['outofstock'],
                    'operator' => 'NOT IN'
                ]
            ],
            'meta_query' => [
                [
                    'key' => '_stock_status',
                    'value' => 'instock'
                ]
            ]
        ];

        $products = wc_get_products($args);
        return array_map(function($product) {
            return [
                'id' => $product->get_id(),
                'price' => get_post_meta($product->get_id(), '_price', true),
                'type' => 'simple'
            ];
        }, $products);
    }

    private static function get_variations_by_meta($selected_preferences) {
        global $wpdb;

        if (empty($selected_preferences)) return [];

        $slugs = array_map('esc_sql', array_values($selected_preferences));
        $slug_patterns = array_map(function($slug) {
            $length = strlen($slug);
            return 's:' . $length . ':"' . $slug . '"';
        }, $slugs);

        $regex_pattern = implode('|', $slug_patterns);

        $query = $wpdb->prepare("SELECT posts.ID, meta.meta_value 
            FROM {$wpdb->posts} posts
            INNER JOIN {$wpdb->postmeta} meta 
                ON posts.ID = meta.post_id
                AND meta.meta_key = '_variation_preferences'
            WHERE posts.post_type = 'product_variation'
            AND meta.meta_value REGEXP %s
        ", $regex_pattern);

        $results = $wpdb->get_results($query);
        
        return array_map(function($row) {
            return [
                'id' => $row->ID,
                'price' => get_post_meta($row->ID, '_price', true),
                'type' => 'variation'
            ];
        }, $results);
    }

    private static function select_products_for_subscription($subscription, $candidates) {
        $target = $subscription->get_total();
        return self::findBestCombination($candidates, $target);
    }

    private static function findBestCombination($products, $target) {
        $bestCombination = [];
        $bestSum = 0;

        $backtrack = function($start, $current, $currentSum) use (&$bestSum, &$bestCombination, $products, $target, &$backtrack) {
            if ($currentSum > $target) return;
            if ($currentSum > $bestSum) {
                $bestSum = $currentSum;
                $bestCombination = $current;
            }
            for ($i = $start; $i < count($products); $i++) {
                $backtrack(
                    $i + 1,
                    array_merge($current, [$products[$i]]),
                    $currentSum + $products[$i]['price']
                );
            }
        };

        $backtrack(0, [], 0);
        return $bestCombination;
    }

    public static function create_subscription_order($subscription, $selected_products) {
        $order = wc_create_order([
            'customer_id' => $subscription->get_customer_id(),
        ]);

        foreach ($selected_products as $product) {
            $order->add_product(wc_get_product($product['id']), 1);
        }

        $order->set_address($subscription->get_address('billing'), 'billing');
        $order->set_address($subscription->get_address('shipping'), 'shipping');
        $order->calculate_totals();
        $order->update_status('processing', 'Pedido criado automaticamente');
    }
}