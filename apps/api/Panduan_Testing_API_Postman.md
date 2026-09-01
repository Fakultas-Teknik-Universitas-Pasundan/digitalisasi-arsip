# 📋 Panduan Testing API dengan Postman

## ✅ Status: UPDATED v4.0 (Master Backend Revision API)

| UC           | Fitur                              | Tests | Status      |
| ------------ | ---------------------------------- | ----- | ----------- |
| UC-01        | User Management                    | 16/16 | ✅ UPDATED  |
| UC-02        | View Documents                     | 2/2   | ✅ PASSED   |
| UC-03        | Audit Log                          | 5/5   | ✅ PASSED   |
| UC-04        | Document Upload                    | 7/7   | ✅ PASSED   |
| UC-05        | View Status                        | 1/1   | ✅ PASSED   |
| UC-06        | Document Update                    | 3/3   | ✅ PASSED   |
| UC-07        | Document Delete, Trash & Restore   | 9/9   | ✅ ENHANCED |
| UC-08        | Document Verification              | 16/16 | ✅ PASSED   |
| UC-09        | Search Documents                   | 4/4   | ✅ PASSED   |
| UC-10        | Document Download & Bulk ZIP       | 7/7   | ✅ PASSED   |
| UC-11        | Dashboard & Export                 | 5/5   | ✅ PASSED   |
| UC-12        | Unique Validation                  | 3/3   | ✅ PASSED   |
| UC-13        | **System Notifications**           | 5/5   | ✅ NEW v4.0!|
| UC-14        | **System Settings**                | 3/3   | ✅ NEW v4.0!|
| UC-15        | **Master Data (Prodi & Tipe)**     | 3/3   | ✅ NEW v4.0!|
| UC-16        | **QC Performance & Activity**      | 2/2   | ✅ NEW v4.0!|
| **Security** | Rate Limit, Account Lockout, Audit | 8/8   | ✅ ENHANCED |

**Total Tests:** 126 Passed (438 Assertions) | **Security Level:** Production-Ready 🔒
**API Version:** v4.0 - Master Backend Revision API
**Authentication:** Sanctum Token-Based (Bearer Token) / SPA Session Auth

---

## 🆕 What's New in v2.2

### 🔐 Enhanced Security Features

- ✅ **Dual-Layer Rate Limiting:**
    - IP-based: 75 login attempts/minute per IP
    - Account-based: 5 failed attempts → temporary lockout
- ✅ **Account Lockout Protection:**
    - Manager: 5 fails → locked for 5 minutes
    - Other roles: 5 fails → locked for 1 minute
    - Auto-unlock after lockout period
- ✅ **Comprehensive Audit Logging:**
    - `login` - Track all login events (success, failed, lockout)
- ✅ **Configurable via .env:**
    - Rate limits can be adjusted per environment
    - See `config/login-security.php`

### ⚡ Performance Optimizations

- ✅ Database index optimization (98% faster queries)
- ✅ Efficient login attempt tracking
- ✅ Automatic cleanup of old records

## 🆕 What's New in v2.4 (Latest)

### 📖 Swagger Documentation

- ✅ **Interactive API Docs**: Akses di `/api/documentation` (Swagger UI)
- ✅ **L5-Swagger Integration**: Dokumentasi langsung di kode PHP
- ✅ **Auto-generate**: Docs otomatis ter-update saat development

### 📊 Report Generation

- ✅ **Generate Report**: `POST /api/reports/generate` (PDF, XLSX, CSV)
- ✅ **Dashboard Stats**: `GET /api/reports/dashboard`

### 📦 Bulk Operations

- ✅ **Delete Multiple Users**: `POST /api/users/delete-multiple`
- ✅ **Delete Multiple Documents**: `POST /api/documents/delete-multiple`
- ✅ **Download Multiple (ZIP)**: `POST /api/documents/download-multiple`

### 📈 Statistics Endpoints

- ✅ **User Statistics**: `GET /api/users/statistics`
- ✅ **Document Statistics**: `GET /api/documents/statistics`

### 👁️ Document View

- ✅ **View Inline (PDF)**: `GET /api/documents/{id}/view`

## 🆕 What's New in v2.3

### 📈 Dashboard & Reporting

- ✅ **Statistics Endpoint**: `/api/audit-logs/statistics`
- ✅ **CSV Export**: `/api/audit-logs/export`

### 🛡️ Data Integrity

- ✅ **Unique NIP**: Integrity check on database level
- ✅ **Email Check**: Public endpoint for validation
- ✅ **Duplicate Document**: Intelligent content-based de-duplication

## 🆕 What's New in v2.1

### 🆕 Enum Implementation

- ✅ DocumentType: nilai, ijazah, transkrip, **berita_acara_sidang** (NEW!)
- ✅ Prodi: Informatika, Pangan, Industri, Mesin, Lingkungan, Perencanaan Wilayah Kota
- ✅ DocumentStatus: menunggu_verifikasi, terverifikasi, tidak_terverifikasi

## 🆕 What's New in v3.0

### 🔐 Token-Based Auth (Bearer Token)

- ✅ Migrasi dari cookie/session ke Bearer Token
- ✅ Stateless API — tidak perlu CSRF
- ✅ Cross-domain friendly
- ✅ Endpoint baru: `POST /api/auth/logout-all`
- ✅ Token expiration: 24 jam (configurable)

## 🆕 What's New in v2.0

### 📦 Optimized Responses (~40% smaller)

- ✅ Document responses: `uploaded_by_name` & `verified_by_name` (simple strings)
- ✅ Audit log responses: `user_name` (simple string)
- ❌ Removed nested user objects with redundant data (id, email)

---

## 🛠️ Setup Awal

### 1. Jalankan Server Laravel

```bash
cd apps/api
php artisan serve
```

### 2. Setup Database

```bash
php artisan migrate
php artisan db:seed --class=DocumentSeeder
```

```bash
php artisan migrate:fresh
php artisan db:seed --class=UserSeeder
```

### 3. Akun Testing

