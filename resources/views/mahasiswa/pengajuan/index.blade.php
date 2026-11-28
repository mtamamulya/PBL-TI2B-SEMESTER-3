@extends('layouts.pengajuan-app')
@section('title', 'Pengajuan')
@section('content')
@php
    $styles = [
        'Diajukan' => ['badge' => 'bg-blue-100 text-brand', 'note' => 'Pengajuan telah dikirim dan menunggu ditinjau.'],
        'Disetujui' => ['badge' => 'bg-emerald-50 text-emerald-700', 'note' => 'Pengajuan telah disetujui oleh tim administrasi.'],
        'Direview' => ['badge' => 'bg-amber-100 text-amber-800', 'note' => 'Pengajuan sedang direview oleh tim administrasi.'],
        'Selesai' => ['badge' => 'bg-gray-100 text-gray-600', 'note' => 'Pengajuan telah selesai diproses.'],
        'Ditolak' => ['badge' => 'bg-red-100 text-red-700', 'note' => 'Pengajuan ditolak oleh reviewer akademik.'],
        'Perlu revisi' => ['badge' => 'bg-orange-100 text-orange-700', 'note' => 'Pengajuan perlu diperbaiki sebelum diproses.'],
    ];
    $cards = [
        ['Total Pengajuan', $all->count(), false],
        ['Disetujui', $counts['Disetujui'] ?? 0, false],
        ['Direview', ($counts['Direview'] ?? 0) + ($counts['Diajukan'] ?? 0), false],
        ['Selesai', $counts['Selesai'] ?? 0, false],
    ];
    $guide = [
        'Pastikan semua dokumen pendukung sudah lengkap.',
        'Gunakan format PDF dengan ukuran maksimal 5 MB.',
        'Gunakan nama file yang jelas dan relevan.',
    ];
    $fmt = fn ($d) => \Carbon\Carbon::parse($d)->locale('id')->translatedFormat('d M Y');
    $rangeLabel = $from && $to ? $fmt($from).' - '.$fmt($to) : ($from ? 'Mulai '.$fmt($from) : ($to ? 'Sampai '.$fmt($to) : 'Semua tanggal'));
    $cur = $rows->currentPage();
    $last = $rows->lastPage();
    $pages = collect(range(1, $last))->filter(fn ($p) => $p === 1 || $p === $last || abs($p - $cur) <= 1);
    $btn = 'flex h-9 items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-700 hover:bg-gray-50';
    $dateInput = 'h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm outline-none focus:border-brand';
@endphp

