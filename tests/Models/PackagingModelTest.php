<?php
namespace MidesimonePlugin\Tests\Models;

use MidesimonePlugin\Models\PackagingModel;
use WC_Product_Simple;
use WP_UnitTestCase;

class PackagingModelTest extends WP_UnitTestCase {
    protected $packaging_id;
    protected $product_id;
    protected $default_test_data;

    public function setUp(): void {
        parent::setUp();
        
        $this->default_test_data = [
            'packaging_name'   => 'Caixa Premium',
            'packaging_stock_qt' => 15
        ];

        $this->packaging_id = $this->create_test_packaging();
        $this->product_id = $this->create_linked_product($this->packaging_id);

        $this->save_default_meta_data();
    }

    private function create_test_packaging(): int {
        return wp_insert_post([
            'post_type'    => 'packaging',
            'post_status'  => 'publish',
            'post_title'   => 'Test Packaging'
        ]);
    }

    private function create_linked_product(int $packaging_id): int {
        $product = new WC_Product_Simple();
        $product->set_name('Test Product');
        $product->save();
        update_post_meta($product->get_id(), '_packaging_id', $packaging_id);
        return $product->get_id();
    }

    private function save_default_meta_data(): void {
        PackagingModel::save_meta_data(
            $this->packaging_id, 
            $this->default_test_data
        );
    }

    public function test_create_packaging_post() {
        $this->assertSame('packaging', get_post_type($this->packaging_id));
    }

    public function test_save_meta_data() {
        $this->assertEquals(
            $this->default_test_data['packaging_name'],
            get_post_meta($this->packaging_id, '_packaging_name', true)
        );
    }

    public function test_stock_management() {
        PackagingModel::reduce_stock($this->packaging_id, 5);
        $this->assertEquals(
            10,
            PackagingModel::get_stock($this->packaging_id)
        );

        PackagingModel::increase_stock($this->packaging_id, 3);
        $this->assertEquals(
            13,
            PackagingModel::get_stock($this->packaging_id)
        );
    }

    public function test_product_linking() {
        $linked_products = PackagingModel::get_linked_products($this->packaging_id);
        $this->assertContains($this->product_id, $linked_products);
    }

    public function test_reduce_stock_insufficient() {
        PackagingModel::save_meta_data($this->packaging_id, ['packaging_stock_qt' => 5]);
        $this->assertEquals(5, PackagingModel::get_stock($this->packaging_id));
    
        $result = PackagingModel::reduce_stock($this->packaging_id, 10);
        $this->assertFalse($result, 'Deveria falhar ao reduzir mais que o estoque disponível');
        $this->assertEquals(5, PackagingModel::get_stock($this->packaging_id), 'Estoque não deve mudar após redução inválida');
    }
    
    public function test_out_of_stock_updates_product_status() {
        PackagingModel::save_meta_data($this->packaging_id, ['packaging_stock_qt' => 0]);
        
        $product = wc_get_product($this->product_id);
        $this->assertEquals('outofstock', $product->get_stock_status(), 'Produto deve ficar sem estoque quando embalagem está zerada');
    }
    
    public function test_validate_cart_item_stock_with_insufficient_stock() {
        WC()->cart->empty_cart();
        wc_clear_notices();
    
        PackagingModel::save_meta_data($this->packaging_id, ['packaging_stock_qt' => 0]);
        
        $product = wc_get_product($this->product_id);
        $product->set_stock_status('instock');
        $product->set_backorders('yes');
        $product->save();
    
        WC()->cart->add_to_cart($this->product_id, 3);
    
        PackagingModel::validate_cart_item_stock();
    
        $notices = wc_get_notices('error');
        $this->assertNotEmpty($notices, 'Deveria gerar um notice de estoque insuficiente');
        
        foreach (WC()->cart->get_cart() as $cart_item) {
            $this->assertEquals(0, $cart_item['quantity'], 'Quantidade no carrinho deve ser zerada');
        }
    
        WC()->cart->empty_cart();
        wc_clear_notices();
    }
    
    public function test_get_product_stock_status() {
        PackagingModel::save_meta_data($this->packaging_id, ['packaging_stock_qt' => 0]);
        $product = wc_get_product($this->product_id);
        $status = PackagingModel::get_product_stock_status('instock', $product);

        $this->assertEquals('outofstock', $status, 'Status do produto deve refletir estoque da embalagem');
    }

    public function tearDown(): void {
        wp_delete_post($this->packaging_id, true);
        wp_delete_post($this->product_id, true);
        parent::tearDown();
    }
}