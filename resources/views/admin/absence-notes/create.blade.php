@extends('layouts.app')

@section('title', 'Tambah Catatan Sakit/Izin')

@section('content')
    <h1 class="text-2xl font-bold text-slate-800">Tambah Catatan Sakit/Izin</h1>

    <div class="mt-6 max-w-lg rounded-xl bg-white p-6 shadow">
        <form method="POST" action="{{ route('absence-notes.store') }}">
            @include('admin.absence-notes._form')
        </form>
    </div>

    @vite(['resources/js/absence-notes.js'])
@endsection