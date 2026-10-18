const mongoose = require('mongoose');
const Order = require('../models/Order');
const Product = require('../models/Product');

// Allowed state transitions
const ORDER_TRANSITIONS = {
  pending: ['processing', 'cancelled'],
  processing: ['delivered', 'cancelled'],
  delivered: [],
  cancelled: [],
};

const PAYMENT_TRANSITIONS = {
  pending: ['paid', 'failed'],
  paid: ['refunded'],
  failed: ['pending'],
  refunded: [],
};

// ─── POST /api/orders — place an order ─────────────────────────────────────────
const createOrder = async (req, res) => {
  try {
    const { items, minecraftUsername, note, paymentMethod, idempotencyKey } = req.body;
    const clientKey = req.headers['x-idempotency-key'] || idempotencyKey;

    // Check idempotency if key provided
    if (clientKey && typeof clientKey === 'string' && clientKey.trim()) {
      const existing = await Order.findOne({
        user: req.user._id,
        idempotencyKey: clientKey.trim(),
      }).populate('items.product', 'name image price category slug');

      if (existing) {
        return res.status(200).json({
          success: true,
          message: 'Đơn hàng đã được tạo trước đó (idempotent replay).',
          data: existing,
        });
      }
    }

    // Validate items array
    if (!items || !Array.isArray(items) || items.length === 0) {
      return res.status(400).json({ success: false, message: 'Giỏ hàng trống hoặc không hợp lệ.' });
    }

    // Validate payment method if sent
    const validPaymentMethods = ['qr', 'manual'];
    const chosenPaymentMethod = paymentMethod || 'qr';
    if (!validPaymentMethods.includes(chosenPaymentMethod)) {
      return res.status(400).json({
        success: false,
        message: 'Phương thức thanh toán không hợp lệ. Chỉ chấp nhận qr hoặc manual.',
      });
    }

    const enriched = [];
    let calculatedTotal = 0;
    const stockToDeduct = [];

    for (let i = 0; i < items.length; i++) {
      const item = items[i];
      if (!item || typeof item !== 'object') {
        return res.status(400).json({ success: false, message: `Mục số ${i + 1} không hợp lệ.` });
      }

      const productId = item.productId || item.product;
      if (!productId || !mongoose.isValidObjectId(productId)) {
        return res.status(400).json({
          success: false,
          message: `ID sản phẩm tại mục số ${i + 1} không hợp lệ.`,
        });
      }

      // Strict quantity validation: must be integer >= 1
      const rawQty = item.quantity;
      const qty = Number(rawQty);
      if (!Number.isInteger(qty) || qty <= 0) {
        return res.status(400).json({
          success: false,
          message: `Số lượng sản phẩm tại mục số ${i + 1} phải là số nguyên dương lớn hơn 0.`,
        });
      }

      if (qty > 1000) {
        return res.status(400).json({
          success: false,
          message: `Số lượng tối đa cho mỗi sản phẩm là 1000.`,
        });
      }

      // Fetch product from DB to ensure it exists and get server-authoritative price
      const product = await Product.findById(productId);
      if (!product) {
        return res.status(404).json({
          success: false,
          message: `Sản phẩm không tồn tại hoặc đã bị gỡ bỏ.`,
        });
      }

      if (product.status !== 'active') {
        return res.status(400).json({
          success: false,
          message: `Sản phẩm "${product.name}" hiện không còn mở bán.`,
        });
      }

      if (product.inStock === false) {
        return res.status(400).json({
          success: false,
          message: `Sản phẩm "${product.name}" đã hết hàng.`,
        });
      }

      if (typeof product.stock === 'number' && product.stock > 0 && product.stock < qty) {
        return res.status(400).json({
          success: false,
          message: `Sản phẩm "${product.name}" chỉ còn ${product.stock} món trong kho, không đủ số lượng ${qty}.`,
        });
      }

      // Price snapshot
      const price = Number(product.price);
      calculatedTotal += price * qty;

      enriched.push({
        product: product._id,
        name: product.name,
        price,
        quantity: qty,
        image: product.image || '',
        gameReward: product.gameReward || '',
      });

      if (typeof product.stock === 'number' && product.stock > 0) {
        stockToDeduct.push({ product, qty });
      }
    }

    // Deduct stock safely
    for (const { product, qty } of stockToDeduct) {
      product.stock = Math.max(0, product.stock - qty);
      if (product.stock === 0) {
        product.inStock = false;
      }
      await product.save();
    }

    const orderData = {
      user: req.user._id,
      items: enriched,
      total: calculatedTotal,
      orderStatus: 'pending',
      paymentStatus: 'pending',
      status: 'pending',
      paymentMethod: chosenPaymentMethod,
      minecraftUsername: (minecraftUsername || req.user.minecraftUsername || '').trim(),
      note: (note || '').trim(),
    };

    if (clientKey && typeof clientKey === 'string' && clientKey.trim()) {
      orderData.idempotencyKey = clientKey.trim();
    }

    const order = await Order.create(orderData);
    const populated = await Order.findById(order._id)
      .populate('user', 'username email minecraftUsername')
      .populate('items.product', 'name image price category slug');

    res.status(201).json({
      success: true,
      message: 'Đặt hàng thành công! Vui lòng thanh toán qua QR để hoàn tất.',
      data: populated,
    });
  } catch (error) {
    console.error('createOrder error:', error);
    if (error.name === 'ValidationError') {
      const messages = Object.values(error.errors).map((e) => e.message);
      return res.status(400).json({ success: false, message: messages.join(', ') });
    }
    if (error.code === 11000) {
      return res.status(409).json({ success: false, message: 'Yêu cầu đặt hàng bị trùng lặp.' });
    }
    res.status(500).json({ success: false, message: 'Lỗi server khi tạo đơn hàng.' });
  }
};

