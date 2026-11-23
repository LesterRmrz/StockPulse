<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * 10 categories x 15 hand-written product names = 150 products,
 * plus 20 suppliers (two per category).
 *
 * Product names are written out instead of generated so the demo looks like a
 * real shop rather than "Incredible Granite Chair".
 */
class CatalogSeeder extends Seeder
{
    /** prefix => [category name, [min cost, max cost], product names] */
    private const CATALOG = [
        'ELC' => ['Electronics', [6, 55], [
            'USB-C Hub 7-Port', 'Wireless Mouse M2', 'Mechanical Keyboard K8', 'Bluetooth Speaker Mini',
            'Noise-Cancelling Earbuds', '65W GaN Charger', 'Power Bank 20000mAh', '1080p Webcam',
            'Smart Plug Wi-Fi', 'LED Strip Light 5m', 'Portable SSD 1TB', 'HDMI Cable 2m',
            'Wireless Charging Pad', 'Smartwatch Band Pro', 'USB Microphone',
        ]],
        'ACC' => ['Computer Accessories', [3, 40], [
            'Laptop Sleeve 14-inch', 'Aluminum Laptop Stand', 'Monitor Riser', 'XL Mouse Pad',
            'Cable Organizer Kit', 'Keyboard Wrist Rest', 'Laptop Cooling Pad', 'Webcam Cover Set',
            'Screen Cleaning Kit', 'Adjustable Phone Stand', 'Tablet Case 11-inch', 'Headphone Hanger',
            'USB Flash Drive 64GB', 'SD Card 128GB', 'Dual Monitor Docking Station',
        ]],
        'HOM' => ['Home & Kitchen', [3, 35], [
            'Ceramic Pour-Over Set', 'Stainless Water Bottle 750 ml', 'French Press 1L', 'Bamboo Cutting Board',
            'Glass Food Container Set', 'Non-Stick Frying Pan 28cm', 'Electric Kettle 1.7L', 'Chef Knife 8-inch',
            'Silicone Spatula Set', 'Insulated Coffee Mug', 'Digital Kitchen Scale', 'Spice Rack Organizer',
            'Linen Table Runner', 'Scented Soy Candle', 'Cotton Bath Towel Set',
        ]],
        'OFF' => ['Office Supplies', [1, 25], [
            'A5 Dot Notebook', 'LED Desk Lamp', 'Gel Pen Set 12-pack', 'Desk Organizer Tray',
            'Sticky Notes Pack', 'Whiteboard A3', 'Document Folder Set', 'Heavy Duty Stapler',
            'Ergonomic Seat Cushion', 'Desk Calendar 2027', 'Highlighter Set', 'Mini Paper Shredder',
            'Label Maker', 'Weekly Planner', 'Classic Fountain Pen',
        ]],
        'BAG' => ['Bags & Travel', [4, 45], [
            'Everyday Backpack 20L', 'Travel Duffel 40L', 'Packing Cubes Set', 'Passport Holder',
            'Crossbody Sling Bag', 'Canvas Tote Bag', 'Luggage Tag Pair', 'Memory Foam Travel Pillow',
            'Toiletry Bag', 'Laptop Backpack 15-inch', 'Waterproof Dry Bag 10L', 'Hard Shell Carry-On',
            'Leather Card Wallet', 'Rain Poncho', 'Universal Travel Adapter',
        ]],
        'HLT' => ['Health & Personal Care', [2, 40], [
            'Electric Toothbrush', 'Digital Thermometer', 'First Aid Kit', 'Reusable Face Masks 5-pack',
            'Hand Sanitizer 500 ml', 'Compact Massage Gun', 'Weekly Pill Organizer', 'Foot Massage Roller',
            'Blood Pressure Monitor', 'Hot Water Bag', 'Posture Corrector', 'Sleep Eye Mask',
            'Bamboo Toothbrush 4-pack', 'Digital Body Scale', 'Electric Heating Pad',
        ]],
        'SPT' => ['Sports & Outdoors', [2, 45], [
            'Yoga Mat 6mm', 'Resistance Bands Set', 'Adjustable Dumbbell 10kg', 'Speed Jump Rope',
            'Foam Roller', 'LED Camping Lantern', 'Hiking Water Bottle 1L', 'Cycling Gloves',
            'Badminton Racket Pair', 'Basketball Size 7', 'Trekking Poles Pair', 'Swim Goggles',
            'Microfiber Gym Towel', 'Folding Camp Chair', 'Running Phone Armband',
        ]],
        'TOY' => ['Toys & Games', [2, 35], [
            'Building Blocks 500pc', 'Jigsaw Puzzle 1000pc', 'Remote Control Car', 'Wooden Chess Set',
            'Party Card Game', 'Plush Bear 30cm', 'Magnetic Tiles 60pc', 'Speed Cube 3x3',
            'Kids Drawing Tablet', 'Volcano Science Kit', 'Strategy Board Game', 'Water Blaster',
            'Rainbow Kite', 'Wooden Train Set', 'Modeling Dough 12-pack',
        ]],
        'PET' => ['Pet Supplies', [2, 40], [
            'Reflective Dog Leash', 'Cat Scratching Post', 'Stainless Pet Bowl', 'Dog Chew Toy',
            'Cat Litter Scoop', 'Pet Bed Medium', 'Grooming Brush', 'Automatic Pet Feeder',
            'Cat Teaser Wand', 'Adjustable Dog Harness', 'Pet Carrier Bag', 'Aquarium Filter',
            'Hanging Bird Feeder', 'Pet Water Fountain', 'Poop Bag Rolls 8-pack',
        ]],
        'BTY' => ['Beauty', [2, 30], [
            'Gentle Facial Cleanser', 'Vitamin C Serum', 'Sunscreen SPF50', 'Hydrating Sheet Masks 10-pack',
            'Lip Balm Set', 'Compact Hair Dryer', 'Makeup Brush Set', 'Micellar Water 400 ml',
            'Ceramic Hair Straightener', 'Nail Care Kit', 'Body Lotion 400 ml', 'Jade Face Roller',
            'Dry Shampoo', 'Rollerball Perfume', 'Cotton Pads 200-pack',
        ]],
    ];

