<?php

namespace MidesimonePlugin\Views;

class PreferenceView {
    public static function render_meta_box($post) {
        $description = get_post_meta($post->ID, '_preference_description', true);
        $options     = get_post_meta($post->ID, '_preference_options', true) ?: [];

        ?>
        <label for="preference_description">Descrição:</label>
        <textarea id="preference_description" name="preference_description" style="width: 100%;"><?php echo esc_textarea($description); ?></textarea><br><br>

        <label>Lista de Opções:</label>
        <table id="preference-options-table" style="width: 100%;">
            <thead>
                <tr>
                    <th>Opção</th>
                    <th>Acréscimo</th>
                    <th>Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($options as $index => $option): ?>
                    <tr>
                        <td><input type="text" name="preference_options[<?php echo esc_attr($index); ?>][name]" value="<?php echo esc_attr($option['name']); ?>" style="width: 100%;"></td>
                        <td><input type="number" min="0" step="0.01" name="preference_options[<?php echo esc_attr($index); ?>][value]" value="<?php echo esc_attr($option['value']); ?>" style="width: 100%;"></td>
                        <td><button type="button" class="remove-option button">Remover</button></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <button type="button" id="add-preference-option" class="button">Adicionar Opção</button>

        <?php
        wp_nonce_field('preference_meta_nonce_action', 'preference_meta_nonce');

        self::load_dynamic_options_script();
    }

    private static function load_dynamic_options_script() {
        ?>
        <script>
            jQuery(document).ready(function($) {
                let optionIndex = <?php echo json_encode(count(get_post_meta(get_the_ID(), '_preference_options', true) ?: [])); ?>;

                $('#add-preference-option').on('click', function() {
                    let uniqueKey = Date.now() + Math.random().toString(36).substr(2, 9);
                    let newRow = `
                        <tr>
                            <td><input type="text" name="preference_options[${uniqueKey}][name]" style="width: 100%;"></td>
                            <td><input type="number" min="0" step="0.01" name="preference_options[${uniqueKey}][value]" style="width: 100%;"></td>
                            <td><button type="button" class="remove-option button">Remover</button></td>
                        </tr>
                    `;
                    $('#preference-options-table tbody').append(newRow);
                    optionIndex++;
                });

                $(document).on('click', '.remove-option', function() {
                    $(this).closest('tr').remove();
                });
            });
        </script>
        <?php
    }

    public static function render_preference_columns($column, $meta_data) {
        switch ($column) {
            case 'preference_description':
                echo esc_html($meta_data['preference_description'] ?: '(Sem descrição)');
                break;

            case 'preference_options':
                if (!empty($meta_data['preference_options'])) {
                    $output = array_map(function ($option) {
                        return sprintf('%s (+ $%.2f)', esc_html($option['name']), esc_html($option['value']));
                    }, $meta_data['preference_options']);
                    echo esc_html(implode('; ', $output));
                } else {
                    echo '(Sem opções)';
                }
                break;
        }
    }

    public static function render_variation_preferences($preferences, $selected, $variation) {
        ?>
        <div class="variation-preferences" style="margin: 20px 0; border-top: 1px solid #ddd; padding-top: 20px;">
            <h4>Preferências da Variação</h4>
            <?php foreach ($preferences as $pref) : ?>
                <fieldset style="margin: 10px 0; padding: 10px; border: 1px solid #eee;">
                    <legend style="font-weight: bold;"><?= esc_html($pref->name) ?></legend>
                    <?php 
                    $child_terms = get_terms([
                        'taxonomy' => 'jewelry_preference',
                        'child_of' => $pref->term_id,
                        'hide_empty' => false
                    ]);
                    
                    foreach ($child_terms as $term) : ?>
                        <label style="display: block; margin: 5px 0;">
                            <input type="checkbox" 
                                   name="variation_preferences[<?= esc_attr($variation->ID) ?>][]" 
                                   value="<?= esc_attr($term->slug) ?>"
                                   <?= in_array($term->slug, $selected) ? 'checked' : '' ?>>
                            <?= esc_html($term->name) ?>
                        </label>
                    <?php endforeach; ?>
                </fieldset>
            <?php endforeach; ?>
        </div>
        <?php
    }
}
