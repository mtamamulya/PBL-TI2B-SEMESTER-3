@extends('layouts.pengajuan-app')
@section('title', 'Pengajuan Berita')
@section('content')
@php
    $statuses = [
        'Semua' => null,
        'Draft' => 'Draft',
        'Diajukan' => 'Diajukan',
        'Direvisi' => 'Direvisi',
        'Dipublikasikan' => 'Dipublikasikan',
        'Ditolak' => 'Ditolak',
    ];
    $badges = [
        'Draft' => 'bg-gray-100 text-gray-600',
        'Diajukan' => 'bg-blue-100 text-brand',
        'Direvisi' => 'bg-orange-100 text-orange-700',
        'Dipublikasikan' => 'bg-emerald-50 text-emerald-700',
        'Ditolak' => 'bg-red-100 text-red-700',
    ];
@endphp

<div class="flex flex-col gap-6 px-3 py-5 sm:px-5 sm:py-6 lg:px-8 lg:py-7">
    {{-- Judul halaman (Style Halaman Kegiatan Mahasiswa) --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex flex-col gap-1.5">
            <h1 class="text-3xl font-bold tracking-tight text-gray-900">Pengajuan Berita</h1>
            <p class="text-2xl text-gray-400">Kelola berita kegiatan dan informasi program studi.</p>
        </div>
        <a href="{{ route('pengajuan.berita.create') }}"
           class="mt-1 flex items-center gap-2 rounded-lg bg-blue-100 px-4 py-2 text-[15px] font-medium text-brand hover:bg-blue-200">
            <i class="ti ti-plus text-base"></i>Ajukan Berita
        </a>
    </div>

    @if (session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</div>
    @endif

    {{-- Kontainer Utama (Style Halaman Kegiatan Mahasiswa) --}}
    <section class="flex flex-col gap-5 rounded-lg bg-white p-4 shadow-sm">
        {{-- Tab Status --}}
        <div class="flex flex-wrap gap-5 overflow-x-auto border-b border-gray-300">
            @foreach ($statuses as $label => $value)
                <a href="{{ route('pengajuan.berita.index', array_filter(['status' => $value, 'q' => $query])) }}"
                   class="flex shrink-0 items-center gap-2 border-b-2 px-1 pb-3 text-sm {{ $status === $value ? 'border-brand font-medium text-brand' : 'border-transparent text-gray-500 hover:text-gray-900' }}">
                    {{ $label }}
                    <span class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-brand">{{ $value ? ($counts[$value] ?? 0) : $counts->sum() }}</span>
                </a>
            @endforeach
        </div>

        {{-- Pencarian --}}
        <form method="GET" action="{{ route('pengajuan.berita.index') }}">
            @if ($status)<input type="hidden" name="status" value="{{ $status }}">@endif
            <label class="flex min-h-[42px] items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-500 focus-within:border-brand">
                <i class="ti ti-search text-base text-gray-400"></i>
                <input name="q" value="{{ $query }}" data-live-search placeholder="Cari judul atau kategori berita..." class="min-w-0 flex-1 bg-transparent text-sm font-normal text-gray-900 outline-none placeholder:text-gray-400">
            </label>
        </form>

        {{-- Tabel (Style Halaman Kegiatan Mahasiswa) --}}
        <div class="overflow-x-auto rounded-md border border-gray-300">
            <table class="w-full min-w-[720px] border-collapse text-left text-sm">
                <thead>
                    <tr class="bg-gray-100 text-left">
                        <th scope="col" class="border-b border-gray-300 px-3 py-2.5 font-medium text-gray-900">Judul Berita</th>
                        <th scope="col" class="border-b border-gray-300 px-3 py-2.5 font-medium text-gray-900">Kategori</th>
                        <th scope="col" class="border-b border-gray-300 px-3 py-2.5 font-medium text-gray-900">Tanggal Diajukan</th>
                        <th scope="col" class="border-b border-gray-300 px-3 py-2.5 font-medium text-gray-900">Status</th>
                        <th scope="col" class="border-b border-gray-300 px-3 py-2.5 text-right font-medium text-gray-900">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr class="border-b border-gray-300 last:border-b-0">
                            <td class="max-w-[260px] truncate px-3 py-2.5 font-medium text-gray-900">{{ $item['title'] ?: 'Tanpa judul' }}</td>
                            <td class="px-3 py-2.5 text-gray-700">{{ $item['category'] ?: '—' }}</td>
                            <td class="px-3 py-2.5 text-gray-700">{{ \Carbon\Carbon::parse($item['submitted_at'])->locale('id')->translatedFormat('d F Y') }}</td>
                            <td class="px-3 py-2.5">
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badges[$item['status']] ?? 'bg-gray-100 text-gray-600' }}">{{ $item['status'] }}</span>
                            </td>
                            <td class="px-3 py-2.5">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('pengajuan.berita.show', $item['id']) }}" aria-label="Lihat berita"
                                       class="flex size-10 items-center justify-center rounded-lg bg-blue-100 text-brand hover:bg-blue-200">
                                        <i class="ti ti-eye text-lg"></i>
                                    </a>
                                    @if (in_array($item['status'], ['Draft', 'Direvisi'], true))
                                        <a href="{{ route('pengajuan.berita.edit', $item['id']) }}" aria-label="Edit berita"
                                           class="flex size-10 items-center justify-center rounded-lg bg-blue-100 text-brand hover:bg-blue-200">
                                            <i class="ti ti-pencil text-lg"></i>
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-gray-500">Belum ada pengajuan berita.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection