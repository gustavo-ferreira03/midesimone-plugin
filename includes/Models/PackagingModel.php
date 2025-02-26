<?php

namespace MidesimonePlugin\Models;

class PackagingModel {
    public static function register_post_type() {
        $labels = [
            'name'               => 'Embalagens',
            'singular_name'      => 'Embalagem',
            'menu_name'          => 'Embalagens',
            'add_new'            => 'Adicionar Nova',
            'add_new_item'       => 'Adicionar Nova Embalagem',
            'edit_item'          => 'Editar Embalagem',
            'new_item'           => 'Nova Embalagem',
            'view_item'          => 'Ver Embalagem',
            'search_items'       => 'Pesquisar Embalagens',
            'not_found'          => 'Nenhuma embalagem encontrada',
            'not_found_in_trash' => 'Nenhuma embalagem encontrada na lixeira',
        ];

        $args = [
            'label'         => 'Embalagens',
            'labels'        => $labels,
            'public'        => true,
            'has_archive'   => true,
            'supports'      => [ '' ],
            'menu_icon'     => 'dashicons-archive',
            'rewrite'       => [ 'slug' => 'packaging' ],
        ];

        register_post_type('packaging', $args);
    }

    public static function save_meta_data($post_id, $data) {
        $fields = [
            'packaging_name' => [
                'meta_key' => '_packaging_name',
                'sanitize' => 'sanitize_text_field',
                'required' => true
            ],
            'packaging_description' => [
                'meta_key' => '_packaging_description',
                'sanitize' => 'wp_kses_post'
            ],
            'packaging_stock_qt' => [
                'meta_key' => '_packaging_stock_qt',
                'sanitize' => 'intval',
                'validate' => function($value) {
                    return $value >= 0;
                }
            ]
        ];

        foreach ($fields as $field => $config) {
            if (!isset($data[$field])) continue;
            
            $value = call_user_func($config['sanitize'], $data[$field]);
            
            if (isset($config['validate']) && !call_user_func($config['validate'], $value)) {
                continue;
            }
            
            update_post_meta($post_id, $config['meta_key'], $value);
        }

        self::update_linked_products_stock_status($post_id);
    }

    public static function get_meta_data($post_id) {
        return [
            'post_id'               => $post_id,
            'packaging_name'        => get_post_meta($post_id, '_packaging_name', true),
            'packaging_description' => get_post_meta($post_id, '_packaging_description', true),
            'packaging_stock_qt'    => get_post_meta($post_id, '_packaging_stock_qt', true),
            'packaging_edit_link'   => get_edit_post_link($post_id),
        ];
    }

    public static function add_packaging_columns($columns) {
        unset($columns['date']);
        unset($columns['title']);
    
        $columns['packaging_name']      = 'Nome';
        $columns['linked_products']     = 'Produtos Vinculados';
        $columns['packaging_stock_qt']  = 'Quantidade em Estoque';
    
        return $columns;
    }    

    public static function get_all_packagings() {
        $args = [
            'post_type' => 'packaging',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ];
        $query = new \WP_Query($args);
        return $query->posts;
    }

    public static function get_stock($packaging_id) {
        return intval(get_post_meta($packaging_id, '_packaging_stock_qt', true));
    }
    
    public static function reduce_stock($packaging_id, $quantity) {
        $current_stock = self::get_stock($packaging_id);
        if ($current_stock < $quantity) {
            error_log(__('Estoque insuficiente para embalagem ID:', 'text-domain') . $packaging_id);
            return false;
        }
        return self::update_stock_value($packaging_id, $current_stock, $current_stock - $quantity);
    }
    
    public static function increase_stock($packaging_id, $quantity) {
        $quantity = absint($quantity);
        if ($quantity === 0) {
            return false;
        }
        
        $current_stock = self::get_stock($packaging_id);
        return self::update_stock_value($packaging_id, $current_stock, $current_stock + $quantity);
    }
    
    private static function update_stock_value($packaging_id, $old_stock, $new_stock) {
        global $wpdb;
        
        $updated = $wpdb->update(
            $wpdb->postmeta,
            ['meta_value' => $new_stock],
            [
                'post_id'   => $packaging_id,
                'meta_key'  => '_packaging_stock_qt',
                'meta_value'=> $old_stock
            ],
            ['%d'],
            ['%d', '%s', '%d']
        );
        
        if ($updated !== false) {
            wp_cache_delete($packaging_id, 'post_meta');
            self::update_linked_products_stock_status($packaging_id);
            return true;
        }
        
        return false;
    }

