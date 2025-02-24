<?php
namespace MidesimonePlugin\Controllers;

use MidesimonePlugin\Models\AuctionModel;
use MidesimonePlugin\Views\AuctionView;

class AuctionController {
    
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register_auction_panel']);
        add_action('admin_post_create_auction', [__CLASS__, 'handle_create_auction']);
    }
    
    public static function register_auction_panel() {
        add_menu_page(
            __('Leilões', 'text-domain'),
            __('Leilões', 'text-domain'),
            'manage_options',
            'auction-panel',
            [__CLASS__, 'render_auction_panel'],
            'dashicons-hammer',
            6
        );
    }
    
    public static function render_auction_panel() {
        $products = AuctionModel::get_stagnant_products();
        AuctionView::render_panel($products);
    }
    
    public static function handle_create_auction() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Usuário não autorizado', 'text-domain'));
        }
        
        if (isset($_POST['auction_product_id'])) {
            $selected_id = intval(sanitize_text_field($_POST['auction_product_id']));
            $new_auction_id = AuctionModel::create_auction_product($selected_id);
            
            if (is_wp_error($new_auction_id)) {
                $error_message = $new_auction_id->get_error_message();
                $redirect_url = admin_url('admin.php?page=auction-panel&auction_status=error&error_message=' . urlencode($error_message));
                wp_redirect($redirect_url);
                exit;
            }
            
            if ($new_auction_id) {
                $redirect_url = admin_url("post.php?post={$new_auction_id}&action=edit");
                wp_redirect($redirect_url);
                exit;
            }
            
            wp_redirect(admin_url('admin.php?page=auction-panel&auction_status=error'));
            exit;
        }
    }
}
