# Fondasi SaaS — Absensi Karyawan

Status: **fondasi multi-tenant siap**, tetapi **pendaftaran publik belum dibuka**.
Tenant hanya dibuat oleh super admin platform.

Riwayat keputusan dan implementasi: [`SAAS_HISTORY.md`](SAAS_HISTORY.md).

## Model arsitektur

Saat ini memakai **shared database, shared schema** (satu database, `tenant_id`
di setiap tabel domain). Ini pilihan paling ringan untuk infrastruktur yang
tersedia. Database-per-tenant dapat ditambahkan nanti tanpa mengubah kontrak
mobile.

```
Flutter mobile
   │ Bearer token
   ▼
Laravel API
   ├─ Auth (email global, token Sanctum)
   ├─ ResolveTenant → TenantContext
   ├─ BelongsToTenant global scope
   └─ /platform/* (super admin)
          │
          ▼
   tenants (plan, status, trial, max_employees)
   users, office_locations, attendances, leave_*, payroll_*, ...
   (semua memiliki tenant_id)
```

## Isolasi data

- Trait `App\Models\Concerns\BelongsToTenant` menerapkan global scope
  `where tenant_id = TenantContext::id()` dan mengisi `tenant_id` saat create.
- Middleware `ResolveTenant` dijalankan **sebelum route model binding**, sehingga
  ID milik tenant lain tidak bisa di-resolve.
- Semua tabel domain memiliki `tenant_id`; kode cuti/perbaikan unik per tenant.
- File upload diberi prefix `tenant-{id}`; selfie/wajah disimpan di disk privat.
- Audit log menyimpan `tenant_id` dan dapat difilter per tenant.

## Peran platform

| Peran | tenant_id | Akses |
|---|---|---|
| `super_admin` | null | `/api/platform/*` |
| `admin` | tenant | seluruh fitur perusahaan |
| `supervisor` | tenant | persetujuan, shift, payroll |
| `employee` | tenant | absensi, cuti, lembur, slip |

Super admin **tidak bisa memakai mobile**; aplikasi menampilkan layar
“Akun Platform”. Super admin dapat mengakses data tenant tertentu lewat API
dengan header `X-Tenant-Id: {id}`.

## Membuat tenant (hanya super admin)

Tidak ada endpoint registrasi publik. Alur produksi:

1. Seed super admin:

```bash
php artisan db:seed --class=SuperAdminSeeder
```

Set `SUPER_ADMIN_EMAIL`, `SUPER_ADMIN_PASSWORD`, `SUPER_ADMIN_NAME` di `.env`.

2. Login super admin:

```http
POST /api/auth/login
{ "email": "superadmin@absensi.com", "password": "superadmin123" }
```

3. Buat tenant + admin pertama:

```http
POST /api/platform/tenants
Authorization: Bearer {token_super_admin}
{
  "name": "PT Contoh",
  "code": "contoh",
  "plan": "basic",
  "trial_days": 14,
  "max_employees": 50,
  "admin_name": "Admin Contoh",
  "admin_email": "admin@contoh.com",
  "admin_password": "passwordku"
}
```

Provisioning otomatis membuat:

- pengaturan kantor default,
- kantor pusat default,
- 3 jenis cuti,
- pengaturan payroll + 7 komponen potongan,
- 3 template shift,
- akun admin perusahaan.

4. Kelola tenant:

| Method | Endpoint | Keterangan |
|---|---|---|
| GET | `/api/platform/tenants` | daftar tenant + jumlah user |
| POST | `/api/platform/tenants` | provisioning tenant baru |
| GET | `/api/platform/tenants/{id}` | detail tenant |
| PATCH | `/api/platform/tenants/{id}` | plan/status/trial/kuota |

Status: `trial`, `active`, `suspended`, `cancelled`. Tenant suspended/expired
ditolak saat login dan pada request token berikutnya.

## Kuota

`tenants.max_employees` membatasi jumlah akun karyawan. Admin tenant yang
melebihi kuota mendapat `422` saat menambah karyawan. Super admin dapat
menaikkan kuota lewat `PATCH /api/platform/tenants/{id}`.

## Pendaftaran publik

- Tidak ada route `/register` atau signup di mobile.
- Tenant baru hanya dibuat lewat endpoint super admin.
- Untuk sementara, satu email hanya boleh terdaftar di satu tenant (email unik
  global). Ini disengaja sampai tabel membership/identity terpisah dibuat.

## Billing

Belum terintegrasi payment gateway. Fondasi yang tersedia:

- `plan`, `status`, `trial_ends_at`, `subscription_ends_at`,
  `max_employees`, `features`.

Gateway (Midtrans/Xendit/Stripe), invoice, webhook, dan penagihan otomatis dapat
ditambahkan tanpa mengubah isolasi tenant.

## Keterbatasan saat ini

- Shared database: belum ada isolasi resource/backup per tenant.
- Public attachment lama masih dapat diakses jika URL diketahui; selfie/wajah
  sudah privat. Untuk produksi ketat, tambahkan endpoint file berautentikasi.
- Custom domain/subdomain belum di-resolve; kolom `domain` disiapkan.
- Belum ada membership multi-tenant per email.
- Belum ada panel web super admin; pengelolaan lewat API.
- Belum ada billing otomatis.

## Uji yang sudah dilakukan

- Super admin membuat tenant + admin.
- Admin tenant hanya melihat user tenant sendiri.
- Route model binding lintas tenant menghasilkan `404`.
- Kuota karyawan menolak user ke-3 saat `max_employees = 2`.
- Tenant suspended: login `422`, token lama `403`.
- `POST /api/auth/register` → `404` (tidak ada pendaftaran publik).
- Login admin demo tetap hanya melihat data demo.
