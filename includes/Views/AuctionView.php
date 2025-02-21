<?php
namespace MidesimonePlugin\Views;

class AuctionView {
    public static function render_panel($products) {
        ?>
        <div class="wrap">
            <h1><?php _e('Criar Leilão', 'text-domain'); ?></h1>
            
            <?php if (isset($_GET['auction_status'])) : ?>
                <div class="notice notice-<?php echo $_GET['auction_status'] === 'error' ? 'error' : 'success'; ?> is-dismissible">
                    <p><?php echo $_GET['auction_status'] === 'error' 
                        ? __('Erro ao criar leilão.', 'text-domain') 
                        : __('Leilão criado com sucesso!', 'text-domain'); ?></p>
                </div>
            <?php endif; ?>
            
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="create_auction">
                
                <div class="form-field">
                    <label for="auction_product_id"><?php _e('Selecione o produto:', 'text-domain'); ?></label>
                    <select name="auction_product_id" id="auction_product_id" style="width: 300px;">
                        <?php foreach ($products as $product) : ?>
                            <option value="<?php echo esc_attr($product->get_id()); ?>">
                                <?php echo esc_html($product->get_name()); ?> (ID: <?php echo $product->get_id(); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <?php submit_button(__('Criar Leilão', 'text-domain')); ?>
            </form>
        </div>
        <?php
    }
}