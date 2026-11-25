-- Warehouse Management System (WMS) Showcase Seed
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `wms_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `wms_db`;

CREATE TABLE IF NOT EXISTS `app_sessions` (
  `id` varchar(128) NOT NULL,
  `data` mediumblob NOT NULL,
  `expires_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_app_sessions_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE `activity_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `action` varchar(60) NOT NULL,
  `entity` varchar(40) DEFAULT NULL,
  `entity_id` int(10) unsigned DEFAULT NULL,
  `details` varchar(500) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_activity_user` (`user_id`),
  KEY `idx_activity_created` (`created_at`),
  CONSTRAINT `fk_activity_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=89 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `customers`;
CREATE TABLE `customers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_customers_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `damaged_stock`;
CREATE TABLE `damaged_stock` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `damage_no` varchar(30) NOT NULL,
  `warehouse_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `batch_id` int(10) unsigned NOT NULL,
  `quantity` int(10) unsigned NOT NULL,
  `reason` varchar(255) NOT NULL,
  `damage_date` date NOT NULL,
  `idempotency_key` varchar(64) DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_damaged_no` (`damage_no`),
  UNIQUE KEY `uq_damaged_idem` (`idempotency_key`),
  KEY `idx_damaged_product` (`product_id`),
  KEY `idx_damaged_warehouse` (`warehouse_id`),
  KEY `fk_damaged_batch` (`batch_id`),
  KEY `fk_damaged_user` (`created_by`),
  CONSTRAINT `fk_damaged_batch` FOREIGN KEY (`batch_id`) REFERENCES `inventory_batches` (`id`),
  CONSTRAINT `fk_damaged_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `fk_damaged_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_damaged_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`),
  CONSTRAINT `chk_damaged_qty` CHECK (`quantity` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `document_sequences`;
CREATE TABLE `document_sequences` (
  `seq_key` varchar(40) NOT NULL,
  `last_value` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`seq_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `inventory_batches`;
CREATE TABLE `inventory_batches` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int(10) unsigned NOT NULL,
  `warehouse_id` int(10) unsigned NOT NULL,
  `batch_number` varchar(60) NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `initial_quantity` int(11) NOT NULL DEFAULT 0,
  `unit_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `received_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_batch` (`product_id`,`warehouse_id`,`batch_number`),
  KEY `idx_batch_fifo` (`product_id`,`warehouse_id`,`received_at`,`id`),
  KEY `idx_batch_expiry` (`expiry_date`),
  KEY `idx_batch_warehouse` (`warehouse_id`),
  CONSTRAINT `fk_batches_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `fk_batches_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`),
  CONSTRAINT `chk_batches_qty` CHECK (`quantity` >= 0)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(150) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempted_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_login_attempts` (`email`,`attempted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `sku` varchar(60) NOT NULL,
  `barcode` varchar(64) DEFAULT NULL,
  `name` varchar(180) NOT NULL,
  `category_id` int(10) unsigned DEFAULT NULL,
  `unit` varchar(20) NOT NULL DEFAULT 'pcs',
  `price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `cost_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `min_stock_level` int(10) unsigned NOT NULL DEFAULT 0,
  `has_expiry` tinyint(1) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_products_sku` (`sku`),
  UNIQUE KEY `uq_products_barcode` (`barcode`),
  KEY `idx_products_name` (`name`),
  KEY `idx_products_category` (`category_id`),
  CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_products_price` CHECK (`price` >= 0 and `cost_price` >= 0)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `purchase_items`;
CREATE TABLE `purchase_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `purchase_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `quantity` int(10) unsigned NOT NULL,
  `received_qty` int(10) unsigned NOT NULL DEFAULT 0,
  `unit_cost` decimal(12,2) NOT NULL,
  `batch_number` varchar(60) NOT NULL,
  `expiry_date` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_purchase_items_purchase` (`purchase_id`),
  KEY `idx_purchase_items_product` (`product_id`),
  CONSTRAINT `fk_purchase_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `fk_purchase_items_purchase` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_purchase_items_qty` CHECK (`quantity` > 0 and `received_qty` <= `quantity`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `purchases`;
CREATE TABLE `purchases` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `purchase_no` varchar(30) NOT NULL,
  `vendor_id` int(10) unsigned NOT NULL,
  `warehouse_id` int(10) unsigned NOT NULL,
  `purchase_date` date NOT NULL,
  `status` enum('ordered','partial','received','cancelled') NOT NULL DEFAULT 'ordered',
  `total_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `notes` varchar(500) DEFAULT NULL,
  `idempotency_key` varchar(64) DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `received_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_purchases_no` (`purchase_no`),
  UNIQUE KEY `uq_purchases_idem` (`idempotency_key`),
  KEY `idx_purchases_vendor` (`vendor_id`),
  KEY `idx_purchases_warehouse` (`warehouse_id`),
  KEY `idx_purchases_date` (`purchase_date`),
  KEY `fk_purchases_user` (`created_by`),
  CONSTRAINT `fk_purchases_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_purchases_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`),
  CONSTRAINT `fk_purchases_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `sale_item_batches`;
CREATE TABLE `sale_item_batches` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `sale_item_id` int(10) unsigned NOT NULL,
  `batch_id` int(10) unsigned NOT NULL,
  `batch_number` varchar(60) NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `quantity` int(10) unsigned NOT NULL,
  `unit_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_sib_item` (`sale_item_id`),
  KEY `idx_sib_batch` (`batch_id`),
  CONSTRAINT `fk_sib_batch` FOREIGN KEY (`batch_id`) REFERENCES `inventory_batches` (`id`),
  CONSTRAINT `fk_sib_item` FOREIGN KEY (`sale_item_id`) REFERENCES `sale_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `sale_items`;
