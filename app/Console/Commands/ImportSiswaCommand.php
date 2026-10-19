<?php

namespace App\Console\Commands;

use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportSiswaCommand extends Command
{
    protected $signature = 'import:siswa
        {file=BLANKO_SPP_2026-2027.xlsx : Nama file .xlsx di dalam storage/app}';

    protected $description = 'Import data siswa dari sheet X, XI, XII (format blok vertikal "KELAS : X.A") ke tabel students';

    // Sheet yang dibaca, masing-masing berisi beberapa blok kelas tersusun vertikal ke bawah
    private array $sheetYangDibaca = ['X', 'XI', 'XII'];

    private array $romawiKeTingkat = ['X' => 10, 'XI' => 11, 'XII' => 12];

    public function handle(): int
    {
        $path = storage_path('app/' . $this->argument('file'));

        if (! file_exists($path)) {
            $this->error("File tidak ditemukan: {$path}");
            return self::FAILURE;
        }

        $spreadsheet = IOFactory::load($path);

        $dibuat = 0;
        $dilewati = 0;
        $gagalKelas = [];

        foreach ($this->sheetYangDibaca as $namaSheet) {
            $sheet = $spreadsheet->getSheetByName($namaSheet);

            if (! $sheet) {
                $this->warn("Sheet \"{$namaSheet}\" tidak ditemukan, dilewati.");
                continue;
            }

            $rows = $sheet->toArray(null, true, true, false);
            $totalBaris = count($rows);

            $i = 0;
            while ($i < $totalBaris) {
                $label = $rows[$i][0] ?? null;

                if (! is_string($label) || ! preg_match('/^KELAS\s*:\s*(X|XI|XII)\.(\w+)\s*$/i', trim($label), $match)) {
                    $i++;
                    continue;
                }

                $tingkat = $this->romawiKeTingkat[strtoupper($match[1])];
                $rombel = strtoupper($match[2]);

                $kelas = SchoolClass::where('tingkat', $tingkat)
                    ->whereRaw('UPPER(rombel) = ?', [$rombel])
                    ->first();

                // Baris data dimulai 3 baris setelah label kelas:
                // baris label, baris header (NO|NO. INDUK|NAMA|...), baris sub-header (angka semester/bulan), baru data.
                $j = $i + 3;

                while ($j < $totalBaris) {
                    $noVal = $rows[$j][0] ?? null;
                    $nisVal = $rows[$j][1] ?? null;
                    $namaVal = $rows[$j][2] ?? null;

                    // Berhenti kalau ketemu label kelas berikutnya
                    if (is_string($noVal) && preg_match('/^KELAS\s*:/i', trim($noVal))) {
                        break;
                    }

                    // Baris kosong total -> lewati saja, bukan akhir blok (sub-header bulan kadang menyisakan baris kosong)
                    if ($noVal === null && $nisVal === null && $namaVal === null) {
                        $j++;
                        continue;
                    }

                    if (is_string($namaVal) && trim($namaVal) !== '') {
                        $nama = trim($namaVal);
                        $nis = $nisVal !== null ? trim((string) $nisVal) : '';

                        if ($nis === '') {
                            $j++;
                            continue; // lewati baris tanpa NIS sama sekali
                        }

                        if (! $kelas) {
                            $gagalKelas[] = "{$label} ({$nama})";
                            $j++;
                            continue;
                        }

                        $student = Student::firstOrCreate(
                            ['nis' => $nis],
                            [
                                'name' => $nama,
                                'card_id' => $nis, // sementara, update nanti kalau kartu fisik sudah dicetak
                                'class_id' => $kelas->id,
                                'is_active' => true,
                            ]
                        );

                        $student->wasRecentlyCreated ? $dibuat++ : $dilewati++;
                    }

                    $j++;
                }

                $i = $j; // lanjut dari posisi blok berikutnya
            }
        }

        $this->info("Selesai. Siswa baru dibuat: {$dibuat}. Dilewati (sudah ada/duplikat): {$dilewati}.");

        if (! empty($gagalKelas)) {
            $this->warn('Kelas tidak ditemukan untuk siswa berikut (dilewati):');
            foreach ($gagalKelas as $item) {
                $this->line("  - {$item}");
            }
        }

        return self::SUCCESS;
    }
}