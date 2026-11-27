@extends('layouts.app')

@section('title', 'Profil')

@section('content')

@php
    $badge = 'rounded-full border border-blue-200 bg-blue-100 px-2.5 py-0.5 text-[13px] text-brand';
@endphp

<div x-data="profilDosenPage(@js(csrf_token()))"
     x-init="muatData()"
     x-on:profil-foto-diperbarui.window="sinkronkanFotoDariEvent($event.detail)"
     class="flex flex-col gap-6">

    {{-- Judul halaman + tombol --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex flex-col gap-1.5">
            <h1 class="text-3xl font-bold tracking-tight">Profil</h1>
            <p class="text-2xl text-gray-400">Dashboard / Profil</p>
        </div>
        <div class="mt-2 flex items-center gap-3">
            <a href="{{ route('dosen.dashboard') }}"
               class="flex h-11 items-center gap-2 rounded-md bg-blue-100 px-4 text-base font-medium text-brand hover:bg-blue-200">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg>
                Kembali
            </a>
            <button type="button" x-on:click="bukaModalSandi()"
                    class="flex h-11 cursor-pointer items-center gap-2 rounded-md bg-brand px-4 text-base font-medium text-white hover:bg-blue-700">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 20h4l11-11a2.8 2.8 0 0 0-4-4L4 16zM13.5 6.5l4 4"/></svg>
                Ubah Kata Sandi
            </button>
        </div>
    </div>

    {{-- Notifikasi halaman --}}
    <template x-if="pesanHalaman">
        <div class="rounded-lg px-4 py-3 text-sm font-medium"
             :class="tipePesanHalaman === 'sukses'
                ? 'border border-green-200 bg-green-50 text-green-800'
                : 'border border-red-200 bg-red-50 text-red-800'"
             x-text="pesanHalaman"></div>
    </template>

    {{-- Kartu identitas --}}
    <section class="flex flex-col gap-8 rounded-lg bg-white p-5 shadow-sm">
        <div class="flex items-center gap-5">
            <div class="relative shrink-0">
                <template x-if="urlFotoTampil">
                    <img :src="urlFotoTampil"
                         :alt="profil.nama ? 'Foto ' + profil.nama : 'Foto Profil'"
                         class="size-[88px] rounded-full object-cover">
                </template>
                <template x-if="!urlFotoTampil">
                    <span class="flex size-[88px] items-center justify-center rounded-full bg-red-600 text-3xl font-semibold text-white"
                          aria-hidden="true"
                          x-text="inisialNama(profil.nama)"></span>
                </template>

                <input type="file"
                       x-ref="inputFoto"
                       accept="image/jpeg,image/png,image/webp"
                       x-on:change="pilihFileFoto($event)"
                       class="hidden">

                <button type="button"
                        aria-label="Ganti foto profil"
                        x-on:click="$refs.inputFoto.click()"
                        class="absolute right-0 bottom-0 flex size-9 cursor-pointer items-center justify-center rounded-full border-2 border-white bg-blue-200 text-brand hover:bg-blue-300">
                    <svg class="size-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 20h4l11-11a2.8 2.8 0 0 0-4-4L4 16zM13.5 6.5l4 4"/></svg>
                </button>
            </div>

            <div class="flex min-w-0 flex-col gap-1">
                <div class="flex flex-wrap items-center gap-4">
                    <h2 class="text-2xl font-semibold" x-text="sedangMemuat ? 'Memuat...' : (profil.nama || '-')"></h2>
                    <span class="rounded-full border border-green-200 bg-green-100 px-2.5 py-0.5 text-[13px] text-green-700">Aktif</span>
                </div>
                <p class="text-xl text-gray-400" x-text="profil.nip ? 'NIP ' + profil.nip : '-'"></p>
            </div>
        </div>

        <dl class="grid grid-cols-1 gap-5 rounded-lg bg-blue-50 px-5 py-5 sm:grid-cols-2 lg:grid-cols-[1.8fr_0.8fr_1.4fr_0.8fr]">
            <div class="flex min-w-0 flex-col gap-1.5">
                <dt class="text-lg text-gray-400">Email akun</dt>
                <dd class="text-xl font-medium break-words" x-text="profil.email || '-'"></dd>
            </div>
            <div class="flex min-w-0 flex-col gap-1.5">
                <dt class="text-lg text-gray-400">Role</dt>
                <dd class="text-xl font-medium break-words">Dosen</dd>
            </div>
            <div class="flex min-w-0 flex-col gap-1.5">
                <dt class="text-lg text-gray-400">Pangkat / golongan saat ini</dt>
                <dd class="text-xl font-medium break-words"
                    x-text="profil.pangkat_terakhir ? `${profil.pangkat_terakhir.pangkat || '-'}, ${profil.pangkat_terakhir.golongan || '-'}` : 'Belum ada data'"></dd>
            </div>
            <div class="flex min-w-0 flex-col gap-1.5">
                <dt class="text-lg text-gray-400">Akun login</dt>
                <dd class="text-xl font-medium break-words">Aktif</dd>
            </div>
        </dl>
    </section>

    {{-- Riwayat pangkat dan golongan --}}
    <section class="flex flex-col gap-8 rounded-lg bg-white p-5 shadow-sm">
        <h2 class="text-2xl font-medium">Riwayat Pangkat dan Golongan</h2>

        <div class="overflow-x-auto rounded-md border border-gray-300">
            <table class="w-full min-w-[720px] table-fixed border-collapse text-sm">
                <thead>
                    <tr class="bg-gray-100 text-left">
                        <th scope="col" class="w-1/4 border-b border-gray-300 px-2.5 py-3 font-medium">Pangkat</th>
                        <th scope="col" class="w-1/4 border-b border-gray-300 px-2.5 py-3 font-medium">Golongan</th>
                        <th scope="col" class="w-1/4 border-b border-gray-300 px-2.5 py-3 font-medium">Nomor SK</th>
                        <th scope="col" class="w-1/4 border-b border-gray-300 px-2.5 py-3 font-medium">Tanggal SK</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="!sedangMemuat && riwayatPangkat.length === 0">
                        <tr>
                            <td colspan="4" class="px-2.5 py-4 text-center text-gray-500">Belum ada riwayat pangkat dan golongan.</td>
                        </tr>
                    </template>

                    <template x-for="(r, idx) in riwayatPangkat" :key="r.id_histori_pangkat_golongan ?? idx">
                        <tr class="border-b border-gray-300 last:border-b-0">
                            <td class="px-2.5 py-3">
                                <span class="flex flex-wrap items-center gap-2">
                                    <span x-text="r.pangkat || '-'"></span>
                                    <template x-if="idx === 0">
                                        <span class="{{ $badge }} py-px text-xs">Saat ini</span>
                                    </template>
                                </span>
                            </td>
                            <td class="px-2.5 py-3" x-text="r.golongan || '-'"></td>
                            <td class="px-2.5 py-3" x-text="r.nomor_sk || '-'"></td>
                            <td class="px-2.5 py-3" x-text="r.tanggal_sk || '-'"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </section>

    {{-- MODAL CROP & ZOOM FOTO PROFIL --}}
    <div x-show="modalCropTerbuka"
         x-cloak
         x-on:keydown.escape.window="tutupModalCrop()"
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
         role="dialog"
         aria-modal="true"
         aria-labelledby="judul-modal-crop">
        <div class="flex w-full max-w-md flex-col gap-4 rounded-xl bg-white p-6 shadow-xl" x-on:click.stop>
            <div class="flex items-start justify-between gap-4">
                <div class="flex flex-col gap-1">
                    <h2 id="judul-modal-crop" class="text-2xl font-medium">Ubah foto profil</h2>
                    <p class="text-sm text-gray-400">Geser gambar untuk mengatur posisi, lalu zoom in / zoom out.</p>
                </div>
                <button type="button"
                        x-on:click="tutupModalCrop()"
                        aria-label="Tutup"
                        class="flex size-9 shrink-0 cursor-pointer items-center justify-center rounded-lg hover:bg-gray-100">
                    <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M5 5l14 14M19 5L5 19"/></svg>
                </button>
            </div>

            <template x-if="pesanErrorCrop">
                <p role="alert" class="rounded-lg border border-red-200 bg-red-50 px-3.5 py-2 text-sm text-red-600" x-text="pesanErrorCrop"></p>
            </template>

            <div class="flex justify-center py-1">
                <canvas x-ref="canvasCrop"
                        width="240"
                        height="240"
                        x-on:mousedown="mulaiGeser($event)"
                        x-on:mousemove="geserGambar($event)"
                        x-on:mouseup="selesaiGeser()"
                        x-on:mouseleave="selesaiGeser()"
                        x-on:touchstart.prevent="mulaiGeser($event)"
                        x-on:touchmove.prevent="geserGambar($event)"
                        x-on:touchend="selesaiGeser()"
                        x-on:wheel.prevent="zoomDenganWheel($event)"
                        class="cursor-grab rounded-full border-2 border-brand bg-gray-900 active:cursor-grabbing"></canvas>
            </div>

            <div class="flex items-center justify-center gap-3">
                <button type="button"
                        x-on:click="ubahZoom(-0.15)"
                        class="cursor-pointer rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Zoom Out (-)
                </button>
                <span class="min-w-14 text-center text-sm font-semibold text-gray-700"
                      x-text="Math.round(skalaZoom * 100) + '%'"></span>
                <button type="button"
                        x-on:click="ubahZoom(0.15)"
                        class="cursor-pointer rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Zoom In (+)
                </button>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2">
                <button type="button"
                        x-on:click="tutupModalCrop()"
                        :disabled="sedangMenyimpanFoto"
                        class="h-11 cursor-pointer rounded-lg border border-red-500 bg-red-500 px-5 text-sm font-medium text-white hover:bg-red-600 disabled:opacity-50">
                    Batal
                </button>
                <button type="button"
                        x-on:click="simpanHasilCrop()"
                        :disabled="sedangMenyimpanFoto"
                        class="h-11 cursor-pointer rounded-lg bg-brand px-5 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                    <span x-text="sedangMenyimpanFoto ? 'Menyimpan...' : 'Simpan foto'"></span>
                </button>
            </div>
        </div>
    </div>

    {{-- MODAL UBAH KATA SANDI --}}
    <div x-show="modalSandiTerbuka"
         x-cloak
         x-on:keydown.escape.window="tutupModalSandi()"
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">

        <form x-ref="formSandi"
              role="dialog"
              aria-modal="true"
              aria-label="Ubah Kata Sandi"
              method="POST"
              autocomplete="off"
              x-on:submit.prevent="langkahSandi === 1 ? (bisaKirimOtp && kirimOtp()) : (bisaSimpanSandi && simpanSandi())"
              class="grid max-h-full w-full max-w-4xl overflow-x-hidden overflow-y-auto rounded-2xl bg-white shadow-2xl md:grid-cols-[minmax(0,5fr)_minmax(0,7fr)]">

            {{-- KIRI --}}
            <div class="flex flex-col justify-between gap-10 bg-blue-50 p-6 sm:p-8">
                <div class="flex flex-col gap-8">
                    <div class="flex flex-col gap-2">
                        <p class="text-sm font-medium tracking-[0.2em] text-[#02215c] uppercase">Keamanan akun</p>
                        <h2 class="text-3xl font-medium">Ubah Kata Sandi</h2>
                    </div>

                    <ol class="flex flex-col">
                        <li class="flex gap-4">
                            <div class="flex flex-col items-center">
                                <span class="flex size-10 shrink-0 items-center justify-center rounded-full border-2 border-brand text-sm"
                                      :class="langkahSandi > 1 ? 'bg-brand text-white' : 'bg-white text-brand'">
                                    <span x-show="langkahSandi === 1">01</span>
                                    <svg x-show="langkahSandi > 1" x-cloak class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
                                </span>
                                <span class="my-1 h-8 w-0.5 bg-brand"></span>
                            </div>
                            <div class="flex flex-col gap-1 pt-1.5">
                                <span class="text-lg font-medium">Kata sandi baru</span>
                                <span class="text-base">Buat kata sandi yang kuat</span>
                            </div>
                        </li>
                        <li class="flex gap-4">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-full border-2 bg-white text-sm"
                                  :class="langkahSandi === 2 ? 'border-brand text-brand' : 'border-gray-300 text-gray-500'">02</span>
                            <div class="flex flex-col gap-1 pt-1.5" :class="langkahSandi === 2 ? '' : 'text-gray-400'">
                                <span class="text-lg font-medium">Verifikasi OTP</span>
                                <span class="text-base">Konfirmasi dengan kode dari email Anda.</span>
                            </div>
                        </li>
                    </ol>
                </div>

                <div class="flex items-start gap-3">
                    <svg class="mt-0.5 size-6 shrink-0 text-brand" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l7 3v5.5c0 4.2-2.9 7.7-7 9.5-4.1-1.8-7-5.3-7-9.5V6z"/><path d="M9 12l2.2 2.2L15.5 10"/></svg>
                    <p class="text-base">Kata sandi lama tidak diperlukan. Identitas Anda dipastikan lewat kode OTP.</p>
                </div>
            </div>

            {{-- KANAN --}}
            <div class="flex min-h-[420px] min-w-0 flex-col gap-5 p-6 sm:p-8">

                {{-- LANGKAH 1 --}}
                <div x-show="langkahSandi === 1" class="flex flex-1 flex-col gap-5">
                    <div class="flex flex-col gap-2">
                        <h3 class="text-3xl font-medium">Buat kata sandi baru</h3>
                        <p class="text-base text-gray-400">Gunakan kata sandi yang berbeda dari sebelumnya dan belum pernah dipakai di tempat lain.</p>
                    </div>

                    <div class="flex flex-col gap-2">
                        <label for="sandi-baru" class="text-base font-medium">Kata sandi baru</label>
                        <div class="relative">
                            <input id="sandi-baru" name="password" x-ref="inputSandiBaru" x-model="sandiBaru"
                                   :type="lihatSandi1 ? 'text' : 'password'" autocomplete="new-password"
                                   placeholder="Masukkan kata sandi baru"
                                   class="h-12 w-full rounded-lg border border-gray-200 bg-white pr-12 pl-4 text-base placeholder:text-gray-400">
                            <button type="button" x-on:click="lihatSandi1 = !lihatSandi1"
                                    :aria-label="lihatSandi1 ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'" :aria-pressed="lihatSandi1"
                                    class="absolute top-1/2 right-3 flex size-8 -translate-y-1/2 cursor-pointer items-center justify-center rounded-md text-gray-900 hover:bg-gray-100">
                                <svg x-show="!lihatSandi1" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3"/><path d="M4 4l16 16"/></svg>
                                <svg x-show="lihatSandi1" x-cloak class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>

                        <div class="grid grid-cols-4 gap-2" role="img" :aria-label="`Kekuatan kata sandi: ${skorSandi} dari 4`">
                            <template x-for="n in 4" :key="n">
                                <span class="h-1.5 rounded-full" :class="n <= skorSandi ? warnaSandi : 'bg-gray-200'"></span>
                            </template>
                        </div>

                        <ul class="grid grid-cols-1 gap-x-6 gap-y-3 pt-2 sm:grid-cols-2">
                            <template x-for="s in syaratSandi" :key="s.teks">
                                <li class="flex items-center gap-3 text-base" :class="s.ok ? 'text-gray-900' : 'text-gray-400'">
                                    <span class="flex size-6 shrink-0 items-center justify-center rounded-full border-2"
                                          :class="s.ok ? 'border-green-500 bg-green-500 text-white' : 'border-gray-300'">
                                        <svg x-show="s.ok" class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
                                    </span>
                                    <span x-text="s.teks"></span>
                                </li>
                            </template>
                        </ul>
                    </div>

                    <div class="flex flex-col gap-2">
                        <label for="sandi-konfirmasi" class="text-base font-medium">Konfirmasi kata sandi baru</label>
                        <div class="relative">
                            <input id="sandi-konfirmasi" name="password_confirmation" x-model="konfirmasiSandi"
                                   :type="lihatSandi2 ? 'text' : 'password'" autocomplete="new-password"
                                   placeholder="Masukkan kata sandi baru"
                                   class="h-12 w-full rounded-lg border border-gray-200 bg-white pr-12 pl-4 text-base placeholder:text-gray-400">
                            <button type="button" x-on:click="lihatSandi2 = !lihatSandi2"
                                    :aria-label="lihatSandi2 ? 'Sembunyikan konfirmasi kata sandi' : 'Tampilkan konfirmasi kata sandi'" :aria-pressed="lihatSandi2"
                                    class="absolute top-1/2 right-3 flex size-8 -translate-y-1/2 cursor-pointer items-center justify-center rounded-md text-gray-900 hover:bg-gray-100">
                                <svg x-show="!lihatSandi2" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3"/><path d="M4 4l16 16"/></svg>
                                <svg x-show="lihatSandi2" x-cloak class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                        <p x-show="konfirmasiSandi !== '' && !sandiCocok" x-cloak role="alert" class="text-sm text-red-600">Konfirmasi belum sama dengan kata sandi baru.</p>
                    </div>

                    <p class="text-base text-gray-400" x-text="`Kode OTP akan dikirim ke ${emailSamar}.`"></p>
                </div>

                {{-- LANGKAH 2 --}}
                <div x-show="langkahSandi === 2" x-cloak class="flex flex-1 flex-col gap-5">
                    <div class="flex flex-col gap-2">
                        <h3 class="text-3xl font-medium">Masukkan kode OTP</h3>
                        <p class="text-base text-gray-400" x-text="`Kami mengirim kode 6 digit ke ${emailSamar}. Kode berlaku 5 menit.`"></p>
                    </div>

                    <div class="flex flex-col gap-3">
                        <div class="flex items-center justify-between">
                            <span id="label-otp" class="text-base font-medium">Kode OTP</span>
                            <span class="text-base tabular-nums" :class="otpKedaluwarsa ? 'text-red-600' : ''" x-text="waktuOtp" aria-live="off"></span>
                        </div>

                        <div class="flex gap-2 sm:gap-3" role="group" aria-labelledby="label-otp">
                            <template x-for="(d, i) in otpDigits" :key="i">
                                <input :id="`otp-sandi-${i}`" type="text" inputmode="numeric" maxlength="1" autocomplete="one-time-code"
                                       :aria-label="`Digit ${i + 1}`" :value="d"
                                       x-on:input="masukOtp($event, i)"
                                       x-on:keydown.backspace="hapusOtp($event, i)"
                                       x-on:paste.prevent="tempelOtp($event)"
                                       :class="d ? 'border-brand' : 'border-gray-300 focus:border-brand'"
                                       class="h-14 w-0 min-w-0 flex-1 rounded-xl border-2 bg-white text-center text-3xl font-medium sm:h-16">
                            </template>
                        </div>
                        <input type="hidden" name="otp" :value="otpDigits.join('')">

                        <p x-show="otpKedaluwarsa && !idOtp && !sandiSelesai" x-cloak role="alert" class="text-sm text-red-600">Kode sudah kedaluwarsa. Silakan kirim ulang kode.</p>

                    </div>

                    <div>
                        <button type="button" x-on:click="kirimOtp()" :disabled="prosesSandi || sandiSelesai"
                                class="cursor-pointer text-base text-brand hover:underline">Kirim ulang kode</button>
                    </div>

                    <p x-show="sandiSelesai" x-cloak role="status"
                       class="rounded-lg border border-green-200 bg-green-50 px-3.5 py-2.5 text-sm text-green-800">
                        Kata sandi berhasil diubah.
                    </p>
                </div>

                <p x-show="infoOtp" x-cloak role="status" class="text-sm" x-text="infoOtp"></p>
                <div class="mt-auto flex flex-wrap items-center justify-end gap-3 pt-4">
                    <button type="button" x-on:click="tutupModalSandi()"
                            class="h-12 cursor-pointer rounded-lg bg-red-500 px-6 text-base text-white hover:bg-red-600" x-text="sandiSelesai ? 'Tutup' : 'Batal'"></button>

                    <button x-show="langkahSandi === 1" type="submit" :disabled="!bisaKirimOtp"
                            class="h-12 rounded-lg bg-brand px-6 text-base text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-gray-200 disabled:text-gray-400 disabled:hover:bg-gray-200">Kirim Kode OTP</button>
                    <button x-show="langkahSandi === 2" x-cloak type="submit" :disabled="!bisaSimpanSandi"
                            class="h-12 rounded-lg bg-brand px-6 text-base text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-gray-200 disabled:text-gray-400 disabled:hover:bg-gray-200">Simpan Kata Sandi</button>
                </div>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('profilDosenPage', (csrfTokenBlade = '') => ({
        csrfTokenBlade,
        sedangMemuat: true,
        sedangMenyimpanFoto: false,
        pesanHalaman: '',
        tipePesanHalaman: 'sukses',
        profil: {
            id_dosen: null,
            nama: '',
            nip: '',
            email: '',
            foto_profil: null,
            pangkat_terakhir: null,
        },
        riwayatPangkat: [],
        urlFotoTampil: '',

        // Crop & Zoom
        modalCropTerbuka: false,
        pesanErrorCrop: '',
        gambarSumber: null,
        mimeTipeAsli: 'image/jpeg',
        skalaZoom: 1,
        offsetX: 0,
        offsetY: 0,
        sedangGeser: false,
        titikAwalX: 0,
        titikAwalY: 0,

        // Modal Ubah Kata Sandi
        modalSandiTerbuka: false,
        langkahSandi: 1,
        sandiBaru: '',
        konfirmasiSandi: '',
        lihatSandi1: false,
        lihatSandi2: false,
        otpDigits: ['', '', '', '', '', ''],
        sisaOtp: 300,
        timerOtp: null,
        infoOtp: '',
        prosesSandi: false,
        idOtp: null,
        sandiSelesai: false,

        get emailSamar() {
            const email = this.profil.email || 'user@polines.ac.id';
            const [nama, domain] = email.split('@');
            if (!nama || !domain) return email;
            return `${nama.slice(0, 1)}${'*'.repeat(11)}@${domain}`;
        },

        get syaratSandi() {
            return [
                { teks: 'Minimal 8 karakter', ok: this.sandiBaru.length >= 8 },
                { teks: 'Mengandung huruf',   ok: /[A-Za-z]/.test(this.sandiBaru) },
                { teks: 'Mengandung angka',   ok: /\d/.test(this.sandiBaru) },
                { teks: 'Mengandung simbol',  ok: /[^A-Za-z0-9]/.test(this.sandiBaru) },
            ];
        },
        get skorSandi() { return this.syaratSandi.filter((s) => s.ok).length; },
        get warnaSandi() { return ['', 'bg-red-500', 'bg-orange-400', 'bg-yellow-400', 'bg-green-500'][this.skorSandi]; },
        get sandiCocok() { return this.konfirmasiSandi !== '' && this.konfirmasiSandi === this.sandiBaru; },
        get bisaKirimOtp() { return !this.prosesSandi && this.skorSandi === 4 && this.sandiCocok; },
        get waktuOtp() {
            const m = String(Math.floor(this.sisaOtp / 60)).padStart(2, '0');
            const s = String(this.sisaOtp % 60).padStart(2, '0');
            return `${m}:${s}`;
        },
        get otpKedaluwarsa() { return this.sisaOtp === 0; },
        get bisaSimpanSandi() { return !this.prosesSandi && this.otpDigits.every((d) => d !== '') && (this.idOtp !== null || !this.otpKedaluwarsa) && !this.sandiSelesai; },

        async muatData() {
            this.sedangMemuat = true;
            this.pesanHalaman = '';

            try {
                const [resProfil, resRiwayat] = await Promise.all([
                    fetch('/api/v1/dosen/profil', {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    }),
                    fetch('/api/v1/dosen/riwayat-pangkat', {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    }),
                ]);

                const dataProfil = await resProfil.json().catch(() => ({}));
                const dataRiwayat = await resRiwayat.json().catch(() => ({}));

                if (!resProfil.ok || dataProfil.success === false) {
                    throw new Error(dataProfil.message || 'Gagal memuat data profil dosen.');
                }

                this.profil = dataProfil.data ?? this.profil;
                this.riwayatPangkat = Array.isArray(dataRiwayat.data) ? dataRiwayat.data : [];
                this.urlFotoTampil = this.tambahCacheBust(this.profil.foto_profil);
            } catch (error) {
                this.tampilkanPesanHalaman('error', error.message || 'Terjadi kesalahan saat memuat profil.');
            } finally {
                this.sedangMemuat = false;
            }
        },

        tampilkanPesanHalaman(tipe, pesan) {
            this.tipePesanHalaman = tipe;
            this.pesanHalaman = pesan;
        },

        tambahCacheBust(url) {
            if (!url) return '';
            const sep = url.includes('?') ? '&' : '?';
            return `${url}${sep}v=${Date.now()}`;
        },

        sinkronkanFotoDariEvent(detail) {
            if (detail?.foto_profil) {
                this.profil.foto_profil = detail.foto_profil;
                this.urlFotoTampil = this.tambahCacheBust(detail.foto_profil);
            }
        },

        inisialNama(nama) {
            if (!nama) return 'DS';
            return nama.trim().split(/\s+/).slice(0, 2).map((b) => b[0]?.toUpperCase() || '').join('');
        },

        pilihFileFoto(event) {
            const file = event.target.files?.[0];
            if (!file) return;

            this.pesanErrorCrop = '';
            if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                this.tampilkanPesanHalaman('error', 'Format file harus JPG, JPEG, PNG, atau WEBP.');
                event.target.value = '';
                return;
            }

            if (file.size > 2 * 1024 * 1024) {
                this.tampilkanPesanHalaman('error', 'Ukuran file maksimal 2 MB.');
                event.target.value = '';
                return;
            }

            this.mimeTipeAsli = file.type;
            const reader = new FileReader();
            reader.onload = (e) => {
                const img = new Image();
                img.onload = () => {
                    this.gambarSumber = img;
                    this.skalaZoom = 1;
                    this.offsetX = 0;
                    this.offsetY = 0;
                    this.modalCropTerbuka = true;
                    this.$nextTick(() => this.gambarKeCanvas());
                };
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        },

        tutupModalCrop() {
            this.modalCropTerbuka = false;
            this.sedangGeser = false;
            this.pesanErrorCrop = '';
            if (this.$refs.inputFoto) {
                this.$refs.inputFoto.value = '';
            }
        },

        ubahZoom(delta) {
            this.skalaZoom = Math.min(3, Math.max(0.5, Number((this.skalaZoom + delta).toFixed(2))));
            this.gambarKeCanvas();
        },

        zoomDenganWheel(event) {
            this.ubahZoom(event.deltaY < 0 ? 0.1 : -0.1);
        },

        mulaiGeser(event) {
            this.sedangGeser = true;
            const titik = this.ambilKoordinat(event);
            this.titikAwalX = titik.x - this.offsetX;
            this.titikAwalY = titik.y - this.offsetY;
        },

        geserGambar(event) {
            if (!this.sedangGeser) return;
            const titik = this.ambilKoordinat(event);
            this.offsetX = titik.x - this.titikAwalX;
            this.offsetY = titik.y - this.titikAwalY;
            this.gambarKeCanvas();
        },

        selesaiGeser() {
            this.sedangGeser = false;
        },

        ambilKoordinat(event) {
            if (event.touches && event.touches.length > 0) {
                return { x: event.touches[0].clientX, y: event.touches[0].clientY };
            }
            return { x: event.clientX, y: event.clientY };
        },

        gambarKeCanvas() {
            const canvas = this.$refs.canvasCrop;
            if (!canvas || !this.gambarSumber) return;

            const ctx = canvas.getContext('2d');
            const ukuran = canvas.width;
            ctx.clearRect(0, 0, ukuran, ukuran);

            const rasio = Math.max(ukuran / this.gambarSumber.width, ukuran / this.gambarSumber.height);
            const lebar = this.gambarSumber.width * rasio * this.skalaZoom;
            const tinggi = this.gambarSumber.height * rasio * this.skalaZoom;
            const x = (ukuran - lebar) / 2 + this.offsetX;
            const y = (ukuran - tinggi) / 2 + this.offsetY;

            ctx.drawImage(this.gambarSumber, x, y, lebar, tinggi);
        },

        async simpanHasilCrop() {
            const canvas = this.$refs.canvasCrop;
            if (!canvas || this.sedangMenyimpanFoto) return;

            this.sedangMenyimpanFoto = true;
            this.pesanErrorCrop = '';

            try {
                const mimeOutput = ['image/jpeg', 'image/png', 'image/webp'].includes(this.mimeTipeAsli)
                    ? this.mimeTipeAsli
                    : 'image/jpeg';
                const ekstensi = mimeOutput === 'image/png' ? 'png' : (mimeOutput === 'image/webp' ? 'webp' : 'jpg');

                const blob = await new Promise((resolve) => canvas.toBlob(resolve, mimeOutput, 0.9));
                if (!blob) {
                    throw new Error('Gagal memproses hasil crop gambar.');
                }

                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                    || this.csrfTokenBlade;

                const formData = new FormData();
                if (csrfToken) {
                    formData.append('_token', csrfToken);
                }
                formData.append('_method', 'PATCH');
                formData.append('foto_profil', blob, `foto-profil.${ekstensi}`);

                const headers = {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                };
                if (csrfToken) {
                    headers['X-CSRF-TOKEN'] = csrfToken;
                }

                const response = await fetch('/api/v1/dosen/profil', {
                    method: 'POST',
                    headers,
                    body: formData,
                    credentials: 'same-origin',
                });

                const hasil = await response.json().catch(() => ({}));
                if (!response.ok || hasil.success === false) {
                    throw new Error(hasil.errors?.foto_profil?.[0] || hasil.message || `Gagal menyimpan foto profil (HTTP ${response.status}).`);
                }

                const fotoBaru = hasil.data?.foto_profil ?? this.profil.foto_profil;
                this.profil = { ...this.profil, ...(hasil.data ?? {}), foto_profil: fotoBaru };
                this.urlFotoTampil = this.tambahCacheBust(fotoBaru);

                window.dispatchEvent(new CustomEvent('profil-foto-diperbarui', {
                    detail: { foto_profil: this.urlFotoTampil },
                }));

                this.tutupModalCrop();
                this.tampilkanPesanHalaman('sukses', hasil.message || 'Foto profil berhasil diperbarui.');
            } catch (error) {
                this.pesanErrorCrop = error.message || 'Gagal menyimpan foto profil.';
            } finally {
                this.sedangMenyimpanFoto = false;
            }
        },

        // Modal Ubah Kata Sandi
        bukaModalSandi() {
            this.modalSandiTerbuka = true;
            this.$nextTick(() => this.$refs.inputSandiBaru?.focus());
        },
        tutupModalSandi() {
            if (this.prosesSandi) return;
            this.idOtp = null;
            this.modalSandiTerbuka = false;
            clearInterval(this.timerOtp);
            this.langkahSandi = 1;
            this.sandiBaru = '';
            this.konfirmasiSandi = '';
            this.lihatSandi1 = false;
            this.lihatSandi2 = false;
            this.otpDigits = ['', '', '', '', '', ''];
            this.sisaOtp = 300;
            this.infoOtp = '';
            this.sandiSelesai = false;
        },
        mulaiTimerOtp() {
            clearInterval(this.timerOtp);
            this.sisaOtp = 300;
            this.timerOtp = setInterval(() => {
                if (this.sisaOtp > 0) this.sisaOtp--;
                if (this.sisaOtp === 0) clearInterval(this.timerOtp);
            }, 1000);
        },
        async postSandi(langkah, payload = {}) {
            const response = await fetch('/api/v1/dosen/profil/ganti-sandi/' + langkah, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrfTokenBlade},
                body: JSON.stringify(payload),
            });
            const result = await response.json().catch(() => ({}));
            if (!response.ok || !result.success) {
                throw new Error(Object.values(result.errors || {}).flat()[0] || result.message || 'Permintaan gagal. Silakan coba lagi.');
            }
            return result.data;
        },
        async kirimOtp() {
            if (this.prosesSandi || this.sandiSelesai) return;
            this.prosesSandi = true;
            this.infoOtp = '';
            try {
                await this.postSandi('kirim-otp');
                this.idOtp = null;
                this.langkahSandi = 2;
                this.otpDigits = ['', '', '', '', '', ''];
                this.mulaiTimerOtp();
                this.infoOtp = 'Kode OTP telah dikirim.';
                this.$nextTick(() => document.getElementById('otp-sandi-0')?.focus());
            } catch (error) {
                this.infoOtp = error.message;
            } finally {
                this.prosesSandi = false;
            }
        },
        masukOtp(event, i) {
            const digit = event.target.value.replace(/\D/g, '').slice(-1);
            this.otpDigits[i] = digit;
            event.target.value = digit;
            if (digit && i < 5) document.getElementById(`otp-sandi-${i + 1}`)?.focus();
        },
        hapusOtp(event, i) {
            if (this.otpDigits[i] === '' && i > 0) {
                this.otpDigits[i - 1] = '';
                document.getElementById(`otp-sandi-${i - 1}`)?.focus();
            }
        },
        tempelOtp(event) {
            const angka = (event.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, 6);
            if (!angka) return;
            angka.split('').forEach((d, i) => { this.otpDigits[i] = d; });
            document.getElementById(`otp-sandi-${Math.min(angka.length, 5)}`)?.focus();
        },
        async simpanSandi() {
            if (!this.bisaSimpanSandi) return;
            this.prosesSandi = true;
            this.infoOtp = '';
            try {
                if (this.idOtp === null) {
                    const data = await this.postSandi('verifikasi', {otp: this.otpDigits.join('')});
                    this.idOtp = data.otp_id;
                }
                await this.postSandi('simpan', {
                    otp_id: this.idOtp, password: this.sandiBaru, password_confirmation: this.konfirmasiSandi,
                });
                clearInterval(this.timerOtp);
                this.sandiBaru = '';
                this.konfirmasiSandi = '';
                this.sandiSelesai = true;
            } catch (error) {
                this.infoOtp = error.message;
            } finally {
                this.prosesSandi = false;
            }
        },
    }));
});
</script>
@endpush
@endsection