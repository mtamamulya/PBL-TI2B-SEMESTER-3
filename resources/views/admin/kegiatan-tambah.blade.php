{{--
    resources/views/admin/kegiatan-tambah.blade.php
    Form Tambah Kegiatan Admin dan Kaprodi, dengan kolom dari master kategori.
--}}
@extends(request()->routeIs('kaprodi.*') ? 'layouts.kaprodi' : 'layouts.admin')

@section('title', 'Tambah Kegiatan')

@php
    $card   = 'flex flex-col gap-4 rounded-lg border border-black/[0.06] bg-white p-4';
    $label  = 'text-base font-medium text-[#0A0A0A]';
    $field  = 'h-10 w-full rounded-lg border border-[#E8E8EC] bg-white px-3 text-base text-[#2E2E2E] outline-none placeholder:text-[#A8A8A8] focus:border-[#2277FF]';
    $select = $field.' appearance-none pr-10';
    $error  = 'text-sm text-[#FF3F3F]';
    $chev   = '<svg class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-[#1C2024]" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6l6 -6" /></svg>';
@endphp

@section('content')
<main class="flex flex-col gap-8 px-4 py-8 sm:px-6">
    <div class="flex flex-col gap-2">
        <h1 class="text-[32px] font-bold leading-[38px] tracking-[-0.2px]">Tambah Kegiatan</h1>
        <p class="text-xl font-medium leading-[30px] tracking-[-0.15px] text-[#A8A8A8] sm:text-2xl">Dashboard / Kegiatan / Tambah</p>
    </div>

    <form method="POST" action="{{ route(request()->routeIs('kaprodi.*') ? 'kaprodi.kegiatan.store' : 'admin.kegiatan.store') }}" enctype="multipart/form-data" class="flex flex-col gap-6 rounded-lg bg-white p-4 shadow-sm sm:p-6" novalidate>
        @csrf
        <input type="hidden" name="isian_dinamis" value="1">

        {{-- ============ 1. Informasi Kegiatan ============ --}}
        <section class="{{ $card }}">
            <div class="flex items-center gap-[11px]">
                <span class="flex size-6 items-center justify-center rounded-full bg-[#2277FF] text-sm font-semibold text-white">1</span>
                <h2 class="text-sm font-semibold text-[#0A0A0A]">Informasi Kegiatan</h2>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="flex min-w-0 flex-col gap-2">
                    <label for="id_kategori" class="{{ $label }}">Kategori</label>
                    <div class="relative">
                        <select id="id_kategori" name="id_kategori" class="{{ $select }}">
                            <option value="">Pilih kategori</option>
                            @foreach (collect($kategori)->groupBy(fn ($k) => $k['jenis'] ?? 'Lainnya') as $jenis => $items)
                                <optgroup label="{{ $jenis }}">
                                    @foreach ($items as $k)
                                        <option value="{{ $k['id_kategori'] }}" data-jenis="{{ $jenis }}" @selected((string) old('id_kategori') === (string) $k['id_kategori'])>{{ $k['nama_kategori'] }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        {!! $chev !!}
                    </div>
                    @error('id_kategori') <p class="{{ $error }}">{{ $message }}</p> @enderror
                    @if (empty($kategori)) <p class="{{ $error }}">Belum ada kategori kegiatan. Buat dulu di menu Kategori Kegiatan.</p> @endif
                </div>

                <div class="flex min-w-0 flex-col gap-2">
                    <label for="jenis_kegiatan_tampil" class="{{ $label }}">Jenis kegiatan <span class="text-[#A8A8A8]">(otomatis dari kategori)</span></label>
                    <input id="jenis_kegiatan_tampil" type="text" value="{{ old('jenis_kegiatan') }}" placeholder="Pilih kategori dulu" readonly tabindex="-1" class="{{ $field }} cursor-not-allowed bg-[#F5F5F7]">
                    @error('jenis_kegiatan') <p class="{{ $error }}">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex min-w-0 flex-col gap-2">
                <label for="nama_kegiatan" class="{{ $label }}">Nama kegiatan</label>
                <input id="nama_kegiatan" name="nama_kegiatan" type="text" value="{{ old('nama_kegiatan') }}" placeholder="Contoh: Seminar Nasional AI 2026" class="{{ $field }}">
                @error('nama_kegiatan') <p class="{{ $error }}">{{ $message }}</p> @enderror
            </div>

            <div id="bagianPemilik" class="hidden space-y-2">
                <label for="pemilik" class="{{ $label }}">Pemilik kegiatan <span class="{{ $error }}">*</span></label>
                <select id="pemilik" name="pemilik" class="{{ $field }}" required disabled></select>
                @error('pemilik') <p class="{{ $error }}" role="alert">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-col gap-2">
                <p id="statusIsianKategori" class="text-sm text-[#6E6E76]" role="status"></p>
                <div id="isianKategori" class="grid gap-4 md:grid-cols-2"></div>
                <div id="galatIsianKategori">
                    @error('detail') <p class="{{ $error }}" role="alert">{{ $message }}</p> @enderror
                    @error('api') <p class="{{ $error }}" role="alert">{{ $message }}</p> @enderror
                </div>
                <button type="button" id="muatUlangIsian" class="hidden self-start text-sm font-medium text-[#2277FF]">Coba lagi</button>
            </div>
        </section>

        {{-- Aksi --}}
        <div class="flex items-center justify-end gap-3">
            <a href="{{ route(request()->routeIs('kaprodi.*') ? 'kaprodi.kegiatan.index' : 'admin.kegiatan.index') }}" class="inline-flex h-10 items-center rounded-lg bg-[#FF3F3F] px-5 text-base font-medium text-white hover:bg-[#E63535]">Batal</a>
            <button type="submit" id="simpanKegiatan" disabled class="inline-flex h-10 items-center rounded-lg bg-[#2277FF] px-5 text-base font-medium text-white hover:bg-[#1A63D8] disabled:cursor-not-allowed disabled:opacity-50">Simpan</button>
        </div>
    </form>
    <template id="pilihPesertaKegiatan">
        <fieldset class="flex min-w-0 flex-col gap-3 rounded-lg border border-[#E8E8EC] p-3 md:col-span-2">
            <legend class="{{ $label }} px-1" data-judul></legend>
            <fieldset>
                <legend class="text-sm font-medium" data-judul-kelompok></legend>
                <div class="mt-2 flex flex-wrap gap-3" data-kelompok></div>
            </fieldset>
            <label class="flex flex-col gap-1 text-sm font-medium">Cari peserta satu per satu
                <input type="search" placeholder="Nama, NIM, atau NIP" class="{{ $field }}" data-cari>
            </label>
            <ul class="hidden max-h-60 overflow-y-auto rounded-lg border border-[#E8E8EC] bg-white" aria-label="Hasil pencarian peserta" data-kandidat></ul>
            <p class="hidden text-sm text-[#6E6E76]" role="status" data-status-cari></p>
            <ul class="space-y-1 text-sm" aria-label="Peserta yang ditambahkan satu per satu" data-peserta></ul>
            <p class="text-sm text-[#6E6E76]" aria-live="polite" data-jumlah></p>
            <div data-nilai></div>
            <p class="{{ $error }} hidden" role="alert" data-galat></p>
        </fieldset>
    </template>
</main>
@endsection

@push('scripts')
<script>
(function () {
    var KANDIDAT = {{ Illuminate\Support\Js::from($kandidat) }};
    var pemilikLama = {{ Illuminate\Support\Js::from(old('pemilik', '')) }};

    var $ = function (id) { return document.getElementById(id); };

    var FIELD = 'h-10 w-full rounded-lg border border-[#E8E8EC] bg-white px-3 text-base text-[#2E2E2E] outline-none placeholder:text-[#A8A8A8] focus:border-[#2277FF]';
    var LABEL = 'text-base font-medium text-[#0A0A0A]';

    var urlKategori = {{ Illuminate\Support\Js::from(route(request()->routeIs('kaprodi.*') ? 'kaprodi.kegiatan.create.kategori' : 'admin.kegiatan.create.kategori', ['id' => 0], false)) }};
    var kategoriLama = {{ Illuminate\Support\Js::from((string) old('id_kategori', '')) }};
    var jawabanLama = {{ Illuminate\Support\Js::from(old('detail', [])) }};
    var galatIsian = {{ Illuminate\Support\Js::from($errors->getBag('default')->messages()) }};
    var selKategori = $('id_kategori');
    var permintaanIsian = 0;

    function tampilPemilik(jenis, pulihkan) {
        var tipe = { 'Kegiatan Dosen': 'Dosen', 'Kegiatan Mahasiswa': 'Mahasiswa' }[jenis];
        var pilih = $('pemilik');
        $('bagianPemilik').classList.toggle('hidden', !tipe);
        pilih.disabled = !tipe;
        pilih.replaceChildren(new Option('Pilih pemilik kegiatan', ''));
        if (!tipe) return;

        KANDIDAT.filter(function (kandidat) { return kandidat.tipe === tipe; }).forEach(function (kandidat) {
            pilih.add(new Option(kandidat.nama, kandidat.key));
        });
        if (pulihkan && Array.from(pilih.options).some(function (opsi) { return opsi.value === pemilikLama; })) {
            pilih.value = pemilikLama;
        }
    }

    function kartuPeserta(field, lama, errors, jenis) {
        var kartu = $('pilihPesertaKegiatan').content.firstElementChild.cloneNode(true);
        var id = field.id_isian_kategori;
        var nama = 'detail.' + id;
        var elemen = function (key) { return kartu.querySelector('[data-' + key + ']'); };
        elemen('judul').textContent = field.input + (field.wajib ? ' *' : ' (opsional)');
        var pilihan = new Set(Object.values(lama[id] || {}).filter(function (nilai) { return typeof nilai === 'string'; }));
        var aktif = KANDIDAT.filter(function (kandidat) { return kandidat.aktif; });
        var kegiatanDosen = jenis === 'Kegiatan Dosen';
        var pesertaAktif = new Set(aktif.map(function (kandidat) { return kandidat.key; }));
        var jumlahKelompok = new Map();
        function kelompokPeserta(kandidat) {
            if (kegiatanDosen) return kandidat.tipe === 'Dosen' ? 'SemuaDosen' : null;
            return kandidat.angkatan ? 'Angkatan:' + kandidat.angkatan : null;
        }
        elemen('judul-kelompok').textContent = kegiatanDosen ? 'Pilih dosen sekaligus' : 'Pilih angkatan';
        aktif.forEach(function (kandidat) {
            var key = kelompokPeserta(kandidat);
            if (key) jumlahKelompok.set(key, (jumlahKelompok.get(key) || 0) + 1);
        });
        Array.from(jumlahKelompok).sort(function (a, b) { return b[0].localeCompare(a[0]); }).forEach(function (baris) {
            var label = document.createElement('label');
            label.className = 'inline-flex items-center gap-2 text-sm';
            var kotak = document.createElement('input');
            kotak.type = 'checkbox';
            kotak.checked = pilihan.has(baris[0]);
            kotak.addEventListener('change', function () {
                if (kotak.checked) pilihan.add(baris[0]); else pilihan.delete(baris[0]);
                renderPilihan();
            });
            var namaKelompok = kegiatanDosen ? 'Semua Dosen (' + baris[1] + ' aktif)' : baris[0].split(':')[1] + ' (' + baris[1] + ' mahasiswa)';
            label.append(kotak, namaKelompok);
            elemen('kelompok').append(label);
        });
        if (!jumlahKelompok.size) {
            elemen('kelompok').textContent = kegiatanDosen ? 'Belum ada dosen aktif.' : 'Belum ada angkatan dengan mahasiswa aktif di prodi ini.';
        }

        var cari = elemen('cari'), hasil = elemen('kandidat'), statusCari = elemen('status-cari');
        function tutupHasil() {
            hasil.replaceChildren();
            hasil.classList.add('hidden');
            statusCari.textContent = '';
            statusCari.classList.add('hidden');
        }
        function cariKandidat() {
            var query = cari.value.trim().toLowerCase();
            tutupHasil();
            if (!query) return;

            var cocok = aktif.filter(function (kandidat) {
                return !pilihan.has(kandidat.key) && [kandidat.nama, kandidat.identitas].some(function (nilai) {
                    return String(nilai ?? '').toLowerCase().includes(query);
                });
            }).slice(0, 30);
            if (!cocok.length) {
                statusCari.classList.remove('hidden');
                statusCari.textContent = 'Tidak ada hasil. Peserta yang sudah dipilih secara manual tidak ditampilkan.';
                return;
            }
            cocok.forEach(function (kandidat) {
                var baris = document.createElement('li');
                var tombol = document.createElement('button');
                tombol.type = 'button';
                tombol.className = 'block w-full px-3 py-2 text-left text-sm hover:bg-[#F1F6FF] focus:bg-[#F1F6FF]';
                tombol.textContent = kandidat.nama + ' · ' + kandidat.tipe + (kandidat.identitas ? ' · ' + kandidat.identitas : '');
                tombol.addEventListener('click', function () {
                    pilihan.add(kandidat.key);
                    cari.value = '';
                    renderPilihan();
                    cari.focus();
                });
                baris.append(tombol);
                hasil.append(baris);
            });
            hasil.classList.remove('hidden');
        }
        function renderPilihan() {
            var manual = elemen('peserta'), nilai = elemen('nilai');
            manual.replaceChildren();
            nilai.replaceChildren();
            var jumlah = 0;
            aktif.forEach(function (kandidat) {
                var dipilihManual = pilihan.has(kandidat.key);
                var lewatKelompok = pilihan.has(kelompokPeserta(kandidat));
                if (dipilihManual || lewatKelompok) jumlah++;
                if (!dipilihManual) return;
                var item = document.createElement('li');
                var hapus = document.createElement('button');
                hapus.type = 'button';
                hapus.className = 'ml-2 text-[#E5484D]';
                hapus.textContent = 'Hapus pilihan manual';
                hapus.setAttribute('aria-label', 'Hapus pilihan manual ' + kandidat.nama);
                hapus.addEventListener('click', function () { pilihan.delete(kandidat.key); renderPilihan(); });
                item.append(kandidat.nama + ' · ' + kandidat.tipe + (lewatKelompok ? ' (juga dipilih lewat kelompok)' : ''), hapus);
                manual.append(item);
            });
            Array.from(pilihan).filter(function (key) {
                return jumlahKelompok.has(key) || pesertaAktif.has(key);
            }).forEach(function (key, i) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'detail[' + id + '][' + i + ']';
                input.value = key;
                nilai.append(input);
            });
            elemen('jumlah').textContent = jumlah + ' peserta unik. Batalkan pilihan ' + (kegiatanDosen ? 'Semua Dosen' : 'angkatan') + ' untuk mengeluarkan peserta yang ditambahkan lewat pilihan itu.';
            cariKandidat();
        }
        cari.addEventListener('input', cariKandidat);
        cari.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') tutupHasil();
            if (event.key !== 'ArrowDown' && event.key !== 'Enter') return;
            event.preventDefault();
            if (hasil.classList.contains('hidden')) cariKandidat();
            var awal = hasil.querySelector('button');
            if (!awal) return;
            if (event.key === 'Enter' && hasil.children.length === 1) awal.click();
            else awal.focus();
        });
        hasil.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                tutupHasil();
                cari.focus();
                return;
            }
            if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') return;
            event.preventDefault();
            var baris = event.target.closest('li');
            var berikutnya = event.key === 'ArrowDown' ? baris?.nextElementSibling : baris?.previousElementSibling;
            if (berikutnya) berikutnya.querySelector('button').focus();
            else if (event.key === 'ArrowUp') cari.focus();
        });
        var galat = Object.keys(errors).find(function (key) { return key === nama || key.startsWith(nama + '.'); });
        if (galat) {
            elemen('galat').textContent = errors[galat][0];
            elemen('galat').classList.remove('hidden');
        }
        renderPilihan();
        return kartu;
    }

    function kartuIsian(field, lama, errors) {
        var id = field.id_isian_kategori;
        var nama = 'detail.' + id;
        var banyak = Number(field.jumlah_isian) === 0;
        var file = field.jenis_input === 'berkas';
        var barisLama = lama[id] && typeof lama[id] === 'object' ? lama[id] : {};
        var indeks = Object.keys(barisLama).filter(function (key) { return /^\d+$/.test(key); });
        if (!banyak) indeks = indeks.slice(0, 1);
        var berikutnya = 0;
        var grup = document.createElement('fieldset');
        grup.className = 'flex min-w-0 flex-col gap-2';
        var judul = document.createElement('legend');
        judul.className = LABEL;
        judul.textContent = field.input + (field.wajib ? ' *' : ' (opsional)');
        grup.append(judul);
        var baris = document.createElement('div');
        baris.className = 'flex flex-col gap-2';
        grup.append(baris);

        function pesanIsian(wadah, key) {
            if (!errors[key]) return;
            var pesan = document.createElement('p');
            pesan.className = 'text-sm text-[#FF3F3F]';
            pesan.textContent = errors[key][0];
            wadah.append(pesan);
        }
        function tambahBaris(i) {
            berikutnya = Math.max(berikutnya, Number(i) + 1);
            var wadah = document.createElement('div');
            wadah.className = 'flex flex-col gap-1';
            var tag = Array.isArray(field.pilihan) ? 'select' : field.jenis_input === 'paragraf' ? 'textarea' : 'input';
            var kontrol = document.createElement(tag);
            var nilai = String(barisLama[i] ?? '');
            kontrol.name = 'detail[' + id + '][' + i + ']';
            kontrol.required = field.wajib && !banyak;
            kontrol.setAttribute('aria-label', field.input + (banyak ? ' ' + (Number(i) + 1) : ''));
            kontrol.className = tag === 'textarea' ? FIELD + ' min-h-24 py-2' : FIELD;
            if (tag === 'select') {
                kontrol.add(new Option('Pilih ' + field.input, ''));
                field.pilihan.forEach(function (opsi) { kontrol.add(new Option(opsi.label, opsi.nilai)); });
            } else if (tag === 'input') {
                kontrol.type = { tanggal: 'date', angka: 'number', rupiah: 'number', berkas: 'file' }[field.jenis_input] || 'text';
                if (kontrol.type === 'number') kontrol.step = 'any';
                if (file) kontrol.accept = '.pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.txt';
            }
            if (!file) kontrol.value = nilai;
            wadah.append(kontrol);
            if (banyak && Number(i) !== 0) {
                var hapus = document.createElement('button');
                hapus.type = 'button';
                hapus.className = 'self-start text-sm text-[#E5484D]';
                hapus.textContent = 'Hapus isian';
                hapus.addEventListener('click', function () { wadah.remove(); });
                wadah.append(hapus);
            }
            pesanIsian(wadah, nama + '.' + i);
            baris.append(wadah);
        }
        (indeks.length ? indeks : [0]).forEach(tambahBaris);

        if (banyak) {
            var tambah = document.createElement('button');
            tambah.type = 'button';
            tambah.className = 'self-start text-sm font-medium text-[#2277FF]';
            tambah.textContent = file ? '+ Tambah berkas' : '+ Tambah isian';
            tambah.addEventListener('click', function () { tambahBaris(berikutnya); });
            grup.append(tambah);
        }
        pesanIsian(grup, nama);
        return grup;
    }

    async function muatIsian(pulihkan) {
        var nomor = ++permintaanIsian;
        var id = selKategori.value;
        var jenis = selKategori.selectedOptions[0]?.dataset.jenis || '';
        var pulihkanKategori = pulihkan && id === kategoriLama;
        var wadah = $('isianKategori'), status = $('statusIsianKategori');
        $('jenis_kegiatan_tampil').value = jenis;
        tampilPemilik(jenis, pulihkanKategori);
        $('simpanKegiatan').disabled = true;
        $('muatUlangIsian').classList.add('hidden');
        if (!pulihkan) $('galatIsianKategori').hidden = true;
        wadah.replaceChildren();
        status.textContent = id ? 'Memuat isian kategori…' : 'Pilih kategori untuk melihat isiannya.';
        if (!id) return;

        try {
            var respons = await fetch(urlKategori.replace(/0$/, id), { headers: { Accept: 'application/json' }, cache: 'no-store' });
            if (!respons.ok) throw new Error('Isian kategori gagal dimuat.');
            var data = await respons.json();
            if (nomor !== permintaanIsian) return;
            data.isian.forEach(function (field) {
                var render = field.jenis_input === 'anggota_kegiatan' ? kartuPeserta : kartuIsian;
                wadah.append(render(field, pulihkanKategori ? jawabanLama : {}, pulihkanKategori ? galatIsian : {}, jenis));
            });
            status.textContent = data.isian.length ? '' : 'Kategori ini belum memiliki isian tambahan.';
            $('simpanKegiatan').disabled = false;
        } catch (_) {
            if (nomor !== permintaanIsian) return;
            status.textContent = 'Isian kategori gagal dimuat. Silakan coba lagi.';
            $('muatUlangIsian').classList.remove('hidden');
        }
    }

    selKategori.addEventListener('change', function () {
        kategoriLama = '';
        muatIsian(false);
    });
    $('muatUlangIsian').addEventListener('click', function () { muatIsian(selKategori.value === kategoriLama); });
    muatIsian(true);

})();
</script>
@endpush