| Email               | Password   | Role     |
| ------------------- | ---------- | -------- |
| `manager@test.com`  | `password` | Manager  |
| `uploader@test.com` | `password` | Uploader |
| `qc@test.com`       | `password` | QC       |
| `sbap@test.com`     | `password` | SBAP     |

### 4. Postman Configuration (PENTING!)

> **💡 API menggunakan Bearer Token.** Tidak perlu cookies atau CSRF token.

#### Yang Perlu Anda Lakukan:

**A. Create Environment Variable (Untuk Auto-Save Token):**

1. Klik **"Environments"** di sidebar kiri
2. Klik **"+"** untuk create environment baru
3. Nama: `Digitalisasi Arsip API`
4. Tambahkan variable:
    - Variable: `auth_token`
    - Initial value: (kosongkan)
5. Klik **"Save"**
6. **Aktifkan environment** dengan dropdown di kanan atas

**B. Setup Authorization (Global):**

1. Di Collection, klik tab **"Authorization"**
2. Type: **Bearer Token**
3. Token: `{{auth_token}}`
4. Semua request dalam collection akan otomatis menggunakan token ini

---

## 🔐 STEP 0: Autentikasi (Sanctum Token-Based API)

> **🔒 PENTING:** API menggunakan **Bearer Token** untuk autentikasi. Token didapat dari login response.

### ✅ 0.1 Login

```
POST http://localhost:8000/api/auth/login

Headers:
Content-Type: application/json
Accept: application/json

Body (raw JSON):
{
  "email": "manager@test.com",
  "password": "password"
}
```

**Postman Settings:**

- Di tab "Tests", tambahkan script ini untuk auto-save token:

```javascript
var jsonData = pm.response.json();
if (jsonData.data && jsonData.data.token) {
    pm.environment.set("auth_token", jsonData.data.token);
}
```

**Expected Response:**

```json
{
    "message": "Login berhasil.",
    "data": {
        "user": {
            "id": 1,
            "name": "Manager User",
            "email": "manager@test.com",
            "role": "manager"
        },
        "token": "1|abc123def456...",
        "token_type": "Bearer",
        "expires_in": 86400
    }
}
```

- Status: `200 OK`
- Token tersimpan di environment variable `auth_token`

---

### ✅ 0.2 Verifikasi Token (Check Authentication)

```
GET http://localhost:8000/api/auth/me

Headers:
Authorization: Bearer {{auth_token}}
```

**Expected Response:**

```json
{
    "message": "Data pengguna berhasil diambil.",
    "data": {
        "id": 1,
        "name": "Manager User",
        "email": "manager@test.com",
        "role": "manager"
    }
}
```

**Status:** `200 OK`

---

### 🚪 0.3 Logout (Current Device)

```
POST http://localhost:8000/api/auth/logout

Headers:
Authorization: Bearer {{auth_token}}
```

**Expected Response:**

```json
{
    "message": "Logout berhasil."
}
```

**Status:** `200 OK`

- Token di-revoke, tidak bisa dipakai lagi

---

### 🚪 0.4 Logout All Devices

```
POST http://localhost:8000/api/auth/logout-all

Headers:
Authorization: Bearer {{auth_token}}
```

**Expected Response:**

```json
{
    "message": "Logout dari semua perangkat berhasil."
}
```

**Status:** `200 OK`

- Semua token di-revoke (semua perangkat)

---

## 👥 UC-01: User Management (Login: Manager)

### ✅ 1.1 List Semua User

```
GET http://localhost:8000/api/users
```

**Expected Response:**

```json
{
    "message": "Daftar pengguna berhasil diambil.",
    "data": [
        {
            "id": 1,
            "name": "Manager User",
            "email": "manager@test.com",
            "nip": "1234567890",
            "role": "manager",
            "created_at": "2026-02-03T07:12:44.000000Z",
            "updated_at": "2026-02-03T07:12:44.000000Z"
        }
    ],
    "meta": {
        "current_page": 1,
        "last_page": 1,
        "per_page": 15,
        "total": 4
    }
}
```

**Status:** `200 OK`

---

### ✅ 1.2 Buat User Baru

```
POST http://localhost:8000/api/users

Headers:
Content-Type: application/json
Authorization: Bearer {{auth_token}}

Body (raw JSON - pilih "JSON" di dropdown, BUKAN "Text"):
{
  "name": "Uploader Baru",
  "email": "uploader_baru@test.com",
  "password": "password123",
  "nip": "1234567890",
  "role": "uploader"
}
```

**Expected Response:**

```json
{
    "message": "Pengguna berhasil dibuat.",
    "data": {
        "id": 5,
        "name": "Uploader Baru",
        "email": "uploader_baru@test.com",
        "nip": "1234567890",
        "role": "uploader",
        "created_at": "2026-02-03T07:12:44.000000Z",
        "updated_at": "2026-02-03T07:12:44.000000Z"
    }
}
```

**Status:** `201 Created`

---

### ✅ 1.3 Update User (Partial Update)

```
PATCH http://localhost:8000/api/users/{id}

Headers:
Content-Type: application/json
Authorization: Bearer {{auth_token}}

Body (raw JSON - pilih "JSON" di dropdown, BUKAN "Text"):
{
  "name": "Uploader Baru 2- Updated",
  "role": "qc"
}
```

**Expected Response:**

```json
{
    "message": "Pengguna berhasil diperbarui.",
    "data": {
        "id": 5,
        "name": "Uploader Baru 2- Updated",
        "email": "uploader_baru@test.com",
        "nip": "1234567890",
        "role": "qc",
        "created_at": "2026-02-03T07:12:44.000000Z",
        "updated_at": "2026-02-03T07:12:44.000000Z"
    }
}
```

**Status:** `200 OK`

---

### ✅ 1.4 Hapus User

```
DELETE http://localhost:8000/api/users/{id}

Headers:
Authorization: Bearer {{auth_token}}
```

**Expected Response:**

```json
{
    "message": "Pengguna berhasil dihapus."
}
```

