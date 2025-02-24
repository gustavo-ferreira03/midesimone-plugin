<?php
namespace MidesimonePlugin\Models;

use Exception;
use WC_Product_Auction_Premium;

class AuctionModel {
    public static function get_stagnant_products() {
        $args = [
            'limit'        => -1,
            'status'       => 'publish',
            'stock_status' => 'instock',
            'type'         => ['simple', 'variable']
        ];
        
        $products = wc_get_products($args);
        
        foreach ($products as $key => $product) {
            $last_date = self::get_last_purchase_date($product->get_id());
            
            if ($last_date) {
                $days = floor((time() - strtotime($last_date)) / 86400);
            } else {
                $created_date = get_the_date('U', $product->get_id());
                $days = floor((time() - $created_date) / 86400);
            }
            
            $products[$key]->days_stagnant = $days;
        }
        
        usort($products, function($a, $b) {
            return $b->days_stagnant - $a->days_stagnant;
        });
        
        return $products;
    }
    
    public static function get_last_purchase_date($product_id) {
        global $wpdb;
        $query = $wpdb->prepare("SELECT MAX(p.post_date)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->prefix}woocommerce_order_items oi ON p.ID = oi.order_id
            INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
            WHERE oim.meta_key = '_product_id'
              AND oim.meta_value = %d
              AND p.post_status IN ('wc-completed','wc-processing')
        ", $product_id);
        
        return $wpdb->get_var($query);
    }
    public static function create_auction_product($original_id) {
        try {
            $original_product = wc_get_product($original_id);
            if (!$original_product) {
                throw new Exception(__('Produto inválido.', 'text-domain'));
            }
            
            $new_product = new WC_Product_Auction_Premium();
            $new_product->set_props([
                'name' => $original_product->get_name() . ' - Leilão',
                'slug' => sanitize_title($original_product->get_name()) . '-leilao-' . uniqid(),
                'description' => $original_product->get_description(),
                'short_description' => $original_product->get_short_description(),
                'status' => 'publish'
            ]);
            
            if ($original_product->managing_stock()) {
                $reserve_quantity = 1;
                $original_stock = $original_product->get_stock_quantity();
                if ($original_stock < $reserve_quantity) {
                    throw new Exception(__('Estoque insuficiente para reservar.', 'text-domain'));
                }
                $new_product->set_manage_stock(true);
                $new_product->set_stock_quantity($reserve_quantity);
                $original_product->set_stock_quantity($original_stock - $reserve_quantity);
                $original_product->save();
            }
            
            $new_product_id = $new_product->save();
            if (!$new_product_id) {
                throw new Exception(__('Falha ao criar o produto leilão.', 'text-domain'));
            }
            
            self::copy_product_metadata($original_product->get_id(), $new_product_id);
            
            wp_set_object_terms($new_product_id, 'auction', 'product_type');
            
            update_post_meta($new_product_id, '_yith_auction_start_date', current_time('timestamp'));
            update_post_meta($new_product_id, '_yith_auction_end_date', strtotime('+7 days'));
            
            return $new_product_id;
            
        } catch (Exception $e) {
            error_log('Erro ao criar leilão: ' . $e->getMessage());
            return new \WP_Error('auction_creation_failed', $e->getMessage());
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
