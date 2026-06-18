# Ultimate WordPress Booster

Plugin tăng tốc WordPress sử dụng phương pháp **Static Page Cache** (Tạo bộ nhớ đệm trang tĩnh) kết hợp với **Sitemap Preloader** (Tự động tải trước liên kết), tương thích 100% với cấu hình Nginx tối ưu **Rocket-Nginx**.

## 🚀 Tính năng nổi bật

1. **Preload Cache qua Sitemap**:
   - Tự động phát hiện hoặc nhập sitemap XML của website.
   - Trích xuất toàn bộ URL trong sitemap và đưa vào hàng đợi preload (sử dụng bảng SQL tạm: `{wp_prefix}ultimate_wp_booster_queue`).
   - Cung cấp giao diện quản lý tiến trình thời gian thực (real-time progress bar) với các nút Bắt đầu, Tạm dừng và Xóa hàng đợi.
   - Hỗ trợ tải hàng loạt chạy ngầm qua WP-Cron để tránh quá tải CPU của máy chủ.

2. **Cấu hình tùy biến nâng cao**:
   - **Priority URLs (Đường dẫn ưu tiên)**: Cho phép nhập danh sách các URL quan trọng để ưu tiên cào trước (lần lượt nạp vào cache trước).
   - **Exclude URLs (Đường dẫn loại trừ)**: Cho phép nhập các đường dẫn (hỗ trợ Wildcard/RegEx) cần bỏ qua không bao giờ lưu cache (ví dụ: giỏ hàng, thanh toán, trang admin, v.v.).
   - **Cache Lifespan (Thời gian lưu cache)**: Dễ dàng cấu hình thời gian hiệu lực của cache (tính bằng Giờ).

3. **Tương thích hoàn toàn với Rocket-Nginx**:
   - Cache được lưu trữ dưới dạng tệp tĩnh `.html` và tệp nén sẵn `.html_gzip`.
   - Đường dẫn thư mục lưu trữ giống hệt WP Rocket: `wp-content/cache/wp-rocket/[domain]/[request_uri]/index-https.html`.
   - Giúp Nginx có thể phục vụ tệp tĩnh ngay lập tức bằng cấu hình của [Rocket-Nginx](https://github.com/satellitewp/rocket-nginx) mà không cần chạy PHP hay truy vấn Database.

4. **Tự động Cập nhật trực tiếp từ GitHub**:
   - Tích hợp trình cập nhật tự động từ kho lưu trữ public: `https://github.com/tuend-work/ultimate-wp-booster`.
   - Nút kiểm tra và cập nhật trực tiếp được hiển thị ngay tại tab **Cập nhật** trong trang cấu hình của plugin.

---

## 🛠️ Cài đặt & Cấu hình

1. Tải thư mục plugin `ultimate-wp-booster` lên thư mục `wp-content/plugins/` của trang web WordPress.
2. Kích hoạt plugin qua trang quản trị WordPress Admin.
3. Đi tới **Cài đặt > Ultimate WP Booster** để thiết lập sitemap, cấu hình thời gian lưu cache, danh sách loại trừ và thực hiện preloading.

### 🌐 Cấu hình Nginx (Tùy chọn nhưng khuyến khích)
Để tối ưu hóa tốc độ tải trang bằng cách phục vụ tệp tĩnh trực tiếp qua Nginx, hãy tích hợp cấu hình **Rocket-Nginx**:

1. Clone dự án Rocket-Nginx:
   ```bash
   cd /etc/nginx/
   git clone https://github.com/satellitewp/rocket-nginx.git
   ```
2. Tạo tệp cấu hình theo hướng dẫn của dự án và `include` nó trong block server Nginx của bạn:
   ```nginx
   include /etc/nginx/rocket-nginx/default.conf;
   ```
3. Khởi động lại Nginx:
   ```bash
   sudo systemctl restart nginx
   ```

---

## 📂 Cấu trúc thư mục mã nguồn

- [ultimate-wp-booster.php](file:///f:/DEV/ultimate-wp-booster/ultimate-wp-booster.php): Tệp khởi chạy chính của plugin.
- [github-updater.php](file:///f:/DEV/ultimate-wp-booster/github-updater.php): Trình cập nhật tự động của plugin thông qua GitHub Repository.
- [advanced-cache.php](file:///f:/DEV/ultimate-wp-booster/advanced-cache.php): File drop-in phục vụ và lưu cache rất sớm trước khi WP boot đầy đủ.
- [includes/class-uwb-activator.php](file:///f:/DEV/ultimate-wp-booster/includes/class-uwb-activator.php): Khởi tạo bảng cơ sở dữ liệu, ghi đè hằng số `WP_CACHE` và chuẩn bị thư mục cache khi kích hoạt.
- [includes/class-uwb-deactivator.php](file:///f:/DEV/ultimate-wp-booster/includes/class-uwb-deactivator.php): Dọn dẹp cron, xóa file drop-in và vô hiệu hóa cache khi tắt plugin.
- [includes/class-uwb-cache.php](file:///f:/DEV/ultimate-wp-booster/includes/class-uwb-cache.php): Bộ máy xử lý ghi, xóa và dọn dẹp cache tĩnh.
- [includes/class-uwb-preloader.php](file:///f:/DEV/ultimate-wp-booster/includes/class-uwb-preloader.php): Hệ thống phân tích sitemap, quản lý hàng đợi cơ sở dữ liệu và tải trang.
- [includes/class-uwb-admin.php](file:///f:/DEV/ultimate-wp-booster/includes/class-uwb-admin.php): Giao diện quản trị, lưu trữ cài đặt và quản lý tiến trình.