<?php

namespace Database\Seeders;

use App\Enums\DocumentType as DocumentTypeEnum;
use App\Models\DocumentType;
use Illuminate\Database\Seeder;

class DocumentTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            [
                'code' => DocumentTypeEnum::NILAI->value,
                'name' => DocumentTypeEnum::NILAI->label(),
                'requires_verification' => true,
            ],
            [
                'code' => DocumentTypeEnum::TRANSKRIP->value,
                'name' => DocumentTypeEnum::TRANSKRIP->label(),
                'requires_verification' => true,
            ],
            [
                'code' => DocumentTypeEnum::IJAZAH->value,
                'name' => DocumentTypeEnum::IJAZAH->label(),
                'requires_verification' => true,
            ],
            [
                'code' => DocumentTypeEnum::BERITA_ACARA_SIDANG->value,
                'name' => DocumentTypeEnum::BERITA_ACARA_SIDANG->label(),
                'requires_verification' => true,
            ],
        ];

        foreach ($types as $type) {
            DocumentType::updateOrCreate(
                ['code' => $type['code']],
                $type
            );
        }
    }
}
