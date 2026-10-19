@extends('layouts.app')

@section('title', 'Sakit / Izin')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold text-slate-800">Sakit / Izin</h1>
        <a href="{{ route('absence-notes.create') }}"
            class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
            + Tambah Catatan
        </a>
    </div>

    @if (session('success'))
        <div class="mt-4 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-700">{{ session('success') }}</div>
    @endif

    <form method="GET" class="mt-6 grid grid-cols-2 gap-4 rounded-xl bg-white p-4 shadow sm:grid-cols-4">
        <div>
            <label class="block text-xs font-medium text-slate-500">Cari Nama</label>
            <input type="text" name="nama" value="{{ request('nama') }}" placeholder="Nama siswa..."
                class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">Jenis</label>
            <select name="jenis" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                <option value="">Semua</option>
                <option value="sakit" @selected(request('jenis') == 'sakit')>Sakit</option>
                <option value="izin" @selected(request('jenis') == 'izin')>Izin</option>
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
            <a href="{{ route('absence-notes.index') }}" class="ml-3 text-sm text-slate-500 hover:underline">Reset</a>
        </div>
    </form>

    <div class="mt-6 overflow-hidden rounded-xl bg-white shadow">
        <div class="overflow-x-auto">
            <table class="w-full whitespace-nowrap text-left text-sm">
                <thead class="bg-slate-50 text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3">Kelas</th>
                        <th class="px-4 py-3">Jenis</th>
                        <th class="px-4 py-3">Tanggal</th>
                        <th class="px-4 py-3">Keterangan</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($notes as $note)
                        <tr>
                            <td class="px-4 py-3 font-medium">{{ $note->student->name }}</td>
                            <td class="px-4 py-3">{{ $note->student->schoolClass->name }}</td>
                            <td class="px-4 py-3">
                                @if ($note->jenis === 'sakit')
                                    <span class="rounded-full bg-amber-50 px-2 py-1 text-xs text-amber-700">Sakit</span>
                                @else
                                    <span class="rounded-full bg-sky-50 px-2 py-1 text-xs text-sky-700">Izin</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                {{ $note->tanggal_mulai->format('d M Y') }}
                                @if (! $note->tanggal_mulai->equalTo($note->tanggal_selesai))
                                    — {{ $note->tanggal_selesai->format('d M Y') }}
                                @endif
                            </td>
                            {{-- Keterangan boleh turun baris karena bisa panjang --}}
                            <td class="min-w-[12rem] max-w-xs whitespace-normal px-4 py-3 text-slate-500">{{ $note->keterangan ?: '—' }}</td>
                            <td class="px-4 py-3 text-right space-x-3">
                                <a href="{{ route('absence-notes.edit', $note) }}" class="inline-block py-1 text-emerald-600 hover:underline">Edit</a>
                                <form action="{{ route('absence-notes.destroy', $note) }}" method="POST" class="inline"
                                    onsubmit="return confirm('Hapus catatan ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="py-1 text-red-600 hover:underline">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-slate-400">Belum ada catatan sakit/izin.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $notes->links() }}</div>
@endsection