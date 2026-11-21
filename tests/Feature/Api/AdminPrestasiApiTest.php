<?php

namespace Tests\Feature\Api;

use App\Models\JenisDokumen;
use App\Models\JenisKegiatan;
use App\Models\Kategori;
use App\Models\Kegiatan;
use App\Models\Prestasi;
use App\Models\Timeline;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminPrestasiApiTest extends TestCase
{
    use RefreshDatabase;

    private const API = '/api/v1/admin/prestasi';

    private function pengguna(string $role): User
    {
        $roleIds = [
            'admin' => 1,
            'kaprodi' => 2,
            'dosen' => 3,
            'mahasiswa' => 4,
        ];

        $id = $roleIds[$role];

        DB::table('roles')->updateOrInsert(
            ['id_role' => $id],
            ['nama_role' => $role]
        );

        return User::create([
            'id_role' => $id,
            'email' => uniqid().'@test.local',
            'password' => 'password',
            'status' => true,
        ]);
    }

    private function sebagai(string $role = 'admin'): static
    {
        return $this->actingAs(
            $this->pengguna($role),
            'sanctum'
        );
    }

    private function buatPrestasi(): Prestasi
    {
        $pemilik = $this->pengguna('mahasiswa');

        return Prestasi::create([
            'id_user' => $pemilik->id_user,
            'created_by' => $pemilik->id_user,
            'id_kegiatan' => null,
            'jenis_prestasi' => 'akademik',
            'bidang_lomba' => 'Teknologi Informasi',
            'nama_lomba' => 'Lomba Pemrograman',
            'penyelenggara' => 'Politeknik',
            'lokasi' => 'Semarang',
            'capaian' => 'Juara 1',
            'tingkat_kejuaraan' => 'nasional',
            'tanggal_mulai' => '2026-11-01',
            'tanggal_selesai' => '2026-11-05',
            'bentuk_partisipasi' => 'individu',
        ]);
    }

    public function test_api_mewajibkan_login(): void
    {
        $this->getJson(self::API)
            ->assertUnauthorized();

        $this->getJson(self::API.'/1')
            ->assertUnauthorized();
    }

    public function test_non_admin_dilarang_mengakses(): void
    {
        $prestasi = $this->buatPrestasi();

        foreach (['mahasiswa', 'dosen', 'kaprodi'] as $role) {
            $this->sebagai($role)
                ->getJson(self::API)
                ->assertForbidden();

            $this->getJson(self::API.'/'.$prestasi->id_prestasi)
                ->assertForbidden();
        }
    }

    public function test_admin_dapat_melihat_daftar(): void
    {
        $prestasi = $this->buatPrestasi();

        $this->sebagai()
            ->getJson(self::API.'?per_page=10')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath(
                'data.0.id_prestasi',
                $prestasi->id_prestasi
            );
    }

    public function test_admin_dapat_melihat_detail(): void
    {
        $prestasi = $this->buatPrestasi();

        $this->sebagai()
            ->getJson(self::API.'/'.$prestasi->id_prestasi)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.id_prestasi',
                $prestasi->id_prestasi
            );
    }

    public function test_prestasi_tidak_ditemukan(): void
    {
        $this->sebagai()
            ->getJson(self::API.'/999999')
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_parameter_paginasi_tidak_valid(): void
    {
        $this->sebagai()
            ->getJson(self::API.'?per_page=500')
            ->assertUnprocessable();
    }

    public function test_opsi_mewajibkan_login(): void
    {
        $this->getJson(self::API.'/opsi')
            ->assertUnauthorized();
    }

    public function test_opsi_hanya_boleh_diakses_admin(): void
    {
        $this->sebagai('mahasiswa')
            ->getJson(self::API.'/opsi')
            ->assertForbidden();
    }

    public function test_admin_dapat_mengambil_opsi_formulir(): void
    {
        $this->sebagai()
            ->getJson(self::API.'/opsi')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'kegiatanOptions',
                    'kegiatanList',
                    'pemilikList',
                    'tingkatList',
                    'capaianList',
                    'jenisList',
                    'jenisDokList',
                    'daftarMahasiswa',
                    'anggotaOptions',
                    'peranList',
                    'jenisDokumenOptions',
                ],
            ]);
    }

    public function test_post_mewajibkan_login(): void
    {
        $this->postJson(self::API, [])
            ->assertUnauthorized();
    }

    public function test_non_admin_tidak_bisa_menambah_prestasi(): void
    {
        $this->sebagai('mahasiswa')
            ->postJson(self::API, [])
            ->assertForbidden();
    }

    public function test_admin_berhasil_menambahkan_prestasi_mandiri(): void
    {
        $pemilik = $this->pengguna('mahasiswa');

        $payload = [
            'sumber' => 'mandiri',
            'id_user' => $pemilik->id_user,
            'nama_lomba' => 'Lomba Pemrograman Nasional',
            'penyelenggara' => 'Politeknik Negeri',
            'lokasi' => 'Semarang',
            'tingkat' => 'Nasional',
            'capaian' => 'Juara 1',
            'jenis' => 'Akademik',
            'kategori' => 'Pemrograman',
            'tanggal_mulai' => '2026-11-01',
            'tanggal_selesai' => '2026-11-05',
            'bentuk' => 'Individu',
            'deskripsi' => 'Prestasi tingkat nasional',
            'dokumen' => [
                [
                    'url' => 'https://drive.google.com/file/d/123456789/view',
                    'jenis' => 'Sertifikat',
                ],
            ],
        ];

        $this->sebagai()
            ->postJson(self::API, $payload)
            ->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('prestasi', [
            'id_user' => $pemilik->id_user,
            'nama_lomba' => 'Lomba Pemrograman Nasional',
            'capaian' => 'Juara 1',
            'jenis_prestasi' => 'akademik',
        ]);
    }

    public function test_post_menolak_data_tidak_valid(): void
    {
        $this->sebagai()
            ->postJson(self::API, [
                'sumber' => 'mandiri',
                'nama_lomba' => '',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'id_user',
                'nama_lomba',
                'penyelenggara',
            ]);
    }

    private function payloadUpdate(int $idUser): array
    {
        return [
            'sumber' => 'mandiri',
            'id_user' => $idUser,
            'nama_lomba' => 'Lomba Pemrograman Diperbarui',
            'penyelenggara' => 'Politeknik Negeri',
            'lokasi' => 'Semarang',
            'tingkat' => 'Nasional',
            'capaian' => 'Juara 2',
            'jenis' => 'Akademik',
            'kategori' => 'Pemrograman',
            'tanggal_mulai' => '2026-11-01',
            'tanggal_selesai' => '2026-11-05',
            'bentuk' => 'Individu',
            'deskripsi' => 'Data telah diperbarui',
            'dokumen' => [
                [
                    'url' => 'https://drive.google.com/file/d/987654321/view',
                    'jenis' => 'Sertifikat',
                ],
            ],
        ];
    }

    public function test_update_mewajibkan_login(): void
    {
        $this->putJson(self::API.'/1', [])
            ->assertUnauthorized();
    }

    public function test_non_admin_tidak_bisa_update(): void
    {
        $this->sebagai('mahasiswa')
            ->putJson(self::API.'/1', [])
            ->assertForbidden();
    }

    public function test_admin_berhasil_update_prestasi(): void
    {
        $prestasi = $this->buatPrestasi();

        $this->sebagai()
            ->putJson(
                self::API.'/'.$prestasi->id_prestasi,
                $this->payloadUpdate($prestasi->id_user)
            )
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('prestasi', [
            'id_prestasi' => $prestasi->id_prestasi,
            'nama_lomba' => 'Lomba Pemrograman Diperbarui',
            'capaian' => 'Juara 2',
        ]);
    }

    public function test_delete_mewajibkan_login(): void
    {
        $this->deleteJson(self::API.'/1')
            ->assertUnauthorized();
    }

    public function test_non_admin_tidak_bisa_delete(): void
    {
        $this->sebagai('dosen')
            ->deleteJson(self::API.'/1')
            ->assertForbidden();
    }

    public function test_admin_berhasil_hapus_prestasi(): void
    {
        $prestasi = $this->buatPrestasi();

        $this->sebagai()
            ->deleteJson(self::API.'/'.$prestasi->id_prestasi)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('prestasi', [
            'id_prestasi' => $prestasi->id_prestasi,
        ]);
    }

    public function test_admin_dapat_menambah_prestasi_tim_mandiri(): void
    {
        $pemilik = $this->pengguna('mahasiswa');
        $rekan = $this->pengguna('dosen');

        $payload = $this->payloadUpdate($pemilik->id_user);
        $payload['bentuk'] = 'Tim';
        $payload['anggota'] = [
            [
                'id_user' => $pemilik->id_user,
                'nama' => 'Pemilik Prestasi',
                'peran' => 'Ketua',
            ],
            [
                'id_user' => $rekan->id_user,
                'nama' => 'Anggota Tim',
                'peran' => 'Anggota',
            ],
        ];

        $this->sebagai()
            ->postJson(self::API, $payload)
            ->assertCreated();

        $prestasi = Prestasi::where(
            'id_user',
            $pemilik->id_user
        )->firstOrFail();

        $this->assertDatabaseHas('anggota_kegiatan', [
            'id_prestasi' => $prestasi->id_prestasi,
            'id_user' => $pemilik->id_user,
            'peran' => 'ketua',
        ]);

        $this->assertDatabaseHas('anggota_kegiatan', [
            'id_prestasi' => $prestasi->id_prestasi,
            'id_user' => $rekan->id_user,
            'peran' => 'anggota',
        ]);
    }

    public function test_update_mempertahankan_bukti_lama(): void
    {
        $prestasi = $this->buatPrestasi();

        $jenis = JenisDokumen::firstOrCreate([
            'nama_jenis_dokumen' => 'Sertifikat',
        ]);

        $bukti = $prestasi->buktiPrestasi()->create([
            'id_jenis_dokumen' => $jenis->id_jenis_dokumen,
            'link' => 'https://drive.google.com/file/d/bukti-lama/view',
            'nama_file' => 'Sertifikat Lama',
        ]);

        $payload = $this->payloadUpdate($prestasi->id_user);
        $payload['dokumen'] = [];
        $payload['dokumen_pertahankan'] = [
            $bukti->id_bukti_prestasi,
        ];

        $this->sebagai()
            ->putJson(
                self::API.'/'.$prestasi->id_prestasi,
                $payload
            )
            ->assertOk();

        $this->assertDatabaseHas('bukti_prestasi', [
            'id_bukti_prestasi' => $bukti->id_bukti_prestasi,
            'id_prestasi' => $prestasi->id_prestasi,
        ]);
    }

    private function buatKegiatanPrestasi(
        string $status = 'diterima',
        string $nama = 'Kompetisi Robotika Nasional'
    ): Kegiatan {
        $jenis = JenisKegiatan::firstOrCreate([
            'nama_jenis_kegiatan' => 'Kegiatan Mahasiswa',
        ]);

        $kategori = Kategori::firstOrCreate(
            [
                'id_jenis_kegiatan' => $jenis->id_jenis_kegiatan,
                'nama_kategori' => 'Lomba',
            ],
            [
                'kebutuhan' => 'surat',
                'isi_surat' => 'Surat tugas kegiatan',
                'lampiran_peserta' => true,
            ]
        );

        $pemilik = $this->pengguna('mahasiswa');

        $kegiatan = Kegiatan::create([
            'id_kategori' => $kategori->id_kategori,
            'id_user' => $pemilik->id_user,
            'nama_kegiatan' => $nama,
        ]);

        Timeline::create([
            'id_kegiatan' => $kegiatan->id_kegiatan,
            'status' => $status,
            'tanggal_status' => now()->subMinute(),
        ]);

        return $kegiatan;
    }

    private function payloadKegiatan(Kegiatan $kegiatan): array
    {
        $payload = $this->payloadUpdate($kegiatan->id_user);

        $payload['sumber'] = 'sebelumnya';
        $payload['id_kegiatan'] = $kegiatan->id_kegiatan;
        $payload['kegiatan'] = $kegiatan->nama_kegiatan;

        return $payload;
    }

    public function test_admin_dapat_menambah_prestasi_dari_kegiatan_diterima(): void
    {
        $kegiatan = $this->buatKegiatanPrestasi();

        $payload = $this->payloadKegiatan($kegiatan);

        // Nama dari kegiatan tidak boleh dimanipulasi melalui request.
        $payload['nama_lomba'] = 'Nama Palsu';

        $this->sebagai()
            ->postJson(self::API, $payload)
            ->assertCreated();

        $this->assertDatabaseHas('prestasi', [
            'id_kegiatan' => $kegiatan->id_kegiatan,
            'id_user' => $kegiatan->id_user,
            'nama_lomba' => $kegiatan->nama_kegiatan,
        ]);
    }

    public function test_kegiatan_belum_diterima_tidak_boleh_digunakan(): void
    {
        $kegiatan = $this->buatKegiatanPrestasi('diajukan');

        $this->sebagai()
            ->postJson(self::API, $this->payloadKegiatan($kegiatan))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['kegiatan']);
    }

    public function test_pemilik_harus_terlibat_dalam_kegiatan(): void
    {
        $kegiatan = $this->buatKegiatanPrestasi();
        $orangLain = $this->pengguna('mahasiswa');

        $payload = $this->payloadKegiatan($kegiatan);
        $payload['id_user'] = $orangLain->id_user;

        $this->sebagai()
            ->postJson(self::API, $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['kegiatan']);
    }

    public function test_opsi_hanya_menampilkan_kegiatan_diterima(): void
    {
        $diterima = $this->buatKegiatanPrestasi(
            'diterima',
            'Kompetisi Disetujui'
        );

        $this->buatKegiatanPrestasi(
            'diajukan',
            'Kompetisi Menunggu'
        );

        $this->sebagai()
            ->getJson(self::API.'/opsi')
            ->assertOk()
            ->assertJsonCount(1, 'data.kegiatanList')
            ->assertJsonPath(
                'data.kegiatanList.0.id',
                $diterima->id_kegiatan
            );
    }

    public function test_prestasi_tim_menolak_lebih_dari_satu_ketua(): void
    {
        $pemilik = $this->pengguna('mahasiswa');
        $rekan = $this->pengguna('dosen');

        $payload = $this->payloadUpdate($pemilik->id_user);
        $payload['bentuk'] = 'Tim';

        $payload['anggota'] = [
            [
                'id_user' => $pemilik->id_user,
                'nama' => 'Pemilik',
                'peran' => 'Ketua',
            ],
            [
                'id_user' => $rekan->id_user,
                'nama' => 'Rekan',
                'peran' => 'Ketua',
            ],
        ];

        $this->sebagai()
            ->postJson(self::API, $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['anggota']);
    }
}
