# Đảo Tò Mò - Minecraft Web Portal & Backend (Laravel 11)

Hệ thống Web Portal và RESTful API dành cho máy chủ Minecraft **Đảo Tò Mò** (`daotomo.site`), được phát triển trên nền tảng **PHP 8.2+** và **Laravel 11**, tích hợp giao diện người dùng đầy đủ (HTML/CSS/JS, Three.js 3D Skin Viewer, Sound FX, Command Palette, Hệ thống giỏ hàng và Đơn hàng).

---

## 🚀 Tính năng nổi bật

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
- **Database Support:**
  - **SQLite (Mặc định - Zero-config):** Khởi chạy ngay lập tức không cần cài đặt thêm MySQL service.
  - **MySQL (Sẵn sàng):** Chỉ cần bật MySQL trên XAMPP và đổi cấu hình trong `.env`.
- **Validation chặt chẽ:**
  - Kiểm tra kích thước file skin Minecraft bằng PHP GD (`64x64`, `64x32`, `128x128`).
  - Chống race-condition và bảo vệ giá tiền đơn hàng (tính toán lại từ cơ sở dữ liệu, hỗ trợ `idempotency_key`).
  - Máy trạng thái đơn hàng (State-machine invariant): Không cho phép thanh toán đơn đã hủy.
  - Phân quyền RBAC (Role-based access control) qua `AdminMiddleware`.

---

## 🛠️ Yêu cầu môi trường

- **PHP:** >= 8.2 (Khuyến nghị dùng PHP đi kèm XAMPP tại `C:\xampp\php\php.exe`)
- **PHP Extensions cần thiết:** `pdo_sqlite`, `pdo_mysql`, `gd`, `zip`, `mbstring`, `fileinfo`
- **Composer:** >= 2.x
- **Git**

---

## 📦 Hướng dẫn cài đặt & Chạy ứng dụng

### 1. Clone repository
```bash
git clone https://github.com/Dragonkun1946/daotomo-backend.git
cd daotomo-backend
```

### 2. Cài đặt thư viện PHP
```bash
composer install
```

### 3. Cấu hình môi trường (.env)
Tạo file `.env` từ `.env.example`:
```bash
cp .env.example .env
php artisan key:generate
```

*(Mặc định `.env` đã được cấu hình sẵn SQLite tại `database/database.sqlite`, không cần cấu hình thêm gì!)*

Nếu muốn dùng MySQL qua XAMPP:
1. Mở XAMPP và khởi động **MySQL**.
2. Tạo database tên `daotomo` trên phpMyAdmin.
3. Trong `.env`, đổi `DB_CONNECTION=sqlite` thành `DB_CONNECTION=mysql` và bỏ comment các dòng MySQL:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=daotomo
   DB_USERNAME=root
   DB_PASSWORD=
   ```

### 4. Chạy Migration và Seed dữ liệu mẫu
```bash
php artisan migrate:fresh --seed
```

Lệnh trên sẽ tự động tạo bảng và nạp sẵn 2 tài khoản cùng 9 sản phẩm Minecraft mẫu.

### 5. Khởi động Web Server
```bash
php artisan serve --port=8000
```
Truy cập trình duyệt tại: **`http://127.0.0.1:8000`**

---

## 🔑 Tài khoản thử nghiệm có sẵn

| Vai trò | Email | Mật khẩu | Chức năng |
|---|---|---|---|
| **Admin** | `admin@daotomo.site` | `Admin@123456` | Truy cập trang Quản trị viên (`/html/Admin.html`), CRUD sản phẩm, duyệt đơn hàng |
| **Player** | `player@daotomo.site` | `Player@123456` | Mua hàng, điểm danh nhận xu, đổi skin Minecraft 3D |

---

## 📡 Danh sách RESTful API Endpoints

### 🔐 Authentication (`/api/auth`)
- `POST /api/auth/register` - Đăng ký tài khoản
- `POST /api/auth/login` - Đăng nhập (trả về Sanctum Bearer token)
- `GET /api/auth/me` - Lấy thông tin tài khoản hiện tại (kèm thông tin skin)
- `PUT /api/auth/me` - Cập nhật thông tin tài khoản
- `POST /api/auth/skin` - Tải lên skin Minecraft PNG (hỗ trợ 64x64, 64x32, 128x128)
- `POST /api/auth/forgot-password` - Quên mật khẩu
- `POST /api/auth/reset-password` - Đặt lại mật khẩu

### 🛒 Sản phẩm (`/api/products`)
- `GET /api/products` - Danh sách sản phẩm (hỗ trợ filter category, search, featured)
- `GET /api/products/{id}` - Chi tiết sản phẩm
- `POST /api/products` *(Admin)* - Tạo sản phẩm mới
- `PUT /api/products/{id}` *(Admin)* - Chỉnh sửa sản phẩm
- `DELETE /api/products/{id}` *(Admin)* - Xóa sản phẩm

### 🧾 Đơn hàng (`/api/orders`)
- `POST /api/orders` - Đặt hàng mới (tính giá chuẩn từ DB, sinh mã `DTM-XXXXXX`)
- `GET /api/orders/my-orders` - Lịch sử đơn hàng của người dùng đang đăng nhập
- `GET /api/orders/{id}` - Chi tiết đơn hàng (bảo vệ quyền sở hữu)
- `POST /api/orders/{id}/cancel` - Hủy đơn hàng
- `POST /api/orders/{id}/payment-proof` - Gửi ảnh xác nhận thanh toán chuyển khoản
- `GET /api/orders/admin/all` *(Admin)* - Danh sách toàn bộ đơn hàng
- `PUT /api/orders/{id}/status` *(Admin)* - Cập nhật trạng thái đơn hàng (tuân thủ state-machine)

### 🎁 Điểm danh (`/api/checkin`)
- `GET /api/checkin/me` - Lấy lịch sử và trạng thái điểm danh hôm nay
- `POST /api/checkin` - Điểm danh nhận xu hàng ngày (tính chuỗi streak)

---

## 🧪 Kiểm thử tự động (Automated Tests)

Chạy bộ kiểm thử toàn diện 24 feature tests:
```bash
php artisan test
```
**Kết quả:** `24 passed, 0 failed` (59 assertions) bao gồm kiểm thử xác thực, RBAC, kiểm tra kích thước skin PNG, chống mua hàng sai giá và luồng đơn hàng.

---

## 📂 Sao lưu phiên bản cũ (Node.js Codebase Backup)

Phiên bản backend cũ sử dụng Node.js/Express/MongoDB đã được sao lưu toàn vẹn:
- **Git Branch:** `backup-nodejs`
- **Tập tin nén:** `daotomo-backend-backup.zip` (được tạo trước khi chuyển đổi)
- Để xem lại phiên bản Node.js cũ trong git:
  ```bash
  git checkout backup-nodejs
  ```
