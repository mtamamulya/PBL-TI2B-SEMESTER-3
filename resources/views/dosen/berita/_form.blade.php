{{--
Form pengajuan berita. Dipakai bersama oleh halaman Ajukan Berita (create) dan Edit Berita (edit).
--}}
@php
$inputClass   = 'h-11 w-full rounded-lg border border-gray-200 bg-white px-3.5 text-[15px] text-gray-900 placeholder:text-gray-400 disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-400';
$selectClass  = 'h-11 w-full appearance-none rounded-lg border border-gray-200 bg-white pr-10 pl-3.5 text-[15px] text-gray-900 disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-400';
$labelClass   = 'text-[15px] font-medium';
$hintClass    = 'text-[13px] text-gray-500';
$radioKartu   = 'flex cursor-pointer items-center gap-3 rounded-lg border border-gray-200 bg-white px-4 py-3 text-[15px] has-[:checked]:border-brand has-[:checked]:bg-blue-50 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand/40';
$sectionClass = 'flex flex-col gap-4.5 rounded-lg border border-gray-200 bg-white p-4.5';
$numClass     = 'flex size-[26px] shrink-0 items-center justify-center rounded-full bg-brand text-[13px] font-semibold text-white';
$chevron      = '<svg class="pointer-events-none absolute top-1/2 right-3.5 size-4 -translate-y-1/2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>';
@endphp

