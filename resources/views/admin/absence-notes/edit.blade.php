@extends('layouts.app')

@section('title', 'Edit Catatan Sakit/Izin')

@section('content')
    <h1 class="text-2xl font-bold text-slate-800">Edit Catatan — {{ $note->student->name }}</h1>

    <div class="mt-6 max-w-lg rounded-xl bg-white p-6 shadow">
        <form method="POST" action="{{ route('absence-notes.update', $note) }}">
            @method('PUT')
            @include('admin.absence-notes._form')
        </form>
    </div>

    @vite(['resources/js/absence-notes.js'])
@endsection