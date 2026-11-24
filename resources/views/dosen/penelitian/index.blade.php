@extends('layouts.app')

@section('title', 'Penelitian')

@section('content')
@php
    $daftarTahun = [2026, 2025, 2024, 2023, 2022];
    $daftarDana = ['Hibah Internal', 'Hibah Nasional', 'Mandiri'];

    $selectClass = 'h-10 w-full appearance-none rounded-lg border border-gray-200 bg-white pr-9 pl-3.5 text-[15px] text-gray-900';
@endphp

<div class="flex flex-wrap items-start justify-between gap-4">
    <div class="flex flex-col gap-1.5">
        <h1 class="text-4xl font-bold tracking-tight">Penelitian</h1>
        <p class="text-2xl text-gray-400">Dashboard / Penelitian</p>
    </div>

    <a href="{{ route('penelitian.create') }}"
       class="mt-2 flex h-11 items-center gap-2 rounded-md bg-brand px-4 text-base font-medium text-white">
        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.7" stroke-linecap="round" aria-hidden="true">
            <path d="M12 5v14M5 12h14" />
        </svg>
        Tambah Penelitian
    </a>
</div>

<section
    x-data="penelitian()"
    x-init="muatData()"
    class="mt-6 flex flex-col gap-6 rounded-lg bg-white p-4 shadow-sm"
>
    <div class="flex flex-wrap items-center gap-4">
        <div class="relative min-w-60 flex-1 max-w-[320px]">
            <label for="q" class="sr-only">Cari judul penelitian</label>
            <svg class="pointer-events-none absolute top-1/2 left-3 size-5 -translate-y-1/2 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8" />
                <path d="m21 21-4.35-4.35" />
            </svg>
            <input
                id="q"
                name="q"
                type="search"
                x-model="cari"
                @input.debounce.300ms="muatData()"
                placeholder="Cari judul atau ketua penelitian..."
                class="h-10 w-full rounded-lg border border-gray-200 bg-white pr-3 pl-10 text-[15px] text-gray-900 placeholder:text-gray-400"
            >
        </div>

        <div class="relative w-full sm:w-46">
            <label for="tahun" class="sr-only">Filter tahun</label>
            <select id="tahun" x-model="f.tahun" @change="halaman = 1" class="{{ $selectClass }}">
                <option value="">Semua Tahun</option>
                @foreach ($daftarTahun as $th)
                    <option value="{{ $th }}">{{ $th }}</option>
                @endforeach
            </select>
            <svg class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="m6 9 6 6 6-6" />
            </svg>
        </div>

        <div class="relative w-full sm:w-46">
            <label for="dana" class="sr-only">Filter sumber dana</label>
            <select id="dana" x-model="f.dana" @change="halaman = 1" class="{{ $selectClass }}">
                <option value="">Sumber Dana</option>
                @foreach ($daftarDana as $dn)
                    <option value="{{ $dn }}">{{ $dn }}</option>
                @endforeach
            </select>
            <svg class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="m6 9 6 6 6-6" />
            </svg>
        </div>
    </div>

    <template x-if="errorMsg">
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" x-text="errorMsg"></div>
    </template>

    <template x-if="memuat">
        <div class="px-4 py-8 text-center text-sm text-gray-500">Memuat data penelitian dari server...</div>
    </template>

    <div x-show="!memuat" class="overflow-x-auto rounded-md border border-gray-300">
        <table class="w-full min-w-[900px] table-fixed border-collapse text-sm">
            <thead>
                <tr class="bg-gray-100 text-left">
                    <x-th-urut kolom="judul" label="Judul" class="w-1/6 border-b border-gray-300 p-0 font-medium" />
                    <x-th-urut kolom="ketua" label="Ketua" class="w-1/6 border-b border-gray-300 p-0 font-medium" />
                    <x-th-urut kolom="tahun" label="Tahun Pelaksanaan" class="w-1/6 border-b border-gray-300 p-0 font-medium" />
                    <x-th-urut kolom="dana" label="Sumber Dana" class="w-1/6 border-b border-gray-300 p-0 font-medium" />
                    <x-th-urut kolom="status" label="Status" class="w-1/6 border-b border-gray-300 p-0 font-medium" />
                    <th scope="col" class="w-1/6 border-b border-gray-300 px-2.5 py-3 font-medium">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="k in tampil" :key="k.id">
                    <tr class="border-b border-gray-300 last:border-b-0 hover:bg-gray-50">
                        <td class="truncate px-2.5 py-3" :title="k.judul" x-text="k.judul"></td>
                        <td class="truncate px-2.5 py-3" :title="k.ketua" x-text="k.ketua"></td>
                        <td class="px-2.5 py-3" x-text="k.tahun"></td>
                        <td class="px-2.5 py-3" x-text="k.dana"></td>
                        <td class="px-2.5 py-3">
                            <span class="inline-block rounded-full border border-blue-200 bg-blue-100 px-2.5 py-0.5 text-xs text-blue-800" x-text="k.status"></span>
                        </td>
                        <td class="px-2.5 py-2">
                            <div class="flex items-center gap-3">
                                <a :href="`{{ url('/penelitian') }}/${k.id}`"
                                   :aria-label="`Lihat detail ${k.judul}`"
                                   class="flex size-8 items-center justify-center rounded-lg bg-blue-100 text-brand hover:bg-blue-200">
                                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                                        <path d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                        <path d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                    </svg>
                                </a>

                                <a :href="`{{ url('/penelitian') }}/${k.id}/edit`"
                                   :aria-label="`Edit ${k.judul}`"
                                   class="flex size-8 items-center justify-center rounded-lg bg-orange-100 text-orange-400 hover:bg-orange-200">
                                    <svg class="size-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                                        <path d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                    </svg>
                                </a>

                                <button
                                    type="button"
                                    :aria-label="`Hapus ${k.judul}`"
                                    @click="$dispatch('minta-hapus', { jenis: 'penelitian', nama: k.judul, action: `{{ url('/api/v1/dosen/penelitian') }}/${k.id}` })"
                                    class="flex size-8 cursor-pointer items-center justify-center rounded-lg bg-red-100 text-red-500 hover:bg-red-200">
                                    <svg class="size-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                                        <path d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>

        <p x-show="!memuat && terfilter.length === 0" x-cloak class="px-4 py-10 text-center text-sm text-gray-500">
            Tidak ada penelitian yang cocok dengan pencarian atau filter ini.
        </p>
    </div>

    <x-tabel-footer />
