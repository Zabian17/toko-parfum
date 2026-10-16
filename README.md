# Toko Parfum - Laravel 13

Backend dan frontend untuk sistem penjualan parfum, meliputi:

- Katalog produk publik
- Checkout online
- Integrasi ongkir RajaOngkir
- Integrasi pembayaran Midtrans (Snap)
- Admin panel (produk, kategori, pesanan, stok, laporan, settings, user, role)
- Kasir offline (POS)

Project ini menggunakan Laravel sebagai API/backend, sementara UI publik dan admin menggunakan halaman HTML statis di folder `public` yang mengakses API via Axios.

---

## 1. Gambaran Umum Arsitektur

### 1.1 Komponen utama

- Backend API: Laravel 13 (`routes/api.php`)
- Frontend publik: HTML + JS di `public/*.html`
- Admin panel: HTML + JS di `public/admin/*.html`
- Auth admin: token Sanctum (Bearer token)
- Database: MySQL/MariaDB (disarankan)
- Queue: database queue untuk email notifikasi
- Storage: local public disk (`storage/app/public` -> `public/storage`)

### 1.2 Alur high-level

1. User membuka halaman publik (`/`, `/katalog`, `/product`, `/cart`, `/checkout`)
2. Frontend memanggil API publik (`/api/products`, `/api/categories`, dll)
3. Saat checkout, backend membuat order + order_items + potong stok
4. Jika metode `midtrans`, frontend meminta Snap token (`/api/payment/snap/{orderNumber}`)
5. Midtrans webhook mengirim notifikasi ke `/api/payment/notification`
6. Admin panel mengelola data melalui endpoint `/api/admin/*` dengan token Sanctum

---

## 2. Teknologi Yang Digunakan

### 2.1 Backend

- PHP `^8.3`
- Laravel Framework `^13.0`
- Laravel Sanctum `^4.0` (token auth)
- Intervention Image `^3.0` (compress/resize gambar produk)
- Laravel Queue (database driver)
- Laravel Mailables + queue (`OrderCreatedMail`, `OrderStatusUpdatedMail`)

### 2.2 Integrasi eksternal

- RajaOngkir API
	- provinsi
	- kota
	- ongkir
- Midtrans Snap API
	- generate snap token
	- webhook status pembayaran

### 2.3 Frontend publik

- Bootstrap 5.3
- Bootstrap Icons
- Axios
- Tom Select (katalog)
- Select2 + jQuery (checkout)
- SweetAlert2 (cart)

### 2.4 Frontend admin

- Bootstrap 5.3
- Bootstrap Icons
- Axios
- Tabulator (data table/filter/sort server-side style)
- Chart.js (dashboard)

### 2.5 Tools pengembangan

- PHPUnit
- Laravel Pint
- laramint/laravel-brain (dev helper)

---

## 3. Fitur Utama

### 3.1 Fitur publik

- Lihat produk + filter/search/sort
- Lihat detail produk by slug
- Keranjang belanja localStorage
- Checkout online (guest checkout)
- Pilih ongkir (RajaOngkir)
- Pembayaran Midtrans/COD/transfer/cash
- Halaman informasi: cara pemesanan, pengiriman, kebijakan retur

### 3.2 Fitur admin

- Login admin via token
- Dashboard ringkasan bisnis + chart
- CRUD produk (thumbnail, multi image, tags, variants by tier)
- CRUD kategori
- CRUD customer
- Manajemen pesanan + update status + bukti bayar
- Manajemen stok + riwayat pergerakan
- POS kasir offline
- Settings toko/pembayaran/pengiriman/notifikasi
- CRUD tier produk (grade + size + price)
- CRUD role + permission menu
- CRUD user admin

### 3.3 Otomasi bisnis

- Generate nomor order otomatis: `ORD-YYYYMMDD-XXXXX`
- Snapshot data produk di `order_items` saat transaksi
- Potong stok otomatis ketika order dibuat
- Email order dibuat dan email update status order (queued)

---

## 4. Prasyarat

Pastikan environment sudah terpasang:

- PHP >= 8.3
- Composer
- MySQL/MariaDB
- Ekstensi PHP umum Laravel (pdo_mysql, mbstring, openssl, tokenizer, xml, ctype, fileinfo, dll)

Opsional:

- Node.js + npm (hanya jika Anda ingin mengubah asset Vite bawaan Laravel)

Catatan:

- Frontend utama project ini sudah berupa HTML statis + CDN, jadi tanpa Node pun tetap bisa jalan.

---

## 5. Tutorial Pemasangan (Step by Step)

## 5.1 Clone project

```bash
git clone <url-repo-anda> toko_parfum
cd toko_parfum
```

## 5.2 Install dependency PHP

```bash
composer install
```

## 5.3 Buat file environment

```bash
cp .env.example .env
php artisan key:generate
```