CREATE TABLE `sale_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `sale_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `quantity` int(10) unsigned NOT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `line_total` decimal(14,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sale_items_sale` (`sale_id`),
  KEY `idx_sale_items_product` (`product_id`),
  CONSTRAINT `fk_sale_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `fk_sale_items_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_sale_items_qty` CHECK (`quantity` > 0)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `sales`;
CREATE TABLE `sales` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `sale_no` varchar(30) NOT NULL,
  `customer_id` int(10) unsigned DEFAULT NULL,
  `warehouse_id` int(10) unsigned NOT NULL,
  `sale_date` date NOT NULL,
  `total_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `notes` varchar(500) DEFAULT NULL,
  `idempotency_key` varchar(64) DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sales_no` (`sale_no`),
  UNIQUE KEY `uq_sales_idem` (`idempotency_key`),
  KEY `idx_sales_customer` (`customer_id`),
  KEY `idx_sales_warehouse` (`warehouse_id`),
  KEY `idx_sales_date` (`sale_date`),
  KEY `fk_sales_user` (`created_by`),
  CONSTRAINT `fk_sales_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_sales_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_sales_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `stock_adjustments`;
CREATE TABLE `stock_adjustments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `adjustment_no` varchar(30) NOT NULL,
  `warehouse_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `batch_id` int(10) unsigned NOT NULL,
  `adjustment_type` enum('increase','decrease') NOT NULL,
  `quantity` int(10) unsigned NOT NULL,
  `reason` varchar(255) NOT NULL,
  `idempotency_key` varchar(64) DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_adjustments_no` (`adjustment_no`),
  UNIQUE KEY `uq_adjustments_idem` (`idempotency_key`),
  KEY `idx_adjustments_product` (`product_id`),
  KEY `idx_adjustments_warehouse` (`warehouse_id`),
  KEY `fk_adjustments_batch` (`batch_id`),
  KEY `fk_adjustments_user` (`created_by`),
  CONSTRAINT `fk_adjustments_batch` FOREIGN KEY (`batch_id`) REFERENCES `inventory_batches` (`id`),
  CONSTRAINT `fk_adjustments_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `fk_adjustments_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_adjustments_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`),
  CONSTRAINT `chk_adjustments_qty` CHECK (`quantity` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `stock_transactions`;
CREATE TABLE `stock_transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `txn_date` datetime NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `warehouse_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `batch_id` int(10) unsigned NOT NULL,
  `batch_number` varchar(60) NOT NULL,
  `txn_type` enum('purchase','sale','transfer_out','transfer_in','transfer_return','adjustment_in','adjustment_out','damage') NOT NULL,
  `quantity_change` int(11) NOT NULL,
  `balance_after` int(11) NOT NULL,
  `reference_type` varchar(30) NOT NULL,
  `reference_id` int(10) unsigned NOT NULL,
  `reference_no` varchar(30) NOT NULL,
  `notes` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_txn_date` (`txn_date`),
  KEY `idx_txn_product` (`product_id`),
  KEY `idx_txn_warehouse` (`warehouse_id`),
  KEY `idx_txn_batch` (`batch_id`),
  KEY `idx_txn_type` (`txn_type`),
  KEY `idx_txn_ref` (`reference_type`,`reference_id`),
  KEY `fk_txn_user` (`user_id`),
  CONSTRAINT `fk_txn_batch` FOREIGN KEY (`batch_id`) REFERENCES `inventory_batches` (`id`),
  CONSTRAINT `fk_txn_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `fk_txn_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_txn_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `stock_transfer_batches`;
CREATE TABLE `stock_transfer_batches` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `transfer_item_id` int(10) unsigned NOT NULL,
  `source_batch_id` int(10) unsigned NOT NULL,
  `batch_number` varchar(60) NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `quantity` int(10) unsigned NOT NULL,
  `unit_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `original_received_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_stb_item` (`transfer_item_id`),
  KEY `idx_stb_batch` (`source_batch_id`),
  CONSTRAINT `fk_stb_batch` FOREIGN KEY (`source_batch_id`) REFERENCES `inventory_batches` (`id`),
  CONSTRAINT `fk_stb_item` FOREIGN KEY (`transfer_item_id`) REFERENCES `stock_transfer_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `stock_transfer_items`;
CREATE TABLE `stock_transfer_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `transfer_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `quantity` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sti_transfer` (`transfer_id`),
  KEY `idx_sti_product` (`product_id`),
  CONSTRAINT `fk_sti_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `fk_sti_transfer` FOREIGN KEY (`transfer_id`) REFERENCES `stock_transfers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_sti_qty` CHECK (`quantity` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `stock_transfers`;
CREATE TABLE `stock_transfers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `transfer_no` varchar(30) NOT NULL,
  `from_warehouse_id` int(10) unsigned NOT NULL,
  `to_warehouse_id` int(10) unsigned NOT NULL,
  `status` enum('pending','in_transit','completed','cancelled') NOT NULL DEFAULT 'pending',
  `transfer_date` date NOT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `idempotency_key` varchar(64) DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `dispatched_at` datetime DEFAULT NULL,
  `received_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_transfers_no` (`transfer_no`),
  UNIQUE KEY `uq_transfers_idem` (`idempotency_key`),
  KEY `idx_transfers_from` (`from_warehouse_id`),
  KEY `idx_transfers_to` (`to_warehouse_id`),
  KEY `idx_transfers_status` (`status`),
  KEY `fk_transfers_user` (`created_by`),
  CONSTRAINT `fk_transfers_from` FOREIGN KEY (`from_warehouse_id`) REFERENCES `warehouses` (`id`),
  CONSTRAINT `fk_transfers_to` FOREIGN KEY (`to_warehouse_id`) REFERENCES `warehouses` (`id`),
  CONSTRAINT `fk_transfers_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `chk_transfers_wh` CHECK (`from_warehouse_id` <> `to_warehouse_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','staff') NOT NULL DEFAULT 'staff',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `account_db` varchar(64) NOT NULL DEFAULT 'wms_db',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `vendors`;
CREATE TABLE `vendors` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `contact_person` varchar(120) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_vendors_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `warehouses`;
CREATE TABLE `warehouses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `name` varchar(120) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_warehouses_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


USE `wms_db`;

INSERT INTO `users` VALUES (3,'Sumeet Ajay Tiwari','sumeettiwari22@gmail.com','$2y$10$fN/KHc4g/4FbNQnkyUgoLehnCnRe.97t36WcBKEGNJ5z0.m5uEbd.','admin',1,'2026-11-25 13:31:08','2026-11-21 19:36:02','wms_account_3');



CREATE DATABASE IF NOT EXISTS `wms_account_3` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `wms_account_3`;
DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE `activity_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `action` varchar(60) NOT NULL,
  `entity` varchar(40) DEFAULT NULL,
  `entity_id` int(10) unsigned DEFAULT NULL,
  `details` varchar(500) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_activity_user` (`user_id`),
  KEY `idx_activity_created` (`created_at`),
  CONSTRAINT `fk_activity_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=106 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `activity_logs` VALUES (1,3,'warehouse_create','warehouse',1,'Mumbai Central Fulfillment Center',NULL,'2026-11-22 18:48:11'),(2,3,'warehouse_create','warehouse',2,'Delhi Northern Logistics Hub',NULL,'2026-11-22 18:48:11'),(3,3,'warehouse_create','warehouse',3,'Bengaluru Silicon Depot',NULL,'2026-11-22 18:48:11'),(4,3,'warehouse_create','warehouse',4,'Hyderabad Apex Cargo Terminal',NULL,'2026-11-22 18:48:11'),(5,3,'warehouse_create','warehouse',5,'Chennai Coastal Fulfillment Hub',NULL,'2026-11-22 18:48:11'),(6,3,'warehouse_create','warehouse',6,'Kolkata Eastern Logistics Terminal',NULL,'2026-11-22 18:48:11'),(7,3,'warehouse_create','warehouse',7,'Ahmedabad Western Gateway Hub',NULL,'2026-11-22 18:48:11'),(8,3,'warehouse_create','warehouse',8,'Pune Automotive & Engineering Park',NULL,'2026-11-22 18:48:11'),(9,3,'warehouse_create','warehouse',9,'Kochi Harbor Freight Station',NULL,'2026-11-22 18:48:11'),(10,3,'warehouse_create','warehouse',10,'Jaipur Express Distribution Center',NULL,'2026-11-22 18:48:11'),(11,3,'warehouse_create','warehouse',11,'Lucknow Central Logistics Yard',NULL,'2026-11-22 18:48:11'),(12,3,'warehouse_create','warehouse',12,'Indore Cargo Transit Depot',NULL,'2026-11-22 18:48:11'),(13,3,'warehouse_create','warehouse',13,'Nagpur Multi-Modal Hub',NULL,'2026-11-22 18:48:11'),(14,3,'warehouse_create','warehouse',14,'Patna Riverfront Warehouse',NULL,'2026-11-22 18:48:11'),(15,3,'warehouse_create','warehouse',15,'Bhubaneswar Eastern Gateway',NULL,'2026-11-22 18:48:11'),(16,3,'warehouse_create','warehouse',16,'Visakhapatnam Port Logistics Park',NULL,'2026-11-22 18:48:11'),(17,3,'warehouse_create','warehouse',17,'Surat Textile Distribution Center',NULL,'2026-11-22 18:48:11'),(18,3,'warehouse_create','warehouse',18,'Vadodara Industrial Cargo Depot',NULL,'2026-11-22 18:48:11'),(19,3,'warehouse_create','warehouse',19,'Goa Coastal Distribution Station',NULL,'2026-11-22 18:48:11'),(20,3,'category_create','category',1,'Electronics & IT Hardware',NULL,'2026-11-22 18:48:11'),(21,3,'category_create','category',2,'Industrial Tools & Equipment',NULL,'2026-11-22 18:48:11'),(22,3,'category_create','category',3,'Healthcare & Pharmaceuticals',NULL,'2026-11-22 18:48:11'),(23,3,'category_create','category',4,'Packaged Foods & Groceries',NULL,'2026-11-22 18:48:11'),(24,3,'category_create','category',5,'Packaging & Stationery',NULL,'2026-11-22 18:48:11'),(25,3,'vendor_create','vendor',1,'Premier National Hardware Ltd',NULL,'2026-11-22 18:48:11'),(26,3,'vendor_create','vendor',2,'Apex Healthcare Pharma Corp',NULL,'2026-11-22 18:48:11'),(27,3,'vendor_create','vendor',3,'Global Agro Foods Supplies',NULL,'2026-11-22 18:48:11'),(28,3,'vendor_create','vendor',4,'Universal Packaging & Office Goods',NULL,'2026-11-22 18:48:11'),(29,3,'customer_create','customer',1,'Metro Retail Hypermarkets Ltd',NULL,'2026-11-22 18:48:11'),(30,3,'customer_create','customer',2,'Zenith Tech Logistics Pvt Ltd',NULL,'2026-11-22 18:48:11'),(31,3,'customer_create','customer',3,'Apollo Care Hospital Network',NULL,'2026-11-22 18:48:11'),(32,3,'product_create','product',1,'ELC-MOU - Ergonomic Optical Wireless Mouse',NULL,'2026-11-22 18:48:11'),(33,3,'product_create','product',2,'ELC-KEY - Mechanical Gaming Keyboard RGB',NULL,'2026-11-22 18:48:11'),(34,3,'product_create','product',3,'ELC-CHG - USB-C Fast Charger 65W GaN',NULL,'2026-11-22 18:48:11'),(35,3,'product_create','product',4,'ELC-HDP - Noise Cancelling Wireless Headphones',NULL,'2026-11-22 18:48:11'),(36,3,'product_create','product',5,'IND-DRL - Heavy Duty 20V Cordless Impact Drill',NULL,'2026-11-22 18:48:11'),(37,3,'product_create','product',6,'IND-CAL - Digital Stainless Steel Vernier Caliper 150mm',NULL,'2026-11-22 18:48:11'),(38,3,'product_create','product',7,'IND-GLV - Industrial Heavy-Duty Nitrile Safety Gloves',NULL,'2026-11-22 18:48:11'),(39,3,'product_create','product',8,'IND-SCR - Precision Screwdriver Toolkit (48-in-1)',NULL,'2026-11-22 18:48:11'),(40,3,'product_create','product',9,'MED-MSK - Disposable 3-Ply Surgical Masks (50s)',NULL,'2026-11-22 18:48:11'),(41,3,'product_create','product',10,'MED-PCM - Paracetamol 500mg Strips (10x10)',NULL,'2026-11-22 18:48:11'),(42,3,'product_create','product',11,'MED-SYR - Pediatric Antibiotic Oral Suspension 60ml',NULL,'2026-11-22 18:48:11'),(43,3,'product_create','product',12,'MED-ANT - Topical Antiseptic Solution 500ml',NULL,'2026-11-22 18:48:11'),(44,3,'product_create','product',13,'FOD-OIL - Cold Pressed Extra Virgin Cooking Oil 1L',NULL,'2026-11-22 18:48:11'),(45,3,'product_create','product',14,'FOD-TEA - Organic Darjeeling Green Tea Bags (100s)',NULL,'2026-11-22 18:48:11'),(46,3,'product_create','product',15,'FOD-RIC - Premium Royal Basmati Rice 5kg',NULL,'2026-11-22 18:48:11'),(47,3,'product_create','product',16,'FOD-ALM - Roasted California Almonds 500g',NULL,'2026-11-22 18:48:11'),(48,3,'product_create','product',17,'OFF-PPR - Multi-Purpose Copier Paper A4 Ream (500s)',NULL,'2026-11-22 18:48:11'),(49,3,'product_create','product',18,'OFF-TPE - Heavy Duty Brown Carton Sealing Tape (6s)',NULL,'2026-11-22 18:48:11'),(50,3,'product_create','product',19,'OFF-LBL - Direct Thermal Shipping Labels 4x6 (500s)',NULL,'2026-11-22 18:48:11'),(51,3,'product_create','product',20,'OFF-BX1 - Corrugated Shipping Cartons (Bundle of 25)',NULL,'2026-11-22 18:48:11'),(52,3,'purchase_create','purchase',1,'PO-20261122-0001 total 117000.00',NULL,'2026-11-22 18:48:11'),(53,3,'purchase_receive','purchase',1,'PO-20261122-0001 -> received',NULL,'2026-11-22 18:48:11'),(54,3,'purchase_create','purchase',2,'PO-20261122-0002 total 56100.00',NULL,'2026-11-22 18:48:11'),(55,3,'purchase_receive','purchase',2,'PO-20261122-0002 -> received',NULL,'2026-11-22 18:48:11'),(56,3,'purchase_create','purchase',3,'PO-20261122-0003 total 9090.00',NULL,'2026-11-22 18:48:11'),(57,3,'purchase_receive','purchase',3,'PO-20261122-0003 -> received',NULL,'2026-11-22 18:48:11'),(58,3,'purchase_create','purchase',4,'PO-20261122-0004 total 41910.00',NULL,'2026-11-22 18:48:11'),(59,3,'purchase_receive','purchase',4,'PO-20261122-0004 -> received',NULL,'2026-11-22 18:48:11'),(60,3,'purchase_create','purchase',5,'PO-20261122-0005 total 21650.00',NULL,'2026-11-22 18:48:11'),(61,3,'purchase_receive','purchase',5,'PO-20261122-0005 -> received',NULL,'2026-11-22 18:48:11'),(62,3,'purchase_create','purchase',6,'PO-20261122-0006 total 63250.00',NULL,'2026-11-22 18:48:11'),(63,3,'purchase_receive','purchase',6,'PO-20261122-0006 -> received',NULL,'2026-11-22 18:48:11'),(64,3,'purchase_create','purchase',7,'PO-20261122-0007 total 13525.00',NULL,'2026-11-22 18:48:11'),(65,3,'purchase_receive','purchase',7,'PO-20261122-0007 -> received',NULL,'2026-11-22 18:48:11'),(66,3,'sale_create','sale',1,'SO-20261122-0001 total 33373.00',NULL,'2026-11-22 18:48:11'),(67,3,'sale_create','sale',2,'SO-20261122-0002 total 32492.00',NULL,'2026-11-22 18:48:11'),(68,3,'sale_create','sale',3,'SO-20261122-0003 total 2390.00',NULL,'2026-11-22 18:48:11'),(69,3,'sale_create','sale',4,'SO-20261122-0004 total 7170.00',NULL,'2026-11-22 18:48:11'),(70,3,'sale_create','sale',5,'SO-20261122-0005 total 7085.00',NULL,'2026-11-22 18:48:11'),(71,3,'sale_create','sale',6,'SO-20261122-0006 total 20190.00',NULL,'2026-11-22 18:48:11'),(72,3,'sale_create','sale',7,'SO-20261122-0007 total 4490.00',NULL,'2026-11-22 18:48:11'),(73,3,'logout','user',3,'User signed out','::1','2026-11-22 18:48:38'),(74,3,'login','user',3,'User signed in','::1','2026-11-22 18:49:50'),(75,3,'logout','user',3,'User signed out','::1','2026-11-22 18:50:53'),(76,3,'login','user',3,'User signed in',NULL,'2026-11-22 18:54:14'),(77,3,'login','user',3,'User signed in','::1','2026-11-22 18:57:11'),(78,3,'logout','user',3,'User signed out','::1','2026-11-22 18:57:22'),(79,3,'login','user',3,'User signed in','::1','2026-11-22 19:10:15'),(80,3,'logout','user',3,'User signed out','::1','2026-11-22 19:10:47'),(81,3,'login','user',3,'User signed in','::1','2026-11-22 19:14:11'),(82,3,'logout','user',3,'User signed out','::1','2026-11-22 19:14:14'),(83,3,'login','user',3,'User signed in','::1','2026-11-22 19:14:25'),(84,3,'product_create','product',21,'IND-LSR-001 - Wooden Product','::1','2026-11-22 19:15:26'),(85,3,'logout','user',3,'User signed out','::1','2026-11-22 19:23:14'),(86,3,'login','user',3,'User signed in','::1','2026-11-22 19:25:31'),(87,3,'login','user',3,'User signed in','::1','2026-11-22 19:34:48'),(88,3,'logout','user',3,'User signed out','::1','2026-11-22 19:34:48'),(89,3,'login','user',3,'User signed in','::1','2026-11-22 19:36:52'),(90,3,'logout','user',3,'User signed out','::1','2026-11-22 19:36:57'),(91,3,'login','user',3,'User signed in','::1','2026-11-22 19:40:24'),(92,3,'logout','user',3,'User signed out','::1','2026-11-22 19:40:24'),(93,3,'login','user',3,'User signed in','::1','2026-11-22 19:41:41'),(95,3,'login','user',3,'User signed in','::1','2026-11-24 18:39:00'),(96,3,'login','user',3,'User signed in','::1','2026-11-24 18:44:14'),(97,3,'login','user',3,'User signed in','::1','2026-11-24 18:45:26'),(98,3,'login','user',3,'User signed in','::1','2026-11-24 20:52:36'),(99,3,'logout','user',3,'User signed out','::1','2026-11-24 20:52:36'),(100,3,'login','user',3,'User signed in','::1','2026-11-24 20:59:57'),(101,3,'login','user',3,'User signed in','::1','2026-11-24 21:04:17'),(102,3,'login','user',3,'User signed in','::1','2026-11-24 21:16:01'),(103,3,'login','user',3,'User signed in','::1','2026-11-24 21:26:09'),(104,3,'login','user',3,'User signed in','::1','2026-11-25 13:31:08'),(105,3,'logout','user',3,'User signed out','::1','2026-11-25 13:31:08');
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `categories` VALUES (1,'Electronics & IT Hardware','Computing, accessories and networking devices','2026-11-22 13:18:11'),(2,'Industrial Tools & Equipment','Measuring instruments, machinery and power tools','2026-11-22 13:18:11'),(3,'Healthcare & Pharmaceuticals','Medical supplies, antiseptics and PPE','2026-11-22 13:18:11'),(4,'Packaged Foods & Groceries','Packaged grains, oils and tea','2026-11-22 13:18:11'),(5,'Packaging & Stationery','Cartons, tapes and office supplies','2026-11-22 13:18:11');
DROP TABLE IF EXISTS `customers`;
CREATE TABLE `customers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_customers_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `customers` VALUES (1,'Metro Retail Hypermarkets Ltd','procurement@metroretail.in','+91 22 6120 3301','Mumbai, MH',1,'2026-11-22 13:18:11'),(2,'Zenith Tech Logistics Pvt Ltd','ops@zenithlogistics.in','+91 80 4099 3302','Bengaluru, KA',1,'2026-11-22 13:18:11'),(3,'Apollo Care Hospital Network','pharmacy@apollocare.org','+91 44 2490 3303','Chennai, TN',1,'2026-11-22 13:18:11');
DROP TABLE IF EXISTS `damaged_stock`;
CREATE TABLE `damaged_stock` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `damage_no` varchar(30) NOT NULL,
  `warehouse_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `batch_id` int(10) unsigned NOT NULL,
  `quantity` int(10) unsigned NOT NULL,
  `reason` varchar(255) NOT NULL,
  `damage_date` date NOT NULL,
  `idempotency_key` varchar(64) DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_damaged_no` (`damage_no`),
  UNIQUE KEY `uq_damaged_idem` (`idempotency_key`),
  KEY `idx_damaged_product` (`product_id`),
  KEY `idx_damaged_warehouse` (`warehouse_id`),
  KEY `fk_damaged_batch` (`batch_id`),
  KEY `fk_damaged_user` (`created_by`),
  CONSTRAINT `fk_damaged_batch` FOREIGN KEY (`batch_id`) REFERENCES `inventory_batches` (`id`),
  CONSTRAINT `fk_damaged_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `fk_damaged_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_damaged_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`),
  CONSTRAINT `chk_damaged_qty` CHECK (`quantity` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `document_sequences`;
CREATE TABLE `document_sequences` (
  `seq_key` varchar(40) NOT NULL,
  `last_value` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`seq_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `document_sequences` VALUES ('PO-20261122',7),('SO-20261122',7);
DROP TABLE IF EXISTS `inventory_batches`;
CREATE TABLE `inventory_batches` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int(10) unsigned NOT NULL,
  `warehouse_id` int(10) unsigned NOT NULL,
  `batch_number` varchar(60) NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `initial_quantity` int(11) NOT NULL DEFAULT 0,
  `unit_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `received_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_batch` (`product_id`,`warehouse_id`,`batch_number`),
  KEY `idx_batch_fifo` (`product_id`,`warehouse_id`,`received_at`,`id`),
  KEY `idx_batch_expiry` (`expiry_date`),
  KEY `idx_batch_warehouse` (`warehouse_id`),
  CONSTRAINT `fk_batches_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `fk_batches_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`),
  CONSTRAINT `chk_batches_qty` CHECK (`quantity` >= 0)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `inventory_batches` VALUES (1,1,1,'BATCH-MOU-01',NULL,38,50,450.00,'2026-11-22 18:48:11','2026-11-22 13:18:11','2026-11-22 13:18:11'),(2,2,1,'BATCH-KEY-01',NULL,25,30,1750.00,'2026-11-22 18:48:11','2026-11-22 13:18:11','2026-11-22 13:18:11'),(3,3,1,'BATCH-CHG-01',NULL,50,60,700.00,'2026-11-22 18:48:11','2026-11-22 13:18:11','2026-11-22 13:18:11'),(4,5,2,'BATCH-DRL-01',NULL,4,8,3200.00,'2026-11-22 18:48:11','2026-11-22 13:18:11','2026-11-22 13:18:11'),(5,6,2,'BATCH-CAL-01',NULL,6,10,920.00,'2026-11-22 18:48:11','2026-11-22 13:18:11','2026-11-22 13:18:11'),(6,7,2,'BATCH-GLV-01',NULL,10,15,140.00,'2026-11-22 18:48:11','2026-11-22 13:18:11','2026-11-22 13:18:11'),(7,8,2,'BATCH-SCR-01',NULL,32,40,480.00,'2026-11-22 18:48:11','2026-11-22 13:18:11','2026-11-22 13:18:11'),(8,9,3,'BATCH-MSK-01',NULL,15,25,120.00,'2026-11-22 18:48:11','2026-11-22 13:18:11','2026-11-22 13:18:11'),(9,10,3,'BATCH-MED-SOON','2026-12-01',50,60,24.00,'2026-11-22 18:48:11','2026-11-22 13:18:11','2026-11-22 13:18:11'),(10,11,3,'BATCH-MED-EXP1','2026-11-07',25,25,78.00,'2026-11-22 18:48:11','2026-11-22 13:18:11','2026-11-22 13:18:11'),(11,12,3,'BATCH-MED-EXP2','2026-11-14',30,30,90.00,'2026-11-22 18:48:11','2026-11-22 13:18:11','2026-11-22 13:18:11'),(12,13,7,'BATCH-FOD-EXP1','2026-11-17',18,18,520.00,'2026-11-22 18:48:11','2026-11-22 13:18:11','2026-11-22 13:18:11'),(13,14,7,'BATCH-FOD-EXP2','2026-11-20',20,20,210.00,'2026-11-22 18:48:11','2026-11-22 13:18:11','2026-11-22 13:18:11'),(14,15,7,'BATCH-RIC-01','2027-05-21',32,40,420.00,'2026-11-22 18:48:11','2026-11-22 13:18:11','2026-11-22 13:18:11'),(15,16,7,'BATCH-ALM-01','2027-05-21',30,35,330.00,'2026-11-22 18:48:11','2026-11-22 13:18:11','2026-11-22 13:18:11'),(16,17,8,'BATCH-PPR-01',NULL,20,30,210.00,'2026-11-22 18:48:11','2026-11-22 13:18:11','2026-11-22 13:18:11'),(17,18,8,'BATCH-TPE-01',NULL,55,70,55.00,'2026-11-22 18:48:11','2026-11-22 13:18:11','2026-11-22 13:18:11'),(18,19,8,'BATCH-LBL-01',NULL,42,50,230.00,'2026-11-22 18:48:11','2026-11-22 13:18:11','2026-11-22 13:18:11'),(19,1,4,'BATCH-MOU-02',NULL,19,25,450.00,'2026-11-22 18:48:11','2026-11-22 13:18:11','2026-11-22 13:18:11'),(20,4,4,'BATCH-HDP-01',NULL,16,20,2600.00,'2026-11-22 18:48:11','2026-11-22 13:18:11','2026-11-22 13:18:11'),(21,18,6,'BATCH-TPE-02',NULL,25,35,55.00,'2026-11-22 18:48:11','2026-11-22 13:18:11','2026-11-22 13:18:11'),(22,20,6,'BATCH-BX1-01',NULL,32,40,290.00,'2026-11-22 18:48:11','2026-11-22 13:18:11','2026-11-22 13:18:11');
DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(150) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempted_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_login_attempts` (`email`,`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `sku` varchar(60) NOT NULL,
  `barcode` varchar(64) DEFAULT NULL,
  `name` varchar(180) NOT NULL,
  `category_id` int(10) unsigned DEFAULT NULL,
  `unit` varchar(20) NOT NULL DEFAULT 'pcs',
  `price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `cost_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `min_stock_level` int(10) unsigned NOT NULL DEFAULT 0,
  `has_expiry` tinyint(1) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_products_sku` (`sku`),
  UNIQUE KEY `uq_products_barcode` (`barcode`),
  KEY `idx_products_name` (`name`),
  KEY `idx_products_category` (`category_id`),
  CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_products_price` CHECK (`price` >= 0 and `cost_price` >= 0)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `products` VALUES (1,'ELC-MOU','2062717606263','Ergonomic Optical Wireless Mouse',1,'pcs',699.00,450.00,10,0,NULL,1,'2026-11-22 13:18:11','2026-11-22 13:18:11'),(2,'ELC-KEY','2066841975028','Mechanical Gaming Keyboard RGB',1,'pcs',2599.00,1750.00,10,0,NULL,1,'2026-11-22 13:18:11','2026-11-22 13:18:11'),(3,'ELC-CHG','2013675611004','USB-C Fast Charger 65W GaN',1,'pcs',1199.00,700.00,15,0,NULL,1,'2026-11-22 13:18:11','2026-11-22 13:18:11'),(4,'ELC-HDP','2014652126061','Noise Cancelling Wireless Headphones',1,'pcs',3999.00,2600.00,5,0,NULL,1,'2026-11-22 13:18:11','2026-11-22 13:18:11'),(5,'IND-DRL','2066606252463','Heavy Duty 20V Cordless Impact Drill',2,'pcs',4800.00,3200.00,20,0,NULL,1,'2026-11-22 13:18:11','2026-11-22 13:18:11'),(6,'IND-CAL','2020882183040','Digital Stainless Steel Vernier Caliper 150mm',2,'pcs',1450.00,920.00,25,0,NULL,1,'2026-11-22 13:18:11','2026-11-22 13:18:11'),(7,'IND-GLV','2042595123434','Industrial Heavy-Duty Nitrile Safety Gloves',2,'pairs',220.00,140.00,50,0,NULL,1,'2026-11-22 13:18:11','2026-11-22 13:18:11'),(8,'IND-SCR','2034066943870','Precision Screwdriver Toolkit (48-in-1)',2,'sets',799.00,480.00,15,0,NULL,1,'2026-11-22 13:18:11','2026-11-22 13:18:11'),(9,'MED-MSK','2053672782635','Disposable 3-Ply Surgical Masks (50s)',3,'boxes',199.00,120.00,80,0,NULL,1,'2026-11-22 13:18:11','2026-11-22 13:18:11'),(10,'MED-PCM','2064868201465','Paracetamol 500mg Strips (10x10)',3,'boxes',40.00,24.00,20,1,NULL,1,'2026-11-22 13:18:11','2026-11-22 13:18:11'),(11,'MED-SYR','2080507925738','Pediatric Antibiotic Oral Suspension 60ml',3,'bottles',125.00,78.00,0,1,NULL,1,'2026-11-22 13:18:11','2026-11-22 13:18:47'),(12,'MED-ANT','2036986578613','Topical Antiseptic Solution 500ml',3,'bottles',145.00,90.00,0,1,NULL,1,'2026-11-22 13:18:11','2026-11-22 13:18:47'),(13,'FOD-OIL','2058152758353','Cold Pressed Extra Virgin Cooking Oil 1L',4,'bottles',750.00,520.00,0,1,NULL,1,'2026-11-22 13:18:11','2026-11-22 13:18:47'),(14,'FOD-TEA','2009441535216','Organic Darjeeling Green Tea Bags (100s)',4,'boxes',340.00,210.00,0,1,NULL,1,'2026-11-22 13:18:11','2026-11-22 13:18:47'),(15,'FOD-RIC','2051801166462','Premium Royal Basmati Rice 5kg',4,'bags',590.00,420.00,20,1,NULL,1,'2026-11-22 13:18:11','2026-11-22 13:18:11'),(16,'FOD-ALM','2023996271603','Roasted California Almonds 500g',4,'pouches',490.00,330.00,20,1,NULL,1,'2026-11-22 13:18:11','2026-11-22 13:18:11'),(17,'OFF-PPR','2069527609408','Multi-Purpose Copier Paper A4 Ream (500s)',5,'reams',295.00,210.00,100,0,NULL,1,'2026-11-22 13:18:11','2026-11-22 13:18:11'),(18,'OFF-TPE','2002686450146','Heavy Duty Brown Carton Sealing Tape (6s)',5,'packs',89.00,55.00,25,0,NULL,1,'2026-11-22 13:18:11','2026-11-22 13:18:11'),(19,'OFF-LBL','2081264491955','Direct Thermal Shipping Labels 4x6 (500s)',5,'rolls',350.00,230.00,20,0,NULL,1,'2026-11-22 13:18:11','2026-11-22 13:18:11'),(20,'OFF-BX1','2031594593171','Corrugated Shipping Cartons (Bundle of 25)',5,'bundles',450.00,290.00,15,0,NULL,1,'2026-11-22 13:18:11','2026-11-22 13:18:11');
DROP TABLE IF EXISTS `purchase_items`;
CREATE TABLE `purchase_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `purchase_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `quantity` int(10) unsigned NOT NULL,
  `received_qty` int(10) unsigned NOT NULL DEFAULT 0,
  `unit_cost` decimal(12,2) NOT NULL,
  `batch_number` varchar(60) NOT NULL,
  `expiry_date` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_purchase_items_purchase` (`purchase_id`),
  KEY `idx_purchase_items_product` (`product_id`),
  CONSTRAINT `fk_purchase_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `fk_purchase_items_purchase` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_purchase_items_qty` CHECK (`quantity` > 0 and `received_qty` <= `quantity`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `purchase_items` VALUES (1,1,1,50,50,450.00,'BATCH-MOU-01',NULL),(2,1,2,30,30,1750.00,'BATCH-KEY-01',NULL),(3,1,3,60,60,700.00,'BATCH-CHG-01',NULL),(4,2,5,8,8,3200.00,'BATCH-DRL-01',NULL),(5,2,6,10,10,920.00,'BATCH-CAL-01',NULL),(6,2,7,15,15,140.00,'BATCH-GLV-01',NULL),(7,2,8,40,40,480.00,'BATCH-SCR-01',NULL),(8,3,9,25,25,120.00,'BATCH-MSK-01',NULL),(9,3,10,60,60,24.00,'BATCH-MED-SOON','2026-12-01'),(10,3,11,25,25,78.00,'BATCH-MED-EXP1','2027-05-21'),(11,3,12,30,30,90.00,'BATCH-MED-EXP2','2027-05-21'),(12,4,13,18,18,520.00,'BATCH-FOD-EXP1','2027-05-21'),(13,4,14,20,20,210.00,'BATCH-FOD-EXP2','2027-05-21'),(14,4,15,40,40,420.00,'BATCH-RIC-01','2027-05-21'),(15,4,16,35,35,330.00,'BATCH-ALM-01','2027-05-21'),(16,5,17,30,30,210.00,'BATCH-PPR-01',NULL),(17,5,18,70,70,55.00,'BATCH-TPE-01',NULL),(18,5,19,50,50,230.00,'BATCH-LBL-01',NULL),(19,6,1,25,25,450.00,'BATCH-MOU-02',NULL),(20,6,4,20,20,2600.00,'BATCH-HDP-01',NULL),(21,7,18,35,35,55.00,'BATCH-TPE-02',NULL),(22,7,20,40,40,290.00,'BATCH-BX1-01',NULL);
DROP TABLE IF EXISTS `purchases`;
CREATE TABLE `purchases` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `purchase_no` varchar(30) NOT NULL,
  `vendor_id` int(10) unsigned NOT NULL,
  `warehouse_id` int(10) unsigned NOT NULL,
  `purchase_date` date NOT NULL,
  `status` enum('ordered','partial','received','cancelled') NOT NULL DEFAULT 'ordered',
  `total_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `notes` varchar(500) DEFAULT NULL,
  `idempotency_key` varchar(64) DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `received_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_purchases_no` (`purchase_no`),
  UNIQUE KEY `uq_purchases_idem` (`idempotency_key`),
  KEY `idx_purchases_vendor` (`vendor_id`),
  KEY `idx_purchases_warehouse` (`warehouse_id`),
  KEY `idx_purchases_date` (`purchase_date`),
  KEY `fk_purchases_user` (`created_by`),
  CONSTRAINT `fk_purchases_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_purchases_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`),
  CONSTRAINT `fk_purchases_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `purchases` VALUES (1,'PO-20261122-0001',1,1,'2026-11-16','received',117000.00,'Inbound consignment 1',NULL,3,'2026-11-22 13:18:11','2026-11-22 18:48:11'),(2,'PO-20261122-0002',1,2,'2026-11-17','received',56100.00,'Inbound consignment 2',NULL,3,'2026-11-22 13:18:11','2026-11-22 18:48:11'),(3,'PO-20261122-0003',2,3,'2026-11-18','received',9090.00,'Inbound consignment 3',NULL,3,'2026-11-22 13:18:11','2026-11-22 18:48:11'),(4,'PO-20261122-0004',3,7,'2026-11-19','received',41910.00,'Inbound consignment 4',NULL,3,'2026-11-22 13:18:11','2026-11-22 18:48:11'),(5,'PO-20261122-0005',4,8,'2026-11-20','received',21650.00,'Inbound consignment 5',NULL,3,'2026-11-22 13:18:11','2026-11-22 18:48:11'),(6,'PO-20261122-0006',1,4,'2026-11-21','received',63250.00,'Inbound consignment 6',NULL,3,'2026-11-22 13:18:11','2026-11-22 18:48:11'),(7,'PO-20261122-0007',4,6,'2026-11-22','received',13525.00,'Inbound consignment 7',NULL,3,'2026-11-22 13:18:11','2026-11-22 18:48:11');
DROP TABLE IF EXISTS `sale_item_batches`;
CREATE TABLE `sale_item_batches` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `sale_item_id` int(10) unsigned NOT NULL,
  `batch_id` int(10) unsigned NOT NULL,
  `batch_number` varchar(60) NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `quantity` int(10) unsigned NOT NULL,
  `unit_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_sib_item` (`sale_item_id`),
  KEY `idx_sib_batch` (`batch_id`),
  CONSTRAINT `fk_sib_batch` FOREIGN KEY (`batch_id`) REFERENCES `inventory_batches` (`id`),
  CONSTRAINT `fk_sib_item` FOREIGN KEY (`sale_item_id`) REFERENCES `sale_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `sale_item_batches` VALUES (1,1,1,'BATCH-MOU-01',NULL,12,450.00),(2,2,2,'BATCH-KEY-01',NULL,5,1750.00),(3,3,3,'BATCH-CHG-01',NULL,10,700.00),(4,4,4,'BATCH-DRL-01',NULL,4,3200.00),(5,5,5,'BATCH-CAL-01',NULL,4,920.00),(6,6,6,'BATCH-GLV-01',NULL,5,140.00),(7,7,7,'BATCH-SCR-01',NULL,8,480.00),(8,8,8,'BATCH-MSK-01',NULL,10,120.00),(9,9,9,'BATCH-MED-SOON','2026-12-01',10,24.00),(10,10,14,'BATCH-RIC-01','2027-05-21',8,420.00),(11,11,15,'BATCH-ALM-01','2027-05-21',5,330.00),(12,12,16,'BATCH-PPR-01',NULL,10,210.00),(13,13,17,'BATCH-TPE-01',NULL,15,55.00),(14,14,18,'BATCH-LBL-01',NULL,8,230.00),(15,15,19,'BATCH-MOU-02',NULL,6,450.00),(16,16,20,'BATCH-HDP-01',NULL,4,2600.00),(17,17,21,'BATCH-TPE-02',NULL,10,55.00),(18,18,22,'BATCH-BX1-01',NULL,8,290.00);
DROP TABLE IF EXISTS `sale_items`;
CREATE TABLE `sale_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `sale_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `quantity` int(10) unsigned NOT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `line_total` decimal(14,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sale_items_sale` (`sale_id`),
  KEY `idx_sale_items_product` (`product_id`),
  CONSTRAINT `fk_sale_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `fk_sale_items_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_sale_items_qty` CHECK (`quantity` > 0)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `sale_items` VALUES (1,1,1,12,699.00,8388.00),(2,1,2,5,2599.00,12995.00),(3,1,3,10,1199.00,11990.00),(4,2,5,4,4800.00,19200.00),(5,2,6,4,1450.00,5800.00),(6,2,7,5,220.00,1100.00),(7,2,8,8,799.00,6392.00),(8,3,9,10,199.00,1990.00),(9,3,10,10,40.00,400.00),(10,4,15,8,590.00,4720.00),(11,4,16,5,490.00,2450.00),(12,5,17,10,295.00,2950.00),(13,5,18,15,89.00,1335.00),(14,5,19,8,350.00,2800.00),(15,6,1,6,699.00,4194.00),(16,6,4,4,3999.00,15996.00),(17,7,18,10,89.00,890.00),(18,7,20,8,450.00,3600.00);
DROP TABLE IF EXISTS `sales`;
CREATE TABLE `sales` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `sale_no` varchar(30) NOT NULL,
  `customer_id` int(10) unsigned DEFAULT NULL,
  `warehouse_id` int(10) unsigned NOT NULL,
  `sale_date` date NOT NULL,
  `total_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `notes` varchar(500) DEFAULT NULL,
  `idempotency_key` varchar(64) DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sales_no` (`sale_no`),
  UNIQUE KEY `uq_sales_idem` (`idempotency_key`),
  KEY `idx_sales_customer` (`customer_id`),
  KEY `idx_sales_warehouse` (`warehouse_id`),
  KEY `idx_sales_date` (`sale_date`),
  KEY `fk_sales_user` (`created_by`),
  CONSTRAINT `fk_sales_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_sales_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_sales_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `sales` VALUES (1,'SO-20261122-0001',1,1,'2026-11-16',33373.00,'Customer dispatch order 1',NULL,3,'2026-11-22 13:18:11'),(2,'SO-20261122-0002',2,2,'2026-11-17',32492.00,'Customer dispatch order 2',NULL,3,'2026-11-22 13:18:11'),(3,'SO-20261122-0003',3,3,'2026-11-18',2390.00,'Customer dispatch order 3',NULL,3,'2026-11-22 13:18:11'),(4,'SO-20261122-0004',1,7,'2026-11-19',7170.00,'Customer dispatch order 4',NULL,3,'2026-11-22 13:18:11'),(5,'SO-20261122-0005',2,8,'2026-11-20',7085.00,'Customer dispatch order 5',NULL,3,'2026-11-22 13:18:11'),(6,'SO-20261122-0006',3,4,'2026-11-21',20190.00,'Customer dispatch order 6',NULL,3,'2026-11-22 13:18:11'),(7,'SO-20261122-0007',1,6,'2026-11-22',4490.00,'Customer dispatch order 7',NULL,3,'2026-11-22 13:18:11');
DROP TABLE IF EXISTS `stock_adjustments`;
CREATE TABLE `stock_adjustments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `adjustment_no` varchar(30) NOT NULL,
  `warehouse_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `batch_id` int(10) unsigned NOT NULL,
  `adjustment_type` enum('increase','decrease') NOT NULL,
  `quantity` int(10) unsigned NOT NULL,
  `reason` varchar(255) NOT NULL,
  `idempotency_key` varchar(64) DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_adjustments_no` (`adjustment_no`),
  UNIQUE KEY `uq_adjustments_idem` (`idempotency_key`),
  KEY `idx_adjustments_product` (`product_id`),
  KEY `idx_adjustments_warehouse` (`warehouse_id`),
  KEY `fk_adjustments_batch` (`batch_id`),
  KEY `fk_adjustments_user` (`created_by`),
  CONSTRAINT `fk_adjustments_batch` FOREIGN KEY (`batch_id`) REFERENCES `inventory_batches` (`id`),
  CONSTRAINT `fk_adjustments_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `fk_adjustments_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_adjustments_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`),
  CONSTRAINT `chk_adjustments_qty` CHECK (`quantity` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `stock_transactions`;
CREATE TABLE `stock_transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `txn_date` datetime NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `warehouse_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `batch_id` int(10) unsigned NOT NULL,
  `batch_number` varchar(60) NOT NULL,
  `txn_type` enum('purchase','sale','transfer_out','transfer_in','transfer_return','adjustment_in','adjustment_out','damage') NOT NULL,
  `quantity_change` int(11) NOT NULL,
  `balance_after` int(11) NOT NULL,
  `reference_type` varchar(30) NOT NULL,
  `reference_id` int(10) unsigned NOT NULL,
  `reference_no` varchar(30) NOT NULL,
  `notes` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_txn_date` (`txn_date`),
  KEY `idx_txn_product` (`product_id`),
  KEY `idx_txn_warehouse` (`warehouse_id`),
  KEY `idx_txn_batch` (`batch_id`),
  KEY `idx_txn_type` (`txn_type`),
  KEY `idx_txn_ref` (`reference_type`,`reference_id`),
  KEY `fk_txn_user` (`user_id`),
  CONSTRAINT `fk_txn_batch` FOREIGN KEY (`batch_id`) REFERENCES `inventory_batches` (`id`),
  CONSTRAINT `fk_txn_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `fk_txn_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_txn_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `stock_transactions` VALUES (1,'2026-11-22 18:48:11',3,1,1,1,'BATCH-MOU-01','purchase',50,50,'purchase',1,'PO-20261122-0001',NULL),(2,'2026-11-22 18:48:11',3,1,2,2,'BATCH-KEY-01','purchase',30,30,'purchase',1,'PO-20261122-0001',NULL),(3,'2026-11-22 18:48:11',3,1,3,3,'BATCH-CHG-01','purchase',60,60,'purchase',1,'PO-20261122-0001',NULL),(4,'2026-11-22 18:48:11',3,2,5,4,'BATCH-DRL-01','purchase',8,8,'purchase',2,'PO-20261122-0002',NULL),(5,'2026-11-22 18:48:11',3,2,6,5,'BATCH-CAL-01','purchase',10,10,'purchase',2,'PO-20261122-0002',NULL),(6,'2026-11-22 18:48:11',3,2,7,6,'BATCH-GLV-01','purchase',15,15,'purchase',2,'PO-20261122-0002',NULL),(7,'2026-11-22 18:48:11',3,2,8,7,'BATCH-SCR-01','purchase',40,40,'purchase',2,'PO-20261122-0002',NULL),(8,'2026-11-22 18:48:11',3,3,9,8,'BATCH-MSK-01','purchase',25,25,'purchase',3,'PO-20261122-0003',NULL),(9,'2026-11-22 18:48:11',3,3,10,9,'BATCH-MED-SOON','purchase',60,60,'purchase',3,'PO-20261122-0003',NULL),(10,'2026-11-22 18:48:11',3,3,11,10,'BATCH-MED-EXP1','purchase',25,25,'purchase',3,'PO-20261122-0003',NULL),(11,'2026-11-22 18:48:11',3,3,12,11,'BATCH-MED-EXP2','purchase',30,30,'purchase',3,'PO-20261122-0003',NULL),(12,'2026-11-22 18:48:11',3,7,13,12,'BATCH-FOD-EXP1','purchase',18,18,'purchase',4,'PO-20261122-0004',NULL),(13,'2026-11-22 18:48:11',3,7,14,13,'BATCH-FOD-EXP2','purchase',20,20,'purchase',4,'PO-20261122-0004',NULL),(14,'2026-11-22 18:48:11',3,7,15,14,'BATCH-RIC-01','purchase',40,40,'purchase',4,'PO-20261122-0004',NULL),(15,'2026-11-22 18:48:11',3,7,16,15,'BATCH-ALM-01','purchase',35,35,'purchase',4,'PO-20261122-0004',NULL),(16,'2026-11-22 18:48:11',3,8,17,16,'BATCH-PPR-01','purchase',30,30,'purchase',5,'PO-20261122-0005',NULL),(17,'2026-11-22 18:48:11',3,8,18,17,'BATCH-TPE-01','purchase',70,70,'purchase',5,'PO-20261122-0005',NULL),(18,'2026-11-22 18:48:11',3,8,19,18,'BATCH-LBL-01','purchase',50,50,'purchase',5,'PO-20261122-0005',NULL),(19,'2026-11-22 18:48:11',3,4,1,19,'BATCH-MOU-02','purchase',25,25,'purchase',6,'PO-20261122-0006',NULL),(20,'2026-11-22 18:48:11',3,4,4,20,'BATCH-HDP-01','purchase',20,20,'purchase',6,'PO-20261122-0006',NULL),(21,'2026-11-22 18:48:11',3,6,18,21,'BATCH-TPE-02','purchase',35,35,'purchase',7,'PO-20261122-0007',NULL),(22,'2026-11-22 18:48:11',3,6,20,22,'BATCH-BX1-01','purchase',40,40,'purchase',7,'PO-20261122-0007',NULL),(23,'2026-11-22 18:48:11',3,1,1,1,'BATCH-MOU-01','sale',-12,38,'sale',1,'SO-20261122-0001',NULL),(24,'2026-11-22 18:48:11',3,1,2,2,'BATCH-KEY-01','sale',-5,25,'sale',1,'SO-20261122-0001',NULL),(25,'2026-11-22 18:48:11',3,1,3,3,'BATCH-CHG-01','sale',-10,50,'sale',1,'SO-20261122-0001',NULL),(26,'2026-11-22 18:48:11',3,2,5,4,'BATCH-DRL-01','sale',-4,4,'sale',2,'SO-20261122-0002',NULL),(27,'2026-11-22 18:48:11',3,2,6,5,'BATCH-CAL-01','sale',-4,6,'sale',2,'SO-20261122-0002',NULL),(28,'2026-11-22 18:48:11',3,2,7,6,'BATCH-GLV-01','sale',-5,10,'sale',2,'SO-20261122-0002',NULL),(29,'2026-11-22 18:48:11',3,2,8,7,'BATCH-SCR-01','sale',-8,32,'sale',2,'SO-20261122-0002',NULL),(30,'2026-11-22 18:48:11',3,3,9,8,'BATCH-MSK-01','sale',-10,15,'sale',3,'SO-20261122-0003',NULL),(31,'2026-11-22 18:48:11',3,3,10,9,'BATCH-MED-SOON','sale',-10,50,'sale',3,'SO-20261122-0003',NULL),(32,'2026-11-22 18:48:11',3,7,15,14,'BATCH-RIC-01','sale',-8,32,'sale',4,'SO-20261122-0004',NULL),(33,'2026-11-22 18:48:11',3,7,16,15,'BATCH-ALM-01','sale',-5,30,'sale',4,'SO-20261122-0004',NULL),(34,'2026-11-22 18:48:11',3,8,17,16,'BATCH-PPR-01','sale',-10,20,'sale',5,'SO-20261122-0005',NULL),(35,'2026-11-22 18:48:11',3,8,18,17,'BATCH-TPE-01','sale',-15,55,'sale',5,'SO-20261122-0005',NULL),(36,'2026-11-22 18:48:11',3,8,19,18,'BATCH-LBL-01','sale',-8,42,'sale',5,'SO-20261122-0005',NULL),(37,'2026-11-22 18:48:11',3,4,1,19,'BATCH-MOU-02','sale',-6,19,'sale',6,'SO-20261122-0006',NULL),(38,'2026-11-22 18:48:11',3,4,4,20,'BATCH-HDP-01','sale',-4,16,'sale',6,'SO-20261122-0006',NULL),(39,'2026-11-22 18:48:11',3,6,18,21,'BATCH-TPE-02','sale',-10,25,'sale',7,'SO-20261122-0007',NULL),(40,'2026-11-22 18:48:11',3,6,20,22,'BATCH-BX1-01','sale',-8,32,'sale',7,'SO-20261122-0007',NULL);
DROP TABLE IF EXISTS `stock_transfer_batches`;
CREATE TABLE `stock_transfer_batches` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `transfer_item_id` int(10) unsigned NOT NULL,
  `source_batch_id` int(10) unsigned NOT NULL,
  `batch_number` varchar(60) NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `quantity` int(10) unsigned NOT NULL,
  `unit_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `original_received_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_stb_item` (`transfer_item_id`),
  KEY `idx_stb_batch` (`source_batch_id`),
  CONSTRAINT `fk_stb_batch` FOREIGN KEY (`source_batch_id`) REFERENCES `inventory_batches` (`id`),
  CONSTRAINT `fk_stb_item` FOREIGN KEY (`transfer_item_id`) REFERENCES `stock_transfer_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `stock_transfer_items`;
CREATE TABLE `stock_transfer_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `transfer_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `quantity` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sti_transfer` (`transfer_id`),
  KEY `idx_sti_product` (`product_id`),
  CONSTRAINT `fk_sti_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `fk_sti_transfer` FOREIGN KEY (`transfer_id`) REFERENCES `stock_transfers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_sti_qty` CHECK (`quantity` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `stock_transfers`;
CREATE TABLE `stock_transfers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `transfer_no` varchar(30) NOT NULL,
  `from_warehouse_id` int(10) unsigned NOT NULL,
  `to_warehouse_id` int(10) unsigned NOT NULL,
  `status` enum('pending','in_transit','completed','cancelled') NOT NULL DEFAULT 'pending',
  `transfer_date` date NOT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `idempotency_key` varchar(64) DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `dispatched_at` datetime DEFAULT NULL,
  `received_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_transfers_no` (`transfer_no`),
  UNIQUE KEY `uq_transfers_idem` (`idempotency_key`),
  KEY `idx_transfers_from` (`from_warehouse_id`),
  KEY `idx_transfers_to` (`to_warehouse_id`),
  KEY `idx_transfers_status` (`status`),
  KEY `fk_transfers_user` (`created_by`),
  CONSTRAINT `fk_transfers_from` FOREIGN KEY (`from_warehouse_id`) REFERENCES `warehouses` (`id`),
  CONSTRAINT `fk_transfers_to` FOREIGN KEY (`to_warehouse_id`) REFERENCES `warehouses` (`id`),
  CONSTRAINT `fk_transfers_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `chk_transfers_wh` CHECK (`from_warehouse_id` <> `to_warehouse_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','staff') NOT NULL DEFAULT 'staff',
  `account_db` varchar(64) NOT NULL DEFAULT 'wms_account_3',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` VALUES (3,'Sumeet Ajay Tiwari','sumeettiwari22@gmail.com','$2y$10$fN/KHc4g/4FbNQnkyUgoLehnCnRe.97t36WcBKEGNJ5z0.m5uEbd.','admin','wms_account_3',1,'2026-11-25 13:31:08','2026-11-22 13:08:22');
DROP TABLE IF EXISTS `vendors`;
CREATE TABLE `vendors` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `contact_person` varchar(120) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_vendors_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `vendors` VALUES (1,'Premier National Hardware Ltd',NULL,'sales@premierhardware.in','+91 22 2840 2201','Mumbai, MH',1,'2026-11-22 13:18:11'),(2,'Apex Healthcare Pharma Corp',NULL,'orders@apexpharma.in','+91 80 4120 2202','Bengaluru, KA',1,'2026-11-22 13:18:11'),(3,'Global Agro Foods Supplies',NULL,'trade@globalagro.in','+91 11 2730 2203','New Delhi, DL',1,'2026-11-22 13:18:11'),(4,'Universal Packaging & Office Goods',NULL,'support@universalpack.in','+91 20 2740 2204','Pune, MH',1,'2026-11-22 13:18:11');
DROP TABLE IF EXISTS `warehouses`;
CREATE TABLE `warehouses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `name` varchar(120) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_warehouses_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `warehouses` VALUES (1,'WH-BOM','Mumbai Central Fulfillment Center','Andheri East Industrial Zone, Mumbai, MH','+91 22 2847 1001',1,'2026-11-22 13:18:11'),(2,'WH-DEL','Delhi Northern Logistics Hub','Okhla Phase III Industrial Area, New Delhi, DL','+91 11 4150 1002',1,'2026-11-22 13:18:11'),(3,'WH-BLR','Bengaluru Silicon Depot','Electronic City Phase 1, Hosur Road, Bengaluru, KA','+91 80 2852 1003',1,'2026-11-22 13:18:11'),(4,'WH-HYD','Hyderabad Apex Cargo Terminal','HITEC City Logistics Park, Hyderabad, TG','+91 40 2311 1004',1,'2026-11-22 13:18:11'),(5,'WH-MAA','Chennai Coastal Fulfillment Hub','Ambattur Industrial Estate, Chennai, TN','+91 44 2625 1005',1,'2026-11-22 13:18:11'),(6,'WH-CCU','Kolkata Eastern Logistics Terminal','Taratala Industrial Area, Kolkata, WB','+91 33 2401 1006',1,'2026-11-22 13:18:11'),(7,'WH-AMD','Ahmedabad Western Gateway Hub','Naroda Industrial Estate, Ahmedabad, GJ','+91 79 2280 1007',1,'2026-11-22 13:18:11'),(8,'WH-PNQ','Pune Automotive & Engineering Park','Chakan MIDC Phase II, Pune, MH','+91 20 6612 1008',1,'2026-11-22 13:18:11'),(9,'WH-COK','Kochi Harbor Freight Station','Willingdon Island Port Area, Kochi, KL','+91 484 266 1009',1,'2026-11-22 13:18:11'),(10,'WH-JAI','Jaipur Express Distribution Center','Sitapura Industrial Area, Jaipur, RJ','+91 141 277 1010',1,'2026-11-22 13:18:11'),(11,'WH-LKO','Lucknow Central Logistics Yard','Transport Nagar, Lucknow, UP','+91 522 243 1011',1,'2026-11-22 13:18:11'),(12,'WH-IDR','Indore Cargo Transit Depot','Pithampur Industrial Area Sector 1, Indore, MP','+91 731 401 1012',1,'2026-11-22 13:18:11'),(13,'WH-NAG','Nagpur Multi-Modal Hub','MIHAN SEZ Logistics Zone, Nagpur, MH','+91 712 258 1013',1,'2026-11-22 13:18:11'),(14,'WH-PAT','Patna Riverfront Warehouse','Fatuha Industrial Area, Patna, BR','+91 612 229 1014',1,'2026-11-22 13:18:11'),(15,'WH-BBI','Bhubaneswar Eastern Gateway','Mancheswar Industrial Estate, Bhubaneswar, OD','+91 674 258 1015',1,'2026-11-22 13:18:11'),(16,'WH-VTZ','Visakhapatnam Port Logistics Park','Autonagar Industrial Area, Visakhapatnam, AP','+91 891 275 1016',1,'2026-11-22 13:18:11'),(17,'WH-STV','Surat Textile Distribution Center','Pandesara GIDC, Surat, GJ','+91 261 289 1017',1,'2026-11-22 13:18:11'),(18,'WH-VAD','Vadodara Industrial Cargo Depot','Makarpura GIDC, Vadodara, GJ','+91 265 264 1018',1,'2026-11-22 13:18:11'),(19,'WH-GOA','Goa Coastal Distribution Station','Verna Industrial Estate, Verna, GA','+91 832 278 1019',1,'2026-11-22 13:18:11');


SET FOREIGN_KEY_CHECKS = 1;

