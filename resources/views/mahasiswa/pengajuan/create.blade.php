@extends('layouts.pengajuan-app')
@section('title', request()->routeIs('kegiatan.create') ? 'Tambah Kegiatan' : ($item ? 'Edit Pengajuan' : 'Pengajuan Kegiatan Baru'))
@section('content')
@php
    $isActivityHistory = $isActivityHistory ?? false;
    $d = $item['data'] ?? [];
    $v = fn ($k, $def = '') => old($k, $d[$k] ?? $def);
    $sectionClass = 'flex flex-col gap-4.5 rounded-lg border border-gray-200 bg-white p-4.5';
    $numClass     = 'flex size-[26px] shrink-0 items-center justify-center rounded-full bg-brand text-[13px] font-semibold text-white';
    $input        = 'h-11 w-full rounded-lg border border-gray-200 bg-white px-3.5 text-[15px] text-gray-900 outline-none placeholder:text-gray-400 focus:border-brand';
    $select       = $input.' appearance-none pr-10';
    $members = old('members') !== null
        ? (json_decode(old('members'), true) ?: [])
        : ($d['members'] ?? []);
    $initialDocuments = old('documents', collect($item['documents'] ?? [])->map(fn ($f) => [
        'name' => $f['name'] ?? '',
        'type' => $f['type'] ?? 'Dokumentasi',
        'url' => $f['url'] ?? (isset($f['path']) ? asset('storage/'.$f['path']) : ''),
    ])->all());
    $categories = ['Lomba', 'Volunteer', 'Magang', 'Pengabdian'];
    $documentTypes = ['Dokumentasi', 'Surat Mitra', 'Surat Mahasiswa', 'Surat Pengabdian'];
    $locLabels = [
        'Lomba' => 'Lokasi Lomba',
        'Volunteer' => 'Lokasi Kegiatan',
        'Magang' => 'Lokasi Magang',
        'Pengabdian' => 'Lokasi Pengabdian',
    ];
    $levels = ['Kabupaten', 'Provinsi', 'Nasional', 'Internasional'];
    $cancelUrl = $item
        ? route('pengajuan.show', $item['id'])
        : ($isActivityHistory ? route('kegiatan.index') : route('pengajuan.index'));
    $formAction = $item
        ? route('pengajuan.update', $item['id'])
        : ($isActivityHistory ? route('kegiatan.store') : route('pengajuan.store'));
