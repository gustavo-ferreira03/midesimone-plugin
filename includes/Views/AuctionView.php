<?php

namespace MidesimonePlugin\Views;

class AuctionView {
    public static function render_panel($products) {
        ?>
        <div class="wrap">
            <h1><?php _e('Criar Leilão', 'text-domain'); ?></h1>
            
            <?php if (isset($_GET['auction_status']) && $_GET['auction_status'] === 'error'): ?>
                <div class="notice notice-error is-dismissible">
                    <p>
                        <?php 
                            echo isset($_GET['error_message']) 
                                ? esc_html(urldecode($_GET['error_message'])) 
                                : __('Erro ao criar leilão.', 'text-domain'); 
                        ?>
                    </p>
                </div>
            <?php endif; ?>
            
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="create_auction">
                
                <div class="form-field">
                    <label for="auction_product_id"><?php _e('Selecione o produto ou variação:', 'text-domain'); ?></label>
                    <select name="auction_product_id" id="auction_product_id" style="width: 300px;">
                        <?php foreach ($products as $product) : ?>
                            <?php 
                                $days = $product->days_stagnant;
                            ?>
                            <?php if ($product->is_type('variable')) : ?>
                                <optgroup label="<?php echo esc_attr($product->get_name() . ' - parado há ' . $days . ' dias'); ?>">
                                    <?php 
                                    $variation_ids = $product->get_children();
                                    foreach ($variation_ids as $variation_id) :
                                        $variation = wc_get_product($variation_id);
                                        if (!$variation) continue;
                                        $attributes = [];
                                        foreach ($variation->get_attributes() as $name => $value) {
                                            $attributes[] = wc_attribute_label($name) . ': ' . $value;
                                        }
                                        $variation_label = implode(', ', $attributes);
                                    ?>
                                        <option value="<?php echo esc_attr($variation_id); ?>">
                                            <?php echo esc_html($variation_label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php else : ?>
                                <option value="<?php echo esc_attr($product->get_id()); ?>">
                                    <?php 
                                        echo esc_html($product->get_name()) 
                                            . ' - parado há ' 
                                            . $days 
                                            . ' dias'; 
                                    ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <?php submit_button(__('Criar Leilão', 'text-domain')); ?>
            </form>
        </div>
        <?php
    }
}
