<?php

namespace App\BackendKel2\Prestasi;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PrestasiController extends Controller
{
    public function simpan(Request $request)
    {
        // 1. VALIDASI (mendukung tautan cloud storage ATAU unggahan file PDF)
        $validated = $request->validate([
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
            'dokumen' => 'required|array|min:1',
            'dokumen.*.jenis' => 'required|string',
            'dokumen.*.url' => [
                'nullable',
                'required_without:dokumen.*.file',
                'url',
                function ($attribute, $value, $fail) {
                    if (empty($value)) {
                        return;
                    }
                    $host = strtolower(parse_url($value, PHP_URL_HOST) ?? '');
                    $allowed = ['drive.google.com', 'docs.google.com', '1drv.ms', 'onedrive.live.com'];
                    if (! in_array($host, $allowed) && ! str_ends_with($host, '.sharepoint.com')) {
                        $fail('Tautan harus berasal dari Google Drive atau OneDrive.');
                    }
                },
            ],
            'dokumen.*.file' => 'nullable|required_without:dokumen.*.url|file|mimes:pdf|max:5120',
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

            foreach ($request->dokumen as $index => $dok) {
                $idJenisDokumen = DB::table('jenis_dokumen')
                    ->where('nama_jenis_dokumen', $dok['jenis'])
                    ->value('id_jenis_dokumen');

                if (! $idJenisDokumen) {
                    $idJenisDokumen = DB::table('jenis_dokumen')->insertGetId([
                        'nama_jenis_dokumen' => $dok['jenis'],
                    ], 'id_jenis_dokumen');
                }

                $uploadedFile = $request->file("dokumen.{$index}.file");

                if ($uploadedFile) {
                    $originalBase = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $sanitizedName = (Str::slug($originalBase) ?: 'sertifikat').'.pdf';
                    $storedName = time().'_'.$index.'_'.$sanitizedName;
                    $linkOrPath = $uploadedFile->storeAs('bukti-prestasi', $storedName, 'local');
                    $namaFile = $sanitizedName;
                } else {
                    $linkOrPath = $dok['url'];
                    $namaFile = 'Bukti '.$dok['jenis'];
                }

                DB::table('bukti_prestasi')->insert([
                    'id_prestasi' => $idPrestasi,
                    'id_jenis_dokumen' => $idJenisDokumen,
                    'link' => $linkOrPath,
                    'nama_file' => $namaFile,
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

    public function lihatBukti($id)
    {
        $bukti = DB::table('bukti_prestasi')
            ->join('prestasi', 'bukti_prestasi.id_prestasi', '=', 'prestasi.id_prestasi')
            ->where('bukti_prestasi.id_bukti_prestasi', $id)
            ->select('bukti_prestasi.*', 'prestasi.id_user')
            ->first();

        if (! $bukti) {
            abort(404, 'Bukti prestasi tidak ditemukan.');
        }

        $user = auth()->user();
        $roleName = strtolower((string) DB::table('roles')
            ->where('id_role', $user->id_role)
            ->value('nama_role'));

        $isOwner = (int) $bukti->id_user === (int) auth()->id();
        $isPrivileged = in_array($roleName, ['admin', 'superadmin', 'dosen', 'verifikator'], true);

        if (! $isOwner && ! $isPrivileged) {
            abort(403, 'Anda tidak memiliki izin mengakses file ini.');
        }

        if (str_starts_with($bukti->link, 'http://') || str_starts_with($bukti->link, 'https://')) {
            return redirect()->away($bukti->link);
        }

        if (! Storage::disk('local')->exists($bukti->link)) {
            abort(404, 'File sertifikat tidak ditemukan.');
        }

        return Storage::disk('local')->response($bukti->link, $bukti->nama_file);
    }
}
