-- 1. Bảng users
CREATE TABLE users
(
    user_id      INT AUTO_INCREMENT PRIMARY KEY,
    username     VARCHAR(50),
    password     VARCHAR(255),
    full_name    VARCHAR(100),
    email        VARCHAR(100),
    phone_number VARCHAR(15),
    address      VARCHAR(255),
    avatar_url   VARCHAR(255),
    role         ENUM ('user', 'admin')
);

-- 2. Bảng categories
CREATE TABLE categories
(
    category_id   INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100)
);

-- 3. Bảng products
CREATE TABLE products
(
    product_id   INT AUTO_INCREMENT PRIMARY KEY,
    product_name VARCHAR(255),
    category_id  INT,
    description  TEXT,
    price        DECIMAL(10, 2),
    stock        INT,
    image_url    VARCHAR(255),
    FOREIGN KEY (category_id) REFERENCES categories (category_id)
);

-- 4. Bảng product_images
CREATE TABLE product_images
(
    product_image_id INT AUTO_INCREMENT PRIMARY KEY,
    product_id       INT,
    image_url        VARCHAR(255),
    FOREIGN KEY (product_id) REFERENCES products (product_id)
);

-- 5. Bảng cart
CREATE TABLE cart
(
    cart_id    INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT,
    created_at DATETIME,
    updated_at DATETIME,
    FOREIGN KEY (user_id) REFERENCES users (user_id)
);

-- 6. Bảng cart_items
CREATE TABLE cart_items
(
    cart_item_id INT AUTO_INCREMENT PRIMARY KEY,
    cart_id      INT,
    product_id   INT,
    quantity     INT,
    FOREIGN KEY (cart_id) REFERENCES cart (cart_id),
    FOREIGN KEY (product_id) REFERENCES products (product_id)
);

-- 7. Bảng discounts
CREATE TABLE discounts
(
    discount_id    INT AUTO_INCREMENT PRIMARY KEY,
    discount_code  VARCHAR(50),
    discount_desc  VARCHAR(255),
    discount_value DECIMAL(5, 2),
    start_date     DATE,
    end_date       DATE
);

-- 8. Bảng orders
CREATE TABLE orders
(
    order_id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id          INT,
    order_date       DATETIME,
    payment_method   VARCHAR(50),
    shipping_address VARCHAR(255),
    total_amount     DECIMAL(10, 2),
    status           VARCHAR(50),
    discount_id      INT,
    FOREIGN KEY (user_id) REFERENCES users (user_id),
    FOREIGN KEY (discount_id) REFERENCES discounts (discount_id)
);

-- 9. Bảng order_details
CREATE TABLE order_details
(
    order_id         INT,
    product_id       INT,
    quantity         INT,
    price            DECIMAL(10, 2),
    discount_applied DECIMAL(10, 2),
    PRIMARY KEY (order_id, product_id),
    FOREIGN KEY (order_id) REFERENCES orders (order_id),
    FOREIGN KEY (product_id) REFERENCES products (product_id)
);

-- 10. Bảng password_requests
CREATE TABLE password_requests
(
    id           INT AUTO_INCREMENT PRIMARY KEY,
    email        VARCHAR(255),
    new_password TEXT,
    created_at   DATETIME,
    status       ENUM ('pending', 'done'),
    username     VARCHAR(255)
);
