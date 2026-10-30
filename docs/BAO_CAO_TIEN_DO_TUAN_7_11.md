# BÁO CÁO TIẾN ĐỘ ĐỒ ÁN (TỪ TUẦN 7 ĐẾN TUẦN 11)

**Học kỳ:** 3 | **Năm học:** 2025 - 2026 | **Lớp:** 25C2 - LTM1  
**Khoa / Ngành:** Lập trình máy tính | **Khóa:** 2025  

---

## THÔNG TIN CHUNG

### 1. Nhóm sinh viên thực hiện
1. **Nguyễn Trọng Nghĩa** — MSSV: `25002449` — Lớp: `25C2-LTM1` (Tỷ lệ đóng góp: 50%)  
   *Nhiệm vụ chính:* Phân tích yêu cầu; thiết kế backend; xây dựng API; xác thực/phân quyền; tích hợp SePay; xử lý đơn hàng/giao dịch; tích hợp API máy chủ game/mock server.
2. **Tống Hoàng Anh Tuấn** — MSSV: `25002461` — Lớp: `25C2-LTM1` (Tỷ lệ đóng góp: 50%)  
   *Nhiệm vụ chính:* Thiết kế giao diện; xây dựng frontend/Blade/JavaScript; trang quản trị, sản phẩm, đơn hàng, tài khoản; hiển thị trạng thái giao dịch; kiểm thử giao diện và UX.

### 2. Thông tin đề tài
* **Tên đề tài đăng ký chính thức:**  
  **XÂY DỰNG NỀN TẢNG WEB QUẢN TRỊ TẬP TRUNG VÀ TÍCH HỢP API CHO MÁY CHỦ TRÒ CHƠI TRỰC TUYẾN**
* **Tên dự án thực tế:** Đảo Tò Mò (Minecraft Community & E-commerce Platform — `daotomo.site`)
* **Lĩnh vực:** Web Application, Management Information System.
* **Giai đoạn báo cáo:** Từ Tuần 7 đến hết Tuần 11 (Kế hoạch tổng thể 15 tuần).

---

## PHẦN A. KẾT QUẢ ĐỐI CHIẾU TÀI LIỆU VÀ KẾ HOẠCH ĐĂNG KÝ

Căn cứ theo **Phiếu Đăng Ký Đề Tài** chính thức của nhóm, kế hoạch thực hiện sơ bộ từ Tuần 7 đến Tuần 11 được quy định nguyên văn như sau:

| Tuần | Giai đoạn theo Phiếu đăng ký | Kết quả dự kiến theo Phiếu đăng ký |
|---|---|---|
| **Tuần 7** | Lập trình: Sản phẩm/dịch vụ | Module sản phẩm hoàn chỉnh. |
| **Tuần 8** | Lập trình: Đơn hàng và giao dịch | Module đơn hàng/giao dịch hoàn chỉnh. |
| **Tuần 9** | Tích hợp SePay (webhook, chống trùng) | Luồng thanh toán hoàn chỉnh, an toàn. |
| **Tuần 10**| Xây dựng API máy chủ game/mock server | Lớp API vận hành, tài liệu API. |
| **Tuần 11**| Tích hợp toàn hệ thống | Hệ thống tích hợp end-to-end. |

*(Các tuần còn lại: Tuần 12 Kiểm thử; Tuần 13 Triển khai Cloud Render; Tuần 14 Viết báo cáo & tài liệu; Tuần 15 Demo & bảo vệ).*

---

## PHẦN B. BÁO CÁO KIỂM TOÁN SOURCE CODE VÀ TIẾN ĐỘ THỰC TẾ

### 1. Hiện trạng tại mốc bắt đầu (Đầu Tuần 7)
* **Khung công nghệ ban đầu:** 
  Dự án được khởi tạo ở giai đoạn trước Tuần 7 với kiến trúc nguyên mẫu Node.js/Express kết hợp MongoDB (`server.js`, `controllers/authController.js`, `models/Order.js`).
