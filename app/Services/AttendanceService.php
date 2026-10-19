<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Facades\Date;

class AttendanceService
{
    public function record(Student $student, string $source = 'scan', ?int $recordedBy = null): array
    {
        $now = Date::now();

        // Hari Minggu libur
        if ($now->dayOfWeek === Carbon::SUNDAY) {
            return [
                'status' => 'holiday',
                'message' => 'Hari Minggu libur, tidak ada absensi.',
            ];
        }

        // Cegah scan beruntun (kartu yang sama terbaca berkali-kali)
        $lastLog = AttendanceLog::where('student_id', $student->id)
            ->latest('scanned_at')
            ->first();

        if ($lastLog && $lastLog->scanned_at->diffInMinutes($now) < 2) {
            return ['status' => 'duplicate', 'log' => $lastLog];
        }

        $bukaPulang = $this->bukaPulang($now);
        $batasPulang = $this->batasPulang($now);

        $logsHariIni = AttendanceLog::where('student_id', $student->id)
            ->whereDate('scanned_at', $now->toDateString())
            ->get();

        $sudahMasuk = $logsHariIni->contains('type', 'masuk');
        $sudahPulang = $logsHariIni->contains('type', 'pulang');

        // Absensi hari ini sudah lengkap
        if ($sudahPulang) {
            return [
                'status' => 'complete',
                'message' => 'Absensi hari ini sudah lengkap (masuk dan pulang).',
            ];
        }

        // ── Belum absen masuk → ini scan MASUK ─────────────────────
        if (! $sudahMasuk) {
            // Jam pulang sudah dibuka = absen masuk sudah ditutup
            if ($now->gte($bukaPulang)) {
                return [
                    'status' => 'closed',
                    'message' => 'Absen masuk sudah ditutup (batas jam '
                        . $bukaPulang->format('H:i') . '). Silakan hubungi guru.',
                ];
            }

            $batasMasuk = $now->copy()->setTimeFromTimeString(config('attendance.jam_masuk'));

            $log = AttendanceLog::create([
                'student_id' => $student->id,
                'scanned_at' => $now,
                'type' => 'masuk',
                'status' => $now->gt($batasMasuk) ? 'telat' : 'tepat_waktu',
                'source' => $source,
                'recorded_by' => $recordedBy,
            ]);

            return ['status' => 'recorded', 'log' => $log];
        }

        // ── Sudah masuk, belum pulang → ini scan PULANG ────────────
        if ($now->lt($bukaPulang)) {
            return [
                'status' => 'too_early',
                'message' => 'Absen pulang belum waktunya. Absen pulang dibuka jam '
                    . $bukaPulang->format('H:i') . '.',
            ];
        }

        if ($now->gt($batasPulang)) {
            return [
                'status' => 'closed',
                'message' => 'Batas absen pulang sudah lewat (jam '
                    . $batasPulang->format('H:i') . '). Keterangan: belum melakukan absen pulang.',
            ];
        }

        $log = AttendanceLog::create([
            'student_id' => $student->id,
            'scanned_at' => $now,
            'type' => 'pulang',
            'status' => null,
            'source' => $source,
            'recorded_by' => $recordedBy,
        ]);

        return ['status' => 'recorded', 'log' => $log];
    }

    /**
     * Untuk rekap: true jika siswa sudah masuk, belum pulang, dan batas pulang hari itu sudah lewat.
     */
    public function belumAbsenPulang(AttendanceLog $log): bool
    {
        if ($log->type !== 'masuk' || $log->scanned_at->dayOfWeek === Carbon::SUNDAY) {
            return false;
        }

        $adaPulang = AttendanceLog::where('student_id', $log->student_id)
            ->where('type', 'pulang')
            ->whereDate('scanned_at', $log->scanned_at->toDateString())
            ->exists();

        if ($adaPulang) {
            return false;
        }

        return Date::now()->gt($this->batasPulang($log->scanned_at));
    }

    public function bukaPulang(Carbon $tanggal): Carbon
    {
        $jam = config('attendance.jam_pulang.' . $this->namaHari($tanggal), '12:30');

        return $tanggal->copy()->setTimeFromTimeString($jam);
    }

    public function batasPulang(Carbon $tanggal): Carbon
    {
        $jam = config('attendance.batas_pulang.' . $this->namaHari($tanggal), '13:00');

        return $tanggal->copy()->setTimeFromTimeString($jam);
    }

    private function namaHari(Carbon $tanggal): string
    {
        return match ($tanggal->dayOfWeek) {
            Carbon::MONDAY => 'senin',
            Carbon::TUESDAY => 'selasa',
            Carbon::WEDNESDAY => 'rabu',
            Carbon::THURSDAY => 'kamis',
            Carbon::FRIDAY => 'jumat',
            Carbon::SATURDAY => 'sabtu',
            default => 'minggu',
        };
    }
}