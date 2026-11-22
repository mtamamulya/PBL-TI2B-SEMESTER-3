{{--
    resources/views/backendkel1/import/mata-kuliah.blade.php
    Impor mata kuliah dari Excel (admin dan kaprodi). Layout mengikuti peran: layouts.admin / layouts.kaprodi.
    Satu halaman: unduh template, unggah, lalu pratinjau (belum menyimpan) dan konfirmasi.
--}}
@extends($layout)

@section('title', 'Impor Mata Kuliah')

@php
    $rute = fn (string $akhir = '') => route($peran.'.kurikulum.mata-kuliah.import'.$akhir);
    $label = 'text-base text-[#111]';
    $select = 'h-10 w-full appearance-none rounded-md border border-[#E4E4E7] bg-white pl-4 pr-10 text-base text-[#2E2E2E] outline-none focus:border-[#2277FF]';
    $card = 'flex flex-col gap-4 rounded-md border border-[#EDEDED] bg-white p-4';
    $th = 'border border-[#00002F]/15 px-3 py-2 text-left font-semibold';
    $td = 'border border-[#00002F]/15 px-3 py-2 align-top';
    $hasil = $preview['hasil'] ?? null;
@endphp

@section('content')
<main class="flex flex-col gap-6 px-4 py-8 sm:px-6">
    <div class="flex flex-col gap-2">
        <h1 class="text-[32px] font-bold leading-[38px] tracking-[-0.2px]">Impor Mata Kuliah dari Excel</h1>
        <x-admin.breadcrumb :items="[['Dashboard', $peran.'.dashboard'], ['Kurikulum'], ['Mata Kuliah', $peran.'.kurikulum.mata-kuliah'], ['Impor Excel']]" />
    </div>

    @if (session('galat'))
        <div class="rounded-md border border-[#F5C2C2] bg-[#FDECEC] px-4 py-3 text-sm text-[#B42318]" role="alert">{{ session('galat') }}</div>
    @endif
    @if ($errors->any())
        <div class="rounded-md border border-[#F5C2C2] bg-[#FDECEC] px-4 py-3 text-sm text-[#B42318]" role="alert">
            @foreach ($errors->all() as $pesan)
                <p>{{ $pesan }}</p>
            @endforeach
        </div>
    @endif

    <div class="flex flex-col gap-5 rounded-lg bg-white p-4 pb-6 shadow-[0_1px_4px_rgba(0,0,0,0.06)] sm:p-6">

        {{-- 1. Template --}}
        <section class="{{ $card }}">
            <div class="flex items-center gap-2.5">
                <span class="flex size-6 items-center justify-center rounded-full bg-[#2277FF] text-xs font-semibold text-white">1</span>
                <h2 class="text-sm font-semibold text-[#0A0A0A]">Unduh template</h2>
            </div>
            <p class="text-sm text-[#6B6B72]">
                Isi data mulai baris 2 pada sheet "Data". Kolom: Semester, Kode MK, Nama MK, SKS Teori, SKS Praktik, Jam Teori, Jam Praktik.
                Aturan dan contoh ada di sheet "Petunjuk". Satu berkas untuk satu kurikulum, maksimal 1.000 baris.
            </p>
            <div>
                <a href="{{ $rute('.template') }}" class="inline-flex items-center gap-2 rounded-md border border-[#2277FF] px-4 py-2 text-base font-medium text-[#2277FF]">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2" /><path d="M7 11l5 5l5 -5" /><path d="M12 4l0 12" /></svg>
                    Unduh template.xlsx
                </a>
            </div>
        </section>

        {{-- 2. Unggah --}}
        <section class="{{ $card }}">
            <div class="flex items-center gap-2.5">
                <span class="flex size-6 items-center justify-center rounded-full bg-[#2277FF] text-xs font-semibold text-white">2</span>
                <h2 class="text-sm font-semibold text-[#0A0A0A]">Unggah berkas</h2>
            </div>
            <form method="POST" action="{{ $rute('.pratinjau') }}" enctype="multipart/form-data" class="grid gap-4 md:grid-cols-2">
                @csrf
                <div class="flex min-w-0 flex-col gap-2">
                    <label for="kurikulum" class="{{ $label }}">Kurikulum tujuan</label>
                    <div class="relative">
                        <select id="kurikulum" name="kurikulum" required class="{{ $select }}">
                            <option value="">Pilih kurikulum</option>
                            @foreach ($kurikulumList as $k)
                                <option value="{{ $k['id'] }}" @selected((string) $k['id'] === (string) $kurikulumId)>{{ $k['nama'] }}</option>
                            @endforeach
                        </select>
                        <svg class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 text-[#111]" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6l6 -6" /></svg>
                    </div>
                </div>
                <div class="flex min-w-0 flex-col gap-2">
                    <label for="berkas" class="{{ $label }}">Berkas Excel (.xlsx, maks. 5 MB)</label>
                    <input id="berkas" name="berkas" type="file" required accept=".xlsx" class="block w-full rounded-md border border-[#E4E4E7] bg-white text-sm text-[#2E2E2E] file:mr-4 file:border-0 file:bg-[#CDDEF9] file:px-4 file:py-2.5 file:text-sm file:font-medium file:text-[#2277FF]">
                </div>
                <div class="md:col-span-2">
                    <button type="submit" class="rounded-sm bg-[#2277FF] px-4 py-2 text-base font-medium text-white">Periksa berkas (pratinjau)</button>
                    <span class="ml-3 text-sm text-[#6B6B72]">Belum ada data yang disimpan pada tahap ini.</span>
                </div>
            </form>
        </section>

        {{-- 3. Pratinjau --}}
        @if ($hasil)
            @php
                $jumlahValid = count($hasil['valid']);
                $jumlahLewat = count($hasil['dilewati']);
                $jumlahSalah = count($hasil['salah']);
            @endphp
            <section class="{{ $card }}">
                <div class="flex items-center gap-2.5">
                    <span class="flex size-6 items-center justify-center rounded-full bg-[#2277FF] text-xs font-semibold text-white">3</span>
                    <h2 class="text-sm font-semibold text-[#0A0A0A]">Pratinjau dan konfirmasi</h2>
                </div>
                <p class="text-sm text-[#6B6B72]">
                    Berkas <span class="font-medium text-[#0A0A0A]">{{ $preview['nama_berkas'] }}</span>
                    untuk kurikulum <span class="font-medium text-[#0A0A0A]">{{ $preview['kurikulum_nama'] }}</span>.
                </p>

                <div class="grid gap-3 sm:grid-cols-4">
                    <div class="rounded-md border border-[#E4E4E7] bg-[#F9F9FB] p-3"><p class="text-2xl font-bold">{{ $hasil['total'] }}</p><p class="text-sm text-[#6B6B72]">Baris terbaca</p></div>
                    <div class="rounded-md border border-[#D6EDB5] bg-[#E6F3D3] p-3"><p class="text-2xl font-bold text-[#4C7D08]">{{ $jumlahValid }}</p><p class="text-sm text-[#4C7D08]">Akan diimpor</p></div>
                    <div class="rounded-md border border-[#F6E3A8] bg-[#FFF6DB] p-3"><p class="text-2xl font-bold text-[#8A6100]">{{ $jumlahLewat }}</p><p class="text-sm text-[#8A6100]">Dilewati (sudah ada)</p></div>
                    <div class="rounded-md border border-[#F5C2C2] bg-[#FDECEC] p-3"><p class="text-2xl font-bold text-[#B42318]">{{ $jumlahSalah }}</p><p class="text-sm text-[#B42318]">Salah (tidak diimpor)</p></div>
                </div>

                @if ($jumlahSalah)
                    <div>
                        <h3 class="mb-2 text-sm font-semibold text-[#B42318]">Baris yang salah</h3>
                        <div class="max-h-80 overflow-auto rounded-sm border border-[#00002F]/15">
                            <table class="w-full border-collapse text-sm">
                                <thead class="bg-[#000055]/[0.03]"><tr><th class="{{ $th }} w-20">Baris</th><th class="{{ $th }} w-40">Kode MK</th><th class="{{ $th }}">Masalah</th></tr></thead>
                                <tbody>
                                    @foreach ($hasil['salah'] as $s)
                                        <tr><td class="{{ $td }}">{{ $s['baris'] }}</td><td class="{{ $td }}">{{ $s['kode'] ?: '-' }}</td><td class="{{ $td }}">{{ implode(' ', $s['pesan']) }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                @if ($jumlahLewat)
                    <div>
                        <h3 class="mb-2 text-sm font-semibold text-[#8A6100]">Dilewati</h3>
                        <div class="max-h-60 overflow-auto rounded-sm border border-[#00002F]/15">
                            <table class="w-full border-collapse text-sm">
                                <thead class="bg-[#000055]/[0.03]"><tr><th class="{{ $th }} w-20">Baris</th><th class="{{ $th }} w-40">Kode MK</th><th class="{{ $th }}">Alasan</th></tr></thead>
                                <tbody>
                                    @foreach ($hasil['dilewati'] as $d)
                                        <tr><td class="{{ $td }}">{{ $d['baris'] }}</td><td class="{{ $td }}">{{ $d['kode'] }}</td><td class="{{ $td }}">{{ $d['alasan'] }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                @if ($jumlahValid)
                    <div>
                        <h3 class="mb-2 text-sm font-semibold text-[#4C7D08]">Akan diimpor</h3>
                        <div class="max-h-80 overflow-auto rounded-sm border border-[#00002F]/15">
                            <table class="w-full border-collapse text-sm">
                                <thead class="bg-[#000055]/[0.03]">
                                    <tr>
                                        <th class="{{ $th }}">Smt</th><th class="{{ $th }}">Kode MK</th><th class="{{ $th }}">Nama MK</th>
                                        <th class="{{ $th }}">SKS T</th><th class="{{ $th }}">SKS P</th><th class="{{ $th }}">Jam T</th><th class="{{ $th }}">Jam P</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($hasil['valid'] as $v)
                                        <tr>
                                            <td class="{{ $td }}">{{ $v['semester'] }}</td><td class="{{ $td }}">{{ $v['kode'] }}</td><td class="{{ $td }}">{{ $v['nama'] }}</td>
                                            <td class="{{ $td }}">{{ $v['sks_teori'] }}</td><td class="{{ $td }}">{{ $v['sks_praktek'] }}</td><td class="{{ $td }}">{{ $v['jam_teori'] }}</td><td class="{{ $td }}">{{ $v['jam_praktek'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                <div class="flex flex-wrap items-center gap-3">
                    @if ($jumlahValid)
                        <form method="POST" action="{{ $rute('.simpan') }}">
                            @csrf
                            <button type="submit" class="rounded-sm bg-[#2277FF] px-4 py-2 text-base font-medium text-white">Impor {{ $jumlahValid }} mata kuliah</button>
                        </form>
                    @else
                        <p class="text-sm text-[#6B6B72]">Tidak ada baris yang bisa diimpor. Perbaiki berkas lalu unggah ulang.</p>
                    @endif
                    <form method="POST" action="{{ $rute('.batal') }}">
                        @csrf
                        <button type="submit" class="rounded-sm bg-[#FF3B3B] px-4 py-2 text-base font-medium text-white">Batal</button>
                    </form>
                </div>
            </section>
        @endif
    </div>
</main>
@endsection
