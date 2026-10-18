const express = require('express');
const mongoose = require('mongoose');
const cors = require('cors');
const path = require('path');
require('dotenv').config();
const passport = require('./config/passport');

const authRoutes = require('./routes/auth');
const productRoutes = require('./routes/products');
const orderRoutes = require('./routes/orders');
const checkinRoutes = require('./routes/checkin');

const app = express();

// ─── Middleware ───────────────────────────────────────────────────────────────
app.use(cors({
  origin: process.env.NODE_ENV === 'production'
    ? ['https://daotomo.site', 'https://www.daotomo.site']
    : ['http://localhost:3000', 'http://127.0.0.1:3000', 'http://localhost:5000', 'http://127.0.0.1:5000', 'http://localhost:5500', 'http://127.0.0.1:5500'],
  credentials: true
}));

app.use(express.json());
app.use(express.urlencoded({ extended: true }));
app.use(passport.initialize()); // no app.use(passport.session()) — we're stateless (JWT only)

// Serve static frontend
app.use(express.static(path.join(__dirname, 'public')));

// ─── API Routes (phải để trước fallback) ─────────────────────────────────────
app.use('/api/auth', authRoutes);
app.use('/api/products', productRoutes);
app.use('/api/orders', orderRoutes);
app.use('/api/checkin', checkinRoutes);

app.get('/api/health', (req, res) => {
  res.json({ status: 'OK', message: 'Đảo Tò Mò API is running 🏝️' });
});

// ─── SPA Fallback ─────────────────────────────────────────────────────────────
app.get('*', (req, res) => {
  if (req.url.startsWith('/api/')) {
    return res.status(404).json({ success: false, message: 'API route not found' });
  }
  // If a file with an extension was requested but not found in public/, return 404 instead of HTML
  if (/\.(png|jpe?g|gif|svg|webp|ico|css|js|map|json|woff2?|ttf|eot)$/i.test(req.path)) {
    return res.status(404).send('File not found');
  }
  res.sendFile(path.join(__dirname, 'public', 'index.html'));
});

// ─── Global Error Handler ─────────────────────────────────────────────────────
app.use((err, req, res, next) => {
  console.error(err.stack);
  res.status(err.status || 500).json({
    success: false,
    message: err.message || 'Lỗi server nội bộ',
  });
});

// ─── Connect MongoDB & Start Server ───────────────────────────────────────────
const PORT = process.env.PORT || 5000;
const MONGODB_URI = process.env.MONGODB_URI || 'mongodb://127.0.0.1:27017/daotomo';

if (require.main === module) {
  mongoose.connect(MONGODB_URI, { serverSelectionTimeoutMS: 2500 })
    .then(() => {
      console.log('✅ Kết nối MongoDB thành công');
      app.listen(PORT, () => {
        console.log(`🚀 Server chạy tại http://localhost:${PORT}`);
      });
    })
    .catch(err => {
      console.warn('⚠️ Cảnh báo MongoDB:', err.message || err);
      console.log('💡 Server vẫn khởi động để phục vụ giao diện Web & Static assets.');
      app.listen(PORT, () => {
        console.log(`🚀 Server chạy tại http://localhost:${PORT} (Chế độ xem trước giao diện)`);
      });
    });
}

module.exports = app;
