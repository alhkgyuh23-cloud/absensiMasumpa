<?php

namespace App\Http\Controllers;

use App\Models\SchoolClass;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SchoolClassController extends Controller
{
    public function index(): View
    {
        $classes = SchoolClass::withCount('students')
            ->orderBy('tingkat')
            ->orderBy('rombel')
            ->get();

        return view('admin.classes.index', compact('classes'));
    }

    public function create(): View
    {
        return view('admin.classes.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        if ($this->isDuplicate($data)) {
            return back()->withErrors(['rombel' => 'Kelas dengan kombinasi ini sudah ada.'])->withInput();
        }

        SchoolClass::create($data);

        return redirect()->route('classes.index')->with('success', 'Kelas berhasil ditambahkan.');
    }

    public function edit(SchoolClass $class): View
    {
        return view('admin.classes.edit', ['schoolClass' => $class]);
    }

    public function update(Request $request, SchoolClass $class): RedirectResponse
    {
        $data = $this->validated($request);

        if ($this->isDuplicate($data, $class->id)) {
            return back()->withErrors(['rombel' => 'Kelas dengan kombinasi ini sudah ada.'])->withInput();
        }

        $class->update($data);

        return redirect()->route('classes.index')->with('success', 'Kelas berhasil diperbarui.');
    }

    public function destroy(SchoolClass $class): RedirectResponse
    {
        if ($class->students()->exists()) {
            return back()->withErrors(['rombel' => 'Kelas tidak bisa dihapus karena masih ada siswa di dalamnya.']);
        }

        $class->delete();

        return redirect()->route('classes.index')->with('success', 'Kelas berhasil dihapus.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'tingkat' => ['required', 'integer', 'in:10,11,12'],
            'rombel' => ['required', 'string', 'max:5', 'regex:/^[A-Za-z]+$/'],
        ]);
    }

    private function isDuplicate(array $data, ?int $ignoreId = null): bool
    {
        return SchoolClass::where('tingkat', $data['tingkat'])
            ->whereRaw('UPPER(rombel) = ?', [strtoupper($data['rombel'])])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
    }
}