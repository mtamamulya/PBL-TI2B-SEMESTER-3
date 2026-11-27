@extends($layout ?? 'layouts.admin')

@section('title', 'Profil Prodi')

@section('content')
<div class="px-6 pb-8 pt-4 md:px-8">
    <h1 class="text-[32px] font-bold leading-tight text-[#111]">Profil Prodi</h1>
    <nav aria-label="Breadcrumb" class="mt-[7px] text-xl leading-[26px] text-[#B9B9BF]">
        <a href="{{ route(($prefix ?? 'admin').'.dashboard') }}" class="transition-colors hover:text-[#226CFF] hover:underline">Dashboard</a>
        @if (($prefix ?? 'admin') === 'admin')
            <span class="mx-1">/</span>
            <span>Data Prodi</span>
        @endif
        <span class="mx-1">/</span>
        <span class="text-[#6E6E76]" aria-current="page">Profil Prodi</span>
    </nav>

    @if ($dummy ?? false)
        <p class="mt-4 max-w-[1152px] rounded-lg border border-[#F5D58A] bg-[#FFF8E6] px-4 py-2 text-sm text-[#8A5A00]">
            Mode dummy: data disimpan sementara di session, belum ke database.
        </p>
    @endif

    {{-- Tiap bagian = satu file sendiri (pembagian kerja Fase 2). Kirim $profil, $prefix. --}}
    <div class="mt-[26px] max-w-[1152px] space-y-6">
        @include('admin.profil-prodi._identitas')         {{-- Fase 2A --}}
        @include('admin.profil-prodi._visi-deskripsi')    {{-- Fase 2A --}}
        @include('admin.profil-prodi._daftar-poin')       {{-- Fase 2B --}}
        @include('admin.profil-prodi._kontak-sosial')     {{-- Fase 2A --}}
        @include('admin.profil-prodi._logo')              {{-- Fase 2B --}}
    </div>
</div>
@endsection
