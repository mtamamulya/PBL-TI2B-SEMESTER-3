{{--
    resources/views/admin/partials/berita-form.blade.php
    Isi form berita, dipakai bersama oleh berita-tambah & berita-edit (admin & kaprodi).
    Variabel: $action, $method ('POST'|'PUT'), $berita (array|null), $kategori, $kegiatanList, $max.
--}}
@php
    $b = $berita ?? [];
    $prefix = request()->routeIs('kaprodi.*') ? 'kaprodi' : 'admin';
    $labelCls = 'text-[14px] font-medium leading-[1.5] text-[#111827]';
    $inputCls = 'h-11 w-full rounded-lg border border-[#E5E7EB] bg-white px-3.5 text-[15px] text-[#111827] outline-none placeholder:text-[#9CA3AF] focus:border-[#2277FF] disabled:cursor-not-allowed disabled:bg-[#F3F4F6] disabled:text-[#9CA3AF]';
    $selectCls = 'h-11 w-full appearance-none rounded-lg border border-[#E5E7EB] bg-white px-3.5 pr-10 text-[15px] text-[#111827] outline-none focus:border-[#2277FF] disabled:cursor-not-allowed disabled:bg-[#F3F4F6] disabled:text-[#9CA3AF] cursor-pointer';
    $radioKartu = 'flex cursor-pointer items-center gap-3 rounded-lg border border-[#E5E7EB] bg-white px-4 py-3 text-[15px] font-medium text-[#111827] transition-colors has-[:checked]:border-[#2277FF] has-[:checked]:bg-[#EFF6FF]';
    $sectionCls = 'flex flex-col gap-4 rounded-xl border border-[#E5E7EB] bg-white p-5';
    $numCls = 'flex size-[26px] shrink-0 items-center justify-center rounded-full bg-[#2277FF] text-[13px] font-semibold text-white';
    $hint = 'text-xs leading-[1.5] text-[#6B7280]';
    $chev = '<svg class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 text-[#6B7280]" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6l6 -6" /></svg>';

    $isiAwal = old('isi', $b['isi'] ?? '');
    $sumber = old('sumber', $b['sumber'] ?? 'none');
    $adaFoto = ! empty($b['foto']);
    $maxKarakter = max((int) ($max ?? 5000), 5000);
    $urlUploadGambar = \Illuminate\Support\Facades\Route::has($prefix.'.berita.upload-gambar')
        ? route($prefix.'.berita.upload-gambar')
        : '';
@endphp

