const express = require('express');
const router = express.Router();
const {
  createOrder,
  getMyOrders,
  getOrderById,
  updateOrderStatus,
  getAllOrders,
  cancelMyOrder,
  submitPaymentProof,
} = require('../controllers/orderController');
const { protect, adminOnly } = require('../middleware/auth');

// All order routes require login
router.use(protect);

// User order operations
router.post('/', createOrder);
router.get('/my', getMyOrders);

// Admin operations
router.get('/', adminOnly, getAllOrders);
router.put('/:id/status', adminOnly, updateOrderStatus);

// Order actions (ownership or admin verified in controller)
router.put('/:id/cancel', cancelMyOrder);
router.put('/:id/payment-proof', submitPaymentProof);

// Order detail by ID or orderCode
router.get('/:id', getOrderById);

module.exports = router;
