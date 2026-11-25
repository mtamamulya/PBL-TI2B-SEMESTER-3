@extends('layouts.app')
@section('title', 'Pengajuan Berita')

@section('content')
{{-- Judul halaman + tombol --}}
<div class="flex flex-wrap items-start justify-between gap-4">
    <div class="flex flex-col gap-1.5">
        <h1 class="text-3xl font-bold tracking-tight">Pengajuan Berita</h1>
        <p class="text-2xl text-gray-400">Dashboard / Pengajuan / Berita</p>
    </div>
    <a href="{{ route('berita.create') }}" class="mt-2 flex h-11 items-center gap-2 rounded-md bg-brand px-4 text-base font-medium text-white hover:bg-blue-700">
        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
        Ajukan Berita
    </a>
</div>

{{-- Tab status, pencarian, urut kolom, dan pagination berjalan di browser (Alpine.js + fetch API). --}}
<section class="flex flex-col gap-6 rounded-lg bg-white p-4 shadow-sm"
         x-data="beritaDaftar()">
    {{-- Tab status. Angka biru hanya tampil di tab yang aktif. --}}
    <div role="tablist" aria-label="Status berita" class="flex gap-x-2 overflow-x-auto border-b border-gray-200 pb-px [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
        <template x-for="t in tab" :key="t">
            <button type="button" role="tab" :aria-selected="aktif === t" x-on:click="aktif = t; halaman = 1; muatData()"
                    :class="aktif === t ? 'border-brand text-gray-900' : 'border-transparent text-gray-400 hover:text-gray-700'"
                    class="-mb-px flex shrink-0 cursor-pointer items-center gap-2 border-b-2 px-3 py-2.5 text-sm">
                <span x-text="t"></span>
                <span x-show="aktif === t" x-cloak
                      class="flex h-5 min-w-5 items-center justify-center rounded-full bg-brand px-1.5 text-xs text-white"
                      x-text="jumlah(t)"></span>
            </button>
        </template>
    </div>

    <div class="flex flex-wrap items-center gap-4">
        <div class="relative min-w-60 flex-[1_1_320px]">
            <label for="q" class="sr-only">Cari judul atau kategori berita</label>
            <svg class="pointer-events-none absolute top-1/2 left-3 size-5 -translate-y-1/2 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M16.5 16.5L21 21"/></svg>
            <input id="q" name="q" type="search" x-model="cari" x-on:input.debounce.500ms="halaman = 1; muatData()" placeholder="Cari judul atau kategori berita..."
                   class="h-10 w-full rounded-lg border border-gray-200 bg-white pr-3 pl-10 text-[15px] placeholder:text-gray-400">
        </div>
    </div>

    {{-- Tabel --}}
    <div class="overflow-x-auto rounded-md border border-gray-300">
        <table class="w-full min-w-[900px] table-fixed border-collapse text-sm">
            <thead>
                <tr class="bg-gray-100 text-left">
                    <th scope="col" class="w-[28%] border-b border-gray-300 p-0 font-medium px-2.5 py-3">Judul Berita</th>
                    <th scope="col" class="w-1/5 border-b border-gray-300 p-0 font-medium px-2.5 py-3">Kategori</th>
                    <th scope="col" class="w-1/5 border-b border-gray-300 p-0 font-medium px-2.5 py-3">Tanggal Diajukan</th>
                    <th scope="col" class="w-1/6 border-b border-gray-300 p-0 font-medium px-2.5 py-3">Status</th>
                    <th scope="col" class="border-b border-gray-300 px-2.5 py-3 font-medium">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="b in daftar" :key="b.id">
                    <tr class="border-b border-gray-300 last:border-b-0 hover:bg-gray-50">
                        <td class="truncate px-2.5 py-3" :title="b.judul" x-text="b.judul"></td>
                        <td class="truncate px-2.5 py-3" :title="b.kategori" x-text="b.kategori"></td>
                        <td class="px-2.5 py-3" x-text="b.tanggal"></td>
                        <td class="px-2.5 py-3"><span :class="warna[b.status]" class="inline-block rounded-md border px-2 py-0.5 text-[13px]" x-text="b.status"></span></td>
                        <td class="px-2.5 py-2">
                            <div class="flex items-center gap-3">
                                <a :href="`{{ url('/berita') }}/${b.id}`" :aria-label="`Lihat detail ${b.judul}`"
                                   class="flex size-8 items-center justify-center rounded-lg bg-blue-100 text-brand hover:bg-blue-200">
                                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="2.8"/></svg>
                                </a>
                                {{-- Edit hanya untuk berita yang masih bisa diubah: Draft dan Direvisi --}}
                                <template x-if="b.status === 'Draft' || b.status === 'Direvisi'">
                                    <a :href="`{{ url('/berita') }}/${b.id}/edit`" :aria-label="`Edit ${b.judul}`"
                                       class="flex size-8 items-center justify-center rounded-lg bg-orange-100 text-orange-400 hover:bg-orange-200">
                                        <svg class="size-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 20h4l11-11a2.8 2.8 0 0 0-4-4L4 16zM13.5 6.5l4 4"/></svg>
                                    </a>
                                </template>
                            </div>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
        <p x-show="daftar.length === 0 && !loading" x-cloak class="px-4 py-10 text-center text-sm text-gray-500">
            Tidak ada berita yang cocok dengan filter ini.
        </p>
        <p x-show="loading" x-cloak class="px-4 py-10 text-center text-sm text-gray-500">
            Memuat data...
        </p>
    </div>

    {{-- Pagination --}}
    <nav aria-label="Halaman" x-show="totalHalaman > 1" class="flex items-center justify-center gap-2.5 pt-1 pb-4">
        <button type="button" x-on:click="ke(halamanAktif - 1)" :disabled="halamanAktif === 1" aria-label="Halaman sebelumnya"
                class="flex size-8 cursor-pointer items-center justify-center rounded border border-brand bg-brand text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg>
        </button>
        <template x-for="n in totalHalaman" :key="n">
            <button type="button" x-on:click="ke(n)" x-text="n"
                    :aria-label="`Halaman ${n}`" :aria-current="n === halamanAktif ? 'page' : null"
                    :class="n === halamanAktif ? 'border-brand bg-white text-brand' : 'border-gray-300 bg-white text-gray-900 hover:bg-gray-50'"
                    class="flex size-8 cursor-pointer items-center justify-center rounded border text-sm font-semibold"></button>
        </template>
        <button type="button" x-on:click="ke(halamanAktif + 1)" :disabled="halamanAktif === totalHalaman" aria-label="Halaman berikutnya"
                class="flex size-8 cursor-pointer items-center justify-center rounded border border-brand bg-blue-100 text-brand hover:bg-blue-200 disabled:cursor-not-allowed disabled:opacity-50">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg>
        </button>
    </nav>
