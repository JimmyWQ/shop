SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(60) NOT NULL,
    email VARCHAR(160) NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(30) NOT NULL DEFAULT '',
    role VARCHAR(20) NOT NULL DEFAULT 'customer',
    status TINYINT NOT NULL DEFAULT 1,
    create_time DATETIME DEFAULT NULL,
    update_time DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(60) NOT NULL,
    slug VARCHAR(80) NOT NULL,
    description VARCHAR(255) NOT NULL DEFAULT '',
    cover VARCHAR(255) NOT NULL DEFAULT '',
    sort INT NOT NULL DEFAULT 0,
    status TINYINT NOT NULL DEFAULT 1,
    create_time DATETIME DEFAULT NULL,
    update_time DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_id INT UNSIGNED NOT NULL,
    name VARCHAR(160) NOT NULL,
    slug VARCHAR(180) NOT NULL,
    subtitle VARCHAR(255) NOT NULL DEFAULT '',
    description TEXT,
    highlights TEXT,
    specs TEXT,
    price DECIMAL(10, 2) NOT NULL DEFAULT 0,
    original_price DECIMAL(10, 2) DEFAULT NULL,
    stock INT NOT NULL DEFAULT 0,
    sales INT NOT NULL DEFAULT 0,
    cover VARCHAR(255) NOT NULL DEFAULT '',
    badge VARCHAR(50) NOT NULL DEFAULT '',
    featured TINYINT NOT NULL DEFAULT 0,
    status TINYINT NOT NULL DEFAULT 1,
    sort INT NOT NULL DEFAULT 0,
    create_time DATETIME DEFAULT NULL,
    update_time DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_products_slug (slug),
    KEY idx_products_category (category_id),
    KEY idx_products_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE orders (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_no VARCHAR(40) NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    payment_method VARCHAR(20) NOT NULL DEFAULT 'wechat',
    subtotal DECIMAL(10, 2) NOT NULL DEFAULT 0,
    shipping_fee DECIMAL(10, 2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(10, 2) NOT NULL DEFAULT 0,
    item_count INT NOT NULL DEFAULT 0,
    receiver VARCHAR(60) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    address VARCHAR(255) NOT NULL,
    remark VARCHAR(255) NOT NULL DEFAULT '',
    pay_time DATETIME DEFAULT NULL,
    ship_time DATETIME DEFAULT NULL,
    complete_time DATETIME DEFAULT NULL,
    cancel_time DATETIME DEFAULT NULL,
    create_time DATETIME DEFAULT NULL,
    update_time DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_orders_no (order_no),
    KEY idx_orders_user (user_id),
    KEY idx_orders_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    product_name VARCHAR(160) NOT NULL,
    product_cover VARCHAR(255) NOT NULL DEFAULT '',
    price DECIMAL(10, 2) NOT NULL DEFAULT 0,
    quantity INT NOT NULL DEFAULT 1,
    subtotal DECIMAL(10, 2) NOT NULL DEFAULT 0,
    create_time DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_order_items_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
