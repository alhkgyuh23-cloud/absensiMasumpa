<?php

namespace App\Exports;

use DateTimeImmutable;
use DateTimeInterface;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Rekap absensi S / I / A dalam bentuk Excel, memakai data dari AbsenceRecapService::build().
 *
 * Isi file:
 *  - Sheet "Ringkasan": jumlah S / I / A / Hadir per tanggal (semua kelas pada filter) + rekap per kelas.
 *  - Satu sheet per kelas: NO | IDK | NAMA | satu kolom per tanggal | S | I | A,
 *    dengan baris jumlah per tanggal di bawah tabel.
 *
 * Total S / I / A memakai rumus COUNTIF, jadi ikut berubah bila sel diedit di Excel.
 */
class RekapAbsensiExport
{
    private const KOLOM_TANGGAL_AWAL = 4;   // kolom D
    private const BARIS_BAND = 11;          // pita merah nama kelas
    private const BARIS_DATA_AWAL = 15;     // baris siswa pertama

    private const HARI_PENDEK = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
    private const HARI_PANJANG = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    private const BULAN = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    private const BULAN_PENDEK = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    // Warna (ARGB)
    private const MERAH_BAND = 'FFFF0000';
    private const ABU_HEADER = 'FFD9D9D9';
    private const ABU_FOOTER = 'FFF2F2F2';
    private const KUNING_HEADER = 'FFFFD966';
    private const KUNING_BODY = 'FFFFF2CC';

    private array $efektifSet;

    /**
     * @param  array  $hasil  keluaran AbsenceRecapService::build()
     */
    public function __construct(
        private array $hasil,
        private DateTimeInterface $from,
        private DateTimeInterface $to,
        private string $semester,
        private string $tapel,
        private ?string $logoPath = null,
    ) {
        $this->efektifSet = array_flip($hasil['hariEfektifList'] ?? []);
    }

    /** Bangun file lalu tulis ke $path (boleh 'php://output'). */
    public function simpan(string $path): void
    {
        $spreadsheet = $this->buat();

        $writer = new Xlsx($spreadsheet);
        $writer->save($path);

        $spreadsheet->disconnectWorksheets();
    }

    public function buat(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);
        $spreadsheet->getProperties()
            ->setTitle('Rekap Absensi Murid')
            ->setCreator('MA Sumber Bungur Pakong Pamekasan');

        $ringkasan = $spreadsheet->getActiveSheet();
        $ringkasan->setTitle('Ringkasan');

        $tanggal = $this->hasil['tanggal'] ?? [];
        $info = [];
        $dipakai = ['ringkasan' => true];

        foreach ($this->hasil['kelas'] as $k) {
            $sheet = $spreadsheet->createSheet();
            $nama = $this->namaSheetUnik((string) $k['nama'], $dipakai);
            $sheet->setTitle($nama);

            $siswa = $k['siswa'];
            $siswa = is_array($siswa) ? $siswa : $siswa->all();

            $info[] = ['nama' => (string) $k['nama'], 'sheet' => $nama] + $this->isiSheetKelas($sheet, (string) $k['nama'], $siswa, $tanggal);
        }

        $this->isiRingkasan($ringkasan, $tanggal, $info);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    // ═════════════════════════════════════════════════════════════════
    // Sheet per kelas
    // ═════════════════════════════════════════════════════════════════

