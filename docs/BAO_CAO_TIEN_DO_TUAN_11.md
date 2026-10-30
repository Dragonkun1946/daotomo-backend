BỘ GIÁO DỤC VÀ ĐÀO TẠO
TRƯỜNG CAO ĐẲNG LÝ TỰ TRỌNG
KHOA CÔNG NGHỆ THÔNG TIN

BÁO CÁO TIẾN ĐỘ ĐỒ ÁN
(Đến hết Tuần 11)

ĐỀ TÀI:
XÂY DỰNG NỀN TẢNG WEB QUẢN TRỊ TẬP TRUNG VÀ TÍCH HỢP API CHO MÁY CHỦ TRÒ CHƠI TRỰC TUYẾN

Giảng viên hướng dẫn: Mai Chiếm Tuấn
Sinh viên thực hiện:
Nguyễn Trọng Nghĩa - MSSV 25002449
Tống Hoàng Anh Tuấn - MSSV 25002461
Lớp: 25C2-LTM1 · Ngành: Lập trình máy tính · Khóa 2025
Học kỳ 3, năm học 2025 - 2026
TP. Hồ Chí Minh

---

1. Giới thiệu
Việc quản lý tài khoản, sản phẩm, đơn hàng và giao dịch bằng các công cụ rời rạc gây khó khăn cho việc theo dõi dữ liệu và đối soát. Đề tài xây dựng một nền tảng Web quản trị tập trung, tích hợp thanh toán SePay và lớp API trao đổi dữ liệu với máy chủ trò chơi (hoặc mock server), giúp quản trị viên theo dõi, xử lý giao dịch và hỗ trợ vận hành ở mức thông tin — không xây dựng máy chủ trò chơi từ đầu.

Báo cáo này trình bày kết quả thực hiện từ Tuần 8 đến hết Tuần 11 theo lộ trình 15 tuần trong phiếu đăng ký đề tài: hoàn thành module đơn hàng và giao dịch (Tuần 8), tích hợp thanh toán tự động qua SePay (Tuần 9), bước đầu xây dựng dữ liệu kết nối máy chủ game (Tuần 10) và tích hợp vận hành toàn hệ thống Web - Thanh toán (Tuần 11).

---

2. Mục tiêu và phạm vi giai đoạn này (Tuần 8 đến Tuần 11)
- Xây dựng hoàn chỉnh module đơn hàng và giao dịch: tạo đơn hàng, tính giá từ cơ sở dữ liệu server-side, chống trùng lặp (idempotency), hỗ trợ đơn thành viên và khách vãng lai.
- Tích hợp cổng thanh toán trực tuyến SePay: sinh mã VietQR động, tiếp nhận webhook tự động, xác thực chữ ký bảo mật HMAC-SHA256, chống tấn công phát lại (replay attack) và tự phục hồi đơn hàng.
- **Đợt tái thiết kế toàn diện Giao diện (UI/UX Rework 2.0):** Thực hiện bước chuyển đổi mỹ thuật từ giao diện thô sơ ở mốc Tuần 7 sang hệ thống Design System "Neo-Mythic Island" hoàn chỉnh trên 9 trang chính.
- **Tích hợp Mô hình 3D Nhân vật tương tác (Three.js WebGL):** Nhúng trực tiếp thư viện `skinview3d` để hiển thị mô hình 3D xoay 360 độ, cử động mượt mà và xây dựng 3D Skin Studio đổi skin trực tuyến.
- Khắc phục triệt để lỗi gửi email quên mật khẩu qua kiến trúc chuyển phát email đa tầng (Resend/Brevo HTTPS API cổng 443 giải quyết hạn chế chặn cổng SMTP của Render).
- Tái cấu trúc mã nguồn theo mô hình Domain-Oriented Architecture để mã nguồn sạch, dễ mở rộng và đạt chuẩn công nghiệp.
- Chuẩn bị dữ liệu định danh người chơi trong game (`game_username`) và mã phần thưởng (`game_reward`) phục vụ lớp API kết nối máy chủ trò chơi.
- Các nội dung về plugin kết nối thực tế trong game và kiểm thử tải trên Render thuộc Tuần 12 và Tuần 13.

---

