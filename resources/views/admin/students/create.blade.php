@extends('layouts.app')

@section('title', 'Tambah Siswa')

@section('content')
    <h1 class="text-2xl font-bold text-slate-800">Tambah Siswa</h1>

    <div class="mt-6 max-w-2xl rounded-xl bg-white p-6 shadow">
        <form method="POST" action="{{ route('students.store') }}">
            @include('admin.students._form')
        </form>
    </div>
@endsection