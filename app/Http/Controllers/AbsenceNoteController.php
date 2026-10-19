<?php

namespace App\Http\Controllers;

use App\Models\AbsenceNote;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AbsenceNoteController extends Controller
{
    public function index(Request $request): View
    {
        $query = AbsenceNote::with(['student.schoolClass'])
            ->orderByDesc('tanggal_mulai');

        if ($request->filled('nama')) {
            $query->whereHas('student', fn ($q) => $q->where('name', 'like', '%' . $request->nama . '%'));
        }

        if ($request->filled('jenis')) {
            $query->where('jenis', $request->jenis);
        }

        if ($request->filled('class_id')) {
            $query->whereHas('student', fn ($q) => $q->where('class_id', $request->class_id));
        }

        $notes = $query->paginate(20)->withQueryString();

        $classes = SchoolClass::orderBy('tingkat')->orderBy('rombel')->get();

        return view('admin.absence-notes.index', compact('notes', 'classes'));
    }


    public function create(): View
    {
        $classes = SchoolClass::with(['students' => fn ($q) => $q->orderBy('name')])
            ->orderBy('tingkat')
            ->orderBy('rombel')
            ->get();

        return view('admin.absence-notes.create', compact('classes'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;

        AbsenceNote::create($data);

        return redirect()->route('absence-notes.index')->with('success', 'Catatan sakit/izin berhasil ditambahkan.');
    }

    public function edit(AbsenceNote $absenceNote): View
    {
        $classes = SchoolClass::with(['students' => fn ($q) => $q->orderBy('name')])
            ->orderBy('tingkat')
            ->orderBy('rombel')
            ->get();

        return view('admin.absence-notes.edit', [
            'note' => $absenceNote,
            'classes' => $classes,
        ]);
    }

    public function update(Request $request, AbsenceNote $absenceNote): RedirectResponse
    {
        $absenceNote->update($this->validated($request));

        return redirect()->route('absence-notes.index')->with('success', 'Catatan sakit/izin berhasil diperbarui.');
    }

    public function destroy(AbsenceNote $absenceNote): RedirectResponse
    {
        $absenceNote->delete();

        return redirect()->route('absence-notes.index')->with('success', 'Catatan sakit/izin berhasil dihapus.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'jenis' => ['required', 'in:sakit,izin'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ]);
    }
}