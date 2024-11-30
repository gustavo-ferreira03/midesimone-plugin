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
            'supports'      => [ 'title', 'editor' ],
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
}