</section>

<x-modal-hapus />
<x-tabel-js />
@endsection

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('penelitian', () => ({
        ...tabelData({
            daftar: [],
            cari: '',
            f: { tahun: '', dana: '' },
            get terfilter() {
                const q = this.cari.trim().toLowerCase();
                return this.daftar.filter((k) =>
                    (q === '' || (k.judul || '').toLowerCase().includes(q) || (k.ketua || '').toLowerCase().includes(q)) &&
                    (this.f.tahun === '' || String(k.tahun || '') === String(this.f.tahun)) &&
                    (this.f.dana === '' || (k.dana || '') === this.f.dana)
                );
            },
        }, { perHalaman: 5, kunci: {} }),
        memuat: true,
        errorMsg: '',
        async muatData() {
            this.memuat = true;
            this.errorMsg = '';
            try {
                const params = new URLSearchParams({ per_page: 50 });
                if (this.cari.trim() !== '') {
                    params.set('q', this.cari.trim());
                }
                const res = await fetch(`/api/v1/dosen/penelitian?${params.toString()}`, {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });
                const json = await res.json();
                if (!res.ok) {
                    throw new Error(json.message || 'Gagal memuat data penelitian.');
                }
                const rawItems = Array.isArray(json.data) ? json.data : (json.data?.data || []);
                this.daftar = rawItems.map((item) => {
                    const tglMulai = item.tanggal_mulai || '';
                    return {
                        id: item.id_kegiatan ?? item.id,
                        judul: item.nama_kegiatan ?? item.judul ?? '-',
                        ketua: item.ketua ?? (item.peran === 'ketua' ? 'Anda (Ketua)' : '-'),
                        tahun: item.tahun ?? (tglMulai ? tglMulai.slice(0, 4) : '-'),
                        dana: item.dana ?? item.sumber_dana ?? '-',
                        status: item.status ?? 'Draft',
                    };
                });
                this.halaman = 1;
            } catch (err) {
                this.errorMsg = err.message || 'Terjadi kesalahan saat menghubungi API.';
            } finally {
                this.memuat = false;
            }
        },
    }));
});
</script>
@endpush