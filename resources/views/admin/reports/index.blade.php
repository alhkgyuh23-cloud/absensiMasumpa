@extends('layouts.app')

@section('title', 'Rekap Absensi')

@section('content')
    <h1 class="text-2xl font-bold text-slate-800">Rekap Absensi</h1>

    <form method="GET" class="mt-6 grid grid-cols-2 gap-4 rounded-xl bg-white p-4 shadow sm:grid-cols-4">
        <div>
            <label class="block text-xs font-medium text-slate-500">Dari Tanggal</label>
            <input type="date" name="from" value="{{ $from }}"
                class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">Sampai Tanggal</label>
            <input type="date" name="to" value="{{ $to }}"
                class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
        </div>
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
        <div class="col-span-2 sm:col-span-4">
            <div class="flex flex-wrap items-center gap-2">
                <button type="submit"
                    class="rounded-lg bg-emerald-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-emerald-700">
                    Filter
                </button>

                {{-- Memakai isi form saat ini (tanpa harus menekan Filter dulu), hasilnya terbuka di tab baru --}}
                <button type="submit" formaction="{{ route('reports.pdf') }}" formtarget="_blank"
                    class="rounded-lg border border-slate-300 bg-white px-4 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Cetak PDF
                </button>

                {{-- Excel: rincian per tanggal, langsung terunduh --}}
                <button type="submit" formaction="{{ route('reports.excel') }}"
                    class="rounded-lg border border-emerald-600 bg-white px-4 py-1.5 text-sm font-medium text-emerald-700 hover:bg-emerald-50">
                    Cetak Excel
                </button>

                <a href="{{ route('reports.index') }}" class="ml-1 text-sm text-slate-500 hover:underline">Reset</a>
            </div>
            <p class="mt-2 text-xs text-slate-400">
                Cetak PDF memuat total S / I / A per kelas untuk rentang tanggal di atas
                (misalnya 1 Juli – 31 Desember untuk semester Ganjil).
                Cetak Excel memuat rincian per tanggal: siapa yang S / I / A di tanggal berapa,
                lengkap dengan jumlah siswa S / I / A setiap tanggalnya.
            </p>
        </div>
    </form>

    <div class="mt-6 overflow-hidden rounded-xl bg-white shadow">
        <div class="overflow-x-auto">
            <table class="w-full whitespace-nowrap text-left text-sm">
                <thead class="bg-slate-50 text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Waktu</th>
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3">Kelas</th>
                        <th class="px-4 py-3">Absen Masuk</th>
                        <th class="px-4 py-3">Absen Pulang</th>
                        <th class="px-4 py-3">Keterangan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($rekap as $row)
                        <tr>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $row->tanggal->format('d M Y') }}</td>
                            <td class="px-4 py-3 font-medium text-slate-800">{{ $row->nama }}</td>
                            <td class="px-4 py-3">{{ $row->kelas }}</td>
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
                            <td colspan="6" class="px-4 py-6 text-center text-slate-400">Tidak ada data untuk filter ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $rekap->links() }}</div>
@endsection