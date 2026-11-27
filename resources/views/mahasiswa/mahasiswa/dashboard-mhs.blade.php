@extends('layouts.mahasiswa-tim')

@section('title', 'Dashboard Mahasiswa')

@section('content')
@php
    $jam     = now()->hour;
    $sapaan  = match (true) { $jam < 11 => 'pagi', $jam < 15 => 'siang', $jam < 18 => 'sore', default => 'malam' };
    $depan   = explode(' ', trim($nama))[0];
    $persen  = $ringkasan['sks_total'] > 0 ? round($ringkasan['sks_tempuh'] / $ringkasan['sks_total'] * 100) : 0;
    $selectCls = 'h-10 w-full appearance-none rounded-lg border border-gray-300 bg-white pl-3.5 pr-9 text-sm font-normal text-gray-800 outline-none transition focus:border-brand';

    $kartuCls  = 'flex min-h-[152px] min-w-0 flex-[1_1_220px] flex-col justify-between rounded-lg border border-gray-200/80 bg-white p-4 shadow-sm';
    $judulCls  = 'text-[15px] font-semibold tracking-tight text-gray-800';
    $angkaCls  = 'text-4xl font-bold tracking-tight text-gray-900 tabular-nums leading-none';
    $satuanCls = 'text-xs font-medium text-gray-500';
    $ketCls    = 'text-xs text-gray-400';
@endphp

