@extends($layout ?? 'layouts.admin')

@section('title', 'Notifikasi')

@section('content')
    @php
        $prefixAktif = $prefix ?? 'admin';
        $konfig = [
            'csrf' => csrf_token(),
            'urlBaca' => route($prefixAktif.'.notifikasi.baca', '__ID__'),
            'urlSemua' => route($prefixAktif.'.notifikasi.baca-semua'),
        ];
    @endphp

    <style>[x-cloak]{display:none!important}</style>

    <div class="px-6 pb-8 pt-4 md:px-8" x-data="notifikasi(@js($notifikasi ?? []), @js($konfig))">
        {{-- Header & Breadcrumb mengikuti pola halaman admin --}}
        <div class="flex max-w-[1152px] flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-[32px] font-bold leading-tight text-[#111]">Notifikasi</h1>
                <nav aria-label="Breadcrumb" class="mt-[7px] text-xl leading-[26px] text-[#B9B9BF]">
                    <a href="{{ route($prefixAktif.'.dashboard') }}" class="transition-colors hover:text-[#226CFF] hover:underline">Dashboard</a>
                    <span class="mx-1">/</span>
                    <span class="text-[#6E6E76]" aria-current="page">Notifikasi</span>
                </nav>
            </div>

            <button type="button" x-on:click="tandaiSemua()" :disabled="belumDibaca === 0"
                    class="flex h-11 cursor-pointer items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 disabled:cursor-not-allowed disabled:text-gray-400 disabled:hover:bg-white">
                <svg class="size-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 13l4 4 7-9M11 15.5l1.5 1.5 8-10"/>
                </svg>
                <span>Tandai semua dibaca</span>
            </button>
        </div>

        @if ($dummy ?? false)
            <p class="mt-4 max-w-[1152px] rounded-lg border border-[#F5D58A] bg-[#FFF8E6] px-4 py-2 text-sm text-[#8A5A00]">
                Mode dummy: data contoh, status dibaca disimpan sementara di session.
            </p>
        @endif

        <div class="mt-[26px] flex max-w-[1152px] flex-col gap-5">
            {{-- Filter --}}
            <div role="group" aria-label="Filter notifikasi" class="flex flex-wrap gap-2">
                <template x-for="c in chip" :key="c.kunci">
                    <button type="button" x-on:click="filter = c.kunci" :aria-pressed="filter === c.kunci"
                            :class="filter === c.kunci ? 'border-[#226CFF] bg-[#226CFF] font-semibold text-white' : 'border-gray-300 bg-white font-medium text-gray-700 hover:bg-gray-50'"
                            class="flex min-h-10 cursor-pointer items-center gap-2 rounded-full border px-4 text-sm transition-colors">
                        <span x-text="c.label"></span>
                        <span :class="filter === c.kunci ? 'bg-white/25 text-white' : 'bg-gray-100 text-gray-700'"
                              class="flex h-[22px] min-w-[22px] items-center justify-center rounded-full px-1.5 text-xs font-semibold"
                              x-text="c.jumlah"></span>
                    </button>
                </template>
            </div>

            {{-- Daftar notifikasi dikelompokkan per waktu --}}
            <div class="flex w-full flex-col gap-5">
                <template x-for="g in grup" :key="g.judul">
                    <section :aria-label="g.judul" class="flex flex-col gap-2">
                        <h2 class="text-sm font-semibold tracking-wide text-gray-500" x-text="g.judul"></h2>
                        <ul class="overflow-hidden rounded-lg border border-gray-200/80 bg-white shadow-sm">
                            <template x-for="n in g.item" :key="n.id">
                                <li class="border-t border-gray-200 first:border-t-0">
                                    <a :href="n.href" x-on:click="buka(n, $event)"
                                       :class="n.unread ? 'bg-blue-50/80' : 'bg-white'"
                                       class="flex items-start gap-3.5 px-4 py-4 transition-colors hover:bg-blue-50/60 sm:px-5">
                                        {{-- Ikon sesuai jenis (kegiatan / berita) --}}
                                        <span :class="(warna[n.type] || warna.kegiatan).tile"
                                              class="flex size-11 shrink-0 items-center justify-center rounded-xl">
                                            {{-- Ikon Kegiatan (clipboard) --}}
                                            <svg x-show="n.type === 'kegiatan'" class="size-[22px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <rect x="4.5" y="5" width="15" height="15.5" rx="2.5"/>
                                                <path d="M9 3.5h6v3H9zM9 13.5l2.2 2.2 4-4.4"/>
                                            </svg>
                                            {{-- Ikon Berita (koran / dokumen) --}}
                                            <svg x-show="n.type === 'berita'" x-cloak class="size-[22px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <path d="M4 5.5A1.5 1.5 0 0 1 5.5 4h11A1.5 1.5 0 0 1 18 5.5v13a1.5 1.5 0 0 0 1.5 1.5h-13A2.5 2.5 0 0 1 4 17.5v-12Z"/>
                                                <path d="M18 8h1.5A1.5 1.5 0 0 1 21 9.5v8a2.5 2.5 0 0 1-2.5 2.5"/>
                                                <path d="M7.5 8h7M7.5 12h7M7.5 16h4.5"/>
                                            </svg>
                                        </span>

                                        <div class="flex min-w-0 flex-1 flex-col gap-1">
                                            <div class="flex flex-wrap items-center gap-x-2.5 gap-y-1">
                                                <span :class="n.unread ? 'font-bold text-[#111]' : 'font-medium text-gray-800'"
                                                      class="break-words text-[15px]" x-text="n.title"></span>
                                                <span :class="(warna[n.type] || warna.kegiatan).tag"
                                                      class="rounded-md px-2 py-0.5 text-xs font-medium"
                                                      x-text="label[n.type] || n.type"></span>
                                            </div>
                                            <span class="break-words text-sm leading-normal text-gray-600" x-text="n.body"></span>
                                            <span class="text-[13px] text-gray-400" x-text="n.time"></span>
                                        </div>

                                        {{-- Titik biru belum dibaca --}}
                                        <div class="flex shrink-0 items-center pt-1.5">
                                            <template x-if="n.unread">
                                                <span>
                                                    <span class="sr-only">Belum dibaca</span>
                                                    <span aria-hidden="true" class="block size-2.5 rounded-full bg-[#226CFF]"></span>
                                                </span>
                                            </template>
                                        </div>
                                    </a>
                                </li>
                            </template>
                        </ul>
                    </section>
                </template>

                {{-- Keadaan kosong --}}
                <div x-show="tampil.length === 0" x-cloak
                     class="flex flex-col items-center gap-2.5 rounded-lg border border-gray-200/80 bg-white px-6 py-12 text-center shadow-sm">
                    <span class="flex size-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-500">
                        <svg class="size-[26px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M6 16.5V11a6 6 0 0 1 12 0v5.5l1.5 1.5h-15zM10 20.5a2 2 0 0 0 4 0"/>
                        </svg>
                    </span>
                    <span class="text-[17px] font-semibold text-[#111]">Tidak ada notifikasi</span>
                    <span class="max-w-sm text-sm leading-normal text-gray-500">Tidak ada notifikasi yang cocok dengan filter ini. Coba pilih filter lain.</span>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('notifikasi', (daftar, konfig) => ({
            daftar: daftar,
            filter: 'semua',

            label: { kegiatan: 'Kegiatan', berita: 'Berita' },
            warna: {
                kegiatan: { tile: 'bg-blue-100 text-blue-700',       tag: 'bg-blue-100 text-blue-700' },
                berita:   { tile: 'bg-emerald-100 text-emerald-700', tag: 'bg-emerald-100 text-emerald-700' },
            },

            init() {
                this.$watch('belumDibaca', (n) => window.dispatchEvent(new CustomEvent('notif-count', { detail: n })));
                window.dispatchEvent(new CustomEvent('notif-count', { detail: this.belumDibaca }));
            },

            get belumDibaca() {
                return this.daftar.filter(n => n.unread).length;
            },

            cocok(n, kunci) {
                return kunci === 'semua' || (kunci === 'belum' ? n.unread : n.type === kunci);
            },

            get chip() {
                return [
                    ['semua', 'Semua'],
                    ['belum', 'Belum dibaca'],
                    ['kegiatan', 'Kegiatan'],
                    ['berita', 'Berita'],
                ].map(([kunci, label]) => ({
                    kunci,
                    label,
                    jumlah: this.daftar.filter(n => this.cocok(n, kunci)).length,
                }));
            },

            get tampil() {
                return this.daftar.filter(n => this.cocok(n, this.filter));
            },

            get grup() {
                return ['Hari ini', 'Kemarin', 'Lebih lama']
                    .map(judul => ({ judul, item: this.tampil.filter(n => n.g === judul) }))
                    .filter(g => g.item.length > 0);
            },

            kirim(url) {
                return fetch(url, {
                    method: 'POST',
                    keepalive: true,
                    credentials: 'same-origin',
                    headers: {
                        'X-CSRF-TOKEN': konfig.csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                });
            },

            async buka(n, event) {
                const navigasiBiasa = !event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey;
                if (n.href === '#' || navigasiBiasa) {
                    event.preventDefault();
                }

                if (n.unread) {
                    try {
                        const response = await this.kirim(konfig.urlBaca.replace('__ID__', encodeURIComponent(n.id)));
                        if (response.ok) {
                            n.unread = false;
                        }
                    } catch {
                        // Biarkan status belum dibaca jika gagal menyimpan ke server.
                    }
                }

                if (navigasiBiasa && n.href && n.href !== '#') {
                    window.location.assign(n.href);
                }
            },

            async tandaiSemua() {
                const belum = this.daftar.filter(n => n.unread);
                belum.forEach(n => { n.unread = false; });

                try {
                    const r = await this.kirim(konfig.urlSemua);
                    if (!r.ok) {
                        throw new Error('gagal');
                    }
                } catch {
                    belum.forEach(n => { n.unread = true; });
                }
            },
        }));
    });
</script>
@endpush