Jika di Windows PowerShell:

```powershell
Copy-Item .env.example .env
php artisan key:generate
```

## 5.4 Konfigurasi .env

Contoh minimum (MySQL):

```env
APP_NAME="Toko Parfum"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000
FRONTEND_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=toko_parfum
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=database
FILESYSTEM_DISK=public

MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="noreply@tokoparfum.local"
MAIL_FROM_NAME="Toko Parfum"

RAJAONGKIR_API_KEY=
RAJAONGKIR_BASE_URL=https://rajaongkir.komerce.id/api/v1/
RAJAONGKIR_CACHE_ENABLED=true
RAJAONGKIR_CACHE_TTL=86400

MIDTRANS_ENABLED=false
MIDTRANS_SERVER_KEY=
MIDTRANS_CLIENT_KEY=
MIDTRANS_IS_PRODUCTION=false
```

Untuk mengaktifkan pembayaran Midtrans, isi Server Key dan Client Key dari menu **Settings > Access Keys** di Midtrans, lalu set `MIDTRANS_ENABLED=true`. Gunakan key Sandbox saat `MIDTRANS_IS_PRODUCTION=false`. Aktifkan juga `midtrans_enabled` pada pengaturan pembayaran admin, lalu jalankan `php artisan config:clear` setelah mengubah `.env`. Callback notifikasi Midtrans: `https://domain-toko/api/payment/notification`.

Ongkir checkout memakai RajaOngkir Shipping Cost. Isi `RAJAONGKIR_API_KEY`, aktifkan RajaOngkir pada **Admin > Pengaturan > Pengiriman**, lalu cari dan pilih area asal toko di pengaturan tersebut. Isi berat produk dalam gram pada form produk; checkout menghitung tarif dari berat produk dan area tujuan yang dipilih pelanggan.

## 5.5 Migrate database + symlink storage

```bash
php artisan migrate
php artisan storage:link
```

## 5.6 Seed data awal

Disarankan seed manual berurutan agar data master lengkap:

```bash
php artisan db:seed --class=RoleSeeder
php artisan db:seed --class=SettingsSeeder
php artisan db:seed --class=CategorySeeder
php artisan db:seed --class=ProductTierSeeder
php artisan db:seed --class=ProductSeeder
```

Opsional demo transaksi:

```bash
php artisan db:seed --class=TransactionSeeder
```

Catatan:

- `DatabaseSeeder` saat ini hanya memanggil `TransactionSeeder`.
- `TransactionSeeder` akan skip jika belum ada produk.
- Karena itu, untuk setup baru gunakan seed manual seperti di atas.

## 5.7 Buat user admin pertama

Jika belum ada user admin, buat via Tinker:

```bash
php artisan tinker
```

Lalu jalankan:

```php
$role = \App\Models\Role::where('slug', 'super_admin')->first();
\App\Models\User::create([
		'name' => 'Super Admin',
		'email' => 'admin@local.test',
		'password' => 'password123',
		'role_id' => $role?->id,
]);
```

## 5.8 Jalankan aplikasi

Terminal 1:

```bash
php artisan serve
```

Terminal 2 (untuk queue email):

```bash
php artisan queue:work
```

Akses:

- Toko publik: `http://127.0.0.1:8000`
- Admin login: `http://127.0.0.1:8000/admin/login`

---

## 6. Routing

## 6.1 Web routes (HTML)

Definisi di `routes/web.php`:

- `GET /` -> render `public/index.html`
- `GET /{path}` -> render `public/{path}.html` jika file ada

Contoh URL yang didukung:

- `/katalog` -> `public/katalog.html`
- `/product` -> `public/product.html`
- `/cart` -> `public/cart.html`
- `/checkout` -> `public/checkout.html`
- `/pages/cara-pemesanan` -> `public/pages/cara-pemesanan.html`
- `/admin/login` -> `public/admin/login.html`
- `/admin/dashboard` -> `public/admin/dashboard.html`

## 6.2 API publik

### Produk dan kategori

- `GET /api/products`
- `GET /api/products/slug/{slug}`
- `GET /api/products/{product}`
- `GET /api/product-tiers`
- `GET /api/categories`
- `GET /api/categories/{category}`

### Checkout, shipping, payment

- `POST /api/checkout`
- `GET /api/shipping/provinces`
- `GET /api/shipping/cities?province_id=...`
- `POST /api/shipping/cost`
- `GET /api/payment/config`
- `GET /api/payment/snap/{orderNumber}`
- `POST /api/payment/notification`

### Auth Breeze (session)

- `POST /register`
- `POST /login`
- `POST /forgot-password`
- `POST /reset-password`
- `POST /logout`

## 6.3 API admin auth

- `POST /api/admin/auth/login`
- `POST /api/admin/auth/logout` (butuh token)

