<?php

namespace MidesimonePlugin\Models;

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
    
    public static function register_preferences_taxonomy() {
        $labels = [
            'name'              => 'Preferências para Assinaturas',
            'singular_name'     => 'Preferência de Joia',
            'search_items'      => 'Buscar Preferências',
            'all_items'         => 'Todas as Preferências',
            'edit_item'         => 'Editar Preferência',
            'update_item'       => 'Atualizar Preferência',
            'add_new_item'      => 'Adicionar Nova Preferência',
            'new_item_name'     => 'Nova Preferência',
            'menu_name'         => 'Preferências para Assinaturas',
        ];
    
        $args = [
            'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => ['slug' => 'jewelry-preference'],
        ];
    
        register_taxonomy('jewelry_preference', ['product'], $args);
    }

    public static function enforce_child_selection($post_id) {
        if (get_post_type($post_id) !== 'product') {
            return;
        }
    
        $terms = wp_get_post_terms($post_id, 'jewelry_preference');
    
        if (!empty($terms)) {
            $parent_terms = [];
            foreach ($terms as $term) {
                if ($term->parent > 0) {
                    $parent_terms[] = $term->parent;
                }
            }
            if (!empty($parent_terms)) {
                wp_set_object_terms($post_id, array_unique($parent_terms), 'jewelry_preference', true);
            }
        }
    }

    public static function save_meta_data($post_id, $data) {
        if (isset($data['preference_description'])) {
            update_post_meta($post_id, '_preference_description', sanitize_textarea_field($data['preference_description']));
        }
    
        if (isset($data['preference_options']) && is_array($data['preference_options'])) {
            $sanitized_options = array_map(function ($option) {
                return [
                    'name'  => sanitize_text_field($option['name']),
                    'slug'  => sanitize_title($option['name']),
                    'value' => isset($option['value']) ? floatval($option['value']) : 0,
                ];
            }, $data['preference_options']);
    
            update_post_meta($post_id, '_preference_options', $sanitized_options);
            self::create_preference_taxonomy($post_id, $sanitized_options);
        }
    }
    
    public static function create_preference_taxonomy($post_id, $options) {
        self::delete_preference_taxonomy($post_id);
        
        $preference_name = get_the_title($post_id);
        $parent_term = wp_insert_term($preference_name, 'jewelry_preference');
        $parent_term_id = $parent_term['term_id'] ?? 0;
        
        if ($parent_term_id) {
            update_term_meta($parent_term_id, '_preference_id', $post_id);
            
            if (is_array($options)) {
                foreach ($options as $option) {
                    if (!empty($option['name'])) {
                        $option_slug = sanitize_title($option['name']);
                        
                        $subterm = wp_insert_term(
                            $option['name'],
                            'jewelry_preference',
                            [
                                'parent' => $parent_term_id,
                                'slug'   => $option_slug,
                            ]
                        );
                        
                        if (!is_wp_error($subterm) && isset($subterm['term_id'])) {
                            update_term_meta($subterm['term_id'], '_preference_id', $post_id);
                        }
                    }
                }
            }
        }
    }

    // TODO: ON DELETE PREFERENCE, DELETE TAXONOMY
    public static function delete_preference_taxonomy($post_id) {
        $terms = get_terms([
            'taxonomy'   => 'jewelry_preference',
            'meta_query' => [
                [
                    'key'   => '_preference_id',
                    'value' => $post_id,
                ]
            ],
            'hide_empty' => false,
        ]);
    
        if (!empty($terms) && !is_wp_error($terms)) {
            foreach ($terms as $term) {
                wp_delete_term($term->term_id, 'jewelry_preference');
            }
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

    public static function get_all_preference_terms() {
        return get_terms([
            'taxonomy' => 'jewelry_preference',
            'hide_empty' => false,
            'parent' => 0,
        ]);
    }

    public static function save_variation_preferences($variation_id, $i) {
        if (isset($_POST['variation_preferences'][$variation_id])) {
            $slugs = array_map('sanitize_text_field', $_POST['variation_preferences'][$variation_id]);
            update_post_meta($variation_id, '_variation_preferences', $slugs);
        } else {
            delete_post_meta($variation_id, '_variation_preferences');
        }
    }
}
