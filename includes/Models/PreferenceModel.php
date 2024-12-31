<?php

namespace JewelryPlugin\Models;

class PreferenceModel {
    public static function register_post_type() {
        $labels = [
            'name'               => 'Preferências',
            'singular_name'      => 'Preferência',
            'menu_name'          => 'Preferências para Assinaturas',
            'add_new'            => 'Adicionar Nova',
            'add_new_item'       => 'Adicionar Nova Preferência',
            'edit_item'          => 'Editar Preferência',
            'new_item'           => 'Nova Preferência',
            'view_item'          => 'Ver Preferência',
            'search_items'       => 'Pesquisar Preferências',
            'not_found'          => 'Nenhuma preferência encontrada',
            'not_found_in_trash' => 'Nenhuma preferência encontrada na lixeira',
        ];

        $args = [
            'label'         => 'Preferências',
            'labels'        => $labels,
            'public'        => false,
            'has_archive'   => false,
            'supports'      => ['title'],
            'menu_icon'     => 'dashicons-admin-settings',
            'rewrite'       => ['slug' => 'preference'],
            'show_ui'       => true,
            'show_in_menu'  => true,
        ];

        register_post_type('preference', $args);
    }

    public static function save_meta_data($post_id, $data) {
        if (isset($data['preference_description'])) {
            update_post_meta($post_id, '_preference_description', sanitize_textarea_field($data['preference_description']));
        }

        if (isset($data['preference_options']) && is_array($data['preference_options'])) {
            $sanitized_options = array_map(function ($option) {
                return [
                    'name'  => sanitize_text_field($option['name'] ?? ''),
                    'value' => isset($option['value']) ? floatval($option['value']) : 0,
                ];
            }, $data['preference_options']);

            update_post_meta($post_id, '_preference_options', $sanitized_options);
        }
    }

    public static function get_meta_data($post_id) {
        return [
            'preference_name'        => get_post_meta($post_id, '_preference_name', true),
            'preference_description' => get_post_meta($post_id, '_preference_description', true),
            'preference_options'     => get_post_meta($post_id, '_preference_options', true),
        ];
    }

    public static function add_preference_columns($columns) {
        unset($columns['date']);
        $columns['preference_description'] = 'Descrição';
        $columns['preference_options'] = 'Opções';

        return $columns;
    }

    public static function get_all_preferences() {
        $args = [
            'post_type'      => 'preference',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ];
        $query = new \WP_Query($args);
        return $query->posts;
    }
}
