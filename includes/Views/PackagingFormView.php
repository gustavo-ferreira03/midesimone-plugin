<?php

namespace JewelryPlugin\Views;

class PackagingView {

    /**
     * Renderiza os metaboxes de "Detalhes da Embalagem".
     *
     * @param \WP_Post $post Post object.
     */
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
        <input type="number" id="packaging_stock_qt" name="packaging_stock_qt" value="<?php echo esc_attr( $stock_qt ); ?>" style="width: 100%;">

        <?php
        wp_nonce_field( 'packaging_meta_nonce_action', 'packaging_meta_nonce' );
    }
}
