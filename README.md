# Warehouse Management System (WMS)

A complete multi-warehouse inventory system built with **PHP 8.1+**, **MySQL/MariaDB**, **HTML, CSS, Bootstrap 5 and JavaScript**.

The project is split into two folders:

| Folder | Contents |
|---|---|
| `backend/` | PHP JSON API, business logic, database schema, installer |
| `frontend/` | Responsive admin dashboard (HTML, CSS, JavaScript, Bootstrap, local vendor libraries) |

The frontend talks to the backend through one endpoint (`backend/api/index.php`) using `fetch()` with a PHP session cookie and a CSRF token. All libraries (Bootstrap, Bootstrap Icons, JsBarcode, html5-qrcode, Chart.js) are stored locally in `frontend/assets/vendor`, so the app works without internet access.

---

## 1. Requirements

* XAMPP with **PHP 8.1 or newer** (current XAMPP releases qualify) and **MariaDB/MySQL** (MariaDB 10.4+ or MySQL 8.0.16+, needed for CHECK constraints)
* A modern browser (Chrome, Edge, Firefox, Safari)

## 2. Installation with XAMPP

1. **Copy the project.** Create the folder `C:\xampp\htdocs\wms` (macOS/Linux: `/Applications/XAMPP/htdocs/wms` or `/opt/lampp/htdocs/wms`) and extract the zip into it so you get:
   ```
   htdocs/wms/backend
   htdocs/wms/frontend
   htdocs/wms/README.md
   ```
2. **Start services.** Open the XAMPP Control Panel and start **Apache** and **MySQL**.
3. **Create the database** (choose one method):
   * **Command line (recommended):**
     ```
     C:\xampp\php\php.exe C:\xampp\htdocs\wms\backend\database\install.php
     ```
     Initializes the complete database schema with administrative access.
   * **phpMyAdmin:** open `http://localhost/phpmyadmin`, click **Import**, and import `backend/database/schema.sql`.
4. **Check the database settings** in `backend/config/config.php`. The XAMPP defaults (`root`, empty password, `127.0.0.1:3306`, database `wms_db`) already match. You can also set the time zone and the expiry alert window (`expiry_alert_days`, default 30 days) there.
5. **Open the application:** `http://localhost/wms/frontend/`

### Login Accounts & Showcases

| Role / Owner | Email | Password | Dataset Details |
|---|---|---|---|
| **Sumeet Tiwari (HR Showcase)** | `sumeettiwari22@gmail.com` | `$M@rty22` | **19 Warehouses**, 20 Products, **5 Low Stock**, **1 Expiring + 4 Expired**, Live 7-Day Trend Chart, ₹ 253,170 Stock Valuation |

> **Dynamic Multi-Tenancy**: When any new user registers via the sign-up page, the system automatically provisions an isolated database schema (`wms_account_{id}`) initialized clean from **ZERO** (0 products, 0 warehouses, 0 stock). Each business tenant operates with full administrative privileges over their own isolated data without cross-tenant bleed.

Passwords can be changed at any time (user menu, top right, then **Change password**). The currency symbol and number format can be changed in `frontend/assets/js/config.js`.

---

## 3. Roles

| Capability | Administrator | Staff |
|---|:---:|:---:|
| Dashboard, reports, transaction log, stock views | Yes | Yes |
| Products, vendors, customers: add / edit | Yes | Yes |
| Purchases, sales, transfers (create, dispatch, receive), adjustments, damaged stock | Yes | Yes |
| Delete products / vendors / customers | Yes | No |
| Cancel purchases and transfers | Yes | No |
| Manage warehouses and categories | Yes | View only |
| Manage users, view activity logs | Yes | No |

Permissions are enforced on the server for every API action, not only by hiding menu items.

## 4. Feature map

| # | Requirement | Where to find it |
|---|---|---|
| 1 | Products (add, edit, delete, search, SKU, price, category, barcode) | **Products** |
| 2 | Barcode generate / scan | **Barcode** page, barcode button in **Products**, scan fields in every product picker |
| 3 | Stock transfer with status | **Stock Transfers** (pending, in transit, completed, cancelled) |
| 4 | Purchases, received stock | **Purchases** (create, partial or full receipt) |
| 5 | Sales with inventory deduction | **Sales** (FIFO, printable invoice) |
| 6 | Vendors and purchase history | **Vendors** |
| 7 | Customers and sales history | **Customers** |
| 8 | Damaged stock | **Damaged Stock** |
| 9 | Low stock alerts | Dashboard, bell icon, **Low Stock** |
| 10 | Stock adjustment with reason | **Stock Adjustments** |
| 11 | Reports (stock, purchases, sales, transfers, damaged, movements) | **Reports** (filters, pagination, CSV export, print) |
| 12 | FIFO | Automatic on sales and transfer dispatch |
| 13 | Batch number tracking by warehouse | **Batches**, **Stock Levels** |
| 14 | Expiry dates | **Expiry Alerts**, expired batches are never sold or transferred |
| 15 | Transaction log | **Transaction Log** |

### How the key workflows behave