// ─── GET /api/orders/my — current user's order history ────────────────────────
const getMyOrders = async (req, res) => {
  try {
    const { status, orderStatus, paymentStatus, page, limit } = req.query;
    const filter = { user: req.user._id };

    if (orderStatus) filter.orderStatus = orderStatus;
    if (paymentStatus) filter.paymentStatus = paymentStatus;
    if (status && !orderStatus && !paymentStatus) {
      filter.$or = [{ status }, { orderStatus: status }, { paymentStatus: status }];
    }

    let query = Order.find(filter)
      .populate('items.product', 'name image price category slug')
      .sort({ createdAt: -1 });

    const usePagination = page !== undefined || limit !== undefined;
    if (usePagination) {
      const pageNum = Math.max(parseInt(page, 10) || 1, 1);
      const limitNum = Math.min(Math.max(parseInt(limit, 10) || 10, 1), 50);
      const skip = (pageNum - 1) * limitNum;

      const [orders, total] = await Promise.all([
        query.skip(skip).limit(limitNum),
        Order.countDocuments(filter),
      ]);

      return res.json({
        success: true,
        count: orders.length,
        total,
        page: pageNum,
        pages: Math.ceil(total / limitNum),
        data: orders,
      });
    }

    const orders = await query;
    res.json({ success: true, count: orders.length, data: orders });
  } catch (error) {
    console.error('getMyOrders error:', error);
    res.status(500).json({ success: false, message: 'Lỗi server khi tải lịch sử đơn hàng.' });
  }
};

