<?php

namespace Database\Seeders;

use App\Enums\Prodi as ProdiEnum;
use App\Models\Prodi;
use Illuminate\Database\Seeder;

class ProdiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $prodis = [
            [
                'code' => 'TIF',
                'name' => ProdiEnum::INFORMATIKA->value,
                'degree' => 'S1',
                'is_active' => true,
            ],
            [
                'code' => 'TP',
                'name' => ProdiEnum::PANGAN->value,
                'degree' => 'S1',
                'is_active' => true,
            ],
            [
                'code' => 'TI',
                'name' => ProdiEnum::INDUSTRI->value,
                'degree' => 'S1',
                'is_active' => true,
            ],
            [
                'code' => 'TMI',
                'name' => ProdiEnum::MESIN->value,
                'degree' => 'S1',
                'is_active' => true,
            ],
            [
                'code' => 'TL',
                'name' => ProdiEnum::LINGKUNGAN->value,
                'degree' => 'S1',
                'is_active' => true,
            ],
            [
                'code' => 'PWK',
                'name' => ProdiEnum::PERENCANAAN_WILAYAH_KOTA->value,
                'degree' => 'S1',
                'is_active' => true,
            ],
        ];

        foreach ($prodis as $prodi) {
            Prodi::updateOrCreate(
                ['code' => $prodi['code']],
                $prodi
            );
        }
    }
}
