<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            [
                'key' => 'max_upload_size_mb',
                'value' => '10',
                'group_name' => 'storage',
                'description' => 'Batas maksimal ukuran file upload dalam Megabyte (MB)',
            ],
            [
                'key' => 'allowed_file_types',
                'value' => 'pdf,jpg,png',
                'group_name' => 'storage',
                'description' => 'Ekstensi file yang diizinkan untuk diunggah',
            ],
            [
                'key' => 'app_name',
                'value' => 'Digitalisasi Arsip AGY',
                'group_name' => 'general',
                'description' => 'Nama resmi aplikasi Digital Arsip FT Unpas',
            ],
            [
                'key' => 'notification_email_enabled',
                'value' => 'true',
                'group_name' => 'notification',
                'description' => 'Aktifkan notifikasi email saat verifikasi dokumen',
            ],
        ];

        foreach ($settings as $setting) {
            SystemSetting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
