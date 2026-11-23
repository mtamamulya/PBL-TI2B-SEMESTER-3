@extends('layouts.app')

@section('title', $prestasiId ? 'Edit Prestasi' : 'Tambah Prestasi')

@php
    $daftarKategori = ['Teknologi', 'IoT', 'Jaringan', 'Olahraga', 'Seni'];
    $inputClass   = 'h-11 w-full rounded-lg border border-gray-200 bg-white px-3.5 text-[15px] text-gray-900 placeholder:text-gray-400';
    $selectClass  = 'h-11 w-full appearance-none rounded-lg border border-gray-200 bg-white pr-10 pl-3.5 text-[15px] text-gray-900';
    $labelClass   = 'text-[15px] font-medium';
    $hintClass    = 'text-[13px] text-gray-500';
    $sectionClass = 'flex flex-col gap-4.5 rounded-lg border border-gray-200 bg-white p-4.5';
    $numClass     = 'flex size-[26px] shrink-0 items-center justify-center rounded-full bg-brand text-[13px] font-semibold text-white';
    $radioKartu   = 'flex cursor-pointer items-center gap-3 rounded-lg border border-gray-200 bg-white px-4 py-3 text-[15px] has-[:checked]:border-brand has-[:checked]:bg-blue-50 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand/40';
    $chevron      = '<svg class="pointer-events-none absolute top-1/2 right-3.5 size-4 -translate-y-1/2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>';
    $wajib        = '<span class="text-red-500" aria-hidden="true">*</span>';
@endphp

