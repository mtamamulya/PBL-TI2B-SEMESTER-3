@extends('layouts.app')

@section('title', 'Detail Pengajuan')

@php
    $asal  = $asal ?? 'dosen';
    $dosen = $asal === 'dosen';
    $pengajuanId = (int) ($id ?? request()->route('id') ?? 1);
    $pengaju = auth()->user()->dosen->nama ?? auth()->user()->email ?? 'Dosen';
    $peninjau = $dosen ? 'Admin' : 'Kuwat Santoso';
    $judulKeputusan = $dosen ? 'Keputusan Admin' : 'Keputusan Anda';
    $cardClass = 'flex flex-col gap-4 rounded-lg border border-gray-200 bg-white p-4';
@endphp

@section('content')

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex flex-col gap-1.5">
            <h1 class="text-3xl font-bold tracking-tight">Detail Pengajuan</h1>
            <p class="text-2xl text-gray-400">Dashboard / Pengajuan / {{ $dosen ? 'Dosen' : 'Mahasiswa' }} / Detail</p>
        </div>
        <a href="{{ $dosen ? route('pengajuan.dosen') : (url()->previous() !== url()->current() ? url()->previous() : route('pengajuan.mahasiswa')) }}"
           class="mt-2 flex h-11 items-center gap-2 rounded-md bg-blue-100 px-4 text-base font-medium text-brand hover:bg-blue-200">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg>
            Kembali
        </a>
    </div>

    <div class="grid grid-cols-1 gap-5 rounded-lg bg-white p-4.5 shadow-sm lg:grid-cols-2 lg:items-start"
         x-data="tinjauan(@js($pengaju), @js($peninjau), @js($pengajuanId), @js($dosen))">

        {{-- KOLOM KIRI: RIWAYAT STATUS (TIMELINE DARI DATABASE) --}}
        <section class="{{ $cardClass }}" aria-labelledby="h-riwayat">
            <h2 id="h-riwayat" class="text-xl font-semibold">Riwayat Status</h2>

            <p x-show="loading" class="py-4 text-sm text-gray-500">Memuat riwayat status...</p>

            <ol x-show="!loading" x-cloak>
                <template x-for="(r, idx) in riwayat" :key="r.id_timeline || idx">
                    <li class="flex gap-3">
                        <div class="flex flex-col items-center">
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-full text-white" :class="gaya[r.tipe].ikonBg">
                                <svg x-show="r.tipe === 'oranye'" class="size-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h4l11-11a2.8 2.8 0 0 0-4-4L4 16zM13.5 6.5l4 4"/></svg>
                                <svg x-show="r.tipe === 'merah'" class="size-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                                <svg x-show="r.tipe === 'biru' || r.tipe === 'hijau'" class="size-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
                            </span>
                            <span x-show="idx < riwayat.length - 1" class="min-h-6 w-0.5 flex-1 bg-brand" aria-hidden="true"></span>
                        </div>

                        <div class="flex min-w-0 flex-1 flex-col gap-1 text-[15px]" :class="idx < riwayat.length - 1 ? 'pb-5' : ''">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="font-semibold" :class="gaya[r.tipe].judulWarna" x-text="r.judul"></h3>
                                <span x-show="idx === riwayat.length - 1" class="rounded-full bg-lime-100 px-2 py-0.5 text-xs text-lime-700">Status saat ini</span>
                            </div>
                            <p class="mt-1" x-text="r.waktu"></p>
                            <p x-text="`Oleh ${r.oleh}`"></p>

                            <template x-if="r.catatan">
                                <p class="mt-2 rounded-md px-3 py-2.5 text-xs" :class="gaya[r.tipe].catatanBg" x-text="r.catatan"></p>
                            </template>
                        </div>
                    </li>
                </template>
            </ol>
        </section>

        {{-- KOLOM KANAN: DETAIL, CATATAN REVISI & AJUKAN ULANG --}}
        <div class="flex flex-col gap-5">

            {{-- JIKA STATUS DIREVISI: TAMPILKAN CATATAN ADMIN --}}
            <template x-if="isDosen && !loading && ['Diterima', 'Selesai'].includes(statusSekarang)">
                <a href="{{ route('pengajuan.dosen.surat', $pengajuanId) }}" target="_blank" rel="noopener"
                   class="justify-self-start rounded-lg bg-brand px-4 py-2 text-white">Buka Surat</a>
            </template>
            <template x-if="statusSekarang === 'Direvisi'">
                <section class="{{ $cardClass }}" aria-labelledby="h-keputusan-revisi">
                    <h2 id="h-keputusan-revisi" class="text-xl font-semibold">{{ $judulKeputusan }}</h2>
                    <div class="flex flex-col gap-2 px-4 pb-3">
                        <p class="text-[15px] font-medium">Catatan revisi dari reviewer</p>
                        <p class="whitespace-pre-wrap break-words rounded-md bg-gray-50 px-3 py-2.5 text-[15px] text-gray-900" x-text="catatanRevisiTerakhir"></p>
                    </div>
                </section>
            </template>

            <template x-if="isDosen && !loading && statusSekarang === 'Draft'">
                <section class="{{ $cardClass }}">
                    <button type="button" @click="kirimPengajuan()" :disabled="submitting" class="rounded-lg bg-brand px-4 py-2 text-white">Ajukan ke admin</button>
                    <p x-show="galat" x-text="galat" role="alert" class="text-sm text-red-600"></p>
                </section>
            </template>

            {{-- FORM AJUKAN ULANG UNTUK DOSEN SAAT STATUS DIREVISI --}}
            <template x-if="isDosen && statusSekarang === 'Direvisi'">
                <section class="{{ $cardClass }}" aria-labelledby="h-perbaikan">
                    <h2 id="h-perbaikan" class="text-xl font-semibold">Ajukan Ulang Pengajuan</h2>

                    <div x-show="!kembali" class="flex flex-col gap-4 px-4 pb-3">
                        <div class="flex flex-col gap-2">
                            <label for="catatan-perbaikan" class="text-[15px]">Catatan perbaikan (opsional)</label>
                            <textarea id="catatan-perbaikan" rows="3" x-model="catatanPerbaikan"
                                      placeholder="Tuliskan ringkasan perbaikan yang telah dilakukan..."
                                      class="min-h-20 w-full resize-y rounded-md border border-gray-300 bg-white px-3 py-2.5 text-[15px]"></textarea>
                        </div>

                        <p x-show="galat" x-cloak x-text="galat" role="alert" class="text-sm text-red-600"></p>

                        <button type="button" x-on:click="kirimPengajuan()" :disabled="submitting"
                                class="flex h-10 w-full cursor-pointer items-center justify-center rounded-md bg-brand text-base text-white hover:bg-blue-700 disabled:opacity-50">
                            <span x-text="submitting ? 'Mengirim...' : 'Ajukan kembali'"></span>
                        </button>
                        <a class="text-brand underline" href="{{ route('dosen.kegiatan.edit', request()->route('id')) }}">Perbaiki kegiatan atau ganti lampiran</a>
                    </div>

                    <p x-show="kembali" x-cloak class="px-4 pb-3 text-[15px] text-green-700">
                        Pengajuan sudah diajukan kembali dan menunggu review admin.
                    </p>
                </section>
            </template>

            {{-- INFORMASI KEGIATAN --}}
            <section class="{{ $cardClass }}" aria-labelledby="h-info">
                <div class="flex items-center justify-between gap-3">
                    <h2 id="h-info" class="text-xl font-semibold">Informasi Kegiatan</h2>
                    <span class="rounded-md border px-2 py-0.5 text-xs" :class="warnaStatus[statusSekarang] || 'border-gray-200 bg-gray-100 text-gray-700'"
                          x-text="statusSekarang"></span>
                </div>

                <dl class="grid grid-cols-2 gap-x-4 gap-y-5 px-4 text-[15px]">
                    <div class="col-span-2 flex flex-col gap-2"><dt>Nama kegiatan</dt><dd class="font-medium" x-text="info.nama || '-'"></dd></div>
                    <div class="flex flex-col gap-2"><dt>Jenis kegiatan</dt><dd class="font-medium">Kegiatan Dosen</dd></div>
                    <div class="flex flex-col gap-2"><dt>Kategori</dt><dd class="font-medium" x-text="info.kategori || '-'"></dd></div>
                    <div class="col-span-2 flex flex-col gap-2"><dt>Diajukan oleh</dt><dd class="font-medium" x-text="pengaju"></dd></div>
                </dl>
            </section>
        </div>
    </div>