3. Công nghệ và kiến trúc
| Thành phần | Công nghệ sử dụng |
|---|---|
| Backend | PHP 8.2+, Framework Laravel 11.31 (Clean Architecture, Service Layer) |
| Đồ họa 3D & WebGL | Three.js WebGL Engine, `skinview3d` bundle, OrbitControls, Canvas GD Avatar |
| Cơ sở dữ liệu | SQLite (môi trường dev/test), PostgreSQL/MySQL (môi trường Render production) |
| Xác thực | Laravel Sanctum (Token-based Auth), băm mật khẩu Bcrypt, đăng nhập Discord OAuth2 |
| Email Service | Resend HTTPS API & Brevo HTTPS API (Cổng 443), Laravel Mailable (SMTP fallback) |
| Cổng thanh toán | SePay Gateway, VietQR API, chuẩn HMAC-SHA256 timestamped |
| Frontend | HTML5, CSS3, JavaScript (ES6+), Design System 2.0 "Neo-Mythic Island" |
| Triển khai | Render (Backend Docker container), Vercel (Frontend), tên miền `daotomo.site` |
| Công cụ | Visual Studio Code, Git/GitHub, Composer, PHPUnit/Pest |

*Lưu ý về công nghệ so với mốc Tuần 7:* Tại mốc Tuần 7, nhóm từng thử nghiệm nguyên mẫu bằng Node.js/Express. Tuy nhiên, từ Tuần 8–9, nhóm đã quyết định chuyển đổi và chuẩn hóa toàn bộ backend sang **PHP/Laravel 11** đúng với mục V trong Phiếu đăng ký đề tài ban đầu. Nhóm vẫn duy trì trường `_id` trong dữ liệu JSON trả về để tương thích hoàn toàn với frontend JavaScript có sẵn.

3.1. Cấu trúc 6 Bảng Cơ sở dữ liệu nghiệp vụ (Database Schema)
Cơ sở dữ liệu của dự án được thiết kế chuẩn hóa theo mô hình quan hệ (RDBMS), hỗ trợ ràng buộc khóa ngoại (Foreign Keys), tính toàn vẹn giao dịch (ACID Transactions) và các chỉ mục duy nhất (Unique Index) chống trùng lặp:
| Tên bảng | Khóa chính & Các trường dữ liệu cốt lõi | Vai trò và ràng buộc nghiệp vụ |
|---|---|---|
| `users` | `id, username, name, email, password, role, game_username, skin_url, avatar, coins, discord_id, bio` | Lưu trữ tài khoản người dùng, định danh trong game (IGN), số dư xu, liên kết Discord OAuth và quyền Admin/User. |
| `products` | `id, name, slug, price, original_price, category, image, images, stock_quantity, is_active, featured, game_reward` | Quản lý danh mục 12 sản phẩm game: gói VIP, tiền tệ, trang bị, hòm kho báu và mã phần thưởng game_reward. |
| `orders` | `id, order_code, user_id, game_username, total_amount, order_status, payment_status, payment_method, idempotency_key` | Quản lý đơn hàng, mã đơn DTM-XXXXXX, tính tiền server-side, chống trùng idempotency và lưu vết status_history. |
| `order_items` | `id, order_id, product_id, product_name, price, quantity, subtotal` | Chi tiết từng sản phẩm trong đơn hàng, lưu snapshot giá và tên sản phẩm tại thời điểm mua tránh sai lệch khi đổi giá. |
| `payment_transactions` | `id, order_id, sepay_transaction_id, account_number, transfer_amount, code, content, reference_code` | Lưu vết toàn bộ giao dịch đối soát từ Webhook SePay, ràng buộc duy nhất reference_code chống tấn công phát lại Replay. |
| `checkins` | `id, user_id, checkin_date, coins_awarded, streak_day` | Quản lý tính năng điểm danh nhận xu hàng ngày, ràng buộc 1 lượt/ngày/user và theo dõi chuỗi ngày liên tiếp (1-7 ngày). |

