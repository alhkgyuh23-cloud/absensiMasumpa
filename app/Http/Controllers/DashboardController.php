<?php

namespace App\Http\Controllers;

use App\Models\AbsenceNote;
use App\Models\AttendanceLog;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(AttendanceService $attendanceService): View
    {
        $ringkasan = $this->ringkasanHariIni($attendanceService);

        return view('admin.dashboard', [
            'totalHadirHariIni' => $ringkasan['totalHadirHariIni'],
            'totalSiswa' => $ringkasan['totalSiswa'],
            'classes' => $ringkasan['classes'],
            'kehadiranPerKelas' => $ringkasan['kehadiranPerKelas'],
            'siswaPerKelas' => $ringkasan['siswaPerKelas'],
        ]);
    }

    public function data(AttendanceService $attendanceService): JsonResponse
    {
        $ringkasan = $this->ringkasanHariIni($attendanceService);

        $classes = $ringkasan['classes']->map(function ($class) use ($ringkasan) {
            $rows = $ringkasan['kehadiranPerKelas']->get($class->id, collect());

            return [
                'id' => $class->id,
                'hadir' => $rows->filter(fn ($r) => $r->masuk)->count(),
                'total' => $ringkasan['siswaPerKelas']->get($class->id, 0),
                'rows' => $rows->map(fn ($row) => [
                    'tanggal' => $row->tanggal->format('d M Y'),
                    'nama' => $row->nama,
                    'masuk' => $row->masuk?->format('H:i'),
                    'pulang' => $row->pulang?->format('H:i'),
                    'status' => $row->status,
                    'belum_pulang' => $row->belum_pulang,
                    'jenis' => $row->jenis,
                ])->values(),
            ];
        })->values();

        return response()->json([
            'totalHadirHariIni' => $ringkasan['totalHadirHariIni'],
            'totalSiswa' => $ringkasan['totalSiswa'],
            'classes' => $classes,
        ]);
    }

    private function ringkasanHariIni(AttendanceService $attendanceService): array
    {
        $totalHadirHariIni = AttendanceLog::whereDate('scanned_at', today())
            ->where('type', 'masuk')
            ->count();

        $totalSiswa = Student::where('is_active', true)->count();

        // Semua scan hari ini, lengkap dengan nama dan kelas siswa.
        // ->toBase() penting: tanpa ini, hasilnya tetap dianggap Eloquent Collection
        // walau isinya stdClass, dan groupBy()/map()/merge() di bawah akan error
        // mencari method getKey() yang cuma ada di model Eloquent.
        $logs = AttendanceLog::query()
            ->whereDate('attendance_logs.scanned_at', today())
            ->join('students', 'students.id', '=', 'attendance_logs.student_id')
            ->orderBy('students.name')
            ->orderBy('attendance_logs.scanned_at')
            ->get([
                'attendance_logs.student_id',
                'attendance_logs.scanned_at',
                'attendance_logs.type',
                'attendance_logs.status',
                'students.name as student_name',
                'students.class_id',
            ])
            ->toBase();

        // Batas pulang hari ini sudah lewat atau belum
        $lewatBatasPulang = now()->gt($attendanceService->batasPulang(now()));

        // Satu baris per siswa: jam masuk dan jam pulang disatukan
        $kehadiran = $logs->groupBy('student_id')->map(function ($items) use ($lewatBatasPulang) {
            $masuk = $items->firstWhere('type', 'masuk');
            $pulang = $items->firstWhere('type', 'pulang');
            $pertama = $items->first();

            return (object) [
                'class_id' => $pertama->class_id,
                'tanggal' => $pertama->scanned_at,
                'nama' => $pertama->student_name,
                'masuk' => $masuk?->scanned_at,
                'pulang' => $pulang?->scanned_at,
                'status' => $masuk?->status,
                'belum_pulang' => $masuk && ! $pulang && $lewatBatasPulang,
                'jenis' => null,
            ];
        });

        // Siswa yang sakit/izin hari ini TAPI tidak scan sama sekali -> tetap perlu muncul,
        // bukan dianggap "tidak ada data".
        $sudahAdaDiKehadiran = $kehadiran->keys(); // student_id yang sudah punya baris dari scan

        $catatanHariIni = AbsenceNote::with('student')
            ->whereDate('tanggal_mulai', '<=', today())
            ->whereDate('tanggal_selesai', '>=', today())
            ->get()
            ->toBase()
            ->whereNotIn('student_id', $sudahAdaDiKehadiran);

        $tambahanSakitIzin = $catatanHariIni->map(function ($note) {
            return (object) [
                'class_id' => $note->student->class_id,
                'tanggal' => today(),
                'nama' => $note->student->name,
                'masuk' => null,
                'pulang' => null,
                'status' => null,
                'belum_pulang' => false,
                'jenis' => $note->jenis,
            ];
        })->keyBy(fn ($row, $i) => 'sakit_izin_' . $i);

        $kehadiran = $kehadiran->merge($tambahanSakitIzin)->values();

        $kehadiranPerKelas = $kehadiran->groupBy('class_id');

        $classes = SchoolClass::orderBy('tingkat')->orderBy('rombel')->get();

        $siswaPerKelas = Student::where('is_active', true)
            ->selectRaw('class_id, count(*) as total')
            ->groupBy('class_id')
            ->pluck('total', 'class_id');

        return [
            'totalHadirHariIni' => $totalHadirHariIni,
            'totalSiswa' => $totalSiswa,
            'classes' => $classes,
            'kehadiranPerKelas' => $kehadiranPerKelas,
            'siswaPerKelas' => $siswaPerKelas,
        ];
    }
}