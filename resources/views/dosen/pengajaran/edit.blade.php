@extends('layouts.app')

@section('title', 'Edit Pengajaran')

@section('content')

    <div class="flex flex-col gap-1.5">
        <h1 class="text-3xl font-bold tracking-tight">Edit Pengajaran</h1>
        <p class="text-2xl text-gray-400">Dashboard / Pengajaran / Edit Pengajaran</p>
    </div>

    @include('dosen.pengajaran._form', ['isEdit' => true])

@endsection