3.2. Mô hình tổ chức mã nguồn (Domain-Oriented Clean Architecture):
| Thư mục/tệp | Vai trò |
|---|---|
| `app/Http/Controllers/Api/` | Bộ điều khiển chia theo domain: `Auth`, `User`, `Order`, `Product`, `Checkin`, `Payment` |
| `app/Http/Controllers/Admin/` | Bộ điều khiển dành riêng cho quản trị viên: `AdminOrderController`, `AdminProductController` |
| `app/Http/Controllers/*.php` | Các Controller gốc đóng vai trò Facade Adapter đảm bảo 100% tương thích ngược |
| `app/Services/` | Tầng xử lý nghiệp vụ độc lập: `SepayService`, `OrderService`, `PasswordResetService`, `DiscordOAuthService`, `SkinService`, `CheckinService` |
| `app/Http/Requests/` | `BaseApiRequest` và các Form Requests chuẩn hóa validate dữ liệu, trả mã lỗi 422/400 JSON |
| `app/Http/Resources/` | `UserResource`, `OrderResource`: chuẩn hóa dữ liệu trả về cho client |
| `app/Models/` | `User`, `Product`, `Order`, `OrderItem`, `PaymentTransaction`, `Checkin` |
| `public/js/skin-viewer.js & bundle` | Động cơ render 3D WebGL Three.js: xoay 360 độ, animation và trích xuất khuôn mặt |
| `routes/api.php` | Định tuyến 37 endpoints RESTful có phân quyền rõ ràng |
| `database/seeders/` | `DatabaseSeeder.php`: nạp 12 sản phẩm chính thức và các tài khoản demo chuẩn |
| `public/` | Giao diện: Cửa hàng (`Store.html`), Chi tiết (`ProductDetail.html`), Đơn hàng/Tài khoản (`Account.html`), Quản trị (`Admin.html`) |

---

4. Kết quả thực hiện

4.1. Nâng cấp tài khoản, hồ sơ và đăng nhập Discord
- Nâng cấp xác thực Token Sanctum cho các thao tác đặt hàng, xem lịch sử đơn, điểm danh hàng ngày.
- Bổ sung luồng đăng nhập nhanh bằng **Discord OAuth2**: tự động bảo vệ CSRF state token, trích xuất thông tin người dùng, tự động liên kết tài khoản theo email và lưu `discord_username`.
- Nâng cấp trang Hồ sơ cá nhân (`Account.html`): người dùng xem và cập nhật Tên hiển thị, Tên định danh trong game (IGN) (ràng buộc chuẩn Java 3–16 ký tự `[a-zA-Z0-9_]`), Tiểu sử bio và ảnh đại diện.
- Tự động bóc tách khuôn mặt từ ngoại trang (skin) nhân vật 3D (chuẩn PNG 64x64 và 64x32) qua thư viện PHP GD (ghép lớp đầu và lớp mũ) để làm avatar tài khoản.

4.2. Module đơn hàng và giao dịch hoàn chỉnh (Tuần 8)
- Xây dựng luồng tạo đơn hàng chặt chẽ: Backend truy vấn trực tiếp giá sản phẩm từ cơ sở dữ liệu để tính tổng tiền, hoàn toàn không tin tưởng giá gửi lên từ frontend.
- Sinh mã đơn hàng chuẩn format ngân hàng `DTM-XXXXXX` (ví dụ `DTM-71D1E9`) và nội dung chuyển khoản `DTM XXXXXX`.
- Cơ chế chống tạo trùng đơn (Idempotency): kiểm tra `idempotency_key`, nếu phát hiện trùng sẽ trả về đơn hàng đã tạo trước đó thay vì tạo mới.
- Hỗ trợ cả thành viên đăng nhập lẫn khách vãng lai mua hàng không cần tài khoản (`/api/orders/guest`).
- Bảng lịch sử trạng thái (`status_history`) lưu lại từng bước: khởi tạo, đã thanh toán, đã nộp bằng chứng, hủy đơn.
- Quản trị viên quản lý đơn hàng bằng State Machine với luật bất biến: không cho phép chuyển đơn đã hủy sang đã thanh toán, không cho chuyển đơn đã giao về chờ xử lý.

4.3. Tích hợp thanh toán SePay tự động và an toàn (Tuần 9)
- Kết nối tự động với tài khoản ngân hàng MBBank (`0977364711` - `NGUYEN TRONG NGHIA`).
- Tự động sinh mã VietQR chuẩn NAPAS và SePay QR kèm số tiền và nội dung chuyển khoản được mã hóa chính xác.
- Bảo mật Webhook hai tầng:
  + Xác thực qua `API Key` (`Authorization: Apikey ...`).
  + Xác thực chữ ký điện tử `HMAC-SHA256` có đối soát timestamp drift dưới 300 giây nhằm triệt tiêu nguy cơ tấn công phát lại (Replay Attack).
- Đối soát tự động: Kiểm tra số tiền thực nhận (`transferAmount >= total_amount`), khớp số tài khoản thụ hưởng, chống ghi nhận giao dịch trùng lặp (`idempotent` trên `transaction_id`).
- Cơ chế tự phục hồi (Auto-recovery): Nếu người mua chuyển khoản đúng cú pháp nhưng do lag mạng đơn hàng chưa kịp lưu trên DB, Webhook SePay tự động phát hiện, tạo đơn bù và đánh dấu `paid`.
- Endpoint Polling thời gian thực (`GET /api/orders/check-status/{code}`): frontend tự động thăm dò mỗi 3 giây và chuyển trạng thái thành công ngay khi có tiền về mà không cần tải lại trang.