@endphp
<div class="flex flex-col gap-5 px-4 pb-10 pt-6 sm:px-6 lg:px-8"
     x-data="{
         categories: @js($categoriesList),
         selectedCategoryId: @js($initialCategoryId),
         title: @js($initialTitle),
         savedDetails: @js($savedDetails),
         multiValues: {},
         submitting: false,
         get activeCategory() {
             return this.categories.find(c => String(c.id_kategori) === String(this.selectedCategoryId)) || null;
         },
         initMultiValues() {
             if (!this.activeCategory) return;
             for (const field of this.activeCategory.isian) {
                 if (field.jumlah_isian === 0) {
                     const key = String(field.id_isian_kategori);
                     if (!Array.isArray(this.multiValues[key])) {
                         const existing = this.savedDetails[key];
                         this.multiValues[key] = Array.isArray(existing) && existing.length > 0 ? [...existing] : [''];
                     }
                 }
             }
         },
         addMultiRow(idIsian) {
             const key = String(idIsian);
             if (!Array.isArray(this.multiValues[key])) this.multiValues[key] = [''];
             this.multiValues[key].push('');
         },
         removeMultiRow(idIsian, idx) {
             const key = String(idIsian);
             if (this.multiValues[key].length > 1) {
                 this.multiValues[key].splice(idx, 1);
             }
         }
     }"
     x-init="$nextTick(() => check()); $watch('category', () => setTimeout(() => check(), 60)); $watch('participation', () => setTimeout(() => check(), 60)); $watch('members', () => check()); $watch('documents', () => check(), { deep: true })">

    {{-- Judul halaman + breadcrumb + tombol kembali (Style Kelompok 3) --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex flex-col gap-1.5">
            <h1 class="text-3xl font-bold tracking-tight text-gray-900">{{ $isActivityHistory ? 'Tambah Kegiatan' : ($item ? 'Edit Pengajuan' : 'Pengajuan Kegiatan Baru') }}</h1>
            <p class="text-2xl text-gray-400">
                <a href="{{ $isActivityHistory ? route('kegiatan.index') : route('pengajuan.index') }}" class="hover:text-gray-600">{{ $isActivityHistory ? 'Riwayat Kegiatan' : 'Kegiatan' }}</a>
                / {{ $isActivityHistory ? 'Tambah Kegiatan' : ($item ? 'Edit Pengajuan' : 'Pengajuan Baru') }} /
                <span class="text-brand" x-text="category || 'Umum'"></span>
            </p>
            <p class="pt-0.5 text-[15px] text-gray-400">{{ $isActivityHistory ? 'Lengkapi data untuk langsung menambahkan kegiatan ke riwayat Anda.' : ($item ? 'Perbarui isian pengajuan Anda, lalu simpan perubahan.' : 'Lengkapi formulir berikut untuk mengajukan kegiatan baru.') }}</p>
        </div>
        <a href="{{ $cancelUrl }}"
           class="mt-2 flex h-11 items-center gap-2 rounded-md bg-blue-100 px-4 text-base font-medium text-brand hover:bg-blue-200">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg>
            Kembali
        </a>
    </div>

    <form method="POST" action="{{ $formAction }}" x-ref="form"
          @input="check()" @change="check()"
          @submit="if (!ok) { $event.preventDefault(); } else { submitting = true; }"
          class="flex flex-col gap-5 rounded-lg bg-white p-4.5 shadow-sm">
        @csrf
        @if ($item) @method('PUT') @endif

        {{-- 1. KATEGORI KEGIATAN --}}
        <section class="{{ $sectionClass }}" aria-labelledby="h-kategori">
            <div class="flex items-center gap-2.5">
                <span class="{{ $numClass }}">1</span>
                <h2 id="h-kategori" class="text-[15px] font-semibold text-gray-900">Kategori Kegiatan</h2>
            </div>

        <div class="flex flex-col gap-5 p-5 sm:p-7">
            {{-- PILIH KATEGORI DARI ADMIN --}}
            <div class="flex flex-col gap-1.5">
                <label class="text-[13px] font-semibold leading-5 text-[#374151]">
                    Kategori Kegiatan <span class="text-[#FF3F3F]">*</span>
                </label>
                <div class="relative">
                    <select name="id_kategori" x-model="selectedCategoryId" required class="{{ $select }}">
                        <option value="">Pilih kategori kegiatan...</option>
                        <template x-for="cat in categories" :key="cat.id_kategori">
                            <option :value="String(cat.id_kategori)"
                                    :selected="String(cat.id_kategori) === String(selectedCategoryId)"
                                    x-text="cat.nama_kategori + (cat.jenis ? ' (' + cat.jenis + ')' : '')"></option>
                        </template>
                    </select>
                    <i class="ti ti-chevron-down pointer-events-none absolute right-3.5 top-3.5 text-base text-gray-500"></i>
                </div>
                <p class="text-[15px] text-gray-400" x-text="category ? 'Kategori terpilih: ' + category + '.' : 'Pilih kategori untuk menampilkan isian pengajuan.'"></p>
            </x-pengajuan.field>
        </section>

        {{-- 2. DETAIL KEGIATAN --}}
        <section x-show="category !== ''" x-cloak class="{{ $sectionClass }}" aria-labelledby="h-detail">
            <div class="flex items-center gap-2.5">
                <span class="{{ $numClass }}">2</span>
                <h2 id="h-detail" class="text-[15px] font-semibold text-gray-900">Detail Kegiatan</h2>
            </div>

            <div class="flex flex-col gap-5">
                {{-- LOMBA --}}
                <template x-if="category === 'Lomba'">
                    <div class="flex flex-col gap-5">
                        <x-pengajuan.field label="Judul Kegiatan" required hint="Judul harus singkat, jelas, dan mudah dipahami." name="title">
                            <input name="title" value="{{ $v('title') }}" class="{{ $input }}" placeholder="Masukkan judul kegiatan...">
                        </x-pengajuan.field>

                        @include('pengajuan.partials.team', ['input' => $input, 'select' => $select, 'memberLabel' => 'Anggota Lomba'])

                        <div class="flex flex-col gap-5 md:flex-row">
                            <div class="flex min-w-0 flex-1 flex-col gap-1.5">
                                <label class="text-[15px] font-medium leading-5 text-gray-900">
                                    <span x-text="participation === 'Tim' ? 'Tingkat Kejuaraan' : 'Tingkat Lomba'"></span> <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <select name="level" x-model="level" class="{{ $select }}">
                                        <option value="">Pilih...</option>
                                        @foreach ($levels as $l)<option>{{ $l }}</option>@endforeach
                                    </select>
                                    <i class="ti ti-chevron-down pointer-events-none absolute right-3.5 top-3.5 text-base text-gray-500"></i>
                                </div>
                                <p class="text-xs leading-[18px] text-gray-400" x-text="level ? 'Tingkat terpilih: ' + level + '.' : 'Pilihan: Kabupaten, Provinsi, Nasional, Internasional.'"></p>
                                @error('level')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
                            </div>
                            <x-pengajuan.field class="flex-1" label="Penyelenggara Lomba" required hint="Masukkan penyelenggara lomba." name="organizer">
                                <input name="organizer" value="{{ $v('organizer') }}" class="{{ $input }}" placeholder="Nama lembaga / instansi">
                            </x-pengajuan.field>
                        </div>

                        <x-pengajuan.field label="Link Visual/Website Lomba" optional hint="Cantumkan tautan poster atau situs resmi lomba." name="info_url">
                            <input name="info_url" value="{{ $v('info_url') }}" class="{{ $input }}" placeholder="https://...">
                        </x-pengajuan.field>

                        <x-pengajuan.field label="Dosen Pembimbing" optional name="advisors">
                            @include('pengajuan.partials.advisor', ['input' => $input])
                        </x-pengajuan.field>
                    </div>
                </template>

                {{-- VOLUNTEER --}}
                <template x-if="category === 'Volunteer'">
                    <div class="flex flex-col gap-5">
                        <x-pengajuan.field label="Nama Program Relawan" required hint="Tuliskan nama program yang akan Anda ikuti." name="title">
                            <input name="title" value="{{ $v('title') }}" class="{{ $input }}" placeholder="Masukkan nama program relawan...">
                        </x-pengajuan.field>
                        <x-pengajuan.field label="Komunitas / Organisasi Penyelenggara" required hint="Gunakan nama resmi penyelenggara program relawan." name="organizer">
                            <input name="organizer" value="{{ $v('organizer') }}" class="{{ $input }}" placeholder="Masukkan nama komunitas atau organisasi">
                        </x-pengajuan.field>
                        <x-pengajuan.field label="Peran / Divisi Relawan" required hint="Tuliskan peran Anda dalam pelaksanaan program." name="role">
                            <input name="role" value="{{ $v('role') }}" class="{{ $input }}" placeholder="Masukkan peran atau divisi relawan">
                        </x-pengajuan.field>
                    </div>
                </template>

                {{-- MAGANG --}}
                <template x-if="category === 'Magang'">
                    <div class="flex flex-col gap-5">
                        <x-pengajuan.field label="Posisi / Role Magang" required hint="Tuliskan posisi yang akan dijalani selama magang." name="role">
                            <input name="role" value="{{ $v('role') }}" class="{{ $input }}" placeholder="Masukkan posisi atau peran magang...">
                        </x-pengajuan.field>
                        <x-pengajuan.field label="Nama Perusahaan / Instansi" required hint="Gunakan nama resmi perusahaan atau instansi penerima." name="organizer">
                            <input name="organizer" value="{{ $v('organizer') }}" class="{{ $input }}" placeholder="Masukkan nama perusahaan / instansi">
                        </x-pengajuan.field>
                        <x-pengajuan.field label="Durasi Magang (Bulan)" required hint="Masukkan durasi dalam bulan." name="duration_months">
                            <input type="number" min="1" max="60" name="duration_months" value="{{ $v('duration_months') }}" class="{{ $input }}" placeholder="Contoh: 6">
                        </x-pengajuan.field>
                    </div>
                </template>

                {{-- PENGABDIAN --}}
                <template x-if="category === 'Pengabdian'">
                    <div class="flex flex-col gap-5">
                        <x-pengajuan.field label="Nama / Judul Kegiatan" required hint="Tuliskan nama atau judul kegiatan secara singkat dan jelas." name="title">
                            <input name="title" value="{{ $v('title') }}" class="{{ $input }}" placeholder="Masukkan nama atau judul kegiatan">
                        </x-pengajuan.field>

                        <x-pengajuan.field label="Penyelenggara / Instansi / Sumber" required hint="Masukkan instansi penyelenggara atau sumber informasi kegiatan." name="organizer">
                            <input name="organizer" value="{{ $v('organizer') }}" class="{{ $input }}" placeholder="Nama lembaga, instansi, atau sumber">
                        </x-pengajuan.field>

                        <x-pengajuan.field label="Tautan Informasi Kegiatan" optional hint="Tautan situs resmi, poster, atau halaman pendaftaran." name="info_url">
                            <input name="info_url" value="{{ $v('info_url') }}" class="{{ $input }}" placeholder="https://...">
                        </x-pengajuan.field>

                        <x-pengajuan.field label="Dosen Pembimbing / Pendamping" optional name="advisor">
                            <input name="advisor" x-model="advisor" value="{{ $v('advisor') }}" class="{{ $input }}" placeholder="Masukkan nama dosen pendamping">
                        </x-pengajuan.field>
                    </div>
                </template>

                {{-- FIELD BERSAMA --}}
                <div class="flex flex-col gap-1.5">
                    <label class="text-[15px] font-medium leading-5 text-gray-900">
                        <span x-text="locLabels[category] || 'Lokasi Kegiatan'"></span> <span class="text-red-500">*</span>
                    </label>
                    <input name="location" value="{{ $v('location') }}" class="{{ $input }}"
                           x-bind:placeholder="category === 'Magang' ? 'Kota atau alamat penempatan magang' : 'Nama tempat, kota, atau platform daring'">
                    <p class="text-xs leading-[18px] text-gray-400">Tuliskan nama tempat, kota, atau platform daring tempat kegiatan berlangsung.</p>
                    @error('location')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
                </div>

                <div class="flex flex-col gap-5 md:flex-row">
                    <x-pengajuan.field class="flex-1" label="Tanggal Mulai" required hint="Pilih tanggal mulai pelaksanaan kegiatan." name="start_date">
                        <input type="date" name="start_date" value="{{ $v('start_date') }}" class="{{ $input }}">
                    </x-pengajuan.field>
                    <x-pengajuan.field class="flex-1" label="Tanggal Selesai" required hint="Pilih tanggal selesai pelaksanaan kegiatan." name="end_date">
                        <input type="date" name="end_date" value="{{ $v('end_date') }}" class="{{ $input }}">
                    </x-pengajuan.field>
                </div>
            </div>
        </section>

        {{-- 3. DOKUMEN PENDUKUNG --}}
        <section x-show="category !== ''" x-cloak class="{{ $sectionClass }}" aria-labelledby="h-dokumen">
            <div class="flex items-center gap-2.5">
                <span class="{{ $numClass }}">3</span>
                <h2 id="h-dokumen" class="text-[15px] font-semibold text-gray-900">Dokumen pendukung <span class="font-normal text-gray-400">(opsional)</span></h2>
            </div>

            <p class="text-[15px] text-gray-400">Tambahkan tautan dokumen jika diperlukan. Tidak ada batas jumlah dokumen.</p>

            <template x-for="(document, index) in documents" :key="document.id || index">
                <div class="overflow-hidden rounded-lg border border-gray-200">
                    <div class="flex items-center justify-between gap-3 bg-[#02215c] px-3.5 py-3 text-white">
                        <h3 class="text-sm font-semibold" x-text="'Dokumen ' + (index + 1)"></h3>
                        <button type="button" @click="removeDocument(document)" aria-label="Hapus dokumen" class="flex size-7 items-center justify-center rounded text-white/80 hover:bg-white/10 hover:text-white">
                            <i class="ti ti-x text-base"></i>
                        </button>
                    </div>
                    <div class="flex flex-col gap-4 p-3.5">
                        <div class="grid grid-cols-1 gap-4.5 md:grid-cols-2">
                            <label class="flex flex-col gap-1.5 text-[15px] font-medium text-gray-900">
                                <span>Nama dokumen <span class="text-red-500">*</span></span>
                                <input type="text" x-model="document.name" :name="'documents[' + index + '][name]'" maxlength="255" required
                                       class="{{ $input }}" placeholder="Contoh: Proposal pengabdian">
                                @error('documents.*.name')<span class="text-xs text-red-500">{{ $message }}</span>@enderror
                            </label>
                            <label class="flex flex-col gap-1.5 text-[15px] font-medium text-gray-900">
                                <span>Jenis dokumen <span class="text-red-500">*</span></span>
                                <div class="relative">
                                    <select x-model="document.type" :name="'documents[' + index + '][type]'" required class="{{ $select }}">
                                        <option value="">Pilih jenis dokumen</option>
                                        <template x-for="type in documentTypes" :key="type">
                                            <option :value="type" x-text="type"></option>
                                        </template>
                                    </select>
                                    <i class="ti ti-chevron-down pointer-events-none absolute right-3.5 top-3.5 text-base text-gray-500"></i>
                                </div>
                                @error('documents.*.type')<span class="text-xs text-red-500">{{ $message }}</span>@enderror
                            </label>
                        </div>
                        <label class="flex flex-col gap-1.5 text-[15px] font-medium text-gray-900">
                            <span>Tautan dokumen <span class="text-red-500">*</span></span>
                            <input type="url" x-model="document.url" :name="'documents[' + index + '][url]'" maxlength="500" required
                                   class="{{ $input }}" placeholder="https://...">
                            @error('documents.*.url')<span class="text-xs text-red-500">{{ $message }}</span>@enderror
                        </label>

                        {{-- MODE 1: SATU ISIAN (jumlah_isian === 1) --}}
                        <template x-if="field.jumlah_isian === 1">
                            <div>
                                {{-- Teks Pendek --}}
                                <template x-if="field.jenis_input === 'teks'">
                                    <input type="text"
                                           :name="'detail[' + field.id_isian_kategori + ']'"
                                           :value="savedDetails[field.id_isian_kategori] ?? ''"
                                           :required="field.wajib"
                                           class="{{ $input }}"
                                           :placeholder="'Masukkan ' + field.input.toLowerCase() + '...'">
                                </template>

                                {{-- Teks Panjang / Paragraf --}}
                                <template x-if="field.jenis_input === 'paragraf'">
                                    <textarea :name="'detail[' + field.id_isian_kategori + ']'"
                                              :required="field.wajib"
                                              class="{{ $textarea }}"
                                              :placeholder="'Tuliskan ' + field.input.toLowerCase() + '...'"
                                              x-text="savedDetails[field.id_isian_kategori] ?? ''"></textarea>
                                </template>

                                {{-- Angka --}}
                                <template x-if="field.jenis_input === 'angka'">
                                    <input type="number"
                                           :name="'detail[' + field.id_isian_kategori + ']'"
                                           :value="savedDetails[field.id_isian_kategori] ?? ''"
                                           :required="field.wajib"
                                           class="{{ $input }}"
                                           placeholder="0">
                                </template>

                                {{-- Tanggal --}}
                                <template x-if="field.jenis_input === 'tanggal'">
                                    <input type="date"
                                           :name="'detail[' + field.id_isian_kategori + ']'"
                                           :value="savedDetails[field.id_isian_kategori] ?? ''"
                                           :required="field.wajib"
                                           class="{{ $input }}">
                                </template>

                                {{-- Unggah Berkas --}}
                                <template x-if="field.jenis_input === 'berkas'">
                                    <div class="flex flex-col gap-1.5">
                                        <input type="file"
                                               :name="'detail_files[' + field.id_isian_kategori + ']'"
                                               accept=".pdf,.jpg,.jpeg,.png"
                                               :required="field.wajib && !savedDetails[field.id_isian_kategori]"
                                               class="block w-full rounded-lg border border-[#E5E7EB] bg-white px-3 py-2 text-[13px] text-[#374151] file:mr-3 file:rounded-md file:border-0 file:bg-[#EFF6FF] file:px-3 file:py-1 file:text-xs file:font-semibold file:text-[#2277FF]">
                                        <template x-if="savedDetails[field.id_isian_kategori]">
                                            <p class="text-xs text-[#059669]">Berkas tersimpan: <span x-text="savedDetails[field.id_isian_kategori]"></span> (unggah baru jika ingin mengganti).</p>
                                        </template>
                                    </div>
                                </template>

                                {{-- Pilihan Baku (Tingkat Kejuaraan, Jenis Prestasi, Dosen) --}}
                                <template x-if="Array.isArray(field.pilihan)">
                                    <div class="relative">
                                        <select :name="'detail[' + field.id_isian_kategori + ']'"
                                                :required="field.wajib"
                                                class="{{ $select }}">
                                            <option value="">Pilih...</option>
                                            <template x-for="opt in field.pilihan" :key="opt.nilai">
                                                <option :value="opt.nilai"
                                                        :selected="String(savedDetails[field.id_isian_kategori] ?? '') === String(opt.nilai)"
                                                        x-text="opt.label"></option>
                                            </template>
                                        </select>
                                        <i class="ti ti-chevron-down pointer-events-none absolute right-3 top-3 text-base"></i>
                                    </div>
                                </template>
                            </div>
                        </template>

                        {{-- MODE 2: LEBIH DARI SATU ISIAN / REPEATABLE (jumlah_isian === 0) --}}
                        <template x-if="field.jumlah_isian === 0">
                            <div class="flex flex-col gap-2">
                                <template x-for="(val, idx) in (multiValues[field.id_isian_kategori] || [''])" :key="idx">
                                    <div class="flex items-center gap-2">
                                        <template x-if="Array.isArray(field.pilihan)">
                                            <div class="relative flex-1">
                                                <select :name="'detail[' + field.id_isian_kategori + '][]'"
                                                        x-model="multiValues[field.id_isian_kategori][idx]"
                                                        :required="field.wajib && idx === 0"
                                                        class="{{ $select }}">
                                                    <option value="">Pilih...</option>
                                                    <template x-for="opt in field.pilihan" :key="opt.nilai">
                                                        <option :value="opt.nilai" x-text="opt.label"></option>
                                                    </template>
                                                </select>
                                                <i class="ti ti-chevron-down pointer-events-none absolute right-3 top-3 text-base"></i>
                                            </div>
                                        </template>
                                        <template x-if="!Array.isArray(field.pilihan)">
                                            <input :type="field.jenis_input === 'angka' ? 'number' : (field.jenis_input === 'tanggal' ? 'date' : 'text')"
                                                   :name="'detail[' + field.id_isian_kategori + '][]'"
                                                   x-model="multiValues[field.id_isian_kategori][idx]"
                                                   :required="field.wajib && idx === 0"
                                                   class="{{ $input }} flex-1"
                                                   :placeholder="field.input + ' #' + (idx + 1)">
                                        </template>
                                        <button type="button"
                                                x-show="(multiValues[field.id_isian_kategori] || []).length > 1"
                                                @click="removeMultiRow(field.id_isian_kategori, idx)"
                                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-[#FECACA] text-[#DC2626] hover:bg-[#FEF2F2]">
                                            <i class="ti ti-trash text-base"></i>
                                        </button>
                                    </div>
                                </template>
                                <button type="button" @click="addMultiRow(field.id_isian_kategori)"
                                        class="flex h-9 w-fit items-center gap-1.5 rounded-lg border border-[#2277FF] px-3 text-xs font-semibold text-[#2277FF] hover:bg-[#EFF6FF]">
                                    <i class="ti ti-plus text-sm"></i>
                                    <span x-text="'Tambah ' + field.input"></span>
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
            </template>

            <button type="button" @click="addDocument()" class="flex h-10 w-fit items-center gap-2 rounded-lg border border-brand px-3.5 text-sm font-semibold text-brand hover:bg-blue-50">
                <i class="ti ti-plus text-base"></i>Tambah dokumen
            </button>
            <p x-show="documentNote" x-text="documentNote" class="text-xs text-red-600"></p>
            @error('documents')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
            @error('documents.*')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
        </section>

        {{-- Kotak Status Biru (Style Kelompok 3) --}}
        <div role="status" class="flex items-center gap-2.5 rounded-lg bg-blue-100 px-4 py-3.5 text-sm text-brand">
            <svg class="size-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9.5"/><path d="M12 11v5.5M12 7.5v.5"/></svg>
            <span x-text="ok ? 'Semua isian wajib sudah lengkap. Periksa kembali data sebelum menyimpan.' : 'Lengkapi semua isian wajib (*) sebelum menyimpan.'"></span>
        </div>

        {{-- Tombol Aksi --}}
        <div class="flex flex-wrap items-center justify-end gap-2.5">
            <a href="{{ $cancelUrl }}" class="flex h-11 items-center rounded-lg border border-gray-200 bg-white px-5 text-base font-medium text-gray-700 hover:bg-gray-50">Batal</a>
            <button type="submit" :disabled="!ok || submitting"
                    class="flex h-11 items-center gap-2 rounded-lg bg-brand px-5 text-base font-medium text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50">
                <span x-text="submitting ? 'Menyimpan...' : '{{ $isActivityHistory ? 'Simpan ke Riwayat' : ($item ? 'Simpan Perubahan' : 'Kirim Pengajuan') }}'"></span>
                <i class="ti text-xs" :class="submitting ? 'ti-loader-2 animate-spin' : 'ti-arrow-right'"></i>
            </button>
        </div>
    </form>
</div>
@endsection