<div class="flex flex-col gap-6 px-3 py-5 sm:px-5 sm:py-6 lg:px-8 lg:py-7">
    {{-- Judul halaman (Style Halaman Dosen) --}}
    <div class="flex flex-col gap-1.5">
        <h1 class="text-3xl font-bold tracking-tight text-gray-900">Pengajuan</h1>
        <p class="text-2xl text-gray-400">Ringkasan pengajuan kegiatan Anda</p>
    </div>

    {{-- Kartu ringkasan (Style Halaman Dosen) --}}
    <div class="flex flex-wrap gap-6">
        @foreach ($cards as [$label, $value, $alert])
            <div class="flex min-h-41 min-w-0 flex-[1_1_220px] flex-col justify-between rounded-lg bg-white p-3 shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <span class="text-xl font-medium text-gray-900">{{ $label }}</span>
                    <a href="{{ route('pengajuan.create') }}" aria-label="{{ $label }}" class="flex rounded-full text-gray-900 hover:text-brand">
                        <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="12" cy="12" r="9.5"/><path d="M9 15l6-6M10 9h5v5"/>
                        </svg>
                    </a>
                </div>
                <div class="text-5xl leading-none font-bold text-gray-900 {{ $alert ? 'text-red-600' : '' }}">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    {{-- Daftar pengajuan (Style Halaman Dosen) --}}
    <section class="flex flex-col gap-5 rounded-lg bg-white p-4 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-medium text-gray-900">Daftar pengajuan</h2>
            <a href="{{ route('pengajuan.create') }}" class="rounded-lg bg-blue-100 px-4 py-2 text-[15px] font-medium text-brand hover:bg-blue-200">Tambah pengajuan</a>
        </div>

        <form method="GET" action="{{ route('pengajuan.index') }}" class="flex flex-wrap items-end gap-4 xl:flex-nowrap">
            <label class="flex w-full min-w-0 flex-col gap-2 text-sm font-medium text-gray-700 md:min-w-[260px] md:flex-1">
                Cari pengajuan
                <div class="flex min-h-[42px] items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 focus-within:border-brand">
                    <input name="q" value="{{ $q }}" data-live-search placeholder="Cari jenis atau judul pengajuan" class="min-w-0 flex-1 bg-transparent text-sm font-normal text-gray-900 outline-none placeholder:text-gray-400">
                    <i aria-hidden="true" class="ti ti-search text-base text-gray-400"></i>
                </div>
            </label>

            {{-- STATUS (dropdown kustom) --}}
            <div x-data="{
                     open: false,
                     value: @js($status),
                     options: @js(array_merge([''], array_keys($styles))),
                     pick(v) {
                         this.value = v;
                         this.open = false;
                         this.$nextTick(() => this.$refs.status.form.submit());
                     }
                 }"
                 @click.outside="open = false" @keydown.escape="open = false"
                 class="relative flex w-full flex-col gap-2 text-sm font-medium text-gray-700 sm:w-[200px] sm:shrink-0">
                Status
                <input type="hidden" name="status" x-ref="status" :value="value">
                <button type="button" @click="open = !open"
                        :class="open ? 'border-brand' : 'border-gray-300'"
                        class="flex min-h-[42px] w-full items-center gap-2 rounded-lg border bg-white px-3 text-left transition">
                    <span class="min-w-0 flex-1 truncate text-sm font-normal" :class="value ? 'text-gray-900' : 'text-gray-500'" x-text="value || 'Semua status'"></span>
                    <i class="ti ti-chevron-down text-base text-gray-400 transition-transform duration-200" :class="open ? 'rotate-180' : ''"></i>
                </button>
                <div x-show="open" x-cloak
                     x-transition:enter="transition ease-out duration-100"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100"
                     class="absolute left-0 top-full z-30 mt-2 flex w-full min-w-[200px] flex-col gap-0.5 rounded-lg border border-gray-300 bg-white p-1.5 shadow-sm">
                    <template x-for="o in options" :key="o">
                        <button type="button" @click="pick(o)"
                                :class="value === o ? 'bg-blue-100 font-medium text-brand' : 'text-gray-900 hover:bg-gray-100'"
                                class="h-9 rounded-lg px-3 text-left text-sm font-normal transition" x-text="o || 'Semua status'"></button>
                    </template>
                </div>
            </div>

            {{-- RENTANG TANGGAL --}}
            <div x-data="{ open: false }" @click.outside="open = false" class="relative flex w-full flex-col gap-2 text-sm font-medium text-gray-700 sm:w-[260px] sm:shrink-0">
                Rentang tanggal
                <button type="button" @click="open = !open" class="flex min-h-[42px] w-full items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 text-left">
                    <span class="min-w-0 flex-1 truncate text-sm font-normal {{ $from || $to ? 'text-gray-900' : 'text-gray-400' }}">{{ $rangeLabel }}</span>
                    <i class="ti ti-calendar text-base text-gray-400"></i>
                </button>
                <div x-show="open" x-cloak class="absolute left-0 top-full z-30 mt-2 flex w-full min-w-[260px] flex-col gap-3 rounded-lg border border-gray-300 bg-white p-4 shadow-sm sm:w-[320px]">
                    <label class="flex flex-col gap-1.5 text-sm font-medium text-gray-700">Dari
                        <input type="date" name="from" x-ref="from" value="{{ $from }}" class="{{ $dateInput }}">
                    </label>
                    <label class="flex flex-col gap-1.5 text-sm font-medium text-gray-700">Sampai
                        <input type="date" name="to" x-ref="to" value="{{ $to }}" class="{{ $dateInput }}">
                    </label>
                    <div class="flex items-center justify-end gap-2">
                        <button type="button" @click="$refs.from.value = ''; $refs.to.value = ''" class="h-9 rounded-lg border border-gray-300 px-3 text-sm font-medium text-gray-700 hover:bg-gray-50">Hapus</button>
                        <button type="submit" class="h-9 rounded-lg bg-brand px-4 text-sm font-medium text-white hover:bg-blue-700">Terapkan</button>
                    </div>
                </div>
            </div>

            <a href="{{ route('pengajuan.index') }}" class="flex h-[42px] w-full items-center justify-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-medium text-brand hover:bg-gray-50 sm:w-auto sm:shrink-0">Reset</a>
        </form>

        {{-- Tampilan Mobile --}}
        <div class="flex flex-col gap-3 lg:hidden">
            @forelse ($rows as $r)
                @php $st = $styles[$r['status']] ?? $styles['Direview']; @endphp
                <article class="rounded-md border border-gray-300 bg-white p-3">
                    <div class="flex items-start gap-3">
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-semibold text-brand">{{ $rows->firstItem() + $loop->index }}</span>
                        <div class="min-w-0 flex-1">
                            <h3 class="break-words text-sm font-medium text-gray-900">{{ $r['title'] }}</h3>
                            <p class="mt-0.5 text-xs text-gray-500">{{ $r['category'] }} <span aria-hidden="true">·</span> {{ $r['submitted_at']->copy()->locale('id')->translatedFormat('d M Y') }}</p>
                            <span class="mt-2 inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $st['badge'] }}">{{ $r['status'] }}</span>
                            <p class="mt-1 text-xs leading-5 text-gray-500">{{ $st['note'] }}</p>
                        </div>
                    </div>
                    <div class="mt-3 flex items-center justify-end gap-2 border-t border-gray-300 pt-2">
                        @if ($r['editable'])
                            <a href="{{ route('pengajuan.edit', $r['id']) }}" class="flex h-9 items-center gap-1.5 rounded-lg bg-blue-100 px-3 text-xs font-medium text-brand hover:bg-blue-200">
                                <i class="ti ti-pencil"></i>Edit
                            </a>
                        @endif
                        <a href="{{ route('pengajuan.show', $r['id']) }}" class="flex h-9 items-center gap-1.5 rounded-lg bg-blue-100 px-3 text-xs font-medium text-brand hover:bg-blue-200">
                            Lihat detail<i class="ti ti-chevron-right"></i>
                        </a>
                    </div>
                </article>
            @empty
                <div class="rounded-md border border-gray-300 bg-white px-4 py-8 text-center text-sm text-gray-500">Tidak ada pengajuan.</div>
            @endforelse
        </div>

        {{-- Tabel Desktop (Style Halaman Dosen) --}}
        <div class="hidden overflow-x-auto rounded-md border border-gray-300 lg:block">
            <table class="w-full min-w-[860px] border-collapse text-sm">
                <thead>
                    <tr class="bg-gray-100 text-left">
                        <th scope="col" class="border-b border-gray-300 px-3 py-2.5 font-medium text-gray-900">Nama pengajuan / jenis dokumen</th>
                        <th scope="col" class="w-32 border-b border-gray-300 px-3 py-2.5 font-medium text-gray-900">Tanggal</th>
                        <th scope="col" class="w-72 border-b border-gray-300 px-3 py-2.5 font-medium text-gray-900">Status Pengajuan</th>
                        <th scope="col" class="w-36 border-b border-gray-300 px-3 py-2.5 text-right font-medium text-gray-900">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $r)
                        @php $st = $styles[$r['status']] ?? $styles['Direview']; @endphp
                        <tr class="border-b border-gray-300 last:border-b-0">
                            <td class="px-3 py-2.5">
                                <div class="flex items-center gap-3">
                                    <div class="flex size-8 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-semibold text-brand">{{ $rows->firstItem() + $loop->index }}</div>
                                    <div class="flex flex-col gap-0.5">
                                        <div class="font-medium text-gray-900">{{ $r['title'] }}</div>
                                        <div class="text-xs text-gray-500">{{ $r['category'] }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-2.5 text-gray-700">{{ $r['submitted_at']->copy()->locale('id')->translatedFormat('d M Y') }}</td>
                            <td class="px-3 py-2.5">
                                <div class="flex flex-col items-start gap-1">
                                    <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $st['badge'] }}">{{ $r['status'] }}</span>
                                    <span class="text-xs text-gray-500">{{ $st['note'] }}</span>
                                </div>
                            </td>
                            <td class="px-3 py-2.5">
                                <div class="flex items-center justify-end gap-2">
                                    @if ($r['editable'])
                                        <a href="{{ route('pengajuan.edit', $r['id']) }}" title="Edit pengajuan" aria-label="Edit pengajuan"
                                           class="flex size-10 items-center justify-center rounded-lg bg-blue-100 text-brand hover:bg-blue-200">
                                            <i class="ti ti-pencil text-lg"></i>
                                        </a>
                                    @else
                                        <span title="Pengajuan dengan status {{ $r['status'] }} tidak dapat diedit" aria-disabled="true"
                                              class="flex size-10 cursor-not-allowed items-center justify-center rounded-lg bg-gray-100 text-gray-400">
                                            <i class="ti ti-pencil text-lg"></i>
                                        </span>
                                    @endif
                                    <a href="{{ route('pengajuan.show', $r['id']) }}" aria-label="Lihat detail {{ $r['title'] }}"
                                       class="flex size-10 items-center justify-center rounded-lg bg-blue-100 text-brand hover:bg-blue-200">
                                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3"/>
                                        </svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-gray-500">Tidak ada pengajuan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Panduan Pengajuan --}}
        <div class="rounded-md border border-gray-300 bg-white p-3.5">
            <div class="mb-3 text-base font-medium text-gray-900">Panduan Pengajuan</div>
            <div class="flex flex-col gap-2.5">
                @foreach ($guide as $g)
                    <div class="flex items-start gap-2 text-sm text-gray-600">
                        <span class="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded border border-gray-400 text-brand">
                            <i class="ti ti-check text-[10px]"></i>
                        </span>
                        {{ $g }}
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Paginasi --}}
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="text-sm text-gray-500">{{ $rows->count() }} pengajuan ditampilkan • Total {{ $all->count() }} pengajuan</div>
            <nav class="flex flex-wrap items-center gap-2" aria-label="Halaman">
                @if ($rows->onFirstPage())
                    <span class="flex h-9 items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-gray-50 px-3 text-sm text-gray-400"><i class="ti ti-chevron-left"></i><span class="hidden sm:inline">Sebelumnya</span></span>
                @else
                    <a href="{{ $rows->previousPageUrl() }}" class="{{ $btn }}"><i class="ti ti-chevron-left"></i><span class="hidden sm:inline">Sebelumnya</span></a>
                @endif

                @php $prev = 0; @endphp
                @foreach ($pages as $p)
                    @if ($p - $prev > 1)<span class="px-1 text-gray-400">…</span>@endif
                    <a href="{{ $rows->url($p) }}" class="flex size-9 items-center justify-center rounded-lg text-sm font-medium {{ $p === $cur ? 'bg-brand text-white' : 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50' }}">{{ $p }}</a>
                    @php $prev = $p; @endphp
                @endforeach

                @if ($rows->hasMorePages())
                    <a href="{{ $rows->nextPageUrl() }}" class="{{ $btn }}"><span class="hidden sm:inline">Berikutnya</span><i class="ti ti-chevron-right"></i></a>
                @else
                    <span class="flex h-9 items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-gray-50 px-3 text-sm text-gray-400"><span class="hidden sm:inline">Berikutnya</span><i class="ti ti-chevron-right"></i></span>
                @endif
            </nav>
        </div>
    </section>
</div>
@endsection