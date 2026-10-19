# Đảo Tò Mò - Minecraft Web Portal & Backend (Laravel 11)

Hệ thống Web Portal và RESTful API dành cho máy chủ Minecraft **Đảo Tò Mò** (`daotomo.site`), được phát triển trên nền tảng **PHP 8.2+** và **Laravel 11**, tích hợp giao diện người dùng đầy đủ (HTML/CSS/JS, Three.js 3D Skin Viewer, Sound FX, Command Palette, Hệ thống giỏ hàng và Đơn hàng).

---

## ⚠️ Khắc phục lỗi Render: `Cannot find module '/opt/render/project/src/server.js'`

### Nguyên nhân lỗi
Trước đây repository sử dụng **Node.js (Express)** với file khởi động `server.js`. Render đã tự động gán cấu hình dịch vụ dạng:
- **Runtime:** `Node`
- **Build Command:** `npm install`
- **Start Command:** `node server.js`

Khi dự án được chuyển đổi hoàn toàn sang **PHP & Laravel 11**, file `server.js` đã không còn. Do Render không có môi trường chạy native PHP, tài liệu chuẩn của Render yêu cầu chạy PHP/Laravel thông qua **Docker Container** ([Xem tài liệu chính thức](https://render.com/docs/deploy-php-laravel-docker)).

---

## 🚢 Hướng dẫn triển khai chuẩn hóa trên Render (Docker)

Repository đã được chuẩn hóa với đầy đủ:
- `Dockerfile` (Dựa trên base image `richarvey/nginx-php-fpm:3.1.6` hỗ trợ PHP 8.2+ & Nginx).
- `conf/nginx/nginx-site.conf` (Document root trỏ vào `/var/www/html/public/`, chặn truy cập file ẩn và bảo mật).
- `scripts/00-laravel-deploy.sh` (Script khởi động container, set quyền storage, chạy migration an toàn `php artisan migrate --force`).
- `.dockerignore` (Loại bỏ cache, file env và sqlite local khỏi image).
- `render.yaml` (Cấu hình Blueprint chuẩn).

### Các bước sửa cấu hình trên Render Dashboard (Chỉ mất 1 phút)

1. Đăng nhập vào [Render Dashboard](https://dashboard.render.com).
2. Chọn Web Service **`daotomo-backend`** (hoặc tên dịch vụ của bạn).
3. Vào tab **Settings**:
   - Tìm mục **Build & Deploy** -> **Runtime**: Chuyển từ **`Node`** sang **`Docker`**.
   - Xóa bỏ **Build Command** (`npm install`) và **Start Command** (`node server.js`) nếu còn tồn tại.
   - Trường **Dockerfile Path** để mặc định là `./Dockerfile`.
4. Vào tab **Environment** (Biến môi trường) và cấu hình các giá trị cần thiết (xem bảng bên dưới).
5. Nhấn **Save Changes** và chọn **Manual Deploy** -> **Deploy latest commit**.

---

## 🔐 Bảng biến môi trường Production (Environment Variables)

| Tên biến | Bắt buộc | Ví dụ / Giá trị khuyến nghị | Mục đích |
|---|---|---|---|
| `APP_KEY` | **Có** | `base64:...` (chạy `php artisan key:generate --show` để lấy) | Khóa mã hóa session & token của Laravel |
| `APP_ENV` | **Có** | `production` | Chạy ở chế độ production |
| `APP_DEBUG` | **Có** | `false` | Tắt debug để không lộ thông tin nhạy cảm |
| `LOG_CHANNEL` | **Có** | `stderr` | Xuất log ra console Render để xem lỗi thời gian thực |
| `DB_CONNECTION` | **Có** | `pgsql` *(khi dùng Render Postgres)* hoặc `sqlite` | Driver cơ sở dữ liệu |
| `DATABASE_URL` | Tùy chọn | `postgres://user:pass@host:5432/db` | Internal Database URL của PostgreSQL trên Render |
| `FRONTEND_URL` | Khuyến nghị | `https://www.daotomo.site` | Domain frontend để redirect sau OAuth / reset mật khẩu |
| `CORS_ALLOWED_ORIGINS` | Khuyến nghị | `https://daotomo.site,https://www.daotomo.site` | Danh sách domain được phép gọi API |
| `DISCORD_CLIENT_ID` | Khi dùng Discord | `123456789012345678` | Client ID ứng dụng Discord |
| `DISCORD_CLIENT_SECRET` | Khi dùng Discord | *(Secret từ Discord Portal)* | Client Secret ứng dụng Discord |
| `DISCORD_CALLBACK_URL` | Khi dùng Discord | `https://<tên-app>.onrender.com/api/auth/discord/callback` | URL callback đã khai báo trên Discord Portal |

> 🔒 **Lưu ý an toàn:** Không bao giờ commit các giá trị bí mật (`APP_KEY`, `DISCORD_CLIENT_SECRET`, mật khẩu DB) vào Git. Mọi giá trị bí mật chỉ điền qua tab **Environment** trên Render Dashboard.

---

## 🛡️ Quy trình Migration và Sao lưu Database an toàn

1. **Tuyệt đối không dùng `migrate:fresh`:**
   - Trong `scripts/00-laravel-deploy.sh`, lệnh chạy là `php artisan migrate --force`.
   - Lệnh này **chỉ thực thi các migration mới**, giữ nguyên toàn bộ bảng và dữ liệu người dùng hiện có.
2. **Không tạo lại `APP_KEY` mỗi lần deploy:**
   - `APP_KEY` được sinh 1 lần và cấu hình cố định trong biến môi trường Render. Việc đổi `APP_KEY` sẽ làm mất hiệu lực các session và token đã cấp.
3. **Chiến lược Backup:**
   - **Render PostgreSQL:** Render tự động tạo snapshot hàng ngày. Khi cần can thiệp cấu trúc lớn, có thể chạy lệnh `pg_dump` từ Render Shell.
   - **SQLite:** Nếu dùng SQLite kèm Render Persistent Disk, backup định kỳ file `database.sqlite`.

---

## 🎮 Luồng Discord OAuth 2.0

Hệ thống hỗ trợ đăng nhập và liên kết tài khoản Discord an toàn:
1. Người dùng bấm **"Tiếp tục với Discord"** tại trang `/html/Account.html`.
2. Frontend chuyển hướng tới `/api/auth/discord`.
3. Backend sinh chuỗi `state` ngẫu nhiên lưu vào Cache (chống tấn công CSRF) và chuyển hướng tới trang xác thực của Discord:
   `https://discord.com/api/oauth2/authorize?...`
4. Sau khi người dùng đồng ý, Discord gọi lại callback:
   `/api/auth/discord/callback?code=...&state=...`
5. Backend kiểm tra `state`, trao đổi `code` lấy `access_token`, tải hồ sơ người dùng:
   - Nếu `discord_id` đã có: đăng nhập tài khoản tương ứng.
   - Nếu email trùng với tài khoản sẵn có: tự động liên kết `discord_id`.
   - Nếu là người dùng mới: tạo tài khoản mới với username, email và avatar Discord.
6. Cấp Sanctum Bearer Token và redirect an toàn về frontend:
   `${FRONTEND_URL}/html/Account.html?oauthToken=${token}`

---

## 🚀 Tính năng nổi bật của hệ thống

### 1. Giao diện người dùng (Frontend - Giữ nguyên 100%)
- **Trang chủ & Cửa hàng (`/`, `/html/Shop.html`):** Hiển thị danh mục Rank VIP, Key Crate, Xu Đảo, Vật phẩm đặc biệt; giỏ hàng real-time, bộ lọc danh mục và tìm kiếm.
- **Mô hình Skin Minecraft 3D tương tác:**
  - Chưa đăng nhập: Mô hình 3D Steve mặc định cử động xoay/chạy.
  - Sau khi đăng nhập: Tự động tải skin tùy chỉnh của người chơi.
  - Người dùng có thể upload skin PNG trực tiếp (`64x64`, `64x32`, `128x128`), hệ thống kiểm tra hợp lệ và hiển thị ngay trên mô hình 3D.
- **Hệ thống nạp thẻ & Thanh toán (`/html/Recharge.html`):** Chuyển khoản QR, tải minh chứng chuyển khoản (ảnh biên lai).
- **Điểm danh nhận quà hàng ngày (`/html/Checkin.html`):** Tính streak liên tục và cộng xu thưởng.
- **Hồ sơ cá nhân & Quản lý đơn hàng (`/html/Account.html`):** Đổi mật khẩu, xem lịch sử giao dịch và trạng thái đơn hàng.
- **Admin Panel (`/html/Admin.html`):** Quản lý sản phẩm, duyệt đơn hàng và cập nhật trạng thái đơn (pending -> paid -> completed / cancelled).
- **Hiệu ứng & Trải nghiệm:** Sound FX Minecraft, phím tắt nhanh (`Ctrl+K` Command Palette), Modal thông báo, Toast notification.

### 2. Backend & Kiến trúc (Laravel 11)
- **Framework:** Laravel 11 (PHP 8.2+).
- **Authentication:** Laravel Sanctum (Bearer Token API Auth).
- **Cơ sở dữ liệu:** Hỗ trợ SQLite, MySQL (XAMPP) và PostgreSQL (Render).
- **Bảo mật:**
  - Validation kích thước skin Minecraft bằng PHP GD (`64x64`, `64x32`, `128x128`).
  - Chống race-condition và bảo vệ giá tiền đơn hàng (tính toán lại từ DB, hỗ trợ `idempotency_key`).
  - Máy trạng thái đơn hàng: Ngăn chặn chuyển đổi trạng thái không hợp lệ.
  - Phân quyền RBAC qua `AdminMiddleware`.
  - Trust Proxies & Force HTTPS tự động khi chạy trên Render.

---

## 🛠️ Cài đặt & Chạy Local

### Yêu cầu môi trường
- PHP >= 8.2 (đi kèm XAMPP hoặc cài rời với extension `pdo_sqlite`, `pdo_mysql`, `gd`, `zip`, `mbstring`, `fileinfo`)
- Composer >= 2.x
- Git

### Các bước chạy local:
```bash
# 1. Clone repository
git clone https://github.com/Dragonkun1946/daotomo-backend.git
cd daotomo-backend

# 2. Cài đặt thư viện
composer install

# 3. Tạo file cấu hình và sinh khóa app
copy .env.example .env
php artisan key:generate

# 4. Chạy migration và nạp dữ liệu mẫu
php artisan migrate --seed

# 5. Khởi chạy server
php artisan serve --port=8000
```
Truy cập: **`http://127.0.0.1:8000`**

### Tài khoản thử nghiệm có sẵn:
- **Admin:** `admin@daotomo.site` / Mật khẩu: `Admin@123456`
- **Player:** `player@daotomo.site` / Mật khẩu: `Player@123456`

---

## 🧪 Kiểm thử tự động (Automated Testing)

Chạy bộ kiểm thử toàn diện:
```bash
php artisan test
```
**Kết quả thực tế:**
- **30 tests passed, 0 failed (75 assertions)**
- Bao gồm kiểm thử:
  - Health check (`/api/health`)
  - Đăng ký, đăng nhập, hồ sơ người dùng
  - Upload skin Minecraft (kiểm tra định dạng, kích thước 64x64/64x32/128x128)
  - Khôi phục skin Steve mặc định
  - Danh sách & Chi tiết sản phẩm
  - Tạo đơn hàng, tính giá từ DB, chống trùng lặp (Idempotency)
  - Bảo vệ quyền sở hữu đơn hàng & hủy đơn
  - Máy trạng thái đơn hàng (State machine)
  - Phân quyền Admin
  - Điểm danh nhận quà hàng ngày (Streak)
  - Toàn bộ luồng Discord OAuth (Authorize, callback, invalid state, token exchange, user creation & linking)

---

## 📂 Bản sao lưu Node.js cũ (Backup)

Bản sao lưu toàn vẹn mã nguồn Node.js/Express cũ:
- **Tập tin zip:** `daotomo-backend-backup.zip` (4.7 MB)
- **Nhánh Git:** `backup-nodejs` (đã được đẩy lên GitHub `origin/backup-nodejs`)
