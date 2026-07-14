const nodemailer = require('nodemailer');

// Requires these env vars (see .env.example):
//   SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS, SMTP_FROM
let transporter;
function getTransporter() {
  if (!transporter) {
    transporter = nodemailer.createTransport({
      host: process.env.SMTP_HOST,
      port: Number(process.env.SMTP_PORT || 587),
      secure: Number(process.env.SMTP_PORT) === 465,
      auth: { user: process.env.SMTP_USER, pass: process.env.SMTP_PASS },
    });
  }
  return transporter;
}

/**
 * Send the "reset your password" email.
 * @param {string} to        recipient email
 * @param {string} resetUrl  full frontend URL the user clicks (contains the raw token)
 */
async function sendResetPasswordEmail(to, resetUrl) {
  await getTransporter().sendMail({
    from: process.env.SMTP_FROM || 'Đảo Tò Mò <no-reply@daotomo.site>',
    to,
    subject: 'Đặt lại mật khẩu — Đảo Tò Mò',
    html: `
      <div style="font-family:sans-serif;max-width:480px;margin:0 auto;padding:24px;background:#111827;color:#e2e8f0;border-radius:16px">
        <h2 style="color:#fff;">Đặt lại mật khẩu</h2>
        <p>Bạn (hoặc ai đó) vừa yêu cầu đặt lại mật khẩu cho tài khoản Đảo Tò Mò của bạn.</p>
        <p><a href="${resetUrl}" style="display:inline-block;padding:12px 24px;background:#6c63ff;color:#fff;border-radius:12px;text-decoration:none;font-weight:700;">Đặt lại mật khẩu</a></p>
        <p style="font-size:13px;color:#94a3b8;">Liên kết có hiệu lực trong 1 giờ. Nếu bạn không yêu cầu điều này, hãy bỏ qua email này.</p>
      </div>`,
  });
}

module.exports = { sendResetPasswordEmail };