</section>

@push('scripts')
@include('dosen.berita._api')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('beritaDaftar', () => ({
        daftar: [],
        cari: '',
        tab: ['Semua', 'Draft', 'Diajukan', 'Direvisi', 'Dipublikasikan', 'Ditolak'],
        aktif: 'Semua',
        halaman: 1,
        loading: false,
        totalHalaman: 1,
        warna: {
            Draft: 'border-gray-300 bg-gray-100 text-gray-700',
            Diajukan: 'border-blue-200 bg-blue-100 text-blue-700',
            Direvisi: 'border-orange-200 bg-orange-100 text-orange-700',
            Dipublikasikan: 'border-green-200 bg-green-100 text-green-700',
            Ditolak: 'border-red-200 bg-red-100 text-red-600',
        },

        async init() {
            await this.muatData();
        },

        async muatData() {
            this.loading = true;
            try {
                const params = new URLSearchParams();
                if (this.aktif !== 'Semua') params.append('status', this.aktif.toLowerCase());
                if (this.cari) params.append('search', this.cari);
                params.append('page', this.halaman);

                const { data, meta } = await apiBerita(`?${params.toString()}`);
                this.daftar = data;
                this.totalHalaman = meta.last_page;
            } catch (e) {
                console.error('Gagal memuat data:', e);
            } finally {
                this.loading = false;
            }
        },

        ke(hal) {
            if (hal >= 1 && hal <= this.totalHalaman) {
                this.halaman = hal;
                this.muatData();
            }
        },

        jumlah(t) {
            return t === 'Semua' ? this.daftar.length : this.daftar.filter(b => b.status === t).length;
        }
    }));
});
</script>
@endpush
@endsection