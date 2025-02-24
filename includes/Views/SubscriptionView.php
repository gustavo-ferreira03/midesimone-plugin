<?php

namespace MidesimonePlugin\Views;

class SubscriptionView {
    public static function render_preference_options($preferences, $selected_preferences) {
        ?>
        <div class="show_if_subscription">
            <div class="options_group">
                <p class="form-field">
                    <label for="subscription_preferences"><?php _e('Preferências', 'text-domain'); ?></label>
                    <select id="subscription_preferences" name="subscription_preferences[]" multiple="multiple" style="width: 50%;">
                        <?php foreach ( $preferences as $preference ): ?>
                            <option value="<?php echo esc_attr($preference->ID); ?>" <?php echo (is_array($selected_preferences) && in_array($preference->ID, $selected_preferences)) ? 'selected="selected"' : ''; ?>>
                                <?php echo esc_html($preference->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="description"><?php _e('Selecione as preferências para esta assinatura.', 'text-domain'); ?></span>
                </p>
            </div>
        </div>
        <?php
    }

    public static function render_subscription_preferences($preferences) {
        ?>
        <div class="subscription-preferences">
            <h3><?php esc_html_e('Preferências', 'text-domain'); ?></h3>
    
            <?php foreach ($preferences as $preference) : 
                $options = get_post_meta($preference->ID, '_preference_options', true);
    
                if (empty($options) || !is_array($options)) {
                    continue;
                }
            ?>
    
                <div class="preference">
                    <label class="preference-title"><?php echo esc_html($preference->post_title); ?></label>
                    <div class="preference-options">
    
                        <?php foreach ($options as $option) :
                            $radio_id = 'preference-' . $preference->ID . '-' . sanitize_title($option['name']);
                        ?>
                            <div class="preference-option">
                                <input
                                    required
                                    type="radio" 
                                    name="subscription_preferences[<?php echo esc_attr($preference->ID); ?>]" 
                                    value="<?php echo esc_attr($option['slug']); ?>" 
                                    id="<?php echo esc_attr($radio_id); ?>"
                                >
                                <label for="<?php echo esc_attr($radio_id); ?>">
                                    <?php echo esc_html($option['name']); ?> (+ <?php echo wc_price($option['value']); ?>)
                                </label>
                            </div>
                        <?php endforeach; ?>
    
                    </div>
                </div>
    
            <?php endforeach; ?>
        </div>
    
        <?php
    }

    public static function render_subscription_preferences_in_cart($cart_item, $cart_item_key) {
        ?>
        <ul class="subscription-preferences-list" style="color: #aaa;">
            <?php foreach ($cart_item['subscription_preferences'] as $preference_id => $selected_option_id): 
                $preference_post = get_post($preference_id);
                if (!$preference_post) {
                    continue;
                }
                $preference_title = $preference_post->post_title;
                $options = get_post_meta($preference_id, '_preference_options', true);
                $option_name = '';
                if (!empty($options) && is_array($options)):
                    foreach ($options as $option):
                        if (isset($option['slug']) && $option['slug'] === $selected_option_id):
                            $option_name = $option['name'];
                            break;
                        endif;
                    endforeach;
                endif;
                if (!empty($option_name)):
            ?>
                <li><strong><?php echo esc_html($preference_title); ?>:</strong> <?php echo esc_html($option_name); ?></li>
            <?php 
                endif;
             endforeach; ?>
        </ul>
        <?php
    }    
}
