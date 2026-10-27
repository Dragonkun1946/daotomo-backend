# Kiến Trúc Hệ Thống — Đảo Tò Mò (DaoTomo Platform)

Tài liệu này mô tả chi tiết kiến trúc source code, quy ước thiết kế, phân chia domain, luồng dữ liệu và hướng dẫn mở rộng cho nền tảng Đảo Tò Mò.

---

## 1. Cây Thư Mục Tổng Quan (Target Architecture)

```
daotomo-backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/
│   │   │   │   ├── Auth/
│   │   │   │   │   ├── AuthController.php          # Đăng ký, Đăng nhập
│   │   │   │   │   ├── PasswordResetController.php # Quên mật khẩu, Đặt lại mật khẩu
│   │   │   │   │   └── DiscordOAuthController.php  # Điều hướng & Callback Discord OAuth
│   │   │   │   ├── User/
│   │   │   │   │   ├── ProfileController.php       # Xem & cập nhật thông tin hồ sơ
│   │   │   │   │   └── SkinController.php          # Quản lý skin Minecraft & avatar khuôn mặt
│   │   │   │   ├── Order/
│   │   │   │   │   └── OrderController.php         # Tạo đơn thành viên/khách, xem đơn, hủy đơn
│   │   │   │   ├── Product/
│   │   │   │   │   └── ProductController.php       # Danh mục sản phẩm công khai
│   │   │   │   ├── Checkin/
│   │   │   │   │   └── CheckinController.php       # Điểm danh hàng ngày & nhận xu
│   │   │   │   └── Payment/
│   │   │   │       └── SepayWebhookController.php  # Xử lý webhook thanh toán SePay
│   │   │   ├── Admin/
│   │   │   │   ├── AdminOrderController.php        # Quản lý đơn hàng & state machine cho Admin
│   │   │   │   └── AdminProductController.php      # Thêm, sửa, xóa sản phẩm
│   │   │   ├── AuthController.php                  # Facade tương thích ngược
│   │   │   ├── OrderController.php                 # Facade tương thích ngược
│   │   │   ├── ProductController.php               # Facade tương thích ngược
│   │   │   ├── CheckinController.php               # Facade tương thích ngược
│   │   │   ├── SepayWebhookController.php          # Facade tương thích ngược
│   │   │   └── Controller.php                      # Base Controller
│   │   ├── Middleware/
│   │   │   └── AdminMiddleware.php                 # Xác thực quyền quản trị viên (role: admin)
│   │   ├── Requests/
│   │   │   ├── BaseApiRequest.php                  # Chuẩn hóa response lỗi 422/400 JSON thống nhất
│   │   │   ├── Auth/
│   │   │   │   ├── RegisterRequest.php
│   │   │   │   ├── ForgotPasswordRequest.php
│   │   │   │   └── ResetPasswordRequest.php
│   │   │   ├── User/
│   │   │   │   └── UpdateProfileRequest.php
│   │   │   ├── Order/
│   │   │   │   ├── CreateOrderRequest.php
│   │   │   │   └── UpdateOrderStatusRequest.php
│   │   │   └── Product/
│   │   │       ├── StoreProductRequest.php
│   │   │       └── UpdateProductRequest.php
│   │   └── Resources/
│   │       ├── UserResource.php                    # Chuẩn hóa JSON User (hỗ trợ camelCase + snake_case)
│   │       ├── OrderResource.php                   # Chuẩn hóa JSON Order
│   │       └── ProductResource.php                 # Chuẩn hóa JSON Product
│   ├── Mail/
│   │   └── ResetPasswordMail.php                   # Laravel Mailable HTML & Text
│   ├── Models/
│   │   ├── User.php
│   │   ├── Order.php
│   │   ├── OrderItem.php
│   │   ├── Product.php
│   │   ├── Checkin.php
│   │   └── PaymentTransaction.php
│   ├── Providers/
│   │   └── AppServiceProvider.php
│   └── Services/
│       ├── Auth/
│       │   ├── DiscordOAuthService.php             # Xử lý token exchange, lấy profile & map tài khoản
│       │   └── PasswordResetService.php            # Gửi mail đa tầng (Resend HTTPS -> Brevo HTTPS -> SMTP)
│       ├── User/
│       │   └── SkinService.php                     # Đọc header PNG, parse kích thước, render GD avatar
│       ├── Order/
│       │   └── OrderService.php                    # Tính giá, tạo đơn, sinh mã DTM-XXXX, state machine
│       ├── Checkin/
│       │   └── CheckinService.php                  # Tính streak & cộng xu
│       └── SepayService.php                        # Xác thực chữ ký HMAC-SHA256 & xử lý giao dịch
├── conf/
│   └── nginx/nginx-site.conf                       # Cấu hình Nginx production (Render Docker)
├── config/                                         # Cấu hình chuẩn Laravel 11
├── database/
│   ├── factories/                                  # Model Factories cho Testing
│   ├── migrations/                                 # Migrations theo timestamp
│   └── seeders/                                    # Seeders dữ liệu ban đầu
├── public/                                         # Frontend tĩnh & Entrypoint PHP
│   ├── css/                                        # Giao diện modular
│   ├── html/                                       # Các trang HTML độc lập (Account, Admin, Store...)
│   ├── img/                                        # Assets hình ảnh
│   ├── js/
│   │   ├── api.js                                  # HTTP Client gọi API backend
│   │   └── skin-viewer.js                          # SkinView3D WebGL renderer
│   └── index.php                                   # Laravel Front Controller
├── routes/
│   ├── api.php                                     # REST API endpoints
│   ├── web.php                                     # Webhooks root alias & Static fallback
│   └── console.php
├── scripts/
│   └── 00-laravel-deploy.sh                        # Script tự động migrate & seed trên Render
├── tests/
│   ├── Feature/
│   │   ├── ApiTest.php                             # 42 Test cases kiểm tra full tính năng API
│   │   └── SepayWebhookTest.php                    # 17 Test cases kiểm tra bảo mật & Webhook SePay
│   └── Unit/
│       ├── OrderServiceTest.php                    # Unit test Order Service & State Machine
│       ├── PasswordResetServiceTest.php            # Unit test Password Reset Service
│       └── SkinServiceTest.php                     # Unit test Skin Processing & Validation
├── Dockerfile                                      # Multi-stage Docker container cho Render
└── render.yaml                                     # Render Blueprint tự động triển khai
```

