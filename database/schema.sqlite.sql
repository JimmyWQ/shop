DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,
    phone TEXT NOT NULL DEFAULT '',
    role TEXT NOT NULL DEFAULT 'customer',
    status INTEGER NOT NULL DEFAULT 1,
    create_time TEXT DEFAULT NULL,
    update_time TEXT DEFAULT NULL
);

CREATE TABLE categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    slug TEXT NOT NULL UNIQUE,
    description TEXT NOT NULL DEFAULT '',
    cover TEXT NOT NULL DEFAULT '',
    sort INTEGER NOT NULL DEFAULT 0,
    status INTEGER NOT NULL DEFAULT 1,
    create_time TEXT DEFAULT NULL,
    update_time TEXT DEFAULT NULL
);

CREATE TABLE products (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    category_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    slug TEXT NOT NULL UNIQUE,
    subtitle TEXT NOT NULL DEFAULT '',
    description TEXT NOT NULL DEFAULT '',
    highlights TEXT NOT NULL DEFAULT '',
    specs TEXT NOT NULL DEFAULT '',
    price REAL NOT NULL DEFAULT 0,
    original_price REAL DEFAULT NULL,
    stock INTEGER NOT NULL DEFAULT 0,
    sales INTEGER NOT NULL DEFAULT 0,
    cover TEXT NOT NULL DEFAULT '',
    badge TEXT NOT NULL DEFAULT '',
    featured INTEGER NOT NULL DEFAULT 0,
    status INTEGER NOT NULL DEFAULT 1,
    sort INTEGER NOT NULL DEFAULT 0,
    create_time TEXT DEFAULT NULL,
    update_time TEXT DEFAULT NULL
);

CREATE TABLE orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_no TEXT NOT NULL UNIQUE,
    user_id INTEGER NOT NULL,
    status TEXT NOT NULL DEFAULT 'pending',
    payment_method TEXT NOT NULL DEFAULT 'wechat',
    subtotal REAL NOT NULL DEFAULT 0,
    shipping_fee REAL NOT NULL DEFAULT 0,
    total_amount REAL NOT NULL DEFAULT 0,
    item_count INTEGER NOT NULL DEFAULT 0,
    receiver TEXT NOT NULL,
    phone TEXT NOT NULL,
    address TEXT NOT NULL,
    remark TEXT NOT NULL DEFAULT '',
    pay_time TEXT DEFAULT NULL,
    ship_time TEXT DEFAULT NULL,
    complete_time TEXT DEFAULT NULL,
    cancel_time TEXT DEFAULT NULL,
    create_time TEXT DEFAULT NULL,
    update_time TEXT DEFAULT NULL
);

CREATE TABLE order_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id INTEGER NOT NULL,
    product_id INTEGER NOT NULL,
    product_name TEXT NOT NULL,
    product_cover TEXT NOT NULL DEFAULT '',
    price REAL NOT NULL DEFAULT 0,
    quantity INTEGER NOT NULL DEFAULT 1,
    subtotal REAL NOT NULL DEFAULT 0,
    create_time TEXT DEFAULT NULL
);

CREATE INDEX idx_products_category ON products (category_id);
CREATE INDEX idx_products_status ON products (status);
CREATE INDEX idx_orders_user ON orders (user_id);
CREATE INDEX idx_orders_status ON orders (status);
CREATE INDEX idx_order_items_order ON order_items (order_id);
