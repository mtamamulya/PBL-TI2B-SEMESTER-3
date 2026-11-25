{{--
Form pengajuan berita. Dipakai bersama oleh halaman Ajukan Berita (create) dan Edit Berita (edit).
--}}
@php
// Data hardcoded untuk kategori (sementara, nanti bisa dari API)
$daftarKategori = ['Prestasi Mahasiswa', 'Prestasi Dosen', 'Kunjungan Industri', 'Info Akademik', 'Lomba', 'Magang'];

$awal = [
    'sumber'   => $b['sumber'] ?? '',
    'kategori' => $b['kategori'] ?? '',
    'judul'    => $b['judul'] ?? '',
    'isi'      => $b['isi'] ?? '',
];
$inputClass   = 'h-11 w-full rounded-lg border border-gray-200 bg-white px-3.5 text-[15px] text-gray-900 placeholder:text-gray-400 disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-400';
$selectClass  = 'h-11 w-full appearance-none rounded-lg border border-gray-200 bg-white pr-10 pl-3.5 text-[15px] text-gray-900 disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-400';
$labelClass   = 'text-[15px] font-medium';
$hintClass    = 'text-[13px] text-gray-500';
$radioKartu   = 'flex cursor-pointer items-center gap-3 rounded-lg border border-gray-200 bg-white px-4 py-3 text-[15px] has-[:checked]:border-brand has-[:checked]:bg-blue-50 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand/40';
$sectionClass = 'flex flex-col gap-4.5 rounded-lg border border-gray-200 bg-white p-4.5';
$numClass     = 'flex size-[26px] shrink-0 items-center justify-center rounded-full bg-brand text-[13px] font-semibold text-white';
$chevron      = '<svg class="pointer-events-none absolute top-1/2 right-3.5 size-4 -translate-y-1/2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>';
@endphp

{{-- Pastikan ada meta tag CSRF token --}}
@if (!request()->hasHeader('X-CSRF-TOKEN'))
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endif