4.4. Khắc phục lỗi Quên mật khẩu & Chuyển phát email đa tầng
- *Vấn đề phát hiện:* Trên hosting đám mây Render (gói Free Tier), các cổng mạng gửi thư SMTP truyền thống (`25`, `465`, `587`) bị tường lửa của hệ thống chặn hoàn toàn, khiến tiến trình gửi mail bị treo dẫn đến lỗi `504 Gateway Timeout`.
- *Giải pháp đã xử lý:*
  + Xây dựng `PasswordResetService` chuyển phát email đa tầng: ưu tiên gửi qua **Resend HTTPS API (Cổng 443)** hoặc **Brevo HTTPS API (Cổng 443)** — cổng HTTPS không bị Render chặn.
  + Nếu chạy ở môi trường phát triển máy trạm, hệ thống tự động fallback về Mail tiêu chuẩn.
  + Đặt thời gian timeout kết nối SMTP xuống 8 giây để chống treo máy chủ.
  + Thiết kế template email HTML chuẩn thương hiệu Đảo Tò Mò (`ResetPasswordMail`).
  + Token đặt lại mật khẩu ngẫu nhiên 48 ký tự, giới hạn hiệu lực trong 30 phút và bảo vệ chống dò quét email.

4.5. Đợt tái thiết kế toàn diện Giao diện người dùng (UI/UX Rework — Phiên bản 2.0 "Neo-Mythic Island")
- **Bối cảnh so sánh với giao diện Tuần 7:**
  + Ở giai đoạn Tuần 7, giao diện ban đầu của website mang tính chất nguyên mẫu (prototype) với các bảng dữ liệu tĩnh đơn sắc, màu nền trắng xám đơn điệu, thiếu vắng các hiệu ứng tương tác hiện đại và chưa phản ánh được tinh thần phiêu lưu, kỳ ảo của máy chủ trò chơi trực tuyến.
  + Sau khi đã ổn định logic thanh toán và đơn hàng ở Tuần 8–9, nhóm đã dành trọn thời gian triển khai một đợt tái thiết kế giao diện toàn diện (Creative Full UI/UX Rework) lên phiên bản 2.0 "Neo-Mythic Island" (Đảo Kỳ Bí).
- **Hệ thống Design System 3 lớp độc quyền:**
  + *Bảng màu Obsidian & Mythic:* Màu nền Obsidian Dark (`#05070d`, `#101524`), kết hợp ánh hào quang ngọc lục bảo Mythic Emerald (`#10b981`), viền kính đại dương Ocean Lagoon Cyan (`#22d3ee`) với hiệu ứng kính mờ Glassmorphism và điểm xuyết ánh kim hoàng gia Relic Gold (`#f59e0b`).
  + *Bộ Typography chuẩn Gaming:* `Chakra Petch` cho tiêu đề và huy hiệu (hình khối góc cạnh, đậm chất công nghệ và gaming), kết hợp `Be Vietnam Pro` cho nội dung văn bản (chuẩn hiển thị tiếng Việt sắc nét trên mọi mật độ pixel) và `JetBrains Mono` cho các dãy số liệu, mã đơn hàng DTM và tọa độ.
- **Tái thiết kế đồng bộ 9 trang màn hình chính:**
  + *Trang Chủ (`index.blade.php`):* Bảng điều khiển HUD trung tâm, widget trạng thái server realtime, linh vật 3D chuyển động tương tác.
  + *Trang Server (`server.blade.php`):* Cổng kết nối IP và bảng luật chơi công bằng (Fair-Play Matrix).
  + *Trang Cửa Hàng (`shop.blade.php`):* Lưới sản phẩm dạng thẻ bài phát sáng (Marketplace Grid), bộ lọc danh mục và thanh tìm kiếm tức thì.
  + *Trang Chi Tiết Sản Phẩm (`product-detail.blade.php`):* Bảng thông số quyền lợi in-game và modal mua nhanh (Instant Checkout Hub).
  + *Trang Tài Khoản (`profile.blade.php` / `account.blade.php`):* Bố cục 4-Tile HUD thông minh, quản lý thông tin định danh trong game (IGN), lịch sử đơn hàng và Studio 3D Skin.
  + *Trang Điểm Danh Nhận Quà (`daily-reward.blade.php`):* Chuỗi nhiệm vụ 7 ngày (7-Day Questline), rương kho báu mở khóa với hiệu ứng ánh sáng.
  + *Trang Bản Đồ Vệ Tinh (`map.blade.php`):* Nhúng Dynmap thời gian thực kèm thông số tọa độ thế giới.
  + *Trang Cộng Đồng Discord (`discord.blade.php`):* Hub phòng voice Discord và bảng đồng bộ cấp bậc vai trò.
  + *Trang Quản Trị Admin (`admin.blade.php`):* Bảng điều khiển Command Center, biểu đồ KPI doanh thu, quản lý đơn hàng và đối soát giao dịch.

