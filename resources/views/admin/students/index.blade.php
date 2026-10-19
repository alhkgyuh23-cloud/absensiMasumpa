@extends('layouts.app')

@section('title', 'Siswa')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold text-slate-800">Siswa</h1>
        <a href="{{ route('students.create') }}"
            class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
            + Tambah Siswa
        </a>
    </div>

    @if (session('success'))
        <div class="mt-4 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-700">{{ session('success') }}</div>
    @endif

    <form method="GET" class="mt-6 grid grid-cols-2 gap-4 rounded-xl bg-white p-4 shadow sm:grid-cols-3">
        <div>
            <label class="block text-xs font-medium text-slate-500">Tingkat</label>
            <select name="tingkat" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                <option value="">Semua</option>
                @foreach ([10, 11, 12] as $t)
                    <option value="{{ $t }}" @selected(request('tingkat') == $t)>{{ $t }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">Kelas</label>
            <select name="class_id" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                <option value="">Semua</option>
                @foreach ($classes as $class)
                    <option value="{{ $class->id }}" @selected(request('class_id') == $class->id)>{{ $class->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-span-2 flex items-end sm:col-span-1">
            <button type="submit"
                class="rounded-lg bg-emerald-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-emerald-700">
                Filter
            </button>
            <a href="{{ route('students.index') }}" class="ml-3 text-sm text-slate-500 hover:underline">Reset</a>
        </div>
    </form>

    <div class="mt-6 overflow-hidden rounded-xl bg-white shadow">
        <div class="overflow-x-auto">
            <table class="w-full whitespace-nowrap text-left text-sm">
                <thead class="bg-slate-50 text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3">Kelas</th>
                        <th class="px-4 py-3">Kode Kartu</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($students as $student)
                        <tr>
                            <td class="px-4 py-3 font-medium">{{ $student->name }}</td>
                            <td class="px-4 py-3">{{ $student->schoolClass->name }}</td>
                            <td class="px-4 py-3 font-mono">{{ $student->card_id }}</td>
                            <td class="px-4 py-3">
                                @if ($student->is_active)
                                    <span class="rounded-full bg-emerald-50 px-2 py-1 text-xs text-emerald-700">Aktif</span>
                                @else
                                    <span class="rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-500">Nonaktif</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right space-x-3">
                                <a href="{{ route('students.edit', $student) }}" class="inline-block py-1 text-emerald-600 hover:underline">Edit</a>
                                <form action="{{ route('students.destroy', $student) }}" method="POST" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="button" class="py-1 text-red-600 hover:underline"
                                        data-confirm-delete
                                        data-message="Hapus siswa {{ $student->name }}?">
                                        Hapus
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-slate-400">Tidak ada siswa untuk filter ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $students->links() }}</div>
@endsection