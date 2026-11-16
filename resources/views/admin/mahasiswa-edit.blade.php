{{--
    resources/views/admin/mahasiswa-edit.blade.php
    Form edit mahasiswa -> mengambil data dari GET /api/v1/mahasiswa/{id}
    dan menyimpan perubahan via POST /api/v1/mahasiswa/{id} (_method=PUT).
--}}
@extends('layouts.admin')

@section('title', 'Edit Mahasiswa')

@php
    $mhsId     = $mahasiswa['id'] ?? request()->route('id');
    $mahasiswa = $mahasiswa ?? [];
    $tahun     = $tahun ?? range((int) date('Y'), (int) date('Y') - 9);
    $statusAkad = $statusAkad ?? ['Aktif', 'Cuti', 'Nonaktif'];
    $fotoUrl   = $fotoUrl ?? null;

    $field  = 'w-full rounded-lg border border-black/[0.06] bg-white px-4 py-1.5 text-base tracking-[-0.18px] text-[#2E2E2E] outline-none placeholder:text-[#A8A8A8] focus:border-[#2277FF]';
    $label  = 'text-base font-medium leading-[22px] tracking-[-0.18px] text-[#0A0A0A]';
    $hint   = 'text-base tracking-[-0.18px] text-[#A8A8A8]';
    $error  = 'text-sm text-[#FF3F3F]';
    $card   = 'flex flex-col gap-6 rounded-md border border-black/[0.06] bg-white px-4 pb-6 pt-4';
    $select = 'h-10 w-full cursor-pointer appearance-none rounded-lg border border-black/[0.06] bg-white px-4 text-base tracking-[-0.18px] text-[#2E2E2E] outline-none focus:border-[#2277FF]';
@endphp

