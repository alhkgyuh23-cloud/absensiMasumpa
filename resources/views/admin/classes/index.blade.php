@extends('layouts.app')

@section('title', 'Kelas')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold text-slate-800">Kelas</h1>
        <a href="{{ route('classes.create') }}"
            class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
            + Tambah Kelas
        </a>
    </div>

    @if (session('success'))
        <div class="mt-4 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-700">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-600">{{ $errors->first() }}</div>
    @endif

    <div class="mt-6 overflow-hidden rounded-xl bg-white shadow">
        <div class="overflow-x-auto">
            <table class="w-full whitespace-nowrap text-left text-sm">
                <thead class="bg-slate-50 text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Nama Kelas</th>
                        <th class="px-4 py-3">Jumlah Siswa</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($classes as $class)
                        <tr>
                            <td class="px-4 py-3 font-medium">{{ $class->name }}</td>
                            <td class="px-4 py-3">{{ $class->students_count }}</td>
                            <td class="px-4 py-3 text-right space-x-3">
                                <a href="{{ route('classes.edit', $class) }}" class="inline-block py-1 text-emerald-600 hover:underline">Edit</a>
                                <form action="{{ route('classes.destroy', $class) }}" method="POST" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="button" class="py-1 text-red-600 hover:underline"
                                        data-confirm-delete
                                        data-message="Hapus kelas {{ $class->name }}?">
                                        Hapus
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-6 text-center text-slate-400">Belum ada kelas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection