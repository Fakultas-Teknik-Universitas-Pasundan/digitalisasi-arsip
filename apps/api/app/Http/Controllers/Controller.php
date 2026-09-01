<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use OpenApi\Attributes as OA;

#[OA\Info(
    version: '4.0.0',
    title: 'API Digitalisasi Arsip',
    description: <<<'DESC'
REST API untuk sistem digitalisasi arsip akademik.

## Autentikasi
API ini menggunakan Laravel Sanctum dengan session-based authentication / Bearer Token.

**Langkah autentikasi (Session):**
1. GET `/api/csrf-cookie` untuk mendapatkan CSRF token
2. POST `/api/auth/login` dengan credentials
3. Sertakan cookie dan X-XSRF-TOKEN header pada setiap request

## Roles
- **Manager**: Full access ke semua fitur
- **Uploader**: Upload, update & delete dokumen sendiri
- **QC**: Verifikasi dokumen
- **SBAP**: Download dokumen terverifikasi
DESC,
    contact: new OA\Contact(name: 'API Support')
)]
#[OA\Server(
    url: 'http://localhost:8000',
    description: 'Local Development Server'
)]
#[OA\SecurityScheme(
    securityScheme: 'cookieAuth',
    type: 'apiKey',
    in: 'cookie',
    name: 'digitalisasi_arsip_api_session',
    description: 'Session cookie dari Laravel Sanctum'
)]
#[OA\Tag(name: 'Authentication', description: 'Endpoints untuk autentikasi')]
#[OA\Tag(name: 'Notifications', description: 'User Notifications & Badges (UC-13)')]
#[OA\Tag(name: 'System Settings', description: 'Application Settings & Configurations (UC-14)')]
#[OA\Tag(name: 'Master Data', description: 'Master Data Prodi & Document Types (UC-15)')]
#[OA\Tag(name: 'Users', description: 'User Management (UC-01) - Manager only')]
#[OA\Tag(name: 'Documents', description: 'Document Management (UC-04, UC-06, UC-07, UC-08, UC-10)')]
#[OA\Tag(name: 'Audit Logs', description: 'Activity Monitoring (UC-03) - Manager only')]
#[OA\Tag(name: 'Reports', description: 'Report Generation, QC Performance & Dashboard Statistics (UC-09, UC-16) - Manager only')]
#[OA\Tag(name: 'Statistics', description: 'Endpoints untuk statistik data')]
abstract class Controller
{
    use AuthorizesRequests;
}