// ─── GET /api/orders/:id — single order details (owner or admin) ───────────────
const getOrderById = async (req, res) => {
  try {
    const idParam = req.params.id;
    let query;

    if (mongoose.isValidObjectId(idParam)) {
      query = { _id: idParam };
    } else {
      query = { orderCode: idParam.toUpperCase() };
    }

    const order = await Order.findOne(query)
      .populate('user', 'username email minecraftUsername avatar')
      .populate('items.product', 'name image price category slug');

    if (!order) {
      return res.status(404).json({ success: false, message: 'Không tìm thấy đơn hàng.' });
    }

    const isOwner = order.user && order.user._id.toString() === req.user._id.toString();
    const isAdmin = req.user.role === 'admin';

    if (!isOwner && !isAdmin) {
      return res.status(403).json({
        success: false,
        message: 'Bạn không có quyền xem đơn hàng này.',
      });
    }

    res.json({ success: true, data: order });
  } catch (error) {
    console.error('getOrderById error:', error);
    res.status(500).json({ success: false, message: 'Lỗi server khi xem chi tiết đơn hàng.' });
  }
};

// ─── GET /api/orders — admin: list all orders with filter & pagination ────────
const getAllOrders = async (req, res) => {
  try {
    const { status, orderStatus, paymentStatus, search, page, limit } = req.query;
    const filter = {};

    if (orderStatus) filter.orderStatus = orderStatus;
    if (paymentStatus) filter.paymentStatus = paymentStatus;
    if (status && !orderStatus && !paymentStatus) {
      filter.$or = [{ status }, { orderStatus: status }, { paymentStatus: status }];
    }

    if (search && search.trim()) {
      const term = search.trim();
      const searchConditions = [
        { orderCode: { $regex: term, $options: 'i' } },
        { minecraftUsername: { $regex: term, $options: 'i' } },
      ];
      if (mongoose.isValidObjectId(term)) {
        searchConditions.push({ _id: term });
      }
      if (filter.$or) {
        filter.$and = [{ $or: filter.$or }, { $or: searchConditions }];
        delete filter.$or;
      } else {
        filter.$or = searchConditions;
      }
    }

    let query = Order.find(filter)
      .populate('user', 'username email minecraftUsername')
      .populate('items.product', 'name image price category slug')
      .sort({ createdAt: -1 });

    const usePagination = page !== undefined || limit !== undefined;
    if (usePagination) {
      const pageNum = Math.max(parseInt(page, 10) || 1, 1);
      const limitNum = Math.min(Math.max(parseInt(limit, 10) || 10, 1), 100);
      const skip = (pageNum - 1) * limitNum;

      const [orders, total] = await Promise.all([
        query.skip(skip).limit(limitNum),
        Order.countDocuments(filter),
      ]);

      return res.json({
        success: true,
        count: orders.length,
        total,
        page: pageNum,
        pages: Math.ceil(total / limitNum),
        data: orders,
      });
    }

    const orders = await query;
    res.json({ success: true, count: orders.length, data: orders });
  } catch (error) {
    console.error('getAllOrders error:', error);
    res.status(500).json({ success: false, message: 'Lỗi server khi tải danh sách đơn hàng.' });
  }
};

