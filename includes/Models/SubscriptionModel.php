<?php

namespace MidesimonePlugin\Models;

use DateTime;
use DateTimeZone;
use Exception;
use WC_Cart;
use WC_Subscription;

class SubscriptionModel {
    protected static $current_date = null;

    public static function set_current_date($date) {
        self::$current_date = $date;
    }

    public static function get_current_date() {
        return self::$current_date ?: 'now';
    }
    
    public static function save_preferences_field($post_id) {
        if (isset($_POST['subscription_preferences']) && is_array($_POST['subscription_preferences'])) {
            $sanitized = [];
            foreach ($_POST['subscription_preferences'] as $pref_id => $slug) {
                $sanitized[intval($pref_id)] = sanitize_text_field($slug);
            }
            update_post_meta($post_id, '_subscription_preferences', $sanitized);
        } else {
            delete_post_meta($post_id, '_subscription_preferences');
        }
    }

    public static function get_subscription_preferences($subscription_id) {
        return get_post_meta($subscription_id, '_subscription_preferences', true) ?: [];
    }

    public static function validate_subscription_preferences($passed, $product_id, $quantity, $variation_id = null, $variations = null, $cart_item_data = null) {
        $product = wc_get_product($product_id);
        
        if (!$product->is_type('subscription')) {
            return $passed;
        }

        $required_preferences = self::get_subscription_preferences($product_id);
        
        if (!empty($required_preferences)) {
            if (!isset($_POST['subscription_preferences']) || !is_array($_POST['subscription_preferences'])) {
                wc_add_notice(__('Por favor, selecione todas as preferências requeridas.', 'text-domain'), 'error');
                return false;
            }

            $submitted_preferences = array_keys($_POST['subscription_preferences']);
            $missing = array_diff($required_preferences, $submitted_preferences);

            if (!empty($missing)) {
                wc_add_notice(
                    sprintf(
                        __('Por favor, selecione uma opção para: %s', 'text-domain'),
                        implode(', ', array_map('get_the_title', $missing))
                    ),
                    'error'
                );
                return false;
            }

            foreach ($_POST['subscription_preferences'] as $pref_id => $selected_slug) {
                $valid_slugs = array_column(
                    get_post_meta($pref_id, '_preference_options', true),
                    'slug'
                );
                
                if (!in_array($selected_slug, $valid_slugs)) {
                    wc_add_notice(
                        __('Opção inválida selecionada para: ', 'text-domain') . get_the_title($pref_id),
                        'error'
                    );
                    return false;
                }
            }
        }

        return $passed;
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

    public static function apply_selected_preferences(WC_Cart $cart) {
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
    
        foreach ($subscriptions as $subscription) {
            try {
                $current_month = (new DateTime(self::get_current_date(), new DateTimeZone(wp_timezone_string())))->format('Y-m');
                
                if (!self::should_process_subscription($subscription, $current_month)) {
                    continue;
                }
                
                self::process_products_and_create_order($subscription);

                $subscription->update_meta_data('_last_processed_month', $current_month);
                $subscription->save();
            } catch (Exception $e) {
                error_log("[Midesimone] Erro na assinatura {$subscription->get_id()}: {$e->getMessage()}");
                $subscription->update_status('on-hold', 'Erro no processamento automático');
                $subscription->save();
            }
        }
    }
    
    private static function should_process_subscription(WC_Subscription $subscription, string $current_month): bool {
        if ($subscription->get_meta('_last_processed_month') === $current_month) {
            return false;
        }
    
        $get_month = function(string $date) {
            $dt = new DateTime($date, new DateTimeZone('UTC'));
            $dt->setTimezone(new DateTimeZone(wp_timezone_string()));
            return $dt->format('Y-m');
        };
    
        $created_month = $get_month($subscription->get_date('start'));
        $last_renewal_date = $subscription->get_meta('_last_renewal_date');
        $last_renewal_month = $last_renewal_date ? $get_month($last_renewal_date) : null;
    
        $should_process = $created_month === $current_month || $last_renewal_month === $current_month;
        return $should_process;
    }
    
    private static function process_products_and_create_order(WC_Subscription $subscription): void {
        $preferences = $subscription->get_meta('_selected_subscription_preferences');
        if (empty($preferences)) {
            throw new Exception('Nenhuma preferência selecionada');
        }
    
        $candidates = self::get_candidate_products($preferences);
        if (empty($candidates)) {
            throw new Exception('Nenhum produto disponível');
        }
    
        $selected = self::select_products_for_subscription($subscription, $candidates);
        if (empty($selected)) {
            throw new Exception('Nenhuma combinação válida');
        }
    
        self::create_subscription_order($subscription, $selected);
        
        $total = array_sum(array_column($selected, 'price'));
        $remaining = $subscription->get_total() - $total;
        
        if ($remaining > 0) {
            $subscription->add_order_note("Crédito não utilizado: " . wc_price($remaining));
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
                    'taxonomy' => 'jewelry_preference',
                    'field' => 'slug',
                    'terms' => array_values($selected_preferences),
                    'operator' => 'AND'
                ],
                [
                    'taxonomy' => 'product_visibility',
                    'field' => 'slug',
                    'terms' => ['outofstock'],
                    'operator' => 'NOT IN'
                ]
            ],
            'meta_query' => [
                [
                    'key' => '_stock_status',
                    'value' => 'instock'
                ],
                [
                    'key' => '_price',
                    'value' => 0,
                    'compare' => '>'
                ]
            ]
        ];

