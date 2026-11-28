@extends('layouts.app')

@section('title', 'Detail Penelitian')

@section('content')

@php
    $sectionClass = 'flex flex-col gap-4.5 rounded-lg border border-gray-200 bg-white p-4.5';
    $numClass     = 'flex size-[26px] shrink-0 items-center justify-center rounded-full bg-brand text-[13px] font-semibold text-white';
    $idPenelitian = request()->route('id') ?? request()->route('penelitian');
@endphp

    <div
        x-data="detailPenelitian(@js($idPenelitian))"
        x-init="muatDetail()"
        class="flex flex-col gap-5"
    >
        {{-- Judul halaman + tombol kembali --}}
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex flex-col gap-1.5">
                <h1 class="text-3xl font-bold tracking-tight">Detail Penelitian</h1>
                <p class="text-2xl text-gray-400">Dashboard / Penelitian / Detail Penelitian</p>
            </div>
            <a href="{{ route('penelitian.index') }}"
               class="mt-2 flex h-11 items-center gap-2 rounded-md bg-blue-100 px-4 text-base font-medium text-brand hover:bg-blue-200">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg>
                Kembali
            </a>
        </div>

        <template x-if="errorMsg">
            <div role="alert" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" x-text="errorMsg"></div>
        </template>

        <template x-if="memuat">
            <div class="rounded-lg border border-gray-200 bg-white p-6 text-center text-sm text-gray-500 shadow-sm">
                Memuat detail penelitian dari server...
            </div>
        </template>

        {{-- Halaman ini hanya untuk membaca, jadi tidak ada formulir atau tombol simpan --}}
        <div x-show="!memuat && !errorMsg" x-cloak class="flex flex-col gap-5 rounded-lg bg-white p-4.5 shadow-sm">

            {{-- 1. INFORMASI PENELITIAN --}}
            <section class="{{ $sectionClass }}" aria-labelledby="h-info">
                <div class="flex items-center gap-2.5">
                    <span class="{{ $numClass }}">1</span>
                    <h2 id="h-info" class="text-[15px] font-semibold">Informasi Penelitian</h2>
                </div>

                <dl class="grid grid-cols-1 gap-4.5 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <div class="flex flex-col gap-1.5">
                            <dt class="text-sm text-gray-500">Judul penelitian</dt>
                            <dd class="rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-[15px] text-gray-900" x-text="p.judul || '-'"></dd>
                        </div>
                    </div>
                    <div class="md:col-span-2">
                        <div class="flex flex-col gap-1.5">
                            <dt class="text-sm text-gray-500">Afiliasi</dt>
                            <dd class="rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-[15px] text-gray-900" x-text="p.afiliasi || '-'"></dd>
                        </div>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <dt class="text-sm text-gray-500">Skim</dt>
                        <dd class="rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-[15px] text-gray-900" x-text="p.skim || '-'"></dd>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <dt class="text-sm text-gray-500">Ranah</dt>
                        <dd class="rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-[15px] text-gray-900" x-text="p.ranah || '-'"></dd>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <dt class="text-sm text-gray-500">Kelompok bidang</dt>
                        <dd class="rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-[15px] text-gray-900" x-text="p.bidang || '-'"></dd>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <dt class="text-sm text-gray-500">Penelitian sebelumnya (opsional)</dt>
                        <dd class="rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-[15px] text-gray-900" x-text="p.sebelumnya || 'Tidak ada, Penelitian baru'"></dd>
                    </div>
                </dl>
            </section>

            {{-- 2. PELAKSANAAN --}}
            <section class="{{ $sectionClass }}" aria-labelledby="h-laksana">
                <div class="flex items-center gap-2.5">
                    <span class="{{ $numClass }}">2</span>
                    <h2 id="h-laksana" class="text-[15px] font-semibold">Pelaksanaan</h2>
                </div>

                <dl class="grid grid-cols-1 gap-4.5 md:grid-cols-3">
                    <div class="flex flex-col gap-1.5">
                        <dt class="text-sm text-gray-500">Tahun anggaran</dt>
                        <dd class="rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-[15px] text-gray-900" x-text="p.tahun_anggaran || '-'"></dd>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <dt class="text-sm text-gray-500">Tahun pelaksanaan ke-</dt>
                        <dd class="rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-[15px] text-gray-900" x-text="p.tahun_ke || '-'"></dd>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <dt class="text-sm text-gray-500">Lama kegiatan (tahun)</dt>
                        <dd class="rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-[15px] text-gray-900" x-text="p.lama || '-'"></dd>
                    </div>

                    <div class="md:col-span-3">
                        <div class="flex flex-col gap-1.5">
                            <dt class="text-sm text-gray-500">Lokasi</dt>
                            <dd class="rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-[15px] text-gray-900" x-text="p.lokasi || '-'"></dd>
                        </div>
                    </div>

                    <div class="md:col-span-3 md:grid md:grid-cols-2 md:gap-4.5">
                        <div class="flex flex-col gap-1.5">
                            <dt class="text-sm text-gray-500">Nomor SK penugasan</dt>
                            <dd class="rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-[15px] text-gray-900" x-text="p.nomor_sk || '-'"></dd>
                        </div>
                        <div class="mt-4.5 flex flex-col gap-1.5 md:mt-0">
                            <dt class="text-sm text-gray-500">Tanggal SK penugasan</dt>
                            <dd class="rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-[15px] text-gray-900" x-text="p.tanggal_sk || '-'"></dd>
                        </div>
                    </div>
                </dl>
            </section>

            {{-- 3. PENDANAAN DAN MITRA --}}
            <section class="{{ $sectionClass }}" aria-labelledby="h-dana">
                <div class="flex items-center gap-2.5">
                    <span class="{{ $numClass }}">3</span>
                    <h2 id="h-dana" class="text-[15px] font-semibold">Pendanaan dan Mitra</h2>
                </div>

                <dl class="grid grid-cols-1 gap-4.5 md:grid-cols-2">
                    <div class="flex flex-col gap-1.5">
                        <dt class="text-sm text-gray-500">Dana DIKTI</dt>
                        <dd class="rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-[15px] text-gray-900" x-text="p.dana_dikti || '-'"></dd>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <dt class="text-sm text-gray-500">Dana perguruan tinggi</dt>
                        <dd class="rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-[15px] text-gray-900" x-text="p.dana_pt || '-'"></dd>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <dt class="text-sm text-gray-500">Dana institusi lain</dt>
                        <dd class="rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-[15px] text-gray-900" x-text="p.dana_lain || '-'"></dd>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <dt class="text-sm text-gray-500">In kind</dt>
                        <dd class="rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-[15px] text-gray-900" x-text="p.in_kind || '-'"></dd>
                    </div>
                    <div class="md:col-span-2">
                        <div class="flex flex-col gap-1.5">
                            <dt class="text-sm text-gray-500">Mitra (opsional)</dt>
                            <dd class="rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-[15px] text-gray-900" x-text="p.mitra || '-'"></dd>
                        </div>
                    </div>
                </dl>
            </section>

            {{-- 4. ANGGOTA PENELITIAN --}}
            <section class="{{ $sectionClass }}" aria-labelledby="h-anggota">
                <div class="flex items-center gap-2.5">
                    <span class="{{ $numClass }}">4</span>
                    <h2 id="h-anggota" class="text-[15px] font-semibold">Anggota Penelitian</h2>
                </div>

                <div class="overflow-x-auto rounded-md border border-gray-300">
                    <table class="w-full min-w-[520px] border-collapse text-sm">
                        <thead>
                            <tr class="bg-gray-100 text-left">
                                <th scope="col" class="w-1/3 px-2.5 py-2.5 font-medium">Nama</th>
                                <th scope="col" class="w-1/3 px-2.5 py-2.5 font-medium">Peran</th>
                                <th scope="col" class="w-1/3 px-2.5 py-2.5 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(a, idx) in anggota" :key="idx">
                                <tr class="border-t border-gray-300">
                                    <td class="px-2.5 py-2.5" x-text="a.nama || '-'"></td>
                                    <td class="px-2.5 py-2.5" x-text="a.peran || '-'"></td>
                                    <td class="px-2.5 py-2.5" x-text="a.status || '-'"></td>
                                </tr>
                            </template>
                            <tr x-show="anggota.length === 0">
                                <td colspan="3" class="px-2.5 py-4 text-center text-gray-500">Belum ada data anggota.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>

