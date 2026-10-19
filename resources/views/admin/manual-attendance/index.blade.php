@extends('layouts.app')

@section('title', 'Absen Manual')

@section('content')
    <h1 class="text-2xl font-bold text-slate-800">Absen Manual</h1>
    <p class="mt-1 text-sm text-slate-500">
        Khusus untuk siswa yang lupa membawa kartu. Sistem tetap mengikuti aturan jam masuk/pulang seperti biasa.
    </p>

    @if (session('success'))
        <div class="mt-4 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-700">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ session('error') }}</div>
    @endif

    <div class="mt-6 max-w-lg rounded-xl bg-white p-6 shadow">
        <form method="POST" action="{{ route('manual-attendance.store') }}">
            @csrf

            <label class="block text-sm font-medium text-slate-700">Kelas</label>
            <select id="class_filter" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Semua kelas</option>
                @foreach ($classes as $class)
                    <option value="{{ $class->id }}">{{ $class->name }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-slate-400">Pilih kelas untuk mempersempit daftar siswa.</p>

            <div class="mt-4">
                <label class="block text-sm font-medium text-slate-700">Siswa</label>
                <select name="student_id" id="student_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">— Pilih siswa —</option>
                    @foreach ($classes as $class)
                        @if ($class->students->isNotEmpty())
                            <optgroup label="{{ $class->name }}" data-class-id="{{ $class->id }}">
                                @foreach ($class->students as $student)
                                    <option value="{{ $student->id }}">{{ $student->name }} ({{ $class->name }})</option>
                                @endforeach
                            </optgroup>
                        @endif
                    @endforeach
                </select>
            </div>

            <button type="submit"
                class="mt-6 w-full rounded-lg bg-emerald-600 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                Catat Absen Sekarang
            </button>
        </form>
    </div>

    @vite(['resources/js/absence-notes.js'])
@endsection