// ─── PUT /api/orders/:id/status — admin updates order/payment status ───────────
const updateOrderStatus = async (req, res) => {
  try {
    if (!mongoose.isValidObjectId(req.params.id)) {
      return res.status(400).json({ success: false, message: 'ID đơn hàng không hợp lệ.' });
    }

    const order = await Order.findById(req.params.id);
    if (!order) {
      return res.status(404).json({ success: false, message: 'Không tìm thấy đơn hàng.' });
    }

    let { orderStatus, paymentStatus, status, note, transactionId } = req.body;

    // Handle legacy single-status field input
    if (status && !orderStatus && !paymentStatus) {
      if (status === 'paid') {
        paymentStatus = 'paid';
        if (order.orderStatus === 'pending') orderStatus = 'processing';
      } else if (status === 'delivered') {
        orderStatus = 'delivered';
      } else if (status === 'cancelled') {
        orderStatus = 'cancelled';
        if (order.paymentStatus === 'pending') paymentStatus = 'failed';
      } else if (status === 'pending') {
        orderStatus = 'pending';
        paymentStatus = 'pending';
      }
    }

    // Validate orderStatus transitions if changing
    if (orderStatus && orderStatus !== order.orderStatus) {
      const allowed = ORDER_TRANSITIONS[order.orderStatus] || [];
      if (!allowed.includes(orderStatus)) {
        return res.status(400).json({
          success: false,
          message: `Không thể chuyển trạng thái đơn hàng từ "${order.orderStatus}" sang "${orderStatus}".`,
        });
      }
    }

    // Validate paymentStatus transitions if changing
    if (paymentStatus && paymentStatus !== order.paymentStatus) {
      const allowed = PAYMENT_TRANSITIONS[order.paymentStatus] || [];
      if (!allowed.includes(paymentStatus)) {
        return res.status(400).json({
          success: false,
          message: `Không thể chuyển trạng thái thanh toán từ "${order.paymentStatus}" sang "${paymentStatus}".`,
        });
      }
    }

    // Invariant: Cancelled order cannot be set to paid
    const targetOrderStatus = orderStatus || order.orderStatus;
    const targetPaymentStatus = paymentStatus || order.paymentStatus;
    if (targetOrderStatus === 'cancelled' && targetPaymentStatus === 'paid') {
      return res.status(400).json({
        success: false,
        message: 'Đơn hàng đã huỷ không thể đặt trạng thái thanh toán thành đã thanh toán.',
      });
    }

    // Apply changes
    if (orderStatus && orderStatus !== order.orderStatus) {
      const prev = order.orderStatus;
      order.orderStatus = orderStatus;

      // Handle stock restoration if cancelled
      if (orderStatus === 'cancelled' && prev !== 'cancelled') {
        order.cancelledAt = new Date();
        order.cancelledBy = req.user._id;
        order.cancellationReason = note || 'Admin huỷ đơn hàng';

        for (const item of order.items) {
          if (item.product) {
            await Product.findByIdAndUpdate(item.product, {
              $inc: { stock: item.quantity },
              $set: { inStock: true },
            });
          }
        }
      }
    }

    if (paymentStatus && paymentStatus !== order.paymentStatus) {
      order.paymentStatus = paymentStatus;
      if (!order.paymentDetails) order.paymentDetails = {};

      if (paymentStatus === 'paid') {
        order.paymentDetails.paidAt = new Date();
        order.paymentDetails.verifiedBy = req.user._id;
        if (transactionId) order.paymentDetails.transactionId = transactionId;
      }
    }

    if (note && typeof note === 'string') {
      order.paymentDetails.note = note.trim();
    }

    await order.save();

    const updated = await Order.findById(order._id)
      .populate('user', 'username email minecraftUsername')
      .populate('items.product', 'name image price category slug');

    res.json({
      success: true,
      message: `Đã cập nhật trạng thái đơn hàng: ${updated.orderStatus} / ${updated.paymentStatus}`,
      data: updated,
    });
  } catch (error) {
    console.error('updateOrderStatus error:', error);
    res.status(500).json({ success: false, message: 'Lỗi server khi cập nhật trạng thái đơn hàng.' });
  }
};