<form x-data="formBerita(@js($awal))" x-on:submit.prevent="ajukan()"
      method="POST" action="#" enctype="multipart/form-data"
      class="flex flex-col gap-5 rounded-lg bg-white p-4.5 shadow-sm">
    @csrf
    
    {{-- 1. SUMBER KEGIATAN --}}
    <section class="{{ $sectionClass }}" aria-labelledby="h-sumber">
        <div class="flex items-center gap-2.5">
            <span class="{{ $numClass }}">1</span>
            <div>
                <h2 id="h-sumber" class="text-[15px] font-semibold">Sumber Kegiatan</h2>
                <p class="{{ $hintClass }}">Kegiatan terkait bersifat opsional. Anda tetap dapat membuat berita secara mandiri.</p>
            </div>
        </div>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <label class="{{ $radioKartu }}">
                <input type="radio" name="sumber" value="kegiatan" x-model="sumber" required class="size-4 accent-[#1f66e5]">
                Dari kegiatan sebelumnya
            </label>
            <label class="{{ $radioKartu }}">
                <input type="radio" name="sumber" value="mandiri" x-model="sumber" required class="size-4 accent-[#1f66e5]">
                Tanpa kegiatan sebelumnya
            </label>
        </div>
        <div class="grid grid-cols-1 gap-4.5 md:grid-cols-2">
            <div class="flex flex-col gap-2">
                <label for="kegiatan-lama" class="{{ $labelClass }}">Kegiatan sebelumnya <span class="font-normal text-gray-500">(opsional)</span></label>
                <div class="relative">
                    <svg class="pointer-events-none absolute top-1/2 left-3 size-5 -translate-y-1/2 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M16.5 16.5L21 21"/></svg>
                    <input id="kegiatan-lama" name="kegiatan_sebelumnya" type="text" list="daftar-kegiatan"
                           :disabled="sumber !== 'kegiatan'" placeholder="Cari dan pilih kegiatan" class="{{ $inputClass }} pl-10">
                    <datalist id="daftar-kegiatan">
                        <template x-for="k in kegiatanList" :key="k.id">
                            <option :value="k.nama"></option>
                        </template>
                    </datalist>
                </div>
                <p class="{{ $hintClass }}">Aktif jika memilih 'Dari kegiatan sebelumnya'. Contoh: KMIPN VIII 2026 • Lomba</p>
            </div>
            <div class="flex flex-col gap-2">
                <label for="kategori" class="{{ $labelClass }}">Kategori Kegiatan</label>
                <div class="relative">
                    <select id="kategori" name="kategori" x-model="kategori" :disabled="sumber !== 'mandiri'" :required="sumber === 'mandiri'" class="{{ $selectClass }}">
                        <option value="">Pilih kategori kegiatan</option>
                        @foreach ($daftarKategori as $k)
                            <option value="{{ $k }}">{{ $k }}</option>
                        @endforeach
                    </select>
                    {!! $chevron !!}
                </div>
                <p class="{{ $hintClass }}">Aktif ketika memilih 'Tanpa kegiatan sebelumnya'.</p>
            </div>
        </div>
    </section>

    {{-- 2. INFORMASI BERITA --}}
    <section class="{{ $sectionClass }}" aria-labelledby="h-info">
        <div class="flex items-center gap-2.5">
            <span class="{{ $numClass }}">2</span>
            <h2 id="h-info" class="text-[15px] font-semibold">Informasi Berita</h2>
        </div>
        <div class="grid grid-cols-1 gap-4.5">
            <div class="flex flex-col gap-2">
                <label for="judul" class="{{ $labelClass }}">Judul Berita</label>
                <input id="judul" name="judul" type="text" required x-model="judul"
                       placeholder="Mahasiswa TRK Borong Juara di Kompetisi IT Nasional" class="{{ $inputClass }}">
            </div>
            <div class="flex flex-col gap-2">
                <label for="isi" class="{{ $labelClass }}">Isi Berita</label>
                <div wire:ignore>
                    <textarea id="isi" name="isi" x-ref="editorIsi" x-model="isi"
                              placeholder="Tuliskan isi berita di sini"
                              class="w-full rounded-lg border border-gray-200 bg-white px-3.5 py-2.5 text-[15px] text-gray-900"></textarea>
                </div>
                <p class="{{ $hintClass }}" :class="panjangTeks > 1000 ? 'text-red-600 font-medium' : ''">
                    Maksimal 1.000 karakter • <span x-text="panjangTeks.toLocaleString('id-ID')"></span>/1.000
                </p>
            </div>
        </div>
    </section>

    {{-- 3. FOTO SAMPUL --}}
    <section class="{{ $sectionClass }}" aria-labelledby="h-foto">
        <div class="flex items-center gap-2.5">
            <span class="{{ $numClass }}">3</span>
            <div>
                <h2 id="h-foto" class="text-[15px] font-semibold">Foto Sampul Berita</h2>
                <p class="{{ $hintClass }}">Unggah foto yang menunjukkan nama, capaian, atau bukti kegiatan.</p>
            </div>
        </div>
        <div class="overflow-hidden rounded-lg border border-gray-200">
            <div class="bg-[#02215c] px-3.5 py-3 text-white">
                <h3 class="text-sm font-semibold">Foto Sampul</h3>
            </div>
            <div class="flex flex-col gap-2 p-3.5">
                <span class="{{ $labelClass }}">File</span>
                <label class="relative flex cursor-pointer flex-col items-center gap-2 rounded-md border border-dashed border-brand bg-white px-4 py-5 text-center hover:bg-blue-50 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand">
                    <input type="file" name="foto" class="sr-only" accept=".jpg,.jpeg,.png,.webp"
                           x-on:change="pilihFile($event)">
                    <span class="flex items-center gap-2 text-[13px] font-medium text-brand">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 18a4.5 4.5 0 0 1-.6-8.96A6 6 0 0 1 18 8.5a4 4 0 0 1-.5 7.97M12 20v-8M9 15l3-3 3 3"/></svg>
                        <span x-text="namaFile || 'Pilih file dari perangkat'"></span>
                    </span>
                    <span class="text-xs text-gray-500">jpg, jpeg, png, webp, maksimal 2 MB.</span>
                </label>
                <p x-show="galatFile" x-cloak x-text="galatFile" role="alert" class="text-sm text-red-600"></p>
            </div>
        </div>
    </section>

    {{-- TOMBOL --}}
    <div class="flex flex-wrap items-center justify-end gap-3 pt-4">
        <a href="{{ route('berita.index') }}"
           class="flex h-11 items-center rounded-md bg-red-500 px-6 text-base text-white hover:bg-red-600">Batal</a>
        <button type="button" x-on:click="simpanDraft()" :disabled="loading"
                class="h-11 cursor-pointer rounded-md bg-blue-100 px-6 text-base text-brand hover:bg-blue-200 disabled:opacity-50">Simpan Draft</button>
        <button type="submit" :disabled="loading"
                class="h-11 cursor-pointer rounded-md bg-brand px-6 text-base text-white hover:bg-blue-700 disabled:opacity-50">Ajukan Berita</button>
    </div>
</form>

@push('scripts')
<script src="https://cdn.ckeditor.com/ckeditor5/39.0.1/classic/ckeditor.js"></script>
@include('dosen.berita._api')
@include('dosen.berita._form-script')
@endpush