    private function isiSheetKelas(Worksheet $sheet, string $namaKelas, array $siswa, array $tanggal): array
    {
        $n = count($tanggal);
        $awal = self::KOLOM_TANGGAL_AWAL;
        $kolomAkhirTanggal = $awal + $n - 1;
        $kolomS = $awal + $n;
        $kolomI = $kolomS + 1;
        $kolomA = $kolomS + 2;
        $terakhir = $kolomA;

        $h = self::BARIS_DATA_AWAL;                   // baris siswa pertama
        $jumlahSiswa = count($siswa);
        $barisAkhirSiswa = $h + $jumlahSiswa - 1;
        $rS = $barisAkhirSiswa + 1;
        $rI = $rS + 1;
        $rA = $rI + 1;
        $rH = $rA + 1;

        $L = fn (int $kolom): string => Coordinate::stringFromColumnIndex($kolom);
        $lAwal = $L($awal);
        $lAkhirTgl = $n > 0 ? $L($kolomAkhirTanggal) : $lAwal;
        $lTerakhir = $L($terakhir);

        // Lebar kolom
        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(14);
        $sheet->getColumnDimension('C')->setWidth(38);
        for ($c = $awal; $c <= $kolomAkhirTanggal; $c++) {
            $sheet->getColumnDimension($L($c))->setWidth(4.3);
        }
        foreach ([$kolomS, $kolomI, $kolomA] as $c) {
            $sheet->getColumnDimension($L($c))->setWidth(5.5);
        }

        $this->isiKop($sheet, 3, $terakhir, "REKAP ABSENSI MURID");
        $periode = $this->teksTanggal($this->from) . ' s/d ' . $this->teksTanggal($this->to)
            . '   |   Hari efektif: ' . count($this->efektifSet) . ' hari';
        $this->barisTeks($sheet, 9, $terakhir, 'Periode data: ' . $periode, 9, false, true);

        // Pita merah nama kelas
        $sheet->mergeCells('A' . self::BARIS_BAND . ':' . $lTerakhir . self::BARIS_BAND);
        $sheet->setCellValue('A' . self::BARIS_BAND, mb_strtoupper(trim($namaKelas)));
        $band = $sheet->getStyle('A' . self::BARIS_BAND . ':' . $lTerakhir . self::BARIS_BAND);
        $band->getFont()->setBold(true)->setSize(16);
        $band->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $band->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::MERAH_BAND);
        $sheet->getRowDimension(self::BARIS_BAND)->setRowHeight(24);

        // Header: baris 12 = bulan, 13 = hari, 14 = tanggal
        foreach (['A' => 'NO', 'B' => 'IDK', 'C' => 'NAMA'] as $l => $teks) {
            $sheet->mergeCells("{$l}12:{$l}14");
            $sheet->setCellValue("{$l}12", $teks);
        }
        foreach ([$kolomS => 'S', $kolomI => 'I', $kolomA => 'A'] as $c => $teks) {
            $sheet->mergeCells($L($c) . '12:' . $L($c) . '14');
            $sheet->setCellValue($L($c) . '12', $teks);
        }