@endsection

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('tinjauan', (pengaju, peninjau, pengajuanId, isDosen) => ({
            pengaju,
            peninjau,
            isDosen,
            loading: true,
            submitting: false,
            statusSekarang: 'Diajukan',
            catatanRevisiTerakhir: '',
            catatanPerbaikan: '',
            galat: '',
            kembali: false,
            riwayat: [],
            info: { nama: '', kategori: '' },

            warnaStatus: {
                Draft:    'border-gray-200 bg-gray-100 text-gray-700',
                Diajukan: 'border-blue-200 bg-blue-100 text-blue-700',
                Direview: 'border-blue-200 bg-blue-100 text-blue-700',
                Direvisi: 'border-orange-200 bg-orange-100 text-orange-700',
                Ditolak:  'border-red-200 bg-red-100 text-red-600',
                Diterima: 'border-green-200 bg-green-100 text-green-700',
                Selesai:  'border-green-200 bg-green-100 text-green-700',
            },
            gaya: {
                biru:   { ikonBg: 'bg-brand',      judulWarna: 'text-gray-900',   catatanBg: 'bg-gray-100 text-gray-600' },
                oranye: { ikonBg: 'bg-orange-400', judulWarna: 'text-orange-800', catatanBg: 'bg-orange-300 text-gray-900' },
                merah:  { ikonBg: 'bg-red-500',    judulWarna: 'text-red-600',    catatanBg: 'bg-red-400 text-gray-900' },
                hijau:  { ikonBg: 'bg-lime-500',   judulWarna: 'text-lime-600',   catatanBg: 'bg-gray-100 text-gray-600' },
            },

            init() {
                this.ambilTimeline();
            },

            formatStatus(raw) {
                if (!raw) return 'Draft';
                return raw.charAt(0).toUpperCase() + raw.slice(1).toLowerCase();
            },

            tipeWarna(status) {
                if (status === 'Direvisi') return 'oranye';
                if (status === 'Ditolak') return 'merah';
                if (status === 'Diterima' || status === 'Selesai') return 'hijau';
                return 'biru';
            },

            ambilTimeline() {
                this.loading = true;
                fetch(`/api/v1/dosen/pengajuan/${pengajuanId}/timeline`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                })
                .then(res => {
                    if (!res.ok) throw new Error(`HTTP ${res.status}`);
                    return res.json();
                })
                .then(res => {
                    if (res.status === 'success' && res.data) {
                        this.info.nama = res.data.nama_kegiatan;
                        this.info.kategori = res.data.kategori?.nama_kategori || 'Umum';
                        this.statusSekarang = this.formatStatus(res.data.status_terkini);

                        this.riwayat = (res.data.riwayat_tahapan || []).map(item => {
                            const judul = this.formatStatus(item.status);
                            const tgl = item.tanggal_status
                                ? new Date(item.tanggal_status).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) + ' · ' +
                                  new Date(item.tanggal_status).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }).replace(':', '.')
                                : '-';

                            if (judul === 'Direvisi' && item.catatan_revisi) {
                                this.catatanRevisiTerakhir = item.catatan_revisi;
                            }

                            return {
                                id_timeline: item.id_timeline,
                                judul: judul,
                                tipe: this.tipeWarna(judul),
                                waktu: tgl,
                                oleh: judul === 'Diajukan' ? this.pengaju : this.peninjau,
                                catatan: item.catatan_revisi
                            };
                        });
                    }
                })
                .catch(err => console.error('Gagal mengambil timeline:', err))
                .finally(() => { this.loading = false; });
            },

            kirimPengajuan() {
                if (this.submitting) return;
                const aksi = this.statusSekarang === 'Direvisi' ? 'ajukan-ulang' : 'ajukan';
                this.galat = '';
                this.submitting = true;

                fetch(`/api/v1/dosen/pengajuan/${pengajuanId}/${aksi}`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': @js(csrf_token()),
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        catatan: this.catatanPerbaikan.trim() || null
                    })
                })
                .then(async response => {
                    const result = await response.json();
                    if (!response.ok) {
                        throw new Error(Object.values(result.errors || {}).flat()[0] || result.message || 'Gagal mengirim pengajuan.');
                    }
                    return result;
                })
                .then(result => {
                    if (result.status === 'success') {
                        this.kembali = true;
                        this.catatanPerbaikan = '';
                        this.$dispatch('tampil-alert', { tipe: 'sukses', judul: 'Pengajuan dikirim', pesan: 'Menunggu review admin.' });
                        this.ambilTimeline(); // Muat ulang timeline terbaru dari database
                    }
                })
                .catch(err => {
                    this.galat = err.message || 'Tidak dapat menghubungi server.';
                })
                .finally(() => {
                    this.submitting = false;
                });
            }
        }));
    });
</script>
@endpush
