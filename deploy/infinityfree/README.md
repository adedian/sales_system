# Deploy ke InfinityFree

## 1. Build paket (di komputer lokal, XAMPP MySQL harus jalan)

```
php deploy/infinityfree/build.php --url=https://NAMA.infinityfreeapp.com --db-host=sqlXXX.infinityfree.com --db-name=if0_XXXX_sales --db-user=if0_XXXX --db-pass=PASSWORD_DB
```

Semua argumen opsional; yang kosong diisi placeholder dan bisa diedit di `.env` server.
Hasil di `deploy/_out/`: `htdocs/`, `sales-system-htdocs.zip`, `install.sql`, `CREDENTIALS.txt`.

## 2. Database (panel InfinityFree)
1. Control Panel → **MySQL Databases** → buat database (mis. `sales`). Catat host `sqlXXX.infinityfree.com`, nama DB `if0_XXXX_sales`, user `if0_XXXX`.
2. **phpMyAdmin** → pilih database tsb → **Import** → `install.sql`.

## 3. Upload file
Upload **isi** `deploy/_out/htdocs/` ke folder `htdocs` di hosting (FileZilla/FTP, atau Online File Manager lalu extract ZIP). `.htaccess` dan `.env` harus ikut (file berawalan titik).

## 4. Cek
- Buka `APP_URL`, login `superadmin` dengan password di `CREDENTIALS.txt`; wajib ganti saat login pertama.
- Akun lain dinonaktifkan di build ini (password default `ChangeMe@123` tidak boleh ke internet). Aktifkan + reset password lewat menu Pengguna.
- Pilih PHP 8.1+ di Control Panel → Software → PHP Version bila perlu.

## Keterbatasan InfinityFree
- Generator PDF PLTS (Python + proc_open) **tidak jalan**; folder `tools/` tidak diupload.
- Generate proposal AI & resolusi lokasi (cURL keluar) kemungkinan diblokir; `ANTHROPIC_API_KEY` dikosongkan.
- Tidak ada cron/SSH; ada batas hit & ukuran file. Cocok untuk demo/uji, bukan produksi berat.
