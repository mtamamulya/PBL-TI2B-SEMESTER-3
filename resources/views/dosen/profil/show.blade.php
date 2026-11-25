@extends('layouts.app')

@section('title', 'Profil')

@section('content')
@php
    // Aktivitas per tab (tetap dipertahankan jika belum terhubung ke API aktivitas).
    $aktivitas = [
        'Penelitian' => [
            ['judul' => 'Deteksi hoaks dengan machine learning',  'meta' => '2026 • Penelitian Terapan',       'peran' => 'Ketua'],
            ['judul' => 'Analisis sentimen ulasan aplikasi',      'meta' => '2025 • Penelitian Dasar',         'peran' => 'Anggota'],
            ['judul' => 'Sistem rekomendasi mata kuliah',         'meta' => '2025 • Penelitian Pengembangan',  'peran' => 'Anggota'],
        ],
        'Pengabdian' => [
            ['judul' => 'Pelatihan Industri',                     'meta' => '2026 • Pengabdian Masyarakat',    'peran' => 'Ketua'],
            ['judul' => 'Seminar Nasional AI',                    'meta' => '2025 • Pengabdian Masyarakat',    'peran' => 'Anggota'],
        ],
        'Pengajaran' => [
            ['judul' => 'Kecerdasan Buatan',                      'meta' => '2025/2026 Ganjil • Kelas 2A',     'peran' => 'Pengampu'],
            ['judul' => 'Sistem Basis Data',                      'meta' => '2025/2026 Genap • Kelas 3A',      'peran' => 'Pengampu'],
        ],
        'Kegiatan' => [
            ['judul' => 'Kuliah Tamu',                            'meta' => '10 Juli 2026 • Pengajaran',       'peran' => 'Peserta'],
        ],
    ];

    $badge = 'rounded-full border border-blue-200 bg-blue-100 px-2.5 py-0.5 text-[13px] text-brand';
@endphp

