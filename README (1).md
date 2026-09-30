# ♻️ Circular Law Lab – Close the Loop (bản web online)

Board game kinh tế tuần hoàn cho sinh viên luật – chơi nhiều thiết bị, tối đa 6 người/phòng (+ khán giả).

## Cấu trúc
- `index.html` – toàn bộ giao diện + luật chơi (JS thuần, không cần build).
- `api.php` – máy chủ: đăng nhập, phân quyền, phòng chơi (PHP 7.4+, lưu file trong `data/`, tự tạo). Không cần database/Node.
- `config.js` – URL API (mặc định `api.php`). `config.php.example` – chỉ cần khi giao diện và API ở 2 domain khác nhau.

**Cơ chế đồng bộ:** mỗi phòng có một `seed` ngẫu nhiên + danh sách thao tác. Mọi thiết bị chạy cùng một bộ luật xác định và phát lại thao tác theo thứ tự → trạng thái luôn giống nhau; vào lại phòng (F5) sẽ tự khôi phục ván.

## 1. Đưa lên GitHub
```bash
cd circular-law-lab
git init && git add . && git commit -m "Circular Law Lab web"
git branch -M main
git remote add origin https://github.com/<tai-khoan>/circular-law-lab.git
git push -u origin main
```

## 2. Host lên Hostinger (gói hosting có PHP)
**Cách A – Git trong hPanel:** Websites → Manage → Git (tên menu có thể khác chút) → dán URL repo, branch `main`, thư mục cài đặt `public_html` (hoặc thư mục con, ví dụ `game`) → Deploy. Bật Auto deployment/webhook để mỗi lần `git push` tự cập nhật.
**Cách B – Thủ công:** nén `index.html` + `api.php` → File Manager → `public_html` → Extract.

Kiểm tra: mở `https://ten-mien/api.php` phải trả JSON `{"ok":0,"err":"Không tìm thấy phòng "}`. Nếu "Không ghi được thư mục data/", đặt quyền thư mục `data` = 755 (hoặc 775).
Nhớ bật SSL (HTTPS) để chơi ổn định trên điện thoại.

## 3. Tài khoản & phân quyền
- **Tài khoản đầu tiên đăng ký trên máy chủ tự động là `admin`** → hãy tự đăng ký ngay sau khi deploy.
- `admin` (Quản trị): toàn quyền – tạo/xóa người dùng, đổi quyền, đặt lại mật khẩu, bật/tắt tự đăng ký, xem và đóng mọi phòng.
- `host` (Quản trò/giảng viên): tạo phòng, loại người chơi, bắt đầu và đóng **phòng của mình**.
- `player` (Người chơi): vào phòng bằng mã, chơi ở vai trò của mình; chỉ thao tác được khi đến lượt. Người vào sau khi ván bắt đầu là khán giả.
- Mật khẩu băm bằng `password_hash`; phiên đăng nhập 7 ngày; khóa 5 phút nếu sai mật khẩu ≥5 lần. Máy chủ tự kiểm tra quyền ở mọi thao tác (không tin giao diện).
- Muốn chỉ cho người được cấp tài khoản chơi: Quản lý → bỏ chọn “Cho phép tự đăng ký”.

## 4. Chơi
Một người bấm **Tạo phòng online** → gửi mã 4 ký tự (hoặc link `?room=ABCD`) → bạn bè vào, chọn vai trò → chủ phòng 👑 bấm **Bắt đầu**.

## Giới hạn
- Thăm dò 1,2 giây/lần: phù hợp lớp học/CLB. Quy mô rất lớn nên chuyển sang WebSocket (Node) – cần gói Hostinger hỗ trợ Node.js.
- Luật chạy ở phía trình duyệt nên chưa chống gian lận; đủ cho bản thử nghiệm.
- Các dẫn chiếu pháp lý trong phần “Bạn có biết?” là tóm lược giáo dục – hãy đối chiếu văn bản hiện hành trước khi dùng chính thức.

## Xử lý “Lỗi kết nối máy chủ”
Bấm **🩺 Kiểm tra kết nối máy chủ** ở màn hình đăng nhập – app sẽ nói rõ nguyên nhân:
- *file://* → đừng mở trực tiếp file, hãy mở bằng URL của host.
- *HTTP 404 / không phải JSON* → `api.php` chưa nằm cùng thư mục với `index.html` (hoặc host là GitHub Pages, không chạy PHP).
- Web ở GitHub Pages + API ở Hostinger → sửa `config.js` thành URL đầy đủ của `api.php`, tạo `config.php` (từ `config.php.example`) khai báo origin GitHub Pages.
- *ghi dữ liệu: KHÔNG ĐƯỢC* → đặt quyền thư mục `data/` = 755/775. (Nếu không ghi được, máy chủ tự dùng thư mục tạm, nhưng dữ liệu có thể bị xóa.)
