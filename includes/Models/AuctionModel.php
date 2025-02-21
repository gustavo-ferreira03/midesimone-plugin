<?php
namespace MidesimonePlugin\Models;

use Exception;
use WC_Product;
use WC_Product_Auction_Premium;

class AuctionModel {
    public static function get_stagnant_products() {
        $args = [
            'limit' => -1,
            'status' => 'publish',
            'stock_status' => 'instock',
            'type' => 'simple'
        ];
        
        return wc_get_products($args);
    }
    
    public static function create_auction_product($original_id) {
        try {
            $original_product = wc_get_product($original_id);
            
            if (!$original_product || !$original_product->managing_stock()) {
                throw new Exception(__('Produto inválido ou não gerencia estoque', 'text-domain'));
            }

            $new_product = new WC_Product_Auction_Premium();
            
            $new_product->set_props([
                'name' => $original_product->get_name() . ' - Leilão',
                'slug' => sanitize_title($original_product->get_name()) . '-leilao-' . uniqid(),
                'description' => $original_product->get_description(),
                'short_description' => $original_product->get_short_description(),
                'status' => 'publish'
            ]);
            
            $new_product_id = $new_product->save();
            
            self::copy_product_metadata($original_id, $new_product_id);
            
            update_post_meta($new_product_id, '_yith_auction_start_date', current_time('timestamp'));
            update_post_meta($new_product_id, '_yith_auction_end_date', strtotime('+7 days'));
            
            return $new_product_id;
            
        } catch (Exception $e) {
            error_log('Erro ao criar leilão: ' . $e->getMessage());
            return false;
        }
    }

    private static function copy_product_metadata($source_id, $destination_id) {
        $thumbnail_id = get_post_thumbnail_id($source_id);
        if ($thumbnail_id) {
            set_post_thumbnail($destination_id, $thumbnail_id);
        }
        
        $gallery_ids = get_post_meta($source_id, '_product_image_gallery', true);
        if ($gallery_ids) {
            update_post_meta($destination_id, '_product_image_gallery', $gallery_ids);
        }
    }
}