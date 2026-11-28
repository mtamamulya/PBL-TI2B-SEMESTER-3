@extends('layouts.app')

@section('title', 'Detail Pengabdian')

@section('content')

@php
    // $k = data dari DosenPengabdianController (API GET /dosen/pengabdian/{id}).
    $tgl = function ($t) {
        if (! $t) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($t)->format('d/m/Y');
        } catch (\Throwable) {
            return $t;
        }
    };
    $rupiah = fn ($v) => 'Rp '.number_format((int) $v, 0, ',', '.');
    $nilai = fn ($d) => match ($d['jenis_input'] ?? null) {
        'rupiah' => $rupiah($d['isian'] ?? 0),
        'tanggal' => $tgl($d['isian'] ?? null),
        default => $d['isian'] ?? null,
    };

    $isian = $k['detail'] ?? [];
    $anggota = $k['anggota'] ?? [];
    $dokumen = $k['dokumen'] ?? [];
    $timeline = $k['timeline'] ?? [];
    $belumLengkap = $k['belum_lengkap'] ?? [];

    // Data umum kegiatan. Yang kosong atau sudah muncul sebagai isian dilewati.
    $labelIsian = collect($isian)->pluck('input')->map(fn ($s) => mb_strtolower((string) $s))->all();
    $umum = array_filter([
        'Nama kegiatan' => $k['nama_kegiatan'] ?? null,
        'Status' => isset($k['status']) ? ucfirst($k['status']) : null,
        'Tanggal mulai' => $tgl($k['tanggal_mulai'] ?? null),
        'Tanggal selesai' => $tgl($k['tanggal_selesai'] ?? null),
        'Lokasi' => $k['lokasi'] ?? null,
        'Nomor SK penugasan' => $k['nomor_sk'] ?? null,
        'Tanggal SK penugasan' => $tgl($k['tanggal_sk'] ?? null),
        'Deskripsi' => $k['deskripsi'] ?? null,
    ], fn ($v, $label) => $v !== null && $v !== '' && ! in_array(mb_strtolower($label), $labelIsian, true), ARRAY_FILTER_USE_BOTH);

    $sectionClass = 'flex flex-col gap-4.5 rounded-lg border border-gray-200 bg-white p-4.5';
    $numClass     = 'flex size-[26px] shrink-0 items-center justify-center rounded-full bg-brand text-[13px] font-semibold text-white';