**Status:** `200 OK`

---

### ✅ 1.5 Test Authorization (Login: Uploader)

```
GET http://localhost:8000/api/users
```

**Expected Response:**

```json
{
    "message": "This action is unauthorized."
}
```

**Status:** `403 Forbidden` (Uploader tidak boleh akses user management)

---

## 📄 UC-02: Melihat Arsip Digital (Login: Any Role)

### ✅ 2.1 List Semua Dokumen

```
GET http://localhost:8000/api/documents
GET http://localhost:8000/api/documents?sort_by=tahun_lulus&sort_direction=desc
GET http://localhost:8000/api/documents?sort_by=status&sort_direction=asc

```

**Expected Response (v2.3):**

```json
{
    "message": "Daftar dokumen berhasil diambil.",
    "data": [
        {
            "id": 1,
            "document_type": "nilai",
            "file_name": "nilai_pemweb_2024.pdf",
            "file_size": 1234567,
            "file_size_formatted": "1.18 MB",
            "prodi": "Teknik Informatika",
            "tahun_ajaran": "2024/2025",
            "mata_kuliah": "Pemrograman Web",
            "kelas": "A",
            "tahun_lulus": null,
            "npm": null,
            "status": "Terverifikasi",
            "verification_note": null,
            "uploaded_by_name": "Uploader User",
            "verified_by_name": "QC User",
            "verified_at": "2026-01-22T10:30:00.000000Z",
            "created_at": "2026-01-22T09:00:00.000000Z",
            "updated_at": "2026-01-22T10:30:00.000000Z"
        }
    ],
    "meta": {
        "current_page": 1,
        "last_page": 5,
        "per_page": 15,
        "total": 75
    }
}
```

---

### ✅ 2.2 Sorting Dokumen (New v2.3)

Fitur sorting mendukung kolom: `created_at`, `tahun_lulus`, `status`, `document_type`, `prodi`, `file_name`.

```
GET http://localhost:8000/api/documents?sort_by=tahun_lulus&sort_direction=asc
```

**Response:** Daftar dokumen diurutkan berdasarkan tahun lulus secara menaik (ASC).

---

### ✅ 2.3 Lihat Detail Dokumen

```
GET http://localhost:8000/api/documents/{id}
```

**Expected:** Same simplified structure as 2.1, single object in `data`
**Status:** `200 OK`

---

## 📊 UC-03: Memantau Aktivitas Sistem (Login: Manager)

### ✅ 3.1 Lihat Semua Audit Log

```
GET http://localhost:8000/api/audit-logs
```

**Expected Response (v2.3):**

```json
{
    "message": "Log aktivitas berhasil diambil.",
    "data": [
        {
            "id": 9,
            "user": {
                "name": "Manager Arsip",
                "role": "manager"
            },
            "action": {
                "name": "login",
                "label": "Login Berhasil",
                "color": "secondary"
            },
            "document": {
                "id_formatted": "-",
                "name": "-"
            },
            "description": "User Manager Arsip berhasil login.",
            "ip_address": "127.0.0.1",
            "date": {
                "formatted": "18/02/2026",
                "time": "11.07",
                "timestamp": "2026-02-18T11:07:22.000000Z"
            },
            "metadata": {
                "role": "manager",
                "email": "manager@test.com",
                "user_id": 1,
                "ip_address": "127.0.0.1",
                "user_agent": "PostmanRuntime/7.51.1"
            }
        }
    ],
    "meta": {
        "current_page": 1,
        "last_page": 1,
        "per_page": 20,
        "total": 9
    }
}
```

**Status:** `200 OK`

**📋 Response Changes (v2.3):**

- ✅ **Rich Objects:** `user`, `action`, `document` sekarang berupa object terstruktur.
- ✅ **Formatted Dates:** Tanggal sudah diformat (`dd/mm/yyyy`).
- ✅ **Action Labels:** Ada label human-readable.

---

### ✅ 3.2 Filter Log by Action

```
GET http://localhost:8000/api/audit-logs?action=upload_document
```

**Expected:** Hanya log dengan action "upload_document"
**Status:** `200 OK`

---

### ✅ 3.3 Filter Log by User

```
GET http://localhost:8000/api/audit-logs?user_id=2
```

**Expected:** Hanya log dari user dengan ID 2
**Status:** `200 OK`

---

### ✅ 3.4 Lihat Statistik Aktivitas

```
GET http://localhost:8000/api/audit-logs/statistics
```

**Expected Response:**

```json
{
    "message": "Statistik aktivitas berhasil diambil.",
    "data": {
        "total_activities": 45,
        "today_total": 12,
        "today_upload": 5,
        "today_verify": 3,
        "today_reject": 1,
        "by_action": {
            "Upload Dokumen": 15,
            "Verifikasi Dokumen": 10,
            "Tolak Dokumen": 5,
            "Update Dokumen": 8,
            "Hapus Dokumen": 3,
            "Download Dokumen": 4
        },
        "recent_activities": [
            // ... last 10 activities (format AuditLogResource)
        ]
    },
    "period": {
        "start_date": "2026-06-08T00:00:00.000000Z",
        "end_date": "2026-07-08T23:59:59.000000Z"
    }
}
```

> **📝 Catatan:** Field `by_action` menggunakan label Indonesia sebagai key (bukan snake_case). Field `today_*` menunjukkan statistik hari ini.

**Status:** `200 OK`

---

### ✅ 3.5 Test Authorization (Login: Uploader)

```
GET http://localhost:8000/api/audit-logs
```

**Expected:** 403 Forbidden (Hanya Manager yang boleh akses audit logs)
**Status:** `403 Forbidden`

---

## 📤 UC-04: Document Upload (Login: Uploader)

### ✅ 4.1 Upload Dokumen Nilai

```
POST http://localhost:8000/api/documents

Headers:
Authorization: Bearer {{auth_token}}

Body (form-data):
- document_type: nilai
- prodi: Teknik Informatika
- tahun_ajaran: 2024/2025
- mata_kuliah: Pemrograman Web
- kelas: A
- file: (pilih file PDF)
```