        $products = wc_get_products($args);
        return array_map(function($product) {
            return [
                'id' => $product->get_id(),
                'price' => (float) $product->get_price(),
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

        $filtered = array_filter($results, function($row) {
            $stock = get_post_meta($row->ID, '_stock_status', true);
            $price = (float) get_post_meta($row->ID, '_price', true);
            return $stock === 'instock' && $price > 0;
        });

        return array_map(function($row) {
            return [
                'id' => $row->ID,
                'price' => (float) get_post_meta($row->ID, '_price', true),
                'type' => 'variation'
            ];
        }, $filtered);
    }

    public static function select_products_for_subscription($subscription, $candidates) {
        $target = $subscription->get_total();
        return self::findBestCombination($candidates, $target);
    }
    
    public static function create_subscription_order($subscription, $selected_products) {
        $order = wc_create_order([
            'customer_id' => $subscription->get_customer_id(),
            'created_via' => 'subscription_auto_create'
        ]);
        
        foreach ($selected_products as $product) {
            $product_obj = wc_get_product($product['id']);
            if ($product_obj && $product_obj->exists()) {
                $order->add_product($product_obj, 1);
            }
        }
        
        $order->set_address($subscription->get_address('billing'), 'billing');
        $order->set_address($subscription->get_address('shipping'), 'shipping');
        $order->update_meta_data('_subscription_parent', $subscription->get_id());
        $order->calculate_totals();
        $order->save();
        $order->update_status('processing', 'Pedido criado automaticamente via assinatura');

        return $order;
    }

    private static function findBestCombination($products, $target) {
        $target_cents = (int) round($target * 100);
        $products_cents = array_map(function($p) {
            return [
                'id' => $p['id'],
                'price_cents' => (int) round($p['price'] * 100),
                'type' => $p['type']
            ];
        }, $products);
    
        $dp = [0 => ['sum' => 0, 'count' => 0, 'products' => []]];
    
        foreach ($products_cents as $product) {
            for ($s = $target_cents; $s >= $product['price_cents']; $s--) {
                if (isset($dp[$s - $product['price_cents']])) {
                    $new_sum = $dp[$s - $product['price_cents']]['sum'] + $product['price_cents'];
                    $new_count = $dp[$s - $product['price_cents']]['count'] + 1;
                    
                    if ($new_sum > ($dp[$s]['sum'] ?? 0) || 
                       ($new_sum === ($dp[$s]['sum'] ?? 0) && $new_count > $dp[$s]['count'])) {
                        $dp[$s] = [
                            'sum' => $new_sum,
                            'count' => $new_count,
                            'products' => array_merge(
                                $dp[$s - $product['price_cents']]['products'], 
                                [$product]
                            )
                        ];
                    }
                }
            }
        }
    
        $best = ['sum' => 0, 'count' => 0, 'products' => []];
        foreach ($dp as $entry) {
            if ($entry['sum'] > $best['sum'] || 
               ($entry['sum'] === $best['sum'] && $entry['count'] > $best['count'])) {
                $best = $entry;
            }
        }
    
        return array_map(function($p) {
            return [
                'id' => $p['id'],
                'price' => $p['price_cents'] / 100,
                'type' => $p['type']
            ];
        }, $best['products']);
    }
}