<?php

namespace App\BackendKel2\Prestasi;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PrestasiController extends Controller
{
    public function simpan(Request $request)
    {
        // 1. VALIDASI (khusus tautan Google Drive / OneDrive)
        $request->validate([
            'sumber' => 'required|in:sebelumnya,mandiri',
            'id_kegiatan' => 'required_if:sumber,sebelumnya',
            'kegiatan' => 'required_if:sumber,sebelumnya',
            'nama_lomba' => 'required|string|max:255',
            'penyelenggara' => 'required|string|max:150',
            'lokasi' => 'required|string|max:150',
            'tingkat' => 'required|in:Kabupaten/Kota,Provinsi,Nasional,Internasional',
            'capaian' => 'required|string|max:100',
            'jenis' => 'required|in:Akademik,Nonakademik',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'bentuk' => 'required|in:Individu,Tim',
            'dokumen' => 'required|array|min:1|max:5',
            'dokumen.*.jenis' => 'required|string',
            'dokumen.*.url' => [
                'required',
                'url',
                function ($attribute, $value, $fail) {
                    $host = strtolower(parse_url($value, PHP_URL_HOST) ?? '');
                    $allowed = ['drive.google.com', 'docs.google.com', '1drv.ms', 'onedrive.live.com'];
                    if (! in_array($host, $allowed) && ! str_ends_with($host, '.sharepoint.com')) {
                        $fail('Tautan harus berasal dari Google Drive atau OneDrive.');
                    }
                },
            ],
        ]);

        // Validasi kegiatan yang dipilih harus milik user & sudah diterima
        if ($request->sumber === 'sebelumnya') {
            $kegiatanMilikUser = DB::table('kegiatan')
                ->where('id_kegiatan', $request->id_kegiatan)
                ->where('id_user', auth()->id())
                ->exists();

            $statusTerakhir = DB::table('timeline')
                ->where('id_kegiatan', $request->id_kegiatan)
                ->orderByDesc('tanggal_status')
                ->value('status');

            if (! $kegiatanMilikUser || strtolower((string) $statusTerakhir) !== 'diterima') {
                return back()->withInput()->withErrors([
                    'kegiatan' => 'Kegiatan yang dipilih tidak valid atau belum diterima.',
                ]);
            }
        }

        // 2. SIMPAN KE DATABASE DENGAN TRANSACTION
        DB::beginTransaction();
        try {
            $userId = auth()->id();

            $jenisPrestasi = $request->jenis === 'Nonakademik' ? 'non-akademik' : 'akademik';
            $tingkatKejuaraan = strtolower($request->tingkat);
            $bentukPartisipasi = strtolower($request->bentuk);

            $idPrestasi = DB::table('prestasi')->insertGetId([
                'id_user' => $userId,
                'created_by' => $userId,
                'id_kegiatan' => $request->sumber === 'sebelumnya' ? $request->id_kegiatan : null,
                'capaian' => $request->capaian,
                'jenis_prestasi' => $jenisPrestasi,
                'nama_lomba' => $request->nama_lomba,
                'penyelenggara' => $request->penyelenggara,
                'lokasi' => $request->lokasi,
                'tingkat_kejuaraan' => $tingkatKejuaraan,
                'tanggal_mulai' => $request->tanggal_mulai,
                'tanggal_selesai' => $request->tanggal_selesai,
                'bentuk_partisipasi' => $bentukPartisipasi,
                'created_at' => now(),
                'updated_at' => now(),
            ], 'id_prestasi');

            foreach ($request->dokumen as $dok) {
                $idJenisDokumen = DB::table('jenis_dokumen')
                    ->where('nama_jenis_dokumen', $dok['jenis'])
                    ->value('id_jenis_dokumen');

                if (! $idJenisDokumen) {
                    $idJenisDokumen = DB::table('jenis_dokumen')->insertGetId([
                        'nama_jenis_dokumen' => $dok['jenis'],
                    ], 'id_jenis_dokumen');
                }

                DB::table('bukti_prestasi')->insert([
                    'id_prestasi' => $idPrestasi,
                    'id_jenis_dokumen' => $idJenisDokumen,
                    'link' => $dok['url'],
                    'nama_file' => 'Bukti '.$dok['jenis'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::commit();

            return redirect()->route('mahasiswa.prestasi')->with('status', 'Prestasi berhasil ditambahkan!');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withInput()->withErrors(['error' => 'Gagal menyimpan data: '.$e->getMessage()]);
        }
    }
}
