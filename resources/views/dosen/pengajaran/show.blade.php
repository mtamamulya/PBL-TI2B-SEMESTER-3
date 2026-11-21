@extends('layouts.app')

@section('title', 'Detail Pengajaran')

@section('content')

@php
    $sectionClass = 'flex flex-col gap-4.5 rounded-lg border border-gray-200 bg-white p-4.5';
    $numClass     = 'flex size-[26px] shrink-0 items-center justify-center rounded-full bg-brand text-[13px] font-semibold text-white';
@endphp

    {{-- Judul halaman + tombol kembali --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex flex-col gap-1.5">
            <h1 class="text-3xl font-bold tracking-tight">Detail Pengajaran</h1>
            <p class="text-2xl text-gray-400">Dashboard / Pengajaran / Detail Pengajaran</p>
        </div>
        <a href="{{ route('pengajaran.index') }}"
           class="mt-2 flex h-11 items-center gap-2 rounded-md bg-blue-100 px-4 text-base font-medium text-brand hover:bg-blue-200">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg>
            Kembali
        </a>
    </div>

    <div x-data="detailPengajaran()" class="flex flex-col gap-5 rounded-lg bg-white p-4.5 shadow-sm">

        {{-- Indikator Loading --}}
        <div x-show="isLoading" class="py-10 text-center text-sm text-gray-500">
            Memuat detail pengajaran...
        </div>

        <div x-show="!isLoading" x-cloak class="flex flex-col gap-5">
            {{-- 1. KELAS DAN PERIODE --}}
            <section class="{{ $sectionClass }}" aria-labelledby="h-kelas">
                <div class="flex items-center gap-2.5">
                    <span class="{{ $numClass }}">1</span>
                    <h2 id="h-kelas" class="text-[15px] font-semibold">Kelas dan Periode</h2>
                </div>

                <dl class="grid grid-cols-1 gap-4.5 md:grid-cols-2">
                    <x-detail-field label="Tahun akademik" x-text="p.tahun_akademik" icon="chevron" />
                    <x-detail-field label="Semester" x-text="p.semester" locked />
                    <x-detail-field label="Program studi" x-text="p.prodi" icon="chevron" />
                    <x-detail-field label="Kelas" x-text="p.kelas" icon="chevron" />
                </dl>
            </section>

            {{-- 2. MATA KULIAH DAN DOSEN --}}
            <section class="{{ $sectionClass }}" aria-labelledby="h-matkul">
                <div class="flex items-center gap-2.5">
                    <span class="{{ $numClass }}">2</span>
                    <h2 id="h-matkul" class="text-[15px] font-semibold">Mata Kuliah dan Dosen</h2>
                </div>

                <dl class="grid grid-cols-1 gap-4.5 md:grid-cols-2">
                    <x-detail-field label="Mata kuliah" x-text="p.matkul" icon="chevron" />
                    <x-detail-field label="Dosen pengampu" x-text="p.dosen" icon="chevron" locked />
                </dl>

                {{-- Rincian mata kuliah --}}
                <div class="overflow-x-auto rounded-md border border-gray-300">
                    <table class="w-full min-w-[520px] border-collapse text-sm">
                        <thead>
                            <tr class="bg-gray-100 text-left">
                                <th scope="col" class="w-1/3 px-2.5 py-2.5 font-medium">Kode</th>
                                <th scope="col" class="w-1/3 px-2.5 py-2.5 font-medium">SKS</th>
                                <th scope="col" class="w-1/3 px-2.5 py-2.5 font-medium">Dosen Pengampu</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="border-t border-gray-300">
                                <td class="px-2.5 py-2.5" x-text="p.kode"></td>
                                <td class="px-2.5 py-2.5" x-text="p.sks"></td>
                                <td class="px-2.5 py-2.5" x-text="p.dosen_lengkap"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p class="text-[15px] text-gray-400">Dosen pengampu terisi otomatis dari akun yang login.</p>
            </section>

            {{-- Ringkasan --}}
            <div role="status" class="flex items-center gap-2.5 rounded-lg bg-blue-100 px-4 py-3.5 text-sm text-brand">
                <svg class="size-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9.5"/><path d="M12 11v5.5M12 7.5v.5"/></svg>
                <span>Ringkasan : <span x-text="ringkasan"></span></span>
            </div>

            {{-- 3. DOKUMEN PENDUKUNG --}}
            <section class="{{ $sectionClass }}" aria-labelledby="h-dokumen">
                <div class="flex items-center gap-2.5">
                    <span class="{{ $numClass }}">3</span>
                    <h2 id="h-dokumen" class="text-[15px] font-semibold">Dokumen Pendukung</h2>
                </div>

                <template x-for="(d, index) in dokumen" :key="index">
                    <div class="overflow-hidden rounded-lg border border-gray-200">
                        <div class="bg-[#02215c] px-3.5 py-3 text-white">
                            <h3 class="text-sm font-semibold" x-text="`Dokumen ${index + 1}`"></h3>
                        </div>

                        <div class="flex flex-col gap-4 p-3.5">
                            <div class="flex flex-col gap-2">
                                <span class="text-[15px] font-medium">Tautan dokumen</span>
                                <template x-if="d.tautan">
                                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-gray-200 bg-gray-50 p-3">
                                        <div class="flex min-w-0 items-center gap-3">
                                            <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-blue-100 text-brand">
                                                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 14a4 4 0 0 0 5.66 0l3-3a4 4 0 0 0-5.66-5.66l-1 1M14 10a4 4 0 0 0-5.66 0l-3 3a4 4 0 0 0 5.66 5.66l1-1"/></svg>
                                            </span>
                                            <span class="truncate text-[15px]" :title="d.tautan" x-text="d.tautan"></span>
                                        </div>
                                        <a :href="d.tautan" target="_blank" rel="noopener noreferrer"
                                           class="flex h-9 items-center gap-1.5 rounded-lg bg-brand px-3.5 text-sm font-medium text-white hover:bg-blue-700">
                                            Buka dokumen
                                            <svg class="size-[16px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 4h6v6M20 4l-9 9M18 14v4a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4"/></svg>
                                            <span class="sr-only">(dibuka di tab baru)</span>
                                        </a>
                                    </div>
                                </template>
                                <template x-if="!d.tautan">
                                    <p class="rounded-lg border border-dashed border-gray-300 px-4 py-3 text-sm text-gray-500">Belum ada tautan dokumen.</p>
                                </template>
                            </div>

                            <dl class="grid grid-cols-1 gap-4.5 md:grid-cols-2">
                                <x-detail-field label="Nama dokumen" x-text="d.nama" />
                                <x-detail-field label="Jenis dokumen" x-text="d.jenis" icon="chevron" />
                            </dl>
                        </div>
                    </div>
                </template>

                <template x-if="dokumen.length === 0">
                    <p class="rounded-lg border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500">Belum ada dokumen pendukung.</p>
                </template>
            </section>
        </div>
    </div>

@endsection

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('detailPengajaran', () => ({
            p: {
                tahun_akademik: '-',
                semester: '-',
                prodi: '-',
                kelas: '-',
                matkul: '-',
                dosen: '-',
                kode: '-',
                sks: '-',
                dosen_lengkap: '-',
            },
            dokumen: [],
            isLoading: true,

            get ringkasan() {
                if (this.p.matkul === '-') return '-';
                const kelasBersih = String(this.p.kelas).replace('Kelas ', '');
                return `${this.p.matkul}, kelas ${kelasBersih}, ${this.p.tahun_akademik}, ${this.p.dosen_lengkap}`;
            },

            async init() {
                const id = window.location.pathname.split('/').filter(Boolean).pop();

                try {
                    const res = await fetch(`/api/v1/pengajaran/${id}`, {
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': @js(csrf_token())
                        }
                    });
                    const json = await res.json();

                    if (json.status === 'success') {
                        this.p = json.data.detail;
                        this.dokumen = json.data.dokumen;
                    }
                } catch (error) {
                    console.error('Gagal memuat detail pengajaran:', error);
                    alert('Data pengajaran tidak ditemukan atau gagal dimuat.');
                } finally {
                    this.isLoading = false;
                }
            }
        }));
    });
</script>
@endpush