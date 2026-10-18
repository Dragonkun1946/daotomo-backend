const jwt = require('jsonwebtoken');
const crypto = require('crypto');
const fs = require('fs');
const path = require('path');
const User = require('../models/User');
const { sendResetPasswordEmail } = require('../utils/sendEmail');

// ─── Helper: generate JWT ─────────────────────────────────────────────────────
const generateToken = (userId) => {
  return jwt.sign({ id: userId }, process.env.JWT_SECRET, {
    expiresIn: process.env.JWT_EXPIRES_IN || '7d',
  });
};

// ─── POST /api/auth/register ──────────────────────────────────────────────────
const register = async (req, res) => {
  try {
    const { username, email, password, minecraftUsername } = req.body;

    // Validate required fields
    if (!username || !email || !password) {
      return res.status(400).json({
        success: false,
        message: 'Vui lòng điền đầy đủ: tên người dùng, email và mật khẩu.',
      });
    }

    // Check if user already exists
    const existingUser = await User.findOne({ $or: [{ email }, { username }] });
    if (existingUser) {
      const field = existingUser.email === email ? 'Email' : 'Tên người dùng';
      return res.status(409).json({
        success: false,
        message: `${field} này đã được sử dụng.`,
      });
    }

    // Create user (password is hashed in the model's pre-save hook)
    const user = await User.create({
      username,
      email,
      password,
      minecraftUsername: minecraftUsername || '',
    });

    const token = generateToken(user._id);

    res.status(201).json({
      success: true,
      message: 'Đăng ký thành công! Chào mừng bạn đến với Đảo Tò Mò 🏝️',
      token,
      user: {
        id: user._id,
        username: user.username,
        email: user.email,
        role: user.role,
        minecraftUsername: user.minecraftUsername,
        skinUrl: user.skinUrl || '',
      },
    });
  } catch (error) {
    // Handle Mongoose validation errors
    if (error.name === 'ValidationError') {
      const messages = Object.values(error.errors).map((e) => e.message);
      return res.status(400).json({ success: false, message: messages.join(', ') });
    }
    console.error('Register error:', error);
    res.status(500).json({ success: false, message: 'Lỗi server. Vui lòng thử lại.' });
  }
};

// ─── POST /api/auth/login ─────────────────────────────────────────────────────
const login = async (req, res) => {
  try {
    const { email, password } = req.body;

    if (!email || !password) {
      return res.status(400).json({
        success: false,
        message: 'Vui lòng nhập email và mật khẩu.',
      });
    }

    // Explicitly select password since it's hidden by default
    const user = await User.findOne({ email }).select('+password');
    if (!user) {
      return res.status(401).json({
        success: false,
        message: 'Email hoặc mật khẩu không đúng.',
      });
    }

    if (!user.password) {
      return res.status(401).json({
        success: false,
        message: 'Tài khoản này đăng nhập bằng Discord/Google. Vui lòng dùng nút đăng nhập tương ứng.',
      });
    }

    const isMatch = await user.comparePassword(password);
    if (!isMatch) {
      return res.status(401).json({
        success: false,
        message: 'Email hoặc mật khẩu không đúng.',
      });
    }

    const token = generateToken(user._id);

    res.json({
      success: true,
      message: `Đăng nhập thành công! Chào mừng trở lại, ${user.username} 👋`,
      token,
      user: {
        id: user._id,
        username: user.username,
        email: user.email,
        role: user.role,
        minecraftUsername: user.minecraftUsername,
        skinUrl: user.skinUrl || '',
      },
    });
  } catch (error) {
    console.error('Login error:', error);
    res.status(500).json({ success: false, message: 'Lỗi server. Vui lòng thử lại.' });
  }
};

// ─── GET /api/auth/me  (protected) ───────────────────────────────────────────
const getMe = async (req, res) => {
  try {
    const user = await User.findById(req.user._id);
    res.json({ success: true, user });
  } catch (error) {
    res.status(500).json({ success: false, message: 'Lỗi server.' });
  }
};

