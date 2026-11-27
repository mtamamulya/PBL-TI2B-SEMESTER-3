@extends('layouts.app')

@section('title', 'Tambah Pengabdian')

@section('content')

    <div class="flex flex-col gap-1.5">
        <h1 class="text-3xl font-bold tracking-tight">Tambah Pengabdian</h1>
        <p class="text-2xl text-gray-400">Dashboard / Pengabdian / Tambah</p>
    </div>

    @include('dosen.pengabdian._form')

@endsection