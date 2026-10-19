@extends('layouts.app')

@section('title', 'Edit Kelas')

@section('content')
    <h1 class="text-2xl font-bold text-slate-800">Edit Kelas — {{ $schoolClass->name }}</h1>

    <div class="mt-6 max-w-md rounded-xl bg-white p-6 shadow">
        <form method="POST" action="{{ route('classes.update', $schoolClass) }}">
            @method('PUT')
            @include('admin.classes._form')
        </form>
    </div>
@endsection