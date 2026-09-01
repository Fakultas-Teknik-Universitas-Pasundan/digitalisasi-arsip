# 📜 API Contract Specification — Digitalisasi Arsip FT Unpas

> **Version:** 4.0.0 (Master Backend Revision)  
> **Base URL:** `http://localhost:8000` (Local) / `https://api-arsip.ft-unpas.ac.id` (Production)  
> **Interactive Swagger UI:** `http://localhost:8000/api/documentation`  
> **Security Level:** Production-Ready (Rate Limiting, Account Lockout, CSRF/Sanctum Auth, RBAC)

---

## 📑 Daftar Isi
1. [Standar Protokol & Autentikasi](#1-standar-protokol--autentikasi)
2. [Format Standar Respons JSON (Envelope)](#2-format-standar-respons-json-envelope)
3. [Daftar Enum & Master Constants](#3-daftar-enum--master-constants)
4. [Spesifikasi Endpoint API](#4-spesifikasi-endpoint-api)
   - [4.1 Autentikasi & Sesi](#41-autentikasi--sesi)
   - [4.2 Notifikasi Sistem (UC-13)](#42-notifikasi-sistem-uc-13)
   - [4.3 Pengaturan Sistem (UC-14)](#43-pengaturan-sistem-uc-14)
   - [4.4 Master Data Prodi & Tipe Dokumen (UC-15)](#44-master-data-prodi--tipe-dokumen-uc-15)
   - [4.5 Manajemen Pengguna (UC-01)](#45-manajemen-pengguna-uc-01)
   - [4.6 Manajemen & Verifikasi Dokumen (UC-04, 06, 07, 08, 10)](#46-manajemen--verifikasi-dokumen-uc-04-06-07-08-10)
   - [4.7 Log Aktivitas / Audit Log (UC-03)](#47-log-aktivitas--audit-log-uc-03)
   - [4.8 Laporan & Analitik Performa QC (UC-09, UC-16)](#48-laporan--analitik-performa-qc-uc-09-uc-16)
5. [Status Code & Penanganan Error](#5-status-code--penanganan-error)

---

## 1. Standar Protokol & Autentikasi

### A. Mekanisme Autentikasi Sesi (SPA / Next.js)
API ini menggunakan **Laravel Sanctum Stateful Session Authentication** dengan perlindungan CSRF.

**Header Wajib untuk Setiap Request:**
```http
Accept: application/json
Content-Type: application/json
X-Requested-With: XMLHttpRequest
X-XSRF-TOKEN: <nilai-dari-cookie-XSRF-TOKEN>
```

**Alur Autentikasi SPA:**
1. **Inisialisasi CSRF:** `GET /sanctum/csrf-cookie` (Mendapatkan cookie `XSRF-TOKEN` dan `laravel_session`).
2. **Login:** `POST /api/auth/login` dengan `{ "email": "...", "password": "..." }`.
3. **Cek Sesi:** `GET /api/auth/me` untuk mendapatkan informasi profil user yang sedang aktif.
4. **Logout:** `POST /api/auth/logout` untuk menghapus sesi dan cookie.

### B. Matriks Hak Akses (Role-Based Access Control)
| Role | Deskripsi & Hak Akses |
| :--- | :--- |
| **`manager`** | Full Access: Manajemen user, system settings, master data, seluruh dokumen, laporan, analitik QC, dan audit log. |
| **`uploader`** | Upload dokumen baru, edit & hapus dokumen milik sendiri yang berstatus ditolak (*rejected*), melihat notifikasi sendiri. |
| **`qc`** | Melihat antrean verifikasi dokumen (*pending*), memverifikasi/menolak dokumen dengan catatan revisi, melihat notifikasi. |
| **`sbap`** | Mencari, melihat detail, dan mengunduh (*single/bulk ZIP*) dokumen yang telah berstatus terverifikasi (*verified*). |

---

## 2. Format Standar Respons JSON (Envelope)

### A. Respons Sukses Single Object / Resource
```json
{
  "status": "success",
  "message": "Operasi berhasil dilakukan.",
  "data": { ... }
}
```

### B. Respons Sukses List dengan Pagination
```json
{
  "status": "success",
  "data": [
    { ... },
    { ... }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 72
  }
}
```

### C. Respons Validasi Gagal (HTTP 422 Unprocessable Entity)
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": [
      "Email sudah terdaftar dalam sistem."
    ],
    "file": [
      "Ukuran file tidak boleh melebihi 5MB."
    ]
  }
}
```

### D. Respons Error Umum (HTTP 401, 403, 404, 429, 500)
```json
{
  "message": "This action is unauthorized."
}
```

---

## 3. Daftar Enum & Master Constants

### `UserRole`
* `manager` — Administrator / Kepala Bagian
* `uploader` — Staf Pengunggah Arsip Prodi
* `qc` — Staf Quality Control / Verifikator
* `sbap` — Staf Bagian Administrasi Akademik

### `DocumentType`
* `nilai` — Berkas Nilai Mata Kuliah
* `transkrip` — Transkrip Akademik
* `ijazah` — Salinan Ijazah
* `berita_acara_sidang` — Berita Acara Sidang Sarjana (BAS)

### `DocumentStatus`
* `menunggu_verifikasi` — Baru diunggah / Menunggu tinjauan QC
* `terverifikasi` — Disetujui oleh QC / Manager
* `tidak_terverifikasi` — Ditolak oleh QC / Memerlukan perbaikan

---

## 4. Spesifikasi Endpoint API

---

### 4.1 Autentikasi & Sesi

#### `POST /api/auth/login`
* **Deskripsi:** Autentikasi pengguna ke dalam sistem (Proteksi rate limit 75 req/menit & account lockout 5x failed).
* **Akses:** Public
* **Request Body:**
```json
{
  "email": "manager@test.com",
  "password": "password123",
  "remember": true
}
```
* **Response 200 OK:**
```json
{
  "message": "Login berhasil.",
  "data": {
    "user": {
      "id": 1,
      "name": "Manager Akademik",
      "email": "manager@test.com",
      "nip": "198001012005011001",
      "role": "manager",
      "last_seen_at": "2026-08-21T01:00:00.000000Z",
      "created_at": "2026-01-14T04:00:00.000000Z",
      "updated_at": "2026-08-21T01:00:00.000000Z"
    }
  }
}
```

#### `POST /api/auth/check-email`
* **Deskripsi:** Memeriksa apakah email tersedia atau sudah digunakan.
* **Akses:** Public
* **Request Body:** `{ "email": "user@test.com" }`
* **Response 200 OK:**
```json
{
  "status": "success",
  "exists": false,
  "message": "Email tersedia."
}
```

#### `GET /api/auth/me`
* **Deskripsi:** Mengambil informasi profil pengguna yang sedang login.
* **Akses:** Authenticated
* **Response 200 OK:**
```json
{
  "message": "Profil pengguna berhasil diambil.",
  "data": {
    "id": 1,
    "name": "Manager Akademik",
    "email": "manager@test.com",
    "nip": "198001012005011001",
    "role": "manager",
    "last_seen_at": "2026-08-21T01:00:00.000000Z"
  }
}
```

#### `POST /api/auth/logout`
* **Deskripsi:** Mengakhiri sesi pengguna aktif dan menginvaliasi session cookie.
* **Akses:** Authenticated
* **Response 200 OK:**
```json
{
  "message": "Logout berhasil."
}
```

---

### 4.2 Notifikasi Sistem (UC-13)

#### `GET /api/notifications`
* **Deskripsi:** Mengambil daftar notifikasi pengguna terautentikasi.
* **Akses:** Authenticated
* **Query Params:**
  * `unread_only` (optional, boolean): `true` untuk hanya menampilkan yang belum dibaca.
  * `per_page` (optional, integer): default `15`.
  * `page` (optional, integer): default `1`.
* **Response 200 OK:**
```json
{
  "status": "success",
  "data": [
    {
      "id": "d3b07384-d113-4a18-971c-99d821217e94",
      "user_id": 2,
      "title": "Dokumen Diverifikasi",
      "message": "Dokumen 'Nilai_Pemrograman_Web.pdf' telah diverifikasi dan disetujui.",
      "type": "document_verified",
      "action_url": "/documents/12",
      "read_at": null,
      "created_at": "2026-08-21T00:30:00.000000Z",
      "updated_at": "2026-08-21T00:30:00.000000Z"
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

#### `GET /api/notifications/unread-count`
* **Deskripsi:** Mengambil jumlah total notifikasi yang belum dibaca untuk badge navbar.
* **Akses:** Authenticated
* **Response 200 OK:**
```json
{
  "status": "success",
  "unread_count": 3
}
```

#### `PATCH /api/notifications/{id}/read`
* **Deskripsi:** Menandai 1 notifikasi spesifik sebagai sudah dibaca (`read_at` diisi timestamp).
* **Akses:** Authenticated (Hanya pemilik notifikasi)
* **Response 200 OK:**
```json
{
  "status": "success",
  "message": "Notifikasi berhasil ditandai sebagai sudah dibaca.",
  "data": {
    "id": "d3b07384-d113-4a18-971c-99d821217e94",
    "read_at": "2026-08-21T01:10:00.000000Z"
  }
}
```

#### `POST /api/notifications/mark-all-read`
* **Deskripsi:** Menandai seluruh notifikasi milik pengguna menjadi sudah dibaca.
* **Akses:** Authenticated
* **Response 200 OK:**
```json
{
  "status": "success",
  "message": "Semua notifikasi berhasil ditandai sebagai sudah dibaca."
}
```

#### `DELETE /api/notifications/{id}`
* **Deskripsi:** Menghapus satu notifikasi.
* **Akses:** Authenticated (Hanya pemilik notifikasi)
* **Response 200 OK:**
```json
{
  "status": "success",
  "message": "Notifikasi berhasil dihapus."
}
```

---

### 4.3 Pengaturan Sistem (UC-14)

#### `GET /api/system-settings`
* **Deskripsi:** Mengambil konfigurasi sistem dalam bentuk key-value map dan array detail.
* **Akses:** Authenticated
* **Query Params:** `group` (optional, string): Filter nama grup (contoh: `upload`, `app`).
* **Response 200 OK:**
```json
{
  "status": "success",
  "data": {
    "max_upload_size_mb": "10",
    "allowed_file_types": "pdf",
    "app_name": "Digitalisasi Arsip FT Unpas",
    "maintenance_mode": "false"
  },
  "details": [
    {
      "id": 1,
      "key": "max_upload_size_mb",
      "value": "10",
      "group_name": "upload",
      "description": "Maksimal ukuran upload berkas dalam MB",
      "updated_by": 1,
      "updated_at": "2026-08-21T00:00:00.000000Z"
    }
  ]
}
```

#### `PUT /api/system-settings`
* **Deskripsi:** Memperbarui kumpulan pengaturan sistem secara massal.
* **Akses:** Manager only (`role: manager`)
* **Request Body:**
```json
{
  "settings": [
    { "key": "max_upload_size_mb", "value": "15" },
    { "key": "maintenance_mode", "value": "false" }
  ]
}
```
* **Response 200 OK:**
```json
{
  "status": "success",
  "message": "Pengaturan sistem berhasil diperbarui.",
  "data": [
    {
      "id": 1,
      "key": "max_upload_size_mb",
      "value": "15",
      "group_name": "upload",
      "updated_by": 1,
      "updated_at": "2026-08-21T01:15:00.000000Z"
    }
  ]
}
```

---

### 4.4 Master Data Prodi & Tipe Dokumen (UC-15)

#### `GET /api/prodis`
* **Deskripsi:** Mengambil daftar program studi aktif di Fakultas Teknik.
* **Akses:** Authenticated
* **Response 200 OK:**
```json
{
  "status": "success",
  "data": [
    { "id": 1, "code": "IF", "name": "Teknik Informatika", "degree": "S1", "is_active": true },
    { "id": 2, "code": "TP", "name": "Teknologi Pangan", "degree": "S1", "is_active": true },
    { "id": 3, "code": "TI", "name": "Teknik Industri", "degree": "S1", "is_active": true },
    { "id": 4, "code": "TM", "name": "Teknik Mesin", "degree": "S1", "is_active": true },
    { "id": 5, "code": "TL", "name": "Teknik Lingkungan", "degree": "S1", "is_active": true },
    { "id": 6, "code": "PWK", "name": "Perencanaan Wilayah dan Kota", "degree": "S1", "is_active": true }
  ]
}
```

#### `POST /api/prodis`
* **Deskripsi:** Menambahkan program studi baru ke master data.
* **Akses:** Manager only
* **Request Body:**
```json
{
  "code": "TS",
  "name": "Teknik Sipil",
  "degree": "S1",
  "is_active": true
}
```
* **Response 201 Created:**
```json
{
  "status": "success",
  "message": "Program Studi berhasil ditambahkan.",
  "data": {
    "id": 7,
    "code": "TS",
    "name": "Teknik Sipil",
    "degree": "S1",
    "is_active": true,
    "created_at": "2026-08-21T01:20:00.000000Z",
    "updated_at": "2026-08-21T01:20:00.000000Z"
  }
}
```

#### `GET /api/document-types`
* **Deskripsi:** Mengambil daftar jenis dokumen yang didukung sistem.
* **Akses:** Authenticated
* **Response 200 OK:**
```json
{
  "status": "success",
  "data": [
    { "id": 1, "code": "nilai", "name": "Nilai", "requires_verification": true },
    { "id": 2, "code": "transkrip", "name": "Transkrip", "requires_verification": true },
    { "id": 3, "code": "ijazah", "name": "Ijazah", "requires_verification": true },
    { "id": 4, "code": "berita_acara_sidang", "name": "Berita Acara Sidang", "requires_verification": true }
  ]
}
```

---

### 4.5 Manajemen Pengguna (UC-01)

#### `GET /api/users`
* **Deskripsi:** Mengambil daftar seluruh staf pengguna sistem dengan filter dan paginasi.
* **Akses:** Manager only
* **Query Params:** `role`, `search`, `per_page`, `page`.
* **Response 200 OK:**
```json
{
  "message": "Daftar pengguna berhasil diambil.",
  "data": [
    {
      "id": 2,
      "name": "Staf Uploader IF",
      "email": "uploader_if@test.com",
      "nip": "198502022010011002",
      "role": "uploader",
      "last_seen_at": "2026-08-21T01:05:00.000000Z",
      "created_at": "2026-01-14T04:00:00.000000Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 2,
    "per_page": 15,
    "total": 18
  }
}
```

#### `GET /api/users/statistics`
* **Deskripsi:** Ringkasan statistik jumlah total, staf aktif (login dalam 30 hari), dan staf baru.
* **Akses:** Manager only
* **Response 200 OK:**
```json
{
  "message": "Statistik pengguna berhasil diambil.",
  "data": {
    "total_users": 18,
    "active_users": 14,
    "new_users": 2
  }
}
```

#### `POST /api/users`
* **Deskripsi:** Membuat akun staf baru.
* **Akses:** Manager only
* **Request Body:**
```json
{
  "name": "Staf QC Baru",
  "email": "qc_baru@test.com",
  "nip": "199003032015011003",
  "password": "Password123!",
  "role": "qc"
}
```
* **Response 201 Created**

#### `POST /api/users/delete-multiple`
* **Deskripsi:** Menghapus beberapa pengguna sekaligus (proteksi: manager tidak bisa menghapus akun sendiri).
* **Akses:** Manager only
* **Request Body:** `{ "ids": [5, 6, 7] }`
* **Response 200 OK:**
```json
{
  "status": "success",
  "message": "3 pengguna berhasil dihapus."
}
```

---

### 4.6 Manajemen & Verifikasi Dokumen (UC-04, 06, 07, 08, 10)

#### `GET /api/documents`
* **Deskripsi:** Mengambil daftar dokumen arsip dengan dukungan multi-kriteria filtering lengkap.
* **Akses:** Authenticated (Manager/QC/SBAP melihat semua, Uploader hanya melihat dokumen miliknya).
* **Query Params:**
  * `document_type`: `nilai` | `transkrip` | `ijazah` | `berita_acara_sidang`
  * `prodi`: Nama prodi (contoh: `Teknik Informatika`)
  * `status`: `menunggu_verifikasi` | `terverifikasi` | `tidak_terverifikasi`
  * `tahun_ajaran`: (contoh: `2024/2025`)
  * `mata_kuliah`: (pencarian teks mata kuliah)
  * `tahun_lulus`: (contoh: `2024`)
  * `npm`: (NPM mahasiswa)
  * `date_from`, `date_to`: Rentang tanggal upload
  * `search`: Pencarian umum
  * `per_page`, `page`
* **Response 200 OK:** List dokumen + `meta` paginasi.

#### `GET /api/documents/pending`
* **Deskripsi:** Antrean dokumen yang sedang menunggu verifikasi QC (diurutkan secara FIFO / terlama dulu).
* **Akses:** QC & Manager
* **Response 200 OK:** List dokumen dengan status `menunggu_verifikasi`.

#### `GET /api/documents/statistics`
* **Deskripsi:** Statistik dokumen total, terverifikasi, pending, ditolak, dan breakdown per tipe dokumen.
* **Akses:** Authenticated
* **Response 200 OK:**
```json
{
  "message": "Statistik dokumen berhasil diambil.",
  "data": {
    "total_documents": 240,
    "verified_documents": 190,
    "pending_documents": 35,
    "rejected_documents": 15,
    "by_document_type": {
      "nilai": 120,
      "transkrip": 50,
      "ijazah": 45,
      "berita_acara_sidang": 25
    }
  }
}
```

#### `POST /api/documents` (Upload Dokumen)
* **Deskripsi:** Mengunggah berkas arsip PDF baru beserta metadata wajibnya.
* **Akses:** Uploader & Manager
* **Content-Type:** `multipart/form-data`
* **Form Fields:**
  * `file` (File PDF, max 5MB, required)
  * `document_type` (`nilai` | `transkrip` | `ijazah` | `berita_acara_sidang`, required)
  * `prodi` (string, required)
  * *Metadata Dokumen Nilai:* `tahun_ajaran`, `mata_kuliah`, `kelas` (required jika tipe `nilai`)
  * *Metadata Ijazah/Transkrip/BAS:* `tahun_lulus`, `npm` (required jika tipe `ijazah`/`transkrip`/`berita_acara_sidang`)
* **Response 201 Created**

#### `PATCH /api/documents/{id}/verify` (Verifikasi Dokumen - UC-08)
* **Deskripsi:** Melakukan verifikasi dokumen (menyetujui atau menolak dengan catatan perbaikan). Otomatis memicu pembuatan notifikasi untuk uploader terkait.
* **Akses:** QC & Manager
* **Request Body:**
```json
{
  "status": "tidak_terverifikasi",
  "verification_note": "Stempel dekanat pada lembar kedua buram, mohon scan ulang berkas asli."
}
```
* **Response 200 OK:**
```json
{
  "status": "success",
  "message": "Status verifikasi dokumen berhasil diperbarui.",
  "data": {
    "id": 12,
    "status": "Tidak Terverifikasi",
    "verification_note": "Stempel dekanat pada lembar kedua buram, mohon scan ulang berkas asli.",
    "verified_by_name": "QC Verifikator 1",
    "verified_at": "2026-08-21T01:25:00.000000Z"
  }
}
```

#### `GET /api/documents/{id}/view`
* **Deskripsi:** Menampilkan/streaming file PDF secara inline di browser (untuk preview/verifikasi modal).
* **Akses:** Authenticated
* **Response:** Stream `application/pdf` dengan header `Content-Disposition: inline`.

#### `GET /api/documents/{id}/download`
* **Deskripsi:** Mengunduh file dokumen asli.
* **Akses:** SBAP & Manager (hanya untuk dokumen berstatus `terverifikasi`), Uploader (hanya untuk dokumen miliknya).
* **Response:** Stream `application/pdf` dengan header `Content-Disposition: attachment; filename="..."`.

#### `POST /api/documents/download-multiple`
* **Deskripsi:** Mengunduh beberapa dokumen terverifikasi sekaligus dalam 1 file arsip ZIP.
* **Akses:** SBAP & Manager
* **Request Body:** `{ "ids": [1, 2, 3] }`
* **Response:** Stream `application/zip`.

#### `GET /api/documents/trashed`
* **Deskripsi:** Mengambil daftar dokumen yang berada di tempat sampah (*soft-deleted*).
* **Akses:** Manager only
* **Query Params:** `document_type`, `search`, `per_page`, `page`.
* **Response 200 OK:**
```json
{
  "message": "Daftar dokumen terhapus berhasil diambil.",
  "data": [
    {
      "id": 15,
      "file_name": "Nilai_Struktur_Data.pdf",
      "document_type": "nilai",
      "prodi": "Teknik Informatika",
      "deleted_at": "2026-08-21T02:00:00.000000Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 15,
    "total": 1
  }
}
```

#### `POST /api/documents/{id}/restore`
* **Deskripsi:** Memulihkan dokumen yang telah di-soft-delete kembali ke status aktif.
* **Akses:** Manager only
* **Response 200 OK:**
```json
{
  "message": "Dokumen berhasil dipulihkan.",
  "data": {
    "id": 15,
    "file_name": "Nilai_Struktur_Data.pdf",
    "status": "Tidak Terverifikasi"
  }
}
```

#### `DELETE /api/documents/{id}/force-delete`
* **Deskripsi:** Menghapus data dokumen dari database dan menghapus file fisik PDF dari storage secara permanen.
* **Akses:** Manager only
* **Response 200 OK:**
```json
{
  "message": "Dokumen berhasil dihapus permanen."
}
```

---

### 4.7 Log Aktivitas / Audit Log (UC-03)

#### `GET /api/audit-logs`
* **Deskripsi:** Mengambil riwayat log seluruh aktivitas sistem (upload, update, delete, verify, reject, download, login).
* **Akses:** Manager only
* **Query Params:** `action`, `user_id`, `model_type`, `date_from`, `date_to`, `per_page`, `page`.
* **Response 200 OK:** Daftar audit logs + meta pagination.

#### `GET /api/audit-logs/statistics`
* **Deskripsi:** Statistik aktivitas total, aktivitas hari ini, breakdown tindakan, dan recent activities.
* **Akses:** Manager only

#### `GET /api/audit-logs/export`
* **Deskripsi:** Mengekspor riwayat log audit ke file format CSV/Excel.
* **Akses:** Manager only

---

### 4.8 Laporan & Analitik Performa QC (UC-09, UC-16)

#### `GET /api/reports/dashboard`
* **Deskripsi:** Ringkasan statistik laporan dan aktivitas dokumen untuk widget dashboard manajerial.
* **Akses:** Manager only

#### `POST /api/reports/generate`
* **Deskripsi:** Menghasilkan dokumen laporan resmi dalam format PDF atau Excel (XLSX/CSV).
* **Akses:** Manager only
* **Request Body:**
```json
{
  "type": "monthly",
  "period_start": "2026-08-01",
  "period_end": "2026-08-31",
  "format": "pdf",
  "style": "detailed",
  "content": ["upload_stats", "qc_metrics", "doc_status", "user_activity"]
}
```
* **Response:** File stream PDF (`application/pdf`) atau Excel (`application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`).

#### `GET /api/reports/qc-performance` (UC-16)
* **Deskripsi:** Analitik performa individual staf verifikator QC, volume verifikasi/penolakan, tingkat akurasi verifikasi, serta status keaktifan online staf.
* **Akses:** Manager only
* **Query Params:** `start_date`, `end_date`, `per_page`, `page`.
* **Response 200 OK:**
```json
{
  "summary": {
    "total_qc_staff": 4,
    "active_online_qc": 2,
    "total_verified": 190,
    "total_rejected": 15,
    "average_accuracy_rate": 92.68
  },
  "verifiers": [
    {
      "id": 3,
      "name": "Staf QC 1",
      "email": "qc1@test.com",
      "is_online": true,
      "last_seen_at": "2026-08-21T01:20:00.000000Z",
      "total_processed": 110,
      "verified_count": 102,
      "rejected_count": 8,
      "accuracy_rate": 92.73
    }
  ],
  "pagination": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 10,
    "total": 4
  },
  "period": {
    "start_date": "2026-01-01",
    "end_date": "2026-08-21"
  }
}
```

---

## 5. Status Code & Penanganan Error

| HTTP Status Code | Makna & Penggunaan | Contoh Kasus |
| :--- | :--- | :--- |
| **`200 OK`** | Permintaan berhasil diproses. | Mengambil list data, update sukses, preview file. |
| **`201 Created`** | Resource baru berhasil dibuat. | Upload dokumen, buat user baru, tambah prodi baru. |
| **`400 Bad Request`** | Parameter request salah / tidak valid. | Permintaan tidak memenuhi logika prasyarat. |
| **`401 Unauthenticated`** | Sesi belum login atau cookie tidak valid. | Belum login atau session expired. |
| **`403 Forbidden`** | Pengguna login tetapi tidak punya hak akses. | Uploader mencoba akses halaman manajemen user manager. |
| **`404 Not Found`** | Data ID atau route tidak ditemukan. | ID Dokumen tidak ada di database. |
| **`422 Unprocessable Entity`** | Validasi form request gagal. | Format email salah, file bukan PDF, ukuran > 5MB. |
| **`429 Too Many Requests`** | Melebihi batas rate limit login. | 75 request/menit terlampaui / akun terkunci 5 menit. |
| **`500 Server Error`** | Terjadi kendala internal server / database. | Error eksepsi storage / koneksi DB terputus. |
