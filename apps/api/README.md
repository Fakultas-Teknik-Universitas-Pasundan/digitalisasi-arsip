# Sistem Digitalisasi Arsip - API Backend

## 📋 Overview

RESTful API untuk sistem digitalisasi arsip dengan fitur:

- **Authentication:** Laravel Sanctum Session-Based (Stateful SPA Auth)
- **Authorization:** Role-based access control (Manager, QC, Uploader, SBAP)
- **Security:** Dual-layer rate limiting, account lockout, comprehensive audit logging
- **Performance:** Optimized database queries, efficient indexes

---

## 🚀 Quick Start

### Prerequisites

- PHP 8.5+ (teruji kompatibel pada PHP 8.5.8)
- Composer 2.x
- MySQL 8.0+ / MariaDB
- Node.js (untuk frontend)

### Installation

```bash
# 1. Clone repository
git clone <repository-url>
cd apps/api

# 2. Install dependencies
composer install

# 3. Setup environment
cp .env.example .env
php artisan key:generate

# 4. Configure database (.env)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=digitalisasi_arsip
DB_USERNAME=root
DB_PASSWORD=

# 5. Run migrations & seeders
php artisan migrate
php artisan db:seed

# 6. Start server
php artisan serve
```

Server running at: `http://localhost:8000`

---

## 📚 Documentation