## 6.4 API admin (butuh Bearer token Sanctum)

### Dashboard dan laporan

- `GET /api/admin/dashboard`
- `GET /api/admin/reports/sales/{period?}`
- `GET /api/admin/reports/products`
- `GET /api/admin/reports/stock`

### Resource utama

- `apiResource /api/admin/categories`
- `apiResource /api/admin/products`
- `apiResource /api/admin/customers`
- `apiResource /api/admin/tiers` (tanpa show)
- `apiResource /api/admin/roles`
- `apiResource /api/admin/users`

### Order, cashier, stock, settings

- `GET /api/admin/orders`
- `GET /api/admin/orders/{order}`
- `PATCH /api/admin/orders/{order}/status`
- `POST /api/admin/orders/offline`
- `POST /api/admin/cashier/calculate`
- `POST /api/admin/cashier/order`
- `GET /api/admin/stock`
- `GET /api/admin/stock/levels`
- `POST /api/admin/stock/adjust`
- `GET /api/admin/settings`
- `POST /api/admin/settings`
- `PATCH /api/admin/settings/{key}/toggle`
- `POST /api/admin/settings/test-email`

---

## 7. Flow Bisnis

## 7.1 Flow belanja online

1. User buka katalog
2. User pilih produk + tier/size (variant)
3. User tambah ke cart (localStorage)
4. User checkout (`POST /api/checkout`)
5. Backend:
	 - validasi input
	 - validasi stok variant
	 - hitung subtotal + shipping
	 - simpan order + order items
	 - potong stok
	 - queue email order dibuat
6. Jika payment method midtrans:
	 - frontend minta snap token
	 - customer bayar via popup Snap
	 - Midtrans webhook update `payment_status` dan `status`

## 7.2 Flow kasir offline

1. Admin buka halaman kasir
2. Pilih item dan kuantitas
3. Hitung preview total via `POST /api/admin/cashier/calculate`
4. Simpan transaksi via `POST /api/admin/cashier/order`
5. Order dibuat sebagai:
	 - `order_type = offline`
	 - `payment_status = paid`
	 - `status = confirmed`
	 - shipping default pickup

## 7.3 Flow manajemen stok

Stok berkurang otomatis saat order dibuat melalui `OrderService` + `StockService`.

Admin juga bisa adjustment manual:

- `type=in` -> tambah stok
- `type=out` -> kurangi stok
- `type=adjustment` -> delta bisa positif/negatif
- `type=return` -> stok balik

Setiap perubahan tercatat di tabel `stock_movements`.

---

## 8. Status Dan Aturan Domain

## 8.1 Order status

- `pending`
- `confirmed`
- `processing`
- `shipped`
- `delivered`
- `cancelled`

## 8.2 Payment status

- `unpaid`
- `pending`
- `paid`
- `failed`
- `refunded`

## 8.3 Order type

- `online`
- `offline`

## 8.4 Grade/Tier produk

Master tier tersimpan di `product_tiers`:

- `premium` / `super_premium`
- contoh size: `30ml`, `50ml`
- harga diambil dari tier, bukan dari product langsung

---

## 9. Struktur Database (Ringkas)

Tabel inti:

- `categories`
	- hirarki kategori via `parent_id`
- `products`
	- data parfum utama
- `product_tiers`
	- master kombinasi grade/size/price
- `product_variants`
	- relasi produk ke tier + stok per varian
- `product_images`, `product_tags`, `product_tag_pivot`
- `customers`
- `orders`
	- snapshot customer + shipping + payment + status
- `order_items`
	- snapshot item pada saat order
- `stock_movements`
	- audit trail perubahan stok
- `settings`
	- key-value pengaturan toko
- `roles`, `users`
	- RBAC sederhana

Relasi penting:

- Category 1..n Product
- Product 1..n ProductVariant
- ProductVariant n..1 ProductTier
- Order 1..n OrderItem
- Product 1..n StockMovement
- User n..1 Role

---

## 10. Struktur Folder Dan Fungsinya

## 10.1 Backend

- `app/Http/Controllers/Api`
	- endpoint API utama (produk, kategori, order, stock, dll)
- `app/Http/Controllers/Api/Admin`
	- endpoint role/user admin
- `app/Services`
	- business logic utama:
		- `ProductService`
		- `OrderService`
		- `StockService`
		- `MidtransService`
		- `RajaOngkirService`
- `app/Http/Requests`
	- validasi request per domain
- `app/Http/Resources`
	- transform output JSON API
- `app/Models`
	- model Eloquent + relasi
- `app/Mail`
	- email order created / status updated

## 10.2 Routing

- `routes/api.php`
	- public API + admin API
- `routes/web.php`
	- dynamic mapping URL -> file HTML di `public`
- `routes/auth.php`
	- auth bawaan Breeze

