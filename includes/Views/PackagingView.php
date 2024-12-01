<?php

namespace JewelryPlugin\Views;

class PackagingView {
    public static function render_meta_box( $post ) {
        $name        = get_post_meta( $post->ID, '_packaging_name', true );
        $description = get_post_meta( $post->ID, '_packaging_description', true );
        $stock_qt    = get_post_meta( $post->ID, '_packaging_stock_qt', true );

        ?>
        <label for="packaging_name">Nome:</label>
        <input type="text" id="packaging_name" name="packaging_name" value="<?php echo esc_attr( $name ); ?>" style="width: 100%;"><br><br>

        <label for="packaging_description">Descrição:</label>
        <textarea id="packaging_description" name="packaging_description" style="width: 100%;"><?php echo esc_textarea( $description ); ?></textarea><br><br>

        <label for="packaging_stock_qt">Quantidade em Estoque:</label>
        <input type="number" id="packaging_stock_qt" name="packaging_stock_qt" min="0" value="<?php echo esc_attr( $stock_qt ); ?>" style="width: 100%;">

        <?php
        wp_nonce_field( 'packaging_meta_nonce_action', 'packaging_meta_nonce' );
    }

    public static function render_packaging_columns($column, $meta_data) {
        switch ($column) {
            case 'packaging_name':
                echo esc_html($meta_data['packaging_name'] ?: '(Sem nome)');
                break;

            case 'packaging_description':
                echo esc_html($meta_data['packaging_description'] ?: '(Sem descrição)');
                break;

            case 'packaging_stock_qt':
                echo esc_html($meta_data['packaging_stock_qt'] ?: '0');
                break;
        }
    }
}