* **Trạng thái chức năng ở mốc Tuần 7:**
  * Giao diện HTML/CSS/JS tĩnh đã có khung cơ bản các trang: Trang chủ, Máy chủ (`Server.html`), Bản đồ (`Map.html`), Cửa hàng (`Store.html`), Tài khoản (`Account.html`), Discord (`Discord.html`).
  * Module sản phẩm ở mức khởi tạo dữ liệu mẫu với 12 sản phẩm cơ bản trong file `seed.js`.
  * Chưa có luồng xử lý đơn hàng hoàn chỉnh, chưa có cổng thanh toán tự động, chưa có cơ chế kiểm tra tính nhất quán dữ liệu.

### 2. Quá trình phát triển từ Tuần 7 đến Tuần 11

#### a) Bước chuyển đổi kiến trúc công nghệ quan trọng (Tuần 8 - Tuần 9)
* **Quyết định kỹ thuật:** Trong quá trình phát triển Tuần 8–9, nhóm đã quyết định chuyển đổi toàn bộ backend sang **PHP 8.2+ và Framework Laravel 11.31** (Commit `1df8382` ngày 19/10/2026).
* **Lý do kỹ thuật:** Laravel 11 cung cấp sẵn các cơ chế bảo mật cấp doanh nghiệp: Laravel Sanctum (Token Auth), Database Transaction ACID, Eloquent ORM chống SQL Injection, Form Request validation, bảo vệ CSRF và hệ thống kiểm thử tự động mạnh mẽ (PHPUnit/Pest).
* **Tính tương thích:** Hệ thống vẫn giữ cơ chế tương thích dữ liệu MongoDB ban đầu thông qua việc ép kiểu và trường `_id` ở mức API để frontend JavaScript (`api.js`) không bị gián đoạn.

#### b) Module Sản phẩm & Dịch vụ (Tuần 7)
* Đã xây dựng hoàn chỉnh Model `Product`, migration và controller catalog.
* Hỗ trợ tìm kiếm, phân trang, lọc theo danh mục (`vip`, `item`, `coins`, `pack`), cờ trạng thái `in_stock`, `is_active`, `featured`.
* Tích hợp trường `game_reward` phục vụ việc gán lệnh trao quà trên máy chủ trò chơi.
* Phân quyền quản trị: Các thao tác POST/PUT/DELETE sản phẩm được bảo vệ nghiêm ngặt bằng `AdminMiddleware`.

#### c) Module Đơn hàng & Giao dịch (Tuần 8)
* Đã xây dựng Model `Order`, `OrderItem`, `PaymentTransaction` với quan hệ dữ liệu chặt chẽ.
* **Bảo mật giá tiền:** Backend truy vấn trực tiếp giá từ cơ sở dữ liệu để tính tổng tiền (`total_amount`), hoàn toàn không tin tưởng giá trị tiền do client gửi lên.
* **Tính lũy thừa (Idempotency):** Hỗ trợ `idempotency_key` qua HTTP Header hoặc request body, ngăn chặn việc người dùng click đúp tạo nhiều đơn hàng trùng lặp.
* **Hỗ trợ khách vãng lai:** Hỗ trợ tạo đơn hàng nhanh cho cả thành viên đăng nhập lẫn khách chưa có tài khoản (`/api/orders/guest`).
* **Sinh mã đơn hàng thông minh:** Tự động sinh mã tham chiếu định dạng ngân hàng `DTM-XXXXXX` (ví dụ: `DTM-4A2B1C`).

#### d) Tích hợp Cổng thanh toán SePay (Tuần 9 — Hoàn thành xuất sắc)
* Đã kết nối tự động với tài khoản ngân hàng MBBank (`0977364711` - `NGUYEN TRONG NGHIA`).
* Tự động sinh mã VietQR chuẩn NAPAS và SePay QR kèm nội dung chuyển khoản chính xác `DTM XXXXXX`.
* **Bảo mật Webhook:**
  * Xác thực qua `API Key` (`Authorization: Apikey ...`).
  * Xác thực chữ ký số mã hóa `HMAC-SHA256` với cơ chế kiểm tra độ lệch thời gian (Timestamp drift check) nhằm triệt tiêu nguy cơ tấn công phát lại (Replay Attack).
