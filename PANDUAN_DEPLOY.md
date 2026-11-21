# Panduan Deploy SPK Rangkul — https://rangkul.spk.or.id

**Diperbarui:** 20 November 2026
**Hosting:** Hostinger **Cloud Startup** (hPanel)
**Website:** `rangkul.spk.or.id` — website tersendiri di hPanel, **terhubung ke GitHub** (`yoezron/rangkul-spk`,
cabang `main`), dipasang ke folder **`public_html`** website itu.

SPK Rangkul adalah aplikasi Laravel 13 dengan **basis data sendiri**. Rangkul tidak membaca basis data
spk.or.id; keduanya hanya terhubung lewat:

- **SSO** — tombol "Masuk ke SPK Rangkul" di spk.or.id mengirim token bertanda tangan ke `rangkul.spk.or.id/sso/spk`;
- **dua endpoint server-ke-server** di spk.or.id — `rangkul/anggota` dan `rangkul/cari`.

Kontrak lengkapnya: `PANDUAN_DEPLOY_RANGKUL_SSO.md` di repo spk-or-id, bagian 3 dan 4.

> Data Rangkul sangat sensitif (cerita konseling). Ikuti panduan ini apa adanya, terutama bagian
> **.htaccess akar**, **APP_DEBUG**, **APP_KEY**, dan **daftar periksa keamanan**.

---

## ⚠ Tindakan segera (keadaan per 20 November 2026)

Pemeriksaan dari luar terhadap `https://rangkul.spk.or.id` menemukan:

| Temuan | Risiko | Perbaikan |
|---|---|---|
| `https://rangkul.spk.or.id/composer.json` dan `/composer.lock` dapat diunduh; berkas PHP di repo (mis. `/routes/web.php`) dijalankan langsung | Seluruh isi repo berada di folder web; kelak `storage/logs`, basis data SQLite, atau berkas lain bisa ikut terbuka | Commit & push berkas **`.htaccess` di akar repo** (sudah disiapkan), lalu Redeploy — bagian 4.1 |
| `/public/` menampilkan **halaman galat debug Laravel** lengkap dengan jejak program | `APP_DEBUG` menyala di produksi: jalur server dan detail galat terbuka untuk publik | Segera ubah `.env` di server: `APP_DEBUG=false` — bagian 5 |
| Galatnya berasal dari koneksi basis data | `.env` kemungkinan masih salinan `.env.example` (SQLite) | Isi `.env` produksi dengan MySQL — bagian 5 |

Endpoint spk.or.id produksi (`rangkul/anggota` dan `rangkul/cari`) sudah naik dan menolak permintaan tanpa
tanda tangan dengan benar.

---

## Cara kerja deploy GitHub Hostinger — dan akibatnya bagi Laravel

| Fakta (dokumentasi Hostinger) | Akibatnya |
|---|---|
| Isi repo **disalin ke `public_html`**, menimpa berkas yang sama pada setiap deploy. | Akar repo = folder web. Berkas `.htaccess` di akar repo mengarahkan semua permintaan ke `public/` dan menolak berkas aplikasi. |
| Bila ada `composer.json`, Hostinger menjalankan **`composer install` otomatis** pada setiap deploy — terbukti dari `vendor/` yang muncul di server walaupun tidak ikut git. Pemasangan itu **ikut membawa dependensi pengembangan** (`phpunit`, `fakerphp`, `laravel/boost`). Selain itu tidak ada langkah build (npm) maupun perintah pasca-deploy. | Setelah setiap deploy, jalankan sendiri lewat SSH: `composer install --no-dev` (membuang paket pengembangan), migrasi, dan `artisan optimize`. CSS (`public/build`) sudah ikut git. |
| Dokumentasi tidak menjelaskan nasib berkas yang **tidak ada di git** (`.env`, `vendor/`, isi `storage/`). | Uji sekali (bagian 4.3) dan **selalu cadangkan `.env`**. |
| *Auto-deployment* menyalin kode baru begitu ada push ke `main`. | **Matikan auto-deployment.** Kode baru tanpa `composer install`/migrasi bisa langsung merusak layanan; deploy lewat tombol **Redeploy**, lalu jalankan perintah SSH. |
| `php -v` di SSH menunjukkan versi PHP bawaan paket, bukan versi website. | Perintah SSH dan cron memakai biner `/opt/alt/php84/usr/bin/php` secara eksplisit. |