| Document | Description |
| --- | --- |
| [API_CONTRACT.md](API_CONTRACT.md) | Official API Contract Specification (v4.0.0) |
| [Panduan_Testing_API_Postman.md](Panduan_Testing_API_Postman.md) | Comprehensive API testing guide |
| [Swagger UI (`/api/documentation`)](http://localhost:8000/api/documentation) | Interactive OpenAPI 3.0 Documentation |
| [docs/security-testing-guide.md](docs/security-testing-guide.md) | Security features testing guide |

---

## 🔐 Authentication

API menggunakan **Laravel Sanctum Session-Based Authentication (Stateful)**:

```
1. GET /sanctum/csrf-cookie → Set CSRF Cookie
2. POST /api/auth/login → Authenticate & Regenerate Session Cookie
3. Request berikutnya menyertakan Cookie & Header X-XSRF-TOKEN
```

### Session Management

- **Login:** Autentikasi kredensial & `session()->regenerate()`
- **Logout:** Invalidasi session via `Auth::guard('web')->logout()` & `session()->invalidate()`

---

## 🔐 Security Features

### Dual-Layer Rate Limiting

- **Layer 1 (IP-based):** 75 login attempts/minute per IP
- **Layer 2 (Account-based):** 5 failed attempts → temporary lockout
    - Manager: Locked for 5 minutes
    - Other roles: Locked for 1 minute

### Audit Logging

All critical actions logged:

- User management
- Document operations (upload, verify, download, delete)
- Authentication events (login, logout, lockout)

### Configuration

Rate limiting configurable via `.env`:

```env
LOGIN_IP_LIMIT_ATTEMPTS=75
LOGIN_MAX_ATTEMPTS=5
LOGIN_LOCKOUT_MANAGER_MINUTES=5
LOGIN_LOCKOUT_DEFAULT_MINUTES=1
```

See `config/login-security.php` for all options.

---

## 📊 API Endpoints

### Public Endpoints

- `GET /sanctum/csrf-cookie` - Initialize CSRF protection
- `POST /api/auth/login` - Login (rate limited)
- `POST /api/auth/check-email` - Check email availability

### Protected Endpoints (auth:sanctum — Stateful Session required)

**Authentication:**

- `GET /api/auth/me` - Current user
- `POST /api/auth/logout` - Logout (invalidate session)

**Users (Manager only):**

- `GET /api/users` - List users
- `POST /api/users` - Create user
- `GET /api/users/{id}` - View user
- `PATCH /api/users/{id}` - Update user
- `DELETE /api/users/{id}` - Delete user
- `GET /api/users/statistics` - User statistics
- `POST /api/users/delete-multiple` - Delete multiple users

**Documents:**

- `GET /api/documents` - List documents
- `POST /api/documents` - Upload document (Uploader/Manager)
- `GET /api/documents/{id}` - View document
- `PATCH /api/documents/{id}` - Update document (Uploader/Manager)
- `DELETE /api/documents/{id}` - Delete document (Uploader/Manager)
- `GET /api/documents/pending` - Pending documents (QC/Manager)
- `PATCH /api/documents/{id}/verify` - Verify/reject (QC/Manager)
- `GET /api/documents/{id}/download` - Download document
- `GET /api/documents/{id}/view` - View document inline (PDF viewer)
- `POST /api/documents/download-multiple` - Download multiple as ZIP
- `POST /api/documents/delete-multiple` - Delete multiple documents
- `GET /api/documents/statistics` - Document statistics

**Notifications:**

- `GET /api/notifications` - List user notifications (`?unread_only=true`)
- `GET /api/notifications/unread-count` - Get unread count badge
- `PATCH /api/notifications/{id}/read` - Mark single notification as read
- `POST /api/notifications/mark-all-read` - Mark all user notifications as read
- `DELETE /api/notifications/{id}` - Delete notification

**System Settings:**

- `GET /api/system-settings` - List system settings (`?group=general`)
- `PUT /api/system-settings` - Bulk update system settings (Manager only)

**Master Data:**

- `GET /api/prodis` - List active FT Unpas Prodis
- `POST /api/prodis` - Create new Prodi (Manager only)
- `GET /api/document-types` - List document types

**Audit Logs (Manager only):**

- `GET /api/audit-logs` - List audit logs
- `GET /api/audit-logs/statistics` - Statistics
- `GET /api/audit-logs/export` - Export to CSV

**Reports (Manager only):**

- `POST /api/reports/generate` - Generate report (PDF/Excel/CSV)
- `GET /api/reports/dashboard` - Dashboard statistics
- `GET /api/reports/qc-performance` - QC Verifier Performance report & online status

---

## 👥 User Roles

| Role         | Permissions                                                       |
| ------------ | ----------------------------------------------------------------- |
| **Manager**  | Full access (user management, all documents, audit logs, reports) |
| **QC**       | Verify/reject documents, view & download pending documents        |
| **Uploader** | Upload, update, delete own documents                              |
| **SBAP**     | Download & view verified documents                                |

---

## 🗄️ Database Schema

### Core Tables

- `users` - User accounts with roles & `last_seen_at` timestamp
- `documents` - Document metadata & file info
- `notifications` - System notifications triggered on document verification
- `system_settings` - Centralized application configurations
- `prodis` - Master Data Program Studi FT Unpas
- `document_types` - Master Data Jenis Dokumen
- `audit_logs` - Comprehensive activity logging
- `login_attempts` - Failed login tracking
- `personal_access_tokens` - Sanctum Bearer tokens

### Document Types (Enum & Master Data)

- `nilai` - Nilai/Grades
- `ijazah` - Diploma/Certificate
- `transkrip` - Transcript
- `berita_acara_sidang` - Sidang Report

### Prodi (Program Studi - Enum & Master Data)

- Informatika (TIF), Pangan (TP), Industri (TI), Mesin (TMI), Lingkungan (TL), Perencanaan Wilayah Kota (PWK)

---

## 🧪 Testing

### Run Tests

```bash
php artisan test

# With increased memory (if needed)
php -d memory_limit=2G artisan test
```

### Test Coverage

- **109 Passed Tests (388 Assertions)** across 20 Feature Test files
- Authentication & authorization
- Notifications API & automated event triggers
- System settings & Master data API
- QC performance reports & user activity tracking
- CRUD operations
- Security features (rate limiting, lockout)
- Audit logging
- Report generation

---

## 🔄 Versions

| Version  | Released   | Features                                                   |
| -------- | ---------- | ---------------------------------------------------------- |
| **v4.0** | 2026-07-28 | Master Backend Revision API: Notifications, Settings, Master Data (Prodi & DocumentTypes), QC Performance Report, Automatic Event Triggers |
| **v3.1** | 2026-07-21 | PHP 8.5.8 compatibility upgrade & Composer dependency update |
| **v3.0** | 2026-07-18 | Session-based Sanctum authentication (Stateful SPA auth)   |
| **v2.2** | 2026-01-28 | Rate limiting, account lockout, enhanced audit logging     |
| **v2.1** | 2025-12-15 | Enum support, berita_acara_sidang document type            |
| **v2.0** | 2025-11-20 | Sanctum stateful API, optimized responses                  |
| **v1.0** | 2025-10-01 | Initial release                                            |

---

## 🛠️ Tech Stack

**Backend:**

- Laravel 12.x
- PHP 8.5+ (8.5.8)
- MySQL 8.0+

**Authentication:**

- Laravel Sanctum (Session-Based / Stateful SPA)
- Cookie-based authentication with CSRF protection

**Security:**

- Rate limiting (IP + Account)
- Role-based access control
- Comprehensive audit logging
- SQL injection prevention (Eloquent ORM)
- XSS protection

---

## 📝 Environment Variables

### Required (.env)

```env
APP_NAME="Digitalisasi Arsip API"
APP_ENV=production
APP_KEY=<generated>
APP_DEBUG=false
APP_URL=

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=digitalisasi_arsip
DB_USERNAME=root
DB_PASSWORD=

SANCTUM_TOKEN_EXPIRATION=1440

CORS_ALLOWED_ORIGINS=https://app.yourdomain.com
```

### Optional (Rate Limiting)

```env
LOGIN_IP_LIMIT_ATTEMPTS=75
LOGIN_MAX_ATTEMPTS=5
LOGIN_LOCKOUT_MANAGER_MINUTES=5
LOGIN_LOCKOUT_DEFAULT_MINUTES=1
LOGIN_CLEANUP_AFTER_MINUTES=10
```

---

## 🚀 Deployment

### Production Checklist

- [ ] Set `APP_ENV=production`
- [ ] Set `APP_DEBUG=false`
- [ ] Set `SANCTUM_TOKEN_EXPIRATION` (default: 1440 minutes = 24h)
- [ ] Configure `CORS_ALLOWED_ORIGINS`
- [ ] Run migrations: `php artisan migrate --force`
- [ ] Clear caches:
    ```bash
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    ```
- [ ] Set up SSL/HTTPS
- [ ] Set up database backups
- [ ] Monitor audit logs

---

## 📞 Support

For questions or issues:

- Review documentation in `docs/` directory
- Check `swagger.yaml` for API specification
- Review audit logs for debugging

---

## ✅ Status

**Production Ready** ✅

- All tests passing
- Security features implemented
- Documentation complete
- Performance optimized
- Token-based auth (stateless)

**Last Updated:** 2026-07-28
