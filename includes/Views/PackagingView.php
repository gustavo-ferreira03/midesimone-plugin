<?php

namespace MidesimonePlugin\Views;

use MidesimonePlugin\Models\PackagingModel;

class PackagingView {
    public static function render_meta_box($post) {
        $name = get_post_meta($post->ID, '_packaging_name', true);
        $description = get_post_meta($post->ID, '_packaging_description', true);
        $stock_qt = get_post_meta($post->ID, '_packaging_stock_qt', true);

        ?>
        <div class="packaging-metabox">
            <div class="form-field">
                <label for="packaging_name"><?php esc_html_e('Nome:*', 'text-domain'); ?></label>
                <input type="text" required id="packaging_name" name="packaging_name" value="<?php echo esc_attr($name); ?>" class="widefat">
            </div>

            <div class="form-field">
                <label for="packaging_description"><?php esc_html_e('Descrição:', 'text-domain'); ?></label>
                <textarea id="packaging_description" name="packaging_description" class="widefat"><?php echo esc_textarea($description); ?></textarea>
            </div>

            <div class="form-field">
                <label for="packaging_stock_qt"><?php esc_html_e('Quantidade em Estoque:', 'text-domain'); ?></label>
                <input type="number" required id="packaging_stock_qt" name="packaging_stock_qt" min="0" step="1" value="<?php echo esc_attr($stock_qt); ?>" class="widefat">
            </div>

            <?php wp_nonce_field('packaging_meta_nonce_action', 'packaging_meta_nonce'); ?>
        </div>
        <style>
            .packaging-metabox .form-field { margin-bottom: 20px; }
            .packaging-metabox label { display: block; margin-bottom: 5px; font-weight: 600; }
        </style>
        <?php
    }

    public static function render_packaging_columns($column, $meta_data) {
        switch ($column) {
            case 'packaging_name':
                printf('<a href="%s">%s</a>', 
                    esc_url($meta_data['packaging_edit_link']), 
                    esc_html($meta_data['packaging_name'] ?: __('(Sem nome)', 'text-domain'))
                );
                break;
    
            case 'linked_products':
                $product_ids = PackagingModel::get_linked_products($meta_data['post_id']);
                $links = [];
                
                foreach ($product_ids as $product_id) {
                    $edit_link = get_edit_post_link($product_id);
                    $title = get_the_title($product_id);
                    
                    if ($edit_link && $title) {
                        $links[] = sprintf(
                            '<a href="%s" target="_blank">%s</a>',
                            esc_url($edit_link),
                            esc_html($title)
                        );
                    }
                }
                
                echo $links ? implode(', ', $links) : '─';
                break;
    
            case 'packaging_stock_qt':
                $stock = $meta_data['packaging_stock_qt'] ?? 0;
                $color = $stock == 0 ? 'red' : 'inherit';
                printf('<span style="color: %s; font-weight: bold;">%d</span>', esc_attr($color), esc_html($stock));
                break;
        }
    }
    

    public static function render_packaging_dropdown($packagings) {
        ?>
        <div class="options_group show_if_simple show_if_variable hide_if_grouped hide_if_external hide_if_subscription">
            <?php
            woocommerce_wp_select([
                'id' => '_packaging_id',
                'label' => __('Embalagem', 'text-domain'),
                'description' => __('Selecione a embalagem necessária para este produto', 'text-domain'),
                'desc_tip' => true,
                'options' => array_reduce($packagings, function($options, $packaging) {
                    $options[$packaging->ID] = sprintf('%s (Estoque: %d)', 
                        get_post_meta($packaging->ID, '_packaging_name', true),
                        PackagingModel::get_stock($packaging->ID)
                    );
                    return $options;
                }, ['' => __('Nenhuma', 'text-domain')]),
                'wrapper_class' => 'show_if_simple show_if_variable hide_if_grouped hide_if_external',
                'custom_attributes' => [
                    'data-allow-clear' => 'true',
                    'data-placeholder' => __('Selecione uma embalagem', 'text-domain')
                ]
            ]);
            ?>
        </div>
        <?php
    }
}