---

## 0. Urutan singkat

| # | Langkah | Bagian |
|---|---|---|
| 1 | Aktifkan SSH; siapkan PHP 8.4 dan Composer di SSH | 1 |
| 2 | Pastikan sisi spk.or.id sudah lengkap (endpoint sudah naik) | 2 |
| 3 | Di hPanel: matikan auto-deployment, buat basis data MySQL | 3 |
| 4 | Push `.htaccess` akar → Redeploy → `composer install` lewat SSH | 4 |
| 5 | Isi `.env` produksi (`APP_DEBUG=false`), `APP_KEY` sekali, **cadangkan** | 5 |
| 6 | Migrasi dan optimasi | 6 |
| 7 | Cron tiap menit (wajib) | 7 |
| 8 | Uji teknis | 8 |
| 9 | Konfigurasi awal di dashboard, lalu buka layanan | 9 |
| 10 | Uji alur nyata dari spk.or.id | 10 |

---

## 1. Persiapan SSH

1. hPanel → website `rangkul.spk.or.id` → **Advanced → SSH Access** → aktifkan; catat IP, port, dan nama pengguna.
2. Masuk lewat SSH, lalu periksa:

   ```bash
   echo $HOME                                         # mis. /home/u123456789
   ls ~/domains/                                      # harus ada rangkul.spk.or.id
   ls /opt/alt/ | grep php                            # harus ada php84
   /opt/alt/php84/usr/bin/php -v                      # PHP 8.4.x
   ls /usr/local/bin/ | grep composer                 # biasanya composer2
   ```

3. Simpan variabel berikut untuk sesi SSH (ulangi tiap kali masuk):

   ```bash
   export PHP=/opt/alt/php84/usr/bin/php
   export KOMPOSER="$PHP /usr/local/bin/composer2"
   export APP="$HOME/domains/rangkul.spk.or.id/public_html"
   cd "$APP"
   ```

   Jangan menamai variabelnya `COMPOSER`: Composer membaca variabel lingkungan `COMPOSER` sebagai nama berkas
   `composer.json`, sehingga muncul galat *"Composer could not find the config file"*.

**PHP 8.4 wajib:** `composer.lock` mengunci Symfony 8.1 yang menuntut PHP ≥ 8.4.1. Laravel di situs sudah
berjalan sampai ke koneksi basis data, artinya PHP web website ini sudah memenuhi syarat. Bila suatu saat PHP web
turun di bawah 8.4, lihat bagian 14.

---

## 2. Sisi spk.or.id

Per 20 November 2026 endpoint `rangkul/anggota` dan `rangkul/cari` sudah naik. Pastikan juga:

1. `.env` spk.or.id berisi:

   ```
   RANGKUL_URL=https://rangkul.spk.or.id/
   RANGKUL_SSO_SECRET=<rahasia produksi, minimal 32 karakter>
   ```

   Buat rahasia **baru** khusus produksi bila belum (jangan memakai nilai lokal):

   ```bash
   $PHP -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
   ```

2. Menu *Anggota Serikat → SPK Rangkul* sudah ada (skrip `migration_rangkul_2026-11-17.sql`).
3. Rahasia yang sama nanti diisikan ke `SPK_SSO_SECRET` di `.env` Rangkul (bagian 5). Simpan di pengelola kata
   sandi; jangan dikirim lewat WhatsApp atau surel.

---

## 3. hPanel

1. **Git / GitHub** (kartu "Connected with GitHub"):
   - Cabang: `main`. Root directory: `public_html` (biarkan).
   - **Matikan Auto-deployment** (menu tarik di sebelah lencana *Auto-deployment*). Deploy dilakukan dengan tombol
     **Redeploy** setelah semuanya siap (bagian 11).
2. **SSL**: sudah aktif (lencana *SSL*). Pastikan *Force HTTPS* menyala.
3. **Database → Manage**: buat basis data dan pengguna MySQL baru, mis. `u123456789_rangkul`. Catat nama basis
   data, pengguna, kata sandi, dan host (biasanya `localhost`).
