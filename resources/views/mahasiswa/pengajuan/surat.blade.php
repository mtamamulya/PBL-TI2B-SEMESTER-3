<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surat Persetujuan - {{ $judul }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: #fff !important; margin: 0 !important; padding: 0 !important; }
            article {
                width: 100% !important; max-width: none !important; min-height: 0 !important;
                margin: 0 !important; padding: 0 !important; box-shadow: none !important;
            }
            @page { size: A4; margin: 2.5cm; }
            .lampiran-surat {
                border: 0 !important; margin-top: 0 !important; padding-top: 0 !important;
                break-before: page; page-break-before: always;
            }
        }
    </style>
</head>
<body class="bg-gray-100 text-[#0A0A0A]">
    @php
        $nomorTampil = trim((string) $nomorSurat) !== '' ? $nomorSurat : '-';
        $jumlahLampiran = ($adaLampiran ? 1 : 0) + count($lampiranDokumen);
    @endphp

    <div class="no-print sticky top-0 z-10 border-b border-gray-200 bg-white px-6 py-3 shadow-sm">
        <div class="mx-auto flex max-w-[800px] items-center justify-between gap-3">
            <a href="{{ route('pengajuan.show', $id) }}" class="text-sm font-semibold text-gray-600 hover:text-gray-900">
                &larr; Kembali ke detail pengajuan
            </a>
            <button type="button" onclick="window.print()"
                    class="rounded-lg bg-[#2563EB] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                Cetak / Simpan PDF
            </button>
        </div>
    </div>

    <div class="px-4 py-8">
        <article class="mx-auto min-h-[1056px] w-full max-w-[800px] bg-white px-8 py-10 font-serif text-[13px] leading-[1.6] text-[#0A0A0A] shadow-sm sm:px-12">
            <header class="relative border-b border-gray-400 pb-3" style="font-family:'Times New Roman',Times,serif">
                <img src="{{ asset('images/logo.png') }}" alt="" class="absolute left-2 top-1/2 size-[72px] -translate-y-[56%] object-contain" onerror="this.style.visibility='hidden'">
                <div class="px-[92px] text-center leading-snug">
                    <div class="text-[14px] uppercase">Kementerian Pendidikan Tinggi, Sains, dan Teknologi</div>
                    <div class="text-[14px] uppercase">Politeknik Negeri Semarang</div>
                    <div class="mt-0.5 text-[11.5px]">Jalan Prof. H. Soedarto, S.H., Tembalang, Semarang 50275</div>
                    <div class="text-[11.5px]">Telepon (024) 7473417, 7499585, 7499586</div>
                    <div class="text-[11.5px]">Laman www.polines.ac.id, Surel sekretariat@polines.ac.id</div>
                </div>
            </header>

            <div class="mt-6 flex justify-end">
                <div class="text-right">Semarang, {{ $tanggalSurat }}</div>
            </div>

            <table class="mt-2 text-[13px]">
                <tbody>
                    <tr>
                        <td class="pr-3 align-top">Nomor</td>
                        <td class="pr-2 align-top">:</td>
                        <td class="align-top">{{ $nomorTampil }}</td>
                    </tr>
                    @if ($jumlahLampiran > 0)
                    <tr>
                        <td class="pr-3 align-top">Lampiran</td>
                        <td class="pr-2 align-top">:</td>
                        <td class="align-top">{{ $jumlahLampiran }} berkas</td>
                    </tr>
                    @endif
                    <tr>
                        <td class="pr-3 align-top">Hal</td>
                        <td class="pr-2 align-top">:</td>
                        <td class="align-top font-bold">Persetujuan Kegiatan</td>
                    </tr>
                </tbody>
            </table>

            <div class="mt-6 flex flex-col gap-2 text-justify text-[13px]">
                {!! $isiSurat !!}
            </div>

            <div class="mt-14 flex justify-end">
                <div class="w-fit" style="text-align:left">
                    <div>Ketua Program Studi,</div>
                    <div class="flex h-20 items-end">
                        @if ($ttdUrl)
                            <img class="max-h-20 max-w-[200px] object-contain mix-blend-multiply" src="{{ $ttdUrl }}" alt="Tanda tangan Kaprodi">
                        @endif
                    </div>
                    <div><span class="font-bold underline">{{ $namaKaprodi }}</span></div>
                    <div>NIP. {{ $nipKaprodi }}</div>
                </div>
            </div>

            @if ($jumlahLampiran > 0)
            <section class="lampiran-surat mt-12 border-t-2 border-dashed border-gray-300 pt-8">
                <table class="text-[13px]">
                    <tbody>
                        <tr><td class="pr-3 align-top">Lampiran</td><td class="pr-2 align-top">:</td><td class="align-top">Surat Persetujuan Kegiatan</td></tr>
                        <tr><td class="pr-3 align-top">Nomor</td><td class="pr-2 align-top">:</td><td class="align-top">{{ $nomorTampil }}</td></tr>
                        <tr><td class="pr-3 align-top">Tanggal</td><td class="pr-2 align-top">:</td><td class="align-top">{{ $tanggalSurat }}</td></tr>
                    </tbody>
                </table>

                <div class="mt-6 font-bold">Daftar Lampiran</div>
                <ol class="ml-6 list-decimal text-[13px]">
                    @if ($adaLampiran)
                        <li>Daftar Peserta (halaman ini)</li>
                    @endif
                    @foreach ($lampiranDokumen as $nama)
                        <li>{{ $nama }}</li>
                    @endforeach
                </ol>

                @if ($adaLampiran)
                    <div class="mt-8 text-center font-bold uppercase">Daftar Peserta</div>
                    <div class="text-center">{{ $judul }}</div>

                    <table class="mt-4 w-full border-collapse text-[13px]">
                        <thead>
                            <tr>
                                <th class="w-12 border border-black px-2 py-1 text-center font-bold">No</th>
                                <th class="border border-black px-2 py-1 text-left font-bold">Nama</th>
                                <th class="w-40 border border-black px-2 py-1 text-left font-bold">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($peserta as $i => [$namaPeserta, $peranPeserta])
                                <tr>
                                    <td class="border border-black px-2 py-1 text-center">{{ $i + 1 }}</td>
                                    <td class="border border-black px-2 py-1">{{ $namaPeserta }}</td>
                                    <td class="border border-black px-2 py-1">{{ $peranPeserta }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

                <div class="mt-10 flex justify-end">
                    <div class="w-fit" style="text-align:left">
                        <div>Ketua Program Studi,</div>
                        <div class="flex h-20 items-end">
                            @if ($ttdUrl)
                                <img class="max-h-20 max-w-[200px] object-contain mix-blend-multiply" src="{{ $ttdUrl }}" alt="Tanda tangan Kaprodi">
                            @endif
                        </div>
                        <div><span class="font-bold underline">{{ $namaKaprodi }}</span></div>
                        <div>NIP. {{ $nipKaprodi }}</div>
                    </div>
                </div>
            </section>
            @endif
        </article>
    </div>

    @if ($autoPrint)
        <script>
            window.addEventListener('load', () => window.print());
        </script>
    @endif
</body>
</html>