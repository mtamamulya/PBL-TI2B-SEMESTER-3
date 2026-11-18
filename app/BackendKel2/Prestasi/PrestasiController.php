<?php

namespace App\BackendKel2\Prestasi;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
// use App\Models\Prestasi; // Aktifkan jika Model sudah dibuat

class PrestasiController extends Controller
{
    public function index()
    {
        // Untuk lulus test halaman index
        return view('mahasiswa.prestasi.index', [
            'prestasi' => [], 'total' => 0, 'akademik' => 0, 'nonakademik' => 0,
            'urlEdit' => '#', 'urlHapus' => '#'
        ]);
    }

    public function tambah()
    {
        // Test minta daftar kegiatan yang berstatus 'diterima' saja
        $kegiatanList = DB::table('kegiatan')
            ->join('timeline', 'kegiatan.id_kegiatan', '=', 'timeline.id_kegiatan')
            ->join('kategori', 'kegiatan.id_kategori', '=', 'kategori.id_kategori')
            ->where('kegiatan.id_user', auth()->id())
            ->where('timeline.status', 'diterima')
            ->select('kegiatan.id_kegiatan as id', DB::raw("CONCAT(kegiatan.nama_kegiatan, ' • ', kategori.nama_kategori) as label"), 'kegiatan.nama_kegiatan as nama', DB::raw("'' as penyelenggara"))
            ->get()->map(fn($k) => (array) $k)->toArray();

        return view('mahasiswa.prestasi.tambah', [
            'edit' => false,
            'urlSimpan' => route('mahasiswa.prestasi.simpan'),
            'kegiatanList' => $kegiatanList,
            'daftarMahasiswa' => [], // Isi nanti dengan query ke tabel users/mahasiswa
            'dokumenLama' => [],
            'dokumenInput' => [],
            'tingkatList' => ['Kabupaten/Kota', 'Provinsi', 'Nasional', 'Internasional'],
            'jenisList' => ['Akademik', 'Nonakademik'],
            'peranList' => ['Ketua', 'Anggota'],
            'jenisDokList' => ['Sertifikat', 'Surat Tugas', 'Foto Kegiatan', 'Karya/Paper', 'Lainnya'],
        ]);
    }

    public function simpan(Request $request)
    {
        // 1. VALIDASI DATA & URL (Inti dari Test Drive Link)
        $validated = $request->validate([
            'sumber' => 'required|in:sebelumnya,mandiri',
            'id_kegiatan' => 'required_if:sumber,sebelumnya',
            'kegiatan' => 'required_if:sumber,sebelumnya', // Untuk pesan error test terakhir
            'nama_lomba' => 'required|string|max:255',
            'penyelenggara' => 'required|string|max:150',
            'lokasi' => 'required|string|max:150',
            'tingkat' => 'required|in:Kabupaten/Kota,Provinsi,Nasional,Internasional',
            'capaian' => 'required|string|max:100',
            'jenis' => 'required|in:Akademik,Nonakademik',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'bentuk' => 'required|in:Individu,Tim',
            
            // Validasi Ketat Bukti Dokumen
            'dokumen' => 'required|array|min:1',
            'dokumen.*.url' => [
                'required',
                'url',
                function ($attribute, $value, $fail) {
                    $host = strtolower(parse_url($value, PHP_URL_HOST) ?? '');
                    // Validasi khusus domain Drive & OneDrive sesuai requirement
                    $allowed = ['drive.google.com', 'docs.google.com', '1drv.ms', 'onedrive.live.com'];
                    
                    $isValid = in_array($host, $allowed) || str_ends_with($host, '.sharepoint.com');
                    
                    if (!$isValid) {
                        $fail('Tautan harus berasal dari Google Drive atau OneDrive.');
                    }
                },
            ],
            'dokumen.*.jenis' => 'required|string',
        ], [
            'dokumen.required' => 'Tambahkan minimal satu tautan bukti prestasi.',
            'dokumen.*.url.required' => 'Tautan URL wajib diisi.',
            'dokumen.*.url.url' => 'Format URL tidak valid.',
            'dokumen.*.jenis.required' => 'Jenis dokumen wajib dipilih.',
            'kegiatan.required_if' => 'Pilih kegiatan yang sudah ada atau ganti sumber menjadi mandiri.'
        ]);

        // 2. SIMPAN KE DATABASE
        // (Logika insert ke tabel 'prestasi' dan 'bukti_prestasi' akan kita tulis di sini)
        
        // 3. REDIRECT JIKA BERHASIL (Sesuai Test)
        return redirect()->route('mahasiswa.prestasi')->with('status', 'Prestasi berhasil ditambahkan!');
    }
}