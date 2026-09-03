const mongoose = require('mongoose');
const Product = require('../models/Product');

const buildProductFilter = (req) => {
  const { category, search, featured } = req.query;
  const filter = { status: 'active' };

  // Preserve the existing public-store behaviour: products marked out of
  // stock are not shown. New demo products use stock + inStock together.
  filter.inStock = true;

  if (category) filter.category = category;
  if (featured === 'true') filter.featured = true;

  if (search && search.trim()) {
    const keyword = search.trim();
    filter.$or = [
      { name: { $regex: keyword, $options: 'i' } },
      { shortDescription: { $regex: keyword, $options: 'i' } },
      { description: { $regex: keyword, $options: 'i' } },
    ];
  }

  return filter;
};

// GET /api/products
const getAllProducts = async (req, res) => {
  try {
    const { page, limit, sort } = req.query;
    const filter = buildProductFilter(req);

    let query = Product.find(filter);

    if (sort === 'price_asc') query = query.sort({ price: 1 });
    else if (sort === 'price_desc') query = query.sort({ price: -1 });
    else query = query.sort({ createdAt: -1 });

    const usePagination = page !== undefined || limit !== undefined;

    if (usePagination) {
      const pageNumber = Math.max(parseInt(page, 10) || 1, 1);
      const limitNumber = Math.min(Math.max(parseInt(limit, 10) || 12, 1), 100);
      const skip = (pageNumber - 1) * limitNumber;

      const [products, total] = await Promise.all([
        query.skip(skip).limit(limitNumber),
        Product.countDocuments(filter),
      ]);

      return res.json({
        success: true,
        count: products.length,
        total,
        page: pageNumber,
        limit: limitNumber,
        pages: Math.ceil(total / limitNumber),
        data: products,
      });
    }

    const products = await query;
    res.json({ success: true, count: products.length, data: products });
  } catch (error) {
    console.error('getAllProducts error:', error);
    res.status(500).json({ success: false, message: 'Lỗi server khi tải sản phẩm.' });
  }
};

// GET /api/products/slug/:slug
const getProductBySlug = async (req, res) => {
  try {
    const product = await Product.findOne({
      slug: req.params.slug.toLowerCase(),
      status: 'active',
    });

    if (!product) {
      return res.status(404).json({ success: false, message: 'Không tìm thấy sản phẩm' });
    }

    res.json({ success: true, data: product });
  } catch (error) {
    console.error('getProductBySlug error:', error);
    res.status(500).json({ success: false, message: 'Lỗi server.' });
  }
};

// GET /api/products/:id
const getProductById = async (req, res) => {
  try {
    if (!mongoose.isValidObjectId(req.params.id)) {
      return res.status(404).json({ success: false, message: 'Không tìm thấy sản phẩm' });
    }

    const product = await Product.findOne({
      _id: req.params.id,
      status: 'active',
    });

    if (!product) {
      return res.status(404).json({ success: false, message: 'Không tìm thấy sản phẩm' });
    }

    res.json({ success: true, data: product });
  } catch (error) {
    console.error('getProductById error:', error);
    res.status(500).json({ success: false, message: 'Lỗi server.' });
  }
};

// POST /api/products  (admin only)
const createProduct = async (req, res) => {
  try {
    const product = await Product.create(req.body);
    res.status(201).json({ success: true, data: product });
  } catch (error) {
    if (error.name === 'ValidationError') {
      const messages = Object.values(error.errors).map((e) => e.message);
      return res.status(400).json({ success: false, message: messages.join(', ') });
    }
    if (error.code === 11000) {
      return res.status(400).json({ success: false, message: 'Slug sản phẩm đã tồn tại.' });
    }
    console.error('createProduct error:', error);
    res.status(500).json({ success: false, message: 'Lỗi server.' });
  }
};

// PUT /api/products/:id  (admin only)
const updateProduct = async (req, res) => {
  try {
    if (!mongoose.isValidObjectId(req.params.id)) {
      return res.status(404).json({ success: false, message: 'Không tìm thấy sản phẩm.' });
    }

    const product = await Product.findByIdAndUpdate(req.params.id, req.body, {
      new: true,
      runValidators: true,
    });
    if (!product) return res.status(404).json({ success: false, message: 'Không tìm thấy sản phẩm.' });
    res.json({ success: true, data: product });
  } catch (error) {
    if (error.code === 11000) {
      return res.status(400).json({ success: false, message: 'Slug sản phẩm đã tồn tại.' });
    }
    console.error('updateProduct error:', error);
    res.status(500).json({ success: false, message: 'Lỗi server.' });
  }
};

// DELETE /api/products/:id  (admin only)
const deleteProduct = async (req, res) => {
  try {
    if (!mongoose.isValidObjectId(req.params.id)) {
      return res.status(404).json({ success: false, message: 'Không tìm thấy sản phẩm.' });
    }

    const product = await Product.findByIdAndDelete(req.params.id);
    if (!product) return res.status(404).json({ success: false, message: 'Không tìm thấy sản phẩm.' });
    res.json({ success: true, message: 'Đã xóa sản phẩm.' });
  } catch (error) {
    console.error('deleteProduct error:', error);
    res.status(500).json({ success: false, message: 'Lỗi server.' });
  }
};

// GET /api/products/:id/related
const getRelatedProducts = async (req, res) => {
  try {
    if (!mongoose.isValidObjectId(req.params.id)) {
      return res.status(404).json({ success: false, message: 'Không tìm thấy sản phẩm' });
    }

    const product = await Product.findOne({
      _id: req.params.id,
      status: 'active',
    });

    if (!product) {
      return res.status(404).json({ success: false, message: 'Không tìm thấy sản phẩm' });
    }

    const products = await Product.find({
      _id: { $ne: product._id },
      category: product.category,
      status: 'active',
      inStock: true,
    })
      .sort({ featured: -1, createdAt: -1 })
      .limit(4);

    res.json({ success: true, count: products.length, data: products });
  } catch (error) {
    console.error('getRelatedProducts error:', error);
    res.status(500).json({ success: false, message: 'Lỗi server.' });
  }
};

module.exports = {
  getAllProducts,
  getProductById,
  getProductBySlug,
  getRelatedProducts,
  createProduct,
  updateProduct,
  deleteProduct,
};
