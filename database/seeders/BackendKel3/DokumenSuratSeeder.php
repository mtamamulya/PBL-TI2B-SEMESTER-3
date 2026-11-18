<?php

namespace Database\Seeders\BackendKel3;

use App\Models\DokumenTerlampir;
use App\Models\Models\BackendKel3\DokumenSurat;
use Illuminate\Database\Seeder;

class DokumenSuratSeeder extends Seeder
{
    public function run(): void
    {
        // Data Pengajuan 1
        $surat1 = DokumenSurat::firstOrCreate(
            ['nama_kegiatan' => 'Penyuluhan Literasi Digital'],
            [
                'user_id'         => 1,
                'jenis_kegiatan'  => 'Kegiatan Dosen',
                'kategori'        => 'Pengabdian',
                'penyelenggara'   => 'PT Mitra Teknologi',
                'tanggal_mulai'   => '2026-10-10',
                'tanggal_selesai' => '2026-10-12',
                'status'          => 'Diajukan',
            ]
        );

        DokumenTerlampir::firstOrCreate(
            [
                'dokumen_surat_id' => $surat1->id,
                'nama_file'        => 'Proposal-penyuluhan-literasi-digital.pdf',
            ],
            [
                'jenis_dokumen' => 'Proposal',
                'file_path'     => 'https://drive.google.com/file/d/contoh-proposal',
            ]
        );

        // Data Pengajuan 3 (Dokumen Perbaikan)
        $surat3 = DokumenSurat::firstOrCreate(
            ['user_id' => 3],
            [
                'nama_kegiatan'   => 'Pengembangan Sistem Informasi Akademik',
                'jenis_kegiatan'  => 'Kegiatan Dosen',
                'kategori'        => 'Penelitian',
                'penyelenggara'   => 'Politeknik Negeri Semarang',
                'tanggal_mulai'   => '2026-11-01',
                'tanggal_selesai' => '2026-11-05',
                'status'          => 'Revisi',
            ]
        );

        DokumenTerlampir::firstOrCreate(
            [
                'dokumen_surat_id' => $surat3->id,
                'nama_file'        => 'Jobsheet_Routing_Dinamis_OSPF.pdf',
            ],
            [
                'jenis_dokumen' => 'Dokumen Perbaikan',
                'file_path'     => 'https://drive.google.com/file/d/contoh-dokumen-perbaikan',
            ]
        );
    }
}