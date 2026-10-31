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
        User::updateOrCreate(
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
        User::updateOrCreate(
            ['email' => 'player@daotomo.site'],
            [
                'username' => 'DragonPlayer',
                'name' => 'Dragon Player',
                'password' => Hash::make('Player@123456'),
                'role' => 'user',
                'minecraft_username' => 'TestDummyBot',
                'minecraft_uuid' => '00000000-0000-0000-0000-000000000001',
                'game_linked_at' => now(),
                'skin_url' => '/img/steve.png',
                'coins' => 500,
            ]
        );

        // 3. Seed Demo User
        User::updateOrCreate(
            ['email' => 'user@daotomo.site'],
            [
                'username' => 'demouser',
                'name' => 'Demo User',
                'password' => Hash::make('User@123456'),
                'role' => 'user',
                'minecraft_username' => 'DemoUser',
                'skin_url' => '/img/steve.png',
                'coins' => 100,
            ]
        );

        // 4. Seed the Original 12 Products from seed.js
        $img = function ($text) {
            return 'https://placehold.co/800x800/png?text=' . urlencode($text);
        };
        $gallery = function ($text) use ($img) {
            return [$img($text . ' - 1'), $img($text . ' - 2')];
        };

        $products = [
            [
                'name' => 'Rank Knight',
                'slug' => 'rank-knight',
                'short_description' => 'Rank khởi đầu dành cho tân thủ Đảo Tò Mò với bộ đặc quyền tiện ích.',
                'description' => 'Rank Knight cung cấp 3 sethome, kit Knight định kỳ, các lệnh tiện ích /workbench, /disposal, /near và icon Rank Knight độc quyền trên chat & tab.',
                'price' => 39000,
                'original_price' => 59000,
                'image' => $img('Rank Knight'),
                'images' => $gallery('Rank Knight'),
                'category' => 'vip',
                'in_stock' => true,
                'stock_quantity' => 999,
                'is_active' => true,
                'featured' => true,
                'sort_order' => 1,
                'game_reward' => 'rank_knight',
            ],
            [
                'name' => 'Rank Legend',
                'slug' => 'rank-legend',
                'short_description' => 'Rank Huyền Thoại với quyền lợi bay fly, hồi phục và nhiều sethome hơn.',
                'description' => 'Rank Legend mở khóa 5 sethome, kit Legend chiến binh, lệnh /feed, /fly, /hat và biểu tượng Rank Legend rực rỡ.',
                'price' => 59000,
                'original_price' => 89000,
                'image' => $img('Rank Legend'),
                'images' => $gallery('Rank Legend'),
                'category' => 'vip',
                'in_stock' => true,
                'stock_quantity' => 999,
                'is_active' => true,
                'featured' => true,
                'sort_order' => 2,
                'game_reward' => 'rank_legend',
            ],
            [
                'name' => 'Rank Master',
                'slug' => 'rank-master',
                'short_description' => 'Bậc thầy sinh tồn với kho đồ Enderchest di động và lệnh /back tức thì.',
                'description' => 'Rank Master cho phép lưu 7 sethome, kit Master vũ khí giáp vip, lệnh /enderchest, /back, /heal và icon Rank Master uy lực.',
                'price' => 79000,
                'original_price' => 119000,
                'image' => $img('Rank Master'),
                'images' => $gallery('Rank Master'),
                'category' => 'vip',
                'in_stock' => true,
                'stock_quantity' => 999,
                'is_active' => true,
                'featured' => true,
                'sort_order' => 3,
                'game_reward' => 'rank_master',
            ],
            [
                'name' => 'Rank Overlord',
                'slug' => 'rank-overlord',
                'short_description' => 'Bá chủ Đảo Tò Mò với đặc quyền sửa đồ repair all và thời tiết riêng.',
                'description' => 'Rank Overlord sở hữu 10 sethome, kit Overlord siêu cấp, lệnh /repair, /pweather, /ptime, /fly tốc độ cao và huy hiệu Overlord bá đạo.',
                'price' => 109000,
                'original_price' => 159000,
                'image' => $img('Rank Overlord'),
                'images' => $gallery('Rank Overlord'),
                'category' => 'vip',
                'in_stock' => true,
                'stock_quantity' => 999,
                'is_active' => true,
                'featured' => true,
                'sort_order' => 4,
                'game_reward' => 'rank_overlord',
            ],
            [
                'name' => 'Rank God',
                'slug' => 'rank-god',
                'short_description' => 'Rank Tối Thượng Thần Thánh với toàn bộ đặc quyền cao cấp nhất server.',
                'description' => 'Rank God mở khóa 15 sethome, kit God thần thánh, toàn quyền fly, repair all, đổi màu tên, nick name và biểu tượng Thần Vương độc nhất vô nhị.',
                'price' => 159000,
                'original_price' => 229000,
                'image' => $img('Rank God'),
                'images' => $gallery('Rank God'),
                'category' => 'vip',
                'in_stock' => true,
                'stock_quantity' => 999,
                'is_active' => true,
                'featured' => true,
                'sort_order' => 5,
                'game_reward' => 'rank_god',
            ],
            [
                'name' => 'Gói Coin Minecraft 100K',
                'slug' => 'goi-coin-minecraft-100k',
                'short_description' => 'Nạp 100.000 Coin để đổi vật phẩm và tiện ích trong server.',
                'description' => 'Gói Coin Minecraft 100K cung cấp 100.000 Coin vào tài khoản game sau khi đơn hàng được xác nhận theo quy trình của server. Coin có thể được sử dụng cho các tính năng và vật phẩm được hỗ trợ.',
                'price' => 89000,
                'original_price' => 100000,
                'image' => $img('100K Minecraft Coin'),
                'images' => $gallery('100K Minecraft Coin'),
                'category' => 'bundle',
                'in_stock' => true,
                'stock_quantity' => 200,
                'is_active' => true,
                'featured' => false,
                'sort_order' => 6,
                'game_reward' => 'coin_100k',
            ],
            [
                'name' => 'Gói Skin Minecraft',
                'slug' => 'goi-skin-minecraft',
                'short_description' => 'Bộ skin Minecraft độc quyền giúp nhân vật nổi bật hơn.',
                'description' => 'Gói Skin Minecraft gồm bộ skin được tuyển chọn theo phong cách Đảo Tò Mò. Phù hợp cho người chơi muốn thay đổi diện mạo nhân vật và tạo dấu ấn riêng trong server.',
                'price' => 49000,
                'original_price' => 69000,
                'image' => $img('Minecraft Skin Pack'),
                'images' => $gallery('Minecraft Skin Pack'),
                'category' => 'cosmetic',
                'in_stock' => true,
                'stock_quantity' => 100,
                'is_active' => true,
                'featured' => false,
                'sort_order' => 7,
                'game_reward' => 'skin_pack',
            ],
            [
                'name' => 'Hòm Epic',
                'slug' => 'hom-epic',
                'short_description' => 'Hòm Epic chứa cơ hội nhận vật phẩm hiếm trong server.',
                'description' => 'Hòm Epic là một gói vật phẩm ngẫu nhiên dành cho người chơi thích khám phá. Phần thưởng được phân phối theo hệ thống crate của server và có thể bao gồm các vật phẩm cosmetic hoặc tiện ích.',
                'price' => 29000,
                'original_price' => 35000,
                'image' => $img('Epic Crate'),
                'images' => $gallery('Epic Crate'),
                'category' => 'crate',
                'in_stock' => true,
                'stock_quantity' => 150,
                'is_active' => true,
                'featured' => false,
                'sort_order' => 8,
                'game_reward' => 'epic_crate_x1',
            ],
            [
                'name' => 'DaVinci Resolve Basic',
                'slug' => 'davinci-resolve-basic',
                'short_description' => 'Gói tài nguyên học và thực hành dựng video cơ bản.',
                'description' => 'DaVinci Resolve Basic là bộ tài nguyên demo phục vụ người mới học dựng video: nội dung hướng dẫn, bài thực hành và tài nguyên mẫu. Sản phẩm số, không bao gồm giấy phép phần mềm của bên thứ ba.',
                'price' => 79000,
                'original_price' => 119000,
                'image' => $img('DaVinci Resolve Basic'),
                'images' => $gallery('DaVinci Resolve Basic'),
                'category' => 'bundle',
                'in_stock' => true,
                'stock_quantity' => 50,
                'is_active' => true,
                'featured' => false,
                'sort_order' => 9,
                'game_reward' => '',
            ],
            [
                'name' => 'Khóa học Lập trình Web cơ bản',
                'slug' => 'khoa-hoc-lap-trinh-web-co-ban',
                'short_description' => 'Lộ trình nhập môn HTML, CSS và JavaScript cho người mới.',
                'description' => 'Khóa học Lập trình Web cơ bản giúp người mới nắm nền tảng HTML, CSS và JavaScript thông qua các bài học ngắn và dự án thực hành. Nội dung tập trung vào kiến thức cần thiết để tự xây dựng website đầu tiên.',
                'price' => 199000,
                'original_price' => 299000,
                'image' => $img('Web Development Basic'),
                'images' => $gallery('Web Development Basic'),
                'category' => 'bundle',
                'in_stock' => true,
                'stock_quantity' => 999,
                'is_active' => true,
                'featured' => true,
                'sort_order' => 10,
                'game_reward' => '',
            ],
            [
                'name' => 'Khóa học JavaScript',
                'slug' => 'khoa-hoc-javascript',
                'short_description' => 'Học JavaScript từ nền tảng đến xây dựng ứng dụng web thực tế.',
                'description' => 'Khóa học JavaScript tập trung vào biến, hàm, mảng, object, DOM, async/await và cách tổ chức code cho ứng dụng web. Có bài tập thực hành để người học xây dựng nền tảng JavaScript chắc hơn.',
                'price' => 249000,
                'original_price' => 349000,
                'image' => $img('JavaScript Course'),
                'images' => $gallery('JavaScript Course'),
                'category' => 'bundle',
                'in_stock' => true,
                'stock_quantity' => 999,
                'is_active' => true,
                'featured' => true,
                'sort_order' => 11,
                'game_reward' => '',
            ],
            [
                'name' => 'Khóa học Python',
                'slug' => 'khoa-hoc-python',
                'short_description' => 'Khóa Python nhập môn với bài tập và project thực hành.',
                'description' => 'Khóa học Python dành cho người mới bắt đầu, đi từ cú pháp cơ bản đến function, collection, xử lý file và xây dựng project nhỏ. Nội dung hướng tới khả năng tự viết chương trình Python sau khi hoàn thành.',
                'price' => 229000,
                'original_price' => 329000,
                'image' => $img('Python Course'),
                'images' => $gallery('Python Course'),
                'category' => 'bundle',
                'in_stock' => true,
                'stock_quantity' => 999,
                'is_active' => true,
                'featured' => false,
                'sort_order' => 12,
                'game_reward' => '',
            ],
            [
                'name' => 'Gói tài nguyên Minecraft',
                'slug' => 'goi-tai-nguyen-minecraft',
                'short_description' => 'Bộ resource và tài nguyên hình ảnh phục vụ server Minecraft.',
                'description' => 'Gói tài nguyên Minecraft gồm các asset demo phục vụ trang trí, thiết kế nội dung và làm video liên quan đến Minecraft. Tài nguyên được sắp xếp theo nhóm để dễ tìm và sử dụng.',
                'price' => 59000,
                'original_price' => 89000,
                'image' => $img('Minecraft Resources'),
                'images' => $gallery('Minecraft Resources'),
                'category' => 'cosmetic',
                'in_stock' => true,
                'stock_quantity' => 100,
                'is_active' => true,
                'featured' => false,
                'sort_order' => 13,
                'game_reward' => 'minecraft_resource_pack',
            ],
            [
                'name' => 'Gói Modpack Premium',
                'slug' => 'goi-modpack-premium',
                'short_description' => 'Bộ modpack demo được tuyển chọn cho trải nghiệm Minecraft nâng cao.',
                'description' => 'Gói Modpack Premium là bộ modpack demo được tuyển chọn theo chủ đề Minecraft. Sản phẩm phù hợp với người chơi muốn thử một trải nghiệm nhiều nội dung hơn vanilla và có hướng dẫn cài đặt cơ bản.',
                'price' => 129000,
                'original_price' => 179000,
                'image' => $img('Modpack Premium'),
                'images' => $gallery('Modpack Premium'),
                'category' => 'bundle',
                'in_stock' => true,
                'stock_quantity' => 80,
                'is_active' => true,
                'featured' => true,
                'sort_order' => 14,
                'game_reward' => 'modpack_premium',
            ],
        ];

        $targetSlugs = array_column($products, 'slug');

        // Clean up or deactivate old products not in the new catalog
        Product::whereNotIn('slug', $targetSlugs)
            ->whereDoesntHave('orderItems')
            ->delete();

        Product::whereNotIn('slug', $targetSlugs)
            ->update(['is_active' => false]);

        foreach ($products as $p) {
            Product::updateOrCreate(
                ['slug' => $p['slug']],
                $p
            );
        }
    }
}
