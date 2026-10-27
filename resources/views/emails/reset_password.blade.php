<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Đặt lại mật khẩu Đảo Tò Mò</title>
  <style>
    body {
      margin: 0;
      padding: 0;
      background-color: #0b0f19;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
      color: #e2e8f0;
      line-height: 1.6;
    }
    .wrapper {
      width: 100%;
      table-layout: fixed;
      background-color: #0b0f19;
      padding: 40px 0;
    }
    .main {
      background-color: #111827;
      margin: 0 auto;
      max-width: 580px;
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
    }
    .header {
      background: linear-gradient(135deg, #1e1b4b 0%, #0f172a 100%);
      padding: 32px 24px;
      text-align: center;
      border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    }
    .brand-title {
      font-size: 26px;
      font-weight: 800;
      color: #38bdf8;
      margin: 0;
      letter-spacing: -0.5px;
    }
    .brand-subtitle {
      font-size: 13px;
      color: #94a3b8;
      margin-top: 6px;
      text-transform: uppercase;
      letter-spacing: 1px;
    }
    .content {
      padding: 36px 32px;
    }
    .greeting {
      font-size: 18px;
      font-weight: 700;
      color: #f8fafc;
      margin-top: 0;
      margin-bottom: 16px;
    }
    p {
      margin: 0 0 16px 0;
      color: #cbd5e1;
      font-size: 15px;
    }
    .btn-wrap {
      text-align: center;
      margin: 32px 0;
    }
    .btn {
      display: inline-block;
      background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
      color: #ffffff !important;
      text-decoration: none;
      font-weight: 700;
      font-size: 15px;
      padding: 14px 32px;
      border-radius: 8px;
      box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
    }
    .warning-box {
      background: rgba(245, 158, 11, 0.1);
      border: 1px solid rgba(245, 158, 11, 0.3);
      border-radius: 8px;
      padding: 14px 18px;
      margin: 24px 0;
      font-size: 13px;
      color: #fbbf24;
    }
    .fallback-link {
      word-break: break-all;
      font-size: 12px;
      color: #64748b;
      margin-top: 24px;
      padding-top: 20px;
      border-top: 1px solid rgba(255, 255, 255, 0.08);
    }
    .fallback-link a {
      color: #38bdf8;
      text-decoration: underline;
    }
    .footer {
      background-color: #0a0f1d;
      padding: 24px;
      text-align: center;
      font-size: 12px;
      color: #64748b;
      border-top: 1px solid rgba(255, 255, 255, 0.05);
    }
  </style>
</head>
<body>
  <div class="wrapper">
    <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0">
      <tr>
        <td align="center">
          <div class="main">
            <!-- Header -->
            <div class="header">
              <div class="brand-title">🏝️ ĐẢO TÒ MÒ</div>
              <div class="brand-subtitle">Máy Chủ Minecraft & Cộng Đồng</div>
            </div>

            <!-- Content -->
            <div class="content">
              <div class="greeting">Xin chào, {{ $username }}! 👋</div>
              <p>Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản Đảo Tò Mò liên kết với địa chỉ email <strong>{{ $email }}</strong>.</p>
              <p>Để tiếp tục tạo mật khẩu mới và bảo vệ tài khoản, bạn vui lòng nhấn vào nút bên dưới:</p>

              <div class="btn-wrap">
                <a href="{{ $resetUrl }}" class="btn" target="_blank">Đặt Lại Mật Khẩu Ngay</a>
              </div>

              <div class="warning-box">
                ⏱️ <strong>Lưu ý:</strong> Liên kết đặt lại mật khẩu chỉ có hiệu lực trong vòng <strong>30 phút</strong>. Nếu bạn không thực hiện yêu cầu này, vui lòng bỏ qua email và mật khẩu của bạn sẽ được giữ an toàn tuyệt đối.
              </div>

              <div class="fallback-link">
                Nếu nút bấm phía trên không hoạt động, hãy sao chép và dán liên kết sau vào trình duyệt:<br>
                <a href="{{ $resetUrl }}" target="_blank">{{ $resetUrl }}</a>
              </div>
            </div>

            <!-- Footer -->
            <div class="footer">
              <p style="margin-bottom: 4px; font-size: 12px; color: #64748b;">© {{ date('Y') }} Đảo Tò Mò Network. All rights reserved.</p>
              <p style="margin: 0; font-size: 11px; color: #475569;">IP Máy Chủ: play.daotomo.net • Website: https://daotomo.site</p>
            </div>
          </div>
        </td>
      </tr>
    </table>
  </div>
</body>
</html>
