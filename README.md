# 🏝️ Đảo Tò Mò — Minecraft Community & E-commerce Platform

[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-blue.svg)](https://php.net)
[![Laravel Framework](https://img.shields.io/badge/Laravel-11.31-red.svg)](https://laravel.com)
[![Tests Status](https://img.shields.io/badge/Tests-71%20Passed%20(243%20assertions)-brightgreen.svg)](#kiểm-thử-tests)
[![Architecture](https://img.shields.io/badge/Architecture-Domain--Oriented%20Clean-success.svg)](docs/ARCHITECTURE.md)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

Website cộng đồng và cổng nạp / cửa hàng vật phẩm trực tuyến dành cho người chơi Minecraft **Đảo Tò Mò** (`daotomo.site`).

---

## 📑 Mục Lục
- [1. Giới Thiệu & Tính Năng](#1-giới-thiệu--tính-năng)
- [2. Kiến Trúc Source Code](#2-kiến-trúc-source-code)
- [3. Yêu Cầu Môi Trường](#3-yêu-cầu-môi-trường)
- [4. Cài Đặt & Chạy Local](#4-cài-đặt--chạy-local)
- [5. Kiểm Thử (Tests)](#5-kiểm-thử-tests)
- [6. Cấu Hình Môi Trường (Environment Variables)](#6-cấu-hình-môi-trường-environment-variables)
- [7. Triển Khai Lên Render (Production)](#7-triển-khai-lên-render-production)

---

## 1. Giới Thiệu & Tính Năng

* **Cửa Hàng Minecraft (Store):**
  * Danh mục vật phẩm, gói VIP, Rank, Skin và Xu Đảo.
  * Tính toán giá tiền trực tiếp từ cơ sở dữ liệu để chống gian lận giá từ client.
  * Hỗ trợ thanh toán cho cả thành viên đăng nhập lẫn khách vãng lai (Guest Checkout).
* **Cổng Thanh Toán Tự Động SePay:**
  * Sinh mã chuyển khoản chuẩn ngân hàng `DTM XXXXXX`.
  * Tự động sinh mã VietQR và SePay QR kèm nội dung chính xác.
  * Webhook xác thực bảo mật đa tầng (API Key + chữ ký HMAC-SHA256 với cơ chế chống Replay Attack).
  * Tự động hoàn thành đơn hàng và cộng quyền lợi ngay khi nhận được tiền.
* **Xác Thực & Quản Lý Tài Khoản (Authentication & Profile):**
  * Đăng ký, đăng nhập bảo mật qua Laravel Sanctum.
  * Đăng nhập 1-click bằng **Discord OAuth2**.
  * Quên mật khẩu & đặt lại mật khẩu với cơ chế gửi email đa tầng (Resend HTTPS API / Brevo / SMTP).
  * Hồ sơ cá nhân: Đổi tên hiển thị, cập nhật Minecraft IGN, tiểu sử bio, huy hiệu Discord.
* **Hệ Thống Minecraft Skin 3D:**
  * Tải lên file skin Minecraft PNG (chuẩn 64x64, 64x32, 128x128).
  * Tự động bóc tách khuôn mặt và lớp mũ (outer helmet layer) bằng PHP GD để làm ảnh đại diện avatar tài khoản.
  * Tích hợp bộ xem trước 3D tương tác (SkinView3D).
* **Điểm Danh Nhận Quà (Daily Checkin):**
  * Điểm danh mỗi ngày tính theo streak liên tục để nhận thưởng Xu Đảo.
* **Trang Quản Trị Viên (Admin Panel):**
  * Quản lý sản phẩm: Thêm, sửa, xóa, gắn cờ nổi bật, tồn kho.
  * Quản lý đơn hàng: State Machine chuyển trạng thái đơn (chờ duyệt, đã thanh toán, đã giao, hủy đơn) với luật bảo vệ bất biến.

---

## 2. Kiến Trúc Source Code

Dự án được tái cấu trúc theo mô hình **Domain-Oriented Clean Architecture** trên nền tảng **Laravel 11**:

* Chi tiết cấu trúc xem tại: **[Tài Liệu Kiến Trúc (docs/ARCHITECTURE.md)](docs/ARCHITECTURE.md)**

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Api/
│   │   │   ├── Auth/           # AuthController, PasswordResetController, DiscordOAuthController
│   │   │   ├── User/           # ProfileController, SkinController
│   │   │   ├── Order/          # OrderController
│   │   │   ├── Product/        # ProductController
│   │   │   ├── Checkin/        # CheckinController
│   │   │   └── Payment/        # SepayWebhookController
│   │   └── Admin/              # AdminOrderController, AdminProductController
│   ├── Requests/               # BaseApiRequest & FormRequests chuẩn hóa validation lỗi 422
│   └── Resources/              # UserResource, OrderResource (chuẩn hóa JSON serialization)
└── Services/
    ├── Auth/                   # DiscordOAuthService, PasswordResetService
    ├── User/                   # SkinService
    ├── Order/                  # OrderService
    ├── Checkin/                # CheckinService
    └── SepayService.php        # SepayService
```

---

## 3. Yêu Cầu Môi Trường

* **PHP:** >= 8.2 (kèm các extension: `pdo_sqlite`, `curl`, `mbstring`, `fileinfo`, `gd`, `openssl`)
* **Composer:** >= 2.x
* **Cơ sở dữ liệu:** SQLite (mặc định môi trường dev/test) hoặc PostgreSQL (production)

---

## 4. Cài Đặt & Chạy Local

1. **Clone repository:**
   ```bash
   git clone https://github.com/Dragonkun1946/daotomo-backend.git
   cd daotomo-backend
   ```

2. **Cài đặt dependencies:**
   ```bash
   composer install
   ```

3. **Thiết lập file môi trường:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Tạo database và chạy migration kèm seed dữ liệu mẫu:**
   ```bash
   touch database/database.sqlite
   php artisan migrate --seed
   ```

5. **Khởi chạy máy chủ phát triển:**
   ```bash
   php artisan serve --port=8000
   ```
   Mở trình duyệt truy cập: `http://localhost:8000/index.html` hoặc kiểm tra health check: `http://localhost:8000/api/health`.

---

## 5. Kiểm Thử (Tests)

Hệ thống có bộ kiểm thử tự động toàn diện bao gồm cả **Feature Tests** và **Unit Tests**:

```bash
php artisan test
```

**Kết quả kiểm thử thực tế:**
```
PASS  Tests\Unit\ExampleTest (1 test)
PASS  Tests\Unit\OrderServiceTest (3 tests)
PASS  Tests\Unit\PasswordResetServiceTest (3 tests)
PASS  Tests\Unit\SkinServiceTest (4 tests)
PASS  Tests\Feature\ApiTest (42 tests)
PASS  Tests\Feature\ExampleTest (1 test)
PASS  Tests\Feature\SepayWebhookTest (17 tests)

Tests:    71 passed (243 assertions)
Duration: ~2.6s
```

---

## 6. Cấu Hình Môi Trường (Environment Variables)

Các biến môi trường cốt lõi cần thiết trên máy chủ production:

| Biến Môi Trường | Mô Tả | Ví Dụ |
|---|---|---|
| `APP_ENV` | Môi trường chạy | `production` |
| `APP_DEBUG` | Chế độ debug | `false` |
| `FRONTEND_URL` | Domain chính của frontend | `https://daotomo.site` |
| `SEPAY_WEBHOOK_API_KEY` | Khóa API Webhook SePay | *(Lấy từ SePay Dashboard)* |
| `SEPAY_ACCOUNT_NUMBER` | Số tài khoản nhận tiền | `0977364711` |
| `SEPAY_BANK_NAME` | Tên ngân hàng nhận tiền | `MBBank` |
| `DISCORD_CLIENT_ID` | Client ID ứng dụng Discord | *(Từ Discord Developer Portal)* |
| `DISCORD_CLIENT_SECRET`| Client Secret ứng dụng Discord | *(Từ Discord Developer Portal)* |
| `DISCORD_CALLBACK_URL` | URL callback OAuth | `https://daotomo-backend.onrender.com/api/auth/discord/callback` |
| `RESEND_API_KEY` | API Key gửi mail qua HTTPS (Port 443) | `re_xxxxxxxxxxxx` |
| `RESEND_FROM_EMAIL` | Email gửi thư | `onboarding@resend.dev` hoặc domain verified |

---

## 7. Triển Khai Lên Render (Production)

Dự án đã tích hợp sẵn file cấu hình Docker `Dockerfile`, script khởi động `scripts/00-laravel-deploy.sh` và blueprint `render.yaml`:

1. Đẩy code lên branch `main` của GitHub.
2. Render sẽ tự động build image Docker và chạy migration thông qua script deploy.
3. Thêm các biến môi trường tại tab **Environment** trên Render Dashboard.
4. Kiểm tra endpoint kiểm tra sức khỏe tại: `https://<ten-app>.onrender.com/api/health`.
