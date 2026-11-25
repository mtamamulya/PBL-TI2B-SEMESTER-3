@php
    $inputClass   = 'h-11 w-full rounded-lg border border-gray-200 bg-white px-3.5 text-[15px] text-gray-900 placeholder:text-gray-400';
    $selectClass  = 'h-11 w-full appearance-none rounded-lg border border-gray-200 bg-white pr-10 pl-3.5 text-[15px] text-gray-900';
    $labelClass   = 'text-[15px] font-medium';
    $sectionClass = 'flex flex-col gap-4.5 rounded-lg border border-gray-200 bg-white p-4.5';
    $numClass     = 'flex size-[26px] shrink-0 items-center justify-center rounded-full bg-brand text-[13px] font-semibold text-white';
    $chevron      = '<svg class="pointer-events-none absolute top-1/2 right-3.5 size-4 -translate-y-1/2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>';

    $tgl = fn ($v) => substr((string) $v, 0, 10);

    $awalSkim = $nilai['skim'] ?? (is_string(data_get($k, 'skim')) ? data_get($k, 'skim') : '');
    $awalBidang = $nilai['kelompok_bidang'] ?? (is_string(data_get($k, 'kelompok_bidang')) ? data_get($k, 'kelompok_bidang') : '');
    $skimDipilih = old('detail.skim', $awalSkim);
    $bidangDipilih = old('detail.kelompok_bidang', $awalBidang);
@endphp