* **Đối soát tự động:** Tự động đối chiếu số tiền thực nhận (`transferAmount`) với số tiền đơn hàng, kiểm tra tài khoản thụ hưởng.
* **Cơ chế tự phục hồi (Auto-recovery):** Trường hợp khách chuyển khoản thành công nhưng đơn hàng trên web bị lỗi mạng chưa kịp lưu vào DB, Webhook SePay tự động phát hiện, tạo đơn bổ sung và đánh dấu `PAID`.
* **Polling thời gian thực:** Cung cấp endpoint công khai `GET /api/orders/check-status/{code}` giúp màn hình thanh toán tự động chuyển trạng thái thành công ngay khi tiền về tài khoản.

#### e) Tái cấu trúc chuẩn hóa kiến trúc chuyên nghiệp (Refactoring)
* Để mã nguồn đạt chuẩn công nghiệp, nhóm đã tái cấu trúc toàn bộ cây thư mục theo mô hình **Domain-Oriented Architecture**:
  * Tách rời `AuthController` và `OrderController` cồng kềnh thành các Controller chuyên trách: `Auth`, `PasswordReset`, `DiscordOAuth`, `Profile`, `Skin`, `Order`, `AdminOrder`, `Product`, `AdminProduct`, `Payment`.
  * Tách toàn bộ Business Logic vào tầng Domain Services độc lập: `SepayService`, `OrderService`, `PasswordResetService`, `DiscordOAuthService`, `SkinService`, `CheckinService`.
  * Đưa toàn bộ việc kiểm tra dữ liệu ra các `FormRequest` kế thừa `BaseApiRequest` để chuẩn hóa định dạng lỗi JSON 422/400.
  * Tối ưu hóa serialization qua `UserResource`, `OrderResource`.
  * Giữ các Controller gốc làm **Facade Adapters** đảm bảo 100% tương thích ngược với hệ thống test và route cũ.

#### f) Kết quả kiểm thử tự động (Testing Evidence)
* Hệ thống sở hữu bộ kiểm thử tự động toàn diện với **71/71 Tests PASS (243 assertions)** chạy trong 2.6 giây:
  * `Tests\Unit\OrderServiceTest`: 3 test cases kiểm tra tính giá, kiểm tra hàng tồn, state machine chống chuyển trạng thái đơn sai luật.
  * `Tests\Unit\PasswordResetServiceTest`: 3 test cases kiểm tra mã token, bảo vệ chống dò quét email, kiểm tra độ dài mật khẩu.
  * `Tests\Unit\SkinServiceTest`: 4 test cases kiểm tra định dạng ảnh PNG, bóc tách nhị phân IHDR, reset về skin mặc định Steve.
  * `Tests\Feature\ApiTest`: 42 test cases bao phủ toàn bộ luồng Auth, Profile, Skin, Đơn hàng, Điểm danh, Discord OAuth, Quên mật khẩu.
  * `Tests\Feature\SepayWebhookTest`: 17 test cases kiểm tra xác thực Webhook, chữ ký HMAC, Replay attack, thanh toán thiếu tiền, idempotency, auto-recovery.

---

## PHẦN C. BẢNG ĐỐI CHIẾU KẾ HOẠCH TUẦN 7–11 SO VỚI TIẾN ĐỘ THỰC TẾ

