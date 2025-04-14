CREATE DATABASE IF NOT EXISTS pro1014_schema;

USE pro1014_schema;

-- 1) Bảng categories
CREATE TABLE IF NOT EXISTS categories (
    category_id INT NOT NULL AUTO_INCREMENT,
    category_name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active TINYINT(1) DEFAULT 1,
    PRIMARY KEY (category_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- 2) Bảng users
CREATE TABLE IF NOT EXISTS users (
    user_id INT NOT NULL AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NULL,
    email VARCHAR(100) NULL,
    phone_number VARCHAR(15) NULL,
    address VARCHAR(255) NULL,
    avatar_url VARCHAR(255) NULL,
    password_changed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    role ENUM('admin', 'user') DEFAULT 'user',
    is_active TINYINT(1) DEFAULT 1,
    PRIMARY KEY (user_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- 3) Bảng products
CREATE TABLE IF NOT EXISTS products (
    id INT NOT NULL AUTO_INCREMENT,
    product_name VARCHAR(255) NOT NULL,
    price DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    thumbnail TEXT NULL,
    short_description TEXT NULL,
    full_description LONGTEXT NULL,
    extra_info TEXT NULL,
    in_stock TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    brand VARCHAR(100) NULL,
    category_id INT NULL,
    is_active TINYINT(1) DEFAULT 1,
    PRIMARY KEY (id),
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories (category_id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- 4) Bảng product_images
CREATE TABLE IF NOT EXISTS product_images (
    id INT NOT NULL AUTO_INCREMENT,
    product_id INT NOT NULL,
    image_url TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_product_images_product FOREIGN KEY (product_id) REFERENCES products (id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- 6) Bảng banners
CREATE TABLE IF NOT EXISTS banners (
    id INT NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    image_url TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- 7) Bảng password_requests
CREATE TABLE IF NOT EXISTS password_requests (
    id INT NOT NULL AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL,
    new_password TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'done', 'rejected') DEFAULT 'pending',
    username VARCHAR(255) NOT NULL,
    PRIMARY KEY (id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- 8) Bảng carts
CREATE TABLE IF NOT EXISTS carts (
    id INT NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status ENUM('pending', 'completed') DEFAULT 'pending',
    PRIMARY KEY (id),
    CONSTRAINT fk_carts_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- 9) Bảng cart_items
CREATE TABLE IF NOT EXISTS cart_items (
    id INT NOT NULL AUTO_INCREMENT,
    cart_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    price DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_cart_items_cart FOREIGN KEY (cart_id) REFERENCES carts (id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_cart_items_product FOREIGN KEY (product_id) REFERENCES products (id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- 10) Bảng orders
CREATE TABLE IF NOT EXISTS orders (
    id INT NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    total_price DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    status ENUM(
        'pending',
        'delivered',
        'completed',
        'cancelled'
    ) DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- 11) Bảng order_items
CREATE TABLE IF NOT EXISTS order_items (
    id INT NOT NULL AUTO_INCREMENT,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    price DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (id),
    CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders (id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products (id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;