/**
 * Seed script — populates MongoDB with the 8 store products
 * Run with: npm run seed
 */
require('dotenv').config();
const mongoose = require('mongoose');
const Product = require('./models/Product');
const User = require('./models/User');

const products = [
  {
    name: 'Hòm Epic',
    description: 'Mở hòm để nhận ngẫu nhiên một vật phẩm hiếm trong game.',
    price: 20000,
    image: '/img/item1.png',
    category: 'crate',
    gameReward: 'epic_crate_x1',
    featured: false,
  },
  {
    name: 'Cosmetic Pack',
    description: 'Bộ skin độc quyền chỉ có trên server Đảo Tò Mò.',
    price: 35000,
    image: '/img/item2.png',
    category: 'cosmetic',
    gameReward: 'cosmetic_pack_x1',
    featured: true,
  },
  {
    name: 'Legendary Crate',
    description: 'Hòm huyền thoại chứa đồ cực hiếm, tỉ lệ drop cao nhất.',
    price: 50000,
    image: '/img/item3.png',
    category: 'crate',
    gameReward: 'legendary_crate_x1',
    featured: true,
  },
  {
    name: 'Pet Mini',
    description: 'Pet dễ thương đi theo bạn trong suốt hành trình.',
    price: 40000,
    image: '/img/item4.png',
    category: 'pet',
    gameReward: 'pet_mini_x1',
    featured: false,
  },
  {
    name: 'Crate Key',
    description: 'Chìa khóa để mở các rương đặc biệt trên server.',
    price: 15000,
    image: '/img/item5.png',
    category: 'key',
    gameReward: 'crate_key_x1',
    featured: false,
  },
  {
    name: 'Skin Kiếm Rồng',
    description: 'Cosmetic kiếm rồng cực hiếm, thể hiện đẳng cấp của bạn.',
    price: 30000,
    image: '/img/item6.png',
    category: 'cosmetic',
    gameReward: 'dragon_sword_skin',
    featured: false,
  },
  {
    name: 'Bundle Starter',
    description: 'Gói đồ khởi đầu hoàn hảo cho người mới tham gia server.',
    price: 25000,
    image: '/img/item7.png',
    category: 'bundle',
    gameReward: 'starter_bundle_x1',
    featured: false,
  },
  {
    name: 'VIP Cosmetic',
    description: 'Hiệu ứng VIP đặc biệt — nổi bật giữa đám đông.',
    price: 60000,
    image: '/img/item8.png',
    category: 'vip',
    gameReward: 'vip_cosmetic_pack',
    featured: true,
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

    // Clear existing data
    await Product.deleteMany({});
    await User.deleteMany({ role: 'admin' });
    console.log('🗑️  Đã xóa dữ liệu cũ');

    // Insert products
    const inserted = await Product.insertMany(products);
    console.log(`✅ Đã thêm ${inserted.length} sản phẩm`);

    // Create admin user
    await User.create(adminUser);
    console.log(`✅ Đã tạo tài khoản admin`);
    console.log(`   Email: ${adminUser.email}`);
    console.log(`   Password: ${adminUser.password}`);

    console.log('\n🏝️  Seed hoàn tất! Server sẵn sàng.');
  } catch (error) {
    console.error('❌ Seed lỗi:', error.message);
  } finally {
    await mongoose.disconnect();
    process.exit(0);
  }
}

seed();
