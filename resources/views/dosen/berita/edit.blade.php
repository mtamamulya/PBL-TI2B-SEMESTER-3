@extends('layouts.app')
@section('title', 'Edit Berita')

@section('content')
<div class="flex flex-col gap-1.5">
    <h1 class="text-3xl font-bold tracking-tight">Edit Berita</h1>
    <p class="text-2xl text-gray-400">Dashboard / Pengajuan / Berita / Edit</p>
</div>

{{-- Form akan otomatis fetch data berdasarkan $id yang dilempar ke sini --}}
@include('dosen.berita._form', ['awal' => [], 'id' => $id])
@endsection