@endphp

    {{-- Judul halaman + tombol kembali --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex flex-col gap-1.5">
            <h1 class="text-3xl font-bold tracking-tight">Detail Pengabdian</h1>
            <p class="text-2xl text-gray-400">Dashboard / Pengabdian / Detail Pengabdian</p>
        </div>
        <a href="{{ route('pengabdian.index') }}"
           class="mt-2 flex h-11 items-center gap-2 rounded-md bg-blue-100 px-4 text-base font-medium text-brand hover:bg-blue-200">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg>
            Kembali
        </a>
    </div>

    @if (! empty($belumLengkap))
        <p role="alert" class="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-700">
            Isian wajib yang belum dijawab: {{ collect($belumLengkap)->pluck('input')->implode(', ') }}.
        </p>
    @endif

    {{-- Halaman ini hanya untuk membaca, jadi tidak ada formulir atau tombol simpan --}}
    <div class="flex flex-col gap-5 rounded-lg bg-white p-4.5 shadow-sm">

        {{-- 1. INFORMASI PENGABDIAN --}}
        <section class="{{ $sectionClass }}" aria-labelledby="h-info">
            <div class="flex items-center gap-2.5">
                <span class="{{ $numClass }}">1</span>
                <h2 id="h-info" class="text-[15px] font-semibold">Informasi Pengabdian</h2>
            </div>

            <dl class="grid grid-cols-1 gap-4.5 md:grid-cols-2">
                @foreach ($umum as $label => $isi)
                    <div class="{{ in_array($label, ['Nama kegiatan', 'Deskripsi'], true) ? 'md:col-span-2' : '' }}">
                        <x-detail-field :label="$label" :value="$isi" />
                    </div>
                @endforeach
            </dl>
        </section>

        {{-- 2. ISIAN PENGABDIAN (label dan nilai dari kategori, jadi mengikuti definisi isian) --}}
        <section class="{{ $sectionClass }}" aria-labelledby="h-isian">
            <div class="flex items-center gap-2.5">
                <span class="{{ $numClass }}">2</span>
                <h2 id="h-isian" class="text-[15px] font-semibold">Isian Pengabdian</h2>
            </div>

            @if (count($isian) > 0)
                <dl class="grid grid-cols-1 gap-4.5 md:grid-cols-2">
                    @foreach ($isian as $d)
                        <x-detail-field :label="$d['input']" :value="$nilai($d)" />
                    @endforeach
                </dl>
            @else
                <p class="rounded-lg border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500">Belum ada isian.</p>
            @endif
        </section>

        {{-- 3. ANGGOTA PENGABDIAN --}}
        <section class="{{ $sectionClass }}" aria-labelledby="h-anggota">
            <div class="flex items-center gap-2.5">
                <span class="{{ $numClass }}">3</span>
                <h2 id="h-anggota" class="text-[15px] font-semibold">Anggota Pengabdian</h2>
            </div>

            <div class="overflow-x-auto rounded-md border border-gray-300">
                <table class="w-full min-w-[480px] border-collapse text-sm">
                    <thead>
                        <tr class="bg-gray-100 text-left">
                            <th scope="col" class="w-1/2 px-2.5 py-2.5 font-medium">Nama</th>
                            <th scope="col" class="w-1/2 px-2.5 py-2.5 font-medium">Peran</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($anggota as $a)
                            <tr class="border-t border-gray-300">
                                <td class="px-2.5 py-2.5">{{ $a['nama'] ?? '-' }}</td>
                                <td class="px-2.5 py-2.5">{{ ucfirst($a['peran'] ?? '-') }}</td>
                            </tr>
                        @empty
                            <tr class="border-t border-gray-300">
                                <td colspan="2" class="px-2.5 py-4 text-center text-gray-500">Belum ada anggota.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- 4. DOKUMEN PENDUKUNG: tautan dibuka di tab baru. Dokumen diubah di halaman Edit. --}}
        <section class="{{ $sectionClass }}" aria-labelledby="h-dokumen">
            <div class="flex items-center gap-2.5">
                <span class="{{ $numClass }}">4</span>
                <h2 id="h-dokumen" class="text-[15px] font-semibold">Dokumen Pendukung</h2>
            </div>

            @forelse ($dokumen as $d)
                @php $tautan = $d['link_dokumen'] ?? null; @endphp
                <div class="overflow-hidden rounded-lg border border-gray-200">
                    <div class="bg-[#02215c] px-3.5 py-3 text-white">
                        <h3 class="text-sm font-semibold">Dokumen {{ $loop->iteration }}</h3>
                    </div>

                    <div class="flex flex-col gap-4 p-3.5">
                        <div class="flex flex-col gap-2">
                            <span class="text-[15px] font-medium">Tautan dokumen</span>
                            @if ($tautan && preg_match('#^https?://#i', $tautan))
                                <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-gray-200 bg-gray-50 p-3">
                                    <span class="min-w-0 truncate text-[15px]" title="{{ $tautan }}">{{ $tautan }}</span>
                                    <a href="{{ $tautan }}" target="_blank" rel="noopener noreferrer"
                                       class="flex h-9 items-center gap-1.5 rounded-lg bg-brand px-3.5 text-sm font-medium text-white hover:bg-blue-700">
                                        Buka dokumen
                                        <span class="sr-only">(dibuka di tab baru)</span>
                                    </a>
                                </div>
                            @else
                                <p class="rounded-lg border border-dashed border-gray-300 px-4 py-3 text-sm text-gray-500">Belum ada tautan dokumen.</p>
                            @endif
                        </div>

                        <dl class="grid grid-cols-1 gap-4.5 md:grid-cols-2">
                            <x-detail-field label="Nama dokumen" :value="$d['nama_file'] ?? null" />
                            <x-detail-field label="Tipe dokumen" :value="$d['tipe_dokumen'] ?? null" />
                        </dl>
                    </div>
                </div>
            @empty
                <p class="rounded-lg border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500">Belum ada dokumen pendukung.</p>
            @endforelse
        </section>

        {{-- 5. RIWAYAT STATUS --}}
        <section class="{{ $sectionClass }}" aria-labelledby="h-timeline">
            <div class="flex items-center gap-2.5">
                <span class="{{ $numClass }}">5</span>
                <h2 id="h-timeline" class="text-[15px] font-semibold">Riwayat Status</h2>
            </div>

            @forelse ($timeline as $t)
                <div class="rounded-lg border border-gray-200 px-3.5 py-3 text-sm">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="font-medium">{{ ucfirst($t['status'] ?? '-') }}</span>
                        <span class="text-gray-500">{{ $tgl($t['tanggal_status'] ?? null) }}</span>
                    </div>
                    @if (! empty($t['catatan_revisi']))
                        <p class="mt-1 text-gray-600">{{ $t['catatan_revisi'] }}</p>
                    @endif
                </div>
            @empty
                <p class="rounded-lg border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500">Belum ada riwayat status.</p>
            @endforelse
        </section>
    </div>

@endsection