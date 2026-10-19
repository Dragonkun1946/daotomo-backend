# Đảo Tò Mò — Minecraft Server Backend (Laravel 11)

Hệ thống Backend API & Webhook Cổng thanh toán tự động cho Máy chủ Minecraft **Đảo Tò Mò** ([daotomo.site](https://www.daotomo.site)).

---

## 1. Tổng quan kiến trúc & Công nghệ

- **Framework:** PHP 8.2+ / Laravel 11 (kiến trúc RESTful API, Database Transactions, Strict State Machine)
- **Database:** SQLite (Local & Testing) / PostgreSQL (Render Production)
- **Authentication:** Laravel Sanctum (Bearer Token) & Discord OAuth 2.0
- **Thanh toán:** Tự động hóa qua cổng Webhook SePay (VietinBank / VietQR)
- **Container / Deployment:** Docker (`richarvey/nginx-php-fpm`) triển khai tự động trên [Render](https://render.com)
- **Frontend Tương thích:** HTML5, CSS3, Vanilla JS, Three.js / skinview3d

---

## 2. Tích hợp Cổng thanh toán SePay (Tuần 9)

Hệ thống tích hợp cổng thanh toán ngân hàng tự động **SePay** theo tài liệu chính thức: [https://docs.sepay.vn/tich-hop-webhooks.html](https://docs.sepay.vn/tich-hop-webhooks.html).

### 2.1. Luồng hoạt động (Payment Flow)
1. **Khách đặt hàng:** Khách tạo đơn hàng qua `POST /api/orders`. Backend sinh mã đơn duy nhất `DTM-XXXXXX` (với `transferContent = DTM XXXXXX`), kèm mã QR động (VietQR / SePay QR).
2. **Khách quét mã chuyển khoản:** Ứng dụng ngân hàng tự động điền đúng số tiền và nội dung chuyển khoản `DTM XXXXXX`.
3. **SePay gửi Webhook:** Ngay khi nhận được biến động số dư, SePay gửi request `POST /api/webhooks/sepay` tới máy chủ.
4. **Backend xác thực & đối chiếu:**
   - Xác thực chữ ký bí mật hoặc API Key trong header.
   - Kiểm tra loại giao dịch: chỉ xử lý tiền vào (`transferType = 'in'`).
   - Khóa bản ghi đơn hàng (`lockForUpdate()`) trong một Database Transaction.
   - Đối chiếu số tiền chuyển khoản với tổng tiền đơn hàng.
   - Ghi nhận `payment_transactions` với ràng buộc `UNIQUE(sepay_transaction_id)` để chống trùng lặp tuyệt đối.
   - Chuyển trạng thái đơn hàng sang `paid` (Đã thanh toán) và lưu nhật ký trạng thái (`status_history`).
5. **Phản hồi SePay:**
   - Thành công: Trả về HTTP `200 OK` `{ "success": true, "message": "Xác nhận thanh toán SePay thành công" }`.
   - Giao dịch gửi lại (Idempotent): Trả về HTTP `200 OK` `{ "success": true, "message": "Giao dịch đã được xử lý trước đó (Idempotent)" }` để SePay ngừng retry.
   - Sai chữ ký / API key: Trả về HTTP `401 Unauthorized`.
   - Sai số tiền / đơn đã hủy: Trả về HTTP `400 Bad Request`.

### 2.2. Webhook Endpoint
- **URL Production:** `https://daotomo-backend.onrender.com/api/webhooks/sepay`
- **Method:** `POST`
- **Content-Type:** `application/json`

### 2.3. Cấu hình bảo mật Webhook (Authentication)
Hệ thống hỗ trợ 2 phương thức xác thực chuẩn SePay:
1. **HMAC-SHA256 Signature (Khuyến nghị SePay):**
   - Header: `X-Sepay-Signature: sha256=<HMAC_HEX>` (hoặc raw hex `<HMAC_HEX>`)
   - Header: `X-Sepay-Timestamp: <UNIX_TIMESTAMP>`
   - Secret key: `SEPAY_WEBHOOK_SECRET`
   - Thuật toán: `HMAC-SHA256("{timestamp}.{raw_body}", secret)`
   - Cơ chế bảo vệ: Tự động kiểm tra độ lệch thời gian <= 5 phút chống Replay Attack và dùng `hash_equals` chống Timing Attack.
2. **API Key qua Header:**
   - `Authorization: Apikey <SEPAY_WEBHOOK_API_KEY>` (hoặc `Bearer <SEPAY_WEBHOOK_API_KEY>`)
   - hoặc `X-Api-Key: <SEPAY_WEBHOOK_API_KEY>`

---

## 3. Biến môi trường bắt buộc (Environment Variables)

Các biến môi trường cần khai báo trên Render Dashboard (phần Environment):

| Tên biến | Mô tả | Môi trường |
| :--- | :--- | :--- |
| `APP_NAME` | Tên ứng dụng (`DaoTomo`) | Tất cả |
| `APP_ENV` | `production` trên Render, `local` trên máy dev | Tất cả |
| `APP_KEY` | Khóa mã hóa Laravel (`base64:...`) | Tất cả (Bắt buộc) |
| `APP_DEBUG` | `false` trên production | Production |
| `APP_URL` | URL domain production (`https://daotomo-backend.onrender.com`) | Tất cả |
| `DB_CONNECTION` | `pgsql` trên Render hoặc `sqlite` local | Tất cả |
| `DATABASE_URL` | Chuỗi kết nối PostgreSQL tự động cấp bởi Render | Render |
| `CORS_ALLOWED_ORIGINS` | Danh sách domain frontend được phép gọi API (cách nhau bằng dấu phẩy) | Tất cả |
| `DISCORD_CLIENT_ID` | Client ID ứng dụng Discord Developer Portal | Tất cả |
| `DISCORD_CLIENT_SECRET` | Client Secret ứng dụng Discord | Tất cả |
| `DISCORD_CALLBACK_URL` | URL callback OAuth Discord (`.../api/auth/discord/callback`) | Tất cả |
| `SEPAY_WEBHOOK_API_KEY` | API Key cấu hình trên SePay Dashboard Webhook | Webhook |
| `SEPAY_WEBHOOK_SECRET` | Khóa bí mật tạo chữ ký HMAC-SHA256 của Webhook (Tùy chọn) | Webhook |
| `SEPAY_ACCOUNT_NUMBER` | Số tài khoản ngân hàng nhận tiền (`103870631688`) | Webhook / QR |
| `SEPAY_BANK_NAME` | Tên ngân hàng (`VietinBank`) | Webhook / QR |
| `SEPAY_BANK_BIN` | Mã BIN ngân hàng (`970415`) | Webhook / QR |
| `SEPAY_ACCOUNT_NAME` | Tên chủ tài khoản (`DAO TO MO`) | Webhook / QR |

---

## 4. Hướng dẫn cài đặt & Kiểm thử Local

### Cài đặt dependencies:
```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate
```

### Chạy kiểm thử tự động (Automated Tests):
```bash
# Chạy toàn bộ 43 tests kiểm thử:
php artisan test

# Chỉ chạy bộ test SePay Webhook:
php artisan test --filter=SepayWebhookTest
```

---

## 5. Quy trình Migration an toàn trên Production

1. **Tuyệt đối KHÔNG dùng `migrate:fresh` hoặc `migrate:reset`** trên môi trường Production vì sẽ phá hủy toàn bộ dữ liệu đơn hàng và người dùng.
2. Script khởi động container (`scripts/00-laravel-deploy.sh`) sử dụng:
   ```bash
   php artisan migrate --force
   ```
   Lệnh này chỉ áp dụng các migration mới chưa chạy (bổ sung bảng `payment_transactions`, thêm cột mới), hoàn toàn bảo toàn dữ liệu hiện có.
