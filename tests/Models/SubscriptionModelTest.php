<?php

namespace MidesimonePlugin\Tests\Models;

use DateTime;
use DateTimeZone;
use MidesimonePlugin\Models\PreferenceModel;
use MidesimonePlugin\Models\SubscriptionModel;
use WC_Product_Simple;
use WC_Product_Variation;
use WP_UnitTestCase;
use WC_Product_Subscription;
use WC_Subscription;

class SubscriptionModelTest extends WP_UnitTestCase {
    protected $subscription_id;
    protected $preference_id;
    protected $product_ids = [];
    protected $timezone;

    public function setUp(): void {
        parent::setUp();
        
        $this->timezone = wp_timezone_string();
        update_option('timezone_string', $this->timezone);
        
        $this->subscription_id = $this->create_test_subscription_product();
        $this->preference_id = $this->create_test_preference();
        $this->product_ids = [
            'simple' => $this->create_test_product(100.00),
            'variation' => $this->create_test_variation(150.00),
            'with_preferences' => [
                $this->create_product_with_preference(100.00, 'platinum'),
                $this->create_product_with_preference(150.00, 'platinum'),
                $this->create_product_with_preference(200.00, 'platinum'),
            ],
        ];
    }

    private function create_test_subscription_product(): int {
        $sub = new WC_Product_Subscription();
        $sub->set_name('Test Subscription');
        $sub->set_price(300);
        $sub->set_regular_price(300);
        $sub->update_meta_data('_subscription_period', 'month');
        $sub->update_meta_data('_subscription_period_interval', 1);
        $sub->update_meta_data('_subscription_period_interval', 1);
        return $sub->save();
    }

    private function create_test_preference(): int {
        $pref_id = wp_insert_post([
            'post_type' => 'preference',
            'post_title' => 'Test Preference',
            'post_status' => 'publish'
        ]);
        
        $options = [
            ['name' => 'Gold', 'value' => 50],
            ['name' => 'Platinum', 'value' => 100]
        ];
        PreferenceModel::save_meta_data($pref_id, ['preference_options' => $options]);
        
        return $pref_id;
    }

    private function create_test_product(float $price): int {
        $product = new WC_Product_Simple();
        $product->set_name('Test Product');
        $product->set_price($price);
        $product->set_stock_status('instock');
        return $product->save();
    }

    private function create_test_variation(float $price): int {
        $parent_id = $this->create_test_product(0);
        $variation = new WC_Product_Variation();
        $variation->set_parent_id($parent_id);
        $variation->set_price($price);
        $variation->set_stock_status('instock');
        return $variation->save();
    }

    private function create_product_with_preference($price, $slug): int {
        $term = term_exists($slug, 'jewelry_preference');
        if (!$term) {
            $term = wp_insert_term($slug, 'jewelry_preference');
        }
        
        $product = new WC_Product_Simple();
        $product->set_name('Test Product with Preference');
        $product->set_price($price);
        $product->set_stock_status('instock');
        $product_id = $product->save();
        wp_set_object_terms($product_id, $slug, 'jewelry_preference');
        return $product_id;
    }

    public function test_save_and_retrieve_preferences() {
        $test_data = [strval($this->preference_id) => 'gold'];
        $_POST['subscription_preferences'] = $test_data;
        
        SubscriptionModel::save_preferences_field($this->subscription_id);
        $saved = SubscriptionModel::get_subscription_preferences($this->subscription_id);
        
        $this->assertEquals([$this->preference_id => 'gold'], $saved);
    }

    public function test_validation_with_missing_preferences() {
        $product = wc_get_product($this->subscription_id);
        $product->update_meta_data('_subscription_preferences', [$this->preference_id]);
        $product->save();
        
        $passed = SubscriptionModel::validate_subscription_preferences(true, $this->subscription_id, 1);
        $this->assertFalse($passed);
        $this->assertNotEmpty(wc_get_notices('error'));
    }

    public function test_price_calculation_with_preferences() {
        WC()->cart->empty_cart();
        WC()->cart->add_to_cart(
            $this->subscription_id, 
            1, 
            0, 
            [], 
            ['subscription_preferences' => [$this->preference_id => 'platinum']]
        );
        SubscriptionModel::apply_selected_preferences(WC()->cart);
        
        $cart_items = WC()->cart->get_cart();
        $this->assertNotEmpty($cart_items, 'Carrinho está vazio');

        $cart_item = reset($cart_items);
        $this->assertEquals(400.00, $cart_item['data']->get_price());
    }

    public function test_exact_combination_match() {
        $products = [
            ['id' => 1, 'price' => 100, 'type' => 'simple'],
            ['id' => 2, 'price' => 200, 'type' => 'simple'],
            ['id' => 3, 'price' => 300, 'type' => 'variation']
        ];
    
        $result = SubscriptionModel::select_products_for_subscription(
            $this->create_mock_subscription(300),
            $products
        );
        
        $this->assertEquals(300, array_sum(array_column($result, 'price')));
        $this->assertCount(2, $result);
    }
    
