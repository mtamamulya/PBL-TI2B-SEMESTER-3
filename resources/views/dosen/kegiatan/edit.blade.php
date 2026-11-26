@extends('layouts.app')
@section('title', 'Edit Kegiatan')
@section('content')
    <h1 class="text-3xl font-bold">Edit Kegiatan</h1>
    <p>{{ $kegiatan->kategori->nama_kategori }}</p>
    @if ($kegiatan->statusTerkini?->catatan_revisi)
        <p class="rounded-md bg-orange-50 p-3">{{ $kegiatan->statusTerkini->catatan_revisi }}</p>
    @endif
    <form action="{{ route('dosen.kegiatan.update', $kegiatan) }}" method="POST" class="grid gap-5 rounded-lg bg-white p-5 shadow-sm">
        @csrf
        @method('PUT')
        @if ($errors->any())
            <ul role="alert" class="text-red-700">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        @endif
        <label class="grid gap-2">Nama kegiatan
            <input name="nama_kegiatan" required maxlength="255" class="rounded-lg border p-3" value="{{ old('nama_kegiatan', $kegiatan->nama_kegiatan) }}">
        </label>
        @foreach ($fields as $field)
            <label class="grid gap-2">{{ ucfirst(str_replace('_', ' ', $field->input)) }}
                <input name="detail[{{ $field->getKey() }}]" class="rounded-lg border p-3"
                       type="{{ match ($field->jenis_input) { 'tanggal' => 'date', 'angka', 'rupiah' => 'number', default => 'text' } }}"
                       @if (in_array($field->jenis_input, ['angka', 'rupiah'])) step="any" @endif
                       value="{{ old('detail.'.$field->getKey(), $kegiatan->detail->firstWhere('id_isian_kategori', $field->getKey())?->isian) }}">
            </label>
        @endforeach
        <div class="flex items-center justify-end gap-4">
            <a href="{{ route('dosen.kegiatan.show', $kegiatan) }}">Batal</a>
            <button type="submit" class="rounded-lg bg-brand px-4 py-2 text-white">Simpan</button>
        </div>
    </form>
    @if ($kegiatan->statusTerkini?->status === 'direvisi')
        <a href="{{ route('pengajuan.dosen.show', $kegiatan) }}">Buka pengajuan untuk mengajukan ulang</a>
    @endif
@endsection
