<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScanRequest;
use App\Models\Student;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class ScanController extends Controller
{
    public function __construct(private AttendanceService $attendanceService)
    {
    }

    public function index(): View
    {
        return view('scan.index');
    }

    public function store(ScanRequest $request): JsonResponse
    {
        $student = Student::where('card_id', $request->card_id)
            ->where('is_active', true)
            ->first();

        if (! $student) {
            return response()->json([
                'success' => false,
                'popup' => true,
                'reason' => 'not_found',
                'message' => 'Kartu tidak dikenali.',
            ], 404);
        }

        $result = $this->attendanceService->record($student);

        if ($result['status'] === 'duplicate') {
            return response()->json([
                'success' => false,
                'popup' => true,
                'reason' => 'duplicate',
                'nama' => $student->name, // dipakai untuk suara: "Atas nama ..., sudah tercatat"
                'message' => 'Sudah tercatat, tunggu sebentar sebelum scan lagi.',
            ]);
        }

        // Semua kasus yang ditampilkan sebagai popup peringatan
        if (in_array($result['status'], ['holiday', 'too_early', 'closed', 'complete'], true)) {
            return response()->json([
                'success' => false,
                'popup' => true,
                'reason' => $result['status'],
                'message' => $result['message'],
            ]);
        }

        $log = $result['log'];

        $message = $log->type === 'masuk'
            ? ($log->status === 'telat'
                ? 'Absen masuk berhasil (telat).'
                : 'Absen masuk berhasil (tepat waktu).')
            : 'Absen pulang berhasil.';

        return response()->json([
            'success' => true,
            'nama' => $student->name,
            'type' => $log->type,
            'status' => $log->status,
            'jam' => $log->scanned_at->format('H:i'),
            'message' => $message,
        ]);
    }
}