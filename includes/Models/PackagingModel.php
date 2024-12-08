<?php

namespace JewelryPlugin\Models;

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
            'menu_icon'     => 'dashicons-box',
            'rewrite'       => [ 'slug' => 'packaging' ],
        ];

        register_post_type('packaging', $args);
    }

    public static function save_meta_data($post_id, $data) {
        $fields = [
            'packaging_name'        => '_packaging_name',
            'packaging_description' => '_packaging_description',
            'packaging_stock_qt'    => '_packaging_stock_qt',
            'packaging_width'       => '_packaging_width',
            'packaging_height'      => '_packaging_height',
            'packaging_length'      => '_packaging_length',
        ];

        foreach ($fields as $field => $meta_key) {
            if (isset($data[$field])) {
                update_post_meta($post_id, $meta_key, sanitize_text_field($data[$field]));
            }
        }
    }

    public static function get_meta_data($post_id) {
        return [
            'packaging_name'        => get_post_meta($post_id, '_packaging_name', true),
            'packaging_description' => get_post_meta($post_id, '_packaging_description', true),
            'packaging_stock_qt'    => get_post_meta($post_id, '_packaging_stock_qt', true),
        ];
    }

    public static function add_packaging_columns($columns) {
        unset($columns['date']);
        unset($columns['title']);

        $columns['packaging_name'] = 'Nome';
        $columns['packaging_description'] = 'Descrição';
        $columns['packaging_stock_qt'] = 'Quantidade em Estoque';

        return $columns;
    }

    public static function get_all_packaging() {
        $args = [
            'post_type' => 'packaging',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ];
        $query = new \WP_Query($args);
        return $query->posts;
    }
    
    public static function reduce_stock($packaging_id, $quantity) {
        $current_stock = (int) get_post_meta($packaging_id, '_packaging_stock_qt', true);
    
        if ($current_stock >= $quantity) {
            update_post_meta($packaging_id, '_packaging_stock_qt', $current_stock - $quantity);
        } else {
            error_log("Estoque insuficiente para a embalagem ID: $packaging_id");
        }
    }
    
    public static function save_product_packaging_meta($post_id) {
        if (isset($_POST['_packaging_id'])) {
            update_post_meta($post_id, '_packaging_id', sanitize_text_field($_POST['_packaging_id']));
        }
    }
    
    public static function reduce_packaging_stock($order_id) {
        $order = wc_get_order($order_id);
    
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $packaging_id = get_post_meta($product_id, '_packaging', true);
    
            if ($packaging_id) {
                PackagingModel::reduce_stock($packaging_id, $item->get_quantity());
            }
        }
    }   
}