@if ($errors->any())
    <div role="alert" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">
        <p class="font-medium">Data belum bisa disimpan:</p>
        <ul class="mt-1 list-disc pl-5">
            @foreach ($errors->all() as $galat)
                <li>{{ $galat }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ $aksi }}" x-data="formPengabdianDosen(@js($kandidat), @js(old('anggota', $anggotaAwal)))"
      class="flex flex-col gap-5 rounded-lg bg-white p-4.5 shadow-sm">
    @csrf
    @if ($metode !== 'POST')
        @method($metode)
    @endif
    <input type="hidden" name="id_kategori" value="{{ $idKategori }}">

    {{-- 1. INFORMASI PENGABDIAN --}}
    <section class="{{ $sectionClass }}" aria-labelledby="h-info">
        <div class="flex items-center gap-2.5">
            <span class="{{ $numClass }}">1</span>
            <h2 id="h-info" class="text-[15px] font-semibold">Informasi Pengabdian</h2>
        </div>

        <div class="grid grid-cols-1 gap-4.5 md:grid-cols-2">
            <div class="flex flex-col gap-2 md:col-span-2">
                <label for="nama_kegiatan" class="{{ $labelClass }}">Nama kegiatan</label>
                <input id="nama_kegiatan" name="nama_kegiatan" type="text" required
                       value="{{ old('nama_kegiatan', data_get($k, 'nama_kegiatan')) }}"
                       placeholder="Pelatihan Web Desa" class="{{ $inputClass }}">
            </div>

            @if ($adaAfiliasi)
                <div class="flex flex-col gap-2 md:col-span-2">
                    <label for="penyelenggara" class="{{ $labelClass }}">Afiliasi</label>
                    <input id="penyelenggara" name="penyelenggara" type="text"
                           value="{{ old('penyelenggara', $nilai['penyelenggara'] ?? '') }}"
                           placeholder="Program Studi / Jurusan / Perguruan Tinggi" class="{{ $inputClass }}">
                </div>
            @endif

            @if (count($opsiSkim) > 0)
                <div class="flex flex-col gap-2">
                    <label for="skim" class="{{ $labelClass }}">Skim</label>
                    <div class="relative">
                        <select id="skim" name="detail[skim]" required class="{{ $selectClass }}">
                            <option value="">Pilih skim</option>
                            @foreach ($opsiSkim as $o)
                                <option value="{{ $o }}" @selected($skimDipilih === $o)>{{ $o }}</option>
                            @endforeach
                        </select>
                        {!! $chevron !!}
                    </div>
                </div>
            @endif

            @if (count($opsiBidang) > 0)
                <div class="flex flex-col gap-2">
                    <label for="kelompok_bidang" class="{{ $labelClass }}">Kelompok bidang</label>
                    <div class="relative">
                        <select id="kelompok_bidang" name="detail[kelompok_bidang]" required class="{{ $selectClass }}">
                            <option value="">Pilih kelompok bidang</option>
                            @foreach ($opsiBidang as $o)
                                <option value="{{ $o }}" @selected($bidangDipilih === $o)>{{ $o }}</option>
                            @endforeach
                        </select>
                        {!! $chevron !!}
                    </div>
                </div>
            @endif

            <div class="flex flex-col gap-2">
                <label for="tanggal_mulai" class="{{ $labelClass }}">Tanggal mulai</label>
                <input id="tanggal_mulai" name="tanggal_mulai" type="date" required
                       value="{{ old('tanggal_mulai', $tgl(data_get($k, 'tanggal_mulai'))) }}" class="{{ $inputClass }}">
            </div>
            <div class="flex flex-col gap-2">
                <label for="tanggal_selesai" class="{{ $labelClass }}">Tanggal selesai</label>
                <input id="tanggal_selesai" name="tanggal_selesai" type="date" required
                       value="{{ old('tanggal_selesai', $tgl(data_get($k, 'tanggal_selesai'))) }}" class="{{ $inputClass }}">
            </div>

            <div class="flex flex-col gap-2">
                <label for="nomor_sk" class="{{ $labelClass }}">Nomor SK penugasan</label>
                <input id="nomor_sk" name="detail[nomor_sk]" type="text" required
                       value="{{ old('detail.nomor_sk', data_get($k, 'nomor_sk')) }}" placeholder="Nomor SK" class="{{ $inputClass }}">
            </div>
            <div class="flex flex-col gap-2">
                <label for="tanggal_sk" class="{{ $labelClass }}">Tanggal SK penugasan</label>
                <input id="tanggal_sk" name="detail[tanggal_sk]" type="date" required
                       value="{{ old('detail.tanggal_sk', $tgl(data_get($k, 'tanggal_sk'))) }}" class="{{ $inputClass }}">
            </div>
        </div>
    </section>

    {{-- 2. ISIAN PENGABDIAN: daftar isian dari definisi kategori (API), jadi mengikuti isi database --}}
    @if (count($isian) > 0)
        <section class="{{ $sectionClass }}" aria-labelledby="h-isian">
            <div class="flex items-center gap-2.5">
                <span class="{{ $numClass }}">2</span>
                <h2 id="h-isian" class="text-[15px] font-semibold">Isian Pengabdian</h2>
            </div>

            <div class="grid grid-cols-1 gap-4.5 md:grid-cols-2">
                @foreach ($isian as $f)
                    @php $isi = old('detail.'.$f['nama'], $nilai[$f['nama']] ?? ''); @endphp
                    <div class="flex flex-col gap-2">
                        <label for="d-{{ $f['nama'] }}" class="{{ $labelClass }}">{{ $f['label'] }}@unless ($f['wajib']) (opsional)@endunless</label>

                        @if ($f['tipe'] === 'rupiah')
                            <input id="d-{{ $f['nama'] }}" name="detail[{{ $f['nama'] }}]" type="text" inputmode="numeric"
                                   placeholder="Rp" value="{{ $isi }}" @required($f['wajib'])
                                   x-on:input="$event.target.value = $event.target.value.replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.')"
                                   class="{{ $inputClass }}">
                        @elseif ($f['tipe'] === 'angka')
                            <input id="d-{{ $f['nama'] }}" name="detail[{{ $f['nama'] }}]" type="number" min="0" inputmode="numeric"
                                   value="{{ $isi }}" @required($f['wajib']) class="{{ $inputClass }}">
                        @elseif ($f['tipe'] === 'tanggal')
                            <input id="d-{{ $f['nama'] }}" name="detail[{{ $f['nama'] }}]" type="date"
                                   value="{{ $tgl($isi) }}" @required($f['wajib']) class="{{ $inputClass }}">
                        @else
                            <input id="d-{{ $f['nama'] }}" name="detail[{{ $f['nama'] }}]" type="text"
                                   value="{{ $isi }}" @required($f['wajib']) class="{{ $inputClass }}">
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- 3. ANGGOTA PENGABDIAN. Ketua = akun yang login (otomatis dari backend), tidak ikut dikirim. --}}
    <section class="{{ $sectionClass }}" aria-labelledby="h-anggota">
        <div class="flex items-center gap-2.5">
            <span class="{{ $numClass }}">{{ count($isian) > 0 ? 3 : 2 }}</span>
            <h2 id="h-anggota" class="text-[15px] font-semibold">Anggota Pengabdian</h2>
        </div>

        <div class="flex flex-wrap gap-3">
            <div class="relative min-w-56 flex-[1_1_260px]">
                <label for="cari" class="sr-only">Cari dosen atau mahasiswa</label>
                <input id="cari" type="text" list="daftar-kandidat" x-model="cari"
                       x-on:keydown.enter.prevent="tambah()"
                       placeholder="Cari dosen atau mahasiswa..." class="{{ $inputClass }}">
                <datalist id="daftar-kandidat">
                    @foreach ($kandidat as $nama => $status)
                        <option value="{{ $nama }}">{{ $status }}</option>
                    @endforeach
                </datalist>
            </div>

            <div class="relative w-full sm:w-44">
                <label for="status-anggota" class="sr-only">Status anggota</label>
                <select id="status-anggota" x-model="status" class="{{ $selectClass }}">
                    <option value="">Pilih status</option>
                    <option>Dosen</option>
                    <option>Mahasiswa</option>
                </select>
                {!! $chevron !!}
            </div>

            <button type="button" x-on:click="tambah()"
                    class="flex h-11 cursor-pointer items-center gap-2 rounded-lg bg-brand px-5 text-base font-medium text-white hover:bg-blue-700">
                Tambah Anggota
            </button>
        </div>

        <template x-if="errorAnggota">
            <p role="alert" class="text-sm text-red-600" x-text="errorAnggota"></p>
        </template>

        <div class="overflow-x-auto rounded-md border border-gray-300">
            <table class="w-full min-w-[480px] border-collapse text-sm">
                <thead>
                    <tr class="bg-gray-100 text-left">
                        <th scope="col" class="w-1/3 px-2.5 py-2.5 font-medium">Nama</th>
                        <th scope="col" class="w-1/3 px-2.5 py-2.5 font-medium">Peran</th>
                        <th scope="col" class="w-1/3 px-2.5 py-2.5 font-medium">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-t border-gray-300">
                        <td class="px-2.5 py-2.5">{{ $ketua ?? 'Anda' }}</td>
                        <td class="px-2.5 py-2.5">Ketua</td>
                        <td class="px-2.5 py-2.5 text-gray-400">-</td>
                    </tr>
                    <template x-for="(a, i) in anggota" :key="a.nama + '-' + i">
                        <tr class="border-t border-gray-300">
                            <td class="px-2.5 py-2.5">
                                <span x-text="a.nama"></span>
                                <input type="hidden" :name="`anggota[${i}][id_user]`" :value="a.id_user" :disabled="!a.id_user">
                                <input type="hidden" :name="`anggota[${i}][nama]`" :value="a.nama">
                                <input type="hidden" :name="`anggota[${i}][status]`" :value="a.status">
                            </td>
                            <td class="px-2.5 py-2.5">Anggota</td>
                            <td class="px-2.5 py-1.5">
                                <button type="button" x-on:click="hapus(i)" :aria-label="`Hapus ${a.nama}`"
                                        class="flex size-8 cursor-pointer items-center justify-center rounded-lg bg-red-100 text-red-500 hover:bg-red-200">
                                    <svg class="size-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 7h14M10 7V5h4v2M7 7l1 12h8l1-12M10 11v5M14 11v5"/></svg>
                                </button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </section>

    {{-- TOMBOL. Dokumen pendukung dikelola modul Dokumen (lihat halaman Detail). --}}
    <div class="flex flex-wrap items-center justify-end gap-3">
        <a href="{{ route('pengabdian.index') }}"
           class="flex h-11 items-center rounded-md bg-red-500 px-6 text-base text-white hover:bg-red-600">Batal</a>
        <button type="submit"
                class="h-11 cursor-pointer rounded-md bg-brand px-6 text-base text-white hover:bg-blue-700">Simpan</button>
    </div>
