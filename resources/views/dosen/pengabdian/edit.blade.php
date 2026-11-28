@extends('layouts.app')

@section('title', 'Ubah Pengabdian')

@section('content')

    <div class="flex flex-col gap-1.5">
        <h1 class="text-3xl font-bold tracking-tight">Ubah Pengabdian</h1>
        <p class="text-2xl text-gray-400">Dashboard / Pengabdian / Ubah</p>
    </div>

    @include('dosen.pengabdian._form')

@endsection