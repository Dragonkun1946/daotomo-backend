/**
 * Seed script — creates/updates demo store products without deleting other products.
 * Run with: npm run seed
 */
require('dotenv').config();
const mongoose = require('mongoose');
const Product = require('./models/Product');
const User = require('./models/User');

const image = (text) =>
  `https://placehold.co/800x800/png?text=${encodeURIComponent(text)}`;

const gallery = (text) => [
  image(`${text} - 1`),
  image(`${text} - 2`),
];

const products = [
  {
    name: 'Minecraft Premium',
    slug: 'minecraft-premium',
    shortDescription: 'Gói Premium nâng cấp trải nghiệm chơi Minecraft trên server Đảo Tò Mò.',
    description:
      'Minecraft Premium dành cho người chơi muốn mở khóa các quyền lợi và tiện ích cao cấp trên server. Gói gồm quyền truy cập các tính năng Premium theo cấu hình của server và hỗ trợ ưu tiên cho người sở hữu.',
    price: 99000,
    originalPrice: 149000,
    image: image('Minecraft Premium'),
    images: gallery('Minecraft Premium'),
    category: 'vip',
    stock: 100,
    status: 'active',
    featured: true,
    gameReward: 'minecraft_premium',
  },
  {
    name: 'Minecraft Rank VIP',
    slug: 'minecraft-rank-vip',
    shortDescription: 'Rank VIP cho người chơi muốn có thêm quyền lợi và hiệu ứng nổi bật.',
    description:
      'Rank VIP là lựa chọn cân bằng cho người chơi thường xuyên tham gia server. Sản phẩm cung cấp bộ quyền lợi VIP, hiệu ứng và tiện ích trong game theo cấu hình hiện tại của Đảo Tò Mò.',
    price: 79000,
    originalPrice: 109000,
    image: image('Minecraft Rank VIP'),
    images: gallery('Minecraft Rank VIP'),
    category: 'vip',
    stock: 100,
    status: 'active',
    featured: true,
    gameReward: 'rank_vip',
  },
  {
    name: 'Minecraft Rank Premium',
    slug: 'minecraft-rank-premium',
    shortDescription: 'Rank cao cấp với nhiều đặc quyền hơn Rank VIP.',
    description:
      'Rank Premium dành cho người muốn nâng cấp trải nghiệm server với nhiều đặc quyền hơn. Gói phù hợp để làm quà tặng hoặc sử dụng lâu dài trong cộng đồng Đảo Tò Mò.',
    price: 149000,
    originalPrice: 199000,
    image: image('Minecraft Rank Premium'),
    images: gallery('Minecraft Rank Premium'),
    category: 'vip',
    stock: 75,
    status: 'active',
    featured: true,
    gameReward: 'rank_premium',
  },
  {
    name: 'Gói Coin Minecraft 100K',
    slug: 'goi-coin-minecraft-100k',
    shortDescription: 'Nạp 100.000 Coin để đổi vật phẩm và tiện ích trong server.',
    description:
      'Gói Coin Minecraft 100K cung cấp 100.000 Coin vào tài khoản game sau khi đơn hàng được xác nhận theo quy trình của server. Coin có thể được sử dụng cho các tính năng và vật phẩm được hỗ trợ.',
    price: 89000,
    originalPrice: 100000,
    image: image('100K Minecraft Coin'),
    images: gallery('100K Minecraft Coin'),
    category: 'bundle',
    stock: 200,
    status: 'active',
    featured: false,
    gameReward: 'coin_100k',
  },
  {
    name: 'Gói Skin Minecraft',
    slug: 'goi-skin-minecraft',
    shortDescription: 'Bộ skin Minecraft độc quyền giúp nhân vật nổi bật hơn.',
    description:
      'Gói Skin Minecraft gồm bộ skin được tuyển chọn theo phong cách Đảo Tò Mò. Phù hợp cho người chơi muốn thay đổi diện mạo nhân vật và tạo dấu ấn riêng trong server.',
    price: 49000,
    originalPrice: 69000,
    image: image('Minecraft Skin Pack'),
    images: gallery('Minecraft Skin Pack'),
    category: 'cosmetic',
    stock: 100,
    status: 'active',
    featured: false,
    gameReward: 'skin_pack',
  },
  {
    name: 'Hòm Epic',
    slug: 'hom-epic',
    shortDescription: 'Hòm Epic chứa cơ hội nhận vật phẩm hiếm trong server.',
    description:
      'Hòm Epic là một gói vật phẩm ngẫu nhiên dành cho người chơi thích khám phá. Phần thưởng được phân phối theo hệ thống crate của server và có thể bao gồm các vật phẩm cosmetic hoặc tiện ích.',
    price: 29000,
    originalPrice: 35000,
    image: image('Epic Crate'),
    images: gallery('Epic Crate'),
    category: 'crate',
    stock: 150,
    status: 'active',
    featured: false,
    gameReward: 'epic_crate_x1',
  },
  {
    name: 'DaVinci Resolve Basic',
    slug: 'davinci-resolve-basic',
    shortDescription: 'Gói tài nguyên học và thực hành dựng video cơ bản.',
    description:
      'DaVinci Resolve Basic là bộ tài nguyên demo phục vụ người mới học dựng video: nội dung hướng dẫn, bài thực hành và tài nguyên mẫu. Sản phẩm số, không bao gồm giấy phép phần mềm của bên thứ ba.',
    price: 79000,
    originalPrice: 119000,
    image: image('DaVinci Resolve Basic'),
    images: gallery('DaVinci Resolve Basic'),
    category: 'bundle',
    stock: 50,
    status: 'active',
    featured: false,
    gameReward: '',
  },
  {
    name: 'Khóa học Lập trình Web cơ bản',
    slug: 'khoa-hoc-lap-trinh-web-co-ban',
    shortDescription: 'Lộ trình nhập môn HTML, CSS và JavaScript cho người mới.',
    description:
      'Khóa học Lập trình Web cơ bản giúp người mới nắm nền tảng HTML, CSS và JavaScript thông qua các bài học ngắn và dự án thực hành. Nội dung tập trung vào kiến thức cần thiết để tự xây dựng website đầu tiên.',
    price: 199000,
    originalPrice: 299000,
    image: image('Web Development Basic'),
    images: gallery('Web Development Basic'),
    category: 'bundle',
    stock: 999,
    status: 'active',
    featured: true,
    gameReward: '',
  },
  {
    name: 'Khóa học JavaScript',
    slug: 'khoa-hoc-javascript',
    shortDescription: 'Học JavaScript từ nền tảng đến xây dựng ứng dụng web thực tế.',
    description:
      'Khóa học JavaScript tập trung vào biến, hàm, mảng, object, DOM, async/await và cách tổ chức code cho ứng dụng web. Có bài tập thực hành để người học xây dựng nền tảng JavaScript chắc hơn.',
    price: 249000,
    originalPrice: 349000,
    image: image('JavaScript Course'),
    images: gallery('JavaScript Course'),
    category: 'bundle',
    stock: 999,
    status: 'active',
    featured: true,
    gameReward: '',
  },
  {
    name: 'Khóa học Python',
    slug: 'khoa-hoc-python',
    shortDescription: 'Khóa Python nhập môn với bài tập và project thực hành.',
    description:
      'Khóa học Python dành cho người mới bắt đầu, đi từ cú pháp cơ bản đến function, collection, xử lý file và xây dựng project nhỏ. Nội dung hướng tới khả năng tự viết chương trình Python sau khi hoàn thành.',
    price: 229000,
    originalPrice: 329000,
    image: image('Python Course'),
    images: gallery('Python Course'),
    category: 'bundle',
    stock: 999,
    status: 'active',
    featured: false,
    gameReward: '',
  },
  {
    name: 'Gói tài nguyên Minecraft',
    slug: 'goi-tai-nguyen-minecraft',
    shortDescription: 'Bộ resource và tài nguyên hình ảnh phục vụ server Minecraft.',
    description:
      'Gói tài nguyên Minecraft gồm các asset demo phục vụ trang trí, thiết kế nội dung và làm video liên quan đến Minecraft. Tài nguyên được sắp xếp theo nhóm để dễ tìm và sử dụng.',
    price: 59000,
    originalPrice: 89000,
    image: image('Minecraft Resources'),
    images: gallery('Minecraft Resources'),
    category: 'cosmetic',
    stock: 100,
    status: 'active',
    featured: false,
    gameReward: 'minecraft_resource_pack',
  },
  {
    name: 'Gói Modpack Premium',
    slug: 'goi-modpack-premium',
    shortDescription: 'Bộ modpack demo được tuyển chọn cho trải nghiệm Minecraft nâng cao.',
    description:
      'Gói Modpack Premium là bộ modpack demo được tuyển chọn theo chủ đề Minecraft. Sản phẩm phù hợp với người chơi muốn thử một trải nghiệm nhiều nội dung hơn vanilla và có hướng dẫn cài đặt cơ bản.',
    price: 129000,
    originalPrice: 179000,
    image: image('Modpack Premium'),
    images: gallery('Modpack Premium'),
    category: 'bundle',
    stock: 80,
    status: 'active',
    featured: true,
    gameReward: 'modpack_premium',
  },
];