{{-- Styling CKEditor 5 agar menyatu dengan Tailwind & mendukung foto dokumentasi --}}
<style>
    .ck-editor__top .ck-sticky-panel .ck-toolbar {
        border-color: #E5E7EB !important;
        border-radius: 0.5rem 0.5rem 0 0 !important;
        background-color: #F9FAFB !important;
    }
    .ck.ck-editor__main > .ck-editor__editable {
        min-height: 240px;
        border-color: #E5E7EB !important;
        border-radius: 0 0 0.5rem 0.5rem !important;
        padding: 0.75rem 1rem !important;
        font-size: 15px;
        line-height: 1.7;
        color: #111827;
        box-shadow: none !important;
    }
    .ck.ck-editor__main > .ck-editor__editable.ck-focused {
        border-color: #2277FF !important;
    }
    .ck-editor__editable .ck-placeholder::before {
        color: #9CA3AF !important;
        font-style: normal !important;
    }
    .ck-content h2 { font-size: 1.35rem; font-weight: 700; margin: 0.65rem 0 0.35rem; color: #071E38; }
    .ck-content h3 { font-size: 1.15rem; font-weight: 600; margin: 0.5rem 0 0.3rem; color: #071E38; }
    .ck-content h4 { font-size: 1rem; font-weight: 600; margin: 0.4rem 0 0.25rem; color: #071E38; }
    .ck-content p { margin: 0.4rem 0; }
    .ck-content ul { list-style-type: disc; padding-left: 1.5rem; margin: 0.5rem 0; }
    .ck-content ol { list-style-type: decimal; padding-left: 1.5rem; margin: 0.5rem 0; }
    .ck-content blockquote { border-left: 3px solid #2277FF; padding-left: 0.875rem; color: #4B5563; margin: 0.6rem 0; }
    .ck-content a { color: #2277FF; text-decoration: underline; }
    .ck-content figure.image { margin: 1rem auto; text-align: center; max-width: 640px; }
    .ck-content figure.image img { max-width: 100%; max-height: 420px; object-fit: contain; border-radius: 0.5rem; margin: 0 auto; }
    .ck-content figure.image figcaption { font-size: 0.8125rem; color: #6B7280; margin-top: 0.375rem; }
    /* Sembunyikan badge watermark "Powered by CKEditor" */
    .ck-powered-by,
    .ck.ck-powered-by,
    a.ck-powered-by {
        display: none !important;
        visibility: hidden !important;
        opacity: 0 !important;
        pointer-events: none !important;
    }
</style>

<form id="form-berita-utama" method="POST" action="{{ $action }}" enctype="multipart/form-data"
      class="flex flex-col gap-5 rounded-xl bg-white p-5 shadow-[1px_0_3px_rgba(0,0,0,0.02),5px_2px_6px_rgba(0,0,0,0.02)]">
    @csrf
    @if ($method !== 'POST') @method($method) @endif

    <p class="{{ $hint }}">* Wajib diisi &bull; Kolom bertanda opsional dapat dikosongkan.</p>

    @if ($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
            {{ $errors->first() }}
        </div>
    @endif

    {{-- 1. SUMBER KEGIATAN --}}
    <section class="{{ $sectionCls }}">
        <div class="flex items-center gap-2.5">
            <span class="{{ $numCls }}">1</span>
            <div>
                <h2 class="text-[16px] font-bold text-[#111827]">Sumber Kegiatan</h2>
                <p class="{{ $hint }}">Kegiatan terkait bersifat opsional. Anda tetap dapat mencatat berita secara mandiri.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            @foreach ([['prev', 'Dari kegiatan sebelumnya'], ['none', 'Tanpa kegiatan sebelumnya']] as [$v, $l])
                <label class="{{ $radioKartu }}">
                    <input type="radio" name="sumber" value="{{ $v }}" class="size-4 accent-[#2277FF]" @checked($sumber === $v)>
                    <span>{{ $l }}</span>
                </label>
            @endforeach
        </div>

        <div class="grid grid-cols-1 gap-4 rounded-lg border border-[#E5E7EB] bg-[#F9FAFB] p-4 md:grid-cols-2">
            <div class="flex flex-col gap-1.5">
                <label for="kegiatan_id" class="{{ $labelCls }}">Kegiatan sebelumnya <span class="font-normal text-[#6B7280]">(opsional)</span></label>
                <div class="relative">
                    <select id="kegiatan_id" name="kegiatan_id" class="{{ $selectCls }}">
                        <option value="">Cari dan pilih kegiatan</option>
                        @foreach ($kegiatanList as $k)
                            <option value="{{ $k['id'] }}" data-kategori="{{ $k['kategori'] }}" @selected((string) old('kegiatan_id', $b['kegiatan_id'] ?? '') === (string) $k['id'])>{{ $k['nama'] }} &bull; {{ $k['kategori'] }}</option>
                        @endforeach
                    </select>
                    {!! $chev !!}
                </div>
                <span class="{{ $hint }}">Aktif jika memilih &lsquo;Dari kegiatan sebelumnya&rsquo;.</span>
                @error('kegiatan_id') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </div>

            <div class="flex flex-col gap-1.5">
                <label for="kategori" class="{{ $labelCls }}">Kategori Kegiatan *</label>
                <div class="relative">
                    <select id="kategori" name="kategori" class="{{ $selectCls }}">
                        <option value="">Pilih kategori kegiatan</option>
                        @foreach ($kategori as $k)
                            <option value="{{ $k }}" @selected(old('kategori', $b['kegiatan'] ?? '') === $k)>{{ $k }}</option>
                        @endforeach
                    </select>
                    {!! $chev !!}
                </div>
                <span class="{{ $hint }}">Aktif ketika memilih &lsquo;Tanpa kegiatan sebelumnya&rsquo;.</span>
                @error('kategori') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </div>
        </div>

        {{-- Panel Foto Dokumentasi Kegiatan --}}
        <div id="panel-dokumentasi-kegiatan" class="hidden flex-col gap-3 rounded-lg border border-[#DBEAFE] bg-[#F8FAFF] p-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h3 class="text-sm font-semibold text-[#071E38]">Dokumentasi Foto Kegiatan</h3>
                    <p class="{{ $hint }}">Klik <strong>Sisipkan ke Isi</strong> untuk memasukkan foto ke editor, atau <strong>Jadikan Sampul</strong> untuk memakainya sebagai sampul berita.</p>
                </div>
            </div>
            <div id="daftar-dokumentasi-kegiatan" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3"></div>
            <p id="kosong-dokumentasi-kegiatan" class="hidden text-xs text-[#6B7280]">
                Kegiatan ini belum memiliki dokumentasi foto. Anda tetap dapat mengunggah gambar baru langsung di editor atau pada foto sampul di bawah.
            </p>
        </div>
    </section>

    {{-- 2. INFORMASI BERITA (RICH TEXT + SISIP FOTO) --}}
    <section class="{{ $sectionCls }}">
        <div class="flex items-center gap-2.5">
            <span class="{{ $numCls }}">2</span>
            <div>
                <h2 class="text-[16px] font-bold text-[#111827]">Informasi Berita</h2>
                <p class="{{ $hint }}">Isikan judul dan isi berita. Anda dapat memformat teks dan menyisipkan foto dokumentasi langsung di dalam isi berita.</p>
            </div>
        </div>

        <div class="flex flex-col gap-4">
            <div class="flex flex-col gap-1.5">
                <label for="judul" class="{{ $labelCls }}">Judul Berita *</label>
                <input id="judul" name="judul" type="text" required value="{{ old('judul', $b['judul'] ?? '') }}"
                       placeholder="Mahasiswa TRK Borong Juara di Kompetisi IT Nasional"
                       class="{{ $inputCls }}">
                @error('judul') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </div>

            <div class="flex flex-col gap-1.5">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <label for="isi" class="{{ $labelCls }}">Isi Berita *</label>
                    <span class="text-xs font-medium text-[#2277FF]">Gunakan ikon gambar pada toolbar untuk menyisipkan foto dokumentasi</span>
                </div>
                <textarea id="isi" name="isi" placeholder="Tuliskan isi berita di sini"
                          class="min-h-[200px] w-full rounded-lg border border-[#E5E7EB] bg-white p-3.5 text-[15px] text-[#111827] outline-none focus:border-[#2277FF]">{{ $isiAwal }}</textarea>
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span id="upload-konten-status" class="{{ $hint }}">Mendukung format paragraf, subjudul, daftar poin, tautan, dan sisipan gambar (JPG/PNG/WebP maks. 5 MB).</span>
                    <span class="{{ $hint }}">Jumlah karakter teks: <strong id="char-count">0</strong></span>
                </div>
                @error('isi') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </div>
        </div>
    </section>

    {{-- 3. FOTO SAMPUL BERITA --}}
    <section class="{{ $sectionCls }}">
        <div class="flex items-center gap-2.5">
            <span class="{{ $numCls }}">3</span>
            <div>
                <h2 class="text-[16px] font-bold text-[#111827]">Foto Sampul Berita</h2>
                <p class="{{ $hint }}">Unggah foto utama yang ditampilkan pada kartu berita dan bagian atas halaman detail.</p>
            </div>
        </div>

        <div class="overflow-hidden rounded-lg border border-[#E5E7EB]">
            <div class="bg-[#071E38] px-4 py-2.5 text-white">
                <h3 class="text-sm font-semibold">Berkas Sampul Utama</h3>
            </div>
            <div class="flex flex-col gap-3 p-4">
                <label class="flex cursor-pointer flex-col items-center gap-2 rounded-lg border border-dashed border-[#AAC8F8] bg-[#F9FAFB] px-4 py-6 text-center transition-colors hover:bg-[#EFF6FF]">
                    <svg class="text-[#2277FF]" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2"/><path d="M7 9l5 -5l5 5"/><path d="M12 4l0 12"/></svg>
                    <span class="text-sm font-semibold text-[#2277FF]">Pilih berkas sampul dari perangkat</span>
                    <span class="{{ $hint }}">PDF, JPG, atau PNG &bull; Maksimal 5 MB per berkas</span>
                    <input id="foto" name="foto" type="file" accept=".pdf,.jpg,.jpeg,.png" class="sr-only">
                    <input type="hidden" id="sampul_dokumen_id" name="sampul_dokumen_id" value="{{ old('sampul_dokumen_id', '') }}">
                </label>

                <div id="sampul-preview-wrap" class="{{ $adaFoto && preg_match('/\.(jpe?g|png|webp)$/i', (string) ($b['foto'] ?? '')) ? 'flex' : 'hidden' }} items-center gap-4 rounded-lg border border-[#E5E7EB] bg-[#F9FAFB] p-3">
                    <img id="sampul-preview-img" src="{{ $adaFoto ? $b['foto'] : '' }}" alt="Pratinjau sampul" class="h-20 w-32 rounded-md object-cover">
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-semibold text-[#111827]">Pratinjau Foto Sampul</p>
                        <p id="file-status" class="truncate {{ $hint }}">{{ $adaFoto ? 'Berkas saat ini: '.basename($b['foto']) : 'Belum ada berkas diunggah.' }}</p>
                    </div>
                </div>
                @if (! $adaFoto)
                    <p id="file-status-empty" class="{{ $hint }}">Belum ada berkas diunggah. Biarkan kosong jika tidak ingin mengganti sampul.</p>
                @endif
                @error('foto') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </div>
        </div>
    </section>

    {{-- TOMBOL AKSI --}}
    <div class="flex flex-col gap-3 pt-2 sm:flex-row sm:items-center sm:justify-between">
        <p class="{{ $hint }}">Periksa kembali judul, isi berita, dan foto sebelum menyimpan.</p>
        <div class="flex flex-wrap gap-3 text-sm font-semibold">
            <a href="{{ $batal ?? route($prefix.'.berita') }}" class="flex h-10 items-center rounded-lg border border-[#E5E7EB] bg-white px-4 text-[#55555C] hover:bg-[#F9FAFB]">Batal</a>
            <button type="submit" name="status" value="Draft" class="h-10 cursor-pointer rounded-lg bg-[#CDDEF9] px-4 text-[#2277FF] hover:bg-[#BBD2F6]">Simpan Draft</button>
            <button type="submit" name="status" value="Publish" class="h-10 cursor-pointer rounded-lg bg-[#2277FF] px-5 text-white hover:bg-[#1A66E0]">Publish Berita</button>
        </div>
    </div>
</form>

@push('scripts')
<script src="https://cdn.ckeditor.com/ckeditor5/39.0.1/classic/ckeditor.js"></script>
<script>
(function () {
    var uploadUrl = @js($urlUploadGambar);
    var csrfToken = @js(csrf_token());
    var textarea = document.getElementById('isi');
    var counter = document.getElementById('char-count');
    var statusKonten = document.getElementById('upload-konten-status');
    var ckInstance = null;

    function hitungTeks(html) {
        var div = document.createElement('div');
        div.innerHTML = html || '';
        return (div.textContent || div.innerText || '').trim().length;
    }

    function BeritaImageUploadAdapter(loader) {
        this.loader = loader;
    }
    BeritaImageUploadAdapter.prototype.upload = function () {
        var loader = this.loader;
        return loader.file.then(function (file) {
            return new Promise(function (resolve, reject) {
                if (!uploadUrl) {
                    reject('Endpoint unggah gambar belum tersedia.');
                    return;
                }
                if (file.size > 5 * 1024 * 1024) {
                    reject('Ukuran gambar maksimal 5 MB.');
                    return;
                }
                statusKonten.textContent = 'Mengunggah gambar dokumentasi...';
                var data = new FormData();
                data.append('upload', file);

                fetch(uploadUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: data
                })
                .then(function (res) {
                    return res.json().then(function (json) {
                        if (!res.ok || !json.url) {
                            throw new Error((json.error && json.error.message) || json.message || 'Gagal mengunggah gambar.');
                        }
                        statusKonten.textContent = 'Gambar dokumentasi berhasil disisipkan ke dalam isi berita.';
                        resolve({ default: json.url });
                    });
                })
                .catch(function (err) {
                    statusKonten.textContent = 'Gagal mengunggah gambar: ' + err.message;
                    reject(err.message);
                });
            });
        });
    };
    BeritaImageUploadAdapter.prototype.abort = function () {};

    function BeritaUploadAdapterPlugin(editor) {
        editor.plugins.get('FileRepository').createUploadAdapter = function (loader) {
            return new BeritaImageUploadAdapter(loader);
        };
    }

    counter.textContent = hitungTeks(textarea.value);

    if (typeof ClassicEditor !== 'undefined' && textarea) {
        ClassicEditor.create(textarea, {
            placeholder: 'Tuliskan isi berita atau sisipkan foto dokumentasi kegiatan di sini...',
            extraPlugins: [BeritaUploadAdapterPlugin],
            toolbar: [
                'heading', '|',
                'bold', 'italic', 'link', 'uploadImage', '|',
                'bulletedList', 'numberedList', 'blockQuote', '|',
                'undo', 'redo'
            ]
        }).then(function (editor) {
            ckInstance = editor;
            counter.textContent = hitungTeks(editor.getData());
            editor.model.document.on('change:data', function () {
                var html = editor.getData();
                textarea.value = html;
                counter.textContent = hitungTeks(html);
            });
        }).catch(function (err) {
            console.error('Gagal memuat CKEditor:', err);
        });
    } else if (textarea) {
        textarea.addEventListener('input', function () {
            counter.textContent = hitungTeks(textarea.value);
        });
    }

    var form = document.getElementById('form-berita-utama');
    if (form) {
        form.addEventListener('submit', function () {
            if (ckInstance) {
                textarea.value = ckInstance.getData();
            }
        });
    }

    // Pratinjau foto sampul
    var foto = document.getElementById('foto');
    var previewWrap = document.getElementById('sampul-preview-wrap');
    var previewImg = document.getElementById('sampul-preview-img');
    var fileStatus = document.getElementById('file-status');
    var fileStatusEmpty = document.getElementById('file-status-empty');

    if (foto) {
        foto.addEventListener('change', function () {
            if (!foto.files.length) return;
            var f = foto.files[0];
            if (fileStatusEmpty) fileStatusEmpty.classList.add('hidden');
            previewWrap.classList.remove('hidden');
            previewWrap.classList.add('flex');
            if (fileStatus) fileStatus.textContent = 'Berkas dipilih: ' + f.name;

            if (f.type && f.type.indexOf('image/') === 0) {
                var reader = new FileReader();
                reader.onload = function (ev) {
                    previewImg.src = ev.target.result;
                    previewImg.classList.remove('hidden');
                };
                reader.readAsDataURL(f);
            } else {
                previewImg.classList.add('hidden');
            }
        });
    }

    // Sinkronisasi sumber kegiatan
    var radios = document.querySelectorAll('input[name="sumber"]');
    var kegiatan = document.getElementById('kegiatan_id');
    var kategori = document.getElementById('kategori');

    function sync() {
        var checked = document.querySelector('input[name="sumber"]:checked');
        var prev = checked && checked.value === 'prev';
        kegiatan.disabled = !prev;
        kategori.disabled = prev;
        if (prev && kegiatan.selectedOptions[0] && kegiatan.selectedOptions[0].dataset.kategori) {
            kategori.value = kegiatan.selectedOptions[0].dataset.kategori;
        }
    }

    radios.forEach(function (r) { r.addEventListener('change', sync); });
    kegiatan.addEventListener('change', sync);
    sync();

    var kegiatanDocsMap = {};
    @json($kegiatanList).forEach(function (item) {
        kegiatanDocsMap[String(item.id)] = item.dokumentasi || [];
    });

    var panelDok = document.getElementById('panel-dokumentasi-kegiatan');
    var listDok = document.getElementById('daftar-dokumentasi-kegiatan');
    var kosongDok = document.getElementById('kosong-dokumentasi-kegiatan');
    var sampulDokId = document.getElementById('sampul_dokumen_id');

    function escHtml(str) {
        return String(str == null ? '' : str).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function sisipkanFotoKeEditor(url, nama) {
        if (!ckInstance) return;
        ckInstance.model.change(function (writer) {
            var imageElement = writer.createElement('imageBlock', {
                src: url,
                alt: nama || 'Dokumentasi Kegiatan'
            });
            ckInstance.model.insertContent(imageElement, ckInstance.model.document.selection);
        });
        statusKonten.textContent = 'Foto dokumentasi "' + (nama || 'kegiatan') + '" berhasil disisipkan ke dalam isi berita.';
    }

    function renderDokumentasiKegiatan() {
        var checked = document.querySelector('input[name="sumber"]:checked');
        var prev = checked && checked.value === 'prev';
        var selectedId = kegiatan.value;

        if (!prev || !selectedId) {
            panelDok.classList.add('hidden');
            panelDok.classList.remove('flex');
            listDok.innerHTML = '';
            if (sampulDokId) sampulDokId.value = '';
            return;
        }

        panelDok.classList.remove('hidden');
        panelDok.classList.add('flex');

        var docs = kegiatanDocsMap[String(selectedId)] || [];
        if (!docs.length) {
            listDok.innerHTML = '';
            kosongDok.classList.remove('hidden');
            return;
        }

        kosongDok.classList.add('hidden');
        listDok.innerHTML = docs.map(function (d) {
            return (
                '<div class="flex flex-col overflow-hidden rounded-lg border border-[#E5E7EB] bg-white">' +
                    '<img src="' + escHtml(d.url) + '" alt="' + escHtml(d.nama) + '" class="h-32 w-full object-cover bg-[#F3F4F6]">' +
                    '<div class="flex flex-1 flex-col justify-between gap-2 p-3">' +
                        '<div>' +
                            '<p class="truncate text-xs font-semibold text-[#111827]" title="' + escHtml(d.nama) + '">' + escHtml(d.nama) + '</p>' +
                            '<span class="text-[11px] text-[#6B7280]">' + escHtml(d.jenis) + '</span>' +
                        '</div>' +
                        '<div class="flex gap-2">' +
                            '<button type="button" data-dok-sisip data-url="' + escHtml(d.url) + '" data-nama="' + escHtml(d.nama) + '" class="flex-1 cursor-pointer rounded-md bg-[#EFF6FF] px-2 py-1.5 text-xs font-semibold text-[#2277FF] hover:bg-[#DBEAFE]">Sisipkan ke Isi</button>' +
                            '<button type="button" data-dok-sampul data-id="' + escHtml(d.id_dokumen) + '" data-url="' + escHtml(d.url) + '" data-nama="' + escHtml(d.nama) + '" class="cursor-pointer rounded-md border border-[#E5E7EB] bg-white px-2 py-1.5 text-xs font-medium text-[#374151] hover:bg-[#F9FAFB]">Sampul</button>' +
                        '</div>' +
                    '</div>' +
                '</div>'
            );
        }).join('');
    }

    if (listDok) {
        listDok.addEventListener('click', function (e) {
            var btnSisip = e.target.closest('button[data-dok-sisip]');
            if (btnSisip) {
                sisipkanFotoKeEditor(btnSisip.dataset.url, btnSisip.dataset.nama);
                return;
            }
            var btnSampul = e.target.closest('button[data-dok-sampul]');
            if (btnSampul) {
                if (sampulDokId) sampulDokId.value = btnSampul.dataset.id;
                if (foto) foto.value = '';
                if (fileStatusEmpty) fileStatusEmpty.classList.add('hidden');
                previewWrap.classList.remove('hidden');
                previewWrap.classList.add('flex');
                previewImg.src = btnSampul.dataset.url;
                previewImg.classList.remove('hidden');
                if (fileStatus) fileStatus.textContent = 'Mengambil dari dokumentasi kegiatan: ' + btnSampul.dataset.nama;
            }
        });
    }

    if (foto) {
        foto.addEventListener('change', function () {
            if (foto.files.length && sampulDokId) {
                sampulDokId.value = '';
            }
        });
    }

    radios.forEach(function (r) { r.addEventListener('change', renderDokumentasiKegiatan); });
    kegiatan.addEventListener('change', renderDokumentasiKegiatan);
    renderDokumentasiKegiatan();
})();
</script>
@endpush