* **Purchase:** create a purchase with vendor, warehouse and lines (quantity, unit cost, batch number, expiry date). A batch number is generated if left blank. Stock only enters inventory when you **receive** it, either immediately (tick *Receive stock immediately*) or later with partial or full receipts.
* **Sale (FIFO):** stock is issued from the oldest received non-expired batch first, across as many batches as needed. The invoice shows exactly which batches were used. A sale fails, and nothing is changed, if any line lacks enough non-expired stock.
* **Transfer:** *Create* (no stock moves) then *Dispatch* (FIFO deduction at the source, status in transit) then *Receive* (batches are created or merged at the destination, original received date and expiry are preserved). Cancelling an in-transit transfer returns the stock to the source warehouse.
* **Adjustment:** pick warehouse, product and batch; increase or decrease with a mandatory reason. An increase can also create a new batch.
* **Damaged stock:** removes quantity from a batch immediately so it is no longer sellable. Use **Expiry Alerts** then **Write off** to remove expired stock.
* **Low stock:** products whose non-expired stock is below their minimum level (combined across warehouses, or per warehouse using the filter).

### Barcodes

* **USB / Bluetooth scanners** behave like a keyboard: click any "Scan barcode or search product" field, scan, and the product is added automatically (the scanner's Enter key triggers the lookup).
* **Camera scanning** uses the camera button. Browsers only allow camera access on `localhost` or HTTPS, so `http://localhost/wms/frontend/` works, while `http://192.168.x.x/...` needs HTTPS.
* Barcodes are rendered as EAN-13 when valid, otherwise Code 128. Labels can be printed in bulk.

---

## 5. Project structure

```
backend/
  api/index.php            Single entry point (front controller)
  config/config.php        Database, time zone, alert settings
  includes/                Core classes: App, Database, Auth, Router, Inventory (FIFO / batch engine),
                           V (validation), Lines, Paginator, Doc (document numbers), Activity, Response
  modules/                 One class per resource: Products, Purchases, Sales, Transfers, Adjustments,
                           Damaged, Inventory, Transactions, Reports, Dashboard, Vendors, Customers,
                           Categories, Warehouses, Users, Activity, Auth, Lookups
  database/schema.sql      All tables, keys, constraints
  database/install.php     Command-line installer
frontend/
  index.html               Sign-in page
  app.html                 Application shell (sidebar, top bar)
  assets/css/style.css
  assets/js/               core.js, components.js (tables, product search, scanner, line editor), app.js (router)
  assets/js/pages/         One file per module group
  assets/vendor/           Bootstrap, Bootstrap Icons, JsBarcode, html5-qrcode, Chart.js
```

## 6. Database

Tables: `users`, `warehouses`, `categories`, `products`, `vendors`, `customers`, `purchases`, `purchase_items`, `sales`, `sale_items`, `sale_item_batches`, `inventory_batches`, `stock_transfers`, `stock_transfer_items`, `stock_transfer_batches`, `stock_adjustments`, `damaged_stock`, `stock_transactions`, `activity_logs`, plus `login_attempts` and `document_sequences` (support tables).

* Stock lives in `inventory_batches`, one row per **product + warehouse + batch number**, so every warehouse and batch has its own quantity.
* `CHECK (quantity >= 0)` plus guarded `UPDATE ... WHERE quantity >= ?` statements make negative inventory impossible.
* Every stock change is inside a database transaction and appends a row to `stock_transactions` (user, date, warehouse, product, batch, signed quantity, balance after, type, reference). The sum of a batch's ledger always equals its quantity.
* Duplicate deductions are prevented by row locks and status checks (a transfer can only be dispatched once, a purchase received only up to its ordered quantity) and by per-form idempotency keys (a double click or resubmission returns the original document instead of creating another).
* Foreign keys protect history: records that are referenced cannot be deleted; mark them inactive instead.

## 7. Security

* Passwords are stored with `password_hash()`; sign-in is locked for 15 minutes after 5 failed attempts.
* Session cookies are `HttpOnly` and `SameSite=Lax`; the session id is regenerated at sign-in; every state-changing request requires a CSRF token.
* All SQL uses prepared statements; all output is HTML-escaped; CSV exports neutralise formula injection.
* Internal folders (`includes`, `modules`, `config`, `database`, `logs`) are blocked from web access by `.htaccess`.

## 8. Testing performed

The workflows below were executed against a real MariaDB database through the HTTP API and through the browser UI (all pages, forms and buttons), and finished with 0 failures:

* Purchase (create, idempotent resubmission, partial receipt, over-receipt blocked, receive remainder, cancel rules)
* FIFO sale across multiple batches, oversell blocked, expired stock blocked, all-or-nothing rollback on multi-line failure, duplicate submission does not deduct twice
* Transfer (create, dispatch, receive, merge into existing destination batch, cancel in transit returns stock, double dispatch/receive blocked)
* Stock adjustment (increase, decrease, new batch, negative blocked), damaged stock (including expired write-off)
* Reports (all six types, filters, summaries, CSV export), transaction log, low-stock and expiry alerts
* 10 simultaneous sales competing for the same stock: exactly the affordable number succeeded and stock never went negative
* Role restrictions, lockout after repeated failed sign-ins, CSRF rejection, validation errors

## 9. Troubleshooting

| Problem | Fix |
|---|---|
| "Cannot reach the server" | Start Apache and MySQL in XAMPP; open `http://localhost/wms/frontend/` |
| "An unexpected server error occurred" | Read `backend/logs/app.log`; usually wrong database credentials in `config.php` or the database was not imported |
| Blank page or `Unknown database` | Run the installer or import `schema.sql` |
| `install.php` cannot connect | Make sure MySQL is running; if you set a root password in XAMPP, put it in `config.php` |
| Camera does not start | Use `http://localhost/...` or HTTPS, and allow camera access |
| Pop-up blocked when printing | Allow pop-ups for the site |
| Page shows old behaviour after an update | Hard refresh with Ctrl+F5 |
