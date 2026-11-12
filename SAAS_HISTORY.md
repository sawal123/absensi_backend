# Riwayat Implementasi Fondasi SaaS

**Versi:** `0.1.0-saas-foundation`  
**Tanggal:** 12 November 2026  
**Status:** Fondasi selesai, **pendaftaran publik dimatikan**  
**Lokasi:** `absensi_backend`

---

## 1. Ringkasan

Aplikasi Absensi Karyawan awalnya adalah aplikasi **single-tenant** (satu
instalasi untuk satu perusahaan). Pada sesi ini ditambahkan fondasi SaaS
multi-tenant dengan pendekatan **shared database, shared schema**: satu
database, setiap tabel domain memiliki `tenant_id`, dan isolasi dilakukan di
level aplikasi.

Keputusan penting: **pendaftaran publik belum dibuka**. Tenant baru hanya dapat
dibuat oleh `super_admin` melalui endpoint platform. Mobile tidak memiliki menu
signup, dan akun super admin tidak dapat memakai aplikasi mobile.

Infrastruktur tetap ringan: tidak perlu database terpisah per tenant, Redis,
queue, atau layanan tambahan untuk tahap ini.

---

## 2. Latar belakang dan batasan

| Hal | Kondisi |
|---|---|
| Infrastruktur | Terbatas; belum siap database/instance per tenant |
| Model bisnis awal | Satu perusahaan per instalasi |
| Permintaan | Buat fondasi SaaS, tetapi jangan buka pendaftaran publik |
| Mobile | Satu aplikasi, banyak perusahaan |
| Backend | Laravel 13 + Sanctum, SQLite/MySQL |
| Registrasi | Tidak boleh ada self-service signup di production |

Tujuan sesi ini bukan billing penuh, tetapi **fondasi isolasi tenant,
provisioning, dan kontrol platform** yang aman untuk dikembangkan.

---

## 3. Keputusan arsitektur (ADR)

### ADR-001 — Shared database + `tenant_id`

- **Dipilih:** satu database, `tenant_id` di setiap tabel domain.
- **Alternatif:** database-per-tenant.
- **Alasan:** infrastruktur terbatas, migrasi lebih sederhana, biaya operasional
  rendah.
- **Konsekuensi:** isolasi bergantung pada global scope dan disiplin query;
  belum ada isolasi resource/backup per tenant. Jalur upgrade ke
  database-per-tenant tetap terbuka.

### ADR-002 — Super admin platform terpisah

- **Dipilih:** role `super_admin` dengan `tenant_id = null`.
- **Alasan:** provisioning tenant tidak boleh dilakukan admin tenant.
- **Konsekuensi:** super admin memakai API/panel platform, bukan mobile.

### ADR-003 — Email unik global (sementara)

- **Dipilih:** satu email hanya boleh terdaftar di satu tenant.
- **Alasan:** menghindari migrasi indeks unik yang kompleks dan menjaga login
  cukup dengan email + password.
- **Konsekuensi:** satu orang belum bisa menjadi anggota beberapa tenant.
  Tabel membership dapat ditambahkan nanti.

### ADR-004 — Tenant context + global scope

- **Dipilih:** `TenantContext` statis + trait `BelongsToTenant` dengan global
  scope.
- **Alasan:** semua model otomatis terfilter tanpa mengubah banyak controller.
- **Konsekuensi:** context harus di-reset tiap request dan di-set sebelum route
  model binding.

### ADR-005 — Middleware tenant sebelum route model binding

- **Dipilih:** `ResolveTenant` dimasukkan ke priority list sebelum
  `SubstituteBindings`.
- **Alasan:** mencegah akses data tenant lain lewat ID pada route `{user}`,
  `{leaveRequest}`, dan sejenisnya.
- **Bukti uji:** akses user tenant lain menghasilkan `404`.

### ADR-006 — Provisioning hanya lewat super admin

- **Dipilih:** endpoint `/api/platform/tenants`, tanpa route registrasi publik.
- **Alasan:** permintaan eksplisit; onboarding masih manual/terkontrol.
- **Konsekuensi:** belum ada self-service trial signup.