// ─── PUT /api/orders/:id/cancel — cancel an order (owner or admin) ────────────
const cancelMyOrder = async (req, res) => {
  try {
    if (!mongoose.isValidObjectId(req.params.id)) {
      return res.status(400).json({ success: false, message: 'ID đơn hàng không hợp lệ.' });
    }

    const order = await Order.findById(req.params.id);
    if (!order) {
      return res.status(404).json({ success: false, message: 'Không tìm thấy đơn hàng.' });
    }

    const isOwner = order.user.toString() === req.user._id.toString();
    const isAdmin = req.user.role === 'admin';

    if (!isOwner && !isAdmin) {
      return res.status(403).json({ success: false, message: 'Bạn không có quyền huỷ đơn hàng này.' });
    }

    if (order.orderStatus === 'cancelled') {
      return res.status(400).json({ success: false, message: 'Đơn hàng này đã được huỷ trước đó.' });
    }

    if (order.orderStatus === 'delivered') {
      return res.status(400).json({ success: false, message: 'Không thể huỷ đơn hàng đã hoàn tất giao hàng.' });
    }

    // Non-admin can only cancel pending un-paid orders
    if (!isAdmin) {
      if (order.orderStatus !== 'pending' || order.paymentStatus === 'paid') {
        return res.status(400).json({
          success: false,
          message: 'Đơn hàng đang xử lý hoặc đã thanh toán. Vui lòng liên hệ Admin để được hỗ trợ.',
        });
      }
    }

    order.orderStatus = 'cancelled';
    if (order.paymentStatus === 'pending') {
      order.paymentStatus = 'failed';
    } else if (order.paymentStatus === 'paid' && isAdmin) {
      order.paymentStatus = 'refunded';
    }

    order.cancelledAt = new Date();
    order.cancelledBy = req.user._id;
    order.cancellationReason = req.body.reason ? req.body.reason.trim() : (isAdmin ? 'Admin huỷ đơn' : 'Khách hàng huỷ đơn');

    // Restore stock
    for (const item of order.items) {
      if (item.product) {
        await Product.findByIdAndUpdate(item.product, {
          $inc: { stock: item.quantity },
          $set: { inStock: true },
        });
      }
    }

    await order.save();

    res.json({
      success: true,
      message: 'Đơn hàng đã được huỷ thành công.',
      data: order,
    });
  } catch (error) {
    console.error('cancelMyOrder error:', error);
    res.status(500).json({ success: false, message: 'Lỗi server khi huỷ đơn hàng.' });
  }
};

// ─── PUT /api/orders/:id/payment-proof — submit payment proof screenshot ──────
const submitPaymentProof = async (req, res) => {
  try {
    if (!mongoose.isValidObjectId(req.params.id)) {
      return res.status(400).json({ success: false, message: 'ID đơn hàng không hợp lệ.' });
    }

    const order = await Order.findById(req.params.id);
    if (!order) {
      return res.status(404).json({ success: false, message: 'Không tìm thấy đơn hàng.' });
    }

    const isOwner = order.user.toString() === req.user._id.toString();
    const isAdmin = req.user.role === 'admin';

    if (!isOwner && !isAdmin) {
      return res.status(403).json({ success: false, message: 'Bạn không có quyền cập nhật đơn hàng này.' });
    }

    if (order.orderStatus === 'cancelled') {
      return res.status(400).json({ success: false, message: 'Không thể nộp minh chứng cho đơn hàng đã huỷ.' });
    }

    if (order.paymentStatus === 'paid') {
      return res.status(400).json({ success: false, message: 'Đơn hàng này đã được xác nhận thanh toán.' });
    }

    const { paymentProof, note } = req.body;
    if (!paymentProof || typeof paymentProof !== 'string' || !paymentProof.trim()) {
      return res.status(400).json({ success: false, message: 'Vui lòng cung cấp link hoặc ảnh minh chứng thanh toán.' });
    }

    order.paymentProof = paymentProof.trim();
    order.paymentProofUploadedAt = new Date();
    if (note && typeof note === 'string') {
      order.note = (order.note ? `${order.note} | Minh chứng: ${note.trim()}` : note.trim());
    }

    // Do NOT automatically mark as paid! Remains pending until admin verifies.
    await order.save();

    res.json({
      success: true,
      message: 'Đã gửi minh chứng thanh toán thành công. Admin sẽ kiểm tra và kích hoạt trong vòng 24h.',
      data: order,
    });
  } catch (error) {
    console.error('submitPaymentProof error:', error);
    res.status(500).json({ success: false, message: 'Lỗi server khi gửi minh chứng thanh toán.' });
  }
};

module.exports = {
  createOrder,
  getMyOrders,
  getOrderById,
  getAllOrders,
  updateOrderStatus,
  cancelMyOrder,
  submitPaymentProof,
};