    private const SUPPLIER_NAMES = [
        'Northwind Components', 'Pacific Trade Co.', 'Luzon Electronics Supply', 'Visayas Home Goods',
        'Mindanao Paper Works', 'Orient Office Depot', 'Harbor Bags & Leather', 'Summit Travel Gear',
        'CareWell Distributors', 'GreenLeaf Health Supply', 'Peak Sports Wholesale', 'TrailMark Outdoor',
        'BrightPlay Toys', 'Kidzone Trading', 'Paws & Claws Supply', 'AquaPet Imports',
        'Glow Beauty Wholesale', 'Silk Route Cosmetics', 'Metro Gadget Hub', 'Island Kitchenware',
    ];

    public function run(): void
    {
        $suppliers = collect(self::SUPPLIER_NAMES)->map(fn (string $name) => Supplier::create([
            'name' => $name,
            'contact_name' => fake()->name(),
            'email' => 'orders@'.Str::slug($name).'.example',
            'phone' => fake()->numerify('+63 2 8### ####'),
            'lead_time_days' => fake()->numberBetween(3, 12),
        ]));

        $categoryIndex = 0;

        foreach (self::CATALOG as $prefix => [$categoryName, [$minCost, $maxCost], $names]) {
            $category = Category::create([
                'name' => $categoryName,
                'slug' => Str::slug($categoryName),
            ]);

            // Each category is served by two suppliers.
            $categorySuppliers = $suppliers->slice($categoryIndex * 2, 2)->values();
            $categoryIndex++;

            foreach ($names as $i => $name) {
                $cost = fake()->randomFloat(2, $minCost, $maxCost);
                // 50-110% markup, rounded up to the next .50 (e.g. 38.12 -> 38.50)
                $sell = ceil($cost * fake()->randomFloat(2, 1.5, 2.1) * 2) / 2;

                Product::create([
                    'sku' => sprintf('SP-%s-%04d', $prefix, $i + 1),
                    'name' => $name,
                    'description' => "{$name} from our {$categoryName} range.",
                    'category_id' => $category->id,
                    'default_supplier_id' => $categorySuppliers[$i % 2]->id,
                    'cost_price' => $cost,
                    'sell_price' => $sell,
                    'is_active' => true,
                ]);
            }
        }
    }
}