        $headerAbu = $sheet->getStyle("A12:{$lTerakhir}14");
        $headerAbu->getFont()->setBold(true);
        $headerAbu->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::ABU_HEADER);

        $kelompokBulan = [];   // ['2026-10' => [kolomAwal, kolomAkhir, tglPertama]]

        foreach ($tanggal as $idx => $tgl) {
            $c = $awal + $idx;
            $d = new DateTimeImmutable($tgl);
            $efektif = isset($this->efektifSet[$tgl]);

            $sheet->setCellValue($L($c) . '13', self::HARI_PENDEK[(int) $d->format('w')]);
            $sheet->setCellValue($L($c) . '14', (int) $d->format('j'));

            if (! $efektif) {
                $sheet->getStyle($L($c) . '12:' . $L($c) . '14')
                    ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::KUNING_HEADER);
            }

            $kunci = $d->format('Y-m');
            if (! isset($kelompokBulan[$kunci])) {
                $kelompokBulan[$kunci] = [$c, $c, $d];
            } else {
                $kelompokBulan[$kunci][1] = $c;
            }
        }

        foreach ($kelompokBulan as [$cAwal, $cAkhir, $d]) {
            $lebar = $cAkhir - $cAwal + 1;
            $teks = $lebar >= 5
                ? self::BULAN[(int) $d->format('n')] . ' ' . $d->format('Y')
                : self::BULAN_PENDEK[(int) $d->format('n')];

            if ($cAkhir > $cAwal) {
                $sheet->mergeCells($L($cAwal) . '12:' . $L($cAkhir) . '12');
            }
            $sheet->setCellValue($L($cAwal) . '12', $teks);
        }

        if ($n > 0) {
            $sheet->getStyle($lAwal . '13:' . $lAkhirTgl . '14')->getFont()->setSize(8);
        }

        // Baris siswa
        foreach ($siswa as $i => $s) {
            $r = $h + $i;
            $sheet->setCellValue("A{$r}", (int) $s['no']);
            $sheet->setCellValueExplicit("B{$r}", (string) $s['idk'], DataType::TYPE_STRING);
            $sheet->setCellValue("C{$r}", mb_strtoupper((string) $s['nama']));

            foreach ($tanggal as $idx => $tgl) {
                $kode = $s['harian'][$tgl] ?? '';

                if ($kode === 'S' || $kode === 'I' || $kode === 'A') {
                    $sheet->setCellValue($L($awal + $idx) . $r, $kode);
                }
            }

            foreach ([$kolomS => 'S', $kolomI => 'I', $kolomA => 'A'] as $c => $kode) {
                $sheet->setCellValue(
                    $L($c) . $r,
                    $n > 0 ? "=COUNTIF({$lAwal}{$r}:{$lAkhirTgl}{$r},\"{$kode}\")" : 0
                );
            }
        }

        // Baris jumlah per tanggal
        $footer = [
            $rS => ['JUMLAH SAKIT (S)', 'S', $kolomS],
            $rI => ['JUMLAH IZIN (I)', 'I', $kolomI],
            $rA => ['JUMLAH ALPHA (A)', 'A', $kolomA],
            $rH => ['HADIR', null, null],
        ];

        foreach ($footer as $r => [$label, $kode, $kolomTotal]) {
            $sheet->mergeCells("A{$r}:C{$r}");
            $sheet->setCellValue("A{$r}", $label);

            foreach ($tanggal as $idx => $tgl) {
                $l = $L($awal + $idx);

                if ($kode !== null) {
                    $sheet->setCellValue($l . $r, "=COUNTIF({$l}{$h}:{$l}{$barisAkhirSiswa},\"{$kode}\")");
                } elseif (isset($this->efektifSet[$tgl])) {
                    $sheet->setCellValue($l . $r, "=COUNTA(\$C\${$h}:\$C\${$barisAkhirSiswa})-{$l}{$rS}-{$l}{$rI}-{$l}{$rA}");
                } else {
                    $sheet->setCellValue($l . $r, '-');
                }
            }

            if ($kolomTotal !== null) {
                $lt = $L($kolomTotal);
                $sheet->setCellValue($lt . $r, "=SUM({$lt}{$h}:{$lt}{$barisAkhirSiswa})");
            }
        }

        $footerStyle = $sheet->getStyle("A{$rS}:{$lTerakhir}{$rH}");
        $footerStyle->getFont()->setBold(true);
        $footerStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::ABU_FOOTER);

        // Gaya tabel: garis, rata tengah, nama rata kiri
        $tabel = $sheet->getStyle("A12:{$lTerakhir}{$rH}");
        $tabel->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $tabel->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("C{$h}:C{$barisAkhirSiswa}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("A{$rS}:C{$rH}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("{$L($kolomS)}{$h}:{$lTerakhir}{$barisAkhirSiswa}")->getFont()->setBold(true);

        // Kolom bukan hari efektif: latar kuning muda
        foreach ($tanggal as $idx => $tgl) {
            if (! isset($this->efektifSet[$tgl])) {
                $l = $L($awal + $idx);
                $sheet->getStyle("{$l}{$h}:{$l}{$barisAkhirSiswa}")
                    ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::KUNING_BODY);
            }
        }

        // Warna huruf S / I / A (otomatis, ikut berubah kalau sel diedit)
        if ($n > 0 && $jumlahSiswa > 0) {
            $sheet->getStyle("{$lAwal}{$h}:{$lAkhirTgl}{$barisAkhirSiswa}")->setConditionalStyles([
                $this->aturanWarna('S', 'FFF8CBAD', 'FF9C3A00'),
                $this->aturanWarna('I', 'FFBDD7EE', 'FF1F4E79'),
                $this->aturanWarna('A', 'FFFF9999', 'FF9C0006'),
            ]);
        }

        // Keterangan di bawah tabel
        $rKet = $rH + 2;
        $ket = [
            'Keterangan: S = Sakit, I = Izin, A = Alpha (tidak hadir tanpa keterangan). Sel kosong = hadir.',
            'Kolom kuning = bukan hari efektif (tidak ada siswa yang scan masuk); hanya memuat catatan sakit/izin.',
        ];
        foreach ($ket as $j => $teks) {
            $r = $rKet + $j;
            $sheet->mergeCells("A{$r}:{$lTerakhir}{$r}");
            $sheet->setCellValue("A{$r}", $teks);
            $sheet->getStyle("A{$r}")->getFont()->setSize(8)->setItalic(true);
        }

        // Tampilan & cetak
        $sheet->freezePane('D1');
        $sheet->getSheetView()->setZoomScale(90);
        $this->aturCetak($sheet, "11:14");

        return [
            'jumlahSiswa' => $jumlahSiswa,
            'barisS' => $rS,
            'barisI' => $rI,
            'barisA' => $rA,
            'barisH' => $rH,
            'kolomS' => $L($kolomS),
            'kolomI' => $L($kolomI),
            'kolomA' => $L($kolomA),
        ];
    }

    // ═════════════════════════════════════════════════════════════════
    // Sheet Ringkasan
    // ═════════════════════════════════════════════════════════════════

    private function isiRingkasan(Worksheet $sheet, array $tanggal, array $info): void
    {
        foreach (['A' => 30, 'B' => 16, 'C' => 14, 'D' => 12, 'E' => 12, 'F' => 12] as $l => $w) {
            $sheet->getColumnDimension($l)->setWidth($w);
        }

        $this->isiKop($sheet, 2, 6, 'RINGKASAN S / I / A PER TANGGAL');
        $this->barisTeks(
            $sheet,
            9,
            6,
            'Periode data: ' . $this->teksTanggal($this->from) . ' s/d ' . $this->teksTanggal($this->to)
            . '   |   Semester ' . $this->semester . ' Tapel ' . $this->tapel,
            9,
            false,
            true
        );

        if (count($info) === 0) {
            $this->barisTeks($sheet, 11, 6, 'Tidak ada data siswa untuk filter ini.', 11, false, false);

            return;
        }

        $q = fn (string $nama): string => "'" . str_replace("'", "''", $nama) . "'";

        // Tabel 1: per tanggal
        $r0 = 11;
        foreach (['Tanggal', 'Hari Efektif', 'Hadir', 'S', 'I', 'A'] as $i => $teks) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1) . $r0, $teks);
        }

        $r = $r0 + 1;
        $awalData = $r;

        foreach ($tanggal as $idx => $tgl) {
            $d = new DateTimeImmutable($tgl);
            $efektif = isset($this->efektifSet[$tgl]);
            $l = Coordinate::stringFromColumnIndex(self::KOLOM_TANGGAL_AWAL + $idx); // kolom tanggal ini di sheet kelas

            $sheet->setCellValue("A{$r}", self::HARI_PANJANG[(int) $d->format('w')] . ', ' . $this->teksTanggal($d));
            $sheet->setCellValue("B{$r}", $efektif ? 'Ya' : 'Tidak');

            $jumlah = function (string $kunciBaris) use ($info, $q, $l): string {
                return '=' . implode('+', array_map(
                    fn ($i) => $q($i['sheet']) . '!' . $l . $i[$kunciBaris],
                    $info
                ));
            };

            if ($efektif) {
                $sheet->setCellValue("C{$r}", $jumlah('barisH'));
            } else {
                $sheet->setCellValue("C{$r}", '-');
            }
            $sheet->setCellValue("D{$r}", $jumlah('barisS'));
            $sheet->setCellValue("E{$r}", $jumlah('barisI'));
            $sheet->setCellValue("F{$r}", $jumlah('barisA'));

            if (! $efektif) {
                $sheet->getStyle("A{$r}:F{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::KUNING_BODY);
            }

            $r++;
        }

        $akhirData = $r - 1;
        $sheet->setCellValue("A{$r}", 'TOTAL');
        if ($akhirData >= $awalData) {
            $sheet->setCellValue("B{$r}", "=COUNTIF(B{$awalData}:B{$akhirData},\"Ya\")");
            $sheet->setCellValue("C{$r}", "=SUM(C{$awalData}:C{$akhirData})");
            foreach (['D', 'E', 'F'] as $l) {
                $sheet->setCellValue("{$l}{$r}", "=SUM({$l}{$awalData}:{$l}{$akhirData})");
            }
        }
        $this->gayaTabelRingkasan($sheet, $r0, $r);

        // Tabel 2: per kelas
        $r2 = $r + 3;
        $sheet->mergeCells("A" . ($r2 - 1) . ":F" . ($r2 - 1));
        $sheet->setCellValue('A' . ($r2 - 1), 'REKAP PER KELAS (SELURUH PERIODE)');
        $sheet->getStyle('A' . ($r2 - 1))->getFont()->setBold(true)->setSize(11);

        foreach (['Kelas', 'Jumlah Siswa', 'Hari Efektif', 'S', 'I', 'A'] as $i => $teks) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1) . $r2, $teks);
        }

        $r = $r2 + 1;
        $awal2 = $r;
        foreach ($info as $i) {
            $sheet->setCellValue("A{$r}", mb_strtoupper($i['nama']));
            $sheet->setCellValue("B{$r}", $i['jumlahSiswa']);
            $sheet->setCellValue("C{$r}", count($this->efektifSet));
            $sheet->setCellValue("D{$r}", '=' . $q($i['sheet']) . '!' . $i['kolomS'] . $i['barisS']);
            $sheet->setCellValue("E{$r}", '=' . $q($i['sheet']) . '!' . $i['kolomI'] . $i['barisI']);
            $sheet->setCellValue("F{$r}", '=' . $q($i['sheet']) . '!' . $i['kolomA'] . $i['barisA']);
            $r++;
        }

        $akhir2 = $r - 1;
        $sheet->setCellValue("A{$r}", 'TOTAL');
        $sheet->setCellValue("B{$r}", "=SUM(B{$awal2}:B{$akhir2})");
        foreach (['D', 'E', 'F'] as $l) {
            $sheet->setCellValue("{$l}{$r}", "=SUM({$l}{$awal2}:{$l}{$akhir2})");
        }
        $this->gayaTabelRingkasan($sheet, $r2, $r);

        // Keterangan
        $rKet = $r + 2;
        $sheet->mergeCells("A{$rKet}:F{$rKet}");
        $sheet->setCellValue("A{$rKet}", 'Baris kuning = bukan hari efektif (tidak ada siswa yang scan masuk); hanya memuat catatan sakit/izin.');
        $sheet->getStyle("A{$rKet}")->getFont()->setSize(8)->setItalic(true);

        $sheet->freezePane('A12');
        $this->aturCetak($sheet, '11:11', false);
    }

    private function gayaTabelRingkasan(Worksheet $sheet, int $barisHeader, int $barisTotal): void
    {
        $semua = $sheet->getStyle("A{$barisHeader}:F{$barisTotal}");
        $semua->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $semua->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("A" . ($barisHeader + 1) . ":A{$barisTotal}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        $header = $sheet->getStyle("A{$barisHeader}:F{$barisHeader}");
        $header->getFont()->setBold(true);
        $header->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::ABU_HEADER);

        $total = $sheet->getStyle("A{$barisTotal}:F{$barisTotal}");
        $total->getFont()->setBold(true);
        $total->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::ABU_FOOTER);
    }

    // ═════════════════════════════════════════════════════════════════
    // Bagian bersama
    // ═════════════════════════════════════════════════════════════════

    /** Kop surat (logo + 4 baris), garis, dan judul 3 baris di baris 6-8. */
    private function isiKop(Worksheet $sheet, int $kolomTeksAwal, int $kolomAkhir, string $judul): void
    {
        $akhir = Coordinate::stringFromColumnIndex($kolomAkhir);
        $awalTeks = Coordinate::stringFromColumnIndex($kolomTeksAwal);

        $baris = [
            1 => ['YAYASAN PENDIDIKAN ISLAM SUMBER BUNGUR', 11, 17],
            2 => ['MADRASAH ALIYAH SUMBER BUNGUR PAKONG PAMEKASAN', 12, 18],
            3 => ['Jl. Pontren Sumber Bungur Pakong Pamekasan Madura', 10, 15],
            4 => ['e-mail: info@masumberbungur.sch.id    Website: masumberbungur.sch.id', 8, 13],
        ];

        foreach ($baris as $r => [$teks, $ukuran, $tinggi]) {
            $sheet->mergeCells("{$awalTeks}{$r}:{$akhir}{$r}");
            $sheet->setCellValue("{$awalTeks}{$r}", $teks);

            $st = $sheet->getStyle("{$awalTeks}{$r}");
            $st->getFont()->setBold(true)->setSize($ukuran);
            $st->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setShrinkToFit(true);
            $sheet->getRowDimension($r)->setRowHeight($tinggi);
        }

        // Garis di bawah kop
        $sheet->getStyle("A4:{$akhir}4")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM);
        $sheet->getRowDimension(5)->setRowHeight(6);

        // Logo
        if ($this->logoPath !== null && is_file($this->logoPath)) {
            $logo = new Drawing();
            $logo->setName('Logo');
            $logo->setPath($this->logoPath);
            $logo->setCoordinates('A1');
            $logo->setHeight(62);
            $logo->setOffsetX(3);
            $logo->setOffsetY(2);
            $logo->setWorksheet($sheet);
        }

        // Judul
        $this->barisTeks($sheet, 6, $kolomAkhir, $judul, 14, true, false);
        $this->barisTeks($sheet, 7, $kolomAkhir, 'AKHIR SEMESTER ' . $this->semester, 12, true, false);
        $this->barisTeks($sheet, 8, $kolomAkhir, 'TAPEL ' . $this->tapel, 12, true, false);
    }

    private function barisTeks(Worksheet $sheet, int $baris, int $kolomAkhir, string $teks, int $ukuran, bool $tebal, bool $miring): void
    {
        $akhir = Coordinate::stringFromColumnIndex($kolomAkhir);
        $sheet->mergeCells("A{$baris}:{$akhir}{$baris}");
        $sheet->setCellValue("A{$baris}", $teks);

        $st = $sheet->getStyle("A{$baris}");
        $st->getFont()->setBold($tebal)->setItalic($miring)->setSize($ukuran);
        $st->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
    }

    private function aturCetak(Worksheet $sheet, string $barisUlang, bool $landscape = true): void
    {
        $ps = $sheet->getPageSetup();
        $ps->setPaperSize(PageSetup::PAPERSIZE_A4);
        $ps->setOrientation($landscape ? PageSetup::ORIENTATION_LANDSCAPE : PageSetup::ORIENTATION_PORTRAIT);
        $ps->setFitToPage(true);
        $ps->setFitToWidth(1);
        $ps->setFitToHeight(0);
        $ps->setHorizontalCentered(true);
        [$a, $b] = array_map('intval', explode(':', $barisUlang));
        $ps->setRowsToRepeatAtTopByStartAndEnd($a, $b);

        $m = $sheet->getPageMargins();
        $m->setTop(0.5)->setBottom(0.5)->setLeft(0.4)->setRight(0.4);
    }

    private function aturanWarna(string $huruf, string $latar, string $teks): Conditional
    {
        $c = new Conditional();
        $c->setConditionType(Conditional::CONDITION_CELLIS);
        $c->setOperatorType(Conditional::OPERATOR_EQUAL);
        $c->addCondition('"' . $huruf . '"');
        $c->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($latar);
        $c->getStyle()->getFill()->getEndColor()->setARGB($latar);
        $c->getStyle()->getFont()->setBold(true)->getColor()->setARGB($teks);

        return $c;
    }

    private function teksTanggal(DateTimeInterface $d): string
    {
        return $d->format('d') . ' ' . self::BULAN[(int) $d->format('n')] . ' ' . $d->format('Y');
    }

    /** Nama sheet Excel: maksimal 31 karakter, tanpa \ / ? * [ ] :, dan tidak boleh kembar. */
    private function namaSheetUnik(string $nama, array &$dipakai): string
    {
        $bersih = trim(preg_replace('/[\\\\\/\?\*\[\]:]/', '', $nama));
        $bersih = $bersih === '' ? 'Kelas' : mb_substr($bersih, 0, 31);

        $kandidat = $bersih;
        $no = 2;
        while (isset($dipakai[mb_strtolower($kandidat)])) {
            $akhiran = ' (' . $no++ . ')';
            $kandidat = mb_substr($bersih, 0, 31 - mb_strlen($akhiran)) . $akhiran;
        }

        $dipakai[mb_strtolower($kandidat)] = true;

        return $kandidat;
    }
}