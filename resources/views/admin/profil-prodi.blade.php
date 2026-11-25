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

    {{-- Isi halaman Profil Prodi menyusul. --}}
    <section class="mt-[26px] max-w-[1152px] rounded-lg border border-[#EFEFF1] bg-white p-[25px] shadow-[0_1px_3px_rgba(0,0,0,0.07)]">
        <p class="text-sm text-[#6E6E76]">Halaman ini sedang disiapkan.</p>
    </section>
</div>
@endsection
