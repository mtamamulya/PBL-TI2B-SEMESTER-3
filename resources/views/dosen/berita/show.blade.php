@extends('layouts.app')
@section('title', 'Detail Berita')

@section('content')
<div class="flex flex-wrap items-start justify-between gap-4" x-data="detailBerita()">
    <div class="flex flex-col gap-1.5">
        <h1 class="text-3xl font-bold tracking-tight">Detail Berita</h1>
        <p class="text-2xl text-gray-400">Dashboard / Pengajuan / Berita / Detail</p>
    </div>
    <a href="{{ route('berita.index') }}"
       class="mt-2 flex h-11 items-center gap-2 rounded-md bg-blue-100 px-4 text-base font-medium text-brand hover:bg-blue-200">
        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg>
        Kembali
    </a>
</div>

<div x-data="detailBerita()" x-init="muatDetail(@js($id))">
    {{-- Loading --}}
    <div x-show="loading" class="py-10 text-center text-gray-500">Memuat data...</div>

    {{-- Error --}}
    <div x-show="error" x-cloak class="rounded-lg bg-red-50 p-4 text-red-700" x-text="error"></div>

    {{-- Konten --}}
    <template x-if="berita">
        <div class="mt-6">
            {{-- Tahapan pengajuan --}}
            <template x-if="berita.status !== 'Draft'">
                <section class="rounded-lg bg-white p-4.5 shadow-sm mb-5" aria-label="Tahapan pengajuan">
                    <ol class="flex">
                        <template x-for="(langkah, n) in alurLangkah" :key="n">
                            <li class="flex flex-col items-start gap-2" :class="n === 3 ? 'shrink-0' : 'flex-1'">
                                <div class="flex w-full items-center">
                                    <span class="flex size-8 shrink-0 items-center justify-center rounded-full border-2 text-xs font-medium"
                                          :class="lingkaranClass(langkah)">
                                        <template x-if="langkah === 'd' || langkah === 'h'">
                                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
                                        </template>
                                        <template x-if="langkah !== 'd' && langkah !== 'h'" x-text="'0' + (n + 1)"></template>
                                    </span>
                                    <template x-if="n < 3">
                                        <span class="mx-2 h-px flex-1 bg-gray-400"></span>
                                    </template>
                                </div>
                                <span class="text-xs font-medium" :class="teksLangkahClass(langkah)" x-text="namaLangkah[n]"></span>
                            </li>
                        </template>
                    </ol>
                </section>
            </template>

            <div class="grid grid-cols-1 gap-5 lg:grid-cols-[minmax(0,1fr)_24rem] lg:items-start">
                {{-- KIRI: ISI BERITA --}}
                <article class="flex flex-col gap-4 rounded-lg border border-gray-200 bg-white p-5">
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="rounded-md border px-2 py-0.5 text-xs" :class="badgeClass(berita.status)" x-text="berita.status"></span>
                        <span class="text-[13px] text-gray-600">
                            <span x-text="berita.kategori"></span> &middot; 
                            <span x-text="berita.status === 'Draft' ? 'Dibuat' : 'Diajukan'"></span> 
                            <span x-text="berita.tanggal"></span>
                        </span>
                    </div>
                    <h2 class="text-2xl leading-snug font-bold" x-text="berita.judul"></h2>
                    
                    {{-- Foto Sampul --}}
                    <template x-if="berita.sampul">
                        <img :src="berita.sampul" alt="Foto sampul" class="h-56 w-full object-cover rounded-lg">
                    </template>
                    <template x-if="!berita.sampul">
                        <div class="flex h-56 items-center justify-center gap-2 rounded-lg bg-gray-200 text-sm text-gray-600">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M21 16l-5-5-8 8"/></svg>
                            Foto sampul
                        </div>
                    </template>

                    {{-- Isi Berita --}}
                    <div class="konten-berita text-[15px] leading-relaxed text-gray-700" x-html="berita.isi"></div>
                </article>

                {{-- KANAN --}}
                <div class="flex flex-col gap-5">
                    {{-- Draft --}}
                    <template x-if="berita.status === 'Draft'">
                        <section class="flex flex-col gap-4 rounded-lg border border-gray-200 bg-white p-4">
                            <h2 class="text-xl font-semibold">Berita masih draf</h2>
                            <p class="text-[15px] text-gray-600">Berita ini belum diajukan ke admin. Lengkapi lalu ajukan supaya bisa direview.</p>
                            <a :href="`{{ url('/berita') }}/${berita.id}/edit`"
                               class="flex h-10 w-full items-center justify-center rounded-md bg-brand text-base text-white hover:bg-blue-700">Lanjutkan & ajukan</a>
                        </section>
                    </template>

                    {{-- Diajukan --}}
                    <template x-if="berita.status === 'Diajukan'">
                        <div role="status" class="flex items-start gap-3 rounded-lg border border-blue-200 bg-blue-50 p-4 text-[15px] text-blue-800">
                            <svg class="mt-0.5 size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9.5"/><path d="M12 7v5l3 2"/></svg>
                            <div>
                                <p class="font-semibold">Menunggu review admin</p>
                                <p>Admin akan memutuskan apakah berita ini dipublikasikan. Hasilnya tampil di halaman ini.</p>
                            </div>
                        </div>
                    </template>

                    {{-- Direvisi --}}
                    <template x-if="berita.status === 'Direvisi'">
                        <section class="flex flex-col gap-2 rounded-lg border border-gray-200 bg-white p-4">
                            <h2 class="text-xl font-semibold">Keputusan Admin</h2>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-md border border-orange-200 bg-orange-100 px-2 py-0.5 text-xs text-orange-700">Perlu direvisi</span>
                            </div>
                            <div class="rounded-md bg-orange-50 px-3.5 py-3 text-gray-900">
                                <p class="text-[13px] font-semibold">Catatan reviewer</p>
                                <p class="mt-1 text-sm" x-text="berita.catatan_revisi || 'Tidak ada catatan.'"></p>
                            </div>
                            <a :href="`{{ url('/berita') }}/${berita.id}/edit`"
                               class="flex h-10 w-full items-center justify-center rounded-md bg-brand text-base text-white hover:bg-blue-700">Perbaiki & ajukan kembali</a>
                        </section>
                    </template>

                    {{-- Ditolak --}}
                    <template x-if="berita.status === 'Ditolak'">
                        <section class="flex flex-col gap-2 rounded-lg border border-red-200 bg-red-50 p-4">
                            <h2 class="flex items-center gap-2 text-xl font-semibold text-red-700">
                                <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9.5"/><path d="M12 7.5v5.5M12 16.5v.01"/></svg>
                                Alasan Penolakan
                            </h2>
                            <p class="px-1 text-[15px] text-gray-900" x-text="berita.catatan_revisi || 'Tidak ada alasan.'"></p>
                        </section>
                    </template>

                    {{-- Dipublikasikan --}}
                    <template x-if="berita.status === 'Dipublikasikan'">
                        <section class="flex flex-col gap-2 rounded-lg border border-green-200 bg-green-50 p-4">
                            <h2 class="flex items-center gap-2 text-xl font-semibold text-green-700">
                                <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9.5"/><path d="M8 12.5l3 3 5-6"/></svg>
                                Berita dipublikasikan
                            </h2>
                            <p class="px-1 text-[15px] text-gray-900">Disetujui admin dan sudah tampil di website.</p>
                        </section>
                    </template>
                </div>
            </div>
        </div>
    </template>
