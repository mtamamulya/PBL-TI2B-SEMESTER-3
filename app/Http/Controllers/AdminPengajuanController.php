<?php

namespace App\Http\Controllers;

use App\Support\PanggilApiWeb;
use Carbon\Carbon;

/** Daftar pengajuan sisi admin dari API (Diajukan / Direvisi). */
class AdminPengajuanController extends Controller
{
    use PanggilApiWeb;

    public function index()
    {
        $hasil = $this->apiGet('/admin/pengajuan', [
            'status' => 'antrean',
            'per_page' => 100,
        ]);

        $rows = array_map(fn (array $r) => $this->bentukBaris($r), $hasil['data'] ?? []);

        return view('admin.pengajuan', ['rows' => $rows]);
    }

    private function bentukBaris(array $r): array
    {
        $rawDate = $r['tanggal_mulai'] ?? ($r['tanggal_status'] ?? null);
        $tanggalIndo = '-';
        $semester = '';

        if ($rawDate) {
            $dt = Carbon::parse($rawDate);
            $tanggalIndo = $dt->locale('id')->translatedFormat('j F Y');
            $semester = ($dt->month >= 3 && $dt->month <= 8) ? 'Genap' : 'Ganjil';
        }

        $status = match ($r['status'] ?? 'diajukan') {
            'diajukan' => 'Diajukan',
            'direvisi' => 'Direvisi',
            'ditolak' => 'Ditolak',
            'diterima', 'selesai' => 'Disetujui',
            default => $r['label_status'] ?? 'Diajukan',
        };

        return [
            'id' => $r['id_kegiatan'],
            'nama' => $r['nama_kegiatan'] ?? '-',
            'kategori' => $r['kategori'] ?? '-',
            'pengaju' => $r['pengaju']['nama'] ?? '-',
            'tanggal' => $tanggalIndo,
            'raw_date' => $rawDate,
            'status' => $status,
            'semester' => $semester,
            'jenis_pengaju' => $r['pengaju']['jenis_pengaju'] ?? 'Mahasiswa',
        ];
    }
}