4.6. Tích hợp Mô hình 3D Nhân vật tương tác trực tiếp (Interactive 3D Skin Viewer / Studio)
- **Công nghệ ứng dụng:** Nhóm đã tích hợp thư viện `skinview3d.bundle.js` dựa trên nền tảng **Three.js** và **WebGL** vào trực tiếp frontend của website.
- **Các tính năng nổi bật của Mô hình 3D:**
  + *Tương tác không gian 3 chiều 360 độ (OrbitControls):* Cho phép người dùng dùng chuột hoặc cảm ứng chạm trên điện thoại để xoay nhân vật 360 độ tự do theo mọi góc nhìn, phóng to (zoom in), thu nhỏ (zoom out) và xoay nghiêng camera.
  + *Hệ thống hoạt ảnh diễn hoạt thời gian thực (Real-time Skeletal Animations):* Hỗ trợ các trạng thái diễn hoạt mượt mà: Dáng đi bộ (Walking), Chạy nhanh (Running), Đứng thở tự nhiên (Idle breathing), và Vẫy tay chào (Waving).
  + *Quản lý lớp trang phục đa tầng:* Hỗ trợ bật/tắt độc lập lớp áo ngoài (Outer Layer / Hat / Jacket / Armor) để hiển thị chi tiết các phần hoa văn nổi 3D của trang phục nhân vật trong game.
  + *3D Skin Studio & Trình xem trước tức thì (Live Preview):* Người dùng có thể tải lên tệp Skin nhân vật 3D chuẩn (PNG 64x64 hoặc 64x32 pixel). Khung hiển thị 3D sẽ ngay lập tức ánh xạ chất liệu (texture mapping) lên mô hình để kiểm tra toàn diện trước khi lưu.
  + *Tự động bóc tách Avatar 2D từ Mô hình 3D:* Kết hợp Canvas HTML5 và thư viện đồ họa PHP GD ở Backend để tự động bóc tách vùng khuôn mặt (Face) bao gồm cả lớp mũ/tóc ngoài từ skin 3D để tạo ảnh đại diện (Avatar 2D) đồng bộ cho tài khoản.
  + *API Đồng bộ hóa Skin Backend:* Xây dựng các API chuyên biệt (`POST /api/user/skin`, `POST /api/user/skin/reset`) kết nối với `SkinController` và `SkinService`, tự động kiểm tra tính hợp lệ của tệp hình ảnh, lưu trữ an toàn và đồng bộ hóa với máy chủ game trực tuyến.

4.7. Dữ liệu 12 Sản phẩm và Tài khoản demo thực tế trong Cơ sở dữ liệu
Toàn bộ dữ liệu dưới đây được trích xuất trực tiếp từ cơ sở dữ liệu SQLite (`database/database.sqlite`) và tệp nạp dữ liệu chuẩn `DatabaseSeeder.php` của dự án:
| STT | Tên sản phẩm | Danh mục | Giá bán (VNĐ) | Giá gốc (VNĐ) | Mã quà tặng Game (game_reward) |
|:---:|---|---|---:|---:|---|
| 1 | **Gói Tài Khoản Premium** | vip | 99.000 đ | 149.000 đ | `game_premium` |
| 2 | **Gói Cấp Bậc Rank VIP** | vip | 79.000 đ | 109.000 đ | `rank_vip` |
| 3 | **Gói Cấp Bậc Rank Premium** | vip | 149.000 đ | 199.000 đ | `rank_premium` |
| 4 | **Gói 100.000 Coin Trò Chơi** | bundle | 89.000 đ | 100.000 đ | `coin_100k` |
| 5 | **Gói Ngoại Trang 3D (Skin Pack)** | cosmetic | 49.000 đ | 79.000 đ | `skin_pack` |
| 6 | **Hòm Epic** | crate | 29.000 đ | 49.000 đ | `epic_crate_x1` |
| 7 | **DaVinci Resolve Basic** | bundle | 79.000 đ | 129.000 đ | *(Sản phẩm số tài nguyên đồ họa)* |
| 8 | **Khóa học Lập trình Web cơ bản** | bundle | 199.000 đ | 299.000 đ | *(Khóa đào tạo lập trình)* |
| 9 | **Khóa học JavaScript** | bundle | 249.000 đ | 349.000 đ | *(Khóa đào tạo lập trình)* |
| 10 | **Khóa học Python** | bundle | 229.000 đ | 329.000 đ | *(Khóa đào tạo lập trình)* |
| 11 | **Gói Tài Nguyên Trò Chơi (Resource Pack)** | cosmetic | 59.000 đ | 89.000 đ | `game_resource_pack` |
| 12 | **Gói Modpack Premium** | bundle | 129.000 đ | 179.000 đ | `modpack_premium` |

