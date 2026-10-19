<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStudentRequest;
use App\Http\Requests\UpdateStudentRequest;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StudentController extends Controller
{
    public function index(Request $request): View
    {
        $query = Student::with('schoolClass')->orderBy('name');

        if ($request->filled('tingkat')) {
            $query->whereHas('schoolClass', fn ($q) => $q->where('tingkat', $request->tingkat));
        }

        if ($request->filled('class_id')) {
            $query->where('class_id', $request->class_id);
        }

        $students = $query->paginate(20)->withQueryString();

        $classes = SchoolClass::orderBy('tingkat')->orderBy('rombel')->get();

        return view('admin.students.index', compact('students', 'classes'));
    }

    public function create(): View
    {
        $classes = SchoolClass::orderBy('tingkat')->orderBy('rombel')->get();

        return view('admin.students.create', compact('classes'));
    }

    public function store(StoreStudentRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $student = Student::create([
                'name' => $request->name,
                'nis' => $request->nis,
                'card_id' => $request->card_id,
                'class_id' => $request->class_id,
                'is_active' => $request->boolean('is_active', true),
            ]);

            $student->parentContacts()->create([
                'name' => $request->parent_name,
                'relationship' => $request->parent_relationship,
                'wa_number' => $request->parent_wa_number,
                'is_primary' => true,
            ]);
        });

        return redirect()->route('students.index')->with('success', 'Siswa berhasil ditambahkan.');
    }

    public function edit(Student $student): View
    {
        $classes = SchoolClass::orderBy('tingkat')->orderBy('rombel')->get();
        $primaryContact = $student->parentContacts()->where('is_primary', true)->first();

        return view('admin.students.edit', compact('student', 'classes', 'primaryContact'));
    }

    public function update(UpdateStudentRequest $request, Student $student): RedirectResponse
    {
        DB::transaction(function () use ($request, $student) {
            $student->update([
                'name' => $request->name,
                'nis' => $request->nis,
                'card_id' => $request->card_id,
                'class_id' => $request->class_id,
                'is_active' => $request->boolean('is_active', true),
            ]);

            $student->parentContacts()->updateOrCreate(
                ['is_primary' => true],
                [
                    'name' => $request->parent_name,
                    'relationship' => $request->parent_relationship,
                    'wa_number' => $request->parent_wa_number,
                ]
            );
        });

        return redirect()->route('students.index')->with('success', 'Siswa berhasil diperbarui.');
    }

    public function destroy(Student $student): RedirectResponse
    {
        $student->delete();

        return redirect()->route('students.index')->with('success', 'Siswa berhasil dihapus.');
    }
}