### ADR-007 — Billing hanya berupa field

- **Dipilih:** `plan`, `status`, `trial_ends_at`, `subscription_ends_at`,
  `max_employees`, `features`.
- **Alasan:** payment gateway belum dibutuhkan untuk fondasi.
- **Konsekuensi:** invoice, webhook, dan penagihan otomatis belum ada.

---

## 4. Perubahan skema database

### 4.1 Tabel baru: `tenants`

Kolom utama:

- `id`
- `name`
- `code` (unik)
- `domain` (nullable, unik, disiapkan untuk custom domain)
- `plan`
- `status` (`trial`, `active`, `suspended`, `cancelled`)
- `trial_ends_at`
- `subscription_ends_at`
- `max_employees`
- `features` (JSON)
- `notes`
- timestamps

### 4.2 `tenant_id` ditambahkan ke tabel domain

`users`, `office_settings`, `office_locations`, `attendances`, `work_shifts`,
`shift_assignments`, `leave_types`, `leave_balances`, `leave_requests`,
`attendance_corrections`, `salary_profiles`, `salary_allowances`,
`payroll_deduction_rules`, `payroll_settings`, `payroll_slips`, `holidays`,
`overtime_requests`, `notifications`, `audit_logs`.

### 4.3 Backfill data lama

Migration membuat tenant default (`code: default`) bila belum ada, lalu
mengisi seluruh baris lama dengan `tenant_id` tersebut. Data existing tidak
hilang.

### 4.4 Penyesuaian indeks unik

- `leave_requests.code` → unik per tenant.
- `attendance_corrections.code` → unik per tenant.
- `holidays(date, office_location_id)` → unik per tenant.
- `office_settings.tenant_id` → satu baris per tenant.
- `payroll_settings.tenant_id` → satu baris per tenant.
- `users.email` tetap unik global (batasan sementara).

### 4.5 File migration

- `database/migrations/2026_11_12_000100_create_tenants_table.php`
- `database/migrations/2026_11_12_000200_add_tenant_id_to_domain_tables.php`

---

## 5. Implementasi backend

### 5.1 Core tenant

| File | Fungsi |
|---|---|
| `app/Models/Tenant.php` | Model tenant, status, trial/subscription, `isActive()` |
| `app/Support/TenantContext.php` | Menyimpan tenant aktif per request/seeder |
| `app/Models/Concerns/BelongsToTenant.php` | Global scope + auto-isi `tenant_id` saat create |

Trait diterapkan ke 19 model domain. Untuk `User`, super admin dikecualikan dari
auto-isi tenant.

### 5.2 Middleware

| File | Fungsi |
|---|---|
| `app/Http/Middleware/ResetTenantContext.php` | Membersihkan context di awal request |
| `app/Http/Middleware/ResolveTenant.php` | Menentukan tenant dari user/token, cek status langganan |

Urutan route tenant:

```text
auth:sanctum → tenant → device → LogApiActivity
```

`ResolveTenant` juga diprioritaskan sebelum route model binding.

Super admin dapat mengakses data tenant tertentu dengan header
`X-Tenant-Id` (mode support), sedangkan tanpa header context dibiarkan kosong.

### 5.3 Provisioning

| File | Fungsi |
|---|---|
| `app/Services/TenantProvisioner.php` | Membuat data awal tenant |
| `app/Http/Controllers/Api/PlatformTenantController.php` | CRUD tenant platform |
| `app/Http/Resources/TenantResource.php` | Format respons tenant |

Data awal yang dibuat:

1. Pengaturan kantor default.
2. Kantor pusat default.
3. 3 jenis cuti.
4. Pengaturan payroll + 7 komponen potongan.
5. 3 template shift.
6. Akun admin pertama.

### 5.4 Autentikasi dan kuota

`AuthController@login` sekarang:

- menolak user tanpa tenant (selain super admin),
- menolak tenant `suspended`/trial expired,
- mengeset `TenantContext` sebelum membaca `OfficeSetting`,
- mengembalikan data `tenant` pada respons login.

`UserController@store`:

