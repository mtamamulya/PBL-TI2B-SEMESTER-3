<?php

namespace App\BackendKel2\Dokumen;

use App\Models\DetailKegiatan;
use App\Models\Dokumen;
use App\Models\Kegiatan;
use App\Support\LinkFile;
use Illuminate\Validation\ValidationException;

class TautanDokumenService
{
    private const HOSTS = [
        'drive.google.com',
        'docs.google.com',
        'onedrive.live.com',
        '1drv.ms',
    ];

    public function validasi(string $tautan): string
    {
        $tautan = trim($tautan);
        $bagian = parse_url($tautan);

        if ($bagian === false || ! isset($bagian['scheme'], $bagian['host']) || isset($bagian['user']) || isset($bagian['pass'])) {
            throw ValidationException::withMessages(['tautan' => 'Tautan dokumen tidak valid.']);
        }

        if (mb_strtolower($bagian['scheme']) !== 'https') {
            throw ValidationException::withMessages(['tautan' => 'Tautan dokumen wajib menggunakan HTTPS.']);
        }

        $host = mb_strtolower(rtrim($bagian['host'], '.'));
        if (! $this->hostDiizinkan($host)) {
            throw ValidationException::withMessages(['tautan' => 'Domain tautan harus Google Drive, Google Docs, OneDrive, atau SharePoint.']);
        }

        return $tautan;
    }

    public function normalisasi(string $tautan): string
    {
        $tautan = $this->validasi($tautan);
        $bagian = parse_url($tautan);
        $host = mb_strtolower(rtrim($bagian['host'], '.'));
        $path = $bagian['path'] ?? '';

        if ($host === 'drive.google.com' && preg_match('#^/file/d/([^/]+)/(?:view|edit)$#', $path, $cocok)) {
            return 'https://drive.google.com/file/d/'.$cocok[1].'/preview';
        }

        return $tautan;
    }

    public function buatTautanPreview(string $tautan): string
    {
        return $this->normalisasi($tautan);
    }

    /** @param array{id_jenis_dokumen:int,nama_file:string,tautan:string,id_detail_kegiatan?:int|null} $data */
    public function simpan(Kegiatan $kegiatan, array $data): Dokumen
    {
        $detailId = $data['id_detail_kegiatan'] ?? null;
        if ($detailId !== null && ! DetailKegiatan::query()->whereKey($detailId)->where('id_kegiatan', $kegiatan->getKey())->exists()) {
            throw ValidationException::withMessages(['id_detail_kegiatan' => 'Detail kegiatan harus berasal dari kegiatan yang sama.']);
        }

        $tautan = $this->normalisasi($data['tautan']);

        return Dokumen::create([
            'id_jenis_dokumen' => $data['id_jenis_dokumen'],
            'id_kegiatan' => $kegiatan->getKey(),
            'id_detail_kegiatan' => $detailId,
            'nama_file' => $data['nama_file'],
            'tipe_dokumen' => 'tautan',
            'link_dokumen' => $tautan,
            'is_lampiran' => false,
        ]);
    }

    public function url(Dokumen $dokumen): ?string
    {
        return LinkFile::url($dokumen->link_dokumen);
    }

    private function hostDiizinkan(string $host): bool
    {
        return in_array($host, self::HOSTS, true) || str_ends_with($host, '.sharepoint.com');
    }
}
