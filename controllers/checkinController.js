const Checkin = require('../models/Checkin');

// Dates are compared in UTC 'YYYY-MM-DD' form. Good enough for a community
// perk feature; not meant to be precise to the second around midnight.
const todayStr = () => new Date().toISOString().slice(0, 10);

const addDays = (dateStr, delta) => {
  const d = new Date(dateStr + 'T00:00:00.000Z');
  d.setUTCDate(d.getUTCDate() + delta);
  return d.toISOString().slice(0, 10);
};

// Counts the current consecutive streak ending today (or ending yesterday
// if today hasn't been checked in yet, so the streak doesn't zero out the
// moment the clock rolls over before the user has a chance to check in).
const calcStreak = (dateSet, today) => {
  let cursor = dateSet.has(today) ? today : addDays(today, -1);
  let streak = 0;
  while (dateSet.has(cursor)) {
    streak++;
    cursor = addDays(cursor, -1);
  }
  return streak;
};

// ─── POST /api/checkin ────────────────────────────────────────────────────────
const doCheckin = async (req, res) => {
  try {
    const today = todayStr();
    try {
      await Checkin.create({ user: req.user._id, date: today });
    } catch (err) {
      if (err.code === 11000) {
        return res.status(409).json({ success: false, message: 'Bạn đã điểm danh hôm nay rồi. Hẹn gặp lại ngày mai!' });
      }
      throw err;
    }

    const history = await Checkin.find({ user: req.user._id }).select('date -_id');
    const dateSet = new Set(history.map((h) => h.date));
    const streak = calcStreak(dateSet, today);

    res.status(201).json({
      success: true,
      message: `Điểm danh thành công! 🔥 Chuỗi ${streak} ngày liên tiếp.`,
      data: { streak, totalDays: dateSet.size, today },
    });
  } catch (error) {
    console.error('Checkin error:', error);
    res.status(500).json({ success: false, message: 'Lỗi server. Vui lòng thử lại.' });
  }
};

// ─── GET /api/checkin/me ──────────────────────────────────────────────────────
const getMyCheckins = async (req, res) => {
  try {
    const today = todayStr();
    const history = await Checkin.find({ user: req.user._id }).select('date -_id').sort('date');
    const dates = history.map((h) => h.date);
    const dateSet = new Set(dates);

    res.json({
      success: true,
      data: {
        dates,                          // every day the user has ever checked in
        totalDays: dates.length,
        streak: calcStreak(dateSet, today),
        checkedInToday: dateSet.has(today),
        today,
      },
    });
  } catch (error) {
    console.error('Get checkins error:', error);
    res.status(500).json({ success: false, message: 'Lỗi server.' });
  }
};

module.exports = { doCheckin, getMyCheckins };
