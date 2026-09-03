const mongoose = require('mongoose');

const checkinSchema = new mongoose.Schema(
  {
    user: {
      type: mongoose.Schema.Types.ObjectId,
      ref: 'User',
      required: true,
    },
    // Stored as 'YYYY-MM-DD' (UTC) rather than a Date — makes the
    // "one per day" uniqueness check and calendar rendering trivial.
    date: {
      type: String,
      required: true,
    },
  },
  { timestamps: true }
);

// The actual guarantee that a user can only check in once per day —
// enforced by MongoDB itself, not just application logic.
checkinSchema.index({ user: 1, date: 1 }, { unique: true });

module.exports = mongoose.model('Checkin', checkinSchema);