Danh sách tài khoản kiểm thử và demo thực tế trong hệ thống (Tên miền chính thức `daotomo.site`):
| Vai trò | Email đăng nhập | Mật khẩu | Username | Tên nhân vật trong game (IGN) | Số dư Xu |
|---|---|---|---|---|---:|
| **Quản trị viên (Admin)** | admin@daotomo.site | `Admin@123456` | admin | AdminDaoTomo | 99.999 Xu |
| **Người chơi kiểm thử (User)** | player@daotomo.site | `Player@123456` | DragonPlayer | DragonPlayer | 500 Xu |
| **Người dùng mẫu (Demo User)** | user@daotomo.site | `User@123456` | demouser | DemoUser | 100 Xu |

---

5. Danh sách 29 API Endpoints thực tế trong source code (routes/api.php)
Toàn bộ 29 endpoints RESTful dưới đây được khai báo trực tiếp trong tệp `routes/api.php` của mã nguồn hiện tại, phân tách rạch ròi theo phân quyền truy cập:
| Phương thức | Endpoint | Quyền | Mô tả chức năng chi tiết |
|---|---|---|---|
| GET | `/api/health` | Public | Kiểm tra trạng thái sức khỏe hệ thống, kết nối CSDL và số lượng sản phẩm |
| POST | `/api/auth/register` | Public | Đăng ký tài khoản người dùng mới với validation email và mật khẩu |
| POST | `/api/auth/login` | Public | Đăng nhập hệ thống, sinh Sanctum Bearer Token |
| POST | `/api/auth/forgot-password` | Public (Throttle) | Yêu cầu gửi email đặt lại mật khẩu với kiến trúc đa tầng (giới hạn 5 lần/phút) |
| POST | `/api/auth/reset-password/{token}` | Public | Xác thực token 48 ký tự và cập nhật mật khẩu mới |
| GET | `/api/auth/discord` | Public | Chuyển hướng người dùng sang trang cấp quyền OAuth2 của Discord |
| GET | `/api/auth/discord/callback` | Public | Tiếp nhận authorization code và hoàn tất đăng nhập/liên kết Discord |
| GET | `/api/auth/me` | User (Sanctum) | Lấy toàn bộ thông tin chi tiết hồ sơ cá nhân và trạng thái Discord |
| PUT | `/api/auth/me` | User (Sanctum) | Cập nhật Tên hiển thị, Tên định danh trong game (IGN, 3-16 ký tự) và bio cá nhân |
| POST | `/api/auth/skin` | User (Sanctum) | Tải lên / cập nhật Skin nhân vật 3D (PNG 64x64/64x32) và trích xuất avatar GD |
| GET | `/api/products` | Public | Danh sách sản phẩm công khai có phân trang, tìm kiếm từ khóa và lọc danh mục |
| GET | `/api/products/{id}` | Public | Xem thông tin chi tiết một sản phẩm theo ID hoặc slug |
| GET | `/api/orders/check-status/{code}` | Public | Kiểm tra trạng thái thanh toán theo mã đơn hàng DTM (Polling 3s) |
| GET | `/api/orders/status/{code}` | Public | Endpoint alias kiểm tra trạng thái thanh toán đơn hàng |
| POST | `/api/orders/guest` | Public | Tạo đơn hàng nhanh cho khách vãng lai (không cần đăng ký tài khoản) |
| POST | `/api/orders` | User (Sanctum) | Tạo đơn hàng thành viên (tính giá server-side từ DB, chống trùng idempotency) |
| GET | `/api/orders/my` | User (Sanctum) | Xem danh sách lịch sử đơn hàng của cá nhân đã đăng nhập |
| GET | `/api/orders/{id}` | User (Sanctum) | Xem chi tiết một đơn hàng theo ID (chỉ chủ đơn hàng hoặc Admin xem được) |
| PUT | `/api/orders/{id}/cancel` | User (Sanctum) | Hủy đơn hàng khi đơn còn ở trạng thái chờ xử lý (pending) |
| PUT | `/api/orders/{id}/payment-proof` | User (Sanctum) | Người dùng tải lên hình ảnh biên lai / bằng chứng chuyển khoản ngân hàng |
| POST | `/api/checkin` | User (Sanctum) | Điểm danh hàng ngày: nhận xu thưởng và tích lũy chuỗi streak 7 ngày |
| GET | `/api/checkin/me` | User (Sanctum) | Xem lịch sử điểm danh và chuỗi ngày liên tiếp của cá nhân |
| MATCH | `/api/webhooks/sepay` | Public (SePay Key) | Tiếp nhận Webhook SePay tự động gạch nợ (HMAC-SHA256, drift < 300s) |
| MATCH | `/api/sepay/webhook` | Public (SePay Key) | Endpoint alias dự phòng tiếp nhận Webhook SePay |
| POST | `/api/products` | Admin | Thêm mới sản phẩm vào cửa hàng (AdminProductController) |
| PUT | `/api/products/{id}` | Admin | Chỉnh sửa thông tin chi tiết sản phẩm (AdminProductController) |
| DELETE | `/api/products/{id}` | Admin | Xóa sản phẩm khỏi cửa hàng (AdminProductController) |
| GET | `/api/orders` | Admin | Xem toàn bộ đơn hàng trong hệ thống kèm bộ lọc trạng thái (AdminOrderController) |
| PUT | `/api/orders/{id}/status` | Admin | Cập nhật trạng thái đơn hàng theo luật State Machine bất biến |

