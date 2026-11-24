# SariSari Store Manager

A browser-based point of sale and inventory tool for a small sari-sari store. Products have a selling unit such as piece, sachet, or pack. The checkout tracks cash, GCash references, and customer utang in Philippine pesos.

## Run on XAMPP

1. Start Apache and MySQL from the XAMPP Control Panel. The application needs PHP 8.2 or newer on a 64-bit build, with PDO MySQL and mbstring enabled.
2. Put the project in **C:\xampp\htdocs\POS**. In [local phpMyAdmin](http://localhost/phpmyadmin/), select **pos_db**. If the database is new, create it with the **utf8mb4_unicode_ci** collation and import **database/schema.sql** once.
3. Open [SariSari on XAMPP](http://localhost/POS/) from the same computer. Create the first owner account, then sign in. There are no shared or preconfigured passwords.
4. Add a category, then add your first product with its price, unit cost, and opening stock. Create individual cashier accounts under **Staff Accounts**.

The default connection uses XAMPP's local root account with a blank password and is limited to loopback. If you changed the local database login, copy **config.example.php** to **config.local.php**, set **local** to **true**, and enter your local settings. The configuration file is ignored by Git and denied over HTTP.

## Access and calculations

| Task | Store owner | Cashier |
| --- | --- | --- |
| Checkout, stock levels, customer ledger, utang payments | Yes | Yes |
| Receipts and transactions | All | Their own sales |
| Catalog changes, stock adjustments, expenses, reports, staff accounts | Yes | Owner access required |

- Enter unit cost when creating a product. The app keeps the sale-time unit cost and price for every line, so a later catalog change leaves recorded profit intact.
- Checkout saves the sale, payment, inventory changes, and any utang as one InnoDB transaction. The app rejects stale prices and orders that exceed available stock.
- Record a GCash reference after verifying the payment in GCash. For utang, record later cash or GCash payments against an individual sale.
- Gross profit is sales less the cost of goods sold. Net profit subtracts recorded operating expenses. Utang counts on the sale date, and subsequent collections appear on their own line. Weekly reports run Monday through Sunday in Philippine time.

## Three tiers

- **Presentation** uses PHP-rendered HTML templates, local Bootstrap and Bootstrap Icons, CSS, and vanilla JavaScript under **views/** and **assets/**.
- **Application** uses **index.php** to route authenticated requests and **app/** for validation, authorization, store operations, and reporting queries.
- **Data** uses PDO prepared statements and the InnoDB schema in **database/schema.sql**. Foreign keys link sales to their items, users, stock movements, payments, and customer debts.

## Deploy to PHP/MySQL hosting

1. Choose Apache-compatible hosting with PHP 8.2+, PDO MySQL, mbstring, and MySQL or MariaDB. Enable HTTPS on the public domain and redirect HTTP to HTTPS through the host. Configure the host so PHP receives an HTTPS indicator for secured requests. Use a host that honors the supplied Apache access rules, or configure equivalent directory and file restrictions before placing the app online.
2. In the hosting control panel, create a database and a dedicated database user. The app account needs SELECT, INSERT, UPDATE, and DELETE on its database. Use a separate account with schema privileges to import and restore.
3. Create the first owner locally before migration. Stop local data entry during the cutover. Export the local database as SQL using the backup instructions below and import it into the empty production database. The dump already includes the schema.
4. Upload **index.php**, **.htaccess**, **app/**, **views/**, and **assets/**, including the private-directory **.htaccess** files. You can import **database/schema.sql** from your computer through phpMyAdmin. Keep the SQL export and any backup files outside the web root.
5. On the host, create **config.local.php** from **config.example.php**, keep **local** set to **false**, and enter the host's database credentials through its secure file editor or shell. Limit the file's permissions to the hosting account and PHP process as supported. Set **display_errors** off and **log_errors** on in production.
6. Sign in over the public HTTPS URL. Confirm the migrated records, check that private files such as **/app/core.php** return 403 or 404, and verify cashier permissions. HTTPS URLs use secure session cookies.

## Database backup and recovery

- In phpMyAdmin, select the store database, open **Export**, choose SQL, and include table structure and data. Keep timestamped copies in private storage outside Apache's document root. A command-line alternative is **mysqldump --single-transaction --result-file=PATH_TO_BACKUP pos_db**, using your database account and a password prompt as needed.
- To recover, import a known-good SQL file into a new empty database. Point a test installation at the restored database and check that owner sign-in, stock, receipts, and the utang ledger agree with the backup. Change the production configuration to point at the restored database only after confirming the restore.
- Export before each migration or restore and keep backups on a regular schedule appropriate to the shop’s sales volume.