<div class="flex flex-col gap-6">

    {{-- Header Halaman --}}
    <div class="flex flex-col gap-1">
        <h1 class="text-[28px] font-bold tracking-tight text-gray-900 leading-tight">Dashboard Mahasiswa</h1>
        <p class="text-base font-normal text-gray-400">Ringkasan kegiatan akademik Anda</p>
    </div>

    {{-- Kartu Statistik --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {{-- Tahun Akademik --}}
        <div class="{{ $kartuCls }}">
            <div class="flex items-center justify-between gap-2">
                <span class="{{ $judulCls }}">Tahun Akademik</span>
                <span class="flex size-7 items-center justify-center rounded-full text-gray-700" aria-hidden="true">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="9.5"/><path d="M9 15l6-6M10 9h5v5"/>
                    </svg>
                </span>
            </div>
            <div class="flex flex-col gap-1.5 pt-3">
                <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                    <span class="text-[28px] font-bold tracking-tight text-gray-900 tabular-nums leading-none">{{ $ringkasan['tahun_akademik'] }}</span>
                    <span class="{{ $satuanCls }}">{{ $ringkasan['jenis_semester'] }}</span>
                </div>
                <span class="{{ $ketCls }}">Semester berjalan</span>
            </div>
        </div>

        {{-- SKS Ditempuh --}}
        <div class="{{ $kartuCls }}">
            <div class="flex items-center justify-between gap-2">
                <span class="{{ $judulCls }}">SKS Ditempuh</span>
                <span class="flex size-7 items-center justify-center rounded-full text-gray-700" aria-hidden="true">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="9.5"/><path d="M9 15l6-6M10 9h5v5"/>
                    </svg>
                </span>
            </div>
            <div class="flex flex-col gap-2 pt-3">
                <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                    <span class="{{ $angkaCls }}">{{ $ringkasan['sks_tempuh'] }}</span>
                    <span class="{{ $satuanCls }}">Dari {{ $ringkasan['sks_total'] }} SKS kelulusan</span>
                </div>
                <div class="h-1.5 w-full overflow-hidden rounded-full bg-gray-100" role="progressbar" aria-valuenow="{{ $persen }}" aria-valuemin="0" aria-valuemax="100">
                    <div class="h-full rounded-full bg-brand transition-all duration-300" style="width: {{ $persen }}%"></div>
                </div>
                <span class="{{ $ketCls }}">{{ $persen }}% perjalanan studi</span>
            </div>
        </div>

        {{-- Semester Berjalan --}}
        <div class="{{ $kartuCls }}">
            <div class="flex items-center justify-between gap-2">
                <span class="{{ $judulCls }}">Semester Berjalan</span>
                <span class="flex size-7 items-center justify-center rounded-full text-gray-700" aria-hidden="true">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="9.5"/><path d="M9 15l6-6M10 9h5v5"/>
                    </svg>
                </span>
            </div>
            <div class="flex flex-col gap-1.5 pt-3">
                <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                    <span class="{{ $angkaCls }}">{{ $ringkasan['semester_ke'] }}</span>
                    <span class="{{ $satuanCls }}">{{ $ringkasan['jenis_semester'] }} • {{ $ringkasan['tahun_akademik'] }}</span>
                </div>
                <span class="{{ $ketCls }}">{{ $ringkasan['jumlah_mk'] }} mata kuliah • {{ $ringkasan['sks_semester'] }} SKS</span>
            </div>
        </div>

        {{-- Pengajuan Aktif --}}
        <div class="{{ $kartuCls }}">
            <div class="flex items-center justify-between gap-2">
                <span class="{{ $judulCls }}">Pengajuan Aktif</span>
                <a href="{{ route('pengajuan.index') }}" aria-label="Pengajuan Aktif" class="flex size-7 items-center justify-center rounded-full text-gray-700 transition hover:bg-blue-50 hover:text-brand">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="9.5"/><path d="M9 15l6-6M10 9h5v5"/>
                    </svg>
                </a>
            </div>
            <div class="flex flex-col gap-1.5 pt-3">
                <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                    <span class="{{ $angkaCls }}">{{ $ringkasan['pengajuan_aktif'] }}</span>
                    <span class="{{ $satuanCls }}">Sedang diverifikasi</span>
                </div>
                <span class="{{ $ketCls }}">{{ $ringkasan['dokumen_siap'] }} dokumen siap diunduh</span>
            </div>
        </div>
    </div>

    {{-- Filter dan Tabel Mata Kuliah --}}
    <section class="flex flex-col gap-4 rounded-lg border border-gray-200/80 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold tracking-tight text-gray-900">Daftar Mata Kuliah</h2>
        </div>

        <form method="GET" action="{{ url()->current() }}" class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-[1fr_175px_175px_175px_auto]">
            <div class="flex h-10 items-center gap-2.5 rounded-lg border border-gray-300 bg-white px-3.5 transition focus-within:border-brand sm:col-span-2 xl:col-span-1">
                <svg class="size-4 shrink-0 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M10 10m-7 0a7 7 0 1 0 14 0a7 7 0 1 0-14 0"/><path d="M21 21l-6-6"/></svg>
                <input type="search" name="q" value="{{ $filter['q'] }}" data-live-search placeholder="Cari mata kuliah atau dosen..."
                       class="min-w-0 flex-1 bg-transparent text-sm font-normal text-gray-900 outline-none placeholder:text-gray-400">
            </div>

            @foreach ([
                ['semester', 'Semua Semester', ['Ganjil' => 'Ganjil', 'Genap' => 'Genap']],
                ['tingkat',  'Semua Tingkat',  [1 => 'Tingkat 1', 2 => 'Tingkat 2', 3 => 'Tingkat 3', 4 => 'Tingkat 4']],
                ['tahun',    'Tahun Akademik', $tahunList],
            ] as [$field, $placeholder, $options])
                <div class="relative">
                    <select name="{{ $field }}" class="{{ $selectCls }}" onchange="this.form.submit()">
                        <option value="">{{ $placeholder }}</option>
                        @foreach ($options as $val => $text)
                            <option value="{{ $val }}" @selected((string) $filter[$field] === (string) $val)>{{ $text }}</option>
                        @endforeach
                    </select>
                    <svg class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6l6-6"/></svg>
                </div>
            @endforeach

            <a href="{{ url()->current() }}"
               class="flex h-10 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-medium text-brand transition hover:bg-blue-50/70">
                Reset
            </a>
            <button type="submit" class="sr-only">Terapkan</button>
        </form>

        <div class="hidden overflow-x-auto rounded-lg border border-gray-200 md:block">
            <table class="w-full min-w-[760px] table-fixed border-collapse text-left text-sm">
                <colgroup>
                    <col class="w-[30%]">
                    <col class="w-[26%]">
                    <col class="w-[14%]">
                    <col class="w-[14%]">
                    <col class="w-[16%]">
                </colgroup>
                <thead>
                    <tr class="border-b border-gray-200 bg-gray-50/90 text-xs font-semibold tracking-wide text-gray-600 uppercase">
                        <th scope="col" class="px-4 py-3">Mata Kuliah</th>
                        <th scope="col" class="px-4 py-3">Dosen Pengampu</th>
                        <th scope="col" class="px-4 py-3">Tingkat</th>
                        <th scope="col" class="px-4 py-3">Semester</th>
                        <th scope="col" class="px-4 py-3">Tahun Akademik</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($mataKuliah as $mk)
                        <tr class="transition-colors hover:bg-gray-50/70">
                            <td class="truncate px-4 py-3 font-medium text-gray-900" title="{{ $mk['mk'] }}">{{ $mk['mk'] }}</td>
                            <td class="truncate px-4 py-3 text-gray-600" title="{{ $mk['dosen'] }}">{{ $mk['dosen'] }}</td>
                            <td class="px-4 py-3 text-gray-600">Tingkat {{ $mk['tingkat'] }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $mk['semester'] }}</td>
                            <td class="px-4 py-3 text-gray-600 tabular-nums">{{ $mk['tahun'] ?? ($mk['tahun_akademik'] ?? '—') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-sm text-gray-400">Tidak ada mata kuliah yang cocok dengan filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex flex-col gap-3 md:hidden">
            @forelse ($mataKuliah as $mk)
                <article class="flex min-w-0 flex-col gap-3 rounded-lg border border-gray-200 bg-white p-3.5">
                    <div class="min-w-0">
                        <h3 class="break-words text-sm font-semibold text-gray-900">{{ $mk['mk'] }}</h3>
                        <p class="mt-0.5 break-words text-xs text-gray-500">{{ $mk['dosen'] }}</p>
                    </div>
                    <dl class="grid grid-cols-2 gap-x-3 gap-y-2 border-t border-gray-200 pt-2.5 text-xs">
                        <div>
                            <dt class="text-gray-400">Tingkat</dt>
                            <dd class="mt-0.5 font-medium text-gray-800">Tingkat {{ $mk['tingkat'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-400">Semester</dt>
                            <dd class="mt-0.5 font-medium text-gray-800">{{ $mk['semester'] }}</dd>
                        </div>
                        <div class="col-span-2">
                            <dt class="text-gray-400">Tahun Akademik</dt>
                            <dd class="mt-0.5 font-medium text-gray-800 tabular-nums">{{ $mk['tahun'] ?? ($mk['tahun_akademik'] ?? '—') }}</dd>
                        </div>
                    </dl>
                </article>
            @empty
                <p class="rounded-lg border border-gray-200 px-4 py-8 text-center text-sm text-gray-400">Tidak ada mata kuliah yang cocok dengan filter.</p>
            @endforelse
        </div>

        {{ $mataKuliah->links('mahasiswa.mahasiswa.pagination.mahasiswa') }}
    </section>
</div>
@endsection