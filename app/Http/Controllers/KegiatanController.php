<?php

namespace App\Http\Controllers;

use App\Models\Kegiatan;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class KegiatanController extends Controller
{
    public function index(Request $request)
    {
        $isDosen = $request->routeIs('dosen.*');
        $kegiatan = Kegiatan::untukUser($request->user())
            ->with(['kategori.jenisKegiatan', 'statusTerkini', 'detail.isianKategori', 'prestasi', 'dokumen', 'anggota.user.mahasiswa', 'anggota.user.dosen'])
            ->withCount('anggota')->denganTanggal()->orderByDesc('tanggal_mulai')->orderByDesc('id_kegiatan')->get();
        $allActivities = $kegiatan->map(function ($k) use ($isDosen) {
            $members = $k->anggota->map(fn ($member) => [
                'name' => $member->user?->mahasiswa?->nama ?? $member->user?->dosen?->nama ?? $member->user?->email ?? '—',
                'role' => $member->peran,
            ])->values();
            if ($members->isEmpty() && $k->nilaiIsian('anggota')) {
                $members = collect(json_decode($k->nilaiIsian('anggota'), true) ?? []);
            }

            return [
                'id' => $isDosen ? $k->getKey() : 'kegiatan-'.$k->getKey(),
                'name' => $k->nama_kegiatan,
                'peran' => (int) $k->id_user === (int) auth()->id() ? 'Ketua' : 'Anggota',
                'editable' => $isDosen && $this->canEdit($k),
                'type' => $k->kategori?->nama_kategori ?? '—',
                'date' => $k->tanggal_mulai?->locale('id')->translatedFormat('d M Y') ?? '—',
                'raw_date' => $k->tanggal_mulai?->toDateString() ?? '',
                'status' => match ($k->statusTerkini?->status) {
                    'diajukan' => 'Diajukan',
                    'direview' => 'Direview',
                    'diterima' => 'Disetujui',
                    'direvisi' => $isDosen ? 'Direvisi' : 'Perlu revisi',
                    'ditolak' => 'Ditolak',
                    'selesai' => 'Selesai',
                    default => 'Tercatat',
                },
                'note' => $k->nilaiIsian('deskripsi') ?? $k->statusTerkini?->catatan_revisi,
                'capaian' => $k->nilaiIsian('posisi_dalam_kegiatan') ?? '—',
                'tingkat' => ucfirst($k->nilaiIsian('tingkat_kejuaraan') ?? '—'),
                'penyelenggara' => $k->nilaiIsian('penyelenggara') ?? '—',
                'jenis' => $k->prestasi->first()?->jenis_prestasi ?? '—',
                'bentuk' => $k->nilaiIsian('bentuk_partisipasi') ?? ($k->anggota_count > 1 ? 'Tim' : 'Individu'),
                'detail_url' => $isDosen ? route('dosen.kegiatan.show', $k->getKey()) : null,
                'file_name' => $k->dokumen->first()?->nama_file,
                'members' => $members,
                'documents' => $k->dokumen->map(fn ($document) => [
                    'name' => $document->nama_file,
                    'type' => $document->tipe_dokumen,
                    'url' => $this->documentUrl($document->link_dokumen),
                ]),
            ];
        })->sortByDesc('raw_date')->values();
        $counts = $allActivities->countBy(fn ($activity) => mb_strtolower($activity['type']));
        $categoryCounts = [
            'LOMBA' => $counts->get('lomba', 0),
            'VOLUNTEER' => $counts->get('volunteer', 0),
            'KKL' => $counts->get('kkl', 0) + $counts->get('kuliah kerja lapangan', 0),
            'MAGANG' => $counts->get('magang', 0),
            'PENGABDIAN' => $counts->get('pengabdian', 0),
            'PENELITIAN' => $counts->get('penelitian', 0),
        ];

        // Semester ganjil dimulai Agustus, semester genap Februari.
        $year = $request->user()?->mahasiswa?->tahun_masuk
            ?? (now()->month >= 8 ? now()->year : now()->year - 1);
        $start = Carbon::create($year, 8, 1)->startOfDay();
        $semesterCount = max(6, (int) floor($start->diffInMonths(now(), false) / 6) + 1);
        $semesters = collect(range(1, $semesterCount))->map(function ($number) use ($start, $allActivities) {
            $from = $start->copy()->addMonths(($number - 1) * 6);
            $until = $from->copy()->addMonths(6);

            return ['label' => 'Sem '.$number, 'value' => $allActivities->filter(fn ($activity) => $activity['raw_date'] && Carbon::parse($activity['raw_date'])->gte($from) && Carbon::parse($activity['raw_date'])->lt($until))->count()];
        });

        return view($request->routeIs('dosen.*') ? 'dosen.kegiatan.index' : 'mahasiswa.kegiatan.index', [
            'categoryCounts' => $categoryCounts,
            'categories' => $allActivities->pluck('type')->filter(fn ($category) => $category !== '—')->unique()->sort()->values(),
            'semesters' => $semesters,
            'chartMax' => max(20, (int) (ceil((int) $semesters->max('value') / 4) * 4)),
            'allActivities' => $allActivities,
        ]);
    }

    private function canEdit(Kegiatan $kegiatan): bool
    {
        return (int) $kegiatan->id_user === (int) auth()->id()
            && in_array($kegiatan->statusTerkini?->status, [null, 'draft', 'direvisi'], true);
    }

    private function editableFields(Kegiatan $kegiatan)
    {
        return $kegiatan->kategori->isianKategori->filter(fn ($field) => $field->jumlah_isian === 1 && in_array($field->jenis_input, ['teks', 'angka', 'rupiah', 'tanggal'], true));
    }

    public function edit(int $id)
    {
        $kegiatan = Kegiatan::with(['kategori.isianKategori', 'detail', 'statusTerkini', 'dokumen' => fn ($q) => $q->withExists('buktiHistory')])->findOrFail($id);
        abort_unless($this->canEdit($kegiatan), 403);

        return view('dosen.kegiatan.edit', ['kegiatan' => $kegiatan, 'fields' => $this->editableFields($kegiatan)]);
    }

    public function update(Request $request, int $id)
    {
        DB::transaction(function () use ($request, $id) {
            $kegiatan = Kegiatan::with(['kategori.isianKategori', 'detail'])->lockForUpdate()->findOrFail($id);
            abort_unless($this->canEdit($kegiatan), 403);

            $rules = ['nama_kegiatan' => ['required', 'string', 'max:255']];
            $fields = $this->editableFields($kegiatan);
            foreach ($fields as $field) {
                $rules['detail.'.$field->getKey()] = ['sometimes', $field->aturan === 'NOT NULL' ? 'required' : 'nullable', match ($field->jenis_input) {
                    'angka', 'rupiah' => 'numeric',
                    'tanggal' => 'date_format:Y-m-d',
                    default => 'string',
                }];
            }
            $data = $request->validate($rules);
            $kegiatan->update(['nama_kegiatan' => $data['nama_kegiatan']]);
            foreach ($fields as $field) {
                $key = $field->getKey();
                if (! array_key_exists($key, $data['detail'] ?? [])) {
                    continue;
                }
                $answer = $kegiatan->detail->firstWhere('id_isian_kategori', $key);
                $value = $data['detail'][$key];
                if ($value === null) {
                    if ($answer?->dokumen()->exists()) {
                        throw ValidationException::withMessages(['detail.'.$key => 'Isian yang terhubung ke dokumen tidak bisa dikosongkan.']);
                    }
                    if ($answer) {
                        $answer->delete();
                    }
                } elseif (! $answer || $answer->isian !== (string) $value) {
                    $kegiatan->detail()->updateOrCreate(['id_isian_kategori' => $key], ['isian' => (string) $value]);
                }
            }
        });

        return redirect()->route('dosen.kegiatan.show', $id)->with('success', 'Perubahan kegiatan berhasil disimpan.');
    }

    public function gantiLampiran(Request $request, int $id, int $dokumenId)
    {
        $request->validate(['file' => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:5120']);
        $disk = Storage::disk('public');
        $path = null;
        try {
            DB::transaction(function () use ($request, $id, $dokumenId, $disk, &$path) {
                $kegiatan = Kegiatan::where('id_user', $request->user()->getKey())->lockForUpdate()->findOrFail($id);
                abort_unless($kegiatan->statusTerkini?->status === 'direvisi', 403);
                $dokumen = $kegiatan->dokumen()->lockForUpdate()->findOrFail($dokumenId);
                abort_if($dokumen->buktiHistory()->exists(), 403);

                $file = $request->file('file');
                $path = $file->store('dokumen_perbaikan', 'public');
                abort_unless($path, 500, 'Gagal menyimpan berkas.');
                $dokumen->update([
                    'nama_file' => $file->getClientOriginalName(),
                    'tipe_dokumen' => $file->getMimeType(),
                    'link_dokumen' => $disk->url($path),
                ]);
            });
        } catch (\Throwable $e) {
            if ($path) {
                $disk->delete($path);
            }
            throw $e;
        }

        return redirect()->route('dosen.kegiatan.edit', $id)->with('success', 'Lampiran berhasil diganti.');
    }

    public function show(Request $request, int $id)
    {
        $kegiatan = Kegiatan::untukUser($request->user())->with([
            'kategori.jenisKegiatan', 'detail.isianKategori', 'statusTerkini', 'dokumen.jenisDokumen',
            'anggota.user.mahasiswa', 'anggota.user.dosen', 'anggota.user.role',
        ])->findOrFail($id);

        return view($request->routeIs('dosen.*') ? 'dosen.kegiatan.show' : 'kegiatan.show', [
            'id' => $kegiatan->getKey(),
            'catatan' => $kegiatan->statusTerkini?->catatan_revisi,
            'editable' => $this->canEdit($kegiatan),
            'k' => [
                'nama' => $kegiatan->nama_kegiatan,
                'kategori' => $kegiatan->kategori?->nama_kategori ?? '—',
                'jenis' => $kegiatan->kategori?->jenisKegiatan?->nama_jenis_kegiatan ?? '—',
                'penyelenggara' => $kegiatan->nilaiIsian('penyelenggara') ?? '—',
                'tahun' => $kegiatan->tanggal_mulai?->year ?? '—',
                'mulai' => $kegiatan->tanggal_mulai?->locale('id')->translatedFormat('j F Y') ?? '—',
                'selesai' => $kegiatan->tanggal_selesai?->locale('id')->translatedFormat('j F Y') ?? '—',
                'status' => ucfirst($kegiatan->statusTerkini?->status ?? 'Belum diajukan'),
            ],
            'anggota' => $kegiatan->anggota->map(fn ($a) => [
                'nama' => $a->user?->mahasiswa?->nama ?? $a->user?->dosen?->nama ?? $a->user?->email ?? '—',
                'status' => $a->user?->role?->nama_role ?? '—',
            ]),
            'dokumen' => $kegiatan->dokumen->map(fn ($d) => [
                'file' => $d->nama_file, 'nama' => $d->nama_file,
                'url' => $this->documentUrl($d->link_dokumen),
                'jenis' => $d->jenisDokumen?->nama_jenis_dokumen ?? $d->tipe_dokumen,
                'keterangan' => null, 'tautan' => $this->documentUrl($d->link_dokumen),
            ]),
        ]);
    }

    private function documentUrl(?string $link): ?string
    {
        if (! $link || $link === '#') {
            return null;
        }
        if (preg_match('~^https?://~i', $link)) {
            return $link;
        }
        if (str_starts_with($link, '/') && ! str_starts_with($link, '//')) {
            return $link;
        }
        if (parse_url($link, PHP_URL_SCHEME) !== null || str_starts_with($link, '//')) {
            return null;
        }

        return Storage::disk('public')->url($link);
    }
}
