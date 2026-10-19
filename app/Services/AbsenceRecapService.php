<?php

namespace App\Services;

use App\Models\AbsenceNote;
use App\Models\AttendanceLog;
use App\Models\Student;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Str;

class AbsenceRecapService
{
    /**
     * Urutan siswa di dalam satu kelas:
     *   'nama' = A-Z berdasarkan nama (tanda baca diabaikan)
     *   'idk'  = nomor IDK (kolom nis) dari kecil ke besar
     */
    private const URUTKAN = 'nama';

    /**
     * Hitung S (sakit), I (izin), A (alpha) per siswa untuk periode tertentu.
     *
     * Aturan:
     * - Hari efektif  = tanggal Senin-Sabtu yang ada minimal satu siswa scan masuk.
     * - Hadir         = siswa scan masuk pada hari itu (scan selalu menang atas catatan sakit/izin).
     * - S / I         = hari Senin-Sabtu dalam periode yang tercakup catatan Sakit / Izin siswa,
     *                   dan siswa tidak scan masuk. TIDAK bergantung pada ada-tidaknya scan siswa lain.
     *                   Hari Minggu tidak dihitung.
     * - A (alpha)     = hari efektif tanpa scan masuk siswa dan tanpa catatan sakit/izin.
     * - Jika ada catatan sakit dan izin yang tumpang tindih di hari yang sama, sakit didahulukan.
     *
     * Selain total S/I/A, tiap siswa membawa 'harian' = [tanggal => 'H'|'S'|'I'|'A'],
     * dan hasil akhir membawa 'tanggal' (semua tanggal yang punya kolom di Excel) serta
     * 'hariEfektifList'. Total S/I/A dihitung DARI 'harian', jadi PDF dan Excel selalu sama.
     *
     * @return array{kelas: \Illuminate\Support\Collection, hariEfektif: int, hariEfektifList: array, tanggal: array}
     */
    public function build(Carbon $from, Carbon $to, $tingkat = null, $classId = null): array
    {
        $start = $from->copy()->startOfDay();
        $end = $to->copy()->endOfDay();

        // Tanggal scan masuk, satu baris per siswa per tanggal
        $masuk = AttendanceLog::query()
            ->where('type', 'masuk')
            ->whereBetween('scanned_at', [$start, $end])
            ->selectRaw('student_id, DATE(scanned_at) as tanggal')
            ->distinct()
            ->get();

        $hariEfektif = $masuk->pluck('tanggal')
            ->unique()
            ->filter(fn ($tgl) => Carbon::parse($tgl)->dayOfWeek !== Carbon::SUNDAY)
            ->sort()
            ->values()
            ->all();

        // [student_id => ['2026-07-14' => 0, ...]] supaya pengecekan hadir cepat
        $hadirPerSiswa = $masuk->groupBy('student_id')
            ->map(fn ($rows) => array_flip($rows->pluck('tanggal')->all()));

        $catatan = AbsenceNote::query()
            ->whereDate('tanggal_mulai', '<=', $to->toDateString())
            ->whereDate('tanggal_selesai', '>=', $from->toDateString())
            ->get()
            ->groupBy('student_id');

        $students = Student::query()
            ->join('classes', 'classes.id', '=', 'students.class_id')
            ->where('students.is_active', true)
            ->when($tingkat, fn ($q) => $q->where('classes.tingkat', $tingkat))
            ->when($classId, fn ($q) => $q->where('students.class_id', $classId))
            ->orderBy('classes.tingkat')
            ->orderBy('classes.rombel')
            ->get([
                'students.id',
                'students.name',
                'students.nis',
                'students.class_id',
                'classes.name as class_name',
            ]);

        $semuaTanggal = array_flip($hariEfektif); // kumpulan tanggal untuk kolom Excel

        $kelas = $students->groupBy('class_id')->map(function ($siswaKelas) use ($hariEfektif, $hadirPerSiswa, $catatan, $from, $to, &$semuaTanggal) {
            $urut = self::URUTKAN === 'idk'
                ? $siswaKelas->sortBy(fn ($s) => ($s->nis === null || $s->nis === '') ? PHP_INT_MAX : (int) $s->nis)
                : $siswaKelas->sortBy(fn ($s) => $this->kunciNama($s->name));

            $baris = $urut->values()->map(function ($s, $i) use ($hariEfektif, $hadirPerSiswa, $catatan, $from, $to, &$semuaTanggal) {
                $hadir = $hadirPerSiswa->get($s->id, []);
                $hariCatatan = $this->hariCatatan($catatan->get($s->id, collect()), $from, $to);

                $harian = self::statusHarian($hariEfektif, $hadir, $hariCatatan);

                foreach ($harian as $tgl => $kode) {
                    $semuaTanggal[$tgl] = true;
                }

                $hitung = array_count_values($harian);

                return [
                    'no' => $i + 1,
                    'idk' => $s->nis ?? '',
                    'nama' => trim($s->name),
                    's' => $hitung['S'] ?? 0,
                    'i' => $hitung['I'] ?? 0,
                    'a' => $hitung['A'] ?? 0,
                    'harian' => $harian,
                ];
            });

            return [
                'nama' => trim($siswaKelas->first()->class_name),
                'siswa' => $baris,
            ];
        });

        $tanggal = array_keys($semuaTanggal);
        sort($tanggal);

        return [
            'kelas' => $kelas->values(),
            'hariEfektif' => count($hariEfektif),
            'hariEfektifList' => $hariEfektif,
            'tanggal' => $tanggal,
        ];
    }

