# PRO1014 - BE - Dự Án 1

## Giới thiệu

Dự án được xây dựng để cung cấp một hệ thống website bán điện thoại.

## Cấu trúc thư mục

```plaintext
PRO1014/
│── server/                # Backend project
│   ├── app/
│   │   ├── controllers/   # Chứa controller xử lý request
│   │   │   ├── UserController.php
│   │   ├── models/        # Chứa model kết nối database
│   │   │   ├── User.php
│   ├── config/
│   │   ├── database.php   # Cấu hình database
│   ├── routes/
│   │   ├── public.php      # Entry point của API với các route public
│   |   ├── private.php
│── LICENSE
│── README.md
```

## Công nghệ sử dụng

- **Backend (Server)**:
  - PHP (MVC Pattern)
  - MySQL
  - REST API

## Hướng dẫn cài đặt

1. **Clone dự án**

   git clone <git@github.com:AnLeeDai/PRO1014.git>
   cd PRO1014

2. **Cài đặt Backend**
   - Tạo database MySQL.
   - Cập nhật thông tin kết nối trong `server/config/database.php`.
   - Chạy server:
   - sử dụng laragon chạy public/index.php hoặc
     php -S localhost:8000 -t routes/public.php hoặc routes/private.php
3. **Chạy dự án**
   - Mở `routes/public.php` hoặc `routes/private.php`.

## Hướng dẫn phát triển

- **Backend**:
  - Thêm đường dẫn API mới vào `server/routes/api.php`.
  - Viết controller trong `server/app/controllers/`.
  - Định nghĩa model trong `server/app/models/`.
- API routes:
  - Xem tài liệu API route trong `docs.md`.

## Liên hệ

Nếu có bất kỳ câu hỏi hoặc đóng góp nào, vui lòng liên hệ với ledaian22@gmail.com.

---

📌 **FPT Polytechnic - PRO1014**