    public static function handle_order_status_change($order_id, $old_status, $new_status, $order) {
        $restore_statuses = ['cancelled', 'refunded', 'failed'];
        if (in_array($new_status, $restore_statuses)) {
            self::restore_stock_from_order($order);
        }

        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product) continue;
    
            $packaging_id = get_post_meta($product->get_id(), '_packaging_id', true);
            if ($packaging_id) {
                self::update_linked_products_stock_status($packaging_id);
            }
        }
    }

    public static function restore_stock_from_order($order) {
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product) continue;

            $packaging_id = get_post_meta($product->get_id(), '_packaging_id', true);
            $quantity = $item->get_quantity();

            if ($packaging_id) {
                self::increase_stock($packaging_id, $quantity);
            }
        }
    }

    public static function handle_packaging_deletion($post_id) {
        if ('packaging' !== get_post_type($post_id)) return;

        $linked_products = get_posts([
            'post_type' => 'product',
            'meta_key' => '_packaging_id',
            'meta_value' => $post_id,
            'fields' => 'ids',
            'posts_per_page' => -1
        ]);

        foreach ($linked_products as $product_id) {
            delete_post_meta($product_id, '_packaging_id');
            error_log(sprintf(
                __('Embalagem ID %1$s removida do produto ID %2$s', 'text-domain'),
                $post_id,
                $product_id
            ));
        }
    }
    
    public static function save_product_packaging_meta($post_id) {
        if (isset($_POST['_packaging_id'])) {
            update_post_meta($post_id, '_packaging_id', sanitize_text_field($_POST['_packaging_id']));
            $packaging_id = sanitize_text_field($_POST['_packaging_id']);
            self::update_linked_products_stock_status($packaging_id);
        }
    }
    
    public static function order_reduce_packaging_stock($order) {    
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $product_id = $product->is_type('variation') ? $product->get_parent_id() : $product->get_id();
            $packaging_id = get_post_meta($product_id, '_packaging_id', true);
    
            if ($packaging_id) {
                PackagingModel::reduce_stock($packaging_id, $item->get_quantity());
            }
        }
    }

    public static function validate_cart_item_stock() {
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $product = $cart_item['data'];
            $product_id = $product->is_type('variation') ? $product->get_parent_id() : $product->get_id();
            $packaging_id = get_post_meta($product_id, '_packaging_id', true);
            
            if (!$packaging_id) continue;
            
            $required_stock = $cart_item['quantity'];
            $available_stock = self::get_stock($packaging_id);
            
            if ($available_stock < $required_stock) {
                $message = sprintf(
                    __('Não há estoque suficiente de embalagens para "%s". Disponível: %d', 'text-domain'),
                    $product->get_name(),
                    $available_stock
                );
                wc_add_notice($message, 'error');
                WC()->cart->set_quantity($cart_item_key, 0);
            }
        }
    }

    public static function restore_packaging_stock($order_id, $items) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $product_id = $product->is_type('variation') ? $product->get_parent_id() : $product->get_id();
            $packaging_id = get_post_meta($product_id, '_packaging_id', true);
            $quantity = $item->get_quantity();

            if ($packaging_id) {
                $current_stock = PackagingModel::get_stock($packaging_id);
                $new_stock = $current_stock + $quantity;
                update_post_meta($packaging_id, '_packaging_stock_qt', $new_stock);
            }
        }
    }

    public static function get_linked_products($packaging_id) {
        return get_posts([
            'post_type' => ['product', 'product_variation'],
            'meta_key' => '_packaging_id',
            'meta_value' => $packaging_id,
            'fields' => 'ids',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key' => '_packaging_id',
                    'value' => $packaging_id,
                    'compare' => '='
                ]
            ]
        ]);
    }

    public static function update_linked_products_stock_status($packaging_id) {
        $linked_products = self::get_linked_products($packaging_id);
        $packaging_stock = self::get_stock($packaging_id);
    
        foreach ($linked_products as $product_id) {
            $product = wc_get_product($product_id);
            if (!$product) continue;
    
            $new_status = $packaging_stock > 0 ? 'instock' : 'outofstock';
            $product->set_stock_status($new_status);
            $product->save();
        }
    }

    public static function get_product_stock_status($status, $product) {
        $product_id = $product->is_type('variation') ? $product->get_parent_id() : $product->get_id();
        $packaging_id = get_post_meta($product_id, '_packaging_id', true);
        
        if ($packaging_id) {
            $packaging_stock = self::get_stock($packaging_id);
            return $packaging_stock > 0 ? 'instock' : 'outofstock';
        }
        
        return $status;
    }
}
