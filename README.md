# Absensi API (Laravel)

Backend REST API untuk aplikasi Flutter **Absensi Karyawan**. Dibangun dengan
Laravel 13, PHP 8.3+, dan SQLite (default). Autentikasi memakai **Laravel
Sanctum** (Bearer token).

## Kebutuhan

- PHP 8.3 atau lebih baru
- Composer 2.x
- SQLite (default) atau MySQL/PostgreSQL

## Instalasi

```bash
cd absensi_backend
composer install --no-dev
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve --host=0.0.0.0 --port=8000
```

Jika `database/database.sqlite` belum ada:

```bash
type nul > database\database.sqlite
```

Untuk MySQL, ubah `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=absensi
DB_USERNAME=root
DB_PASSWORD=
```

Lalu jalankan ulang `php artisan migrate --seed`.

## Akun Demo

| Peran | Email | Password |
|---|---|---|
| Admin | admin@absensi.com | admin123 |
| Atasan | dewi@absensi.com | 123456 |
| Karyawan | aditya@absensi.com | 123456 |
| Karyawan | budi@absensi.com | 123456 |
| Karyawan | citra@absensi.com | 123456 |
| Karyawan | dimas@absensi.com | 123456 |
| Karyawan | eko@absensi.com | 123456 |
| Karyawan | fajar@absensi.com | 123456 |
| Karyawan | rina@absensi.com | 123456 |
| Karyawan | nanda@absensi.com | 123456 |

Peran yang dikenal: `admin`, `supervisor`, `employee`. Admin dan supervisor
dianggap sebagai atasan (boleh meninjau pengajuan dan mengelola data).

## Autentikasi

```http
POST /api/auth/login
Content-Type: application/json

{
  "email": "admin@absensi.com",
  "password": "admin123",
  "device_name": "android"
}
```

Respons:

```json
{
  "data": {
    "token": "1|xxxxxxxxxxxxxxxxxxxxxxxx",
    "token_type": "Bearer",
    "user": { "id": 1, "name": "Administrator", "role": "admin" }
  }
}
```

Kirim token pada semua endpoint lain:

```http
Accept: application/json
Authorization: Bearer {token}
```

| Method | Endpoint | Keterangan |
|---|---|---|
| POST | `/api/auth/login` | Login, dapat token |
| POST | `/api/auth/logout` | Hapus token aktif |
| GET | `/api/auth/me` | Profil pengguna login |
| PUT | `/api/auth/profile` | Ubah nama/telepon/avatar |
| PUT | `/api/auth/password` | Ubah password |

## Format Respons

Sukses tunggal: `{ "data": { ... } }`
Sukses daftar: `{ "data": [ ... ] }`
Gagal validasi: `422` dengan `{ "message": "...", "errors": { ... } }`
Tidak terautentikasi: `401` · Tidak berizin: `403` · Tidak ditemukan: `404`

## Endpoint

### Pengguna & Pengaturan

| Method | Endpoint | Akses |
|---|---|---|
| GET | `/api/users?role=employee&search=&is_active=` | Atasan |
| POST | `/api/users` | Atasan |
| GET | `/api/users/{id}` | Sendiri/atasan |
| PUT | `/api/users/{id}` | Sendiri/atasan |
| DELETE | `/api/users/{id}` | Admin |
| GET | `/api/office-settings` | Semua |
| PUT | `/api/office-settings` | Admin |

### Absensi

| Method | Endpoint | Keterangan |
|---|---|---|
| GET | `/api/attendance/today` | Absensi hari ini |
| GET | `/api/attendances?from=&to=&employee_id=&status=&limit=` | Riwayat |
| POST | `/api/attendance/check-in` | Absen masuk |
| POST | `/api/attendance/check-out` | Absen pulang |
| GET | `/api/attendance/summary?date=YYYY-MM-DD` | Rekap harian |

Contoh absen masuk:

```json
{
  "latitude": -6.200000,
  "longitude": 106.816666,
  "note": "Hadir",
  "photo": "https://server/storage/uploads/attendance/foto.jpg"
}
```

`photo_file` (multipart) juga diterima bila ingin mengunggah langsung. Server
memvalidasi radius kantor, jam buka absen masuk, dan menandai status `late`
bila melewati jam shift + toleransi.

### Shift & Jadwal

| Method | Endpoint | Akses |
|---|---|---|
| GET | `/api/shifts` | Semua |
| POST | `/api/shifts` | Atasan |
| PUT | `/api/shifts/{id}` | Atasan |
| DELETE | `/api/shifts/{id}` | Atasan |
| GET | `/api/shift-assignments?from=&to=&employee_id=` | Sendiri/atasan |
| POST | `/api/shift-assignments` | Atasan |
| DELETE | `/api/shift-assignments/{id}` | Atasan |

Contoh plotting massal (`shift_id: null` menghapus plotting/libur):