4. Repo GitHub harus **privat**. Isinya tidak memuat rahasia, tetapi tetap tidak untuk publik.

---

## 4. Memasang kode

### 4.1 `.htaccess` di akar repo

Repo kini berisi berkas **`.htaccess` di akarnya** (di samping `composer.json`). Karena deploy menyalin seluruh
repo ke `public_html`, berkas ini:

- mengarahkan semua permintaan ke `public/` — satu-satunya folder yang boleh dilayani;
- menolak `.env`, berkas log, SQLite, `composer.*`, `*.md`, dan folder `app/`, `config/`, `database/`,
  `routes/`, `storage/`, `vendor/`, dll. sebagai lapis kedua.

Commit dan push dari komputer lokal, lalu klik **Redeploy** di hPanel. Periksa:

```bash
curl -so /dev/null -w "%{http_code}\n" https://rangkul.spk.or.id/composer.json    # 404 (sebelumnya 200)
curl -so /dev/null -w "%{http_code}\n" https://rangkul.spk.or.id/routes/web.php   # 404
```

**Jangan pernah menghapus atau mengubah `.htaccess` akar ini** tanpa alasan kuat. Tanpa berkas ini, isi repo
kembali terbuka.

### 4.2 Pasang dependensi PHP

Deploy Hostinger sudah menjalankan `composer install`, tetapi **dengan** paket pengembangan. Pasang ulang tanpa
paket itu — wajib setelah setiap deploy:

```bash
cd "$APP"
ls .htaccess public/build/manifest.json            # keduanya harus ada (dibawa deploy)
$KOMPOSER install --no-dev --optimize-autoloader --no-interaction
ls -d vendor/phpunit vendor/fakerphp vendor/laravel/boost 2>&1   # ketiganya: No such file or directory
chmod -R ug+rwX storage bootstrap/cache
```

### 4.3 Uji sekali: apakah berkas di luar git bertahan setelah deploy?

Dokumentasi Hostinger tidak menjelaskan apakah deploy menghapus berkas yang tidak ada di git (`.env`, `vendor/`,
isi `storage/`). Uji sebelum layanan dibuka:

```bash
echo uji > "$APP/storage/app/penanda-deploy.txt"
```

Klik **Redeploy** di hPanel, lalu:

```bash
ls -la "$APP/storage/app/penanda-deploy.txt" "$APP/vendor/autoload.php" "$APP/.env"
rm -f "$APP/storage/app/penanda-deploy.txt"
```

- **Ketiganya masih ada** → aman; lanjutkan seperti panduan ini.
- **Ada yang hilang** → deploy menghapus folder. Jangan pakai deploy GitHub untuk produksi. Hubungi dukungan
  Hostinger, atau ubah *Root directory* deploy ke subfolder terpisah dan pasang `.env` serta `storage/` di luar
  jangkauan deploy. Pulihkan `.env` dari cadangan.

---

## 5. Isi `.env` produksi

`.env` berada di `$APP/.env`, dilindungi `.htaccess` akar dan aturan Hostinger untuk berkas bertitik.

```bash
cd "$APP"
cp -n .env.example .env        # hanya bila .env belum ada
chmod 600 .env
nano .env
```

Isi seperti ini (ganti nilai bertanda `<…>`):

```dotenv
APP_NAME="SPK Rangkul"
APP_ENV=production
APP_KEY=<biarkan bila sudah terisi; lihat 5.1>
APP_DEBUG=false
APP_URL=https://rangkul.spk.or.id
APP_LOCALE=id
APP_FALLBACK_LOCALE=id
APP_TIMEZONE=Asia/Jakarta

LOG_CHANNEL=stack
LOG_STACK=daily
LOG_DAILY_DAYS=14
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=<u123456789_rangkul>
DB_USERNAME=<u123456789_rangkul>
DB_PASSWORD=<kata sandi basis data>

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_PATH=/
SESSION_DOMAIN=null

CACHE_STORE=database
QUEUE_CONNECTION=database

# Surel diatur dari dashboard (Pengaturan → Surel Brevo). Sampai diaktifkan di sana,
# 'array' membuang surel diam-diam. Jangan pakai 'log': isi surel dan alamat penerima
# akan tertulis di berkas log.
MAIL_MAILER=array
MAIL_FROM_ADDRESS="rangkul@spk.or.id"
MAIL_FROM_NAME="SPK Rangkul"

SPK_URL=https://spk.or.id/
SPK_SSO_SECRET=<sama persis dengan RANGKUL_SSO_SECRET di .env spk.or.id>
SPK_PERAN_SUPERADMIN=Administrator
```