@endsection

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('detailPenelitian', (id) => ({
        memuat: true,
        errorMsg: '',
        p: {},
        anggota: [],
        formatRupiah(val) {
            if (val === null || val === undefined || val === '') return null;
            const num = String(val).replace(/\D/g, '');
            if (!num) return String(val);
            return 'Rp ' + num.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        },
        async muatDetail() {
            this.memuat = true;
            this.errorMsg = '';
            try {
                if (!id) {
                    throw new Error('ID penelitian tidak ditemukan di alamat URL.');
                }
                const res = await fetch(`/api/v1/dosen/penelitian/${id}`, {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });
                const json = await res.json();
                if (!res.ok) {
                    throw new Error(json.message || 'Gagal memuat detail penelitian.');
                }
                const d = json.data || {};
                const detailMap = {};
                if (Array.isArray(d.detail)) {
                    for (const item of d.detail) {
                        if (item.input) {
                            detailMap[item.input] = item.isian;
                        }
                    }
                }
                const skim = detailMap.skim || d.skim || '';
                const ranahMap = {
                    'Penelitian Dasar Unggulan Perguruan Tinggi': 'Penelitian Dasar',
                    'Penelitian Terapan': 'Penelitian Terapan',
                    'Penelitian Pengembangan': 'Penelitian Pengembangan',
                };

                this.p = {
                    judul: d.nama_kegiatan || d.judul || '',
                    afiliasi: detailMap.afiliasi || d.afiliasi || '',
                    skim,
                    ranah: detailMap.ranah || ranahMap[skim] || '',
                    bidang: detailMap.kelompok_bidang || d.kelompok_bidang || '',
                    sebelumnya: d.id_kegiatan_sebelumnya ? `ID #${d.id_kegiatan_sebelumnya}` : 'Tidak ada, Penelitian baru',
                    tahun_anggaran: detailMap.tahun_anggaran || (d.tanggal_mulai ? String(d.tanggal_mulai).slice(0, 4) : ''),
                    tahun_ke: detailMap.tahun_pelaksanaan_ke || '',
                    lama: detailMap.lama_kegiatan || detailMap.lama_kegiatan_tahun || '',
                    lokasi: d.lokasi || detailMap.lokasi || '',
                    nomor_sk: d.nomor_sk || '',
                    tanggal_sk: d.tanggal_sk || '',
                    dana_dikti: this.formatRupiah(detailMap.dana_dikti),
                    dana_pt: this.formatRupiah(detailMap.dana_pt || detailMap.dana_perguruan_tinggi),
                    dana_lain: this.formatRupiah(detailMap.dana_institusi_lain),
                    in_kind: this.formatRupiah(detailMap.in_kind),
                    mitra: detailMap.mitra || '',
                };

                this.anggota = Array.isArray(d.anggota)
                    ? d.anggota.map(a => ({
                        nama: a.nama || '-',
                        peran: a.peran ? (a.peran.charAt(0).toUpperCase() + a.peran.slice(1)) : '-',
                        status: a.status || 'Dosen',
                    }))
                    : [];
            } catch (err) {
                this.errorMsg = err.message || 'Terjadi kesalahan saat memuat detail.';
            } finally {
                this.memuat = false;
            }
        },
    }));
});
</script>
@endpush