    /**
     * Status seorang siswa per tanggal: 'H' hadir, 'S' sakit, 'I' izin, 'A' alpha.
     *
     * - Hari efektif: scan masuk = H; kalau tidak scan, ada catatan = S/I; kalau tidak ada = A.
     * - Hari catatan di luar hari efektif (Senin-Sabtu tanpa scan siapa pun): S/I saja, tidak ada A.
     *
     * @param  array  $hariEfektif   daftar tanggal 'Y-m-d'
     * @param  array  $hadir         [tanggal => apa saja] tanggal scan masuk siswa
     * @param  array  $hariCatatan   [tanggal => 'sakit'|'izin']
     * @return array<string, string> [tanggal => 'H'|'S'|'I'|'A'] urut tanggal
     */
    public static function statusHarian(array $hariEfektif, $hadir, array $hariCatatan): array
    {
        $hasil = [];

        foreach ($hariEfektif as $tgl) {
            if (isset($hadir[$tgl])) {
                $hasil[$tgl] = 'H';
            } elseif (isset($hariCatatan[$tgl])) {
                $hasil[$tgl] = $hariCatatan[$tgl] === 'sakit' ? 'S' : 'I';
            } else {
                $hasil[$tgl] = 'A';
            }
        }

        foreach ($hariCatatan as $tgl => $jenis) {
            if (isset($hasil[$tgl]) || isset($hadir[$tgl])) {
                continue; // sudah tercakup di hari efektif, atau siswa scan masuk = hadir
            }

            $hasil[$tgl] = $jenis === 'sakit' ? 'S' : 'I';
        }

        ksort($hasil);

        return $hasil;
    }

    /**
     * Pecah catatan sakit/izin seorang siswa menjadi [tanggal => jenis],
     * hanya hari Senin-Sabtu yang berada di dalam periode.
     */
    private function hariCatatan($notes, Carbon $from, Carbon $to): array
    {
        $awal = $from->copy()->startOfDay();
        $akhir = $to->copy()->startOfDay();
        $hasil = [];

        foreach ($notes as $n) {
            $mulai = $n->tanggal_mulai->copy()->startOfDay();
            $selesai = $n->tanggal_selesai->copy()->startOfDay();

            if ($mulai->lt($awal)) {
                $mulai = $awal->copy();
            }
            if ($selesai->gt($akhir)) {
                $selesai = $akhir->copy();
            }
            if ($mulai->gt($selesai)) {
                continue;
            }

            foreach (CarbonPeriod::create($mulai, $selesai) as $hari) {
                if ($hari->dayOfWeek === Carbon::SUNDAY) {
                    continue;
                }

                $tgl = $hari->toDateString();

                if (($hasil[$tgl] ?? null) === 'sakit') {
                    continue; // sakit didahulukan
                }

                $hasil[$tgl] = $n->jenis;
            }
        }

        return $hasil;
    }

    /** Kunci urut nama: huruf besar, tanda baca dibuang (A'YUNIL jadi AYUNIL), spasi dirapikan. */
    private function kunciNama(string $nama): string
    {
        $bersih = preg_replace('/[^\p{L}\p{N} ]+/u', '', $nama);
        $bersih = preg_replace('/\s+/', ' ', $bersih);

        return Str::upper(trim($bersih));
    }
}