const express = require('express');
const router = express.Router();
const passport = require('../config/passport');
const {
  register, login, getMe, updateMe,
  forgotPassword, resetPassword, oauthCallback,
} = require('../controllers/authController');
const { protect } = require('../middleware/auth');

router.post('/register', register);
router.post('/login', login);
router.get('/me', protect, getMe);
router.put('/me', protect, updateMe);

// ── Password reset ──
router.post('/forgot-password', forgotPassword);
router.post('/reset-password/:token', resetPassword);

// ── OAuth: Discord ──
router.get('/discord', passport.authenticate('discord', { session: false }));
router.get('/discord/callback',
  passport.authenticate('discord', { session: false, failureRedirect: '/html/Account.html?oauthError=1' }),
  oauthCallback
);

// ── OAuth: Google ──
router.get('/google', passport.authenticate('google', { session: false }));
router.get('/google/callback',
  passport.authenticate('google', { session: false, failureRedirect: '/html/Account.html?oauthError=1' }),
  oauthCallback
);

module.exports = router;
