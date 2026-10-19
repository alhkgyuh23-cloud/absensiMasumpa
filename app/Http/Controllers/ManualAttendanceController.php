<?php

namespace App\Http\Controllers;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\AttendanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ManualAttendanceController extends Controller
{
    public function index(): View
    {
        $classes = SchoolClass::with(['students' => fn ($q) => $q->where('is_active', true)->orderBy('name')])
            ->orderBy('tingkat')
            ->orderBy('rombel')
            ->get();

        return view('admin.manual-attendance.index', compact('classes'));
    }

    public function store(Request $request, AttendanceService $attendanceService): RedirectResponse
    {
        $request->validate([
            'student_id' => ['required', 'exists:students,id'],
        ]);

        $student = Student::where('id', $request->student_id)
            ->where('is_active', true)
            ->firstOrFail();

        $result = $attendanceService->record($student, 'manual', $request->user()->id);

        return match ($result['status']) {
            'recorded' => redirect()->route('manual-attendance.index')->with(
                'success',
                "{$student->name} berhasil dicatat absen {$result['log']->type}"
                    . ($result['log']->status === 'telat' ? ' (telat)' : '') . '.'
            ),
            'duplicate' => redirect()->route('manual-attendance.index')
                ->with('error', 'Sudah tercatat, tunggu sebentar sebelum mencatat lagi.'),
            default => redirect()->route('manual-attendance.index')->with('error', $result['message']),
        };
    }
}