```json
{
  "employee_id": 3,
  "shift_id": 1,
  "from": "2026-11-10",
  "to": "2026-11-16"
}
```

### Cuti & Izin

| Method | Endpoint | Akses |
|---|---|---|
| GET | `/api/leave-types` | Semua |
| GET | `/api/leave-balances?year=&employee_id=` | Sendiri/atasan |
| PUT | `/api/leave-balances` | Atasan |
| GET | `/api/leave-requests?scope=mine\|team&status=&year=&employee_id=` | Sendiri/atasan |
| POST | `/api/leave-requests` | Karyawan |
| GET | `/api/leave-requests/{id}` | Sendiri/atasan |
| PATCH | `/api/leave-requests/{id}/cancel` | Pemilik, status pending |
| PATCH | `/api/leave-requests/{id}/review` | Atasan |

```json
POST /api/leave-requests
{
  "leave_type_id": 1,
  "start_date": "2026-11-13",
  "end_date": "2026-11-14",
  "reason": "Acara keluarga di luar kota.",
  "attachment_url": null
}
```

```json
PATCH /api/leave-requests/12/review
{
  "status": "approved",
  "review_note": "Disetujui."
}
```

Persetujuan otomatis menambah `leave_balances.used_days`.

### Perbaikan Absensi

| Method | Endpoint | Akses |
|---|---|---|
| GET | `/api/attendance-corrections?scope=mine\|team&status=&employee_id=` | Sendiri/atasan |
| POST | `/api/attendance-corrections` | Karyawan |
| GET | `/api/attendance-corrections/{id}` | Sendiri/atasan |
| PATCH | `/api/attendance-corrections/{id}/cancel` | Pemilik, status pending |
| PATCH | `/api/attendance-corrections/{id}/review` | Atasan |

```json
POST /api/attendance-corrections
{
  "date": "2026-11-05",
  "correction_type": "check_out",
  "proposed_check_out_at": "2026-11-05T17:05:00+07:00",
  "reason": "Lupa absen pulang.",
  "evidence_url": null
}
```

`correction_type`: `check_in`, `check_out`, atau `both`. Persetujuan langsung
memperbarui (atau membuat) baris `attendances` pada tanggal tersebut.

### Penggajian

| Method | Endpoint | Akses |
|---|---|---|
| GET | `/api/salary-profiles?employee_id=` | Sendiri/atasan |
| POST/PUT | `/api/salary-profiles` | Atasan |
| GET/DELETE | `/api/salary-profiles/{id}` | Sendiri/atasan |
| GET | `/api/salary-allowances?employee_id=` | Sendiri/atasan |
| PUT/POST | `/api/salary-allowances` | Atasan (ganti seluruh daftar) |
| GET/POST | `/api/payroll-deduction-rules` | Atasan |
| PUT/DELETE | `/api/payroll-deduction-rules/{id}` | Atasan |
| GET | `/api/payroll-settings` | Atasan |
| PUT | `/api/payroll-settings` | Admin |
| GET | `/api/payroll-slips?year=&month=&employee_id=` | Sendiri/atasan |
| GET | `/api/payroll-slips/{id}` | Sendiri/atasan |
| POST | `/api/payroll-slips/finalize` | Atasan |
| DELETE | `/api/payroll-slips?year=&month=` | Admin (buka finalisasi) |

```json
POST /api/payroll-slips/finalize
{ "year": 2026, "month": 10 }
```

Perhitungan mengikuti aplikasi Flutter: gaji pokok + tunjangan + lembur,
potongan keterlambatan/absen/cuti tak dibayar/BPJS, dan PPh21 progresif
(PTKP, biaya jabatan 5% maks. Rp6 juta/tahun).

### Dashboard & Unggah

| Method | Endpoint | Keterangan |
|---|---|---|
| GET | `/api/dashboard/summary` | Ringkasan sesuai peran |
| POST | `/api/uploads` | Upload `file` + `folder` (jpg/png/webp/pdf, maks. 10 MB) |

Respons unggah:

```json
{
  "data": {
    "url": "http://localhost:8000/storage/uploads/attendance/foto.png",
    "name": "foto.png",
    "mime": "image/png",
    "size": 70
  }
}
```

## Menghubungkan Flutter

```bash
flutter run \
  --dart-define=USE_DUMMY_API=false \
  --dart-define=API_BASE_URL=http://10.0.2.2:8000/api
```

- Android emulator: `10.0.2.2`
- iOS simulator / web: `127.0.0.1`
- Perangkat fisik: alamat IP LAN komputer, mis. `http://192.168.1.10:8000/api`

HTTP lokal di Android memerlukan `android:usesCleartextTraffic="true"`.

## Paket Pihak Ketiga

- `laravel/sanctum` — token API (Bearer) untuk aplikasi mobile.

Peran pengguna disimpan di kolom `users.role`, sehingga tidak memerlukan paket
RBAC tambahan.