- menghitung karyawan tenant aktif,
- menolak `422` jika `max_employees` terlampaui.

### 5.5 File dan audit

- Upload publik diberi prefix `tenant-{id}`.
- Selfie absensi dan foto wajah tetap di disk privat dengan prefix tenant.
- `LogApiActivity` mengecualikan `admin_password`, `password`, token, dan file.
- Aksi platform (`POST/PATCH /platform/tenants`) ikut diaudit dengan
  `tenant_id = null`.

### 5.6 Seeder

| File | Fungsi |
|---|---|
| `database/seeders/SuperAdminSeeder.php` | Membuat super admin dari env |
| `database/seeders/DatabaseSeeder.php` | Tenant demo + data demo + memanggil super admin seeder |

Env yang didukung:

```env
SUPER_ADMIN_NAME=Super Admin
SUPER_ADMIN_EMAIL=superadmin@absensi.com
SUPER_ADMIN_PASSWORD=superadmin123
```

### 5.7 Endpoint platform baru

| Method | Endpoint | Akses |
|---|---|---|
| GET | `/api/platform/tenants` | super admin |
| POST | `/api/platform/tenants` | super admin |
| GET | `/api/platform/tenants/{tenant}` | super admin |
| PATCH | `/api/platform/tenants/{tenant}` | super admin |

Tidak ada endpoint:

- `/api/register`
- `/api/auth/register`
- signup di mobile

---

## 6. Implementasi mobile

| Perubahan | Detail |
|---|---|
| `User` | Field `tenantId`, `tenantName`, `tenantCode`, `isSuperAdmin` |
| `PlatformOnlyScreen` | Super admin yang login di mobile hanya melihat layar platform |
| Profil | Menampilkan nama perusahaan |
| Dashboard atasan | Meta menampilkan tenant + “Atasan” |
| Login | Tetap email + password; tenant ditentukan server dari akun |
| Signup | Tidak ada menu/route |

Satu email masih terikat ke satu tenant. Mobile tidak perlu memasukkan kode
perusahaan sampai model membership multi-tenant dibuat.

---

## 7. Verifikasi yang dilakukan

Skenario backend dijalankan terhadap server lokal:

| Skenario | Hasil |
|---|---|
| Login super admin | Berhasil, `is_super_admin=true`, tanpa tenant |
| Daftar tenant platform | Berhasil |
| Buat tenant baru + admin | Berhasil, tenant `trial`, admin terhubung tenant |
| Login admin tenant baru | Berhasil, tenant benar |
| Daftar user tenant baru | Hanya 1 user (admin), tidak melihat user demo |
| Kuota `max_employees=2` | Karyawan ke-3 ditolak `422` |
| Daftar user tenant demo | 10 user demo |
| Akses user tenant lain lewat ID | `404` |
| Suspend tenant | `status=suspended`, `is_active=false` |
| Login tenant suspended | `422` “Langganan perusahaan tidak aktif” |
| Token lama setelah suspend | `403` |
| `POST /api/auth/register` | `404` (tidak ada pendaftaran publik) |

Verifikasi mobile/backend:

- `flutter analyze` → **No issues found**
- `flutter test` → **54 test lulus**
- PHP lint backend → bersih
- APK debug final → berhasil dibuild
- Route platform terdaftar; tidak ada route register

---

## 8. Daftar file penting

### Backend

```text
app/Models/Tenant.php
app/Models/Concerns/BelongsToTenant.php
app/Support/TenantContext.php
app/Support/Uploads.php
app/Http/Middleware/ResetTenantContext.php
app/Http/Middleware/ResolveTenant.php
app/Http/Middleware/LogApiActivity.php
app/Services/TenantProvisioner.php
app/Http/Controllers/Api/PlatformTenantController.php
app/Http/Resources/TenantResource.php
app/Http/Resources/UserResource.php
app/Http/Controllers/Api/AuthController.php
app/Http/Controllers/Api/UserController.php
database/migrations/2026_11_12_000100_create_tenants_table.php
database/migrations/2026_11_12_000200_add_tenant_id_to_domain_tables.php
database/seeders/SuperAdminSeeder.php
database/seeders/DatabaseSeeder.php
routes/api.php
bootstrap/app.php
SAAS_FOUNDATION.md
```

