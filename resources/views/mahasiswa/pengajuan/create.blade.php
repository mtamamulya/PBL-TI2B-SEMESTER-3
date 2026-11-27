@extends(($formDosen ?? false) ? 'layouts.app' : 'layouts.pengajuan-app')
@section('title', request()->routeIs('kegiatan.create') ? 'Tambah Kegiatan' : ($item ? 'Edit Pengajuan' : 'Pengajuan Kegiatan Baru'))
@section('content')
@php
    $isActivityHistory = $isActivityHistory ?? false;
    $categoriesList    = $categoriesList ?? [];
    $initialCategoryId = old('id_kategori', $initialCategoryId ?? '');
    $initialTitle      = old('judul', $initialTitle ?? '');
    $savedDetails      = old('detail', $savedDetails ?? []);
    $d = $item['data'] ?? [];
    $categoriesList = ($categoriesData ?? collect())->values()->all();
    $mahasiswaOptionsList = ($mahasiswaList ?? collect())->values()->all();
    $initialCategoryId = (string) old('id_kategori', $d['id_kategori'] ?? ($item['id_kategori'] ?? ''));
    $initialTitle = old('title', $d['title'] ?? ($item['title'] ?? ''));
    $initialParticipation = old('participation', $d['participation'] ?? 'Individu');
    $initialMemberIds = array_map('intval', (array) old('member_ids', $d['member_ids'] ?? []));
    $savedDetails = old('detail', $d['detail'] ?? []);
    $initialDocuments = old('documents', $item['documents'] ?? []);

    $sectionClass = 'flex flex-col gap-4.5 rounded-lg border border-gray-200 bg-white p-4.5';
    $numClass     = 'flex size-[26px] shrink-0 items-center justify-center rounded-full bg-brand text-[13px] font-semibold text-white';
    $input        = 'h-11 w-full rounded-lg border border-gray-200 bg-white px-3.5 text-[15px] text-gray-900 outline-none placeholder:text-gray-400 focus:border-brand';
    $textarea     = 'min-h-[104px] w-full rounded-lg border border-gray-200 bg-white p-3.5 text-[15px] text-gray-900 outline-none placeholder:text-gray-400 focus:border-brand';
    $select       = $input.' appearance-none pr-10';

    $cancelUrl = $item
        ? route('pengajuan.show', $item['id'])
        : ($isActivityHistory ? route('kegiatan.index') : route('pengajuan.index'));
    $formAction = $item
        ? route('pengajuan.update', $item['id'])
        : ($isActivityHistory ? route('kegiatan.store') : route('pengajuan.store'));
    if ($formDosen ?? false) {
        $cancelUrl = route('pengajuan.dosen');
        $formAction = route('pengajuan.dosen.store');
    }
@endphp