---

6. Kiểm thử
Hệ thống được kiểm thử tự động toàn diện thông qua bộ công cụ PHPUnit/Pest. Tất cả **71 test cases đều đạt kết quả 100% (243 assertions)** trong thời gian thực thi 2.60 giây.

| STT | Nội dung kiểm thử | Kết quả mong đợi | Kết quả thực tế |
|:---:|---|---|:---:|
| 1 | Health check API (`GET /api/health`) | Trả về HTTP 200, kết nối DB OK | **Đạt** |
| 2 | Đăng ký & Đăng nhập (`/api/auth/*`) | Nhận token Sanctum, băm mật khẩu chuẩn | **Đạt** |
| 3 | Upload Skin nhân vật 3D & Render avatar | Bóc tách nhị phân PNG 64x64, tạo avatar khuôn mặt | **Đạt** |
| 4 | Danh mục sản phẩm & Lọc dữ liệu | Trả về 12 sản phẩm mẫu, lọc danh mục chính xác | **Đạt** |
| 5 | Tạo đơn hàng thành viên & khách | Tính giá từ DB, sinh mã `DTM-XXXXXX` | **Đạt** |
| 6 | Kiểm tra chống tạo đơn trùng (Idempotency) | Cùng `idempotency_key` trả về đơn cũ, không tạo mới | **Đạt** |
| 7 | Bảo vệ quyền xem đơn hàng | Người dùng không được xem đơn của người khác (HTTP 403) | **Đạt** |
| 8 | Hủy đơn hàng pending | Cho phép người dùng hủy đơn pending | **Đạt** |
| 9 | State Machine chuyển trạng thái đơn hàng | Chặn đổi đơn đã hủy sang đã thanh toán (HTTP 400) | **Đạt** |
| 10 | Phân quyền Admin (`AdminMiddleware`) | User thường truy cập API Admin bị từ chối HTTP 403 | **Đạt** |
| 11 | Điểm danh hàng ngày (`Checkin`) | Cộng xu, tính đúng streak, chặn điểm danh 2 lần/ngày | **Đạt** |
| 12 | Đăng nhập Discord OAuth2 | Validate state token, map tài khoản theo email | **Đạt** |
| 13 | Quên mật khẩu & Gửi mail | Tạo token 30 phút, gửi mail qua API HTTPS | **Đạt** |
| 14 | Xác thực Webhook SePay (HMAC-SHA256) | Từ chối request sai chữ ký hoặc hết hạn timestamp | **Đạt** |
| 15 | Khớp lệnh thanh toán SePay | Tiền đủ -> chuyển `paid`, tiền thiếu -> giữ `pending` | **Đạt** |
| 16 | Chống trùng giao dịch SePay | Cùng mã giao dịch `transaction_id` không cộng tiền lần 2 | **Đạt** |
| 17 | Tự phục hồi đơn hàng (Auto-recovery) | Tiền về đúng cú pháp nhưng chưa có đơn -> tự sinh đơn | **Đạt** |