## 10.3 Data layer

- `database/migrations`
	- schema database
- `database/seeders`
	- seed data role/settings/category/tier/product/transaksi
- `database/data/dummy-data.json`
	- source data nama parfum untuk `ProductSeeder`

## 10.4 Frontend

- `public/*.html`
	- halaman publik toko
- `public/pages/*.html`
	- halaman informasi statis
- `public/js/api.js`
	- API client publik
- `public/js/cart.js`
	- logika cart localStorage
- `public/admin/*.html`
	- halaman admin panel
- `public/admin/js/admin-auth.js`
	- auth guard, layout nav, util admin
- `public/css/theme.css`
	- tema publik
- `public/admin/css/admin.css`
	- tema admin

## 10.5 Dokumentasi & Desain

- `docs/figma/tokens.json`
	- design tokens untuk referensi style/design
- `docs/DESIGN.md`
	- pedoman desain dan spesifikasi tema visual

---

## 11. Contoh Payload API

## 11.1 Checkout online

```json
{
	"customer_name": "Budi",
	"customer_email": "budi@mail.com",
	"customer_phone": "08123456789",
	"shipping_address": "Jl. Melati No. 1",
	"shipping_city": "Bandung",
	"shipping_province": "Jawa Barat",
	"shipping_postal_code": "40123",
	"rajaongkir_city_id": "23",
	"rajaongkir_province_id": "9",
	"shipping_courier": "jne",
	"shipping_service": "REG",
	"shipping_cost": 15000,
	"payment_method": "midtrans",
	"notes": "Tolong dikirim sore",
	"items": [
		{
			"product_id": 1,
			"product_variant_id": 3,
			"quantity": 2
		}
	]
}
```

## 11.2 Stock adjustment

```json
{
	"product_id": 1,
	"product_variant_id": 3,
	"type": "adjustment",
	"quantity": -2,
	"reference": "OPNAME-2026-05-08",
	"notes": "Koreksi hasil stok opname"
}
```

---

## 12. Catatan Penting Implementasi

1. Queue email
	 - Email order menggunakan queue.
	 - Jalankan worker (`php artisan queue:work`) agar email terkirim.

2. RajaOngkir/Midtrans toggle
	 - Integrasi aktif jika API key terisi dan setting enabled bernilai true.

3. Storage image
	 - Upload image kategori/produk disimpan di disk `public`.
	 - Wajib jalankan `php artisan storage:link`.

4. Dynamic HTML route
	 - `routes/web.php` memetakan URL langsung ke file HTML.
	 - Jika file tidak ada, response 404.

5. CORS
	 - `config/cors.php` sudah mengizinkan `APP_URL`, `FRONTEND_URL`, localhost, 127.0.0.1, devtunnels, ngrok.

---

## 13. Pengujian Dan Quality

Perintah umum:

```bash
php artisan test
./vendor/bin/phpunit
./vendor/bin/pint
```

---

## 14. Troubleshooting

## 14.1 Gagal akses gambar

- Pastikan sudah `php artisan storage:link`
- Pastikan `FILESYSTEM_DISK=public`

## 14.2 Midtrans tidak bisa create token

- Isi `MIDTRANS_SERVER_KEY` dan `MIDTRANS_CLIENT_KEY`, lalu set `MIDTRANS_ENABLED=true`
- Pastikan `MIDTRANS_IS_PRODUCTION` cocok dengan jenis API key
- Cek setting `midtrans_enabled` di menu admin settings
- Cek endpoint `/api/payment/config`
- Atur URL notifikasi Midtrans ke `https://domain-toko/api/payment/notification`

## 14.3 Ongkir kosong

- Cek `RAJAONGKIR_API_KEY`
- Cek setting `rajaongkir_enabled`
- Isi `store_city_id` atau `rajaongkir_origin` di settings

## 14.4 Email tidak terkirim

- Cek konfigurasi `MAIL_*`
- Jalankan queue worker
- Cek log di `storage/logs/laravel.log`

## 14.5 Port artisan serve bentrok

Jalankan di port lain:

```bash
php artisan serve --port=8080
```

---

## 15. Ringkasan Endpoint Penting

- Publik:
	- `GET /api/products`
	- `POST /api/checkout`
	- `GET /api/payment/config`
	- `GET /api/payment/snap/{orderNumber}`
	- `POST /api/payment/notification`
- Admin:
	- `POST /api/admin/auth/login`
	- `GET /api/admin/dashboard`
	- `apiResource /api/admin/products`
	- `GET /api/admin/orders` + `PATCH /api/admin/orders/{order}/status`
	- `POST /api/admin/stock/adjust`
	- `GET/POST /api/admin/settings`

---

## 16. Lisensi

Project mengikuti lisensi dari dependency masing-masing. Laravel framework berlisensi MIT.