    public function test_closest_combination_without_exceeding() {
        $products = [
            ['id' => 1, 'price' => 100, 'type' => 'simple'],
            ['id' => 2, 'price' => 200, 'type' => 'simple'],
            ['id' => 3, 'price' => 300, 'type' => 'variation']
        ];
    
        $result = SubscriptionModel::select_products_for_subscription(
            $this->create_mock_subscription(350),
            $products
        );
        
        $this->assertEquals(300, array_sum(array_column($result, 'price')));
        $this->assertCount(2, $result);
    }
    
    public function test_multiple_items_combination() {
        $products = [
            ['id' => 1, 'price' => 100, 'type' => 'simple'],
            ['id' => 2, 'price' => 200, 'type' => 'simple'],
            ['id' => 4, 'price' => 50, 'type' => 'simple']
        ];
    
        $result = SubscriptionModel::select_products_for_subscription(
            $this->create_mock_subscription(250),
            $products
        );
        
        $this->assertEquals(250, array_sum(array_column($result, 'price')));
        $this->assertCount(2, $result);
    }
    
    public function test_no_valid_items() {
        $products = [
            ['id' => 1, 'price' => 100, 'type' => 'simple'],
            ['id' => 2, 'price' => 200, 'type' => 'simple']
        ];
    
        $result = SubscriptionModel::select_products_for_subscription(
            $this->create_mock_subscription(50),
            $products
        );
        
        $this->assertEmpty($result);
    }
    
    public function test_combination_priority_criteria() {
        $products = [
            ['id' => 1, 'price' => 150, 'type' => 'simple'],
            ['id' => 2, 'price' => 150, 'type' => 'simple'],
            ['id' => 3, 'price' => 200, 'type' => 'variation']
        ];
    
        $result = SubscriptionModel::select_products_for_subscription(
            $this->create_mock_subscription(300),
            $products
        );
        
        $this->assertEquals(300, array_sum(array_column($result, 'price')));
        $this->assertCount(2, $result);
    }

    private function create_mock_subscription(float $total): WC_Subscription {
        $mock = $this->getMockBuilder(WC_Subscription::class)
            ->disableOriginalConstructor()
            ->getMock();
        
        $mock->method('get_total')->willReturn($total);
        return $mock;
    }
    
    public function test_edge_case_empty_preferences() {
        $passed = SubscriptionModel::validate_subscription_preferences(
            true, 
            $this->subscription_id, 
            1
        );
        $this->assertTrue($passed);
    }
    
    public function test_out_of_stock_products() {
        $oos_id = $this->create_test_product(200.00);
        wp_set_post_terms($oos_id, 'outofstock', 'product_visibility');
        
        $candidates = SubscriptionModel::get_candidate_products([]);
        $this->assertNotContains($oos_id, array_column($candidates, 'id'));
    }

    public function test_monthly_processing_logic() {
        $sub = new WC_Subscription();
        $sub->set_status('active');
        $sub->set_date_created((new DateTime())->format('Y-m-d H:i:s'));
        $sub->add_meta_data('_selected_subscription_preferences', [$this->preference_id => 'platinum']);
        $sub->save();
        
        SubscriptionModel::process_subscription_orders();
        
        $updated_sub = wcs_get_subscription($sub->get_id());
        $this->assertEquals(
            (new DateTime())->format('Y-m'),
            $updated_sub->get_meta('_last_processed_month')
        );
    }

    public function test_timezone_handling() {
        $test_date = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
        $sub = new WC_Subscription();
        $sub->set_status('active');
        $sub->set_date_created($test_date->format('Y-m-d H:i:s'));
        $sub->add_meta_data('_selected_subscription_preferences', [$this->preference_id => 'platinum']);
        $sub->save();
        
        SubscriptionModel::process_subscription_orders();
        
        $updated_sub = wcs_get_subscription($sub->get_id());
        $this->assertEquals(
            $test_date->format('Y-m'),
            $updated_sub->get_meta('_last_processed_month')
        );
    }

    public function test_consecutive_months_processing() {
        $sub = new WC_Subscription();
        $sub->set_status('active');
        $sub->set_date_created('2023-01-15 12:00:00');
        $sub->add_meta_data('_selected_subscription_preferences', [$this->preference_id => 'platinum']);
        $sub->save();
        
        $months = [
            '2023-01' => '2023-01-15 12:00:00',
            '2023-02' => '2023-02-15 12:00:00',
            '2023-03' => '2023-03-15 12:00:00',
        ];
        
        foreach ($months as $expected_month => $date) {
            $sub->update_meta_data('_last_renewal_date', $date);
            $sub->save();
            SubscriptionModel::set_current_date($date);
            SubscriptionModel::process_subscription_orders();
            
            $updated_sub = wcs_get_subscription($sub->get_id());
            $this->assertEquals(
                $expected_month,
                $updated_sub->get_meta('_last_processed_month'),
                "Falha no processamento do mês $expected_month"
            );
        }
    }

    public function tearDown(): void {
        WC()->cart->empty_cart();
        wc_clear_notices();
        
        wp_delete_post($this->subscription_id, true);
        wp_delete_post($this->preference_id, true);
        array_walk($this->product_ids, fn($id) => wp_delete_post($id, true));
        
        parent::tearDown();
    }
}