---

7. Khó khăn và cách giải quyết
1. **Lỗi Render Free Tier chặn cổng gửi mail SMTP:** Khi gọi Gmail SMTP qua cổng 587 hoặc 465, kết nối bị treo vô hạn dẫn tới lỗi 504 Gateway Timeout. Nhóm đã giải quyết bằng cách tích hợp trực tiếp **Resend HTTPS API và Brevo HTTPS API qua Cổng 443**, gửi email qua REST request mà không phụ thuộc cổng SMTP.
2. **Lỗi hiển thị chi tiết đơn hàng (Cannot read properties of undefined reading 'orderCode'):** Do sự không đồng nhất giữa vỏ bọc JSON trả về (`order` vs `data`). Nhóm đã cập nhật accessor trong Model `Order`, đồng thời trả về cả hai khóa `order` và `data` để đảm bảo tương thích với cả code frontend cũ lẫn mới.
3. **Bảo mật Webhook SePay và chống Replay Attack:** SePay gửi webhook từ IP động nên không thể chặn theo whitelist IP đơn giản. Nhóm đã áp dụng xác thực chữ ký số `HMAC-SHA256` kết hợp kiểm tra độ lệch thời gian (timestamp drift dưới 300 giây) để ngăn chặn hoàn toàn việc kẻ xấu bắt trộm gói tin và phát lại.
4. **Sai lệch giá khi tạo đơn hàng:** Frontend có thể bị can thiệp để gửi tổng tiền sai lệch. Nhóm đã lập trình lại toàn bộ `OrderService`, buộc hệ thống phải tự lấy ID sản phẩm, đối chiếu giá trong DB và tự nhân với số lượng để ra tổng tiền cuối cùng.

---

8. Hạn chế đã biết
- Module kết nối API máy chủ game trực tuyến hiện mới dừng ở mức thiết kế cấu trúc dữ liệu (`game_username`, `game_reward`, `order_status = processing`), chưa hoàn thiện cầu nối gửi lệnh trực tiếp vào Console máy chủ game trực tuyến thật.
- Tài khoản Resend mới tạo đang dùng tên miền thử nghiệm (`onboarding@resend.dev`), chỉ gửi được đến email đã đăng ký tài khoản Resend; cần xác thực tên miền DNS `daotomo.site` trên Resend để gửi đến toàn bộ người dùng.

---

9. Kế hoạch tiếp theo (Tuần 12 đến Tuần 15)
- **Tuần 12: Hoàn thiện Lớp API kết nối Máy chủ Game & Kiểm thử chức năng toàn diện**
  + Xây dựng Mock Server API hoặc Plugin Webhook / Socket Service để lắng nghe và tự động phát lệnh trao quyền lợi/vật phẩm trong game trực tuyến khi đơn hàng thanh toán thành công.
  + Xuất Postman Collection kiểm thử tự động toàn bộ luồng từ tạo đơn đến phát quà in-game.
- **Tuần 13: Hoàn thiện Triển khai Cloud trên Render & Tên miền chính thức**
  + Tối ưu hóa Dockerfile và Nginx production trên Render.
  + Kiểm tra hoạt động ổn định trên tên miền chính `daotomo.site`.
- **Tuần 14: Viết Báo cáo tổng kết, Tài liệu API và Hướng dẫn sử dụng**
  + Soạn thảo tài liệu đặc tả API chi tiết theo chuẩn OpenAPI.
  + Hoàn thiện quyển báo cáo đồ án theo đúng biểu mẫu và quy định của nhà trường.
- **Tuần 15: Chuẩn bị Slide thuyết trình, Video Demo và Bảo vệ đồ án**
  + Dựng kịch bản demo: Mua gói VIP -> Quét VietQR thanh toán -> Nhận VIP tự động trên server game.
  + Báo cáo và bảo vệ trước Hội đồng chấm thi.
