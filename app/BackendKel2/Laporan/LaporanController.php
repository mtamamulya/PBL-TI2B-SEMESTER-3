<?php

namespace App\BackendKel2\Laporan;

use App\BackendKel2\Support\RoleGuard;
use App\Http\Controllers\Controller;
use App\Models\Kegiatan;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Mpdf\Mpdf;

class LaporanController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAdmin($request);
        $filters = $this->validatedFilters($request);
        $query = $this->reportQuery($filters);
        $items = (clone $query)->orderByDesc('k.tanggal_mulai')->paginate($filters['per_page'] ?? 15);
        $categories = (clone $query)
            ->selectRaw("COALESCE(kat.nama_kategori, 'Tanpa kategori') as kategori, COUNT(*) as jumlah")
            ->groupBy('kat.nama_kategori')
            ->orderBy('kategori')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Laporan kegiatan berhasil dibuat.',
            'data' => [
                'total_kegiatan' => (clone $query)->count(),
                'per_kategori' => $categories,
                'kegiatan' => $items->items(),
            ],
            'meta' => [
                'current_page' => $items->currentPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'last_page' => $items->lastPage(),
                'filters' => $filters,
            ],
        ]);
    }

    public function export(Request $request)
    {
        $this->authorizeAdmin($request);
        $filters = $this->validatedFilters($request, true);
        $rows = $this->reportQuery($filters)->orderByDesc('k.tanggal_mulai')->get();

        return $filters['format'] === 'xls' ? $this->excel($rows) : $this->pdf($rows, $filters);
    }

    private function validatedFilters(Request $request, bool $withFormat = false): array
    {
        return $request->validate([
            'tahun' => ['nullable', 'integer', 'min:2000', 'max:'.(now()->year + 5)],
            'semester' => ['nullable', 'integer', 'in:1,2'],
            'id_kategori' => ['nullable', 'integer', 'exists:kategori,id_kategori'],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            ...($withFormat ? ['format' => ['required', 'in:xls,pdf']] : []),
        ]);
    }

    private function reportQuery(array $filters): Builder
    {
        return DB::query()->fromSub(Kegiatan::tabelDenganTanggal(), 'k')
            ->leftJoin('kategori as kat', 'kat.id_kategori', '=', 'k.id_kategori')
            ->leftJoin('users as u', 'u.id_user', '=', 'k.id_user')
            ->leftJoin('mahasiswa as m', 'm.id_user', '=', 'u.id_user')
            ->leftJoin('dosen as d', 'd.id_user', '=', 'u.id_user')
            ->select([
                'k.id_kegiatan',
                'k.nama_kegiatan',
                'k.tanggal_mulai',
                'k.tanggal_selesai',
                'kat.nama_kategori as kategori',
            ])
            ->selectRaw('COALESCE(m.nama, d.nama, u.email) as pemilik')
            ->selectSub(
                DB::table('timeline as t')
                    ->select('t.status')
                    ->whereColumn('t.id_kegiatan', 'k.id_kegiatan')
                    ->orderByDesc('t.tanggal_status')
                    ->orderByDesc('t.id_timeline')
                    ->limit(1),
                'status'
            )
            ->when($filters['tahun'] ?? null, fn (Builder $query, $year) => $query->whereYear('k.tanggal_mulai', $year))
            ->when($filters['semester'] ?? null, function (Builder $query, $semester) {
                $query->whereMonth('k.tanggal_mulai', (int) $semester === 1 ? '<=' : '>=', (int) $semester === 1 ? 6 : 7);
            })
            ->when($filters['id_kategori'] ?? null, fn (Builder $query, $id) => $query->where('k.id_kategori', $id))
            ->when($filters['tanggal_mulai'] ?? null, fn (Builder $query, $date) => $query->whereDate('k.tanggal_mulai', '>=', $date))
            ->when($filters['tanggal_selesai'] ?? null, fn (Builder $query, $date) => $query->whereDate('k.tanggal_mulai', '<=', $date));
    }

    private function excel($rows)
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<?mso-application progid="Excel.Sheet"?>'
            .'<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"><Worksheet ss:Name="Laporan"><Table>';
        $data = [['Nama Kegiatan', 'Kategori', 'Pemilik', 'Tanggal Mulai', 'Tanggal Selesai', 'Status']];
        foreach ($rows as $row) {
            $data[] = [$row->nama_kegiatan, $row->kategori, $row->pemilik, $row->tanggal_mulai, $row->tanggal_selesai, $row->status];
        }
        foreach ($data as $line) {
            $xml .= '<Row>';
            foreach ($line as $value) {
                $xml .= '<Cell><Data ss:Type="String">'.htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</Data></Cell>';
            }
            $xml .= '</Row>';
        }
        $xml .= '</Table></Worksheet></Workbook>';

        return response()->streamDownload(fn () => print($xml), 'laporan-kegiatan.xls', [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
        ]);
    }

    private function pdf($rows, array $filters)
    {
        $pdf = new Mpdf(['mode' => 'utf-8', 'format' => 'A4-L']);
        $pdf->WriteHTML(view('backendkel2.laporan.pdf', compact('rows', 'filters'))->render());
        $output = $pdf->Output('', 'S');

        return response()->streamDownload(fn () => print($output), 'laporan-kegiatan.pdf', [
            'Content-Type' => 'application/pdf',
        ]);
    }

    private function authorizeAdmin(Request $request): void
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        RoleGuard::ensure($user, ['admin']);
    }
}
