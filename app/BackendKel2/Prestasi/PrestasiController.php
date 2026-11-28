<?php

namespace App\BackendKel2\Prestasi;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PrestasiController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()?->getKey() ?? auth()->id();

        $prestasiList = DB::table('prestasi as p')
            ->leftJoin('kegiatan as k', 'k.id_kegiatan', '=', 'p.id_kegiatan')
            ->where('p.id_user', $userId)
            ->select([
                'p.id_prestasi',
                'p.id_kegiatan',
                'k.nama_kegiatan',
                'p.nama_lomba',
                'p.penyelenggara',
                'p.lokasi',
                'p.tingkat_kejuaraan',
                'p.capaian',
                'p.jenis_prestasi',
                'p.tanggal_mulai',
                'p.tanggal_selesai',
                'p.bentuk_partisipasi',
            ])
            ->orderByDesc('p.id_prestasi')
            ->get();

        $buktiMap = DB::table('bukti_prestasi as bp')
            ->leftJoin('jenis_dokumen as jd', 'jd.id_jenis_dokumen', '=', 'bp.id_jenis_dokumen')
            ->whereIn('bp.id_prestasi', $prestasiList->pluck('id_prestasi'))
            ->select([
                'bp.id_bukti_prestasi',
                'bp.id_prestasi',
                'bp.link',
                'bp.nama_file',
                'jd.nama_jenis_dokumen as jenis',
            ])
            ->get()
            ->groupBy('id_prestasi');

        $data = $prestasiList->map(function ($row) use ($buktiMap) {
            $tingkatLabel = match (strtolower((string) $row->tingkat_kejuaraan)) {
                'kabupaten/kota' => 'Kabupaten/Kota',
                'provinsi' => 'Provinsi',
                'nasional' => 'Nasional',
                'internasional' => 'Internasional',
                default => ucfirst((string) $row->tingkat_kejuaraan),
            };

            $jenisLabel = strtolower((string) $row->jenis_prestasi) === 'non-akademik'
                ? 'Nonakademik'
                : 'Akademik';

            $tanggal = (string) ($row->tanggal_selesai ?: $row->tanggal_mulai ?: '');

            return [
                'id' => (int) $row->id_prestasi,
                'nama' => (string) $row->nama_lomba,
                'bidang' => (string) ($row->lokasi ?? '-'),
                'bentuk' => ucfirst(strtolower((string) $row->bentuk_partisipasi)),
                'capaian' => (string) $row->capaian,
                'tingkat' => $tingkatLabel,
                'jenis' => $jenisLabel,
                'penyelenggara' => (string) $row->penyelenggara,
                'tanggal' => $tanggal,
                'tahun' => strlen($tanggal) >= 4 ? substr($tanggal, 0, 4) : '',
                'kegiatan' => $row->nama_kegiatan,
                'bukti' => ($buktiMap->get($row->id_prestasi) ?? collect())->values()->all(),
            ];
        })->values();

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    public function simpan(Request $request)
    {
        $request->validate([
            'sumber' => 'required|in:sebelumnya,mandiri',
            'id_kegiatan' => 'required_if:sumber,sebelumnya',
            'kegiatan' => 'required_if:sumber,sebelumnya',
            'nama_lomba' => 'required|string|max:255',
            'penyelenggara' => 'required|string|max:150',
            'lokasi' => 'required|string|max:150',
            'tingkat' => 'required|in:Internal,Kabupaten/Kota,Provinsi,Nasional,Internasional',
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
                    $scheme = strtolower(parse_url($value, PHP_URL_SCHEME) ?? '');
                    $allowed = ['drive.google.com', 'docs.google.com', '1drv.ms', 'onedrive.live.com'];
                    if ($scheme !== 'https' || (! in_array($host, $allowed) && ! str_ends_with($host, '.sharepoint.com'))) {
                        $fail('Tautan harus berasal dari Google Drive atau OneDrive.');
                    }
                },
            ],
        ]);

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
                if ($request->expectsJson() || $request->is('api/*')) {
                    return response()->json([
                        'message' => 'Kegiatan yang dipilih tidak valid atau belum diterima.',
                        'errors' => ['kegiatan' => ['Kegiatan yang dipilih tidak valid atau belum diterima.']],
                    ], 422);
                }

                return back()->withInput()->withErrors([
                    'kegiatan' => 'Kegiatan yang dipilih tidak valid atau belum diterima.',
                ]);
            }
        }

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

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Prestasi berhasil disimpan.',
                    'id_prestasi' => $idPrestasi,
                ], 201);
            }

            return redirect()->route('mahasiswa.prestasi')->with('status', 'Prestasi berhasil disimpan.');
        } catch (\Exception $e) {
            DB::rollBack();

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Gagal menyimpan data: '.$e->getMessage(),
                ], 500);
            }

            return back()->withInput()->withErrors(['error' => 'Gagal menyimpan data: '.$e->getMessage()]);
        }
    }
}