---

## 2. Luồng Xử Lý Request (Request Lifecycle)

```
[Client / Browser]
       │
       ▼
[Nginx / Front Controller (index.php)]
       │
       ▼
[Route (routes/api.php)]
       │
       ▼
[FormRequest (app/Http/Requests/*)] ──(Lỗi dữ liệu)──► [BaseApiRequest -> 422/400 JSON]
       │ (Hợp lệ)
       ▼
[Controller (app/Http/Controllers/Api/*)]
       │
       ▼
[Domain Service (app/Services/*)]
       │
       ▼
[Eloquent Model (app/Models/*)] ◄──► [Database (SQLite / PostgreSQL)]
       │
       ▼
[API Resource (app/Http/Resources/*)]
       │
       ▼
[JSON Response (status: 200/201/400/403/404/500)]
```

---

## 3. Các Nguyên Tắc & Quy Ước Thiết Kế (Conventions)

### 3.1. Phân Tách Trách Nhiệm (Separation of Concerns)
1. **Controller:** Chỉ tiếp nhận Request, điều phối gọi Service và trả về Response HTTP tương ứng. Không chứa nghiệp vụ tính toán tiền, xử lý đồ họa hay giao tiếp API bên ngoài.
2. **Form Request:** Đảm nhiệm 100% việc validate dữ liệu đầu vào. Kế thừa `BaseApiRequest` để đảm bảo định dạng lỗi JSON luôn thống nhất:
   ```json
   {
       "success": false,
       "message": "Thông báo lỗi tiếng Việt thân thiện",
       "errors": { "field": ["chi tiết lỗi"] }
   }
   ```
