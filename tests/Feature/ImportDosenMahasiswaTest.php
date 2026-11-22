<?php

namespace Tests\Feature;

use App\Models\User;
use App\Repositories\DosenDbStore;
use App\Repositories\DosenStore;
use App\Repositories\MahasiswaDbStore;
use App\Repositories\MahasiswaStore;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ImportDosenMahasiswaTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER_DOSEN = ['Nama Lengkap', 'NIP', 'Email', 'Role', 'Status', 'Pangkat', 'Golongan', 'Nomor SK', 'Tanggal SK'];

    private const HEADER_MHS = ['NIM', 'Nama Lengkap', 'Email', 'Status'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(DosenStore::class, DosenDbStore::class);
        $this->app->bind(MahasiswaStore::class, MahasiswaDbStore::class);
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
    }

    private function user(int $role): User
    {
        return User::query()->where('id_role', $role)->firstOrFail();
    }

    private function xlsx(array $header, array $baris): UploadedFile
    {
        $s = new Spreadsheet;
        $s->getActiveSheet()->setTitle('Data')->fromArray(array_merge([$header], $baris), null, 'A1', true);
        $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        (new Xlsx($s))->save($path);

        return new UploadedFile($path, 'data.xlsx', null, null, true);
    }

    private function barisDosen(string $nip, string $email, string $nama = 'Dosen Uji'): array
    {
        $store = app(DosenStore::class);

        return [$nama, $nip, $email, 'dosen', 'aktif', $store->opsiPangkat()[0], $store->opsiGolongan()[0], '1042/UN/KP/2022', '2022-01-12'];
    }

    private function pratinjau(string $jenis, UploadedFile $f)
    {
        return $this->post(route('admin.impor.pratinjau', ['jenis' => $jenis]), ['berkas' => $f]);
    }

    public function test_template_bisa_diunduh_admin(): void
    {
        $this->actingAs($this->user(1));
        foreach (['dosen' => 'template-dosen.xlsx', 'mahasiswa' => 'template-mahasiswa.xlsx'] as $jenis => $nama) {
            $this->get(route('admin.impor.template', ['jenis' => $jenis]))->assertOk()->assertDownload($nama);
        }
    }

    public function test_kaprodi_dan_tamu_ditolak(): void
    {
        $this->get(route('admin.impor.form', ['jenis' => 'dosen']))->assertRedirect();
        $this->actingAs($this->user(2))->get(route('admin.impor.form', ['jenis' => 'dosen']))->assertForbidden();
    }

    public function test_jenis_tak_dikenal_404(): void
    {
        $this->actingAs($this->user(1))->get('/admin/impor/lainnya')->assertNotFound();
    }

    public function test_dosen_pratinjau_tidak_menyimpan_lalu_konfirmasi_menyimpan_valid_saja(): void
    {
        $this->actingAs($this->user(1));
        $berkas = fn () => $this->xlsx(self::HEADER_DOSEN, [
            $this->barisDosen('198501012010', 'baru.satu@polines.ac.id'),
            $this->barisDosen('198501012010', 'baru.dua@polines.ac.id'),       // NIP kembar di berkas -> dilewati
            $this->barisDosen('abc', 'baru.tiga@polines.ac.id'),              // NIP salah -> salah
        ]);

        $this->pratinjau('dosen', $berkas())->assertRedirect(route('admin.impor.form', ['jenis' => 'dosen']));
        $this->assertDatabaseMissing('dosen', ['nip' => '198501012010']);

        $this->post(route('admin.impor.simpan', ['jenis' => 'dosen']))->assertRedirect(route('admin.dosen'));
        $this->assertDatabaseHas('dosen', ['nip' => '198501012010']);
        $this->assertSame(1, DB::table('dosen')->where('nip', '198501012010')->count());
        $this->assertDatabaseMissing('users', ['email' => 'baru.dua@polines.ac.id']);

        $hash = DB::table('users')->where('email', 'baru.satu@polines.ac.id')->value('password');
        $this->assertTrue(Hash::check('198501012010', $hash), 'Sandi awal harus sama dengan NIP.');
    }

    public function test_dosen_yang_sudah_ada_dilewati(): void
    {
        $this->actingAs($this->user(1));
        $this->pratinjau('dosen', $this->xlsx(self::HEADER_DOSEN, [$this->barisDosen('198501012010', 'satu@polines.ac.id')]));
        $this->post(route('admin.impor.simpan', ['jenis' => 'dosen']));

        $this->pratinjau('dosen', $this->xlsx(self::HEADER_DOSEN, [$this->barisDosen('198501012010', 'lain@polines.ac.id')]));
        $this->post(route('admin.impor.simpan', ['jenis' => 'dosen']));
        $this->assertSame(1, DB::table('dosen')->where('nip', '198501012010')->count());
    }

    public function test_header_salah_ditolak(): void
    {
        $this->actingAs($this->user(1));
        $this->pratinjau('mahasiswa', $this->xlsx(['A', 'B'], [[1, 2]]))->assertSessionHasErrors('berkas');
    }

    public function test_mahasiswa_impor_dengan_sandi_nim_dan_angkatan_dari_nim(): void
    {
        $this->actingAs($this->user(1));
        $this->pratinjau('mahasiswa', $this->xlsx(self::HEADER_MHS, [
            ['4.33.25.1.09', 'Rina Uji', 'rina.uji@mhs.polines.ac.id', 'Aktif'],
            ['4.33.25.1.09', 'Kembar', 'kembar@mhs.polines.ac.id', 'Aktif'],     // NIM kembar -> dilewati
            ['salah', 'NIM Salah', 'nimsalah@mhs.polines.ac.id', 'Aktif'],       // salah
        ]));
        $this->assertDatabaseMissing('mahasiswa', ['nim' => '4.33.25.1.09']);

        $this->post(route('admin.impor.simpan', ['jenis' => 'mahasiswa']))->assertRedirect(route('admin.mahasiswa'));
        $this->assertDatabaseHas('mahasiswa', ['nim' => '4.33.25.1.09', 'tahun_masuk' => 2025]);
        $this->assertSame(1, DB::table('mahasiswa')->where('nim', '4.33.25.1.09')->count());

        $hash = DB::table('users')->where('email', 'rina.uji@mhs.polines.ac.id')->value('password');
        $this->assertTrue(Hash::check('4.33.25.1.09', $hash), 'Sandi awal harus sama dengan NIM.');
    }

    public function test_bukan_xlsx_ditolak(): void
    {
        $this->actingAs($this->user(1));
        $this->pratinjau('dosen', UploadedFile::fake()->create('d.csv', 1, 'text/csv'))->assertSessionHasErrors('berkas');
    }
}
