<?php

namespace Tests\Feature;

use App\Models\Prestasi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MahasiswaPrestasiDriveLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insert([
            'id_role' => 4,
            'nama_role' => 'mahasiswa',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $id = DB::table('users')->insertGetId([
            'id_role' => 4,
            'email' => 'prestasi-student@test.local',
            'password' => bcrypt('password'),
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAs(User::findOrFail($id));
    }

    private function dataPrestasi(array $dokumen): array
    {
        return [
            'sumber' => 'mandiri',
            'nama_lomba' => 'Kompetisi Inovasi',
            'penyelenggara' => 'Penyelenggara',
            'lokasi' => 'Semarang',
            'tingkat' => 'Nasional',
            'capaian' => 'Juara 1',
            'jenis' => 'Akademik',
            'tanggal_mulai' => '2026-01-01',
            'tanggal_selesai' => '2026-01-02',
            'bentuk' => 'Individu',
            'dokumen' => $dokumen,
        ];
    }

    public function test_prestasi_form_uses_drive_links_instead_of_file_uploads(): void
    {
        $this->get(route('mahasiswa.prestasi.tambah'))
            ->assertOk()
            ->assertSee('Google Drive atau OneDrive')
            ->assertSee('Tambah')
            ->assertSee('id="capaian" name="capaian"', false)
            ->assertDontSee('<select id="capaian"', false)
            ->assertDontSee('type="file"', false);
    }

    public function test_prestasi_accepts_a_custom_capaian(): void
    {
        $this->post(route('mahasiswa.prestasi.simpan'), array_merge(
            $this->dataPrestasi([
                ['url' => 'https://drive.google.com/file/d/example/view', 'jenis' => 'Sertifikat'],
            ]),
            ['capaian' => 'Best Paper']
        ))->assertRedirect(route('mahasiswa.prestasi'))
            ->assertSessionHasNoErrors();
    }

    public function test_prestasi_form_only_lists_eligible_activities(): void
    {
        $jenis = DB::table('jenis_kegiatan')->where('nama_jenis_kegiatan', 'Kegiatan Mahasiswa')->value('id_jenis_kegiatan')
            ?? DB::table('jenis_kegiatan')->insertGetId(['nama_jenis_kegiatan' => 'Kegiatan Mahasiswa'], 'id_jenis_kegiatan');
        $kategori = DB::table('kategori')->insertGetId(['id_jenis_kegiatan' => $jenis, 'nama_kategori' => 'Lomba'], 'id_kategori');
        foreach (['diterima', 'diajukan'] as $status) {
            $id = DB::table('kegiatan')->insertGetId(['id_user' => auth()->id(), 'id_kategori' => $kategori, 'nama_kegiatan' => 'Lomba '.$status], 'id_kegiatan');
            DB::table('timeline')->insert(['id_kegiatan' => $id, 'status' => $status, 'tanggal_status' => now()]);
        }

        $this->get(route('mahasiswa.prestasi.tambah'))->assertOk()
            ->assertSee('Lomba diterima • Lomba')->assertDontSee('Lomba diajukan • Lomba');
        $data = $this->dataPrestasi([['url' => 'https://drive.google.com/file/d/example/view', 'jenis' => 'Sertifikat']]);
        $this->post(route('mahasiswa.prestasi.simpan'), array_merge($data, [
            'sumber' => 'sebelumnya', 'kegiatan' => 'Lomba diajukan', 'id_kegiatan' => $id,
        ]))->assertSessionHasErrors('kegiatan');
    }

    public function test_prestasi_edit_form_keeps_existing_documents_and_new_types(): void
    {
        $prestasi = Prestasi::create([
            'id_user' => auth()->id(),
            'created_by' => auth()->id(),
            'capaian' => 'Juara 1',
            'jenis_prestasi' => 'akademik',
            'nama_lomba' => 'Lomba Inovasi Internet of Things',
            'penyelenggara' => 'Forum Inovasi Teknologi',
            'lokasi' => 'Semarang',
            'tingkat_kejuaraan' => 'nasional',
            'tanggal_mulai' => '2026-09-22',
            'tanggal_selesai' => '2026-09-24',
            'bentuk_partisipasi' => 'individu',
        ]);
        $idJenisDokumen = DB::table('jenis_dokumen')->insertGetId([
            'nama_jenis_dokumen' => 'Sertifikat',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('bukti_prestasi')->insert([
            [
                'id_prestasi' => $prestasi->getKey(),
                'id_jenis_dokumen' => $idJenisDokumen,
                'link' => 'https://drive.google.com/file/d/sertifikat/view',
                'nama_file' => 'sertifikat-iot.pdf',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id_prestasi' => $prestasi->getKey(),
                'id_jenis_dokumen' => $idJenisDokumen,
                'link' => 'https://drive.google.com/file/d/foto/view',
                'nama_file' => 'foto-iot.jpg',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->get(route('mahasiswa.prestasi.edit', $prestasi->getKey()))
            ->assertOk()
            ->assertSee('Dokumentasi kegiatan')
            ->assertSee('sertifikat-iot.pdf')
            ->assertSee('foto-iot.jpg');
    }

    public function test_eye_action_lists_proof_links_without_image_or_file_previews(): void
    {
        $this->get(route('mahasiswa.prestasi'))
            ->assertOk()
            ->assertSeeInOrder(['Informasi prestasi', 'Tautan bukti prestasi'])
            ->assertSee('max-w-xl', false)
            ->assertSee('Belum ada tautan bukti yang ditambahkan.')
            ->assertDontSee('title="Pratinjau dokumen Google Drive"', false)
            ->assertDontSee('Pratinjau foto kegiatan');
    }

    public function test_prestasi_accepts_a_google_drive_sharing_link(): void
    {
        $this->post(route('mahasiswa.prestasi.simpan'), $this->dataPrestasi([
            ['url' => 'https://drive.google.com/file/d/example/view', 'jenis' => 'Sertifikat'],
        ]))->assertRedirect(route('mahasiswa.prestasi'))
            ->assertSessionHasNoErrors();
    }

    public function test_prestasi_accepts_a_onedrive_sharing_link(): void
    {
        $this->post(route('mahasiswa.prestasi.simpan'), $this->dataPrestasi([
            ['url' => 'https://1drv.ms/u/s!example', 'jenis' => 'Sertifikat'],
        ]))->assertRedirect(route('mahasiswa.prestasi'))
            ->assertSessionHasNoErrors();
    }

    public function test_prestasi_rejects_non_google_drive_links(): void
    {
        $this->from(route('mahasiswa.prestasi.tambah'))
            ->post(route('mahasiswa.prestasi.simpan'), $this->dataPrestasi([
                ['url' => 'https://example.com/certificate.pdf', 'jenis' => 'Sertifikat'],
            ]))->assertRedirect(route('mahasiswa.prestasi.tambah'))
            ->assertSessionHasErrors(['dokumen.0.url']);
    }

    public function test_prestasi_requires_a_document_type_for_each_drive_link(): void
    {
        $this->from(route('mahasiswa.prestasi.tambah'))
            ->post(route('mahasiswa.prestasi.simpan'), $this->dataPrestasi([
                ['url' => 'https://drive.google.com/file/d/example/view', 'jenis' => ''],
            ]))->assertRedirect(route('mahasiswa.prestasi.tambah'))
            ->assertSessionHasErrors(['dokumen.0.jenis']);
    }

    public function test_prestasi_requires_at_least_one_proof_link(): void
    {
        $this->from(route('mahasiswa.prestasi.tambah'))
            ->post(route('mahasiswa.prestasi.simpan'), $this->dataPrestasi([]))
            ->assertRedirect(route('mahasiswa.prestasi.tambah'))
            ->assertSessionHasErrors(['dokumen']);
    }
}