### Mobile

```text
lib/data/models/user.dart
lib/screens/shared/platform_only_screen.dart
lib/screens/shared/profile_tab.dart
lib/screens/supervisor/dashboard_tab.dart
lib/app.dart
```

---

## 9. Batasan dan technical debt

1. **Shared database** — belum ada isolasi resource/backup per tenant.
2. **Email unik global** — satu email belum bisa menjadi anggota beberapa tenant.
3. **Belum ada billing** — hanya field plan/status/trial/kuota.
4. **Belum ada panel web super admin** — pengelolaan lewat API.
5. **Belum ada custom domain/subdomain resolver** — kolom `domain` disiapkan.
6. **Public attachment** — file lampiran publik masih dapat diakses jika URL
   diketahui; selfie/wajah sudah privat.
7. **Tenant deletion belum ada** — gunakan status `suspended`/`cancelled`.
8. **Belum ada membership multi-tenant** dan undangan user lintas tenant.
9. **Belum ada metering fitur** — baru kuota jumlah karyawan.
10. **Belum ada observability per tenant** (log/metrics/queue terpisah).

---

## 10. Roadmap berikutnya

### P0 — Sebelum onboarding pelanggan nyata

- Ganti password super admin default.
- Hosting HTTPS + backup database.
- Uji restore backup.
- Pastikan `SAAS` tidak membuka registrasi publik.
- Tambahkan rate limit login/API.
- Tambahkan pemantauan error dan audit retention.

### P1 — Operasional SaaS

- Panel web super admin (daftar tenant, suspend, kuota, support access).
- Billing gateway (Midtrans/Xendit/Stripe), invoice, webhook, trial reminder.
- Custom domain/subdomain per tenant.
- Endpoint file privat berautentikasi.
- Membership multi-tenant per email.
- Export/delete data per tenant.

### P2 — Skala

- Opsi database-per-tenant untuk pelanggan besar.
- Redis/queue/object storage.
- Metering penggunaan dan feature flags per plan.
- SSO/SAML untuk perusahaan.
- Observability dan SLA.

---

## 11. Cara menjalankan

### Backend baru

```bash
cd absensi_backend
composer install --no-dev
php artisan key:generate
php artisan migrate --seed
php artisan db:seed --class=SuperAdminSeeder
php artisan serve --host=0.0.0.0 --port=8000
```

### Buat tenant

Login `superadmin@absensi.com` lalu:

```http
POST /api/platform/tenants
Authorization: Bearer {token}
{
  "name": "PT Contoh",
  "code": "contoh",
  "plan": "basic",
  "trial_days": 14,
  "max_employees": 50,
  "admin_name": "Admin Contoh",
  "admin_email": "admin@contoh.com",
  "admin_password": "password-kuat"
}
```

### Mobile

```bash
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api
```

Tidak ada tombol daftar. Login memakai akun yang dibuat admin perusahaan.

---

## 12. Change log

### 2026-11-12 — `0.1.0-saas-foundation`

- Menambahkan tabel `tenants` dan `tenant_id` di seluruh tabel domain.
- Menambahkan tenant context, global scope, middleware tenant, dan prioritas
  sebelum route model binding.
- Menambahkan role `super_admin` dan endpoint `/api/platform/tenants`.
- Menambahkan provisioning tenant + admin pertama.
- Menambahkan trial, suspend, kuota karyawan.
- Menutup pendaftaran publik.
- Menambahkan isolasi file/audit per tenant.
- Menambahkan tampilan tenant di mobile dan layar khusus super admin.
- Menambahkan `SAAS_FOUNDATION.md` dan dokumen riwayat ini.
- Verifikasi: isolasi lintas tenant, kuota, suspend, dan register `404`.

---

## 13. Catatan

Dokumen ini adalah riwayat keputusan dan implementasi, bukan kontrak API.
Kontrak endpoint dan arsitektur ringkas ada di
[`SAAS_FOUNDATION.md`](SAAS_FOUNDATION.md).