</form>

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('formPengabdianDosen', (kandidat, awal) => ({
            anggota: (awal || []).map(a => ({ id_user: a.id_user || '', nama: a.nama || '', status: a.status || 'Dosen' })),
            cari: '',
            status: '',
            errorAnggota: '',

            init() {
                // Nama yang diketik ada di daftar: status terisi otomatis (masih bisa diubah)
                this.$watch('cari', v => {
                    const nama = Object.keys(kandidat).find(n => n.toLowerCase() === v.trim().toLowerCase());
                    if (nama) this.status = kandidat[nama];
                });
            },

            tambah() {
                const nama = this.cari.trim();
                if (!nama) {
                    this.errorAnggota = 'Ketik nama dosen atau mahasiswa terlebih dahulu.';
                    return;
                }
                if (!this.status) {
                    this.errorAnggota = 'Pilih status anggota (Dosen atau Mahasiswa) terlebih dahulu.';
                    return;
                }
                if (this.anggota.some(a => a.nama.toLowerCase() === nama.toLowerCase())) {
                    this.errorAnggota = 'Anggota ini sudah ada di daftar.';
                    return;
                }
                this.anggota.push({ id_user: '', nama, status: this.status });
                this.cari = '';
                this.status = '';
                this.errorAnggota = '';
            },

            hapus(i) { this.anggota.splice(i, 1); },
        }));
    });
</script>
@endpush