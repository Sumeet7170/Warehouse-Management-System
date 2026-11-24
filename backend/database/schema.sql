CREATE DATABASE IF NOT EXISTS wms_db CHARACTER
SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE wms_db;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS document_sequences;
DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS stock_transactions;
DROP TABLE IF EXISTS damaged_stock;
DROP TABLE IF EXISTS stock_adjustments;
DROP TABLE IF EXISTS stock_transfer_batches;
DROP TABLE IF EXISTS stock_transfer_items;
DROP TABLE IF EXISTS stock_transfers;
DROP TABLE IF EXISTS sale_item_batches;
DROP TABLE IF EXISTS sale_items;
DROP TABLE IF EXISTS sales;
DROP TABLE IF EXISTS purchase_items;
DROP TABLE IF EXISTS purchases;
DROP TABLE IF EXISTS inventory_batches;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS vendors;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS warehouses;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    id int UNSIGNED NOT NULL AUTO_INCREMENT,
    name varchar(120) NOT NULL,
    email varchar(150) NOT NULL,
    password_hash varchar(255) NOT NULL,
    role ENUM('admin', 'staff') NOT NULL DEFAULT 'staff',
    account_db varchar(64) NOT NULL DEFAULT 'wms_db',
    is_active tinyint(1) NOT NULL DEFAULT 1,
    last_login_at datetime NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email(email)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
    id int UNSIGNED NOT NULL AUTO_INCREMENT,
    email varchar(150) NOT NULL,
    ip_address varchar(45) NOT NULL,
    attempted_at datetime NOT NULL,
    PRIMARY KEY (id),
    KEY idx_login_attempts(email, attempted_at)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE document_sequences (
    seq_key varchar(40) NOT NULL,
    last_value int UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (seq_key)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE warehouses (
    id int UNSIGNED NOT NULL AUTO_INCREMENT,
    code varchar(20) NOT NULL,
    name varchar(120) NOT NULL,
    address varchar(255) NULL,
    phone varchar(30) NULL,
    is_active tinyint(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_warehouses_code(code)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE categories (
    id int UNSIGNED NOT NULL AUTO_INCREMENT,
    name varchar(100) NOT NULL,
    description varchar(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_name(name)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE products (
    id int UNSIGNED NOT NULL AUTO_INCREMENT,
    sku varchar(60) NOT NULL,
    barcode varchar(64) NULL,
    name varchar(180) NOT NULL,
    category_id int UNSIGNED NULL,
    unit varchar(20) NOT NULL DEFAULT 'pcs',
    price decimal(12, 2) NOT NULL DEFAULT 0.00,
    cost_price decimal(12, 2) NOT NULL DEFAULT 0.00,
    min_stock_level int UNSIGNED NOT NULL DEFAULT 0,
    has_expiry tinyint(1) NOT NULL DEFAULT 0,
    description text NULL,
    is_active tinyint(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_products_sku(sku),
    UNIQUE KEY uq_products_barcode(barcode),
    KEY idx_products_name(name),
    KEY idx_products_category(category_id),
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT chk_products_price CHECK (price >= 0 AND cost_price >= 0)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE vendors (
    id int UNSIGNED NOT NULL AUTO_INCREMENT,
    name varchar(150) NOT NULL,
    contact_person varchar(120) NULL,
    email varchar(150) NULL,
    phone varchar(30) NULL,
    address varchar(255) NULL,
    is_active tinyint(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_vendors_name(name)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE customers (
    id int UNSIGNED NOT NULL AUTO_INCREMENT,
    name varchar(150) NOT NULL,
    email varchar(150) NULL,
    phone varchar(30) NULL,
    address varchar(255) NULL,
    is_active tinyint(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_customers_name(name)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE purchases (
    id int UNSIGNED NOT NULL AUTO_INCREMENT,
    purchase_no varchar(30) NOT NULL,
    vendor_id int UNSIGNED NOT NULL,
    warehouse_id int UNSIGNED NOT NULL,
    purchase_date date NOT NULL,
    status ENUM('ordered', 'partial', 'received', 'cancelled') NOT NULL DEFAULT 'ordered',
    total_amount decimal(14, 2) NOT NULL DEFAULT 0.00,
    notes varchar(500) NULL,
    idempotency_key varchar(64) NULL,
    created_by int UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    received_at datetime NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_purchases_no(purchase_no),
    UNIQUE KEY uq_purchases_idem(idempotency_key),
    KEY idx_purchases_vendor(vendor_id),
    KEY idx_purchases_warehouse(warehouse_id),
    KEY idx_purchases_date(purchase_date),
    CONSTRAINT fk_purchases_vendor FOREIGN KEY (vendor_id) REFERENCES vendors (id),
    CONSTRAINT fk_purchases_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses (id),
    CONSTRAINT fk_purchases_user FOREIGN KEY (created_by) REFERENCES users (id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE purchase_items (
    id int UNSIGNED NOT NULL AUTO_INCREMENT,
    purchase_id int UNSIGNED NOT NULL,
    product_id int UNSIGNED NOT NULL,
    quantity int UNSIGNED NOT NULL,
    received_qty int UNSIGNED NOT NULL DEFAULT 0,
    unit_cost decimal(12, 2) NOT NULL,
    batch_number varchar(60) NOT NULL,
    expiry_date date NULL,
    PRIMARY KEY (id),
    KEY idx_purchase_items_purchase(purchase_id),
    KEY idx_purchase_items_product(product_id),
    CONSTRAINT fk_purchase_items_purchase FOREIGN KEY (purchase_id) REFERENCES purchases (id) ON DELETE CASCADE,
    CONSTRAINT fk_purchase_items_product FOREIGN KEY (product_id) REFERENCES products (id),
    CONSTRAINT chk_purchase_items_qty CHECK (quantity > 0 AND received_qty <= quantity)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE sales (
    id int UNSIGNED NOT NULL AUTO_INCREMENT,
    sale_no varchar(30) NOT NULL,
    customer_id int UNSIGNED NULL,
    warehouse_id int UNSIGNED NOT NULL,
    sale_date date NOT NULL,
    total_amount decimal(14, 2) NOT NULL DEFAULT 0.00,
    notes varchar(500) NULL,
    idempotency_key varchar(64) NULL,
    created_by int UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sales_no(sale_no),
    UNIQUE KEY uq_sales_idem(idempotency_key),
    KEY idx_sales_customer(customer_id),
    KEY idx_sales_warehouse(warehouse_id),
    KEY idx_sales_date(sale_date),
    CONSTRAINT fk_sales_customer FOREIGN KEY (customer_id) REFERENCES customers (id),
    CONSTRAINT fk_sales_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses (id),
    CONSTRAINT fk_sales_user FOREIGN KEY (created_by) REFERENCES users (id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE sale_items (
    id int UNSIGNED NOT NULL AUTO_INCREMENT,
    sale_id int UNSIGNED NOT NULL,
    product_id int UNSIGNED NOT NULL,
    quantity int UNSIGNED NOT NULL,
    unit_price decimal(12, 2) NOT NULL,
    line_total decimal(14, 2) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_sale_items_sale(sale_id),
    KEY idx_sale_items_product(product_id),
    CONSTRAINT fk_sale_items_sale FOREIGN KEY (sale_id) REFERENCES sales (id) ON DELETE CASCADE,
    CONSTRAINT fk_sale_items_product FOREIGN KEY (product_id) REFERENCES products (id),
    CONSTRAINT chk_sale_items_qty CHECK (quantity > 0)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE inventory_batches (
    id int UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id int UNSIGNED NOT NULL,
    warehouse_id int UNSIGNED NOT NULL,
    batch_number varchar(60) NOT NULL,
    expiry_date date NULL,
    quantity int NOT NULL DEFAULT 0,
    initial_quantity int NOT NULL DEFAULT 0,
    unit_cost decimal(12, 2) NOT NULL DEFAULT 0.00,
    received_at datetime NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_batch(product_id, warehouse_id, batch_number),
    KEY idx_batch_fifo(product_id, warehouse_id, received_at, id),
    KEY idx_batch_expiry(expiry_date),
    KEY idx_batch_warehouse(warehouse_id),
    CONSTRAINT fk_batches_product FOREIGN KEY (product_id) REFERENCES products (id),
    CONSTRAINT fk_batches_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses (id),
    CONSTRAINT chk_batches_qty CHECK (quantity >= 0)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE sale_item_batches (
    id int UNSIGNED NOT NULL AUTO_INCREMENT,
    sale_item_id int UNSIGNED NOT NULL,
    batch_id int UNSIGNED NOT NULL,
    batch_number varchar(60) NOT NULL,
    expiry_date date NULL,
    quantity int UNSIGNED NOT NULL,
    unit_cost decimal(12, 2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (id),
    KEY idx_sib_item(sale_item_id),
    KEY idx_sib_batch(batch_id),
    CONSTRAINT fk_sib_item FOREIGN KEY (sale_item_id) REFERENCES sale_items (id) ON DELETE CASCADE,
    CONSTRAINT fk_sib_batch FOREIGN KEY (batch_id) REFERENCES inventory_batches (id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE stock_transfers (
    id int UNSIGNED NOT NULL AUTO_INCREMENT,
    transfer_no varchar(30) NOT NULL,
    from_warehouse_id int UNSIGNED NOT NULL,
    to_warehouse_id int UNSIGNED NOT NULL,
    status ENUM('pending', 'in_transit', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
    transfer_date date NOT NULL,
    notes varchar(500) NULL,
    idempotency_key varchar(64) NULL,
    created_by int UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    dispatched_at datetime NULL,
    received_at datetime NULL,
    cancelled_at datetime NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_transfers_no(transfer_no),
    UNIQUE KEY uq_transfers_idem(idempotency_key),
    KEY idx_transfers_from(from_warehouse_id),
    KEY idx_transfers_to(to_warehouse_id),
    KEY idx_transfers_status(status),
    CONSTRAINT fk_transfers_from FOREIGN KEY (from_warehouse_id) REFERENCES warehouses (id),
    CONSTRAINT fk_transfers_to FOREIGN KEY (to_warehouse_id) REFERENCES warehouses (id),
    CONSTRAINT fk_transfers_user FOREIGN KEY (created_by) REFERENCES users (id),
    CONSTRAINT chk_transfers_wh CHECK (from_warehouse_id <> to_warehouse_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE stock_transfer_items (
    id int UNSIGNED NOT NULL AUTO_INCREMENT,
    transfer_id int UNSIGNED NOT NULL,
    product_id int UNSIGNED NOT NULL,
    quantity int UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    KEY idx_sti_transfer(transfer_id),
    KEY idx_sti_product(product_id),
    CONSTRAINT fk_sti_transfer FOREIGN KEY (transfer_id) REFERENCES stock_transfers (id) ON DELETE CASCADE,
    CONSTRAINT fk_sti_product FOREIGN KEY (product_id) REFERENCES products (id),
    CONSTRAINT chk_sti_qty CHECK (quantity > 0)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE stock_transfer_batches (
    id int UNSIGNED NOT NULL AUTO_INCREMENT,
    transfer_item_id int UNSIGNED NOT NULL,
    source_batch_id int UNSIGNED NOT NULL,
    batch_number varchar(60) NOT NULL,
    expiry_date date NULL,
    quantity int UNSIGNED NOT NULL,
    unit_cost decimal(12, 2) NOT NULL DEFAULT 0.00,
    original_received_at datetime NOT NULL,
    PRIMARY KEY (id),
    KEY idx_stb_item(transfer_item_id),
    KEY idx_stb_batch(source_batch_id),
    CONSTRAINT fk_stb_item FOREIGN KEY (transfer_item_id) REFERENCES stock_transfer_items (id) ON DELETE CASCADE,
    CONSTRAINT fk_stb_batch FOREIGN KEY (source_batch_id) REFERENCES inventory_batches (id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE stock_adjustments (
    id int UNSIGNED NOT NULL AUTO_INCREMENT,
    adjustment_no varchar(30) NOT NULL,
    warehouse_id int UNSIGNED NOT NULL,
    product_id int UNSIGNED NOT NULL,
    batch_id int UNSIGNED NOT NULL,
    adjustment_type ENUM('increase', 'decrease') NOT NULL,
    quantity int UNSIGNED NOT NULL,
    reason varchar(255) NOT NULL,
    idempotency_key varchar(64) NULL,
    created_by int UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_adjustments_no(adjustment_no),
    UNIQUE KEY uq_adjustments_idem(idempotency_key),
    KEY idx_adjustments_product(product_id),
    KEY idx_adjustments_warehouse(warehouse_id),
    CONSTRAINT fk_adjustments_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses (id),
    CONSTRAINT fk_adjustments_product FOREIGN KEY (product_id) REFERENCES products (id),
    CONSTRAINT fk_adjustments_batch FOREIGN KEY (batch_id) REFERENCES inventory_batches (id),
    CONSTRAINT fk_adjustments_user FOREIGN KEY (created_by) REFERENCES users (id),
    CONSTRAINT chk_adjustments_qty CHECK (quantity > 0)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE damaged_stock (
    id int UNSIGNED NOT NULL AUTO_INCREMENT,
    damage_no varchar(30) NOT NULL,
    warehouse_id int UNSIGNED NOT NULL,
    product_id int UNSIGNED NOT NULL,
    batch_id int UNSIGNED NOT NULL,
    quantity int UNSIGNED NOT NULL,
    reason varchar(255) NOT NULL,
    damage_date date NOT NULL,
    idempotency_key varchar(64) NULL,
    created_by int UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_damaged_no(damage_no),
    UNIQUE KEY uq_damaged_idem(idempotency_key),
    KEY idx_damaged_product(product_id),
    KEY idx_damaged_warehouse(warehouse_id),
    CONSTRAINT fk_damaged_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses (id),
    CONSTRAINT fk_damaged_product FOREIGN KEY (product_id) REFERENCES products (id),
    CONSTRAINT fk_damaged_batch FOREIGN KEY (batch_id) REFERENCES inventory_batches (id),
    CONSTRAINT fk_damaged_user FOREIGN KEY (created_by) REFERENCES users (id),
    CONSTRAINT chk_damaged_qty CHECK (quantity > 0)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE stock_transactions (
    id bigint UNSIGNED NOT NULL AUTO_INCREMENT,
    txn_date datetime NOT NULL,
    user_id int UNSIGNED NOT NULL,
    warehouse_id int UNSIGNED NOT NULL,
    product_id int UNSIGNED NOT NULL,
    batch_id int UNSIGNED NOT NULL,
    batch_number varchar(60) NOT NULL,
    txn_type ENUM('purchase', 'sale', 'transfer_out', 'transfer_in', 'transfer_return', 'adjustment_in', 'adjustment_out', 'damage') NOT NULL,
    quantity_change int NOT NULL,
    balance_after int NOT NULL,
    reference_type varchar(30) NOT NULL,
    reference_id int UNSIGNED NOT NULL,
    reference_no varchar(30) NOT NULL,
    notes varchar(255) NULL,
    PRIMARY KEY (id),
    KEY idx_txn_date(txn_date),
    KEY idx_txn_product(product_id),
    KEY idx_txn_warehouse(warehouse_id),
    KEY idx_txn_batch(batch_id),
    KEY idx_txn_type(txn_type),
    KEY idx_txn_ref(reference_type, reference_id),
    CONSTRAINT fk_txn_user FOREIGN KEY (user_id) REFERENCES users (id),
    CONSTRAINT fk_txn_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses (id),
    CONSTRAINT fk_txn_product FOREIGN KEY (product_id) REFERENCES products (id),
    CONSTRAINT fk_txn_batch FOREIGN KEY (batch_id) REFERENCES inventory_batches (id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE activity_logs (
    id bigint UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id int UNSIGNED NULL,
    action varchar(60) NOT NULL,
    entity varchar(40) NULL,
    entity_id int UNSIGNED NULL,
    details varchar(500) NULL,
    ip_address varchar(45) NULL,
    created_at datetime NOT NULL,
    PRIMARY KEY (id),
    KEY idx_activity_user(user_id),
    KEY idx_activity_created(created_at),
    CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
