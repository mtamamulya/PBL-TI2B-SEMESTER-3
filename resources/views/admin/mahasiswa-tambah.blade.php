{{--
    resources/views/admin/mahasiswa-tambah.blade.php
    Form tambah mahasiswa -> POST /api/v1/mahasiswa (Api\MahasiswaController@store).
--}}
@extends('layouts.admin')

@section('title', 'Tambah Mahasiswa')

@php
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
        <h1 class="text-[32px] font-bold leading-[38px] tracking-[-0.2px]">Tambah Mahasiswa</h1>
        <x-admin.breadcrumb :items="[['Dashboard', 'admin.dashboard'], ['Data Mahasiswa', 'admin.mahasiswa'], ['Tambah']]" />
    </div>

    {{-- Kotak Alert Error Global dari API --}}
    <div id="alertError" class="hidden rounded-md border border-[#FFD9D9] bg-[#FFEDED] px-4 py-3 text-sm text-[#E5484D]" role="alert">
        <p id="alertErrorJudul" class="font-semibold">Gagal menyimpan data mahasiswa:</p>
        <ul id="alertErrorList" class="mt-1 list-inside list-disc"></ul>
    </div>

    <form id="formTambahMahasiswa" method="POST" action="{{ route('admin.mahasiswa.store') }}" class="flex flex-col gap-6 rounded-lg bg-white px-4 pb-6 pt-4">
        @csrf

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
                        <input id="nama" name="nama" required value="{{ old('nama') }}" placeholder="Contoh: Galih Virgi Pramudya" class="{{ $field }}">
                        <p id="err-nama" class="{{ $error }}" hidden></p>
                    </div>
                    <div class="flex min-w-0 flex-1 flex-col gap-2">
                        <label for="nim" class="{{ $label }}">NIM<span class="text-[#FF3F3F]"> *</span></label>
                        <input id="nim" name="nim" required value="{{ old('nim') }}" placeholder="Contoh: 4.33.25.1.09" class="{{ $field }}">
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
                                    <option value="{{ $y }}" @selected((string) old('angkatan', date('Y')) === (string) $y)>{{ $y }}</option>
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
                                    <option value="{{ $s }}" @selected(old('status', 'Aktif') === $s)>{{ $s }}</option>
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
                        <input id="email" name="email" type="email" required value="{{ old('email') }}" placeholder="nama@mhs.polines.ac.id" class="{{ $field }}">
                        <div class="{{ $hint }}">Email harus unik menggunakan domain @mhs.polines.ac.id.</div>
                        <p id="err-email" class="{{ $error }}" hidden></p>
                    </div>
                    <div class="hidden flex-1 md:block"></div>
                </div>
                <div class="flex md:w-[calc(50%-8px)]">
                    <div class="flex min-w-0 flex-1 flex-col gap-2">
                        <label for="password" class="{{ $label }}">Password<span class="text-[#FF3F3F]"> *</span></label>
                        <input id="password" name="password" type="password" required autocomplete="new-password" placeholder="Minimal 8 karakter" class="{{ $field }}">
                        <div class="{{ $hint }}">Password awal untuk login mahasiswa. Minimal 8 karakter.</div>
                        <p id="err-password" class="{{ $error }}" hidden></p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tombol --}}
        <div class="flex flex-wrap items-center gap-4">
            <a href="{{ route('admin.mahasiswa') }}" class="rounded-md bg-[#FF3F3F] cursor-pointer px-4 py-2 text-lg font-medium leading-[22px] tracking-[-0.18px] text-[#FEFEFE] shadow-[0_1px_2px_rgba(0,0,0,0.12)]">Batal</a>
            <button type="submit" id="btnSimpan" class="cursor-pointer rounded-md bg-[#2277FF] px-4 py-2 text-lg font-medium leading-[22px] tracking-[-0.18px] text-[#FEFEFE] shadow-[0_1px_2px_rgba(0,0,0,0.12)] disabled:opacity-60">Simpan</button>
            <span id="err-umum" class="text-sm text-[#FF3F3F]" hidden>Gagal menyimpan. Periksa kembali isian di atas.</span>
        </div>
    </form>
</main>
@endsection

@push('scripts')
<script>
(function () {
    var form = document.getElementById('formTambahMahasiswa');
    var btnSimpan = document.getElementById('btnSimpan');
    var errUmum = document.getElementById('err-umum');
    var fields = ['nama', 'nim', 'angkatan', 'status', 'email', 'password'];
    var alertError = document.getElementById('alertError');
    var alertErrorJudul = document.getElementById('alertErrorJudul');
    var alertErrorList = document.getElementById('alertErrorList');
    var CSRF = @json(csrf_token());

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

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        resetErrors();

        btnSimpan.disabled = true;
        btnSimpan.textContent = 'Menyimpan...';

        var payload = {
            nama: document.getElementById('nama').value.trim(),
            nim: document.getElementById('nim').value.trim(),
            angkatan: document.getElementById('angkatan').value,
            status: document.getElementById('status').value,
            email: document.getElementById('email').value.trim(),
            password: document.getElementById('password').value
        };

        fetch('/api/v1/mahasiswa', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF
            },
            body: JSON.stringify(payload)
        })
        .then(function (res) {
            return res.json().then(function (body) {
                return { status: res.status, ok: res.ok, body: body };
            });
        })
        .then(function (res) {
            if (res.ok && res.body.data && res.body.data.id) {
                window.location.href = '/admin/mahasiswa/' + encodeURIComponent(res.body.data.id);
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
                alertErrorJudul.textContent = res.body.message || 'Terjadi kesalahan saat menyimpan data.';
                alertError.classList.remove('hidden');
                errUmum.textContent = res.body.message || 'Terjadi kesalahan saat menyimpan data.';
                errUmum.hidden = false;
            }

            btnSimpan.disabled = false;
            btnSimpan.textContent = 'Simpan';
        })
        .catch(function () {
            errUmum.textContent = 'Gagal terhubung ke server API.';
            errUmum.hidden = false;
            btnSimpan.disabled = false;
            btnSimpan.textContent = 'Simpan';
        });
    });

    // Ambil opsi Angkatan & Status dari API saat halaman dimuat
    fetch('/api/v1/mahasiswa/opsi', {
        headers: { 'Accept': 'application/json' }
    })
    .then(function (res) { return res.json(); })
    .then(function (json) {
        if (!json.success || !json.data) return;

        var selAngkatan = document.getElementById('angkatan');
        selAngkatan.innerHTML = json.data.tahun.map(function (y) {
            return '<option value="' + y + '">' + y + '</option>';
        }).join('');

        var selStatus = document.getElementById('status');
        selStatus.innerHTML = json.data.status.map(function (s) {
            return '<option value="' + s + '"' + (s === 'Aktif' ? ' selected' : '') + '>' + s + '</option>';
        }).join('');
    });
})();
</script>
@endpush