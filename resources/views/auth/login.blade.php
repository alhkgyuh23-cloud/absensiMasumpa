@extends('layouts.guest')

@section('title', 'Login Admin')

@section('content')
    <div class="w-full max-w-sm rounded-xl bg-white p-8 shadow-lg">
        <h1 class="text-xl font-bold text-slate-800">Login Admin</h1>
        <p class="mt-1 text-sm text-slate-500">Sistem Absensi Siswa</p>

        @if ($errors->any())
            <div class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-600">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('login.attempt') }}" class="mt-6 space-y-4">
            @csrf

            <div>
                <label for="email" class="block text-sm font-medium text-slate-700">Email</label>
                <input type="email" name="email" id="email" placeholder="masukkan contoh@gmail.com" required autofocus
                    value="{{ old('email') }}"
                    class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-slate-700">
                    Password
                </label>

                <div class="relative">
                    <input
                        type="password"
                        name="password"
                        id="password"
                        placeholder="masukkan password"
                        required
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 pr-10 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                    >

                    <button
                        type="button"
                        onclick="togglePassword()"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                    >
                        <!-- Mata terbuka -->
                        <svg
                            id="eye-open"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.5"
                            stroke="currentColor"
                            class="h-5 w-5"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M2.25 12s3.75-7.5 9.75-7.5S21.75 12 21.75 12s-3.75 7.5-9.75 7.5S2.25 12 2.25 12Z"
                            />
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"
                            />
                        </svg>

                        <!-- Mata tertutup -->
                        <svg
                            id="eye-closed"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.5"
                            stroke="currentColor"
                            class="hidden h-5 w-5"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M3.98 8.223A10.477 10.477 0 0 0 2.25 12s3.75 7.5 9.75 7.5a9.72 9.72 0 0 0 4.028-.87M6.228 6.228A9.723 9.723 0 0 1 12 4.5c6 0 9.75 7.5 9.75 7.5a10.45 10.45 0 0 1-2.134 3.204M6.228 6.228 3 3m3.228 3.228 3.65 3.65m4.244 4.244 3.65 3.65M14.122 14.122a3 3 0 0 1-4.244-4.244"
                            />
                        </svg>
                    </button>
                </div>
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="remember" class="rounded border-slate-300">
                Ingat saya
            </label>

            <button type="submit"
                class="w-full rounded-lg bg-emerald-600 py-2 text-sm font-medium text-white transition hover:bg-emerald-700">
                Masuk
            </button>
        </form>
    </div>
@endsection