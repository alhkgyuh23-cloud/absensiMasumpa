<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard')</title>
    @include('layouts._favicon')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">

    {{-- Top bar, hanya tampil di HP --}}
    <header class="sticky top-0 z-30 flex items-center gap-3 bg-slate-900 px-4 py-3 text-white md:hidden">
        <button id="menu-open" type="button" aria-label="Buka menu"
            class="-ml-2 rounded-lg p-2 hover:bg-slate-800">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>
        <span class="text-base font-bold">Absensi Siswa</span>
    </header>

    {{-- Latar gelap di belakang sidebar (HP) --}}
    <div id="sidebar-overlay" class="fixed inset-0 z-40 hidden bg-black/50 md:hidden"></div>

    <div class="md:flex md:min-h-screen">
        <aside id="sidebar"
            class="fixed inset-y-0 left-0 z-50 w-64 -translate-x-full overflow-y-auto bg-slate-900 px-4 py-6 text-white transition-transform duration-200 md:sticky md:top-0 md:z-auto md:h-screen md:shrink-0 md:translate-x-0">
            <div class="flex items-center justify-between px-2">
                <h2 class="text-lg font-bold">Absensi Siswa</h2>
                <button id="menu-close" type="button" aria-label="Tutup menu"
                    class="-mr-2 rounded-lg p-2 text-slate-400 hover:bg-slate-800 hover:text-white md:hidden">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <nav class="mt-8 space-y-1">
                <a href="{{ route('dashboard') }}"
                    class="block rounded-lg px-3 py-2.5 text-sm hover:bg-slate-800 {{ request()->routeIs('dashboard') ? 'bg-slate-800' : '' }}">
                    Dashboard
                </a>
                <a href="{{ route('classes.index') }}"
                    class="block rounded-lg px-3 py-2.5 text-sm hover:bg-slate-800 {{ request()->routeIs('classes.*') ? 'bg-slate-800' : '' }}">
                    Kelas
                </a>
                <a href="{{ route('students.index') }}"
                    class="block rounded-lg px-3 py-2.5 text-sm hover:bg-slate-800 {{ request()->routeIs('students.*') ? 'bg-slate-800' : '' }}">
                    Siswa
                </a>
                <a href="{{ route('manual-attendance.index') }}"
                    class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-800 {{ request()->routeIs('manual-attendance.*') ? 'bg-slate-800' : '' }}">
                    Absen Manual
                </a>
                <a href="{{ route('absence-notes.index') }}"
                    class="block rounded-lg px-3 py-2.5 text-sm hover:bg-slate-800 {{ request()->routeIs('absence-notes.*') ? 'bg-slate-800' : '' }}">
                    Sakit / Izin
                </a>
                <a href="{{ route('reports.index') }}"
                    class="block rounded-lg px-3 py-2.5 text-sm hover:bg-slate-800 {{ request()->routeIs('reports.*') ? 'bg-slate-800' : '' }}">
                    Rekap
                </a>
            </nav>

            <form method="POST" action="{{ route('logout') }}" class="mt-8 px-2">
                @csrf
                <button type="submit" class="py-2 text-sm text-slate-400 hover:text-white">
                    Keluar
                </button>
            </form>
        </aside>

        {{-- min-w-0 penting: supaya konten (tabel lebar) bisa menyempit dan menggeser di dalam wadahnya, bukan mendorong halaman --}}
        <main class="min-w-0 flex-1 p-4 md:p-8">
            @yield('content')
        </main>
    </div>

    {{-- Modal konfirmasi hapus, dipakai bareng oleh halaman mana pun yang punya tombol
         dengan atribut data-confirm-delete (lihat contoh di students/index.blade.php) --}}
    <div id="confirm-delete-modal" class="fixed inset-0 z-[60] hidden items-center justify-center bg-black/50 px-4">
        <div class="w-full max-w-sm rounded-2xl bg-white p-6 text-center shadow-xl">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-red-100">
                <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
            </div>
            <p id="confirm-delete-message" class="mt-4 text-sm font-medium text-slate-800"></p>
            <div class="mt-6 flex gap-3">
                <button type="button" id="confirm-delete-cancel"
                    class="flex-1 rounded-lg border border-slate-300 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">
                    Batal
                </button>
                <button type="button" id="confirm-delete-ok"
                    class="flex-1 rounded-lg bg-red-600 py-2 text-sm font-medium text-white hover:bg-red-700">
                    Hapus
                </button>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');

            function openMenu() {
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
            }

            function closeMenu() {
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }

            document.getElementById('menu-open').addEventListener('click', openMenu);
            document.getElementById('menu-close').addEventListener('click', closeMenu);
            overlay.addEventListener('click', closeMenu);

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') closeMenu();
            });

            // Layar membesar ke ukuran tablet/komputer: pastikan state HP dibersihkan
            window.matchMedia('(min-width: 768px)').addEventListener('change', function (e) {
                if (e.matches) closeMenu();
            });
        })();

        (function () {
            const modal = document.getElementById('confirm-delete-modal');
            const messageEl = document.getElementById('confirm-delete-message');
            const btnCancel = document.getElementById('confirm-delete-cancel');
            const btnOk = document.getElementById('confirm-delete-ok');

            let pendingForm = null;

            function showModal(form, message) {
                pendingForm = form;
                messageEl.textContent = message;
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            }

            function hideModal() {
                pendingForm = null;
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }

            // Delegasi event: tangkap klik pada tombol mana pun yang punya [data-confirm-delete]
            document.addEventListener('click', function (e) {
                const trigger = e.target.closest('[data-confirm-delete]');
                if (!trigger) return;

                const form = trigger.closest('form');
                if (!form) return;

                showModal(form, trigger.dataset.message || 'Yakin ingin menghapus data ini?');
            });

            btnCancel.addEventListener('click', hideModal);

            btnOk.addEventListener('click', function () {
                if (pendingForm) pendingForm.submit();
                hideModal();
            });

            modal.addEventListener('click', function (e) {
                if (e.target === modal) hideModal();
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') hideModal();
            });
        })();
    </script>
</body>
</html>