@section('content')
<main class="flex flex-col gap-8 px-4 py-8 lg:px-6">
    <div class="flex flex-col gap-2">
        <h1 class="text-[32px] font-bold leading-[38px] tracking-[-0.2px]">Edit Mahasiswa</h1>
        <div class="text-lg font-medium text-[#A8A8A8] lg:text-2xl lg:leading-[30px] lg:tracking-[-0.15px]">Dashboard / Data Mahasiswa / Detail / Edit</div>
    </div>

    {{-- Kotak Alert Error Global dari API --}}
    <div id="alertError" class="hidden rounded-md border border-[#FFD9D9] bg-[#FFEDED] px-4 py-3 text-sm text-[#E5484D]" role="alert">
        <p id="alertErrorJudul" class="font-semibold">Gagal menyimpan perubahan:</p>
        <ul id="alertErrorList" class="mt-1 list-inside list-disc"></ul>
    </div>

    <form id="formEditMahasiswa" method="POST" action="{{ route('admin.mahasiswa.update', ['id' => $mhsId], false) }}" enctype="multipart/form-data" class="flex flex-col gap-6 rounded-lg bg-white px-4 pb-6 pt-4">
        @csrf
        @method('PUT')

        {{-- ============ 1. Identitas Mahasiswa ============ --}}
        <div class="{{ $card }}">
            <div class="flex items-center gap-[11px]">
                <div class="flex size-6 items-center justify-center rounded-full bg-[#2277FF] text-sm font-semibold text-white">1</div>
                <div class="text-sm font-semibold text-[#0A0A0A]">Identitas Mahasiswa</div>
            </div>
            <div class="flex flex-col gap-4">
                <div class="flex flex-col gap-4 md:flex-row">
                    <div class="flex min-w-0 flex-1 flex-col gap-2">
                        <label for="nama" class="{{ $label }}">Nama lengkap<span class="text-[#FF3F3F]"> *</span></label>
                        <input id="nama" name="nama" required value="{{ old('nama', $mahasiswa['nama'] ?? '') }}" class="{{ $field }}">
                        <p id="err-nama" class="{{ $error }}" hidden></p>
                    </div>
                    <div class="flex min-w-0 flex-1 flex-col gap-2">
                        <label for="nim" class="{{ $label }}">NIM<span class="text-[#FF3F3F]"> *</span></label>
                        <input id="nim" name="nim" required value="{{ old('nim', $mahasiswa['nim'] ?? '') }}" class="{{ $field }}">
                        <p id="err-nim" class="{{ $error }}" hidden></p>
                    </div>
                </div>
            </div>
        </div>

        {{-- ============ 2. Data Akademik ============ --}}
        <div class="{{ $card }}">
            <div class="flex items-center gap-[11px]">
                <div class="flex size-6 items-center justify-center rounded-full bg-[#2277FF] text-sm font-semibold text-white">2</div>
                <div class="text-sm font-semibold text-[#0A0A0A]">Data Akademik</div>
            </div>
            <div class="flex flex-col gap-4">
                <div class="flex flex-col gap-4 md:flex-row">
                    <div class="flex min-w-0 flex-1 flex-col gap-2">
                        <label for="angkatan" class="{{ $label }}">Angkatan (tahun masuk)<span class="text-[#FF3F3F]"> *</span></label>
                        <div class="relative">
                            <select id="angkatan" name="angkatan" class="{{ $select }}">
                                @foreach ($tahun as $y)
                                    <option value="{{ $y }}" @selected((string) old('angkatan', $mahasiswa['angkatan'] ?? '') === (string) $y)>{{ $y }}</option>
                                @endforeach
                            </select>
                            <svg class="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-[#1C2024]" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6l6 -6" /></svg>
                        </div>
                        <p id="err-angkatan" class="{{ $error }}" hidden></p>
                    </div>
                    <div class="flex min-w-0 flex-1 flex-col gap-2">
                        <label for="status" class="{{ $label }}">Status akademik<span class="text-[#FF3F3F]"> *</span></label>
                        <div class="relative">
                            <select id="status" name="status" class="{{ $select }}">
                                @foreach ($statusAkad as $s)
                                    <option value="{{ $s }}" @selected(old('status', $mahasiswa['status'] ?? '') === $s)>{{ $s }}</option>
                                @endforeach
                            </select>
                            <svg class="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-[#1C2024]" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6l6 -6" /></svg>
                        </div>
                        <p id="err-status" class="{{ $error }}" hidden></p>
                    </div>
                </div>
            </div>
        </div>

        {{-- ============ 3. Akun Login ============ --}}
        <div class="{{ $card }}">
            <div class="flex items-center gap-[11px]">
                <div class="flex size-6 items-center justify-center rounded-full bg-[#2277FF] text-sm font-semibold text-white">3</div>
                <div class="text-sm font-semibold text-[#0A0A0A]">Akun Login</div>
            </div>
            <div class="flex flex-col gap-4">
                <div class="flex flex-col gap-4 md:flex-row">
                    <div class="flex min-w-0 flex-1 flex-col gap-2">
                        <label for="email" class="{{ $label }}">Email akun<span class="text-[#FF3F3F]"> *</span></label>
                        <input id="email" name="email" type="email" required value="{{ old('email', $mahasiswa['email'] ?? '') }}" class="{{ $field }}">
                        <div class="{{ $hint }}">Email harus unik menggunakan domain @mhs.polines.ac.id.</div>
                        <p id="err-email" class="{{ $error }}" hidden></p>
                    </div>
                    <div class="hidden flex-1 md:block"></div>
                </div>

                <div class="flex flex-col gap-4 md:flex-row">
                    {{-- Password baru --}}
                    <div class="flex min-w-0 flex-1 flex-col gap-2">
                        <label for="password" class="{{ $label }}">Password baru<span class="text-[#A8A8A8]"> (opsional)</span></label>
                        <input id="password" name="password" type="password" autocomplete="new-password" placeholder="kosongkan jika tidak diubah" class="{{ $field }}">
                        <div class="{{ $hint }}">Password lama tidak ditampilkan. Isi hanya jika ingin menggantinya.</div>
                        <p id="err-password" class="{{ $error }}" hidden></p>
                    </div>

                    {{-- Foto profil --}}
                    <div class="flex min-w-0 flex-1 flex-col gap-2">
                        <span class="{{ $label }}">Foto profil<span class="text-[#A8A8A8]"> (opsional)</span></span>
                        <div class="flex items-center gap-6">
                            <div class="relative size-[96px] shrink-0">
                                <div class="flex size-full items-center justify-center overflow-hidden rounded-full bg-[#E4C9AE]">
                                    <img id="foto-preview" src="{{ $fotoUrl ?? '' }}" alt="Foto profil" class="size-full object-cover {{ $fotoUrl ? '' : 'hidden' }}">
                                    <span id="foto-inisial" class="text-5xl font-bold text-[#7A4A1E] {{ $fotoUrl ? 'hidden' : '' }}">{{ mb_strtoupper(mb_substr($mahasiswa['nama'] ?? 'M', 0, 1)) }}</span>
                                </div>
                                <label for="foto" title="Pilih foto" class="absolute bottom-0 right-0 flex size-10 cursor-pointer items-center justify-center rounded-full border-2 border-white bg-[#2277FF] text-white">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h4l10.5 -10.5a2.828 2.828 0 1 0 -4 -4l-10.5 10.5v4" /><path d="M13.5 6.5l4 4" /></svg>
                                </label>
                                <input id="foto" name="foto" type="file" accept="image/jpeg,image/png,image/webp" class="sr-only">
                            </div>
                            <div class="flex flex-col gap-2">
                                <div id="foto-hint" class="{{ $hint }}">
                                    {{ $fotoUrl ? 'Klik pensil untuk mengganti foto.' : 'Belum ada foto. Klik pensil untuk memilih.' }} JPG, PNG, atau WebP, maksimal 2 MB.
                                </div>
                                <label id="wrap-hapus-foto" class="{{ $fotoUrl ? 'flex' : 'hidden' }} items-center gap-2 text-sm text-[#2E2E2E]">
                                    <input id="hapus_foto" type="checkbox" name="hapus_foto" value="1" class="size-4 accent-[#FF3F3F]">
                                    Hapus foto saat ini
                                </label>
                            </div>
                        </div>
                        <p id="err-foto" class="{{ $error }}"></p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tombol --}}
        <div class="flex flex-wrap items-center gap-4">
            <a href="{{ route('admin.mahasiswa.show', ['id' => $mhsId], false) }}" class="cursor-pointer rounded-md bg-[#FF3F3F] px-4 py-2 text-lg font-medium leading-[22px] tracking-[-0.18px] text-[#FEFEFE] shadow-[0_1px_2px_rgba(0,0,0,0.12)]">Batal</a>
            <button type="submit" id="btnSimpan" class="cursor-pointer rounded-md bg-[#2277FF] px-4 py-2 text-lg font-medium leading-[22px] tracking-[-0.18px] text-[#FEFEFE] shadow-[0_1px_2px_rgba(0,0,0,0.12)] disabled:opacity-60">Simpan Perubahan</button>
            <span id="err-umum" class="text-sm text-[#FF3F3F]" hidden>Gagal menyimpan. Periksa kembali isian di atas.</span>
        </div>
    </form>