const adminUser = {
  username: 'admin',
  email: 'admin@daotomo.vn',
  password: 'Admin@123456',
  role: 'admin',
};

async function seed() {
  try {
    await mongoose.connect(process.env.MONGODB_URI);
    console.log('✅ Kết nối MongoDB thành công');

    console.log('\n📦 Created/updated products:');
    for (const productData of products) {
      const product = await Product.findOneAndUpdate(
        { slug: productData.slug },
        productData,
        {
          new: true,
          upsert: true,
          runValidators: true,
          setDefaultsOnInsert: true,
        }
      );

      console.log(`   ✓ ${product.name}`);
    }

    // Keep the existing seed behaviour for the development admin account.
    const existingAdmin = await User.findOne({ email: adminUser.email });
    if (!existingAdmin) {
      await User.create(adminUser);
      console.log(`\n✅ Đã tạo tài khoản admin: ${adminUser.email}`);
      console.log(`   Password: ${adminUser.password}`);
    } else {
      console.log(`\nℹ️  Tài khoản admin ${adminUser.email} đã tồn tại, không tạo lại.`);
    }

    const productCount = await Product.countDocuments();
    console.log(`\n📊 Tổng số sản phẩm trong database: ${productCount}`);
    console.log('🏝️  Seed hoàn tất!');
  } catch (error) {
    console.error('❌ Seed lỗi:', error.message);
    process.exitCode = 1;
  } finally {
    await mongoose.disconnect();
  }
}

seed();