@section('content')

    <div class="flex flex-col gap-1.5">
        <h1 class="text-3xl font-bold tracking-tight">{{ $prestasiId ? 'Edit' : 'Tambah' }} Prestasi</h1>
        <p class="text-2xl text-gray-400">Dashboard / Prestasi / {{ $prestasiId ? 'Edit' : 'Tambah' }}</p>
    </div>

    <form x-data="formPrestasi(@js($prestasiId))" x-init="muatAwal()" x-on:submit.prevent="simpan()"
          method="POST" action="#" enctype="multipart/form-data"
          class="flex flex-col gap-5 rounded-lg bg-white p-4.5 shadow-sm">
        @csrf
        <p x-show="error" x-text="error" role="alert" class="text-red-600"></p>
        <fieldset :disabled="!siap || menyimpan" class="contents">

        <p class="text-sm text-gray-500"><span class="text-red-500">*</span> Wajib diisi. Kolom bertanda opsional boleh dikosongkan.</p>

        <section class="{{ $sectionClass }}" aria-labelledby="h-sumber">
            <div class="flex items-center gap-2.5">
                <span class="{{ $numClass }}">1</span>
                <div>
                    <h2 id="h-sumber" class="text-[15px] font-semibold">Sumber kegiatan</h2>
                    <p class="{{ $hintClass }}">Prestasi boleh dicatat mandiri, atau dihubungkan dengan kegiatan yang pernah diajukan.</p>
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

            <div x-show="sumber === 'kegiatan'" x-cloak class="flex flex-col gap-2">
                <label for="kegiatan-lama" class="{{ $labelClass }}">Kegiatan sebelumnya</label>
                <div class="relative">
                    <svg class="pointer-events-none absolute top-1/2 left-3 size-5 -translate-y-1/2 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M16.5 16.5L21 21"/></svg>
                    <select id="kegiatan-lama" x-model="idKegiatan" @change="isiKegiatan()" :required="sumber === 'kegiatan'" class="{{ $inputClass }} pl-10">
                        <option value="">Pilih kegiatan yang diterima</option>
                        <template x-for="k in kegiatanLama" :key="k.id"><option :value="k.id" x-text="k.nama"></option></template>
                    </select>
                </div>
                <p class="{{ $hintClass }}">Nama kegiatan, penyelenggara, dan tanggal akan terisi otomatis dan masih bisa kamu ubah sesuai sertifikat.</p>
            </div>
        </section>

        <section class="{{ $sectionClass }}" aria-labelledby="h-info">
            <div class="flex items-center gap-2.5">
                <span class="{{ $numClass }}">2</span>
                <div>
                    <h2 id="h-info" class="text-[15px] font-semibold">Informasi prestasi</h2>
                    <p class="{{ $hintClass }}">Isi nama lomba dan capaian sesuai dokumen bukti.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4.5 md:grid-cols-2">
                <div class="flex flex-col gap-2 md:col-span-2">
                    <label for="nama" class="{{ $labelClass }}">Nama prestasi {!! $wajib !!}</label>
                    <input id="nama" name="nama_prestasi" type="text" required x-model="nama"
                           placeholder="Juara 1 Lomba Karya Tulis Ilmiah Nasional 2026" class="{{ $inputClass }}">
                </div>

                <div class="flex flex-col gap-2 md:col-span-2">
                    <label for="penyelenggara" class="{{ $labelClass }}">Penyelenggara {!! $wajib !!}</label>
                    <input id="penyelenggara" name="penyelenggara" type="text" required x-model="penyelenggara"
                           placeholder="Nama institusi penyelenggara" class="{{ $inputClass }}">
                </div>

                <div class="flex flex-col gap-2">
                    <label for="tingkat" class="{{ $labelClass }}">Tingkat prestasi {!! $wajib !!}</label>
                    <div class="relative">
                        <select id="tingkat" name="tingkat" x-model="tingkat" required class="{{ $selectClass }}">
                            <option value="">Pilih tingkat prestasi</option>
                            @foreach (['Kabupaten/kota', 'Provinsi', 'Nasional', 'Internasional'] as $t)
                                <option value="{{ strtolower($t) }}">{{ $t }}</option>
                            @endforeach
                        </select>
                        {!! $chevron !!}
                    </div>
                </div>
                <div class="flex flex-col gap-2">
                    <label for="jenis" class="{{ $labelClass }}">Jenis prestasi {!! $wajib !!}</label>
                    <div class="relative">
                        <select id="jenis" name="jenis" x-model="jenis" required class="{{ $selectClass }}">
                            <option value="">Pilih jenis prestasi</option>
                            <option value="akademik">Akademik</option>
                            <option value="non-akademik">Nonakademik</option>
                        </select>
                        {!! $chevron !!}
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    <label for="mulai" class="{{ $labelClass }}">Tanggal mulai {!! $wajib !!}</label>
                    <input id="mulai" name="tanggal_mulai" type="date" required x-model="mulai" class="{{ $inputClass }}">
                </div>
                <div class="flex flex-col gap-2">
                    <label for="selesai" class="{{ $labelClass }}">Tanggal selesai {!! $wajib !!}</label>
                    <input id="selesai" name="tanggal_selesai" type="date" required x-model="selesai" :min="mulai" class="{{ $inputClass }}">
                </div>

                <div class="flex flex-col gap-2">
                    <label for="capaian" class="{{ $labelClass }}">Capaian / peringkat {!! $wajib !!}</label>
                    <input id="capaian" name="capaian" type="text" required x-model="capaian"
                           placeholder="Contoh: Juara 1" class="{{ $inputClass }}">
                    <p class="{{ $hintClass }}">Contoh: Juara 1, Harapan 2, Finalis, Peserta terbaik.</p>
                </div>

                <div class="flex flex-col gap-2">
                    <label for="kategori" class="{{ $labelClass }}">Kategori / bidang <span class="font-normal text-gray-500">(opsional)</span></label>
                    <input id="kategori" name="kategori" type="text" list="daftar-kategori" x-model="kategori"
                           placeholder="Pilih atau tulis kategori" class="{{ $inputClass }}">
                    <datalist id="daftar-kategori">
                        @foreach ($daftarKategori as $k)
                            <option value="{{ $k }}"></option>
                        @endforeach
                    </datalist>
                    <p class="{{ $hintClass }}">Contoh: teknologi, IoT, jaringan, olahraga, seni.</p>
                </div>

                <div class="flex flex-col gap-2 md:col-span-2">
                    <span id="l-bentuk" class="{{ $labelClass }}">Bentuk partisipasi {!! $wajib !!}</span>
                    <div role="radiogroup" aria-labelledby="l-bentuk" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <label class="{{ $radioKartu }}">
                            <input type="radio" name="bentuk" value="Individu" x-model="bentuk" required class="size-4 accent-[#1f66e5]">
                            Individu
                        </label>
                        <label class="{{ $radioKartu }}">
                            <input type="radio" name="bentuk" value="Tim" x-model="bentuk" required class="size-4 accent-[#1f66e5]">
                            Tim
                        </label>
                    </div>
                </div>

                <div class="flex flex-col gap-2 md:col-span-2">
                    <label for="lokasi" class="{{ $labelClass }}">Lokasi {!! $wajib !!}</label>
                    <input id="lokasi" name="lokasi" type="text" required x-model="lokasi"
                           placeholder="Contoh: Semarang" class="{{ $inputClass }}">
                </div>

                <div class="flex flex-col gap-2 md:col-span-2">
                    <label for="deskripsi" class="{{ $labelClass }}">Deskripsi <span class="font-normal text-gray-500">(opsional)</span></label>
                    <textarea id="deskripsi" name="deskripsi" rows="4" maxlength="1000" x-model="deskripsi"
                              placeholder="Jelaskan pencapaian, kontribusi Anda, dan informasi tambahan yang relevan."
                              class="w-full rounded-lg border border-gray-200 bg-white px-3.5 py-3 text-[15px] text-gray-900 placeholder:text-gray-400"></textarea>
                    <p class="{{ $hintClass }}">Maksimal 1.000 karakter · <span x-text="deskripsi.length.toLocaleString('id-ID')"></span>/1.000</p>
                </div>
            </div>
        </section>

        <section x-show="bentuk === 'Tim' && sumber === 'mandiri'" x-cloak class="{{ $sectionClass }}" aria-labelledby="h-anggota">
            <div class="flex items-center gap-2.5">
                <span class="{{ $numClass }}">3</span>
                <div>
                    <h2 id="h-anggota" class="text-[15px] font-semibold">Anggota tim {!! $wajib !!}</h2>
                    <p class="{{ $hintClass }}">Kamu otomatis menjadi ketua. Tambahkan anggota lain di bawah ini.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_11rem_11rem_auto]">
                <div class="relative sm:col-span-2 lg:col-span-1">
                    <label for="cari" class="sr-only">Cari dosen atau mahasiswa</label>
                    <svg class="pointer-events-none absolute top-1/2 left-3 size-5 -translate-y-1/2 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M16.5 16.5L21 21"/></svg>
                    <select id="cari" x-model="pilihanAnggota" class="{{ $inputClass }} pl-10">
                        <option value="">Pilih anggota</option>
                        <template x-for="k in kandidat" :key="k.id_user"><option :value="k.id_user" x-text="k.nama + ' (' + k.status + ')'"></option></template>
                    </select>
                </div>

                <button type="button" x-on:click="tambah()"
                        class="flex h-11 cursor-pointer items-center justify-center gap-2 rounded-lg bg-brand px-5 text-base font-medium text-white hover:bg-blue-700 sm:col-span-2 lg:col-span-1">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                    Tambah
                </button>
            </div>

            <template x-if="errorAnggota">
                <p role="alert" class="text-sm text-red-600" x-text="errorAnggota"></p>
            </template>

            <div class="overflow-x-auto rounded-md border border-gray-300">
                <table class="w-full min-w-[520px] border-collapse text-sm">
                    <thead>
                        <tr class="bg-gray-100 text-left">
                            <th scope="col" class="w-[34%] px-2.5 py-2.5 font-medium">Nama</th>
                            <th scope="col" class="w-1/4 px-2.5 py-2.5 font-medium">Peran</th>
                            <th scope="col" class="w-1/4 px-2.5 py-2.5 font-medium">Status</th>
                            <th scope="col" class="px-2.5 py-2.5 font-medium">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(a, i) in anggota" :key="i">
                            <tr class="border-t border-gray-300">
                                <td class="px-2.5 py-2.5" x-text="a.nama"></td>
                                <td class="px-2.5 py-2.5" x-text="a.peran"></td>
                                <td class="px-2.5 py-2.5" x-text="a.status"></td>
                                <td class="px-2.5 py-1.5">
                                    <template x-if="!a.kunci">
                                        <button type="button" x-on:click="hapus(i)" :aria-label="`Hapus ${a.nama}`"
                                                class="flex size-8 cursor-pointer items-center justify-center rounded-lg bg-red-100 text-red-500 hover:bg-red-200">
                                            <svg class="size-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 7h14M10 7V5h4v2M7 7l1 12h8l1-12M10 11v5M14 11v5"/></svg>
                                        </button>
                                    </template>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="{{ $sectionClass }}" aria-labelledby="h-dokumen">
            <div class="flex items-center gap-2.5">
                <span class="{{ $numClass }}" x-text="bentuk === 'Tim' ? 4 : 3"></span>
                <h2 id="h-dokumen" class="text-[15px] font-semibold">Dokumen Pendukung</h2>
            </div>

            <template x-for="(d, i) in dokumen" :key="d.id">
                <div class="overflow-hidden rounded-lg border border-gray-200">
                    <div class="flex items-center justify-between gap-3 bg-[#02215c] px-3.5 py-3 text-white">
                        <h3 class="text-sm font-semibold" x-text="`Dokumen ${i + 1}`"></h3>
                        <template x-if="i > 0">
                            <button type="button" x-on:click="hapusDokumen(i)" :aria-label="`Hapus Dokumen ${i + 1}`"
                                    class="flex cursor-pointer items-center gap-1.5 rounded-md bg-red-500 px-3.5 py-1.5 text-sm font-medium text-white hover:bg-red-600">
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 7h14M10 7V5h4v2M7 7l1 12h8l1-12M10 11v5M14 11v5"/></svg>
                                Hapus
                            </button>
                        </template>
                    </div>

                    <div class="flex flex-col gap-4 p-3.5">
                        <div class="flex flex-col gap-2">
                            <label :for="`jenis-${d.id}`" class="{{ $labelClass }}">Jenis dokumen {!! $wajib !!}</label>
                            <div class="relative">
                                <select :id="`jenis-${d.id}`" required x-model="d.jenis" class="{{ $selectClass }}">
                                    <option value="">Pilih jenis dokumen</option>
                                    <option>Sertifikat</option>
                                    <option>Piagam</option>
                                    <option>Surat Tugas</option>
                                    <option>Dokumentasi</option>
                                    <option>Lainnya</option>
                                </select>
                                {!! $chevron !!}
                            </div>
                        </div>
                        <div class="flex flex-col gap-2">
                            <label :for="`tautan-${d.id}`" class="{{ $labelClass }}">Tautan dokumen {!! $wajib !!}</label>
                            <input :id="`tautan-${d.id}`" type="url" required x-model="d.tautan"
                                   placeholder="https://drive.google.com/..." class="{{ $inputClass }}">
                            <p class="{{ $hintClass }}">Tempel tautan Google Drive, OneDrive, atau cloud lain. Pastikan akses tautan dibuka untuk yang memiliki tautan.</p>
                        </div>
                    </div>
                </div>
            </template>

            <div>
                <button type="button" x-on:click="tambahDokumen()"
                        class="inline-flex h-10 cursor-pointer items-center gap-2 rounded-md border border-brand bg-white px-4 text-[15px] text-brand hover:bg-blue-50">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                    Tambah dokumen
                </button>
            </div>
        </section>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 pt-4">
            <p class="text-sm text-gray-500">Periksa kembali sebelum menyimpan.</p>
            <div class="flex flex-wrap items-center justify-end gap-3">
                <a href="{{ route('prestasi.index') }}"
                   class="flex h-11 items-center rounded-md bg-red-500 px-6 text-base text-white hover:bg-red-600">Batal</a>
                <button type="submit"
                        class="h-11 cursor-pointer rounded-md bg-brand px-6 text-base text-white hover:bg-blue-700">Simpan prestasi</button>
            </div>
        </div>
        </fieldset>
    </form>

@endsection

@push('scripts')
@include('dosen.prestasi._api')
@include('dosen.prestasi._form-script')
@endpush