3. **Service:** Đảm nhiệm toàn bộ nghiệp vụ lõi (Business Logic), hoàn toàn độc lập với HTTP Request, dễ dàng unit test mà không cần khởi tạo HTTP session.
4. **API Resource:** Đảm nhiệm format dữ liệu trả về cho client. Tự động hỗ trợ cả định dạng `camelCase` (cho JavaScript frontend) lẫn `snake_case` (cho database convention) để giữ nguyên vẹn hợp đồng API.

### 3.2. Cơ Chế Tương Thích Ngược 100% (Backward Compatibility)
Các Controller ở thư mục gốc `app/Http/Controllers/` (`AuthController`, `OrderController`, `ProductController`, `CheckinController`, `SepayWebhookController`) đóng vai trò là **Facade Adapters**:
- Nhận request từ các route hoặc test cũ.
- Tự động ủy quyền (delegate) sang Controller chuyên biệt tương ứng.
- Đảm bảo 100% các route, test cũ, test mới, và frontend JavaScript tiếp tục hoạt động trơn tru không lỗi.

---

## 4. Tích Hợp Bên Ngoài (External Integrations)

### 4.1. Cổng Thanh Toán SePay (`SepayService.php`)
- **Xác thực:** Hỗ trợ cả `API Key` (`Authorization: Apikey ...`) và chữ ký bảo mật `HMAC-SHA256` với chống tấn công phát lại (Replay Attack Protection qua timestamp drift check).
- **Trích xuất mã đơn:** Tự động nhận diện cú pháp `DTM XXXXXX`, `DTM-XXXXXX`, hoặc `DTMXXXXXX`.
- **Tự phục hồi đơn:** Khi khách chuyển khoản hợp lệ nhưng đơn hàng bị lỗi mạng chưa lưu trên DB, SePay Webhook tự động khôi phục và đánh dấu PAID.
- **Tính lũy thừa (Idempotency):** Không bao giờ cộng tiền 2 lần cho cùng một mã giao dịch (`transaction_id`).

### 4.2. Gửi Email Đặt Lại Mật Khẩu Đa Tầng (`PasswordResetService.php`)
Do môi trường Render gói Free chặn các cổng SMTP truyền thống (25, 465, 587), hệ thống áp dụng chiến lược gửi mail 3 tầng:
1. **Resend HTTPS API (Cổng 443):** Nếu có biến `RESEND_API_KEY`, gửi trực tiếp qua API REST của Resend, vượt qua tường lửa của hosting.
2. **Brevo HTTPS API (Cổng 443):** Nếu có biến `BREVO_API_KEY`, gửi qua API REST của Brevo.
3. **Standard SMTP (Fallback):** Hoạt động khi chạy môi trường máy trạm (local/XAMPP) hoặc server có mở cổng SMTP.

### 4.3. Đăng Nhập Discord OAuth (`DiscordOAuthService.php`)
- Bảo vệ CSRF với state token ngẫu nhiên lưu Cache (TTL 15 phút).
- Tự động liên kết tài khoản nếu email trùng khớp với tài khoản đã đăng ký trước.
- Lưu trữ `discord_id`, `discord_username` và cập nhật avatar theo hash mới nhất của Discord.

---

## 5. Hướng Dẫn Mở Rộng Tính Năng Mới

Khi thêm một module hoặc nghiệp vụ mới:
1. **Migration & Model:** Tạo migration trong `database/migrations/` và Model trong `app/Models/`.
2. **Service:** Tạo Service xử lý logic tại `app/Services/{Domain}/{Domain}Service.php`.
3. **Form Request:** Tạo Request validate tại `app/Http/Requests/{Domain}/{Action}Request.php` kế thừa `BaseApiRequest`.
4. **Controller:** Tạo Controller tại `app/Http/Controllers/Api/{Domain}/{Domain}Controller.php`.
5. **Route:** Đăng ký route trong `routes/api.php` theo nhóm quyền tương ứng.
6. **Test:** Viết Feature Test trong `tests/Feature/` và Unit Test trong `tests/Unit/`.