// ─── PUT /api/auth/me  (protected) ───────────────────────────────────────────
const updateMe = async (req, res) => {
  try {
    const allowed = ['username', 'minecraftUsername', 'avatar', 'skinUrl'];
    const updates = {};
    allowed.forEach((field) => {
      if (req.body[field] !== undefined) updates[field] = req.body[field];
    });

    const user = await User.findByIdAndUpdate(req.user._id, updates, {
      new: true,
      runValidators: true,
    });

    res.json({ success: true, message: 'Cập nhật thành công.', user });
  } catch (error) {
    res.status(500).json({ success: false, message: 'Lỗi server.' });
  }
};

// ─── POST /api/auth/skin  (protected) ─────────────────────────────────────────
const updateSkin = async (req, res) => {
  try {
    const { skinData, skinUrl } = req.body;

    // Reset to default (Steve)
    if (skinUrl === '' || skinUrl === '/img/steve.png') {
      const user = await User.findByIdAndUpdate(
        req.user._id,
        { skinUrl: '' },
        { new: true }
      );
      return res.json({
        success: true,
        message: 'Đã khôi phục skin mặc định (Steve).',
        skinUrl: '/img/steve.png',
        user
      });
    }

    // Direct URL provided (e.g. from minotar.net or external)
    if (skinUrl && !skinData) {
      if (typeof skinUrl !== 'string' || skinUrl.length > 500) {
        return res.status(400).json({ success: false, message: 'URL skin không hợp lệ.' });
      }
      const user = await User.findByIdAndUpdate(
        req.user._id,
        { skinUrl },
        { new: true }
      );
      return res.json({
        success: true,
        message: 'Đã lưu skin nhân vật thành công! 🎮',
        skinUrl: user.skinUrl,
        user
      });
    }

    // Base64 file upload
    if (skinData) {
      const matches = skinData.match(/^data:image\/([a-zA-Z]+);base64,(.+)$/);
      const base64Content = matches ? matches[2] : skinData;
      const buffer = Buffer.from(base64Content, 'base64');

      // Max size: 1MB
      if (buffer.length > 1024 * 1024) {
        return res.status(400).json({ success: false, message: 'Kích thước file skin quá lớn (tối đa 1MB).' });
      }

      // Check PNG magic bytes: 89 50 4E 47 0D 0A 1A 0A
      if (buffer.length < 8 || buffer[0] !== 0x89 || buffer[1] !== 0x50 || buffer[2] !== 0x4E || buffer[3] !== 0x47) {
        return res.status(400).json({ success: false, message: 'File phải là định dạng ảnh PNG hợp lệ.' });
      }

      // Verify dimensions (must be 64x64, 64x32, or 128x128 for Minecraft skins)
      if (buffer.length >= 24) {
        const width = buffer.readUInt32BE(16);
        const height = buffer.readUInt32BE(20);
        if (!((width === 64 && height === 64) || (width === 64 && height === 32) || (width === 128 && height === 128))) {
          return res.status(400).json({
            success: false,
            message: `Kích thước skin không đúng chuẩn Minecraft (yêu cầu 64x64 hoặc 64x32, phát hiện ${width}x${height}).`
          });
        }
      }

      // Save to public/uploads/skins/<userId>.png
      const uploadDir = path.join(__dirname, '..', 'public', 'uploads', 'skins');
      if (!fs.existsSync(uploadDir)) {
        fs.mkdirSync(uploadDir, { recursive: true });
      }

      const fileName = `${req.user._id}.png`;
      const filePath = path.join(uploadDir, fileName);
      fs.writeFileSync(filePath, buffer);

      const savedUrl = `/uploads/skins/${fileName}?v=${Date.now()}`;
      const user = await User.findByIdAndUpdate(
        req.user._id,
        { skinUrl: savedUrl },
        { new: true }
      );

      return res.json({
        success: true,
        message: 'Đã lưu skin nhân vật vào tài khoản thành công! 🎮',
        skinUrl: savedUrl,
        user
      });
    }

    return res.status(400).json({ success: false, message: 'Vui lòng cung cấp dữ liệu skin.' });
  } catch (error) {
    console.error('Update skin error:', error);
    res.status(500).json({ success: false, message: 'Lỗi server khi lưu skin.' });
  }
};