Catatan:

- **`APP_DEBUG=false` wajib.** Dengan `true`, setiap galat menampilkan jejak program kepada siapa pun.
- `SESSION_DOMAIN` **harus** `null`, bukan `.spk.or.id`, supaya cookie sesi Rangkul tidak terbagi dengan spk.or.id.
- `SPK_URL` wajib `https://`. Di produksi Rangkul menolak memanggil spk.or.id lewat `http://`.
- Siapa pun yang berperan **Administrator** di spk.or.id otomatis menjadi superadmin Rangkul.

### 5.1 APP_KEY — buat sekali, cadangkan, jangan pernah diganti

```bash
grep ^APP_KEY= .env
```

- **Kosong** → buat: `$PHP artisan key:generate --force`.
- **Sudah terisi** dan belum ada data konseling sungguhan → boleh dipakai terus. Jangan dibuat ulang setelah
  layanan dibuka.

Cerita, jawaban asesmen, pesan, catatan konselor, sandi SMTP, dan token Fonnte **dienkripsi dengan APP_KEY**.

- **Cadangkan** baris `APP_KEY=…` (dan seluruh `.env`) di pengelola kata sandi organisasi, terpisah dari cadangan
  basis data.
- **Jangan** menjalankan `key:generate` lagi setelah ada data. APP_KEY yang hilang atau berganti membuat seluruh
  isi konseling **tidak dapat dibaca selamanya**.

---

## 6. Migrasi dan optimasi

```bash
cd "$APP"
$PHP artisan migrate --force
$PHP artisan optimize
$PHP artisan about --only=environment        # Environment: production, Debug Mode: OFF
$PHP artisan migrate:status
```

Migrasi membuat seluruh tabel, peran bawaan (Administrator Rangkul, Peer Konselor, Komite Rangkul), dan lima
instrumen asesmen. Yang aktif di formulir hanya PHQ-9, GAD-7, dan burnout CBI.

`optimize` menyimpan konfigurasi dan rute dalam cache. Setiap kali `.env` diubah atau kode baru dipasang,
jalankan lagi `$PHP artisan optimize`.

---

## 7. Cron (wajib)

Tanpa cron, **notifikasi surel/WhatsApp tidak pernah terkirim** (semuanya masuk antrean), pengingat pesan
24 jam tidak berjalan, dan audit log tidak dipangkas.

hPanel → **Advanced → Cron Jobs** → *Custom*, tiap menit (`* * * * *`). Ganti `u123456789`:

```bash
/opt/alt/php84/usr/bin/php /home/u123456789/domains/rangkul.spk.or.id/public_html/artisan schedule:run >> /dev/null 2>&1
```

- Pakai biner `/opt/alt/php84/...`, bukan `php` polos yang mengikuti versi paket.
- Tulis jalur lengkap ke `artisan` **tanpa awalan `cd … &&`**. Cron hPanel berbentuk `cd … && php artisan …`
  terbukti tidak pernah menjalankan penjadwal (antrean menumpuk), walaupun perintah yang sama berhasil dari SSH.
  Laravel tidak membutuhkan `cd`: penjadwal menjalankan tugasnya dari folder aplikasi.
- `crontab -l` di SSH menampilkan *no crontab*: itu wajar, cron hPanel disimpan di tempat lain.

**Memastikan cron benar-benar berjalan.** Untuk sementara ganti `>> /dev/null 2>&1` dengan
`>> /home/u123456789/cron-rangkul.log 2>&1`. Dua menit kemudian *Lihat Output* di hPanel atau
`tail -5 ~/cron-rangkul.log` harus berisi *Running ['artisan' queue:work …] DONE* setiap menit. Setelah
terbukti, kembalikan ke `/dev/null` dan hapus lognya.

