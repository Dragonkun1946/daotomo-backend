const mongoose = require('mongoose');

const productSchema = new mongoose.Schema(
  {
    name: {
      type: String,
      required: [true, 'Tên sản phẩm là bắt buộc'],
      trim: true,
      maxlength: [100, 'Tên sản phẩm không được vượt quá 100 ký tự'],
    },
    slug: {
      type: String,
      required: [true, 'Slug sản phẩm là bắt buộc'],
      unique: true,
      trim: true,
      lowercase: true,
      match: [/^[a-z0-9]+(?:-[a-z0-9]+)*$/, 'Slug không hợp lệ'],
    },
    description: {
      type: String,
      required: [true, 'Mô tả sản phẩm là bắt buộc'],
      trim: true,
      maxlength: [3000, 'Mô tả không được vượt quá 3000 ký tự'],
    },
    shortDescription: {
      type: String,
      required: [true, 'Mô tả ngắn là bắt buộc'],
      trim: true,
      maxlength: [300, 'Mô tả ngắn không được vượt quá 300 ký tự'],
    },
    price: {
      type: Number,
      required: [true, 'Giá sản phẩm là bắt buộc'],
      min: [0, 'Giá không được âm'],
    },
    originalPrice: {
      type: Number,
      default: null,
      min: [0, 'Giá gốc không được âm'],
    },
    image: {
      type: String,
      required: [true, 'Hình ảnh sản phẩm là bắt buộc'],
      trim: true,
    },
    images: {
      type: [String],
      default: [],
    },
    category: {
      type: String,
      enum: ['crate', 'cosmetic', 'pet', 'bundle', 'vip', 'key'],
      default: 'bundle',
    },
    stock: {
      type: Number,
      default: 0,
      min: [0, 'Số lượng tồn kho không được âm'],
    },
    // Kept for backwards compatibility with the existing Order API.
    inStock: {
      type: Boolean,
      default: true,
    },
    status: {
      type: String,
      enum: ['active', 'inactive'],
      default: 'active',
    },
    featured: {
      type: Boolean,
      default: false,
    },
    // Existing field retained for game-item delivery compatibility.
    gameReward: {
      type: String,
      default: '',
    },
  },
  {
    timestamps: true,
  }
);

productSchema.virtual('discountPercentage').get(function () {
  if (
    this.originalPrice == null ||
    this.originalPrice <= 0 ||
    this.price >= this.originalPrice
  ) {
    return 0;
  }

  return Math.round(
    ((this.originalPrice - this.price) / this.originalPrice) * 100
  );
});

productSchema.set('toJSON', { virtuals: true });
productSchema.set('toObject', { virtuals: true });

module.exports = mongoose.model('Product', productSchema);