<div class="flex flex-col gap-5 px-4 pb-10 pt-6 sm:px-6 lg:px-8"
     x-data="{
         categories: @js($categoriesList),
         allStudents: @js($mahasiswaOptionsList),
         selectedCategoryId: @js($initialCategoryId),
         title: @js($initialTitle),
         participation: @js($initialParticipation),
         selectedMemberIds: @js($initialMemberIds),
         memberSearch: '',
         selectedCategoryId: @js((string) $initialCategoryId),
         title: @js((string) $initialTitle),
         savedDetails: @js($savedDetails),
         multiValues: {},
         documents: @js(array_values($initialDocuments)),
         documentTypes: ['Dokumentasi', 'Surat Mitra', 'Surat Mahasiswa', 'Surat Pengabdian'],
         get filteredStudents() {
             const q = this.memberSearch.trim().toLowerCase();
             return this.allStudents
                 .filter(s => !this.selectedMemberIds.includes(Number(s.id_user)))
                 .filter(s => !q || s.nama.toLowerCase().includes(q) || s.nim.toLowerCase().includes(q))
                 .slice(0, 8);
         },
         get selectedMembers() {
             return this.selectedMemberIds
                 .map(id => this.allStudents.find(s => Number(s.id_user) === Number(id)))
                 .filter(Boolean);
         },
         addMember(student) {
             const id = Number(student.id_user);
             if (!this.selectedMemberIds.includes(id)) {
                 this.selectedMemberIds.push(id);
             }
             this.memberSearch = '';
         },
         removeMember(idUser) {
             this.selectedMemberIds = this.selectedMemberIds.filter(id => Number(id) !== Number(idUser));
         },
            addDocument() {
                this.documents.push({ name: '', type: 'Dokumentasi', url: '' });
            },
            removeDocument(index) {
                this.documents.splice(index, 1);
            },
         submitting: false,
         get activeCategory() {
             return this.categories.find(c => String(c.id_kategori) === String(this.selectedCategoryId)) || null;
         },
         get nonFileFields() {
             if (!this.activeCategory || !Array.isArray(this.activeCategory.isian)) return [];
             return this.activeCategory.isian.filter(f => f.jenis_input !== 'berkas');
         },
         get fileFields() {
             if (!this.activeCategory || !Array.isArray(this.activeCategory.isian)) return [];
             return this.activeCategory.isian.filter(f => f.jenis_input === 'berkas');
         },
         initMultiValues() {
             if (!this.activeCategory || !Array.isArray(this.activeCategory.isian)) return;
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
             if (this.multiValues[key] && this.multiValues[key].length > 1) {
                 this.multiValues[key].splice(idx, 1);
             }
         }
     }"
     x-init="initMultiValues(); $watch('selectedCategoryId', () => initMultiValues())">

    {{-- Judul halaman + breadcrumb + tombol kembali (Style Kelompok 3) --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex flex-col gap-1.5">
            <h1 class="text-3xl font-bold tracking-tight text-gray-900">{{ $isActivityHistory ? 'Tambah Kegiatan' : ($item ? 'Edit Pengajuan' : 'Pengajuan Kegiatan Baru') }}</h1>
            <p class="text-2xl text-gray-400">
                <a href="{{ $isActivityHistory ? route('kegiatan.index') : route('pengajuan.index') }}" class="hover:text-gray-600">{{ $isActivityHistory ? 'Riwayat Kegiatan' : 'Kegiatan' }}</a>
                / {{ $isActivityHistory ? 'Tambah Kegiatan' : ($item ? 'Edit Pengajuan' : 'Pengajuan Baru') }} /
                <span class="text-brand" x-text="activeCategory ? activeCategory.nama_kategori : 'Umum'"></span>
            </p>
            <p class="pt-0.5 text-[15px] text-gray-400">{{ $isActivityHistory ? 'Lengkapi data untuk langsung menambahkan kegiatan ke riwayat Anda.' : ($item ? 'Perbarui isian pengajuan Anda, lalu simpan perubahan.' : 'Lengkapi formulir berikut untuk mengajukan kegiatan baru.') }}</p>
    <div class="flex flex-col gap-[18px]">
        <div class="flex flex-wrap gap-2.5 text-[13px]">
            <a href="{{ ($formDosen ?? false) ? $cancelUrl : ($isActivityHistory ? route('kegiatan.index') : route('pengajuan.index')) }}" class="text-[#6B7280]">{{ $isActivityHistory ? 'Riwayat Kegiatan' : 'Pengajuan' }}</a>
            <span class="text-[#9CA3AF]">/</span>
            <span class="text-[#6B7280]">{{ $isActivityHistory ? 'Tambah Kegiatan' : ($item ? 'Edit Pengajuan' : 'Pengajuan Baru') }}</span>
            <span class="text-[#9CA3AF]">/</span>
            <span class="font-semibold text-[#2277FF]" x-text="activeCategory ? activeCategory.nama_kategori : 'Pilih Kategori'"></span>
        </div>
        <a href="{{ $cancelUrl }}"
           class="mt-2 flex h-11 items-center gap-2 rounded-md bg-blue-100 px-4 text-base font-medium text-brand hover:bg-blue-200">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg>
            Kembali
        </a>
    </div>

    @if ($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <p class="font-semibold">Periksa kembali isian formulir:</p>
            <ul class="mt-1 list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ $formAction }}" enctype="multipart/form-data"
          @submit="submitting = true"
          class="flex flex-col gap-5 rounded-lg bg-white p-4.5 shadow-sm">
        @csrf
        @if ($item) @method('PUT') @endif

        {{-- 1. KATEGORI KEGIATAN --}}
        <section class="{{ $sectionClass }}" aria-labelledby="h-kategori">
            <div class="flex items-center gap-2.5">
                <span class="{{ $numClass }}">1</span>
                <h2 id="h-kategori" class="text-[15px] font-semibold text-gray-900">Kategori Kegiatan</h2>
            </div>

            <div class="flex flex-col gap-1.5">
                <label class="text-[15px] font-medium leading-5 text-gray-900">
                    Kategori Kegiatan <span class="text-red-500">*</span>
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
                <p class="text-[15px] text-gray-400"
                   x-text="activeCategory ? 'Kategori terpilih: ' + activeCategory.nama_kategori + '.' : 'Pilih kategori untuk menampilkan isian pengajuan.'"></p>
                @error('id_kategori')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
            </div>
        </section>

        {{-- 2. DETAIL KEGIATAN --}}
        <section x-show="activeCategory !== null" x-cloak class="{{ $sectionClass }}" aria-labelledby="h-detail">
            <div class="flex items-center gap-2.5">
                <span class="{{ $numClass }}">2</span>
                <h2 id="h-detail" class="text-[15px] font-semibold text-gray-900">Detail Kegiatan</h2>
            </div>

            <div class="flex flex-col gap-5">
                {{-- Judul Kegiatan --}}
                <div class="flex flex-col gap-1.5">
                    <label class="text-[15px] font-medium leading-5 text-gray-900">
                        Judul Kegiatan <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="judul" x-model="title" required class="{{ $input }}" placeholder="Masukkan judul kegiatan...">
                    <p class="text-xs leading-[18px] text-gray-400">Judul harus singkat, jelas, dan mudah dipahami.</p>
                    @error('judul')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
                </div>

                {{-- JENIS KEPESERTAAN (INDIVIDU / TIM) --}}
                <div class="flex flex-col gap-3">
                    <label class="text-[13px] font-semibold leading-5 text-[#374151]">
                        Jenis Kepesertaan <span class="text-[#FF3F3F]">*</span>
                    </label>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-3.5 transition"
                               :class="participation === 'Individu' ? 'border-[#2277FF] bg-[#EFF6FF]/60' : 'border-[#E5E7EB] bg-white hover:bg-[#F9FAFB]'">
                            <input type="radio" name="participation" value="Individu" x-model="participation" class="mt-0.5 accent-[#2277FF]">
                            <div class="flex flex-col">
                                <span class="text-[13px] font-semibold text-[#111827]">Individu</span>
                                <span class="text-xs text-[#6B7280]">Kegiatan diikuti secara perorangan.</span>
                            </div>
                        </label>
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-3.5 transition"
                               :class="participation === 'Tim' ? 'border-[#2277FF] bg-[#EFF6FF]/60' : 'border-[#E5E7EB] bg-white hover:bg-[#F9FAFB]'">
                            <input type="radio" name="participation" value="Tim" x-model="participation" class="mt-0.5 accent-[#2277FF]">
                            <div class="flex flex-col">
                                <span class="text-[13px] font-semibold text-[#111827]">Tim</span>
                                <span class="text-xs text-[#6B7280]">Tag teman mahasiswa sebagai anggota tim.</span>
                            </div>
                        </label>
                    </div>

                    {{-- PILIH ANGGOTA TIM --}}
                    <div x-show="participation === 'Tim'" x-cloak class="flex flex-col gap-3 rounded-xl border border-[#E5E7EB] bg-[#F9FAFB] p-4">
                        <div class="flex flex-col gap-0.5">
                            <span class="text-xs font-semibold text-[#374151]">Anggota Tim (Tag Mahasiswa) <span class="text-[#FF3F3F]">*</span></span>
                            <span class="text-xs text-[#6B7280]">Anda otomatis tercatat sebagai <strong>Ketua</strong>. Cari nama atau NIM teman untuk ditambahkan sebagai <strong>Anggota</strong>.</span>
                        </div>

                        <div class="relative">
                            <input type="text"
                                   x-model="memberSearch"
                                   class="{{ $input }}"
                                   placeholder="Ketik nama atau NIM teman mahasiswa...">

                            <div x-show="memberSearch.trim().length > 0"
                                 class="absolute z-20 mt-1 max-h-52 w-full overflow-y-auto rounded-lg border border-[#E5E7EB] bg-white shadow-lg">
                                <template x-for="st in filteredStudents" :key="st.id_user">
                                    <button type="button"
                                            @click="addMember(st)"
                                            class="flex w-full items-center justify-between px-3.5 py-2.5 text-left text-xs hover:bg-[#EFF6FF]">
                                        <span class="font-semibold text-[#111827]" x-text="st.nama"></span>
                                        <span class="text-[#6B7280]" x-text="st.nim"></span>
                                    </button>
                                </template>
                                <div x-show="filteredStudents.length === 0" class="px-3.5 py-2.5 text-xs text-[#6B7280]">
                                    Mahasiswa tidak ditemukan atau sudah ditambahkan.
                                </div>
                            </div>
                        </div>

                        {{-- DAFTAR ANGGOTA TERPILIH --}}
                        <div class="flex flex-col gap-2" x-show="selectedMembers.length > 0">
                            <template x-for="m in selectedMembers" :key="m.id_user">
                                <div class="flex items-center justify-between gap-3 rounded-lg border border-[#E5E7EB] bg-white px-3.5 py-2.5">
                                    <input type="hidden" name="member_ids[]" :value="m.id_user">
                                    <div class="flex flex-col">
                                        <span class="text-[13px] font-semibold text-[#111827]" x-text="m.nama"></span>
                                        <span class="text-xs text-[#6B7280]" x-text="'NIM ' + m.nim"></span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="rounded-full bg-[#EFF6FF] px-2.5 py-0.5 text-[11px] font-semibold text-[#2277FF]">Anggota</span>
                                        <button type="button" @click="removeMember(m.id_user)"
                                                class="flex h-7 w-7 items-center justify-center rounded-md text-[#DC2626] hover:bg-[#FEE2E2]">
                                            <i class="ti ti-trash text-sm"></i>
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <p x-show="selectedMembers.length === 0" class="text-xs text-[#9CA3AF]">
                            Belum ada anggota tim yang ditambahkan.
                        </p>
                        @error('member_ids')<p class="text-xs text-[#FF3F3F]">{{ $message }}</p>@enderror
                    </div>
                </div>

                {{-- Isian Dinamis Non-Berkas dari Kategori Admin --}}
                <template x-for="field in nonFileFields" :key="field.id_isian_kategori">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[15px] font-medium leading-5 text-gray-900">
                            <span x-text="field.input"></span>
                            <span x-show="field.wajib" class="text-red-500">*</span>
                            <span x-show="!field.wajib" class="font-normal text-gray-400">(opsional)</span>
                        </label>

                        {{-- MODE 1: SATU ISIAN (jumlah_isian === 1) --}}
                        <template x-if="field.jumlah_isian === 1">
                            <div>
                                {{-- Teks Pendek --}}
                                <template x-if="field.jenis_input === 'teks' && !Array.isArray(field.pilihan)">
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
                                <template x-if="['angka', 'rupiah'].includes(field.jenis_input)">
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

                                {{-- Pilihan Baku --}}
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
                                        <i class="ti ti-chevron-down pointer-events-none absolute right-3.5 top-3.5 text-base text-gray-500"></i>
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
                                                <i class="ti ti-chevron-down pointer-events-none absolute right-3.5 top-3.5 text-base text-gray-500"></i>
                                            </div>
                                        </template>

                                       <template x-if="!Array.isArray(field.pilihan) && field.jenis_input === 'berkas'">
                                            <div class="flex flex-1 flex-col gap-1">
                                                <input type="file"
                                                       :name="'detail_files[' + field.id_isian_kategori + '][]'"
                                                       accept=".pdf,.jpg,.jpeg,.png"
                                                       :required="field.wajib && idx === 0 && !val"
                                                       class="block w-full rounded-lg border border-[#E5E7EB] bg-white px-3 py-2 text-[13px] text-[#374151] file:mr-3 file:rounded-md file:border-0 file:bg-[#EFF6FF] file:px-3 file:py-1 file:text-xs file:font-semibold file:text-[#2277FF]">
                                                <template x-if="val">
                                                    <p class="text-xs text-[#059669]">Berkas tersimpan: <span x-text="val"></span></p>
                                                </template>
                                            </div>
                                        </template>
                                        <template x-if="!Array.isArray(field.pilihan) && field.jenis_input !== 'berkas'">
                                            <input :type="['angka', 'rupiah'].includes(field.jenis_input) ? 'number' : (field.jenis_input === 'tanggal' ? 'date' : 'text')"

                                                   :name="'detail[' + field.id_isian_kategori + '][]'"
                                                   x-model="multiValues[field.id_isian_kategori][idx]"
                                                   :required="field.wajib && idx === 0"
                                                   class="{{ $input }} flex-1"
                                                   :placeholder="field.input + ' #' + (idx + 1)">
                                        </template>
                                        <button type="button"
                                                x-show="(multiValues[field.id_isian_kategori] || []).length > 1"
                                                @click="removeMultiRow(field.id_isian_kategori, idx)"
                                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-red-200 text-red-600 hover:bg-red-50">
                                            <i class="ti ti-trash text-base"></i>
                                        </button>
                                    </div>
                                </template>
                                <button type="button" @click="addMultiRow(field.id_isian_kategori)"
                                        class="flex h-10 w-fit items-center gap-2 rounded-lg border border-brand px-3.5 text-sm font-semibold text-brand hover:bg-blue-50">
                                    <i class="ti ti-plus text-base"></i>
                                    <span x-text="'Tambah ' + field.input"></span>
                                </button>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </section>

        {{-- 3. DOKUMEN PENDUKUNG (BERKAS) --}}
        <section x-show="activeCategory !== null && fileFields.length > 0" x-cloak class="{{ $sectionClass }}" aria-labelledby="h-dokumen">
            <div class="flex items-center gap-2.5">
                <span class="{{ $numClass }}">3</span>
                <h2 id="h-dokumen" class="text-[15px] font-semibold text-gray-900">Dokumen Pendukung</h2>
            </div>

            <p class="text-[15px] text-gray-400">Unggah berkas pendukung sesuai persyaratan kategori (format PDF, JPG, JPEG, atau PNG).</p>

            <div class="flex flex-col gap-4">
                <template x-for="(field, index) in fileFields" :key="field.id_isian_kategori">
                    <div class="overflow-hidden rounded-lg border border-gray-200">
                        <div class="flex items-center justify-between gap-3 bg-[#02215c] px-3.5 py-3 text-white">
                            <h3 class="text-sm font-semibold" x-text="'Dokumen ' + (index + 1) + ' — ' + field.input"></h3>
                            <span x-show="field.wajib" class="rounded bg-white/15 px-2 py-0.5 text-xs font-medium">Wajib</span>
                        </div>
                        <div class="flex flex-col gap-2 p-3.5">
                            <label class="text-[15px] font-medium text-gray-900">
                                <span x-text="field.input"></span>
                                <span x-show="field.wajib" class="text-red-500">*</span>
                            </label>
                            <input type="file"
                                   :name="'detail_files[' + field.id_isian_kategori + ']'"
                                   accept=".pdf,.jpg,.jpeg,.png"
                                   :required="field.wajib && !savedDetails[field.id_isian_kategori]"
                                   class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-[15px] text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-blue-100 file:px-3.5 file:py-1.5 file:text-sm file:font-medium file:text-brand hover:file:bg-blue-200">
                            <template x-if="savedDetails[field.id_isian_kategori]">
                                <p class="text-xs text-emerald-600">Berkas tersimpan: <span x-text="savedDetails[field.id_isian_kategori]"></span> (unggah baru jika ingin mengganti).</p>
                            </template>
                        </div>
                    </div>
                </template>

                {{-- DOKUMEN PENDUKUNG (TAUTAN GOOGLE DRIVE / URL) --}}
                <div class="flex flex-col gap-3">
                    <div class="flex flex-col gap-1">
                        <span class="text-[13px] font-semibold leading-5 text-[#374151]">
                            Dokumen Pendukung <span class="font-normal text-[#6B7280]">(opsional, tautan Google Drive / URL)</span>
                        </span>
                        <p class="text-xs leading-[18px] text-[#6B7280]">Tambahkan tautan Google Drive atau dokumen pendukung lainnya jika diperlukan.</p>
                    </div>

                    <template x-for="(doc, idx) in documents" :key="idx">
                        <div class="flex flex-col gap-3 rounded-xl border border-[#E5E7EB] bg-[#F9FAFB] p-4">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-semibold text-[#374151]" x-text="'Dokumen #' + (idx + 1)"></span>
                                <button type="button" @click="removeDocument(idx)"
                                        class="flex h-7 w-7 items-center justify-center rounded-md text-[#DC2626] hover:bg-[#FEE2E2]">
                                    <i class="ti ti-trash text-sm"></i>
                                </button>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="flex flex-col gap-1">
                                    <label class="text-xs font-medium text-[#374151]">Nama Dokumen <span class="text-[#FF3F3F]">*</span></label>
                                    <input type="text" x-model="doc.name" :name="'documents[' + idx + '][name]'" required maxlength="255"
                                           class="{{ $input }}" placeholder="Contoh: Sertifikat / Surat Tugas">
                                </div>
                                <div class="flex flex-col gap-1">
                                    <label class="text-xs font-medium text-[#374151]">Jenis Dokumen <span class="text-[#FF3F3F]">*</span></label>
                                    <div class="relative">
                                        <select x-model="doc.type" :name="'documents[' + idx + '][type]'" required class="{{ $select }}">
                                            <template x-for="t in documentTypes" :key="t">
                                                <option :value="t" x-text="t"></option>
                                            </template>
                                        </select>
                                        <i class="ti ti-chevron-down pointer-events-none absolute right-3 top-3 text-base"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="flex flex-col gap-1">
                                <label class="text-xs font-medium text-[#374151]">Tautan Dokumen (Link Google Drive) <span class="text-[#FF3F3F]">*</span></label>
                                <input type="url" x-model="doc.url" :name="'documents[' + idx + '][url]'" required maxlength="500"
                                       class="{{ $input }}" placeholder="https://drive.google.com/...">
                            </div>
                        </div>
                    </template>

                    <button type="button" @click="addDocument()"
                            class="flex h-9 w-fit items-center gap-1.5 rounded-lg border border-[#2277FF] bg-white px-3 text-xs font-semibold text-[#2277FF] hover:bg-[#EFF6FF]">
                        <i class="ti ti-plus text-sm"></i>
                        Tambah Tautan Dokumen
                    </button>
                </div>
            </div>
        </section>

        {{-- Kotak Status Biru (Style Kelompok 3) --}}
        <div role="status" class="flex items-center gap-2.5 rounded-lg bg-blue-100 px-4 py-3.5 text-sm text-brand">
            <svg class="size-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9.5"/><path d="M12 11v5.5M12 7.5v.5"/></svg>
            <span x-text="activeCategory ? 'Lengkapi semua isian wajib (*) pada kategori ' + activeCategory.nama_kategori + ' sebelum menyimpan.' : 'Pilih kategori kegiatan terlebih dahulu untuk menampilkan formulir isian.'"></span>
        </div>

        {{-- Tombol Aksi --}}
        <div class="flex flex-wrap items-center justify-end gap-2.5">
            <a href="{{ $cancelUrl }}" class="flex h-11 items-center rounded-lg border border-gray-200 bg-white px-5 text-base font-medium text-gray-700 hover:bg-gray-50">Batal</a>
            <button type="submit" :disabled="!selectedCategoryId || submitting"
                    class="flex h-11 items-center gap-2 rounded-lg bg-brand px-5 text-base font-medium text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50">
                <span x-text="submitting ? 'Menyimpan...' : '{{ $isActivityHistory ? 'Simpan ke Riwayat' : ($item ? 'Simpan Perubahan' : 'Kirim Pengajuan') }}'"></span>
                <i class="ti text-xs" :class="submitting ? 'ti-loader-2 animate-spin' : 'ti-arrow-right'"></i>
            </button>
        </div>
    </form>
</div>
@endsection