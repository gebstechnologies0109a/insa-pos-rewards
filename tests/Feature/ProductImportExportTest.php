<?php

namespace Tests\Feature;

use App\Models\POS\Category;
use App\Models\POS\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ProductImportExportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.com',
            'password' => bcrypt('password'), 'role' => 'admin',
        ]);
    }

    public function test_export_downloads_xlsx(): void
    {
        Product::create(['name' => 'Exportable Product', 'price' => 50]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.products.export'));

        $response->assertOk();
        $this->assertTrue(
            str_contains($response->headers->get('content-disposition'), 'products.xlsx')
        );
    }

    public function test_import_requires_file(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.products.import'), [])
            ->assertSessionHasErrors('file');
    }

    public function test_import_csv_creates_products(): void
    {
        $csv = "name,sku,barcode,price,category,active\n";
        $csv .= "Imported Coffee,IMP-001,8881234567,120.50,Beverages,Yes\n";
        $csv .= "Imported Tea,IMP-002,8881234568,90.00,Beverages,Yes\n";

        $file = UploadedFile::fake()->createWithContent('products.csv', $csv);

        $this->actingAs($this->admin)
            ->post(route('admin.products.import'), ['file' => $file])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('products', ['name' => 'Imported Coffee', 'sku' => 'IMP-001', 'price' => 120.50]);
        $this->assertDatabaseHas('products', ['name' => 'Imported Tea', 'sku' => 'IMP-002']);
        $this->assertDatabaseHas('categories', ['name' => 'Beverages']);
    }

    public function test_import_updates_existing_by_sku(): void
    {
        Product::create(['name' => 'Old Name', 'sku' => 'UPD-001', 'price' => 50]);

        $csv = "name,sku,barcode,price,category,active\n";
        $csv .= "New Name,UPD-001,,75.00,,Yes\n";

        $file = UploadedFile::fake()->createWithContent('update.csv', $csv);

        $this->actingAs($this->admin)
            ->post(route('admin.products.import'), ['file' => $file]);

        $this->assertDatabaseHas('products', ['sku' => 'UPD-001', 'name' => 'New Name', 'price' => 75.00]);
        $this->assertDatabaseCount('products', 1);
    }

    public function test_import_creates_category_if_not_exists(): void
    {
        $csv = "name,sku,barcode,price,category,active\n";
        $csv .= "Snack Bar,SN-001,,45.00,Snacks,Yes\n";

        $file = UploadedFile::fake()->createWithContent('cat.csv', $csv);

        $this->actingAs($this->admin)
            ->post(route('admin.products.import'), ['file' => $file]);

        $this->assertDatabaseHas('categories', ['name' => 'Snacks']);
        $product = Product::where('sku', 'SN-001')->first();
        $this->assertNotNull($product->category_id);
    }

    public function test_import_uses_existing_category(): void
    {
        $cat = Category::create(['name' => 'Drinks']);

        $csv = "name,sku,barcode,price,category,active\n";
        $csv .= "Soda,SD-001,,35.00,Drinks,Yes\n";

        $file = UploadedFile::fake()->createWithContent('existing_cat.csv', $csv);

        $this->actingAs($this->admin)
            ->post(route('admin.products.import'), ['file' => $file]);

        $this->assertDatabaseCount('categories', 1);
        $product = Product::where('sku', 'SD-001')->first();
        $this->assertEquals($cat->id, $product->category_id);
    }
}