| Tugas terjadwal | Kapan |
|---|---|
| Mengosongkan antrean notifikasi (`queue:work --stop-when-empty`) | tiap menit |
| Pengingat WhatsApp pesan belum dibuka/dibalas > 2 jam (`rangkul:ingatkan-pesan-tertunda`) | tiap 10 menit |
| Pengingat pesan belum dibaca > 24 jam (`rangkul:ingatkan-pesan`) | tiap jam |
| Laporan ke komite: kasus tanpa aktivitas > 48 jam (`rangkul:ingatkan-kasus-menggantung`) | tiap jam (menit ke-15) |
| Pemangkasan audit log (`rangkul:pangkas-audit`) | tiap hari 02.30 WIB |

Periksa beberapa menit kemudian:

```bash
$PHP artisan schedule:list
$PHP artisan queue:failed
$PHP artisan tinker --execute 'echo DB::table("jobs")->count(), " menunggu", PHP_EOL;'   # harus 0 atau mendekati 0
```

---

## 8. Uji teknis

```bash
curl -sI https://rangkul.spk.or.id/ | head -1                                       # 200 (halaman depan)
curl -sI http://rangkul.spk.or.id/ | grep -i location                               # dialihkan ke https://
curl -so /dev/null -w "%{http_code}\n" https://rangkul.spk.or.id/beranda            # 302 (ke /masuk)
for f in .env composer.json composer.lock routes/web.php storage/logs/ vendor/autoload.php artisan; do
  printf "%-22s " "$f"; curl -so /dev/null -w "%{http_code}\n" "https://rangkul.spk.or.id/$f"   # semuanya 404/403
done
curl -s https://rangkul.spk.or.id/halaman-yang-tidak-ada | grep -c "vendor/laravel"  # 0 (tanpa jejak program)
```

Uji sambungan Rangkul → spk.or.id dari server (hanya mencetak *true/false*, tanpa data pribadi):

```bash
cd "$APP"
$PHP artisan tinker --execute 'var_dump(app(App\Services\Spk\KlienSpk::class)->siap());'
$PHP artisan tinker --execute 'var_dump(app(App\Services\Spk\KlienSpk::class)->anggota(<id akun admin Anda>)->berhak);'
```

Keduanya harus `bool(true)`. Bila galat, lihat bagian 14.

---

## 9. Konfigurasi awal di dashboard

1. Login di **https://spk.or.id** sebagai akun berperan Administrator → *Anggota Serikat → SPK Rangkul* →
   **Masuk ke SPK Rangkul**. Anda mendarat di `/beranda` sebagai superadmin.
2. Buka **Dashboard pengelola**, lalu atur berurutan:

   | Menu | Yang dilakukan |
   |---|---|
   | **Layanan & audit log** | **Tutup layanan sementara** sampai semua langkah di bawah selesai. Atur masa simpan audit log. |
   | **Surel (Brevo)** | Isi SMTP Brevo (login dan SMTP key) dan alamat pengirim yang **sudah terverifikasi di Brevo**, aktifkan, lalu **Kirim surel uji**. |
   | **WhatsApp (Fonnte)** | Isi token perangkat, aktifkan, **Periksa perangkat**, lalu **Kirim pesan uji**. |
   | **Halaman depan & kontak** | Sesuaikan teks halaman depan, isi surel/WhatsApp/jam layanan tim, dan **pesan keadaan darurat** (nomor krisis yang benar-benar dirujuk). |
   | **Pengguna & penugasan** | Cari anggota menurut nama, lalu tugaskan peran **Komite Rangkul** dan **Peer Konselor**. |
   | **Asesmen dasar** | Komite meninjau teks instrumen. Teks bawaan adalah terjemahan kerja; ganti dengan versi bahasa Indonesia yang tervalidasi bila ada. |
   | **Layanan & audit log** | **Buka layanan.** |

3. Di Brevo, matikan pelacakan buka/klik surel transaksional, dan autentikasi domain `spk.or.id` (DKIM/DMARC)
   supaya surel tidak masuk spam.

---

## 10. Uji alur nyata