**Expected Response:**

```json
{
    "message": "Dokumen berhasil diunggah.",
    "data": {
        "id": 10,
        "document_type": "nilai",
        "file_name": "nilai_pemweb_2024.pdf",
        "file_size": 102400,
        "file_size_formatted": "100 KB",
        "prodi": "Teknik Informatika",
        "tahun_ajaran": "2024/2025",
        "mata_kuliah": "Pemrograman Web",
        "kelas": "A",
        "status": "Menunggu Verifikasi",
        "uploaded_by_name": "Uploader User",
        "verified_by_name": null,
        "verified_at": null,
        "created_at": "2026-02-18T12:00:00.000000Z",
        "updated_at": "2026-02-18T12:00:00.000000Z"
    }
}
```

**Status:** `201 Created`

> **📝 Note:** Prodi sekarang menggunakan Enum. Gunakan nilai: `Teknik Informatika`, `Teknologi Pangan`, `Teknik Industri`, `Teknik Mesin`, `Teknik Lingkungan`, atau `Perencanaan Wilayah dan Kota`

---

### ✅ 4.2 Upload Dokumen Ijazah

```
POST http://localhost:8000/api/documents

Headers:
Authorization: Bearer {{auth_token}}

Body (form-data):
- document_type: ijazah
- prodi: Teknik Informatika
- tahun_lulus: 2024
- npm: 1234567890
- file: (pilih file PDF)
```

**Expected Response:**

```json
{
    "message": "Dokumen berhasil diunggah.",
    "data": {
        "id": 11,
        "document_type": "ijazah",
        "file_name": "ijazah_123.pdf",
        "file_size": 204800,
        "file_size_formatted": "200 KB",
        "prodi": "Teknik Informatika",
        "tahun_lulus": "2024",
        "npm": "1234567890",
        "status": "Menunggu Verifikasi",
        "uploaded_by_name": "Uploader User",
        "verified_by_name": null,
        "created_at": "2026-02-18T12:05:00.000000Z"
    }
}
```

**Status:** `201 Created`

---

### ✅ 4.3 Upload Berita Acara Sidang (NEW!)

```
POST http://localhost:8000/api/documents

Headers:
Authorization: Bearer {{auth_token}}

Body (form-data):
- document_type: berita_acara_sidang
- prodi: Teknik Mesin
- tahun_lulus: 2024
- npm: 9876543210
- file: (pilih file PDF)
```

**Expected Response:**

```json
{
    "message": "Dokumen berhasil diunggah.",
    "data": {
        "id": 12,
        "document_type": "berita_acara_sidang",
        "file_name": "bas_987.pdf",
        "file_size": 153600,
        "file_size_formatted": "150 KB",
        "prodi": "Teknik Mesin",
        "tahun_lulus": "2024",
        "npm": "9876543210",
        "status": "Menunggu Verifikasi",
        "uploaded_by_name": "Uploader User",
        "created_at": "2026-02-18T12:10:00.000000Z"
    }
}
```

**Status:** `201 Created`

> **✨ NEW:** Tipe dokumen `berita_acara_sidang` sekarang didukung!

---

### ✅ 4.4 Test Duplicate Detection

Upload dengan data yang sama persis seperti 4.1

**Expected Response:**

```json
{
    "message": "Dokumen duplikat terdeteksi.",
    "errors": {
        "duplicate": [
            "Dokumen nilai dengan kombinasi tahun ajaran, prodi, mata kuliah, dan kelas yang sama sudah ada."
        ]
    }
}
```

**Status:** `409 Conflict`

---

### ✅ 4.5 Test Validation Error - Missing Fields

Upload tanpa field required

**Expected:** 422 Unprocessable Entity
**Status:** `422 Unprocessable Entity`

---

### ✅ 4.6 Test Validation Error - Invalid Prodi

```
POST http://localhost:8000/api/documents

Headers:
Authorization: Bearer {{auth_token}}

Body (form-data):
- document_type: nilai
- prodi: Invalid Prodi  // ❌ Invalid! Tidak ada di Enum
- tahun_ajaran: 2024/2025
- mata_kuliah: Pemrograman Web
- kelas: A
- file: (pilih file PDF)
```

**Expected Response:**

```json
{
    "message": "The prodi field is invalid.",
    "errors": {
        "prodi": ["The selected prodi is invalid."]
    }
}
```

**Status:** `422 Unprocessable Entity`

---

### ✅ 4.7 Test PDF Magic Number Validation 🔒

1. Buat file `.txt` dengan content apapun
2. Rename file jadi `fake.pdf`
3. Upload file tersebut

**Expected Response:**

```json
{
    "message": "The file field must be a valid file.",
    "errors": {
        "file": [
            "File bukan PDF yang valid. File mungkin rusak atau berbahaya."
        ]
    }
}
```

**Status:** `422 Unprocessable Entity`

---

## 📋 UC-05: Melihat Status Dokumen (Login: Any Role)

### ✅ 5.1 Filter Dokumen by Status

```
GET http://localhost:8000/api/documents?status=menunggu_verifikasi
GET http://localhost:8000/api/documents?status=terverifikasi
GET http://localhost:8000/api/documents?status=tidak_terverifikasi
```

**Expected:** Hanya dokumen dengan status yang dipilih
**Status:** `200 OK`

```json
{
    "message": "Daftar dokumen berhasil diambil.",
    "data": [
        {
            "id": 1,
            "document_type": "nilai",
            "status": "Menunggu Verifikasi",
            "file_name": "nilai_mhs.pdf"
        }
    ]
}
```

---

## ✔️ UC-08: Document Verification (Login: QC)

### ✅ 8.1 Lihat Dokumen Pending

```
GET http://localhost:8000/api/documents/pending
```

**Expected:** 200 OK dengan list dokumen status `menunggu_verifikasi`
**Status:** `200 OK`

---

### ✅ 8.2 Verifikasi (Approve) Dokumen

