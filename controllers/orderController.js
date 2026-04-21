const Order = require('../models/Order');
const Product = require('../models/Product');

// POST /api/orders  — place an order
const createOrder = async (req, res) => {
  try {
    const { items, minecraftUsername, note } = req.body;

    if (!items || !Array.isArray(items) || items.length === 0) {
      return res.status(400).json({ success: false, message: 'Giỏ hàng trống.' });
    }

    // Fetch prices from DB (never trust client-sent prices)
    const enriched = [];
    let total = 0;

    for (const item of items) {
      const product = await Product.findById(item.productId);
      if (!product) {
        return res.status(404).json({ success: false, message: `Sản phẩm không tồn tại.` });
      }
      if (!product.inStock) {
        return res.status(400).json({ success: false, message: `${product.name} đã hết hàng.` });
      }
      const qty = Math.max(1, parseInt(item.quantity) || 1);
      total += product.price * qty;
      enriched.push({
        product: product._id,
        name: product.name,
        price: product.price,
        quantity: qty,
      });
    }

    const order = await Order.create({
      user: req.user._id,
      items: enriched,
      total,
      minecraftUsername: minecraftUsername || req.user.minecraftUsername || '',
      note: note || '',
    });

    res.status(201).json({
      success: true,
      message: 'Đặt hàng thành công! Vui lòng thanh toán qua QR để hoàn tất.',
      data: order,
    });
  } catch (error) {
    console.error('createOrder error:', error);
    res.status(500).json({ success: false, message: 'Lỗi server.' });
  }
};

// GET /api/orders/my  — get current user's orders
const getMyOrders = async (req, res) => {
  try {
    const orders = await Order.find({ user: req.user._id })
      .populate('items.product', 'name image price')
      .sort({ createdAt: -1 });
    res.json({ success: true, count: orders.length, data: orders });
  } catch (error) {
    res.status(500).json({ success: false, message: 'Lỗi server.' });
  }
};

// GET /api/orders/:id  — get single order (owner or admin)
const getOrderById = async (req, res) => {
  try {
    const order = await Order.findById(req.params.id).populate('items.product', 'name image price');
    if (!order) return res.status(404).json({ success: false, message: 'Không tìm thấy đơn hàng.' });

    const isOwner = order.user.toString() === req.user._id.toString();
    const isAdmin = req.user.role === 'admin';
    if (!isOwner && !isAdmin) {
      return res.status(403).json({ success: false, message: 'Bạn không có quyền xem đơn hàng này.' });
    }

    res.json({ success: true, data: order });
  } catch (error) {
    res.status(500).json({ success: false, message: 'Lỗi server.' });
  }
};

// PUT /api/orders/:id/status  — admin updates order status
const updateOrderStatus = async (req, res) => {
  try {
    const { status } = req.body;
    const validStatuses = ['pending', 'paid', 'delivered', 'cancelled'];
    if (!validStatuses.includes(status)) {
      return res.status(400).json({ success: false, message: 'Trạng thái không hợp lệ.' });
    }

    const order = await Order.findByIdAndUpdate(
      req.params.id,
      { status },
      { new: true }
    );
    if (!order) return res.status(404).json({ success: false, message: 'Không tìm thấy đơn hàng.' });

    res.json({ success: true, message: `Đã cập nhật trạng thái: ${status}`, data: order });
  } catch (error) {
    res.status(500).json({ success: false, message: 'Lỗi server.' });
  }
};

// GET /api/orders  — admin: all orders
const getAllOrders = async (req, res) => {
  try {
    const orders = await Order.find()
      .populate('user', 'username email')
      .populate('items.product', 'name price')
      .sort({ createdAt: -1 });
    res.json({ success: true, count: orders.length, data: orders });
  } catch (error) {
    res.status(500).json({ success: false, message: 'Lỗi server.' });
  }
};

module.exports = { createOrder, getMyOrders, getOrderById, updateOrderStatus, getAllOrders };