Gunakan satu akun anggota uji yang terkonfirmasi di spk.or.id.

1. Anggota uji masuk dari spk.or.id → **Ajukan permintaan bantuan** → isi keenam langkah → kirim.
2. Dalam ±1 menit, anggota Komite menerima surel/WhatsApp "Ada permintaan layanan baru".
3. Komite membuka permintaan → menugaskan peer konselor → konselor dan anggota menerima notifikasi.
4. Konselor dan anggota bertukar pesan. Pesan muncul tanpa memuat ulang (±10 detik).
5. Konselor mengusulkan pertemuan daring → anggota setuju → Komite menyetujui → tautan tampil bagi anggota.
6. Konselor menulis catatan sesi → mengusulkan penutupan → anggota setuju → Komite menyetujui.
7. Periksa **Audit log** dan **Rekap & unduhan**.
8. Tutup permintaan uji itu, lalu beri tahu Komite bahwa layanan siap.

---

## 11. Deploy pembaruan berikutnya

Di komputer lokal:

```bash
npm run build          # bila ada perubahan tampilan; hasilnya (public/build) ikut di-commit
php artisan test
git push               # ke main
```

Di server, dengan auto-deployment **mati**:

```bash
export PHP=/opt/alt/php84/usr/bin/php
export KOMPOSER="$PHP /usr/local/bin/composer2"
export APP="$HOME/domains/rangkul.spk.or.id/public_html"
cd "$APP"
$PHP artisan down --refresh=15
```

Klik **Redeploy** di hPanel, tunggu sampai *Completed*, lalu:

```bash
cd "$APP"
$KOMPOSER install --no-dev --optimize-autoloader --no-interaction
$PHP artisan migrate --force
$PHP artisan optimize
$PHP artisan queue:restart
$PHP artisan up
```

---

## 12. Cadangan dan pemulihan

| Yang dicadangkan | Cara | Seberapa sering |
|---|---|---|
| Basis data Rangkul | Backup otomatis Hostinger + ekspor manual sebelum tiap deploy | harian / tiap deploy |
| `APP_KEY` dan `.env` | Pengelola kata sandi organisasi, **terpisah** dari cadangan basis data | tiap ada perubahan |

```bash
mkdir -p ~/cadangan && chmod 700 ~/cadangan
mysqldump -u <user> -p --single-transaction <db> | gzip > ~/cadangan/rangkul-$(date +%F).sql.gz
cp "$APP/.env" ~/cadangan/env-$(date +%F) && chmod 600 ~/cadangan/*
```

Simpan cadangan di `~/cadangan` (folder rumah, **di luar** `public_html`), jangan di dalam `$APP`. Cadangan basis
data tanpa APP_KEY tidak berguna untuk isi konseling, dan sebaliknya.

---

## 13. Daftar periksa keamanan

- [ ] `.htaccess` akar repo terpasang; `composer.json`, `composer.lock`, `routes/web.php`, `.env` → 404/403.
- [ ] `APP_ENV=production`, `APP_DEBUG=false`; halaman galat tidak memuat jejak program (`vendor/laravel`).
- [ ] Auto-deployment dimatikan; uji bertahannya berkas di luar git (4.3) sudah lulus.
- [ ] Tidak ada paket pengembangan di server: `ls -d vendor/phpunit` → *No such file or directory*.
- [ ] SSL aktif dan *Force HTTPS* menyala.
- [ ] `SESSION_SECURE_COOKIE=true`, `SESSION_DOMAIN=null`.
- [ ] `SPK_SSO_SECRET` produksi baru (bukan nilai lokal) dan sama dengan `RANGKUL_SSO_SECRET` spk.or.id.
- [ ] `APP_KEY` dan `.env` dicadangkan di pengelola kata sandi; cadangan berada di luar `public_html`.
- [ ] `MAIL_MAILER` bukan `log`.
- [ ] Repo GitHub privat.
- [ ] `.env` ber-izin `600`.
- [ ] Cron berjalan (`schedule:list` dan notifikasi uji terkirim).
- [ ] Pelacakan buka/klik di Brevo dimatikan.

---

## 14. Pemecahan masalah