<div x-data="profilDosen()" x-init="muatData()" class="flex flex-col gap-6">
    {{-- Judul halaman + tombol kembali --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex flex-col gap-1.5">
            <h1 class="text-3xl font-bold tracking-tight">Profil</h1>
            <p class="text-2xl text-gray-400">Dashboard / Profil</p>
        </div>
        <div class="mt-2 flex items-center gap-3">
            <a href="{{ route('dosen.dashboard') }}"
               class="flex h-11 items-center gap-2 rounded-md bg-blue-100 px-4 text-base font-medium text-brand hover:bg-blue-200">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg>
                Kembali
            </a>
        </div>
    </div>

    {{-- Pesan sukses / error --}}
    <template x-if="sukses">
        <div role="status" class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700" x-text="sukses"></div>
    </template>
    <template x-if="error">
        <div role="alert" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" x-text="error"></div>
    </template>

    {{-- Kartu identitas --}}
    <section class="flex flex-col gap-8 rounded-lg bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-5">
                {{-- Avatar dengan ikon pensil saat hover --}}
                <div class="group relative size-[88px] shrink-0 overflow-hidden rounded-full">
                    <template x-if="profil.foto_profil">
                        <img :src="profil.foto_profil" :alt="profil.nama || 'Foto Profil'" class="size-full rounded-full object-cover">
                    </template>
                    <template x-if="!profil.foto_profil">
                        <span class="flex size-full items-center justify-center rounded-full bg-red-600 text-3xl font-semibold text-white"
                              aria-hidden="true" x-text="inisial(profil.nama)"></span>
                    </template>

                    <button type="button"
                            :disabled="mengupload"
                            x-on:click="bukaPilihFoto()"
                            title="Ganti foto profil"
                            aria-label="Ganti foto profil"
                            class="absolute inset-0 flex cursor-pointer items-center justify-center rounded-full bg-black/50 text-white opacity-0 transition-opacity group-hover:opacity-100 group-focus-within:opacity-100 disabled:cursor-not-allowed">
                        <template x-if="!mengupload">
                            <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M12 20h9" />
                                <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z" />
                            </svg>
                        </template>
                        <template x-if="mengupload">
                            <span class="text-xs font-medium">...</span>
                        </template>
                    </button>

                    <input x-ref="inputFoto"
                           type="file"
                           accept="image/png,image/jpeg,image/jpg,image/webp"
                           x-on:change="pilihFileUntukCrop($event)"
                           class="hidden">
                </div>

                <div class="flex min-w-0 flex-col gap-1">
                    <div class="flex flex-wrap items-center gap-4">
                        <h2 class="text-2xl font-semibold" x-text="loading ? 'Memuat...' : (profil.nama || '-')"></h2>
                        <span class="rounded-full border border-green-200 bg-green-100 px-2.5 py-0.5 text-[13px] text-green-700">Aktif</span>
                    </div>
                    <p class="text-xl text-gray-400" x-text="profil.nip ? `NIP ${profil.nip}` : 'NIP -'"></p>
                </div>
            </div>
        </div>

        <dl class="grid grid-cols-1 gap-5 rounded-lg bg-blue-50 px-5 py-5 sm:grid-cols-2 lg:grid-cols-[1.8fr_0.8fr_1.4fr_0.8fr]">
            <div class="flex min-w-0 flex-col gap-1.5">
                <dt class="text-lg text-gray-400">Email akun</dt>
                <dd class="text-xl font-medium break-words" x-text="profil.email || '-'"></dd>
            </div>
            <div class="flex min-w-0 flex-col gap-1.5">
                <dt class="text-lg text-gray-400">Role</dt>
                <dd class="text-xl font-medium break-words">Dosen</dd>
            </div>
            <div class="flex min-w-0 flex-col gap-1.5">
                <dt class="text-lg text-gray-400">Pangkat / golongan saat ini</dt>
                <dd class="text-xl font-medium break-words" x-text="formatPangkatTerakhir()"></dd>
            </div>
            <div class="flex min-w-0 flex-col gap-1.5">
                <dt class="text-lg text-gray-400">Akun login</dt>
                <dd class="text-xl font-medium break-words">Aktif</dd>
            </div>
        </dl>
    </section>

    {{-- Riwayat pangkat dan golongan --}}
    <section class="flex flex-col gap-8 rounded-lg bg-white p-5 shadow-sm">
        <h2 class="text-2xl font-medium">Riwayat Pangkat dan Golongan</h2>

        <div class="overflow-x-auto rounded-md border border-gray-300">
            <table class="w-full min-w-[720px] table-fixed border-collapse text-sm">
                <thead>
                    <tr class="bg-gray-100 text-left">
                        <th scope="col" class="w-1/4 border-b border-gray-300 px-2.5 py-3 font-medium">Pangkat</th>
                        <th scope="col" class="w-1/4 border-b border-gray-300 px-2.5 py-3 font-medium">Golongan</th>
                        <th scope="col" class="w-1/4 border-b border-gray-300 px-2.5 py-3 font-medium">Nomor SK</th>
                        <th scope="col" class="w-1/4 border-b border-gray-300 px-2.5 py-3 font-medium">Tanggal SK</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="loading">
                        <tr>
                            <td colspan="4" class="px-2.5 py-4 text-center text-gray-400">Memuat riwayat pangkat...</td>
                        </tr>
                    </template>
                    <template x-if="!loading && riwayat.length === 0">
                        <tr>
                            <td colspan="4" class="px-2.5 py-4 text-center text-gray-400">Belum ada data riwayat pangkat.</td>
                        </tr>
                    </template>
                    <template x-for="(r, idx) in riwayat" :key="r.id_histori_pangkat_golongan ?? idx">
                        <tr class="border-b border-gray-300 last:border-b-0">
                            <td class="px-2.5 py-3">
                                <span class="flex flex-wrap items-center gap-2">
                                    <span x-text="r.pangkat || '-'"></span>
                                    <template x-if="idx === 0">
                                        <span class="{{ $badge }} py-px text-xs">Saat ini</span>
                                    </template>
                                </span>
                            </td>
                            <td class="px-2.5 py-3" x-text="r.golongan || '-'"></td>
                            <td class="px-2.5 py-3" x-text="r.nomor_sk || '-'"></td>
                            <td class="px-2.5 py-3" x-text="formatTanggal(r.tanggal_sk)"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </section>

    {{-- Aktivitas dosen: tab berpindah dengan Alpine.js --}}
    <section class="flex flex-col gap-6 rounded-lg bg-white p-5 shadow-sm"
            x-data="{ tab: 'Penelitian', data: @js($aktivitas) }">
        <h2 class="text-2xl font-medium">Aktivitas Dosen</h2>

        <div class="w-full border-b border-gray-200">
            <div role="tablist" aria-label="Jenis aktivitas"
                class="flex overflow-x-auto whitespace-nowrap gap-4 no-scrollbar">
                @foreach (array_keys($aktivitas) as $nama)
                    <button type="button" role="tab" id="tab-{{ $nama }}"
                            x-on:click="tab = '{{ $nama }}'"
                            :aria-selected="tab === '{{ $nama }}'"
                            :class="tab === '{{ $nama }}' ? 'border-brand text-gray-900 font-medium' : 'border-transparent text-gray-400 hover:text-gray-700'"
                            class="-mb-px flex shrink-0 cursor-pointer items-center gap-2 border-b-2 pb-3 pt-1 text-sm transition-colors">
                        <span>{{ $nama }}</span>

                        <span x-show="tab === '{{ $nama }}'" x-cloak
                            class="flex size-5 items-center justify-center rounded-full bg-brand text-xs font-semibold text-white"
                            x-text="data['{{ $nama }}'].length"></span>
                    </button>
                @endforeach
            </div>
        </div>

        <ul role="tabpanel" :aria-labelledby="`tab-${tab}`" class="flex flex-col">
            <template x-for="(a, i) in data[tab]" :key="tab + i">
                <li class="flex items-center justify-between gap-4 border-b border-gray-200 px-4 py-3.5 last:border-b-0">
                    <div class="flex min-w-0 flex-col gap-0.5">
                        <span class="truncate text-[17px]" x-text="a.judul"></span>
                        <span class="text-sm text-gray-500" x-text="a.meta"></span>
                    </div>
                    <span class="{{ $badge }} shrink-0" x-text="a.peran"></span>
                </li>
            </template>
        </ul>
    </section>

    {{-- Modal Crop & Zoom Foto Profil --}}
    <div x-show="modalCropTerbuka"
         x-cloak
         x-on:keydown.escape.window="tutupModalCrop()"
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
         role="dialog"
         aria-modal="true"
         aria-labelledby="judul-modal-crop">
        <div class="flex w-full max-w-md flex-col gap-4 rounded-xl bg-white p-5 shadow-xl" x-on:click.stop>
            <div class="flex items-center justify-between border-b border-gray-200 pb-3">
                <h3 id="judul-modal-crop" class="text-lg font-semibold text-gray-900">Atur & Crop Foto Profil</h3>
                <button type="button"
                        x-on:click="tutupModalCrop()"
                        class="cursor-pointer rounded-md p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700"
                        aria-label="Tutup modal">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </button>
            </div>

            <p class="text-xs text-gray-500">Geser gambar untuk mengatur posisi, lalu gunakan zoom in / zoom out sesuai area lingkaran.</p>

            {{-- Area kanvas crop --}}
            <div class="relative mx-auto flex size-[280px] items-center justify-center overflow-hidden rounded-lg bg-gray-900 select-none">
                <canvas x-ref="canvasCrop"
                        width="280"
                        height="280"
                        x-on:pointerdown="mulaiGeser($event)"
                        x-on:pointermove="geserGambar($event)"
                        x-on:pointerup="selesaiGeser()"
                        x-on:pointercancel="selesaiGeser()"
                        x-on:wheel.prevent="zoomDenganWheel($event)"
                        class="size-[280px] cursor-grab active:cursor-grabbing touch-none"></canvas>

                {{-- Panduan lingkaran preview crop --}}
                <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
                    <div class="size-[220px] rounded-full border-2 border-white/90 shadow-[0_0_0_9999px_rgba(0,0,0,0.45)]"></div>
                </div>
            </div>

            {{-- Kontrol Zoom In / Zoom Out --}}
            <div class="flex flex-col gap-2">
                <div class="flex items-center justify-between text-xs font-medium text-gray-600">
                    <span>Zoom</span>
                    <span x-text="`${Math.round(zoom * 100)}%`"></span>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button"
                            x-on:click="ubahZoom(-0.15)"
                            title="Zoom Out"
                            aria-label="Zoom Out"
                            class="flex size-9 shrink-0 cursor-pointer items-center justify-center rounded-md border border-gray-300 text-gray-700 hover:bg-gray-100">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M5 12h14"/></svg>
                    </button>

                    <input type="range"
                           min="1"
                           max="3"
                           step="0.05"
                           x-model.number="zoom"
                           x-on:input="terapkanZoom()"
                           aria-label="Tingkat zoom"
                           class="h-2 w-full cursor-pointer accent-brand">

                    <button type="button"
                            x-on:click="ubahZoom(0.15)"
                            title="Zoom In"
                            aria-label="Zoom In"
                            class="flex size-9 shrink-0 cursor-pointer items-center justify-center rounded-md border border-gray-300 text-gray-700 hover:bg-gray-100">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 5v14M5 12h14"/></svg>
                    </button>

                    <button type="button"
                            x-on:click="resetCrop()"
                            class="ml-1 shrink-0 cursor-pointer rounded-md border border-gray-300 px-2.5 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-100">
                        Reset
                    </button>
                </div>
            </div>

            {{-- Tombol aksi modal --}}
            <div class="flex items-center justify-end gap-2.5 border-t border-gray-200 pt-3">
                <button type="button"
                        :disabled="mengupload"
                        x-on:click="tutupModalCrop()"
                        class="cursor-pointer rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 disabled:opacity-50">
                    Batal
                </button>
                <button type="button"
                        :disabled="mengupload"
                        x-on:click="simpanHasilCrop()"
                        class="cursor-pointer rounded-md bg-brand px-4 py-2 text-sm font-medium text-white hover:opacity-95 disabled:opacity-50">
                    <span x-show="!mengupload">Simpan Foto</span>
                    <span x-show="mengupload" x-cloak>Mengunggah...</span>
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('profilDosen', () => ({
        loading: true,
        mengupload: false,
        error: '',
        sukses: '',
        profil: {
            id_dosen: null,
            nama: '',
            nip: '',
            email: '',
            foto_profil: null,
            pangkat_terakhir: null,
        },
        riwayat: [],

        // State modal crop & zoom
        modalCropTerbuka: false,
        gambarSumber: null,
        namaFileAsli: 'foto-profil.jpg',
        mimeTipeOutput: 'image/jpeg',
        zoom: 1,
        offsetX: 0,
        offsetY: 0,
        sedangGeser: false,
        startX: 0,
        startY: 0,
        canvasSize: 280,
        cropDiameter: 220,

        headers(isMultipart = false) {
            const h = {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            };

            if (!isMultipart) {
                h['Content-Type'] = 'application/json';
            }

            const csrfMeta = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            if (csrfMeta) {
                h['X-CSRF-TOKEN'] = csrfMeta;
            } else {
                const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);
                if (match) {
                    h['X-XSRF-TOKEN'] = decodeURIComponent(match[1]);
                }
            }

            const token = localStorage.getItem('token') || localStorage.getItem('auth_token');
            if (token) {
                h['Authorization'] = `Bearer ${token}`;
            }

            return h;
        },

        async muatData() {
            this.loading = true;
            this.error = '';

            try {
                const [resProfil, resRiwayat] = await Promise.all([
                    fetch('/api/v1/dosen/profil', {
                        headers: this.headers(false),
                        credentials: 'same-origin',
                    }),
                    fetch('/api/v1/dosen/riwayat-pangkat', {
                        headers: this.headers(false),
                        credentials: 'same-origin',
                    }),
                ]);

                const jsonProfil = await resProfil.json().catch(() => ({}));
                const jsonRiwayat = await resRiwayat.json().catch(() => ({}));

                if (!resProfil.ok) {
                    throw new Error(jsonProfil.message || `Gagal memuat profil (${resProfil.status})`);
                }
                if (!resRiwayat.ok) {
                    throw new Error(jsonRiwayat.message || `Gagal memuat riwayat pangkat (${resRiwayat.status})`);
                }

                this.profil = jsonProfil.data ?? jsonProfil;
                this.riwayat = Array.isArray(jsonRiwayat.data)
                    ? jsonRiwayat.data
                    : (Array.isArray(jsonRiwayat) ? jsonRiwayat : []);
            } catch (err) {
                this.error = err.message || 'Terjadi kesalahan saat mengambil data dari API.';
            } finally {
                this.loading = false;
            }
        },

        bukaPilihFoto() {
            this.error = '';
            this.sukses = '';
            this.$refs.inputFoto?.click();
        },

        pilihFileUntukCrop(event) {
            const file = event.target.files?.[0] || null;
            if (!file) return;

            const tipeValid = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp'];
            if (!tipeValid.includes(file.type)) {
                this.error = 'Format foto profil harus jpg, jpeg, png, atau webp.';
                event.target.value = '';
                return;
            }

            if (file.size > 2 * 1024 * 1024) {
                this.error = 'Ukuran foto maksimal 2 MB.';
                event.target.value = '';
                return;
            }

            this.namaFileAsli = file.name;
            this.mimeTipeOutput = file.type === 'image/png' ? 'image/png' : 'image/jpeg';

            const reader = new FileReader();
            reader.onload = (e) => {
                const img = new Image();
                img.onload = () => {
                    this.gambarSumber = img;
                    this.modalCropTerbuka = true;
                    this.$nextTick(() => {
                        this.resetCrop();
                    });
                };
                img.src = e.target?.result;
            };
            reader.readAsDataURL(file);

            event.target.value = '';
        },

        resetCrop() {
            this.zoom = 1;
            this.offsetX = 0;
            this.offsetY = 0;
            this.gambarCanvas();
        },

        ubahZoom(delta) {
            const nilaiBaru = Math.min(3, Math.max(1, Number((this.zoom + delta).toFixed(2))));
            this.zoom = nilaiBaru;
            this.batasiOffset();
            this.gambarCanvas();
        },

        terapkanZoom() {
            this.zoom = Math.min(3, Math.max(1, Number(this.zoom) || 1));
            this.batasiOffset();
            this.gambarCanvas();
        },

        zoomDenganWheel(event) {
            const delta = event.deltaY < 0 ? 0.1 : -0.1;
            this.ubahZoom(delta);
        },

        mulaiGeser(event) {
            this.sedangGeser = true;
            this.startX = event.clientX - this.offsetX;
            this.startY = event.clientY - this.offsetY;
            event.currentTarget.setPointerCapture?.(event.pointerId);
        },

        geserGambar(event) {
            if (!this.sedangGeser) return;
            this.offsetX = event.clientX - this.startX;
            this.offsetY = event.clientY - this.startY;
            this.batasiOffset();
            this.gambarCanvas();
        },

        selesaiGeser() {
            this.sedangGeser = false;
        },

        hitungDimensiDasar() {
            if (!this.gambarSumber) {
                return { baseWidth: this.cropDiameter, baseHeight: this.cropDiameter };
            }

            const rasioGambar = this.gambarSumber.width / this.gambarSumber.height;
            if (rasioGambar >= 1) {
                return {
                    baseHeight: this.cropDiameter,
                    baseWidth: this.cropDiameter * rasioGambar,
                };
            }

            return {
                baseWidth: this.cropDiameter,
                baseHeight: this.cropDiameter / rasioGambar,
            };
        },

        batasiOffset() {
            const { baseWidth, baseHeight } = this.hitungDimensiDasar();
            const scaledW = baseWidth * this.zoom;
            const scaledH = baseHeight * this.zoom;

            const maxOffsetX = Math.max(0, (scaledW - this.cropDiameter) / 2);
            const maxOffsetY = Math.max(0, (scaledH - this.cropDiameter) / 2);

            this.offsetX = Math.min(maxOffsetX, Math.max(-maxOffsetX, this.offsetX));
            this.offsetY = Math.min(maxOffsetY, Math.max(-maxOffsetY, this.offsetY));
        },

        gambarCanvas() {
            const canvas = this.$refs.canvasCrop;
            if (!canvas || !this.gambarSumber) return;

            const ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, this.canvasSize, this.canvasSize);

            const { baseWidth, baseHeight } = this.hitungDimensiDasar();
            const scaledW = baseWidth * this.zoom;
            const scaledH = baseHeight * this.zoom;

            const drawX = (this.canvasSize - scaledW) / 2 + this.offsetX;
            const drawY = (this.canvasSize - scaledH) / 2 + this.offsetY;

            ctx.drawImage(this.gambarSumber, drawX, drawY, scaledW, scaledH);
        },

        tutupModalCrop() {
            if (this.mengupload) return;
            this.modalCropTerbuka = false;
            this.gambarSumber = null;
            this.sedangGeser = false;
        },

        async simpanHasilCrop() {
            if (!this.gambarSumber || this.mengupload) return;

            const outputSize = 600;
            const canvasHasil = document.createElement('canvas');
            canvasHasil.width = outputSize;
            canvasHasil.height = outputSize;

            const ctx = canvasHasil.getContext('2d');
            const skalaOutput = outputSize / this.cropDiameter;
            const { baseWidth, baseHeight } = this.hitungDimensiDasar();

            const scaledW = baseWidth * this.zoom * skalaOutput;
            const scaledH = baseHeight * this.zoom * skalaOutput;
            const drawX = (outputSize - scaledW) / 2 + (this.offsetX * skalaOutput);
            const drawY = (outputSize - scaledH) / 2 + (this.offsetY * skalaOutput);

            ctx.drawImage(this.gambarSumber, drawX, drawY, scaledW, scaledH);

            const blob = await new Promise((resolve) => {
                canvasHasil.toBlob(resolve, this.mimeTipeOutput, 0.9);
            });

            if (!blob) {
                this.error = 'Gagal memproses hasil crop foto.';
                return;
            }

            if (blob.size > 2 * 1024 * 1024) {
                this.error = 'Hasil crop melebihi batas ukuran 2 MB. Coba perkecil zoom atau pilih foto lebih ringan.';
                return;
            }

            const ekstensi = this.mimeTipeOutput === 'image/png' ? 'png' : 'jpg';
            const namaBersih = this.namaFileAsli.replace(/\.[^/.]+$/, '') || 'foto-profil';
            const fileCrop = new File([blob], `${namaBersih}.${ekstensi}`, { type: this.mimeTipeOutput });

            this.mengupload = true;
            this.error = '';
            this.sukses = '';

            try {
                const fd = new FormData();
                fd.append('foto_profil', fileCrop);
                fd.append('_method', 'PATCH');

                const res = await fetch('/api/v1/dosen/profil', {
                    method: 'POST',
                    headers: this.headers(true),
                    credentials: 'same-origin',
                    body: fd,
                });

                const json = await res.json().catch(() => ({}));

                if (!res.ok) {
                    const pesanValidasi = json.errors
                        ? Object.values(json.errors).flat().join(' ')
                        : null;
                    throw new Error(pesanValidasi || json.message || `Gagal memperbarui foto profil (${res.status})`);
                }

                this.profil = json.data ?? json;
                this.sinkronkanAvatarHeader(this.profil?.foto_profil);
                this.sukses = json.message || 'Foto profil berhasil diperbarui.';
                this.modalCropTerbuka = false;
                this.gambarSumber = null;
            } catch (err) {
                this.error = err.message || 'Terjadi kesalahan saat mengunggah foto profil.';
            } finally {
                this.mengupload = false;
            }
        },

        sinkronkanAvatarHeader(fotoUrl) {
            if (!fotoUrl) return;
            const cacheBustedUrl = `${fotoUrl}${fotoUrl.includes('?') ? '&' : '?'}t=${Date.now()}`;

            document.querySelectorAll('header img').forEach((img) => {
                if (img.closest('aside')) return;
                const srcLower = (img.getAttribute('src') || '').toLowerCase();
                if (srcLower.includes('logo') || srcLower.includes('ti')) return;
                img.src = cacheBustedUrl;
            });
        },

        inisial(nama) {
            if (!nama) return 'DS';
            return nama
                .trim()
                .split(/\s+/)
                .slice(0, 2)
                .map((bagian) => bagian[0]?.toUpperCase() || '')
                .join('');
        },

        formatPangkatTerakhir() {
            const pt = this.profil?.pangkat_terakhir;
            if (!pt) return '-';
            return pt.golongan ? `${pt.pangkat}, ${pt.golongan}` : (pt.pangkat || '-');
        },

        formatTanggal(tgl) {
            if (!tgl) return '-';
            const tanggal = new Date(tgl);
            if (Number.isNaN(tanggal.getTime())) return tgl;
            return tanggal.toLocaleDateString('id-ID', {
                day: 'numeric',
                month: 'long',
                year: 'numeric',
            });
        },
    }));
});
</script>
@endpush