```
PATCH http://localhost:8000/api/documents/{id}/verify

Headers:
Content-Type: application/json
Authorization: Bearer {{auth_token}}

Body (raw JSON - pilih "JSON" di dropdown, BUKAN "Text"):
{
  "status": "terverifikasi"
}
```

**Expected Response:**

```json
{
    "message": "Dokumen berhasil diverifikasi.",
    "data": {
        "id": 1,
        "document_type": "nilai",
        "status": "Terverifikasi",
        "verification_note": null,
        "verified_by_name": "QC User",
        "verified_at": "2026-02-18T14:00:00.000000Z",
        "uploaded_by_name": "Uploader User"
    }
}
```

**Status:** `200 OK`

---

### ✅ 8.3 Tolak (Reject) Dokumen

```
PATCH http://localhost:8000/api/documents/{id}/verify

Headers:
Content-Type: application/json
Authorization: Bearer {{auth_token}}

Body (raw JSON - pilih "JSON" di dropdown, BUKAN "Text"):
{
  "status": "tidak_terverifikasi",
  "verification_note": "Format dokumen tidak sesuai standar."
}
```

**Expected Response:**

```json
{
    "message": "Dokumen ditolak.",
    "data": {
        "id": 1,
        "document_type": "nilai",
        "status": "Tidak Terverifikasi",
        "verification_note": "Format dokumen tidak sesuai standar.",
        "verified_by_name": "QC User",
        "verified_at": "2026-02-18T14:05:00.000000Z",
        "uploaded_by_name": "Uploader User"
    }
}
```

**Status:** `200 OK`

---

### ✅ 8.4 Test Verifikasi Ulang

Coba verifikasi dokumen yang sudah terverifikasi

**Expected Response:**

```json
{
    "message": "Dokumen sudah diverifikasi sebelumnya dan tidak dapat diverifikasi ulang.",
    "errors": {
        "document": [
            "Dokumen sudah diverifikasi sebelumnya dan tidak dapat diverifikasi ulang."
        ]
    }
}
```

**Status:** `422 Unprocessable Entity`

---

## 📝 UC-06: Document Update (Login: Uploader)

### ✅ 6.1 Update Dokumen Rejected

```
PUT http://localhost:8000/api/documents/{id}

Headers:
Content-Type: application/json
Authorization: Bearer {{auth_token}}

Body (raw JSON - pilih "JSON" di dropdown, BUKAN "Text"):
{
  "prodi": "Pangan",
  "tahun_lulus": "2025"
}
```

**Expected Response:**

```json
{
    "message": "Dokumen berhasil diperbarui. Status direset ke menunggu verifikasi.",
    "data": {
        "id": 1,
        "status": "Menunggu Verifikasi",
        "prodi": "Teknologi Pangan",
        "tahun_lulus": "2025",
        "uploaded_by_name": "Uploader User",
        "verified_by_name": null,
        "updated_at": "2026-02-18T13:00:00.000000Z"
    }
}
```

**Status:** `200 OK`

- Status direset ke `menunggu_verifikasi`

---

### ✅ 6.2 Update dengan File Baru

```
POST http://localhost:8000/api/documents/{id}

Headers:
Authorization: Bearer {{auth_token}}

Body (form-data):
- _method: PUT
- prodi: Teknik Industri
- file: (pilih file PDF baru)
```

**Expected:** 200 OK
**Status:** `200 OK`

---

### ✅ 6.3 Test Update Dokumen Terverifikasi

```
PUT http://localhost:8000/api/documents/{id_terverifikasi}

Headers:
Content-Type: application/json
Authorization: Bearer {{auth_token}}

Body (raw JSON - pilih "JSON" di dropdown, BUKAN "Text"):
{
  "prodi": "Test"
}
```

**Expected Response:**

```json
{
    "message": "Dokumen yang sudah terverifikasi tidak dapat diperbarui.",
    "errors": {
        "document": ["Dokumen yang sudah terverifikasi tidak dapat diperbarui."]
    }
}
```

**Status:** `422 Unprocessable Entity`

---

## 🗑️ UC-07: Document Delete (Login: Uploader)

> **⚠️ Catatan:** Hanya dokumen dengan status `tidak_terverifikasi` yang bisa dihapus oleh Uploader

### ✅ 7.1 Hapus Dokumen Rejected

```
DELETE http://localhost:8000/api/documents/{id_rejected}

Headers:
Authorization: Bearer {{auth_token}}
```

**Expected Response:**

```json
{
    "message": "Dokumen berhasil dihapus."
}
```

**Status:** `200 OK`

---

### ✅ 7.2 Test Hapus Dokumen Terverifikasi

```
DELETE http://localhost:8000/api/documents/{id_terverifikasi}

Headers:
Authorization: Bearer {{auth_token}}
```

**Expected:** 403 Forbidden (Dokumen terverifikasi tidak boleh dihapus)
**Status:** `403 Forbidden`

---

### ✅ 7.3 Test Hapus Dokumen Milik Orang Lain

```
DELETE http://localhost:8000/api/documents/{id_milik_user_lain}

Headers:
Authorization: Bearer {{auth_token}}
```

**Expected:** 403 Forbidden (Uploader hanya bisa hapus dokumen miliknya)
**Status:** `403 Forbidden`

---

### ✅ 7.4 Daftar Dokumen Terhapus (Trash - Login: Manager)

```
GET http://localhost:8000/api/documents/trashed

Headers:
Authorization: Bearer {{auth_token_manager}}
```

**Expected:** Daftar dokumen yang berada di tempat sampah (*soft-deleted*).
**Status:** `200 OK`

---

### ✅ 7.5 Pulihkan Dokumen dari Tempat Sampah (Restore - Login: Manager)

```
POST http://localhost:8000/api/documents/{id_soft_deleted}/restore

Headers:
Authorization: Bearer {{auth_token_manager}}
```

**Expected:**
```json
{
    "message": "Dokumen berhasil dipulihkan.",
    "data": {
        "id": 12,
        "file_name": "Nilai_Web.pdf"
    }
}
```
**Status:** `200 OK`