| Gejala | Penyebab umum | Tindakan |
|---|---|---|
| `composer.json` atau berkas repo lain bisa diunduh | `.htaccess` akar belum terdeploy atau terhapus | Pastikan `$APP/.htaccess` ada dan sama dengan versi di repo; Redeploy. |
| Halaman galat menampilkan jejak program | `APP_DEBUG=true` | Ubah ke `false`, lalu `$PHP artisan optimize`. |
| Galat 500 "Class not found" / `vendor/autoload.php` tidak ada | `composer install` belum dijalankan setelah deploy | Bagian 4.2. |
| `vendor/phpunit` atau `vendor/laravel/boost` ada di server | Deploy Hostinger memasang dependensi pengembangan | `$KOMPOSER install --no-dev --optimize-autoloader --no-interaction`, lalu `$PHP artisan optimize`. |
| `.env` atau `vendor/` hilang setelah Redeploy | Deploy menghapus berkas di luar git | Pulihkan `.env` dari cadangan; lihat bagian 4.3. |
| Galat versi PHP / "Composer detected issues" di web | PHP web website < 8.4 | Ubah versi PHP di hPanel ke 8.4. Bila versi paket harus tetap rendah demi spk.or.id, tambahkan di **awal** `.htaccess` akar repo: `<IfModule LiteSpeed>` + blok `<FilesMatch ".(php)$"> SetHandler application/x-lsphp84 </FilesMatch>` + `</IfModule>`, lalu commit, push, dan Redeploy. |
| `composer install` gagal karena versi PHP | Composer dijalankan dengan PHP bawaan paket | Pakai `$KOMPOSER` dari bagian 1. |
| Galat basis data | `.env` masih SQLite / kredensial MySQL salah | Bagian 5; lalu `$PHP artisan optimize`. |
| Login → "Tautan masuk tidak berlaku" | Rahasia kedua `.env` berbeda, atau tautan sudah dipakai/lebih dari 2 menit | Samakan `SPK_SSO_SECRET` = `RANGKUL_SSO_SECRET`; `$PHP artisan optimize`. |
| "spk.or.id sedang tidak dapat dihubungi" | `SPK_URL` salah atau rahasia berbeda (401) | Bagian 2 dan uji bagian 8. |
| Notifikasi tidak terkirim | Cron belum jalan atau memakai `php` polos, SMTP/Fonnte belum aktif, perangkat Fonnte terputus | Bagian 7; `schedule:list`, `queue:failed`; kirim uji dari dashboard. |
| Perubahan `.env` atau kode tidak berpengaruh | Konfigurasi/rute masih ter-cache | `$PHP artisan optimize:clear && $PHP artisan optimize`. |
| Tautan di surel berawalan `http://` | `APP_URL` salah | `APP_URL=https://rangkul.spk.or.id`, lalu `$PHP artisan optimize`. |

---

## 15. Rollback

Folder deploy bukan salinan git, jadi rollback dilakukan lewat GitHub:

```bash
# di komputer lokal
git revert <commit-bermasalah>
git push
```

Lalu di server: `$PHP artisan down` → **Redeploy** → `composer install`, `optimize` (bagian 11) → `$PHP artisan up`.

Bila pembaruan yang gagal menyertakan migrasi, **jangan** menjalankan `migrate:rollback` tanpa memeriksa isi
migrasinya. Pulihkan dari cadangan basis data sebelum deploy, dengan `APP_KEY` yang sama.

---

## Rujukan Hostinger

- [Git deployment untuk website](https://docs.hostinger.com/websites/git)
- [Deploy website dari GitHub atau GitLab](https://www.hostinger.com/support/1583302-how-to-deploy-a-git-repository-in-hostinger/)
- [Mengganti versi PHP (termasuk PHP CLI di `/opt/alt`)](https://www.hostinger.com/tutorials/how-to-change-your-php-version/)
- [Mengganti versi PHP untuk subfolder atau subdomain (`SetHandler`)](https://www.hostinger.com/support/4047803-how-to-change-the-php-version-for-subfolders-or-subdomains-in-hostinger/)
- [Masalah umum Composer di Hostinger](https://www.hostinger.com/support/5792082-how-to-solve-common-composer-issues-at-hostinger/)
