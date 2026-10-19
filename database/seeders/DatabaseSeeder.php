<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed Admin User
        $admin = User::updateOrCreate(
            ['email' => 'admin@daotomo.site'],
            [
                'username' => 'admin',
                'name' => 'Admin Đảo Tò Mò',
                'password' => Hash::make('Admin@123456'),
                'role' => 'admin',
                'minecraft_username' => 'AdminDaoTomo',
                'skin_url' => '/img/steve.png',
                'coins' => 99999,
            ]
        );

        // 2. Seed Test Player
        $player = User::updateOrCreate(
            ['email' => 'player@daotomo.site'],
            [
                'username' => 'DragonPlayer',
                'name' => 'Dragon Player',
                'password' => Hash::make('Player@123456'),
                'role' => 'user',
                'minecraft_username' => 'DragonPlayer',
                'skin_url' => '/img/steve.png',
                'coins' => 500,
            ]
        );

        // 3. Seed Default Products
        $products = [
            [
                'name' => 'Gói VIP Hoàng Gia (Vĩnh viễn)',
                'description' => 'Đặc quyền tối thượng: Lệnh /fly bay lượn, x2 EXP toàn server, prefix [👑HOÀNG GIA], 10 Home, truy cập khu vực VIP.',
                'price' => 150000,
                'category' => 'ranks',
                'image' => '/img/economy.jpg',
                'in_stock' => true,
                'stock_quantity' => -1,
                'is_active' => true,
                'featured' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Gói VIP Hiệp Sĩ (30 Ngày)',
                'description' => 'Đặc quyền chiến binh: /heal, /feed hồi máu tức thì, 5 Home, nhận kit đồ Netherite mỗi tuần.',
                'price' => 70000,
                'category' => 'ranks',
                'image' => '/img/survival.jpg',
                'in_stock' => true,
                'stock_quantity' => -1,
                'is_active' => true,
                'featured' => false,
                'sort_order' => 2,
            ],
            [
                'name' => 'Gói VIP Quý Tộc (30 Ngày)',
                'description' => 'Gói cơ bản phù hợp cho người mới: 3 Home, x1.5 EXP khai khoáng, prefix màu vàng sang trọng.',
                'price' => 30000,
                'category' => 'ranks',
                'image' => '/img/event.jpg',
                'in_stock' => true,
                'stock_quantity' => -1,
                'is_active' => true,
                'featured' => false,
                'sort_order' => 3,
            ],
            [
                'name' => 'Chìa Khóa Rương Thần Thoại (x5)',
                'description' => 'Mở rương Thần Thoại tại /warp crate: Cơ hội trúng cánh Elytra thần thánh, kiếm 100 sát thương và xu đảo.',
                'price' => 50000,
                'category' => 'crates',
                'image' => '/img/economy.jpg',
                'in_stock' => true,
                'stock_quantity' => -1,
                'is_active' => true,
                'featured' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'Chìa Khóa Rương Kim Cương (x10)',
                'description' => 'Mở rương Kim Cương: Nhận tài nguyên xây dựng, block khoáng sản và ngọc Ender phong phú.',
                'price' => 25000,
                'category' => 'crates',
                'image' => '/img/survival.jpg',
                'in_stock' => true,
                'stock_quantity' => -1,
                'is_active' => true,
                'featured' => false,
                'sort_order' => 5,
            ],
            [
                'name' => 'Túi 1,000 Xu Đảo',
                'description' => 'Nạp tiền tệ chính của máy chủ. Dùng để giao dịch tại chợ trời /ah và mua sắm nông sản.',
                'price' => 50000,
                'category' => 'currency',
                'image' => '/img/economy.jpg',
                'in_stock' => true,
                'stock_quantity' => -1,
                'is_active' => true,
                'featured' => false,
                'sort_order' => 6,
            ],
            [
                'name' => 'Rương 2,500 Xu Đảo (Tặng 300 Xu)',
                'description' => 'Gói nạp ưu đãi tặng kèm 300 Xu Đảo khuyến mãi. Tỷ giá tốt nhất cho cư dân chăm chỉ.',
                'price' => 100000,
                'category' => 'currency',
                'image' => '/img/economy.jpg',
                'in_stock' => true,
                'stock_quantity' => -1,
                'is_active' => true,
                'featured' => true,
                'sort_order' => 7,
            ],
            [
                'name' => 'Set Giáp Thần Thoại Netherite Prot IV',
                'description' => 'Trọn bộ mũ, áo, quần, giày Netherite max phù phép: Bảo vệ 4, Bền bỉ 3, Sửa chữa mending.',
                'price' => 120000,
                'category' => 'items',
                'image' => '/img/survival.jpg',
                'in_stock' => true,
                'stock_quantity' => 10,
                'is_active' => true,
                'featured' => true,
                'sort_order' => 8,
            ],
            [
                'name' => 'Kiếm Thần Excalibur (Sharpness V, Fire II)',
                'description' => 'Vũ khí cận chiến huyền thoại gây sát thương cực mạnh, chém ra tia lửa quét sạch quái vật.',
                'price' => 80000,
                'category' => 'items',
                'image' => '/img/survival.jpg',
                'in_stock' => true,
                'stock_quantity' => 15,
                'is_active' => true,
                'featured' => false,
                'sort_order' => 9,
            ],
        ];

        foreach ($products as $p) {
            Product::updateOrCreate(['name' => $p['name']], $p);
        }
    }
}