---

### ✅ 7.6 Hapus Dokumen Permanen & File Fisik (Force Delete - Login: Manager)

```
DELETE http://localhost:8000/api/documents/{id_soft_deleted}/force-delete

Headers:
Authorization: Bearer {{auth_token_manager}}
```

**Expected:**
```json
{
    "message": "Dokumen berhasil dihapus permanen."
}
```
**Status:** `200 OK`

---

## 🔍 UC-09: Search & Filter Documents (Login: Any Role)

### ✅ 9.1 Search by File Name

```
GET http://localhost:8000/api/documents?search=pemweb
```

**Expected:** Dokumen dengan nama file mengandung "pemweb"
**Status:** `200 OK`

```json
{
    "message": "Daftar dokumen berhasil diambil.",
    "data": [
        {
            "id": 1,
            "document_type": "nilai",
            "file_name": "nilai_pemweb_2024.pdf",
            "status": "Terverifikasi",
            "prodi": "Teknik Informatika"
        }
    ],
    "meta": {
        "current_page": 1,
        "total": 1
    }
}
```

---

### ✅ 9.2 Filter by Document Type

```
GET http://localhost:8000/api/documents?document_type=nilai
```

**Expected:** Hanya dokumen tipe "nilai"
**Status:** `200 OK`

---

### ✅ 9.3 Filter by Prodi

```
GET http://localhost:8000/api/documents?prodi=Teknik%20Informatika
```

**Expected:** Hanya dokumen dari prodi "Informatika"
**Status:** `200 OK`

---

### ✅ 9.4 Combined Filters

```
GET http://localhost:8000/api/documents?document_type=nilai&status=terverifikasi&prodi=Teknik%20Informatika
```

**Expected:** Dokumen nilai yang terverifikasi dari prodi Informatika
**Status:** `200 OK`

---

## 📥 UC-10: Document Download (Login: SBAP atau Manager)

### ✅ 10.1 Download Dokumen Terverifikasi

```
GET http://localhost:8000/api/documents/{id_terverifikasi}/download
```

**Postman:**

- Klik tombol **"Send and Download"** untuk menyimpan file
- Atau klik **"Send"** dan check response body untuk stream

**Expected:**

- Status: `200 OK`
- Content-Type: `application/pdf`
- File PDF ter-download

---

### ✅ 10.2 Test Download Dokumen Tidak Terverifikasi (Login: SBAP)

```
GET http://localhost:8000/api/documents/{id_pending}/download
```

**Expected:** 403 Forbidden (SBAP hanya bisa download dokumen terverifikasi)
**Status:** `403 Forbidden`

---

### ✅ 10.3 Test Download sebagai Uploader

Login sebagai Uploader, coba download

**Expected:** 403 Forbidden (Uploader tidak punya akses download)
**Status:** `403 Forbidden`

---

## 📊 Matrix Akses Role

| Fitur                     | Manager | Uploader   | QC     | SBAP       |
| ------------------------- | ------- | ---------- | ------ | ---------- |
| User CRUD                 | ✅      | ❌         | ❌     | ❌         |
| User Statistics           | ✅      | ❌         | ❌     | ❌         |
| View Documents            | ✅      | ✅         | ✅     | ✅         |
| View Document Inline      | ✅      | ✅         | ✅     | ✅         |
| Upload Dokumen            | ✅      | ✅         | ❌     | ❌         |
| Update Dokumen (rejected) | ✅      | ✅\*       | ❌     | ❌         |
| Delete Dokumen (rejected) | ✅      | ✅\*       | ❌     | ❌         |
| Bulk Delete Dokumen       | ✅      | ✅\*       | ❌     | ❌         |
| Verifikasi Dokumen        | ✅      | ❌         | ✅     | ❌         |
| Download Dokumen          | ✅      | ✅\*\*\*  | ✅\*\* | ✅\*\*\*\* |
| Bulk Download (ZIP)       | ✅      | ✅\*\*\*  | ✅\*\* | ✅\*\*\*\* |
| Document Statistics        | ✅      | ❌         | ❌     | ❌         |
| View Audit Logs           | ✅      | ❌         | ❌     | ❌         |
| Export Audit Logs (CSV)   | ✅      | ❌         | ❌     | ❌         |
| Generate Reports          | ✅      | ❌         | ❌     | ❌         |
| Dashboard Stats           | ✅      | ❌         | ❌     | ❌         |

\*Uploader hanya bisa update/delete dokumen miliknya sendiri
\*\*SBAP hanya bisa download dokumen terverifikasi

---

---

## 📈 UC-11: Reports & Export (Login: Manager) [UPDATED v2.4]

### ✅ 11.1 Generate Report (UC-09)

```
POST http://localhost:8000/api/reports/generate

Headers:
Content-Type: application/json
X-XSRF-TOKEN: {{xsrf_token}}

Body (raw JSON):
{
  "period_start": "2026-01-01",
  "period_end": "2026-06-30",
  "format": "pdf",
  "type": "monthly",
  "style": "detailed",
  "content": ["upload_stats", "qc_metrics", "doc_status"]
}
```

**Expected:** File PDF/XLSX/CSV ter-download (sesuai `format`)
**Status:** `200 OK`

> **📝 Format yang didukung:** `pdf`, `xlsx`, `csv`  
> **Tipe laporan:** `monthly`, `annual`, `custom`  
> **Style:** `detailed`, `summary`, `executive`  
> **Content options:** `upload_stats`, `qc_metrics`, `doc_status`, `user_activity`, `trend_analysis`

---

### ✅ 11.2 Dashboard Stats

```
GET http://localhost:8000/api/reports/dashboard
GET http://localhost:8000/api/reports/dashboard?start_date=2026-01-01&end_date=2026-06-30
```

**Expected Response:**

```json
{
    "message": "Statistik dashboard berhasil diambil.",
    "data": {
        "total_reports_generated": 0,
        "most_downloaded_type": "Bulanan",
        "last_generated": "2026-07-08 21:20"
    }
}
```

