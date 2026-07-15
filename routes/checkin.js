const express = require('express');
const router = express.Router();
const { protect } = require('../middleware/auth');
const { doCheckin, getMyCheckins } = require('../controllers/checkinController');

router.post('/', protect, doCheckin);
router.get('/me', protect, getMyCheckins);

module.exports = router;
