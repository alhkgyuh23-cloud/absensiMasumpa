@extends('layouts.kiosk')

@section('title', 'Scan Kartu Siswa')

@section('content')
    <style>
        @keyframes scan-sweep {
            0%   { top: 0%; }
            100% { top: 70%; }
        }

        .scan-sweep {
            height: 30%;
            background: linear-gradient(
                to bottom,
                rgba(34, 197, 94, 0) 0%,
                rgba(34, 197, 94, 0.45) 50%,
                rgba(34, 197, 94, 0) 100%
            );
            animation: scan-sweep 2.4s ease-in-out infinite alternate;
        }

        @keyframes card-pop {
            0%   { opacity: 0; transform: scale(0.92) translateY(8px); }
            100% { opacity: 1; transform: scale(1) translateY(0); }
        }

        .card-pop {
            animation: card-pop 0.22s ease-out;
        }

        /* Garis hitung mundur: menyusut dari penuh ke kosong selama popup tampil */
        @keyframes countdown {
            from { transform: scaleX(1); }
            to   { transform: scaleX(0); }
        }

        .countdown-bar {
            transform-origin: left center;
        }
    </style>

    {{-- Tombol suara (pojok kanan atas) --}}
    <button
        type="button"
        id="sound-toggle"
        class="fixed right-4 top-4 z-40 rounded-full bg-slate-800 p-3 text-slate-300 shadow hover:bg-slate-700"
        aria-label="Matikan suara"
        title="Suara aktif (klik untuk mematikan)"
    >
        <svg id="sound-icon-on" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M15.536 8.464a5 5 0 010 7.072M18.364 5.636a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707A1 1 0 0112 5.586v12.828a1 1 0 01-1.707.707L5.586 15z" />
        </svg>
        <svg id="sound-icon-off" class="hidden h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707A1 1 0 0112 5.586v12.828a1 1 0 01-1.707.707L5.586 15zM17 14l4-4m0 4l-4-4" />
        </svg>
    </button>

    <div class="flex min-h-screen flex-col items-center justify-center px-4 text-center">
        <h1 class="text-3xl font-bold">Arahkan Barcode ke Kamera</h1>
        <p class="mt-2 text-slate-400">atau tap kartu ke alat scan</p>

        {{-- Live kamera --}}
        <div class="relative mt-8 w-full max-w-md overflow-hidden rounded-2xl border-4 border-slate-700 bg-black">
            <div id="reader" class="w-full"></div>

            {{-- band hijau transparan yang menyapu naik-turun --}}
            <div
                id="scan-line"
                class="scan-sweep pointer-events-none absolute inset-x-0"
            ></div>
        </div>

        <p id="camera-status" class="mt-3 text-sm text-slate-500">Menyiapkan kamera...</p>

        {{-- Muncul sampai halaman diketuk/ditekan sekali (syarat browser agar suara boleh berbunyi) --}}
        <p id="sound-hint" class="mt-1 hidden text-sm font-medium text-amber-400">
            Ketuk layar atau tekan tombol sekali untuk mengaktifkan suara
        </p>

        {{-- Input tersembunyi, menangkap ketikan dari alat scan USB --}}
        <input
            type="text"
            id="card-input"
            autocomplete="off"
            class="absolute -left-96 opacity-0"
            aria-hidden="true"
        >

        {{-- Hasil scan tampil sebagai popup di tengah layar, kotak ini hanya penanda siaga --}}
        <div id="result" class="mt-6 w-full max-w-md rounded-2xl bg-slate-800 p-8">
            <p class="text-lg font-semibold text-slate-400">Menunggu kartu...</p>
        </div>
    </div>

    {{-- Popup peringatan / error (hilang otomatis, ketuk untuk menutup lebih cepat) --}}
    <div
        id="popup-modal"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 px-4"
    >
        <div class="card-pop w-full max-w-sm rounded-2xl bg-white p-6 text-center shadow-xl">
            <div id="popup-icon-wrap" class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-amber-100">
                <svg id="popup-icon" class="h-6 w-6 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path id="popup-icon-path" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <p id="popup-message" class="mt-4 text-base font-medium text-slate-800"></p>

            <div class="mt-5 h-1 w-full overflow-hidden rounded-full bg-slate-100">
                <div id="popup-progress" class="countdown-bar h-1 rounded-full bg-amber-400"></div>
            </div>
        </div>
    </div>

    {{-- Card sukses absensi (hilang otomatis, ketuk untuk menutup lebih cepat) --}}
    <div
        id="success-modal"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 px-4"
    >
        <div class="card-pop w-full max-w-sm rounded-2xl bg-white p-8 text-center shadow-xl">
            <div id="success-icon-wrap" class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100">
                <svg id="success-icon" class="h-8 w-8 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path id="success-icon-path" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>

            <p id="success-title" class="mt-5 text-sm font-bold uppercase tracking-widest text-slate-500">Kehadiran</p>
            <p id="success-name" class="mt-2 text-2xl font-bold text-slate-900"></p>

            <p id="success-jam" class="mt-6 text-5xl font-bold tracking-tight text-slate-800"></p>

            <p id="success-message" class="mt-6 rounded-lg bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700"></p>

            <div class="mt-5 h-1 w-full overflow-hidden rounded-full bg-slate-100">
                <div id="success-progress" class="countdown-bar h-1 rounded-full bg-emerald-400"></div>
            </div>
        </div>
    </div>
@endsection