</main>
@endsection

@push('scripts')
<script>
(function () {
    var mhsId = @json($mhsId);
    var CSRF = @json(csrf_token());

    var form = document.getElementById('formEditMahasiswa');
    var btnSimpan = document.getElementById('btnSimpan');
    var errUmum = document.getElementById('err-umum');
    var alertError = document.getElementById('alertError');
    var alertErrorJudul = document.getElementById('alertErrorJudul');
    var alertErrorList = document.getElementById('alertErrorList');

    var inputFoto = document.getElementById('foto');
    var preview = document.getElementById('foto-preview');
    var inisial = document.getElementById('foto-inisial');
    var errFoto = document.getElementById('err-foto');
    var hapusFoto = document.getElementById('hapus_foto');
    var wrapHapusFoto = document.getElementById('wrap-hapus-foto');
    var fotoHint = document.getElementById('foto-hint');

    var allowed = ['image/jpeg', 'image/png', 'image/webp'];
    var fields = ['nama', 'nim', 'angkatan', 'status', 'email', 'password', 'foto'];

    function resetErrors() {
        errUmum.hidden = true;
        alertError.classList.add('hidden');
        alertErrorList.innerHTML = '';
        fields.forEach(function (f) {
            var el = document.getElementById('err-' + f);
            if (el) {
                el.textContent = '';
                el.hidden = true;
            }
        });
    }

    // ---------- 1. Muat Data Mahasiswa Terbaru dari GET /api/v1/mahasiswa/{id} ----------
    if (mhsId) {
        fetch('/api/v1/mahasiswa/' + encodeURIComponent(mhsId), {
            headers: { 'Accept': 'application/json' }
        })
        .then(function (res) { return res.json(); })
        .then(function (json) {
            if (!json.success || !json.data) return;
            var d = json.data;

            document.getElementById('nama').value = d.nama || '';
            document.getElementById('nim').value = d.nim || '';
            document.getElementById('email').value = d.email || '';

            // Pastikan opsi angkatan lama ada di dropdown lalu pilih
            var selAngkatan = document.getElementById('angkatan');
            if (d.angkatan && !Array.from(selAngkatan.options).some(function (o) { return String(o.value) === String(d.angkatan); })) {
                var opt = document.createElement('option');
                opt.value = d.angkatan;
                opt.textContent = d.angkatan;
                selAngkatan.appendChild(opt);
            }
            if (d.angkatan) selAngkatan.value = String(d.angkatan);

            if (d.status) document.getElementById('status').value = d.status;

            // Update foto / inisial
            inisial.textContent = (d.nama || 'M').trim().charAt(0).toUpperCase();
            if (json.fotoUrl) {
                preview.src = json.fotoUrl;
                preview.classList.remove('hidden');
                inisial.classList.add('hidden');
                wrapHapusFoto.classList.remove('hidden');
                wrapHapusFoto.classList.add('flex');
                fotoHint.textContent = 'Klik pensil untuk mengganti foto. JPG, PNG, atau WebP, maksimal 2 MB.';
            } else {
                preview.classList.add('hidden');
                inisial.classList.remove('hidden');
                wrapHapusFoto.classList.add('hidden');
                wrapHapusFoto.classList.remove('flex');
                fotoHint.textContent = 'Belum ada foto. Klik pensil untuk memilih. JPG, PNG, atau WebP, maksimal 2 MB.';
            }
        });
    }

    // ---------- 2. Preview & Validasi File Foto Lokal ----------
    inputFoto.addEventListener('change', function () {
        errFoto.textContent = '';
        errFoto.hidden = true;
        var file = inputFoto.files[0];
        if (!file) return;

        if (!allowed.includes(file.type)) {
            errFoto.textContent = 'Foto harus berformat JPG, PNG, atau WebP.';
            errFoto.hidden = false;
            inputFoto.value = '';
            return;
        }
        if (file.size > 2 * 1024 * 1024) {
            errFoto.textContent = 'Ukuran foto maksimal 2 MB.';
            errFoto.hidden = false;
            inputFoto.value = '';
            return;
        }

        preview.src = URL.createObjectURL(file);
        preview.classList.remove('hidden');
        inisial.classList.add('hidden');
        if (hapusFoto) hapusFoto.checked = false;
    });

    if (hapusFoto) {
        hapusFoto.addEventListener('change', function () {
            if (!hapusFoto.checked) return;
            inputFoto.value = '';
            preview.classList.add('hidden');
            inisial.classList.remove('hidden');
        });
    }

    // ---------- 3. Submit Perubahan ke API via FormData + _method=PUT ----------
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        resetErrors();

        btnSimpan.disabled = true;
        btnSimpan.textContent = 'Menyimpan...';

        var formData = new FormData();
        formData.append('_method', 'PUT');
        formData.append('nama', document.getElementById('nama').value.trim());
        formData.append('nim', document.getElementById('nim').value.trim());
        formData.append('angkatan', document.getElementById('angkatan').value);
        formData.append('status', document.getElementById('status').value);
        formData.append('email', document.getElementById('email').value.trim());

        var pass = document.getElementById('password').value;
        if (pass !== '') {
            formData.append('password', pass);
        }

        if (inputFoto.files[0]) {
            formData.append('foto', inputFoto.files[0]);
        } else if (hapusFoto && hapusFoto.checked) {
            formData.append('hapus_foto', '1');
        }

        fetch('/api/v1/mahasiswa/' + encodeURIComponent(mhsId), {
            method: 'POST', // PHP hanya mem-parsing multipart file di POST; _method=PUT mengarahkan ke route PUT
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': CSRF
            },
            body: formData
        })
        .then(function (res) {
            return res.json().then(function (body) {
                return { status: res.status, ok: res.ok, body: body };
            });
        })
        .then(function (res) {
            if (res.ok) {
                window.location.href = '/admin/mahasiswa/' + encodeURIComponent(mhsId);
                return;
            }

            if (res.status === 422 && res.body.errors) {
                var daftarPesan = [];

                Object.keys(res.body.errors).forEach(function (key) {
                    var pesan = res.body.errors[key][0];
                    daftarPesan.push(pesan);

                    var el = document.getElementById('err-' + key);
                    if (el && pesan) {
                        el.textContent = pesan;
                        el.hidden = false;
                    }
                });

                alertErrorJudul.textContent = res.body.message || 'Periksa kembali isian berikut:';
                alertErrorList.innerHTML = daftarPesan.map(function (m) {
                    return '<li>' + m + '</li>';
                }).join('');
                alertError.classList.remove('hidden');
                alertError.scrollIntoView({ behavior: 'smooth', block: 'center' });

                errUmum.textContent = 'Gagal menyimpan. Periksa kembali isian di atas.';
                errUmum.hidden = false;
            } else {
                alertErrorJudul.textContent = res.body.message || 'Terjadi kesalahan saat menyimpan perubahan.';
                alertError.classList.remove('hidden');
                errUmum.textContent = res.body.message || 'Terjadi kesalahan saat menyimpan perubahan.';
                errUmum.hidden = false;
            }

            btnSimpan.disabled = false;
            btnSimpan.textContent = 'Simpan Perubahan';
        })
        .catch(function () {
            alertErrorJudul.textContent = 'Gagal terhubung ke server API.';
            alertError.classList.remove('hidden');
            errUmum.textContent = 'Gagal terhubung ke server API.';
            errUmum.hidden = false;
            btnSimpan.disabled = false;
            btnSimpan.textContent = 'Simpan Perubahan';
        });
    });
})();
</script>
@endpush