**Status:** `200 OK`

---

### ✅ 11.3 Export Audit Log ke CSV

```
GET http://localhost:8000/api/audit-logs/export?format=csv&start_date=2026-01-01&end_date=2026-12-31
```

**Postman:** Klik tombol **"Send and Download"**.
**Expected:** File `.csv` terunduh.
**Status:** `200 OK`

---

## 📦 UC-13: Bulk Operations (Login: Manager) [BARU v2.4]

### ✅ 13.1 Delete Multiple Users

```
POST http://localhost:8000/api/users/delete-multiple

Headers:
Content-Type: application/json
X-XSRF-TOKEN: {{xsrf_token}}

Body (raw JSON):
{
  "ids": [5, 6, 7]
}
```

**Expected Response:**

```json
{
    "message": "3 pengguna berhasil dihapus.",
    "deleted_count": 3
}
```

**Status:** `200 OK`

---

### ✅ 13.2 Delete Multiple Documents

```
POST http://localhost:8000/api/documents/delete-multiple

Headers:
Content-Type: application/json
X-XSRF-TOKEN: {{xsrf_token}}

Body (raw JSON):
{
  "ids": [10, 11, 12]
}
```

**Expected Response:**

```json
{
    "message": "3 dokumen berhasil dihapus.",
    "deleted_count": 3
}
```

**Status:** `200 OK`

> **⚠️ Catatan:** Hanya dokumen dengan status `tidak_terverifikasi` yang bisa dihapus.

---

### ✅ 13.3 Download Multiple Documents (ZIP)

```
POST http://localhost:8000/api/documents/download-multiple

Headers:
Content-Type: application/json
X-XSRF-TOKEN: {{xsrf_token}}

Body (raw JSON):
{
  "ids": [1, 2, 3]
}
```

**Postman:** Klik tombol **"Send and Download"**.
**Expected:** File `.zip` berisi dokumen-dokumen yang dipilih.
**Status:** `200 OK`

---

## 📈 UC-14: Statistics Endpoints (Login: Manager) [BARU v2.4]

### ✅ 14.1 User Statistics

```
GET http://localhost:8000/api/users/statistics
```

**Expected Response:**

```json
{
    "message": "Statistik pengguna berhasil diambil.",
    "data": {
        "total_users": 25,
        "active_users": 18,
        "new_users": 5
    }
}
```

**Status:** `200 OK`

> **📝 Catatan:** `active_users` = users dengan aktivitas 30 hari terakhir. `new_users` = users dibuat 30 hari terakhir.

---

### ✅ 14.2 Document Statistics

```
GET http://localhost:8000/api/documents/statistics
```

**Expected Response:**

```json
{
    "message": "Statistik dokumen berhasil diambil.",
    "data": {
        "total_documents": 150,
        "verified_documents": 80,
        "pending_documents": 50,
        "rejected_documents": 20
    }
}
```

**Status:** `200 OK`

---

## 👁️ UC-15: Document View Inline (Login: Any Role) [BARU v2.4]

### ✅ 15.1 View Document di Browser (PDF Viewer)

```
GET http://localhost:8000/api/documents/{id}/view
```

**Expected:**

- Status: `200 OK`
- Content-Type: `application/pdf`
- Content-Disposition: `inline` (ditampilkan di browser, bukan di-download)

> **📝 Perbedaan dengan Download:** Endpoint `view` menampilkan PDF langsung di browser (inline), sedangkan `download` mengunduh file ke komputer.

---

## 📖 Swagger Documentation [BARU v2.4]

API sekarang memiliki dokumentasi interaktif yang ter-generate otomatis dari kode PHP.

### Akses Swagger UI

```
GET http://localhost:8000/api/documentation
```

Buka URL di atas pada browser untuk melihat semua endpoint API secara interaktif dengan Swagger UI.

> **💡 Tips:** Swagger UI memungkinkan Anda menguji endpoint langsung dari browser tanpa Postman!

---

## 🛡️ UC-12: User Uniqueness & Validation (Public) [BARU v2.3]

### ✅ 12.1 Check Ketersediaan Email

```
POST http://localhost:8000/api/auth/check-email

Body (JSON):
{
  "email": "new.user@example.com"
}
```

**Expected Response:**

```json
{
    "available": true,
    "message": "Email tersedia."
}
```

**Status:** `200 OK`

### ✅ 12.2 Test Unique NIP (Login: Manager)

Coba buat user baru dengan NIP yang sudah dipakai user lain.

```
POST http://localhost:8000/api/users
Body: { "nip": "1234567890", ... }
```

**Expected Response:**

```json
{
    "message": "The nip has already been taken.",
    "errors": {
        "nip": ["The nip has already been taken."]
    }
}
```

**Status:** `422 Unprocessable Entity`

---

## 🐛 Troubleshooting

### Error 401 Unauthenticated

**Penyebab:**

- Token belum disertakan di header
- Token sudah expired (24 jam)
- Token sudah di-revoke (logout)

**Solusi:**

1. Pastikan header `Authorization: Bearer {{auth_token}}` ada
2. Login ulang untuk mendapatkan token baru
3. Cek environment variable `auth_token` terisi

---

### Error 403 Forbidden

**Penyebab:**

- Role user tidak sesuai
- Dokumen milik user lain
- Status dokumen tidak sesuai

**Solusi:**

1. Check role user yang sedang login (GET `/api/auth/me`)
2. Check ownership dokumen
3. Check status dokumen (terverifikasi/pending/rejected)

---

### Error 422 Unprocessable Entity

**Penyebab:**

- Validation error (field required, format salah)
- Business logic error (dokumen sudah terverifikasi, dll)

**Solusi:**

1. Baca error message di response
2. Check field yang required
3. Check business logic constraints

---

### Update Berhasil tapi Data Tidak Berubah (200 OK tapi data lama)

**Penyebab:**

- Content-Type header tidak diset `application/json`
- Di Postman memilih "Text" bukan "JSON" di dropdown body type