<style>
.ck-editor__top .ck-sticky-panel .ck-toolbar { border-color: #e5e7eb !important; border-radius: 0.5rem 0.5rem 0 0 !important; background-color: #f9fafb !important; }
.ck.ck-editor__main > .ck-editor__editable { min-height: 180px; border-color: #e5e7eb !important; border-radius: 0 0 0.5rem 0.5rem !important; padding: 0.5rem 0.875rem !important; font-size: 15px; color: #111827; box-shadow: none !important; }
.ck.ck-editor__main > .ck-editor__editable.ck-focused { border-color: #1f66e5 !important; }
.ck-content h2 { font-size: 1.35rem; font-weight: 700; margin: 0.5rem 0; }
.ck-content h3 { font-size: 1.15rem; font-weight: 600; margin: 0.4rem 0; }
.ck-content ul { list-style-type: disc; padding-left: 1.5rem; margin: 0.4rem 0; }
.ck-content ol { list-style-type: decimal; padding-left: 1.5rem; margin: 0.4rem 0; }
.ck-powered-by, .ck.ck-powered-by, a.ck-powered-by { display: none !important; }
</style>

<form x-data="formBerita()" x-on:submit.prevent="ajukan()"
      method="POST" action="#" enctype="multipart/form-data"
      class="flex flex-col gap-5 rounded-lg bg-white p-4.5 shadow-sm">
    @csrf

    {{-- 1. SUMBER KEGIATAN --}}
    <section class="{{ $sectionClass }}">
        <div class="flex items-center gap-2.5">
            <span class="{{ $numClass }}">1</span>
            <div>
                <h2 class="text-[15px] font-semibold">Sumber Kegiatan</h2>
                <p class="{{ $hintClass }}">Kegiatan terkait bersifat opsional. Anda tetap dapat membuat berita secara mandiri.</p>
            </div>
        </div>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <label class="{{ $radioKartu }}">
                <input type="radio" name="sumber" value="kegiatan" x-model="sumber" required class="size-4 accent-[#1f66e5]"> Dari kegiatan sebelumnya
            </label>
            <label class="{{ $radioKartu }}">
                <input type="radio" name="sumber" value="mandiri" x-model="sumber" required class="size-4 accent-[#1f66e5]"> Tanpa kegiatan sebelumnya
            </label>
        </div>
        <div class="grid grid-cols-1 gap-4.5 md:grid-cols-2 mt-3">
            <div class="flex flex-col gap-2">
                <label for="kegiatan-lama" class="{{ $labelClass }}">Kegiatan sebelumnya <span class="font-normal text-gray-500">(opsional)</span></label>
                <div class="relative">
                    <svg class="pointer-events-none absolute top-1/2 left-3 size-5 -translate-y-1/2 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M16.5 16.5L21 21"/></svg>
                    <input id="kegiatan-lama" name="kegiatan_sebelumnya" type="text" list="daftar-kegiatan"
                           :disabled="sumber !== 'kegiatan'" placeholder="Cari dan pilih kegiatan" class="{{ $inputClass }} pl-10">
                    <datalist id="daftar-kegiatan">
                        <template x-for="k in kegiatanList" :key="k.id">
                            <option :value="k.nama"></option>
                        </template>
                    </datalist>
                </div>
            </div>
            <div class="flex flex-col gap-2">
                <label for="kategori" class="{{ $labelClass }}">Kategori Kegiatan</label>
                <div class="relative">
                    <select id="kategori" name="kategori" x-model="kategori" :disabled="sumber !== 'mandiri'" :required="sumber === 'mandiri'" class="{{ $selectClass }}">
                        <option value="">Pilih kategori kegiatan</option>
                        <template x-for="k in kategoriList" :key="k.id_kategori">
                            <option :value="k.nama_kategori" x-text="k.nama_kategori"></option>
                        </template>
                    </select>
                    {!! $chevron !!}
                </div>
            </div>
        </div>
    </section>

    {{-- 2. INFORMASI BERITA --}}
    <section class="{{ $sectionClass }}">
        <div class="flex items-center gap-2.5">
            <span class="{{ $numClass }}">2</span>
            <h2 class="text-[15px] font-semibold">Informasi Berita</h2>
        </div>
        <div class="grid grid-cols-1 gap-4.5 mt-3">
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
    <section class="{{ $sectionClass }}">
        <div class="flex items-center gap-2.5">
            <span class="{{ $numClass }}">3</span>
            <div>
                <h2 class="text-[15px] font-semibold">Foto Sampul Berita</h2>
                <p class="{{ $hintClass }}">Unggah foto yang menunjukkan nama, capaian, atau bukti kegiatan.</p>
            </div>
        </div>
        <div class="overflow-hidden rounded-lg border border-gray-200 mt-3">
            <div class="bg-[#02215c] px-3.5 py-3 text-white">
                <h3 class="text-sm font-semibold">Foto Sampul</h3>
            </div>
            <div class="flex flex-col gap-2 p-3.5">
                <span class="{{ $labelClass }}">File</span>
                <label class="relative flex cursor-pointer flex-col items-center gap-2 rounded-md border border-dashed border-brand bg-white px-4 py-5 text-center hover:bg-blue-50 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand">
                    <input type="file" name="foto" class="sr-only" accept=".jpg,.jpeg,.png,.webp"
                           x-on:change="pilihFile($event)">
                    <span class="flex items-center gap-2 text-[13px] font-medium text-brand">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M7 18a4.5 4.5 0 0 1-.6-8.96A6 6 0 0 1 18 8.5a4 4 0 0 1-.5 7.97M12 20v-8M9 15l3-3 3 3"/></svg>
                        <span x-text="namaFile || 'Pilih file dari perangkat'"></span>
                    </span>
                    <span class="text-xs text-gray-500">jpg, jpeg, png, webp, maksimal 2 MB.</span>
                </label>
                <p x-show="galatFile" x-cloak x-text="galatFile" role="alert" class="text-sm text-red-600"></p>
                <template x-if="editId && !namaFile">
                    <p class="text-xs text-gray-500 mt-1">* Biarkan kosong jika tidak ingin mengganti foto yang sudah ada.</p>
                </template>
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
<script>
async function apiBerita(path = '', method = 'GET', data = null) {
    const headers = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || @js(csrf_token());
    if (method !== 'GET' && csrfToken) { headers['X-CSRF-TOKEN'] = csrfToken; }
    let body = null;
    if (data !== null) {
        if (data instanceof FormData) { body = data; }
        else { headers['Content-Type'] = 'application/json'; body = JSON.stringify(data); }
    }
    const response = await fetch('/api/v1/dosen/berita' + path, { method, headers, credentials: 'same-origin', body });
    const result = await response.json();
    if (!response.ok) {
        if (response.status === 401) { throw new Error('Belum login atau sesi telah berakhir. Silakan login ulang.'); }
        if (response.status === 403) { throw new Error('Anda tidak memiliki akses untuk melakukan ini.'); }
        if (response.status === 422 && result.errors) { throw new Error(Object.values(result.errors).flat().join(', ')); }
        throw new Error(result.message || 'Gagal mengakses berita.');
    }
    return result;
}

document.addEventListener('alpine:init', () => {
    Alpine.data('formBerita', () => ({
        sumber: '', kategori: '', judul: '', isi: '',
        namaFile: '', galatFile: '', loading: false,
        kegiatanList: [], kategoriList: [], editId: null,

        async init() {
            const pathParts = window.location.pathname.split('/');
            const editIdx = pathParts.indexOf('edit');
            if (editIdx > 0) { this.editId = parseInt(pathParts[editIdx - 1]); }

            if (this.editId) {
                try {
                    const { data } = await apiBerita(`/${this.editId}`);
                    this.sumber = data.sumber || '';
                    this.kategori = data.kategori || '';
                    this.judul = data.judul || '';
                    this.isi = data.isi || '';
                    this.namaFile = data.sampul ? 'Foto sudah ada (upload baru untuk mengganti)' : '';
                } catch (e) { console.error('Gagal memuat data berita:', e); }
            }

            try {
                const { data } = await apiBerita('/opsi');
                this.kegiatanList = data.kegiatan || [];
                this.kategoriList = data.kategori || [];
            } catch (e) { console.error('Gagal memuat opsi:', e); }

            this.$nextTick(() => {
                if (!this.$refs.editorIsi || typeof ClassicEditor === 'undefined') return;
                ClassicEditor.create(this.$refs.editorIsi, {
                    placeholder: 'Tuliskan isi berita di sini',
                    toolbar: ['heading', '|', 'bold', 'italic', 'link', 'bulletedList', 'numberedList', 'blockQuote', '|', 'undo', 'redo'],
                }).then((editor) => {
                    if (this.isi) editor.setData(this.isi);
                    editor.model.document.on('change:data', () => { this.isi = editor.getData(); });
                }).catch((error) => console.error('Gagal memuat CKEditor:', error));
            });
        },

        get panjangTeks() {
            const div = document.createElement('div');
            div.innerHTML = this.isi || '';
            return (div.textContent || div.innerText || '').trim().length;
        },

        pilihFile(e) {
            const f = e.target.files[0];
            this.galatFile = '';
            if (!f) { this.namaFile = ''; return; }
            if (f.size > 2 * 1024 * 1024) {
                e.target.value = ''; this.namaFile = '';
                this.galatFile = 'Ukuran file melebihi 2 MB.'; return;
            }
            const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
            if (!validTypes.includes(f.type)) {
                e.target.value = ''; this.namaFile = '';
                this.galatFile = 'Format file harus JPG, JPEG, PNG, atau WebP.'; return;
            }
            this.namaFile = f.name;
        },

        async simpanDraft() {
            if (this.judul.trim() === '') {
                this.$dispatch('tampil-alert', { tipe: 'peringatan', judul: 'Judul belum diisi', pesan: 'Isi judul berita sebelum menyimpan draf.' });
                return;
            }
            await this.kirimKeAPI('draft');
        },

        async ajukan() {
            if (this.panjangTeks === 0) {
                this.$dispatch('tampil-alert', { tipe: 'peringatan', judul: 'Isi berita belum diisi', pesan: 'Tuliskan isi berita terlebih dahulu.' });
                return;
            }
            if (this.panjangTeks > 1000) {
                this.$dispatch('tampil-alert', { tipe: 'peringatan', judul: 'Isi terlalu panjang', pesan: 'Maksimal 1.000 karakter.' });
                return;
            }
            await this.kirimKeAPI('diajukan');
        },

        async kirimKeAPI(status) {
            if (this.loading) return;
            this.loading = true;

            try {
                const formData = new FormData();
                formData.append('sumber', this.sumber);
                formData.append('judul', this.judul);
                formData.append('isi', this.isi);
                formData.append('status', status);

                if (this.sumber === 'kegiatan') {
                    const kegiatanInput = document.getElementById('kegiatan-lama');
                    if (kegiatanInput && kegiatanInput.value) formData.append('kegiatan_sebelumnya', kegiatanInput.value);
                }
                if (this.sumber === 'mandiri' && this.kategori) {
                    formData.append('kategori', this.kategori);
                }

                const fotoInput = document.querySelector('input[name="foto"]');
                if (fotoInput && fotoInput.files[0]) {
                    formData.append('foto', fotoInput.files[0]);
                }

                // Mode edit: kirim sebagai POST + topeng _method=PUT (bisa upload file)
                if (this.editId) {
                    formData.append('_method', 'PUT');
                    await apiBerita(`/${this.editId}`, 'POST', formData);
                } else {
                    await apiBerita('', 'POST', formData);
                }

                this.$dispatch('tampil-alert', {
                    tipe: 'sukses',
                    judul: status === 'diajukan' ? 'Berita berhasil diajukan' : 'Draf tersimpan',
                    pesan: status === 'diajukan'
                        ? 'Berita Anda sedang menunggu review admin.'
                        : 'Draf berhasil disimpan.'
                });

                setTimeout(() => { window.location.href = '{{ route("berita.index") }}'; }, 1500);

            } catch (e) {
                this.$dispatch('tampil-alert', { tipe: 'error', judul: 'Gagal menyimpan', pesan: e.message });
            } finally {
                this.loading = false;
            }
        }
    }));
});
</script>
@endpush