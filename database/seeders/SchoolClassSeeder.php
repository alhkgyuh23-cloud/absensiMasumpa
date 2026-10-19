<?php

namespace Database\Seeders;

use App\Models\SchoolClass;
use Illuminate\Database\Seeder;

class SchoolClassSeeder extends Seeder
{
    public function run(): void
    {
        // Sesuai data terbaru dari BLANKO_SPP_2026-2027.xlsx.
        // Tingkat X punya tambahan rombel ICP. Tingkat XI dan XII masing-masing A-F.
        $rombelPerTingkat = [
            10 => ['A', 'B', 'C', 'D', 'E', 'F', 'ICP'],
            11 => ['A', 'B', 'C', 'D', 'E', 'F'],
            12 => ['A', 'B', 'C', 'D', 'E', 'F'],
        ];

        foreach ($rombelPerTingkat as $tingkat => $daftarRombel) {
            foreach ($daftarRombel as $rombel) {
                SchoolClass::firstOrCreate([
                    'tingkat' => $tingkat,
                    'rombel' => $rombel,
                ]);
            }
        }
    }
}