// ─── POST /api/auth/forgot-password ──────────────────────────────────────────
const forgotPassword = async (req, res) => {
  try {
    const { email } = req.body;
    if (!email) {
      return res.status(400).json({ success: false, message: 'Vui lòng nhập email.' });
    }

    const user = await User.findOne({ email });
    // Always respond the same way whether or not the email exists — avoids leaking
    // which emails are registered.
    const genericMsg = 'Nếu email tồn tại, một liên kết đặt lại mật khẩu đã được gửi.';

    if (!user) {
      return res.json({ success: true, message: genericMsg });
    }

    const rawToken = crypto.randomBytes(32).toString('hex');
    user.resetPasswordToken = crypto.createHash('sha256').update(rawToken).digest('hex');
    user.resetPasswordExpires = Date.now() + 60 * 60 * 1000; // 1 hour
    await user.save({ validateBeforeSave: false });

    const frontendUrl = process.env.FRONTEND_URL || 'https://www.daotomo.site';
    const resetUrl = `${frontendUrl}/html/Account.html?resetToken=${rawToken}`;

    try {
      await sendResetPasswordEmail(user.email, resetUrl);
    } catch (mailErr) {
      console.error('Send reset email error:', mailErr);
      // Roll back the token so a broken SMTP config doesn't leave a dangling token
      user.resetPasswordToken = undefined;
      user.resetPasswordExpires = undefined;
      await user.save({ validateBeforeSave: false });
      return res.status(500).json({ success: false, message: 'Không thể gửi email. Vui lòng thử lại sau.' });
    }

    res.json({ success: true, message: genericMsg });
  } catch (error) {
    console.error('Forgot password error:', error);
    res.status(500).json({ success: false, message: 'Lỗi server. Vui lòng thử lại.' });
  }
};

// ─── POST /api/auth/reset-password/:token ────────────────────────────────────
const resetPassword = async (req, res) => {
  try {
    const { password } = req.body;
    if (!password || password.length < 6) {
      return res.status(400).json({ success: false, message: 'Mật khẩu phải có ít nhất 6 ký tự.' });
    }

    const hashedToken = crypto.createHash('sha256').update(req.params.token).digest('hex');
    const user = await User.findOne({
      resetPasswordToken: hashedToken,
      resetPasswordExpires: { $gt: Date.now() },
    }).select('+resetPasswordToken +resetPasswordExpires');

    if (!user) {
      return res.status(400).json({ success: false, message: 'Liên kết không hợp lệ hoặc đã hết hạn.' });
    }

    user.password = password; // re-hashed by the pre-save hook
    user.resetPasswordToken = undefined;
    user.resetPasswordExpires = undefined;
    await user.save();

    const token = generateToken(user._id);
    res.json({
      success: true,
      message: 'Đặt lại mật khẩu thành công!',
      token,
      user: { id: user._id, username: user.username, email: user.email, role: user.role, minecraftUsername: user.minecraftUsername },
    });
  } catch (error) {
    console.error('Reset password error:', error);
    res.status(500).json({ success: false, message: 'Lỗi server. Vui lòng thử lại.' });
  }
};

// ─── GET /api/auth/:provider/callback  (Discord / Google) ────────────────────
// Passport has already attached req.user by this point. We issue our own JWT
// and hand off to the frontend via a redirect (avoids exposing the token in
// server logs the way a JSON response might, and needs no CORS handling).
const oauthCallback = (req, res) => {
  const token = generateToken(req.user._id);
  const frontendUrl = process.env.FRONTEND_URL || 'https://www.daotomo.site';
  res.redirect(`${frontendUrl}/html/Account.html?oauthToken=${token}`);
};

module.exports = { register, login, getMe, updateMe, updateSkin, forgotPassword, resetPassword, oauthCallback };
