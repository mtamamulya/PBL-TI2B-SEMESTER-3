<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Surat Final - {{ $kegiatan->nama_kegiatan }}</title>
    <style>
        body { font-family: 'Times New Roman', serif; font-size: 12pt; line-height: 1.5; margin: 30px; }
        .kop { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; }
        .kop h3, .kop p { margin: 2px 0; }
        .meta-table { width: 100%; margin-bottom: 20px; }
        .meta-table td { vertical-align: top; padding: 2px 0; }
        .isi-surat { margin: 16px 0; text-align: justify; }
        .box-table { margin-top: 20px; }
        .box-table table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .box-table th, .box-table td { border: 1px solid #000; padding: 6px 8px; font-size: 11pt; }
        .ttd-section { width: 45%; float: right; margin-top: 40px; text-align: left; }
        .ttd-img { height: 70px; margin: 6px 0; }
    </style>
</head>
<body>
    <div class="kop">
        <h3>KEMENTERIAN PENDIDIKAN TINGGI, SAINS, DAN TEKNOLOGI</h3>
        <h3>POLITEKNIK NEGERI SEMARANG</h3>
        <p>Jalan Prof. H. Soedarto, S.H., Tembalang, Semarang 50275</p>
    </div>

    <table class="meta-table">
        <tr>
            <td width="18%">Nomor SK</td>
            <td width="2%">:</td>
            <td>{{ $kegiatan->nomor_sk ?? '-' }}</td>
        </tr>
        <tr>
            <td>Kategori</td>
            <td>:</td>
            <td>{{ $kategori->nama_kategori ?? '-' }}</td>
        </tr>
        <tr>
            <td>Kegiatan</td>
            <td>:</td>
            <td><strong>{{ $kegiatan->nama_kegiatan }}</strong></td>
        </tr>
    </table>

    <div class="isi-surat">
        {!! nl2br(e($kategori->isi_surat)) !!}
    </div>

    @if($kategori->lampiran_peserta && $kegiatan->anggota->count() > 0)
        <div class="box-table">
            <strong>Daftar Peserta / Anggota Kegiatan:</strong>
            <table>
                <thead>
                    <tr>
                        <th width="8%">No</th>
                        <th>Nama Peserta</th>
                        <th width="30%">Peran</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($kegiatan->anggota as $idx => $anggota)
                        <tr>
                            <td align="center">{{ $idx + 1 }}</td>
                            <td>{{ $anggota->user->name ?? $anggota->user->nama ?? 'Anggota #' . ($idx + 1) }}</td>
                            <td>{{ $anggota->peran ?? 'Anggota' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if($lampiranDokumen->count() > 0)
        <div class="box-table">
            <strong>Dokumen Terlampir:</strong>
            <table>
                <thead>
                    <tr>
                        <th width="8%">No</th>
                        <th>Jenis Dokumen</th>
                        <th>Nama Berkas</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($lampiranDokumen as $idx => $doc)
                        <tr>
                            <td align="center">{{ $idx + 1 }}</td>
                            <td>{{ $doc->jenisDokumen->nama_jenis_dokumen ?? 'Lampiran' }}</td>
                            <td>{{ $doc->nama_file }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="ttd-section">
        <p>Semarang, {{ ($kegiatan->tanggal_sk ?? now())->translatedFormat('d F Y') }}</p>
        <p>Pejabat Berwenang,</p>

        @if($ttdBase64)
            <img src="{{ $ttdBase64 }}" class="ttd-img" alt="Tanda Tangan">
        @else
            <div style="height: 70px;"></div>
        @endif

        <p><strong>Direktur / Kaprodi</strong></p>
    </div>
</body>
</html>