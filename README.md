# PRO1014 - Dự Án 1

**PRO1014** là môn học Dự Án 1 thuộc chương trình học tại FPT Polytechnic.

## Cấu trúc dự án

```plaintext
PRO1014/
│── server/
│   ├── app/
│   │   ├── controllers/   # Chứa các controller xử lý request
│   │   ├── models/        # Chứa các model kết nối database
│   │   ├── core/          # Chứa các file hệ thống (database, router, config)
│   │   ├── routes/        # Định nghĩa các route API
│   │   ├── index.php      # Entry point của API
│   ├── public/            # Chứa file index.php và các tệp public nếu cần
│   ├── config/            # Chứa cấu hình database và các settings khác
│   ├── .env               # Biến môi trường (database, secret key)
│   ├── composer.json      # Quản lý dependency (nếu dùng Composer)
│── client/                # Frontend project (React/Vue/... nếu có)
│── LICENSE
│── README.md