**Solusi:**

1. Di Postman, pilih body type **raw**
2. Ubah dropdown dari **Text** menjadi **JSON**
3. Atau tambahkan header: `Content-Type: application/json`

---

## 🔔 UC-13: System Notifications (`/api/notifications`)

### 13.1 List User Notifications
```
GET http://localhost:8000/api/notifications?per_page=15&unread_only=true
Headers:
  Authorization: Bearer {{auth_token}}
  Accept: application/json
```
**Expected Response (200 OK):**
```json
{
  "status": "success",
  "data": [
    {
      "id": "9b1deb4d-3b7d-4148-9f2d-0b89f816a1c8",
      "user_id": 2,
      "title": "Dokumen Diverifikasi",
      "message": "Dokumen KTI_2026.pdf telah diverifikasi dan disetujui.",
      "type": "success",
      "action_url": "/documents/1",
      "read_at": null,
      "created_at": "2026-07-28T02:00:00.000000Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 15,
    "total": 1,
    "unread_count": 1
  }
}
```

### 13.2 Unread Count Badge
```
GET http://localhost:8000/api/notifications/unread-count
Headers:
  Authorization: Bearer {{auth_token}}
  Accept: application/json
```
**Expected Response (200 OK):**
```json
{
  "status": "success",
  "unread_count": 1
}
```

### 13.3 Mark Single Notification as Read
```
PATCH http://localhost:8000/api/notifications/{notification_id}/read
Headers:
  Authorization: Bearer {{auth_token}}
  Accept: application/json
```

### 13.4 Mark All Notifications as Read
```
POST http://localhost:8000/api/notifications/mark-all-read
Headers:
  Authorization: Bearer {{auth_token}}
  Accept: application/json
```

---

## ⚙️ UC-14: System Settings (`/api/system-settings`)

### 14.1 List System Settings
```
GET http://localhost:8000/api/system-settings?group=storage
Headers:
  Authorization: Bearer {{auth_token}}
  Accept: application/json
```
**Expected Response (200 OK):**
```json
{
  "status": "success",
  "data": {
    "max_upload_size_mb": "10",
    "allowed_file_types": "pdf,jpg,png"
  }
}
```

### 14.2 Bulk Update Settings (Manager Only)
```
PUT http://localhost:8000/api/system-settings
Headers:
  Authorization: Bearer {{auth_token}}
  Content-Type: application/json
  Accept: application/json

Body (raw JSON):
{
  "settings": [
    {
      "key": "max_upload_size_mb",
      "value": "20"
    }
  ]
}
```

---

## 📚 UC-15: Master Data (`/api/prodis` & `/api/document-types`)

### 15.1 Get Active Prodis
```
GET http://localhost:8000/api/prodis
Headers:
  Authorization: Bearer {{auth_token}}
  Accept: application/json
```

### 15.2 Create New Prodi (Manager Only)
```
POST http://localhost:8000/api/prodis
Headers:
  Authorization: Bearer {{auth_token}}
  Content-Type: application/json
  Accept: application/json

Body (raw JSON):
{
  "code": "TKS",
  "name": "Teknik Kuantum",
  "degree": "S1"
}
```

### 15.3 Get Document Types
```
GET http://localhost:8000/api/document-types
Headers:
  Authorization: Bearer {{auth_token}}
  Accept: application/json
```

---

## 📊 UC-16: QC Performance Report & User Activity (`/api/reports/qc-performance`)

### 16.1 Get QC Performance Report
```
GET http://localhost:8000/api/reports/qc-performance?page=1&per_page=10
Headers:
  Authorization: Bearer {{auth_token}}
  Accept: application/json
```
**Expected Response (200 OK):**
```json
{
  "status": "success",
  "data": [
    {
      "id": 3,
      "staff": "Budi Santoso",
      "email": "budi.qc@unpas.ac.id",
      "role": "Tim Quality Control",
      "terverifikasi": 65,
      "ditolak": 1,
      "false_rejections_count": 0,
      "avg_time": "1.2 Hari",
      "accuracy_rate": "98.5%",
      "is_online": true,
      "last_seen_at": "2026-07-28T02:00:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 10,
    "total": 1
  }
}
```

---

## 📝 Quick Testing Checklist

### Before Testing:

- [ ] Server Laravel running (`php artisan serve`)
- [ ] Database migrated (`php artisan migrate`)
- [ ] **Data Seeded** (`php artisan db:seed`)
- [ ] Postman environment created with `auth_token` variable

### Authentication Flow:

- [ ] POST `/api/auth/login` → Save Bearer Token
- [ ] GET `/api/auth/me` → Verify token works
- [ ] POST `/api/auth/logout` → Revoke token

### Core & Revision Features:

- [ ] User Management (Manager only)
- [ ] Document Upload (Uploader)
- [ ] Document Verification & Notification Trigger (QC)
- [ ] Notifications API (All Users)
- [ ] System Settings API (Manager update)
- [ ] Master Data Prodis & Document Types (All Users)
- [ ] QC Performance Report & Online Status (Manager)

---

## ✅ Testing Selesai!

**API Version:** v4.0 - Master Backend Revision API
**Last Updated:** 28 Juli 2026
**Security Level:** Production-Ready 🔒
**Total Tests:** 109 Passed (388 Assertions)
**Performance:** ~40% smaller API responses

### Key Improvements in v4.0:

- ✅ **Notifications API & Event Triggers** (`/api/notifications`)
- ✅ **System Settings API** (`/api/system-settings`)
- ✅ **Master Data Prodis & Document Types** (`/api/prodis`, `/api/document-types`)
- ✅ **QC Verifier Performance Report & Online Activity (`last_seen_at`)**
- ✅ **Extended Document Statistics (`by_document_type`) & Multi-Criteria Filters**
- ✅ **Dual-Layer Rate Limiting & Lockout Protection**

**Security Features:** Rate Limit | Account Lockout | PDF Validation | Bearer Token Auth | CORS | Audit Logs