| Tuần | Nhiệm vụ theo Phiếu đăng ký | Trạng thái ở mốc đầu Tuần 7 | Công việc phát triển thực tế (Tuần 7 – Tuần 11) | Bằng chứng kiểm tra mã nguồn & Kiểm thử | Kết luận |
|:---:|---|---|---|---|:---:|
| **Tuần 7** | **Lập trình: Sản phẩm/dịch vụ** *(Module sản phẩm hoàn chỉnh)* | Có 12 sản phẩm mẫu trong `seed.js` trên backend Node.js cũ; chưa có API CRUD phân quyền. | • Thiết kế Model `Product`, Migration 2026_10_19_000001.<br>• Xây dựng `ProductController` & `AdminProductController`.<br>• Hỗ trợ lọc danh mục, tìm kiếm, tồn kho, `game_reward`.<br>• Tách quyền Admin qua `AdminMiddleware`. | • `app/Models/Product.php`<br>• `Api/Product/ProductController.php`<br>• Test: `test_get_products_list` (PASS) | **Hoàn thành 100%** |
| **Tuần 8** | **Lập trình: Đơn hàng và giao dịch** *(Module đơn hàng/giao dịch hoàn chỉnh)* | Chưa có module đơn hàng; dữ liệu giao dịch chưa được lưu trữ. | • Xây dựng Models `Order`, `OrderItem`, `PaymentTransaction`.<br>• Tính giá tại backend từ database.<br>• Xây dựng `OrderService`, sinh mã `DTM-XXXXXX`.<br>• Triển khai cơ chế Idempotency chống tạo đơn trùng.<br>• Hỗ trợ đơn hàng thành viên và khách vãng lai. | • `app/Services/Order/OrderService.php`<br>• `Api/Order/OrderController.php`<br>• Tests: `test_order_creation_*`, `test_order_idempotency_*` (PASS) | **Hoàn thành 100%** |
| **Tuần 9** | **Tích hợp SePay (webhook, chống trùng)** *(Luồng thanh toán hoàn chỉnh, an toàn)* | Chưa có tích hợp cổng thanh toán trực tuyến. | • Kết nối SePay Webhook ngân hàng MBBank.<br>• Sinh mã VietQR động & SePay QR.<br>• Xác thực API Key & chữ ký `HMAC-SHA256` có timestamp chống Replay attack.<br>• Chống trùng lặp giao dịch (`idempotent`).<br>• Tự động khôi phục đơn hàng hợp lệ (`auto-recovery`). | • `app/Services/SepayService.php`<br>• `Api/Payment/SepayWebhookController.php`<br>• 17 Tests chuyên sâu trong `SepayWebhookTest.php` (PASS 100%) | **Hoàn thành 100%** |
| **Tuần 10**| **Xây dựng API máy chủ game/mock server** *(Lớp API vận hành, tài liệu API)* | Chưa có liên kết giữa Web và máy chủ game Minecraft. | • Chuẩn bị trường `minecraft_username` trong User & Order.<br>• Validate tên in-game theo chuẩn Java Edition (3–16 ký tự).<br>• Bổ sung trường định danh vật phẩm game `game_reward`.<br>• Trạng thái đơn chuyển sang `processing` chờ phát quà. | • Cột `game_reward` trong `products`.<br>• Cột `minecraft_username` trong `orders`.<br>• Tài liệu đặc tả API tại `docs/ARCHITECTURE.md`. | **Đang thực hiện** *(Đã xong phần schema & web backend; đang thiết lập mock server/RCON bridge)* |
| **Tuần 11**| **Tích hợp toàn hệ thống** *(Hệ thống tích hợp end-to-end)* | Hệ thống phân mảnh giữa frontend và các dịch vụ rời rạc. | • Kết nối hoàn chỉnh luồng: Người dùng đặt hàng -> Thanh toán SePay -> Webhook đối soát -> Cập nhật trạng thái tức thì.<br>• Tích hợp Discord OAuth đăng nhập 1-click.<br>• Hệ thống upload Skin Minecraft 3D & tự bóc tách avatar.<br>• Hệ thống điểm danh hàng ngày tích xu. | • Luồng thanh toán thực tế trên `daotomo.site`.<br>• 71 Tests tích hợp toàn diện trên toàn hệ thống (PASS). | **Hoàn thành phần Web & Thanh toán** *(Đang tiến hành ghép nối với Game Server ở Tuần 11-12)* |

---

## PHẦN D. BÁO CÁO CHUYÊN ĐỀ KỸ THUẬT

