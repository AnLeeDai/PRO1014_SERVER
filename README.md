# PRO1014 - Dự Án 1

## Giới thiệu

Dự án **PRO1014** bao gồm cả **frontend (client)** và **backend (server)**, được xây dựng để cung cấp một hệ thống website bán điện thoại.

## Cấu trúc thư mục

```plaintext
PRO1014/
│── client/                # Frontend project
│   ├── pages/             # Chứa các trang HTML
│   │   ├── users/
│   │   │   ├── users.html
│   ├── public/            # Chứa tài nguyên công khai (ảnh, font, v.v.)
│   ├── scripts/           # Chứa các tập lệnh JavaScript xử lý logic
│   │   ├── users/
│   │   │   ├── user.controller.js
│   │   │   ├── user.service.js
│   ├── index.html         # HTML chạy chính
│   ├── main.css           # Tập tin CSS chính
│   ├── tailwind.config.js # Cấu hình Tailwind CSS
│
│── server/                # Backend project
│   ├── app/
│   │   ├── controllers/   # Chứa controller xử lý request
│   │   │   ├── UserController.php
│   │   ├── models/        # Chứa model kết nối database
│   │   │   ├── User.php
│   │   ├── routes/        # Định nghĩa các route API
│   │   │   ├── api.php
│   ├── config/
│   │   ├── database.php   # Cấu hình database
│   ├── public/
│   │   ├── index.php      # Entry point của API
│
│── LICENSE
│── README.md
```

## Công nghệ sử dụng

- **Frontend (Client)**:

  - HTML, CSS, JavaScript
  - Tailwind CSS
  - JavaScript Modules

- **Backend (Server)**:
  - PHP (MVC Pattern)
  - MySQL
  - REST API

## Hướng dẫn cài đặt

1. **Clone dự án**

   git clone <git@github.com:AnLeeDai/PRO1014.git>
   cd PRO1014

3. **Cài đặt Frontend**

   cd client
   npm install -D tailwindcss
   npx tailwindcss init

4. **Cài đặt Backend**
   - Tạo database MySQL.
   - Cập nhật thông tin kết nối trong `server/config/database.php`.
   - Chạy server:
   - sử dụng laragon chạy public/index.php hoặc 
     php -S localhost:8000 -t server/public
5. **Chạy dự án**
   - Mở `client/index.html` trong trình duyệt hoặc chạy bằng VS Code Live Server.
   - Kiểm tra giao diện và API.

## Hướng dẫn phát triển

- **Frontend**:

  - Thêm trang mới vào `client/pages/`.
  - Thêm logic JavaScript vào `client/scripts/`.
  - Sử dụng Tailwind CSS để thiết kế UI.

- **Backend**:
  - Thêm API mới vào `server/routes/api.php`.
  - Viết controller trong `server/app/controllers/`.
  - Định nghĩa model trong `server/app/models/`.
- API routes:
  - Xem tài liệu API route trong `server/docs.md`.

## Liên hệ

Nếu có bất kỳ câu hỏi hoặc đóng góp nào, vui lòng liên hệ với ledaian22@gmail.com.

---

📌 **FPT Polytechnic - PRO1014**