## SaaS / Multi-tenant

Fondasi SaaS tersedia: tabel `tenants`, `tenant_id` di seluruh tabel domain,
global scope per tenant, provisioning oleh super admin, trial/suspend, dan kuota
karyawan. **Tidak ada pendaftaran publik**; tenant hanya dibuat melalui
`/api/platform/tenants`.

Lihat [`SAAS_FOUNDATION.md`](SAAS_FOUNDATION.md) untuk arsitektur, contoh
provisioning, batasan (email unik global, shared database), dan rencana billing.
Riwayat implementasi ada di [`SAAS_HISTORY.md`](SAAS_HISTORY.md).

## Anti-Fraud & Kebijakan HR

### Autentikasi perangkat & jam server

| Method | Endpoint | Keterangan |
|---|---|---|
| POST | `/api/auth/login` | Body menyertakan `device_id`; respons berisi `server_time` |
| GET | `/api/time` | Jam server untuk sinkronisasi |
| POST | `/api/auth/face` | Unggah `photo` untuk mendaftarkan wajah |
| DELETE | `/api/auth/face` | Hapus data wajah |

Semua endpoint terautentikasi memerlukan header `X-Device-Id`. Bila binding
perangkat aktif, token hanya berlaku untuk device id yang sama.

### Absensi anti-fraud

`POST /api/attendance/check-in` dan `check-out` menerima:

```json
{
  "latitude": -6.2,
  "longitude": 106.816666,
  "is_mocked": false,
  "liveness_passed": true,
  "device_id": "abc123",
  "photo_file": "(multipart)"
}
```

Server menolak mock GPS (jika diblokir), liveness gagal, foto wajib yang tidak
ada, dan face match di luar ambang. Nilai `face_match_score` (0 = identik,
semakin besar semakin berbeda) disimpan di `attendances`.

Foto selfie absensi dan foto referensi wajah disimpan di disk privat
(`storage/app/private`), bukan URL publik.

### Multi-cabang

| Method | Endpoint | Akses |
|---|---|---|
| GET | `/api/office-locations` | Atasan |
| POST | `/api/office-locations` | Admin |
| PUT | `/api/office-locations/{id}` | Admin |
| DELETE | `/api/office-locations/{id}` | Admin |

Karyawan memiliki `office_location_id`. Jika kosong, cabang default dipakai.
`GET /office-settings` mengembalikan kebijakan global + lokasi efektif
pengguna.

### Hari libur

| Method | Endpoint | Akses |
|---|---|---|
| GET | `/api/holidays?year=&office_location_id=` | Semua |
| POST | `/api/holidays` | Admin |
| PUT/DELETE | `/api/holidays/{id}` | Admin |

Hari libur tidak dihitung sebagai hari kerja pada cuti dan payroll.

### Lembur

| Method | Endpoint | Akses |
|---|---|---|
| GET | `/api/overtime-requests?scope=mine\|team&status=&year=&month=` | Sendiri/atasan |
| POST | `/api/overtime-requests` | Karyawan |
| PATCH | `/api/overtime-requests/{id}/cancel` | Pemilik |
| PATCH | `/api/overtime-requests/{id}/review` | Atasan |

Bila `overtime_requires_approval` aktif, payroll memakai total menit lembur
yang berstatus `approved`.

### Notifikasi

| Method | Endpoint |
|---|---|
| GET | `/api/notifications?unread_only=1` |
| PATCH | `/api/notifications/{id}/read` |
| PATCH | `/api/notifications/read-all` |

Notifikasi dibuat otomatis saat pengajuan cuti/perbaikan/lembur dibuat,
dibatalkan, atau ditinjau.

### Laporan & audit

| Method | Endpoint | Akses |
|---|---|---|
| GET | `/api/reports/attendance?from=&to=&employee_id=` | Atasan (CSV) |
| GET | `/api/reports/payroll?year=&month=` | Atasan (CSV) |
| GET | `/api/audit-logs?from=&to=&method=&limit=` | Admin |

Middleware audit mencatat setiap `POST/PUT/PATCH/DELETE` (pengguna, path,
status, IP, user agent, payload tanpa password/berkas).

### Field kebijakan kantor

`PUT /api/office-settings` mendukung `late_tolerance_minutes`,
`face_match_required`, `face_match_threshold`, `overtime_requires_approval`,
`device_binding_enabled`, `selfie_required`, dan `mock_location_blocked`.

## Catatan Keamanan

- Liveness diverifikasi di aplikasi (client-attested) dan dicatat server.
- Face match memakai perbandingan hash gambar (dHash); bukan pengganti SDK
  biometrik bersertifikasi.
- Untuk produksi: gunakan HTTPS, batasi CORS, rotasi token, dan backup
  database secara berkala.
- Halaman publik untuk Google Play: `/privacy.html` dan
  `/data-deletion.html`. Ganti email/alamat placeholder sebelum publish.