### 1. Báo cáo Chuyên đề: Chức năng Thanh toán SePay (Đã hoàn thành)
* **Quy trình xử lý giao dịch:**
  1. *Khách hàng tạo đơn:* Khách chọn vật phẩm -> Backend kiểm tra tình trạng kho và tính tổng tiền thực tế từ DB -> Sinh mã đơn hàng `DTM-XXXXXX` -> Tạo bản ghi `orders` và `order_items` trong database transaction.
  2. *Hiển thị thanh toán:* Màn hình thanh toán hiển thị VietQR với số tiền và nội dung chuyển khoản được mã hóa chuẩn xác.
  3. *Tiếp nhận Webhook:* Khi khách quét mã chuyển tiền qua App ngân hàng, SePay gửi tín hiệu POST Webhook về endpoint `/api/webhooks/sepay`.
  4. *Xác thực an toàn:* Backend kiểm tra chữ ký HMAC-SHA256, kiểm tra timestamp trong phạm vi cho phép (dưới 300 giây) để loại bỏ tấn công phát lại.
  5. *Khớp lệnh & Cập nhật:* Hệ thống đối soát số tiền và tài khoản nhận, chuyển `payment_status` sang `paid`, chuyển `order_status` sang `processing`, lưu lịch sử vào `payment_transactions`.
* **Kết luận kiểm toán:** Chức năng thanh toán SePay đã được xây dựng hoàn chỉnh, có kiểm thử nghiêm ngặt (17 bài test bảo mật) và đã kết nối thành công với tài khoản ngân hàng thực tế.

### 2. Báo cáo Chuyên đề: Sửa lỗi Quên mật khẩu & Gửi email (Đã xử lý dứt điểm)
* **Hiện trạng sự cố:** Khi người dùng gửi yêu cầu đặt lại mật khẩu, giao diện thông báo gửi thành công nhưng hộp thư không nhận được email.
* **Nguyên nhân kỹ thuật chính:**
  * Môi trường máy chủ **Render (Gói Free)** thiết lập tường lửa chặn toàn bộ cổng gửi thư SMTP truyền thống (`Port 25, 465, 587`). Khi gọi thư viện mail của Laravel dùng Gmail SMTP, kết nối bị giữ treo vô hạn cho đến khi máy chủ báo lỗi `504 Gateway Timeout`.
  * Một số lỗi logic ban đầu không bắt lỗi timeout và trả về thông báo thành công vô điều kiện.
* **Biện pháp khắc phục đã triển khai trong mã nguồn:**
  1. *Cơ chế gửi thư đa tầng (Multi-transport Delivery):* Xây dựng `PasswordResetService` ưu tiên gửi qua **Resend HTTPS API (Port 443)** hoặc **Brevo HTTPS API (Port 443)**. Cổng HTTPS 443 hoàn toàn không bị Render chặn.
  2. *Cơ chế Fallback an toàn:* Nếu chạy ở môi trường phát triển local, hệ thống tự động fallback về Mail tiêu chuẩn.
  3. *Giới hạn thời gian kết nối:* Cấu hình `timeout` SMTP xuống 8 giây trong `config/mail.php` để chống treo hệ thống.
  4. *Bảo mật thông tin:* Áp dụng thông báo generic chống dò quét email tồn tại, token đặt lại mật khẩu ngẫu nhiên 48 ký tự hex với thời hạn 30 phút.
  5. *Template thư:* Tạo giao diện email HTML chuyên nghiệp chuẩn thương hiệu Đảo Tò Mò (`ResetPasswordMail`).
* **Trạng thái:** Mã nguồn đã hoàn thiện và kiểm thử đạt kết quả 100%. Sinh viên chỉ cần điền biến môi trường `RESEND_API_KEY` trên Render Dashboard để hoàn tất cấu hình ngoài đời thực.

### 3. Báo cáo Chuyên đề: Module Kết nối API Máy chủ Minecraft (Công việc hiện tại)
* **Mục tiêu của module:** Khi đơn hàng chuyển sang trạng thái đã thanh toán (`paid`), hệ thống sẽ gửi lệnh đến máy chủ game Minecraft để tự động trao vật phẩm (hoặc Rank/Xu) cho người chơi theo đúng `minecraft_username` và `game_reward`.
* **Hiện trạng trong source code:**
  * Đã hoàn thành 100% phần chuẩn bị dữ liệu tại Web Backend: Xác thực tên người chơi in-game, liên kết tài khoản Discord - Minecraft, định danh phần thưởng game trong bảng `products`.
  * Chưa có kết nối mạng trực tiếp đến server game (chưa có code socket RCON hoặc webhook gửi đến Minecraft Plugin).
