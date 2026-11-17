<?php

namespace App\BackendKel2\PengajuanMahasiswa;

use App\Http\Controllers\Controller;
use App\Models\Kegiatan;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class PengajuanMahasiswaController extends Controller
{
    // =========================================================================
    // BAGIAN 1: ENDPOINT API (Untuk pengujian Thunder Client / Frontend API)
    // =========================================================================

    public function apiIndex()
    {
        $data = Kegiatan::with(['kategori', 'user'])->latest()->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar pengajuan kegiatan berhasil diambil',
            'data'    => $data,
        ], 200);
    }

    

public function apiStore(Request $request)
{
    $validated = $request->validate([
        'id_kategori'        => 'required|exists:kategori,id_kategori',
        'id_user'            => 'nullable|exists:users,id_user',
        'nama_kegiatan'      => 'required|string|max:255',
        'detail'             => 'nullable|array',
        'detail.*'           => 'nullable|string',
        'documents'          => 'nullable|array',
        'documents.*.name'   => 'required_with:documents|string|max:255',
        'documents.*.type'   => 'nullable|string|max:100',
        'documents.*.url'    => 'nullable|string|max:500',
        'lampiran'           => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
    ]);

    return DB::transaction(function () use ($request, $validated) {
        $kegiatan = Kegiatan::create([
            'id_kategori'   => $validated['id_kategori'],
            'id_user'       => $validated['id_user'] ?? null,
            'nama_kegiatan' => $validated['nama_kegiatan'],
        ]);

        // 1. Catat status awal di tabel timeline
        DB::table('timeline')->insert([
            'id_kegiatan'    => $kegiatan->id_kegiatan,
            'status'         => 'diajukan',
            'tanggal_status' => now(),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        // 2. Simpan jawaban isian kategori ke detail_kegiatan (format: detail[id_isian_kategori] => nilai)
        if (!empty($validated['detail'])) {
            foreach ($validated['detail'] as $idIsian => $nilai) {
                if ($nilai !== null && $nilai !== '') {
                    DB::table('detail_kegiatan')->insert([
                        'id_kegiatan'        => $kegiatan->id_kegiatan,
                        'id_isian_kategori'  => $idIsian,
                        'isian'              => $nilai,
                        'created_at'         => now(),
                        'updated_at'         => now(),
                    ]);
                }
            }
        }

        // 3. Simpan lampiran file atau tautan ke tabel dokumen
        $idJenisDokumen = DB::table('jenis_dokumen')->value('id_jenis_dokumen');

        if ($idJenisDokumen && $request->hasFile('lampiran')) {
            $path = $request->file('lampiran')->store('lampiran', 'public');
            DB::table('dokumen')->insert([
                'id_jenis_dokumen' => $idJenisDokumen,
                'id_kegiatan'      => $kegiatan->id_kegiatan,
                'nama_file'        => $request->file('lampiran')->getClientOriginalName(),
                'tipe_dokumen'     => $request->file('lampiran')->getClientMimeType(),
                'link_dokumen'     => $path,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }

        if ($idJenisDokumen && !empty($validated['documents'])) {
            foreach ($validated['documents'] as $doc) {
                DB::table('dokumen')->insert([
                    'id_jenis_dokumen' => $idJenisDokumen,
                    'id_kegiatan'      => $kegiatan->id_kegiatan,
                    'nama_file'        => $doc['name'],
                    'tipe_dokumen'     => $doc['type'] ?? 'Tautan',
                    'link_dokumen'     => $doc['url'] ?? null,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan kegiatan berhasil disimpan',
            'data'    => $kegiatan,
        ], 201);
    });
}

    public function apiShow($id)
    {
        $kegiatan = Kegiatan::with(['kategori', 'user'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Detail pengajuan kegiatan berhasil diambil',
            'data'    => $kegiatan,
        ], 200);
    }

    public function apiUpdate(Request $request, $id)
    {
        $kegiatan = Kegiatan::findOrFail($id);

        $validated = $request->validate([
            'id_kategori'   => 'sometimes|exists:kategori,id_kategori',
            'nama_kegiatan' => 'sometimes|string|max:255',
            'detail'        => 'nullable|array',
            'detail.*'      => 'nullable|string',
            'lampiran'      => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        return DB::transaction(function () use ($request, $kegiatan, $validated) {
            $kegiatan->update(collect($validated)->only(['id_kategori', 'nama_kegiatan'])->toArray());

            if (!empty($validated['detail'])) {
                foreach ($validated['detail'] as $idIsian => $nilai) {
                    DB::table('detail_kegiatan')->updateOrInsert(
                        [
                            'id_kegiatan'       => $kegiatan->id_kegiatan,
                            'id_isian_kategori' => $idIsian,
                        ],
                        [
                            'isian'      => $nilai,
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );
                }
            }

            $idJenisDokumen = DB::table('jenis_dokumen')->value('id_jenis_dokumen');
            if ($idJenisDokumen && $request->hasFile('lampiran')) {
                $path = $request->file('lampiran')->store('lampiran', 'public');
                DB::table('dokumen')->insert([
                    'id_jenis_dokumen' => $idJenisDokumen,
                    'id_kegiatan'      => $kegiatan->id_kegiatan,
                    'nama_file'        => $request->file('lampiran')->getClientOriginalName(),
                    'tipe_dokumen'     => $request->file('lampiran')->getClientMimeType(),
                    'link_dokumen'     => $path,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Pengajuan kegiatan berhasil diperbarui',
                'data'    => $kegiatan,
            ], 200);
        });
    }

    public function apiDestroy($id)
    {
        $kegiatan = Kegiatan::findOrFail($id);

        DB::table('dokumen')->where('id_kegiatan', $id)->get()->each(function ($doc) {
            if ($doc->link_dokumen && Storage::disk('public')->exists($doc->link_dokumen)) {
                Storage::disk('public')->delete($doc->link_dokumen);
            }
        });

        $kegiatan->delete();

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan kegiatan berhasil dihapus',
        ], 200);
    }

    // =========================================================================
    // BAGIAN 2: PEMETAAN DATA UNTUK VIEW BLADE MAHASISWA
    // =========================================================================

    public function rows(): Collection
    {
        return Kegiatan::with('kategori')
            ->latest()
            ->get()
            ->map(function ($kegiatan) {
                $latestTimeline = DB::table('timeline')
                    ->where('id_kegiatan', $kegiatan->id_kegiatan)
                    ->latest('id_timeline')
                    ->first();

                $statusPengajuan = $latestTimeline ? ucfirst($latestTimeline->status) : 'Diajukan';

                return [
                    'id'           => $kegiatan->id_kegiatan,
                    'title'        => $kegiatan->nama_kegiatan,
                    'category'     => $kegiatan->kategori->nama_kategori ?? 'Umum',
                    'type'         => $kegiatan->kategori->nama_kategori ?? 'Umum',
                    'submitted_at' => $kegiatan->created_at ?? now(),
                    'status'       => $statusPengajuan,
                    'deskripsi'    => 'Pengajuan telah dikirim dan menunggu ditinjau.',
                    'editable'     => in_array($statusPengajuan, ['Diajukan', 'Direvisi', 'Direview']),
                ];
            });
    }

    public function showDetailData(int $id): array
    {
        $item = Kegiatan::with(['kategori', 'user'])->findOrFail($id);

        $latestTimeline = DB::table('timeline')
            ->where('id_kegiatan', $id)
            ->latest('id_timeline')
            ->first();

        $statusLabel = $latestTimeline ? ucfirst($latestTimeline->status) : 'Diajukan';

        $details = DB::table('detail_kegiatan')
            ->join('isian_kategori', 'detail_kegiatan.id_isian_kategori', '=', 'isian_kategori.id_isian_kategori')
            ->where('detail_kegiatan.id_kegiatan', $id)
            ->select(
                'isian_kategori.id_isian_kategori',
                'isian_kategori.input',
                'isian_kategori.jenis_input',
                'detail_kegiatan.isian'
            )
            ->get();

        $row = collect([
            'id'           => $item->id_kegiatan,
            'title'        => $item->nama_kegiatan,
            'category'     => $item->kategori->nama_kategori ?? 'Umum',
            'type'         => $item->kategori->nama_kategori ?? 'Umum',
            'submitted_at' => $item->created_at ?? now(),
            'status'       => $statusLabel,
            'editable'     => in_array($statusLabel, ['Diajukan', 'Direvisi', 'Direview']),
            'details'      => $details,
        ]);

        $student = [
            'name'  => $item->user->name ?? 'Mahasiswa',
            'nim'   => $item->user->nim ?? '-',
            'prodi' => $item->user->prodi ?? 'Teknologi Rekayasa Komputer',
        ];

        $files = DB::table('dokumen')
            ->where('id_kegiatan', $id)
            ->get()
            ->map(fn ($doc) => [
                'name' => $doc->nama_file,
                'meta' => $doc->tipe_dokumen ?? 'Dokumen',
                'url'  => str_starts_with((string) $doc->link_dokumen, 'http')
                    ? $doc->link_dokumen
                    : ($doc->link_dokumen ? asset('storage/' . $doc->link_dokumen) : null),
            ])
            ->all();

        return compact('item', 'row', 'student', 'files', 'details');
    }
}