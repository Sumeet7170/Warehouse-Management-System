# Warehouse Management System (WMS)

[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Database](https://img.shields.io/badge/MySQL%20%2F%20MariaDB-10.4%2B-4479A1?logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Frontend](https://img.shields.io/badge/Bootstrap-5.3-7952B3?logo=bootstrap&logoColor=white)](https://getbootstrap.com/)
[![Architecture](https://img.shields.io/badge/Architecture-Multi--Tenant%20Isolated-success)]()
[![License](https://img.shields.io/badge/License-MIT-blue.svg)]()

A complete, high-performance, multi-warehouse inventory management system featuring **strict FIFO inventory valuation**, **batch & expiry tracking**, **camera & handheld barcode scanning**, **stock transfers**, **purchase & sales order workflows**, and **dynamic multi-tenant workspace isolation**.

Built natively with **PHP 8.1+**, **MySQL / MariaDB**, **Vanilla JavaScript**, and **Bootstrap 5** with zero external runtime dependencies.

---

## 📸 Key Features

* **Strict FIFO Inventory Deduction**: Automated first-in, first-out stock consumption across batches on sales and transfer dispatches. Over-selling or selling expired inventory is blocked at the database engine level.
* **Batch & Expiry Date Management**: Real-time batch-level tracking per warehouse. Automatic expiry alerting for batches approaching expiry within 30 days and immediate write-off handling for expired goods.
* **Multi-Warehouse Stock Transfers**: Full transfer lifecycle: *Draft* &rarr; *Dispatch* (FIFO source warehouse deduction & transit status) &rarr; *Receive* (destination batch creation/merging preserving original lot dates).
* **Barcode Generation & Scanning**:
  * Integrated **EAN-13** and **Code-128** barcode rendering via JsBarcode.
  * Native hardware scanner support (USB / Bluetooth keyboard emulation).
  * In-browser live camera scanning via `html5-qrcode`.
* **Dynamic Multi-Tenant Isolation**: Every newly registered user automatically gets their own dedicated, isolated database schema (`wms_account_{id}`) initialized clean from **ZERO** (0 products, 0 warehouses, 0 stock). No cross-account data bleed.
* **Comprehensive Analytics & Reports**: Live 7-day sales vs. purchase trend charts (Chart.js), real-time stock valuation ledger, low stock alerts, transaction audits, and one-click CSV export with formula injection sanitization.
* **Zero External CDN Dependencies**: All assets (Bootstrap, Bootstrap Icons, Chart.js, JsBarcode, html5-qrcode) are bundled locally in `frontend/assets/vendor/` for 100% offline air-gapped readiness.

---

## ⚡ Quick Start

### 1. Requirements
* **PHP 8.1 or newer** (with `pdo_mysql` and `mbstring` extensions enabled)
* **MySQL 8.0+** or **MariaDB 10.4+** (supporting `CHECK` constraints)
* Modern web browser (Chrome, Edge, Firefox, Safari)

---

### Option A: 1-Click Automated Launcher (Windows)
Double-click `run.bat` or run from terminal:
```cmd
run.bat
```
* Automatically verifies PHP and MySQL services.
* Automatically initializes database schema if first-time run.
* Starts PHP built-in server on port `8000`.
* Launches your browser to `http://localhost:8000/frontend/`.

---

### Option B: Manual CLI (Without `run.bat`)
1. Ensure MySQL is running (via XAMPP or MySQL service).
2. Start the built-in PHP development server from the project root:
   ```bash
   cd wms
   php -S localhost:8000
   ```
3. Open your browser and navigate to:
   ```
   http://localhost:8000/
   ```

---

### Option C: Using XAMPP Apache
1. Copy or link this project into your XAMPP `htdocs` directory:
   ```cmd
   mklink /J "C:\xampp\htdocs\wms" "C:\path\to\wms"
   ```
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Open in browser: `http://localhost/wms/frontend/`

---

## 🔐 Credentials & Showcase Account

| Account | Email | Password | Role | Dataset Overview |
|---|---|---|---|---|
| **Sumeet Tiwari (HR Showcase)** | `sumeettiwari22@gmail.com` | `$M@rty22` | **Administrator** | **19 Warehouses**, 20 Products, **5 Low Stock Items**, **1 Expiring + 4 Expired Batches**, Live 7-Day Trend Chart, ₹ 253,170 Stock Valuation |

> **Self-Service Registration**: Anyone can register a new account on the Sign Up tab. Upon registration, an isolated workspace is provisioned starting completely from **ZERO**, granting full admin controls over that workspace.

---

## 🏗️ Project Architecture

```
wms/
├── backend/
│   ├── api/
│   │   └── index.php              # Single entry point / Front Controller
│   ├── config/
│   │   └── config.php             # Database credentials & application settings
│   ├── database/
│   │   ├── schema.sql             # Relational DDL (tables, keys, check constraints)
│   │   ├── check_db.php           # Non-destructive connection & readiness probe
│   │   └── install.php            # Command-line schema & admin seeder
│   ├── includes/
│   │   ├── App.php                # Global config container & logger
│   │   ├── Auth.php               # Session, RBAC, dynamic tenant switching, CSRF
│   │   ├── Database.php           # PDO singleton, connection pool & transactions
│   │   ├── Inventory.php          # Core FIFO engine & stock ledger
│   │   ├── Router.php             # Dynamic REST-style module dispatcher
│   │   └── V.php                  # Input sanitization & validator
│   ├── modules/                   # Isolated business controllers
│   │   ├── AuthModule.php         # Sign in, registration, multi-tenant provisioning
│   │   ├── DashboardModule.php    # KPI aggregation, trend analysis, alerts
│   │   ├── ProductsModule.php     # Catalog management & SKU generation
│   │   ├── PurchasesModule.php    # PO creation, partial/full receipt workflows
│   │   ├── SalesModule.php        # FIFO deduction sales & invoice printing
│   │   ├── TransfersModule.php    # Multi-step warehouse transfers
│   │   ├── AdjustmentsModule.php  # Manual inventory reconciliations
│   │   ├── DamagedModule.php      # Quarantine & damaged stock write-offs
│   │   ├── ReportsModule.php      # Filtered data reports & CSV exports
│   │   └── WarehousesModule.php   # Multi-facility management
│   └── logs/
│       └── app.log                # Server diagnostic logs
│
├── frontend/
│   ├── index.html                 # Authentication & Registration UI
│   ├── app.html                   # Single-Page Application (SPA) dashboard shell
│   └── assets/
│       ├── css/style.css          # Design system & dark mode responsive theme
│       ├── js/
│       │   ├── config.js          # Currency & regional settings
│       │   ├── core.js            # API client, formatting & modal helpers
│       │   ├── components.js      # Datatables, live search & camera scanners
│       │   ├── login.js           # Auth state machine & tab controller
│       │   ├── app.js             # Client-side hash router
│       │   └── pages/             # Dynamic page modules (catalog, sales, etc.)
│       └── vendor/                # Bundled offline libraries (Bootstrap, Chart.js)
│
├── index.php                      # Root redirector to frontend/
├── run.bat                        # Automated Windows orchestrator
└── README.md                      # Complete system documentation
```

---

## 🗄️ Database Architecture

The relational schema strictly enforces transactional consistency and history preservation:

* **`inventory_batches`**: Tracks stock at the discrete **Product + Warehouse + Batch** coordinate with non-negative constraints (`CHECK (quantity >= 0)`).
* **`stock_transactions`**: Double-entry ledger audit trail. Every stock movement (purchase, sale, dispatch, receipt, adjustment, damage) records user, timestamp, delta, and balance-after.
* **`document_sequences`**: Atomic sequence counter ensuring collision-free human-readable document identifiers (`PO-202611-0001`, `INV-202611-0001`, `TR-202611-0001`).
* **Foreign Keys**: Cascade protection prevents deletion of master records that have historical transaction dependencies.

---

## 🔒 Security Best Practices

* **SQL Injection Immunity**: 100% of database interactions execute through parameterized PDO prepared statements.
* **Strict CSRF Tokens**: State-changing endpoints require valid `X-CSRF-Token` headers matched against secure session tokens.
* **Brute-Force Rate Limiting**: Exponential lockout after 5 consecutive failed login attempts within 15 minutes.
* **Session Hardening**: `HttpOnly`, `SameSite=Lax` cookies with automatic session ID regeneration on privilege transitions.
* **Formula Injection Prevention**: CSV export data fields prepended with sanitizing characters to neutralize spreadsheet command execution.
* **Directory Shielding**: Internal backend directories (`includes/`, `modules/`, `config/`, `database/`, `logs/`) are protected from direct web traversal via `.htaccess`.

---

## 📄 License

This project is licensed under the [MIT License](LICENSE).