* **Kế hoạch triển khai kỹ thuật tiếp theo:**
  1. *Lựa chọn kiến trúc kết nối:*
     * **Phương án A (RCON Protocol):** Backend Laravel sử dụng giao thức RCON gửi lệnh trực tiếp vào Console server game (ví dụ: `lp user {player} parent add vip` hoặc `give {player} diamond 64`).
     * **Phương án B (Spigot/Paper Plugin Webhook):** Viết plugin Minecraft nhỏ (Java) định kỳ gọi API của Web `GET /api/server/pending-rewards` để nhận danh sách phần thưởng cần phát, sau đó gửi xác nhận về Web.
     * **Phương án C (Mock Server API):** Xây dựng một mock server giả lập phản hồi của máy chủ game để phục vụ báo cáo và bảo vệ đồ án trong trường hợp máy chủ game đóng để bảo trì.
  2. *Cơ chế hàng đợi an toàn (Dispatch Queue):* Xây dựng bảng lưu vết phát quà, chống trùng lặp và hỗ trợ phát lại (retry) nếu người chơi đang offline lúc thanh toán.

---

## PHẦN E. KẾ HOẠCH THỰC HIỆN GIAI ĐOẠN TIẾP THEO (TUẦN 12 – TUẦN 15)

* **Tuần 12: Hoàn thiện Lớp API kết nối Máy chủ Game & Kiểm thử chức năng toàn diện**
  * Triển khai Mock Server / RCON listener cho máy chủ game Minecraft.
  * Xuất Postman Collection kiểm thử tự động toàn bộ luồng từ lúc đặt hàng, thanh toán SePay, đến khi phát lệnh sang game server.
* **Tuần 13: Hoàn thiện Triển khai Cloud trên Render**
  * Đồng bộ cơ sở dữ liệu production, kiểm tra tính ổn định của hệ thống container Docker trên Render.
  * Cấu hình tên miền chính thức `daotomo.site` kết nối liền mạch giữa Frontend Vercel và Backend Render.
* **Tuần 14: Soạn thảo Tài liệu Kỹ thuật, Hướng dẫn sử dụng & Báo cáo đồ án**
  * Hoàn thành tài liệu đặc tả API (OpenAPI / Swagger / Postman Docs).
  * Viết tài liệu hướng dẫn vận hành dành cho quản trị viên.
  * Hoàn thiện quyển báo cáo đồ án môn học theo đúng đề cương của nhà trường.
* **Tuần 15: Chuẩn bị Slide thuyết trình, Video Demo và Bảo vệ đồ án**
  * Dựng video demo kịch bản thực tế: Đăng nhập Discord -> Mua vật phẩm -> Quét VietQR thanh toán -> Nhận vật phẩm tự động trong game.
  * Báo cáo và bảo vệ trước Hội đồng chấm thi.

---

## PHẦN F. DANH SÁCH THÔNG TIN CẦN SINH VIÊN XÁC NHẬN

Để hoàn thiện bản in cuối cùng nộp cho Giảng viên hướng dẫn, sinh viên cần rà soát và xác nhận 3 điểm sau:
1. **Báo cáo sự thay đổi công nghệ cơ sở dữ liệu:** Trong phiếu đăng ký ban đầu, mục V ghi công nghệ cơ sở dữ liệu là `MongoDB` (tương ứng với bản clone Node.js ban đầu). Tại Tuần 8–9, nhóm đã chuyển đổi sang `PHP Laravel 11` (sử dụng SQLite cho dev và PostgreSQL trên Render production, có hỗ trợ trường `_id` tương thích MongoDB). Nhóm cần nêu rõ lý do chuyển đổi kiến trúc này để Giảng viên ghi nhận tính chủ động nâng cấp kỹ thuật của đề tài.
2. **Hình thức nghiệm thu API Máy chủ Game ở Tuần 12:** Nhóm dự kiến demo phát vật phẩm thật qua **Server Minecraft thật (qua RCON/Plugin)** hay sử dụng **Mock Server API** (giả lập theo đúng mục IV.1 STT 7 của phiếu đăng ký)?
3. **Biến môi trường email trên Render:** Nhóm xác nhận đã cấu hình API Key của dịch vụ Resend trên Render để chức năng quên mật khẩu hoạt động thực tế trên môi trường live.
