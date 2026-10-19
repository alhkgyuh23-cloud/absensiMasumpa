@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <h1 class="text-2xl font-bold text-slate-800">Dashboard</h1>
    <p class="mt-1 text-sm text-slate-500">Ringkasan absensi hari ini</p>

    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="rounded-xl bg-white p-6 shadow">
            <p class="text-sm text-slate-500">Hadir hari ini</p>
            <p id="total-hadir" class="mt-2 text-3xl font-bold text-emerald-600">{{ $totalHadirHariIni }}</p>
        </div>
        <div class="rounded-xl bg-white p-6 shadow">
            <p class="text-sm text-slate-500">Total siswa aktif</p>
            <p id="total-siswa" class="mt-2 text-3xl font-bold text-slate-800">{{ $totalSiswa }}</p>
        </div>
    </div>

    <h2 class="mt-8 text-lg font-semibold text-slate-800">Kehadiran Kelas Hari Ini</h2>

    <div class="mt-4 space-y-6">
        @foreach ($classes as $class)
            @php
                $rows = $kehadiranPerKelas->get($class->id, collect());
                $hadir = $rows->filter(fn ($r) => $r->masuk)->count();
                $totalKelas = $siswaPerKelas->get($class->id, 0);
            @endphp

            <div class="overflow-hidden rounded-xl bg-white shadow">
                <div class="flex flex-wrap items-center justify-between gap-1 border-b border-slate-100 px-4 py-3">
                    <h3 class="text-sm font-semibold text-slate-700">Kelas {{ $class->name }}</h3>
                    <span id="hadir-count-{{ $class->id }}" class="text-xs text-slate-500">
                        {{ $hadir }} dari {{ $totalKelas }} siswa hadir
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full whitespace-nowrap text-left text-sm">
                        <thead class="bg-slate-50 text-slate-500">
                            <tr>
                                <th class="hidden px-4 py-3 sm:table-cell">Waktu</th>
                                <th class="px-4 py-3">Nama</th>
                                <th class="px-4 py-3">Absen Masuk</th>
                                <th class="px-4 py-3">Absen Pulang</th>
                                <th class="px-4 py-3">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-{{ $class->id }}" class="divide-y divide-slate-100">
                            @forelse ($rows as $row)
                                <tr>
                                    <td class="hidden px-4 py-3 sm:table-cell">{{ $row->tanggal->format('d M Y') }}</td>
                                    <td class="px-4 py-3 font-medium text-slate-800">{{ $row->nama }}</td>
                                    <td class="px-4 py-3">
                                        @if ($row->masuk)
                                            <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs text-emerald-700">
                                                {{ $row->masuk->format('H:i') }}
                                            </span>
                                        @else
                                            <span class="text-slate-300">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($row->pulang)
                                            <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs text-amber-700">
                                                {{ $row->pulang->format('H:i') }}
                                            </span>
                                        @else
                                            <span class="text-slate-300">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-1">
                                            @if ($row->jenis === 'sakit')
                                                <span class="rounded-full bg-orange-50 px-2 py-0.5 text-xs text-orange-700">Sakit</span>
                                            @elseif ($row->jenis === 'izin')
                                                <span class="rounded-full bg-sky-50 px-2 py-0.5 text-xs text-sky-700">Izin</span>
                                            @elseif ($row->status === 'telat')
                                                <span class="rounded-full bg-red-50 px-2 py-0.5 text-xs text-red-700">Telat</span>
                                            @elseif ($row->status === 'tepat_waktu')
                                                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs text-emerald-700">Tepat waktu</span>
                                            @endif

                                            @if ($row->pulang)
                                                <span class="rounded-full bg-sky-50 px-2 py-0.5 text-xs text-sky-700">Pulang berhasil</span>
                                            @endif

                                            @if ($row->belum_pulang)
                                                <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs text-amber-700">Belum absen pulang</span>
                                            @endif

                                            @if (! $row->jenis && ! $row->status && ! $row->pulang && ! $row->belum_pulang)
                                                <span class="text-slate-300">—</span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-6 text-center text-slate-400">Belum ada yang absen hari ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    </div>

    @vite(['resources/js/dashboard.js'])
@endsection