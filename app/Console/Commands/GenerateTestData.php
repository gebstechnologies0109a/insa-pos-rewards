<?php

namespace App\Console\Commands;

use App\Models\POS\Category;
use App\Models\POS\Product;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GenerateTestData extends Command
{
    protected $signature = 'test:generate-data {--tag=TEST-001} {--customers=2000} {--products=10000} {--dry-run}';
    protected $description = 'Generate large-scale test data: products, customers, and transactions (tagged for reversal)';

    private const BATCH = 500;

    private array $categoryProducts = [
        'Snacks' => ['Chips','Crackers','Cookies','Biscuits','Nuts','Popcorn','Pretzels','Trail Mix','Granola Bars','Rice Cakes','Cheese Puffs','Corn Chips','Wafers','Cheese Sticks','Banana Chips'],
        'Beverages' => ['Soft Drinks','Juice','Water','Energy Drinks','Tea','Coffee','Milk','Smoothies','Sports Drinks','Flavored Water','Iced Tea','Soda','Coconut Water','Lemonade','Kombucha'],
        'Personal Care' => ['Shampoo','Soap','Toothpaste','Deodorant','Lotion','Face Wash','Hair Gel','Conditioner','Mouthwash','Hand Sanitizer','Body Wash','Sunscreen','Lip Balm','Cotton Buds','Wet Wipes'],
        'Canned Goods' => ['Sardines','Corned Beef','Tuna','Beans','Tomato Sauce','Spam','Pork & Beans','Fruit Cocktail','Condensed Milk','Evaporated Milk','Mushroom','Corn','Green Peas','Vienna Sausage','Luncheon Meat'],
        'Dairy' => ['Cheese','Yogurt','Butter','Cream','Ice Cream','Fresh Milk','Cream Cheese','Sour Cream','Whipped Cream','Cottage Cheese','Mozzarella','Cheddar Block','Milk Drink','Flavored Milk','Cheese Spread'],
        'Frozen Foods' => ['Frozen Vegetables','Frozen Meat','Ice Cream Tub','Frozen Pizza','Frozen Fish','Dumplings','French Fries','Nuggets','Frozen Fruits','Waffles','Hotdog','Longganisa','Tocino','Siomai','Lumpia'],
        'Bread & Bakery' => ['White Bread','Wheat Bread','Pandesal','Ensaymada','Mamon','Croissant','Bagels','Muffins','Donuts','Cake','Rolls','Tortilla','Pita','Rye Bread','Brioche'],
        'Rice & Grains' => ['White Rice 5kg','Brown Rice 2kg','Jasmine Rice 5kg','Sticky Rice','Oats','Quinoa','Pasta','Noodles','Corn Grits','All-Purpose Flour','Pancake Mix','Cornstarch','Bread Flour','Rice Flour','Tapioca'],
        'Condiments' => ['Soy Sauce','Vinegar','Ketchup','Mayonnaise','Mustard','Hot Sauce','Fish Sauce','Oyster Sauce','Patis','Bagoong','Worcestershire','Chili Garlic','Banana Ketchup','Calamansi Juice','Sesame Oil'],
        'Instant Food' => ['Instant Noodles','Cup Noodles','Instant Coffee 3in1','Instant Oatmeal','Pancake Mix','Instant Soup','Ready Meals','Instant Rice','Porridge Mix','Cereal','Instant Champorado','Coffee Creamer','Powdered Juice','Hot Chocolate','Milo'],
        'Household' => ['Detergent','Fabric Softener','Dish Soap','Bleach','Floor Cleaner','Glass Cleaner','Sponges','Trash Bags','Paper Towels','Air Freshener','Insect Spray','Moth Balls','Rubber Gloves','Bucket','Mop Head'],
        'Baby Products' => ['Diapers S','Diapers M','Diapers L','Baby Wipes','Baby Powder','Baby Lotion','Baby Shampoo','Formula 400g','Formula 900g','Baby Food','Teether','Baby Oil','Diaper Cream','Bib','Feeding Bottle'],
        'Pet Supplies' => ['Dog Food 1kg','Dog Food 3kg','Cat Food 1kg','Cat Food 3kg','Pet Treats','Pet Shampoo','Cat Litter','Pet Toy','Leash','Food Bowl','Flea Treatment','Pet Vitamins','Collar','Pet Bed','Scratching Post'],
        'Health & Wellness' => ['Multivitamins','Paracetamol','Ibuprofen','Band-Aids','Thermometer','Face Mask','Rubbing Alcohol','Betadine','Biogesic','Neozep','Bioflu','Decolgen','Strepsils','Kremil-S','Medicol'],
        'School & Office' => ['Notebook','Ballpen','Pencil','Eraser','Folder','Bond Paper','Tape','Scissors','Marker','Ruler','Stapler','Paper Clips','Glue Stick','Highlighter','Index Card'],
        'Cooking Oil' => ['Vegetable Oil 1L','Coconut Oil 1L','Canola Oil 1L','Olive Oil 500ml','Palm Oil 1L','Sunflower Oil 1L','Corn Oil 1L','Vegetable Oil 2L','Coconut Oil 350ml','Cooking Spray'],
        'Sweets & Candy' => ['Chocolate Bar','Gummy Bears','Lollipop','Hard Candy','Chewing Gum','Marshmallow','Caramel Candy','Toffee','Jelly Beans','Mints','Mentos','M&Ms','Kit Kat','Snickers','Gummy Worms'],
        'Alcohol' => ['Beer 330ml','Beer 500ml','Red Wine','White Wine','Rum 750ml','Gin 750ml','Vodka 750ml','Whiskey 750ml','Brandy 750ml','Soju','Beer 6-pack','Light Beer','Craft Beer','Tequila','Sake'],
        'Meat & Poultry' => ['Chicken Breast','Pork Belly','Ground Beef','Hotdog 1kg','Bacon','Ham','Sausages','Chicken Wings','Pork Chops','Beef Steak','Chicken Thigh','Ground Pork','Liver','Whole Chicken','Pork Ribs'],
        'Seafood' => ['Tilapia','Bangus','Shrimp 500g','Squid','Crab','Mussels','Salmon Fillet','Tuna Steak','Mackerel','Clams','Dilis','Galunggong','Pusit','Tahong','Lapu-Lapu'],
        'Spices & Seasoning' => ['Salt','Black Pepper','Garlic Powder','Paprika','Cumin','Oregano','Basil','Cinnamon','Turmeric','Chili Powder','Bay Leaves','Onion Powder','Curry Powder','MSG','Magic Sarap'],
        'Laundry' => ['Powder Detergent 1kg','Liquid Detergent 1L','Fabric Conditioner 1L','Stain Remover','Dryer Sheets','Laundry Bar','Color Safe Bleach','Powder Detergent 500g','Fabric Conditioner 500ml','Starch Spray'],
        'Electronics' => ['AA Batteries','AAA Batteries','USB Cable','Earphones','Phone Case','Screen Protector','Power Bank','LED Bulb','Extension Cord','Memory Card','Charger','HDMI Cable','Mouse','Keyboard','Flash Drive'],
        'Kitchen Supplies' => ['Plastic Wrap','Aluminum Foil','Ziplock Bags','Disposable Cups','Paper Plates','Plastic Utensils','Straws','Napkins','Food Container','Ice Tray','Cling Wrap','Cupcake Liner','Toothpick','Match','Candle'],
        'Beauty & Cosmetics' => ['Lipstick','Foundation','Mascara','Eyeliner','Nail Polish','Blush','Face Powder','Perfume 50ml','Cologne 100ml','BB Cream','Concealer','Lip Tint','Setting Spray','Makeup Remover','Cotton Pads'],
        'Noodles & Pasta' => ['Spaghetti 500g','Penne 500g','Fettuccine','Egg Noodles','Rice Noodles','Sotanghon','Misua','Ramen Pack','Udon','Pancit Canton','Macaroni','Lasagna Sheets','Angel Hair','Vermicelli','Flat Noodles'],
        'Sauces & Dips' => ['BBQ Sauce','Teriyaki Sauce','Salsa','Cheese Dip','Ranch Dressing','Gravy Mix','Sweet Chili','Sriracha','Pesto','Pasta Sauce','Alfredo Sauce','Curry Sauce','Hoisin Sauce','Pizza Sauce','Marinara'],
        'Baking Supplies' => ['All-Purpose Flour 1kg','White Sugar 1kg','Baking Powder','Baking Soda','Vanilla Extract','Cocoa Powder','Yeast','Brown Sugar 1kg','Food Coloring','Sprinkles','Cake Flour','Powdered Sugar','Chocolate Chips','Gelatin','Cream of Tartar'],
        'Paper & Tissue' => ['Tissue Roll 4pk','Toilet Paper 9pk','Paper Towels 3pk','Facial Tissue','Table Napkins','Bathroom Tissue','Tissue Roll Single','Wet Tissue','Cotton Balls','Paper Bag'],
    ];

    private array $brands = [
        'Lucky','Golden','Royal','Super','Star','Prime','Fresh','Pure','Best','Top',
        'Max','Ultra','Mega','Big','Happy','Smart','Quick','Easy','Good','Great',
        'Natural','Eco','Silver','Crystal','Diamond','Pearl','Ocean','Mountain','Sun','Moon',
        'Valley','Forest','River','Classic','Premium','Select','Choice','Supreme','Excel','Master',
    ];

    private array $sizes = [
        '25g','30g','50g','75g','100g','120g','150g','175g','200g','250g','300g','350g','400g','500g','750g','1kg',
        '50ml','100ml','150ml','200ml','250ml','330ml','350ml','500ml','750ml','1L','1.5L','2L',
        'Small','Medium','Large','XL','Single','Twin Pack','3-Pack','6-Pack','12-Pack','Family Size',
    ];

    private array $firstNames = [
        'Ronald','Angie','Maria','Juan','Jose','Ana','Pedro','Rosa','Carlo','Miguel',
        'Isabella','Sofia','Gabriel','Andrea','Daniel','Patricia','Rafael','Camille','Antonio','Teresa',
        'Marco','Lucia','David','Elena','Carlos','Carmen','Fernando','Laura','Roberto','Beatriz',
        'Eduardo','Monica','Ricardo','Diana','Alberto','Veronica','Francisco','Gloria','Sergio','Nicole',
        'Enrique','Michelle','Alejandro','Christine','Victor','Jennifer','Manuel','Katherine','Ruben','Stephanie',
        'Aaron','Abigail','Adam','Adrian','Agnes','Aiden','Aimee','Alan','Albert','Alicia',
        'Allen','Amanda','Amber','Amy','Andrew','Angela','Anna','Anthony','April','Archie',
        'Ariel','Arnold','Arthur','Ashley','Austin','Barbara','Barry','Benedict','Benjamin','Bernard',
        'Beth','Billy','Blake','Bobby','Bradley','Brandon','Brenda','Brian','Bridget','Bruce',
        'Bryan','Caleb','Calvin','Cameron','Candice','Carl','Carol','Caroline','Catherine','Cecil',
        'Chad','Charles','Charlotte','Chelsea','Chester','Chris','Christian','Christina','Christopher','Clara',
        'Clarence','Clark','Claudia','Clayton','Clifford','Clyde','Cody','Colin','Connie','Connor',
        'Conrad','Corazon','Courtney','Craig','Crystal','Curtis','Cynthia','Daisy','Dale','Dalton',
        'Damon','Danielle','Daphne','Darren','Darwin','Dean','Deborah','Denise','Dennis','Derek',
        'Dexter','Dominic','Donald','Donna','Dorothy','Douglas','Drew','Dustin','Dylan','Earl',
        'Edgar','Edith','Edmund','Edward','Edwin','Eileen','Elaine','Eleanor','Elijah','Elizabeth',
        'Ella','Ellen','Elmer','Elvira','Emily','Emma','Emmanuel','Eric','Ernest','Erwin',
        'Esther','Ethan','Eugene','Eva','Evan','Evelyn','Faith','Fatima','Felix','Fiona',
        'Florence','Floyd','Frances','Francis','Frank','Frederick','Fritz','Gail','Gary','Gavin',
        'Gene','George','Gerald','Gilbert','Gina','Gladys','Glen','Gordon','Grace','Greg',
    ];

    public function handle(): int
    {
        $tag       = $this->option('tag');
        $custCount = (int) $this->option('customers');
        $prodCount = (int) $this->option('products');
        $dryRun    = $this->option('dry-run');

        $this->info("╔══════════════════════════════════════════╗");
        $this->info("║   INSA POS Test Data Generator: {$tag}   ║");
        $this->info("╚══════════════════════════════════════════╝");
        $this->info("Products: ~" . number_format($prodCount) . " | Customers: " . number_format($custCount));

        if ($dryRun) {
            $this->warn('DRY RUN — nothing will be written.');
            return 0;
        }

        $branch = DB::table('branches')->first();
        if (!$branch) { $this->error('No branch found.'); return 1; }

        $user = DB::table('users')->where('branch_id', $branch->id)->first();
        if (!$user) { $this->error('No user found.'); return 1; }

        $this->info("Branch: {$branch->name} | User: {$user->name}");
        $this->newLine();

        $catIds     = $this->seedCategories();
        $products   = $this->seedProducts($catIds, $prodCount, $branch->id);
        $customerIds= $this->seedCustomers($custCount);
        $this->seedTransactions($customerIds, $products, $branch->id, $user->id, $tag);

        $this->newLine();
        $this->info("ALL DONE — tag: {$tag}");
        $this->info("To reverse: DELETE FROM pos_sale_items WHERE sale_id IN (SELECT id FROM pos_sales WHERE sale_number LIKE '{$tag}-%');");
        $this->info("            DELETE FROM pos_sales WHERE sale_number LIKE '{$tag}-%';");
        $this->info("            DELETE FROM customers WHERE last_name = 'Test';");
        return 0;
    }

    private function seedCategories(): array
    {
        $this->info('[1/4] Categories...');
        $existing = Category::pluck('id', 'name')->toArray();
        $ids = [];
        foreach (array_keys($this->categoryProducts) as $name) {
            $ids[$name] = $existing[$name] ?? Category::create(['name' => $name])->id;
        }
        $this->info('  ' . count($ids) . ' categories ready');
        return $ids;
    }

    private function seedProducts(array $catIds, int $target, int $branchId): array
    {
        $this->info('[2/4] Products...');
        $existing = Product::count();
        $toCreate = max(0, $target - $existing);
        $this->info("  Existing: {$existing} | To create: {$toCreate}");

        if ($toCreate > 0) {
            $bar = $this->output->createProgressBar($toCreate);
            $catNames = array_keys($catIds);
            $batch = [];
            $counter = $existing;
            $now = Carbon::now();

            for ($i = 0; $i < $toCreate; $i++) {
                $counter++;
                $catName   = $catNames[array_rand($catNames)];
                $templates = $this->categoryProducts[$catName];
                $template  = $templates[array_rand($templates)];
                $brand     = $this->brands[array_rand($this->brands)];
                $size      = $this->sizes[array_rand($this->sizes)];
                $prefix    = strtoupper(substr(preg_replace('/[^A-Z]/i', '', $catName), 0, 3));

                $batch[] = [
                    'name'        => "{$brand} {$template} {$size}",
                    'sku'         => $prefix . str_pad($counter, 6, '0', STR_PAD_LEFT),
                    'barcode'     => '48' . str_pad($counter, 11, '0', STR_PAD_LEFT),
                    'price'       => $this->priceFor($catName),
                    'category_id' => $catIds[$catName],
                    'active'      => true,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ];

                if (count($batch) >= self::BATCH) {
                    DB::table('products')->insert($batch);
                    $bar->advance(count($batch));
                    $batch = [];
                }
            }
            if ($batch) { DB::table('products')->insert($batch); $bar->advance(count($batch)); }
            $bar->finish();
            $this->newLine();
        }

        $products = DB::table('products')->where('active', true)->select('id','name','sku','barcode','price')->get();
        $this->info("  Total products: " . $products->count());

        $this->info('  Adding stock...');
        $hasStock = DB::table('stock_movements')->where('branch_id', $branchId)->distinct()->pluck('product_id')->toArray();
        $needStock = $products->whereNotIn('id', $hasStock);

        if ($needStock->count() > 0) {
            $batch = [];
            $now = Carbon::now();
            foreach ($needStock as $p) {
                $batch[] = [
                    'branch_id' => $branchId, 'product_id' => $p->id, 'type' => 'stock_in',
                    'qty' => rand(100, 9999), 'reference_number' => 'INIT-STOCK',
                    'created_at' => $now, 'updated_at' => $now,
                ];
                if (count($batch) >= self::BATCH) {
                    DB::table('stock_movements')->insert($batch);
                    $batch = [];
                }
            }
            if ($batch) DB::table('stock_movements')->insert($batch);
            $this->info("  Stock added for {$needStock->count()} products");
        }

        return $products->all();
    }

    private function priceFor(string $cat): float
    {
        $r = [
            'Snacks'=>[8,150],'Beverages'=>[10,120],'Personal Care'=>[25,350],'Canned Goods'=>[15,180],
            'Dairy'=>[30,250],'Frozen Foods'=>[40,500],'Bread & Bakery'=>[10,200],'Rice & Grains'=>[35,800],
            'Condiments'=>[10,180],'Instant Food'=>[8,120],'Household'=>[20,400],'Baby Products'=>[50,600],
            'Pet Supplies'=>[30,500],'Health & Wellness'=>[10,350],'School & Office'=>[5,100],
            'Cooking Oil'=>[30,350],'Sweets & Candy'=>[5,150],'Alcohol'=>[40,800],
            'Meat & Poultry'=>[80,600],'Seafood'=>[80,700],'Spices & Seasoning'=>[10,150],
            'Laundry'=>[15,300],'Electronics'=>[50,1500],'Kitchen Supplies'=>[10,200],
            'Beauty & Cosmetics'=>[30,500],'Noodles & Pasta'=>[10,100],'Sauces & Dips'=>[15,200],
            'Baking Supplies'=>[15,250],'Paper & Tissue'=>[10,150],
        ];
        [$lo,$hi] = $r[$cat] ?? [10,300];
        return round(rand($lo * 100, $hi * 100) / 100, 2);
    }

    private function seedCustomers(int $count): array
    {
        $this->info('[3/4] Customers...');
        $bar = $this->output->createProgressBar($count);
        $batch = [];
        $now = Carbon::now();
        $nameCount = count($this->firstNames);

        for ($i = 0; $i < $count; $i++) {
            $fn = $this->firstNames[$i % $nameCount];
            if ($i >= $nameCount) $fn .= ' ' . intdiv($i, $nameCount);

            $batch[] = [
                'uuid'        => (string) Str::uuid(),
                'card_number' => 'TC' . str_pad($i + 1, 8, '0', STR_PAD_LEFT),
                'first_name'  => $fn,
                'last_name'   => 'Test',
                'phone'       => '09' . rand(100000000, 999999999),
                'email'       => strtolower(preg_replace('/\s+/', '', $fn)) . '.test' . ($i+1) . '@test.com',
                'loyalty_points' => 0,
                'status'      => 'active',
                'created_at'  => $now,
                'updated_at'  => $now,
            ];

            if (count($batch) >= self::BATCH) {
                DB::table('customers')->insert($batch);
                $bar->advance(count($batch));
                $batch = [];
            }
        }
        if ($batch) { DB::table('customers')->insert($batch); $bar->advance(count($batch)); }
        $bar->finish();
        $this->newLine();

        $ids = DB::table('customers')->where('last_name', 'Test')->pluck('id')->toArray();
        $this->info("  {$count} test customers created");
        return $ids;
    }

    private function seedTransactions(array $customerIds, array $products, int $branchId, int $userId, string $tag): void
    {
        $this->info('[4/4] Transactions...');
        $prodArr = array_values($products);
        $prodCount = count($prodArr);
        $payments = ['cash','cash','cash','cash','gcash','gcash','card'];
        $startDate = Carbon::now()->subDays(90);
        $endDate   = Carbon::now();

        $shiftId = DB::table('pos_shifts')->insertGetId([
            'branch_id' => $branchId, 'user_id' => $userId,
            'opened_at' => $startDate, 'closing_cash' => null,
            'opening_cash' => 5000, 'status' => 'closed',
            'closed_at' => $endDate,
            'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
        ]);

        $total = count($customerIds);
        $bar = $this->output->createProgressBar($total);
        $totalRevenue = 0;
        $saleSeq = DB::table('pos_sales')->max('id') ?? 0;

        foreach ($customerIds as $custId) {
            $saleSeq++;
            $targetSpend = rand(200000, 1000000) / 100; // P2,000 - P10,000
            $saleDate = Carbon::createFromTimestamp(rand($startDate->timestamp, $endDate->timestamp));
            $saleNum  = $tag . '-' . str_pad($saleSeq, 8, '0', STR_PAD_LEFT);
            $payment  = $payments[array_rand($payments)];

            $items = [];
            $subtotal = 0;

            while ($subtotal < $targetSpend) {
                $p   = $prodArr[rand(0, $prodCount - 1)];
                $qty = rand(1, 5);
                $lt  = round($p->price * $qty, 2);

                if ($subtotal + $lt > $targetSpend * 1.2 && $subtotal > 0) {
                    break;
                }

                $items[] = [
                    'product_id' => $p->id, 'product_name' => $p->name,
                    'sku' => $p->sku, 'barcode' => $p->barcode,
                    'qty' => $qty, 'price' => $p->price, 'discount' => 0, 'line_total' => $lt,
                    'created_at' => $saleDate, 'updated_at' => $saleDate,
                ];
                $subtotal += $lt;
            }

            $subtotal = round($subtotal, 2);
            $tendered = $payment === 'cash' ? (float)(ceil($subtotal / 50) * 50) : $subtotal;

            $saleId = DB::table('pos_sales')->insertGetId([
                'sale_number'     => $saleNum,
                'branch_id'       => $branchId,
                'cashier_id'      => $userId,
                'shift_id'        => $shiftId,
                'member_id'       => $custId,
                'subtotal'        => $subtotal,
                'discount_total'  => 0,
                'total'           => $subtotal,
                'payment_method'  => $payment,
                'amount_tendered' => $tendered,
                'change_due'      => round($tendered - $subtotal, 2),
                'status'          => 'completed',
                'sold_at'         => $saleDate,
                'created_at'      => $saleDate,
                'updated_at'      => $saleDate,
            ]);

            foreach ($items as &$item) {
                $item['sale_id'] = $saleId;
            }
            DB::table('pos_sale_items')->insert($items);

            $stockMoves = [];
            foreach ($items as $item) {
                $stockMoves[] = [
                    'branch_id' => $branchId, 'product_id' => $item['product_id'],
                    'type' => 'sale', 'qty' => -$item['qty'],
                    'reference_number' => $saleNum,
                    'created_at' => $saleDate, 'updated_at' => $saleDate,
                ];
            }
            DB::table('stock_movements')->insert($stockMoves);

            $totalRevenue += $subtotal;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("  Total revenue: P" . number_format($totalRevenue, 2));
        $this->info("  Avg per customer: P" . number_format($totalRevenue / max(1, $total), 2));
    }
}
