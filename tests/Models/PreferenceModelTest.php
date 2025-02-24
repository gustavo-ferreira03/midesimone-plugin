<?php

namespace MidesimonePlugin\Tests\Models;

use MidesimonePlugin\Models\PreferenceModel;
use WP_UnitTestCase;

class PreferenceModelTest extends WP_UnitTestCase {
    protected $preference_id;
    protected $product_id;
    protected $term_id;

    public function setUp(): void {
        parent::setUp();
        
        $this->preference_id = $this->create_test_preference();
        $this->product_id = $this->factory->post->create(['post_type' => 'product']);
    }

    private function create_test_preference(): int {
        return wp_insert_post([
            'post_type' => 'preference',
            'post_status' => 'publish',
            'post_title' => 'Test Preference'
        ]);
    }

    public function test_register_post_type() {
        PreferenceModel::register_post_type();
        $this->assertTrue(post_type_exists('preference'));
    }

    public function test_register_taxonomy() {
        PreferenceModel::register_preferences_taxonomy();
        $this->assertTrue(taxonomy_exists('jewelry_preference'));
    }

    public function test_save_meta_data() {
        $test_data = [
            'preference_description' => 'Descrição de teste',
            'preference_options' => [
                ['name' => 'Opção 1', 'value' => 10],
                ['name' => 'Opção 2', 'value' => 20]
            ]
        ];

        PreferenceModel::save_meta_data($this->preference_id, $test_data);

        $this->assertEquals(
            $test_data['preference_description'],
            get_post_meta($this->preference_id, '_preference_description', true)
        );

        $saved_options = get_post_meta($this->preference_id, '_preference_options', true);
        $this->assertCount(2, $saved_options);
        $this->assertEquals('opcao-1', $saved_options[0]['slug']);
    }

    public function test_create_preference_taxonomy() {
        $options = [
            ['name' => 'Ouro', 'value' => 100],
            ['name' => 'Prata', 'value' => 50]
        ];

        PreferenceModel::create_preference_taxonomy($this->preference_id, $options);
        
        $terms = get_terms([
            'taxonomy' => 'jewelry_preference',
            'hide_empty' => false,
            'meta_query' => [[
                'key' => '_preference_id',
                'value' => $this->preference_id
            ]]
        ]);

        $this->assertCount(3, $terms);
    }

    public function test_enforce_child_selection() {
        $parent_term = wp_insert_term('Parent', 'jewelry_preference');
        $child_term = wp_insert_term('Child', 'jewelry_preference', ['parent' => $parent_term['term_id']]);

        wp_set_object_terms($this->product_id, [$child_term['term_id']], 'jewelry_preference');

        PreferenceModel::enforce_child_selection($this->product_id);

        $product_terms = wp_get_post_terms($this->product_id, 'jewelry_preference');
        $term_ids = array_column($product_terms, 'term_id');
        
        $this->assertContains($parent_term['term_id'], $term_ids);
    }

    public function test_get_all_preferences() {
        $preferences = PreferenceModel::get_all_preferences();
        $this->assertContains($this->preference_id, wp_list_pluck($preferences, 'ID'));
    }

    public function test_get_all_preference_terms() {
        $options = [
            ['name' => 'Ouro', 'value' => 100],
            ['name' => 'Prata', 'value' => 50]
        ];
        PreferenceModel::create_preference_taxonomy($this->preference_id, $options);
        $terms = PreferenceModel::get_all_preference_terms();
        
        $this->assertNotEmpty($terms);
    }

    public function test_save_variation_preferences() {
        $variation_id = $this->factory->post->create(['post_type' => 'product_variation']);
        $_POST['variation_preferences'][$variation_id] = ['term1', 'term2'];

        PreferenceModel::save_variation_preferences($variation_id, 0);
        $saved = get_post_meta($variation_id, '_variation_preferences', true);

        $this->assertEquals(['term1', 'term2'], $saved);
        unset($_POST['variation_preferences']);
    }

    public function test_delete_preference_taxonomy() {
        PreferenceModel::create_preference_taxonomy($this->preference_id, [
            ['name' => 'Test Option']
        ]);

        PreferenceModel::delete_preference_taxonomy($this->preference_id);
        $terms = get_terms(['taxonomy' => 'jewelry_preference', 'hide_empty' => false]);
        
        $this->assertEmpty($terms);
    }

    public function test_empty_preference_options() {
        $test_data = ['preference_options' => []];
        PreferenceModel::save_meta_data($this->preference_id, $test_data);
        
        $this->assertEmpty(get_post_meta($this->preference_id, '_preference_options', true));
        $this->assertEmpty(get_terms(['taxonomy' => 'jewelry_preference', 'hide_empty' => false]));
    }
    
    public function test_invalid_option_values() {
        $test_data = [
            'preference_options' => [
                ['name' => 'Opção Inválida', 'value' => 'abc']
            ]
        ];
    
        PreferenceModel::save_meta_data($this->preference_id, $test_data);
        $saved = get_post_meta($this->preference_id, '_preference_options', true);
    
        $this->assertEquals(0, $saved[0]['value']);
    }
    
    public function test_term_meta_association() {
        $options = [['name' => 'Test Term']];
        PreferenceModel::create_preference_taxonomy($this->preference_id, $options);
        
        $terms = get_terms(['taxonomy' => 'jewelry_preference', 'hide_empty' => false]);
        $term_meta = get_term_meta($terms[1]->term_id, '_preference_id', true);
        
        $this->assertEquals($this->preference_id, $term_meta);
    }

    public function tearDown(): void {
        wp_delete_post($this->preference_id, true);
        wp_delete_post($this->product_id, true);
        parent::tearDown();
    }
}