<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Kegiatan</title>
    <style>
        body { font-family: sans-serif; font-size: 10px; color: #1f2937; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #9ca3af; padding: 6px; text-align: left; }
        th { background: #e5e7eb; }
    </style>
</head>
<body>
    <h1>Laporan Kegiatan</h1>
    <p>Dicetak {{ now()->format('d-m-Y H:i') }} · Jumlah data: {{ $rows->count() }}</p>
    <table>
        <thead><tr><th>Nama Kegiatan</th><th>Kategori</th><th>Pemilik</th><th>Tanggal Mulai</th><th>Tanggal Selesai</th><th>Status</th></tr></thead>
        <tbody>
            @forelse ($rows as $row)
                <tr><td>{{ $row->nama_kegiatan }}</td><td>{{ $row->kategori ?? 'Tanpa kategori' }}</td><td>{{ $row->pemilik }}</td><td>{{ $row->tanggal_mulai }}</td><td>{{ $row->tanggal_selesai }}</td><td>{{ $row->status ?? 'Belum diajukan' }}</td></tr>
            @empty
                <tr><td colspan="6">Tidak ada kegiatan pada filter ini.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