</div>

<style>
.konten-berita p { margin-bottom: 0.75rem; }
.konten-berita p:last-child { margin-bottom: 0; }
.konten-berita h2 { font-size: 1.35rem; font-weight: 700; margin: 1rem 0 0.5rem; color: #111827; }
.konten-berita h3 { font-size: 1.15rem; font-weight: 600; margin: 0.85rem 0 0.4rem; color: #111827; }
.konten-berita h4 { font-size: 1rem; font-weight: 600; margin: 0.75rem 0 0.35rem; color: #111827; }
.konten-berita ul { list-style-type: disc; padding-left: 1.5rem; margin: 0.5rem 0 0.75rem; }
.konten-berita ol { list-style-type: decimal; padding-left: 1.5rem; margin: 0.5rem 0 0.75rem; }
.konten-berita li { margin-bottom: 0.25rem; }
.konten-berita blockquote { border-left: 3px solid #d1d5db; padding-left: 0.875rem; color: #4b5563; font-style: italic; margin: 0.75rem 0; }
.konten-berita a { color: #1f66e5; text-decoration: underline; }
</style>

@push('scripts')
@include('dosen.berita._api')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('detailBerita', () => ({
        berita: null,
        loading: true,
        error: '',
        alurLangkah: [],
        namaLangkah: ['Diajukan', 'Direview admin', 'Keputusan', 'Dipublikasikan'],

        async muatDetail(id) {
            this.loading = true;
            try {
                const { data } = await apiBerita(`/${id}`);
                this.berita = data;
                this.setupAlur();
            } catch (e) {
                this.error = e.message;
            } finally {
                this.loading = false;
            }
        },

        setupAlur() {
            const statusMap = {
                'Diajukan': ['a', 'm', 'm', 'm'],
                'Direvisi': ['d', 'd', 'a', 'm'],
                'Ditolak': ['d', 'd', 'a', 'm'],
                'Dipublikasikan': ['d', 'd', 'd', 'h'],
            };
            this.alurLangkah = statusMap[this.berita.status] || [];
        },

        lingkaranClass(langkah) {
            if (langkah === 'd') return 'border-brand bg-brand text-white';
            if (langkah === 'h') return 'border-lime-500 bg-lime-500 text-white';
            if (langkah === 'a') return 'border-brand bg-white text-brand';
            return 'border-gray-400 bg-white text-gray-700';
        },

        teksLangkahClass(langkah) {
            if (langkah === 'm') return 'text-gray-700';
            if (langkah === 'h') return 'text-lime-600';
            if (langkah === 'a') return 'text-brand';
            return 'text-brand';
        },

        badgeClass(status) {
            const badges = {
                'Draft': 'border-gray-300 bg-gray-100 text-gray-700',
                'Diajukan': 'border-blue-200 bg-blue-100 text-blue-700',
                'Direvisi': 'border-orange-200 bg-orange-100 text-orange-700',
                'Dipublikasikan': 'border-green-200 bg-green-100 text-green-700',
                'Ditolak': 'border-red-200 bg-red-100 text-red-600',
            };
            return badges[status] || 'border-gray-300 bg-gray-100 text-gray-700';
        }
    }));
});
</script>
@endpush
@endsection