@extends('layouts.app')
@section('title', 'Edit Berita')

<<<<<<< HEAD
=======


>>>>>>> 3c94cf178bd27d7d854ce99570d8b90f0dfbe1f2
@section('content')
<div class="flex flex-col gap-1.5">
    <h1 class="text-3xl font-bold tracking-tight">Edit Berita</h1>
    <p class="text-2xl text-gray-400">Dashboard / Pengajuan / Berita / Edit</p>
</div>

<<<<<<< HEAD
{{-- Form akan otomatis fetch data berdasarkan $id yang dilempar ke sini --}}
@include('dosen.berita._form', ['awal' => [], 'id' => $id])
=======
    <div class="flex flex-col gap-1.5">
        <h1 class="text-3xl font-bold tracking-tight">Edit Berita</h1>
        <p class="text-2xl text-gray-400">Dashboard / Pengajuan / Berita / Edit</p>
    </div>



    @include('dosen.berita._form', ['idBerita' => (int) request()->route('id')])

>>>>>>> 3c94cf178bd27d7d854ce99570d8b90f0dfbe1f2
@endsection