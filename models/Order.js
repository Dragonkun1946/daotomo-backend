const mongoose = require('mongoose');

const orderSchema = new mongoose.Schema(
  {
    user: {
      type: mongoose.Schema.Types.ObjectId,
      ref: 'User',
      required: true,
    },
    items: [
      {
        product: {
          type: mongoose.Schema.Types.ObjectId,
          ref: 'Product',
          required: true,
        },
        name: String,      // snapshot at time of order
        price: Number,     // snapshot at time of order
        quantity: {
          type: Number,
          default: 1,
          min: 1,
        },
      },
    ],
    total: {
      type: Number,
      required: true,
      min: 0,
    },
    status: {
      type: String,
      enum: ['pending', 'paid', 'delivered', 'cancelled'],
      default: 'pending',
    },
    // Payment info
    paymentMethod: {
      type: String,
      enum: ['qr', 'manual'],
      default: 'qr',
    },
    paymentProof: {
      type: String, // URL or filename of payment screenshot
      default: '',
    },
    // Minecraft username at time of order (for game item delivery)
    minecraftUsername: {
      type: String,
      default: '',
    },
    note: {
      type: String,
      default: '',
    },
  },
  {
    timestamps: true,
  }
);

module.exports = mongoose.model('Order', orderSchema);
