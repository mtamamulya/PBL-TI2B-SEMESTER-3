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
    {{-- Judul halaman + tombol --}}
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

    {{-- Pesan error bila gagal memuat data API --}}
    <template x-if="error">
        <div role="alert" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" x-text="error"></div>
    </template>

    {{-- Kartu identitas --}}
    <section class="flex flex-col gap-8 rounded-lg bg-white p-5 shadow-sm">
        <div class="flex items-center gap-5">
            <div class="relative shrink-0">
                <template x-if="profil.foto_profil">
                    <img :src="profil.foto_profil" :alt="profil.nama || 'Foto Profil'" class="size-[88px] rounded-full object-cover">
                </template>
                <template x-if="!profil.foto_profil">
                    <span class="flex size-[88px] items-center justify-center rounded-full bg-red-600 text-3xl font-semibold text-white"
                          aria-hidden="true" x-text="inisial(profil.nama)"></span>
                </template>
            </div>

            <div class="flex min-w-0 flex-col gap-1">
                <div class="flex flex-wrap items-center gap-4">
                    <h2 class="text-2xl font-semibold" x-text="loading ? 'Memuat...' : (profil.nama || '-')"></h2>
                    <span class="rounded-full border border-green-200 bg-green-100 px-2.5 py-0.5 text-[13px] text-green-700">Aktif</span>
                </div>
                <p class="text-xl text-gray-400" x-text="profil.nip ? `NIP ${profil.nip}` : 'NIP -'"></p>
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
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('profilDosen', () => ({
        loading: true,
        error: '',
        profil: {
            id_dosen: null,
            nama: '',
            nip: '',
            email: '',
            foto_profil: null,
            pangkat_terakhir: null,
        },
        riwayat: [],

        headers() {
            const h = {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            };
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
                        headers: this.headers(),
                        credentials: 'same-origin',
                    }),
                    fetch('/api/v1/dosen/riwayat-pangkat', {
                        headers: this.headers(),
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