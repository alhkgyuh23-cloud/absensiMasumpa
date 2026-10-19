<?php

namespace App\Http\Controllers;

use App\Models\AbsenceNote;
use App\Models\AttendanceLog;
use App\Exports\RekapAbsensiExport;
use App\Models\SchoolClass;
use App\Services\AbsenceRecapService;
use App\Services\AttendanceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request, AttendanceService $attendanceService): View
    {
        $from = $request->date('from') ?? today()->startOfMonth();
        $to = $request->date('to') ?? today();

        $barisScan = $this->ambilBarisScan($request, $from, $to, $attendanceService);
        $barisSakitIzin = $this->ambilBarisSakitIzin($request, $from, $to, $barisScan);

        $gabungan = $barisScan->concat($barisSakitIzin)->sort(function ($a, $b) {
            $cmp = $b->tanggal->timestamp <=> $a->tanggal->timestamp; // tanggal terbaru dulu
            if ($cmp !== 0) return $cmp;

            $cmp = strcmp($a->kelas, $b->kelas);
            if ($cmp !== 0) return $cmp;

            return strcmp($a->nama, $b->nama);
        })->values();

        $rekap = $this->paginateManual($gabungan, $request);

        $classes = SchoolClass::orderBy('tingkat')->orderBy('rombel')->get();

        return view('admin.reports.index', [
            'rekap' => $rekap,
            'classes' => $classes,
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
        ]);
    }

    /**
     * Cetak PDF rekap S / I / A per kelas (satu kelas satu halaman),
     * memakai filter yang sama dengan halaman Rekap.
     */
    public function pdf(Request $request, AbsenceRecapService $recap)
    {
        set_time_limit(120);

        $from = $request->date('from') ?? today()->startOfMonth();
        $to = $request->date('to') ?? today();

        $hasil = $recap->build($from, $to, $request->input('tingkat'), $request->input('class_id'));

        [$semester, $tapel] = $this->semesterDanTapel($from);

        // Logo ditanam langsung (base64) karena dompdf bermasalah dengan path yang berspasi di Windows
        $logoPath = public_path('images/logo-ma.jpg');
        $logo = is_file($logoPath)
            ? 'data:image/jpeg;base64,' . base64_encode(file_get_contents($logoPath))
            : null;

        $pdf = Pdf::loadView('admin.reports.pdf', [
            'kelas' => $hasil['kelas'],
            'hariEfektif' => $hasil['hariEfektif'],
            'semester' => $semester,
            'tapel' => $tapel,
            'logo' => $logo,
            'from' => $from,
            'to' => $to,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('rekap-absensi-' . $from->format('Ymd') . '-' . $to->format('Ymd') . '.pdf');
    }

    /**
     * Unduh rekap S / I / A dalam bentuk Excel: satu sheet per kelas dengan kolom per tanggal,
     * ditambah sheet Ringkasan (jumlah S / I / A / Hadir per tanggal). Filter sama dengan PDF.
     */
    public function excel(Request $request, AbsenceRecapService $recap)
    {
        set_time_limit(180);
        ini_set('memory_limit', '512M');

        $from = $request->date('from') ?? today()->startOfMonth();
        $to = $request->date('to') ?? today();

        $hasil = $recap->build($from, $to, $request->input('tingkat'), $request->input('class_id'));

        [$semester, $tapel] = $this->semesterDanTapel($from);

        $export = new RekapAbsensiExport($hasil, $from, $to, $semester, $tapel, public_path('images/logo-ma.jpg'));

        $namaFile = 'rekap-absensi-' . $from->format('Ymd') . '-' . $to->format('Ymd') . '.xlsx';

        return response()->streamDownload(
            fn () => $export->simpan('php://output'),
            $namaFile,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }

    /** Juli-Desember = Ganjil (tapel dimulai tahun itu), Januari-Juni = Genap (tapel dimulai tahun lalu). */
    private function semesterDanTapel(Carbon $from): array
    {
        $ganjil = $from->month >= 7;
        $tahunAwal = $ganjil ? $from->year : $from->year - 1;

        return [$ganjil ? 'GANJIL' : 'GENAP', $tahunAwal . ' - ' . ($tahunAwal + 1)];
    }

    /**
     * Ambil semua baris hasil scan (masuk/pulang digabung per siswa per tanggal) dalam rentang filter.
     * Tidak di-paginate di sini, karena nanti digabung dulu dengan baris sakit/izin.
     */
    private function ambilBarisScan(Request $request, Carbon $from, Carbon $to, AttendanceService $attendanceService)
    {
        $query = AttendanceLog::query()
            ->join('students', 'students.id', '=', 'attendance_logs.student_id')
            ->join('classes', 'classes.id', '=', 'students.class_id')
            ->whereBetween('attendance_logs.scanned_at', [
                $from->copy()->startOfDay(),
                $to->copy()->endOfDay(),
            ])
            ->selectRaw("
                attendance_logs.student_id,
                DATE(attendance_logs.scanned_at) as tanggal,
                students.name as student_name,
                classes.name as class_name,
                MIN(CASE WHEN attendance_logs.type = 'masuk' THEN attendance_logs.scanned_at END) as masuk_at,
                MIN(CASE WHEN attendance_logs.type = 'pulang' THEN attendance_logs.scanned_at END) as pulang_at,
                MAX(CASE WHEN attendance_logs.type = 'masuk' THEN attendance_logs.status END) as status
            ")
            ->groupBy(
                'attendance_logs.student_id',
                DB::raw('DATE(attendance_logs.scanned_at)'),
                'students.name',
                'classes.name'
            );

        if ($request->filled('tingkat')) {
            $query->where('classes.tingkat', $request->tingkat);
        }

        if ($request->filled('class_id')) {
            $query->where('students.class_id', $request->class_id);
        }

        return $query->get()->map(function ($row) use ($attendanceService) {
            $tanggal = Carbon::parse($row->tanggal);
            $masuk = $row->masuk_at ? Carbon::parse($row->masuk_at) : null;
            $pulang = $row->pulang_at ? Carbon::parse($row->pulang_at) : null;

            return (object) [
                'student_id' => $row->student_id,
                'tanggal' => $tanggal,
                'nama' => $row->student_name,
                'kelas' => $row->class_name,
                'masuk' => $masuk,
                'pulang' => $pulang,
                'status' => $row->status,
                'belum_pulang' => $masuk && ! $pulang
                    && now()->gt($attendanceService->batasPulang($tanggal)),
                'jenis' => null,
            ];
        });
    }

    /**
     * Pecah tiap catatan sakit/izin jadi satu baris per hari dalam rentang filter
     * (hari Minggu dilewati, sama seperti rekap PDF), lalu buang hari yang siswa itu
     * sudah ada baris scan-nya (scan lebih diutamakan).
     */
    private function ambilBarisSakitIzin(Request $request, Carbon $from, Carbon $to, $barisScan)
    {
        $sudahAdaScan = $barisScan
            ->map(fn ($row) => $row->student_id . '|' . $row->tanggal->toDateString())
            ->flip();

        $query = AbsenceNote::with('student.schoolClass')
            ->whereDate('tanggal_mulai', '<=', $to)
            ->whereDate('tanggal_selesai', '>=', $from);

        if ($request->filled('tingkat')) {
            $query->whereHas('student.schoolClass', fn ($q) => $q->where('tingkat', $request->tingkat));
        }

        if ($request->filled('class_id')) {
            $query->whereHas('student', fn ($q) => $q->where('class_id', $request->class_id));
        }

        $hasil = collect();

        foreach ($query->get() as $note) {
            $mulai = $note->tanggal_mulai->greaterThan($from) ? $note->tanggal_mulai : $from->copy();
            $selesai = $note->tanggal_selesai->lessThan($to) ? $note->tanggal_selesai : $to->copy();

            if ($mulai->greaterThan($selesai)) {
                continue;
            }

            foreach (CarbonPeriod::create($mulai, $selesai) as $tanggal) {
                if ($tanggal->isSunday()) {
                    continue; // Minggu libur, tidak ada absensi
                }

                $kunci = $note->student_id . '|' . $tanggal->toDateString();

                if ($sudahAdaScan->has($kunci)) {
                    continue; // siswa ini sudah scan hari itu, tidak perlu baris sakit/izin
                }

                $hasil->push((object) [
                    'student_id' => $note->student_id,
                    'tanggal' => Carbon::parse($tanggal),
                    'nama' => $note->student->name,
                    'kelas' => $note->student->schoolClass->name,
                    'masuk' => null,
                    'pulang' => null,
                    'status' => null,
                    'belum_pulang' => false,
                    'jenis' => $note->jenis,
                ]);
            }
        }

        return $hasil;
    }

    private function paginateManual($gabungan, Request $request, int $perPage = 30): LengthAwarePaginator
    {
        $page = Paginator::resolveCurrentPage() ?: 1;

        $potongan = $gabungan->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator($potongan, $gabungan->count(), $perPage, $page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);
    }
}