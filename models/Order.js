const mongoose = require('mongoose');

const orderSchema = new mongoose.Schema(
  {
    orderCode: {
      type: String,
      unique: true,
      index: true,
      trim: true,
      uppercase: true,
    },
    user: {
      type: mongoose.Schema.Types.ObjectId,
      ref: 'User',
      required: [true, 'Đơn hàng phải thuộc về một người dùng'],
      index: true,
    },
    items: [
      {
        product: {
          type: mongoose.Schema.Types.ObjectId,
          ref: 'Product',
          required: true,
        },
        name: {
          type: String,
          required: true,
          trim: true,
        },
        price: {
          type: Number,
          required: true,
          min: [0, 'Giá sản phẩm không được âm'],
        },
        quantity: {
          type: Number,
          required: true,
          min: [1, 'Số lượng tối thiểu là 1'],
          default: 1,
        },
        image: {
          type: String,
          default: '',
        },
        gameReward: {
          type: String,
          default: '',
        },
      },
    ],
    total: {
      type: Number,
      required: true,
      min: [0, 'Tổng tiền không được âm'],
    },
    // Distinct order lifecycle status
    orderStatus: {
      type: String,
      enum: {
        values: ['pending', 'processing', 'delivered', 'cancelled'],
        message: '{VALUE} không phải là trạng thái đơn hàng hợp lệ',
      },
      default: 'pending',
      index: true,
    },
    // Distinct payment transaction status
    paymentStatus: {
      type: String,
      enum: {
        values: ['pending', 'paid', 'failed', 'refunded'],
        message: '{VALUE} không phải là trạng thái thanh toán hợp lệ',
      },
      default: 'pending',
      index: true,
    },
    // Backward compatibility with legacy code/tests expecting `status`
    status: {
      type: String,
      enum: ['pending', 'paid', 'delivered', 'cancelled'],
      default: 'pending',
      index: true,
    },
    // Payment info
    paymentMethod: {
      type: String,
      enum: {
        values: ['qr', 'manual'],
        message: '{VALUE} không phải là phương thức thanh toán hợp lệ',
      },
      default: 'qr',
    },
    paymentProof: {
      type: String, // URL, path or note of payment proof
      default: '',
      trim: true,
    },
    paymentProofUploadedAt: {
      type: Date,
      default: null,
    },
    paymentDetails: {
      transactionId: { type: String, default: '', trim: true },
      transferContent: { type: String, default: '', trim: true },
      paidAt: { type: Date, default: null },
      verifiedBy: { type: mongoose.Schema.Types.ObjectId, ref: 'User', default: null },
      note: { type: String, default: '', trim: true },
    },
    // Idempotency key to prevent accidental duplicate submission
    idempotencyKey: {
      type: String,
      sparse: true,
      index: true,
      trim: true,
    },
    // Minecraft username at time of order (for game item delivery)
    minecraftUsername: {
      type: String,
      trim: true,
      default: '',
    },
    note: {
      type: String,
      trim: true,
      default: '',
    },
    cancellationReason: {
      type: String,
      default: '',
      trim: true,
    },
    cancelledAt: {
      type: Date,
      default: null,
    },
    cancelledBy: {
      type: mongoose.Schema.Types.ObjectId,
      ref: 'User',
      default: null,
    },
  },
  {
    timestamps: true,
  }
);

// Helper to generate a unique readable order code
function generateOrderCode() {
  const datePart = new Date().toISOString().slice(2, 10).replace(/-/g, '');
  const randomPart = Math.random().toString(36).substring(2, 7).toUpperCase();
  return `DTM${datePart}${randomPart}`;
}

// Method to ensure unique orderCode and sync legacy `status`
orderSchema.methods.syncStatuses = function () {
  if (!this.orderCode) {
    this.orderCode = generateOrderCode();
  }

  if (!this.paymentDetails) {
    this.paymentDetails = {};
  }
  if (!this.paymentDetails.transferContent) {
    this.paymentDetails.transferContent = this.orderCode;
  }

  // Synchronize legacy `status` with orderStatus & paymentStatus
  if (this.orderStatus === 'cancelled') {
    this.status = 'cancelled';
  } else if (this.orderStatus === 'delivered') {
    this.status = 'delivered';
  } else if (this.paymentStatus === 'paid') {
    this.status = 'paid';
  } else {
    this.status = 'pending';
  }

  return this;
};

// Pre-save hook: ensure unique orderCode and sync legacy `status`
orderSchema.pre('save', function (next) {
  this.syncStatuses();
  next();
});

module.exports = mongoose.model('Order', orderSchema);
