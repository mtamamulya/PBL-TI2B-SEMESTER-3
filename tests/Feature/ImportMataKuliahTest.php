<?php

namespace Tests\Feature;

use App\Models\User;
use App\Repositories\KurikulumDbStore;
use App\Repositories\KurikulumStore;
use App\Repositories\MataKuliahDbStore;
use App\Repositories\MataKuliahStore;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ImportMataKuliahTest extends TestCase
{
    use RefreshDatabase;

    private int $kurikulumId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(KurikulumStore::class, KurikulumDbStore::class);
        $this->app->bind(MataKuliahStore::class, MataKuliahDbStore::class);
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
        $this->kurikulumId = app(KurikulumDbStore::class)->create(['name' => 'Kurikulum Uji'])['id'];
    }

    private function user(int $role): User
    {
        return User::query()->where('id_role', $role)->firstOrFail();
    }

    private function xlsx(array $baris, ?array $header = null, string $nama = 'mk.xlsx'): UploadedFile
    {
        $header ??= ['Semester', 'Kode MK', 'Nama MK', 'SKS Teori', 'SKS Praktik', 'Jam Teori', 'Jam Praktik'];
        $s = new Spreadsheet;
        $s->getActiveSheet()->setTitle('Data')->fromArray(array_merge([$header], $baris), null, 'A1', true);
        $path = tempnam(sys_get_temp_dir(), 'mk').'.xlsx';
        (new Xlsx($s))->save($path);

        return new UploadedFile($path, $nama, null, null, true);
    }

    private function pratinjau(string $peran, UploadedFile $f)
    {
        return $this->post(route("$peran.kurikulum.mata-kuliah.import.pratinjau"), [
            'kurikulum' => $this->kurikulumId, 'berkas' => $f,
        ]);
    }

    private function jumlahMk(): int
    {
        return DB::table('mata_kuliah')->where('id_kurikulum', $this->kurikulumId)->count();
    }

    public function test_template_bisa_diunduh_admin_dan_kaprodi(): void
    {
        foreach ([1 => 'admin', 2 => 'kaprodi'] as $role => $peran) {
            $this->actingAs($this->user($role))
                ->get(route("$peran.kurikulum.mata-kuliah.import.template"))
                ->assertOk()
                ->assertDownload('template-mata-kuliah.xlsx');
        }
    }

    public function test_dosen_dan_tamu_ditolak(): void
    {
        $this->get(route('admin.kurikulum.mata-kuliah.import'))->assertRedirect();
        $this->actingAs($this->user(3))->get(route('admin.kurikulum.mata-kuliah.import'))->assertForbidden();
    }

    public function test_pratinjau_tidak_menyimpan(): void
    {
        $this->actingAs($this->user(1));
        $this->pratinjau('admin', $this->xlsx([[1, 'IF101', 'Algoritma', 2, 1, 2, 2]]))->assertRedirect();
        $this->assertSame(0, $this->jumlahMk());
    }

    public function test_konfirmasi_hanya_menyimpan_yang_valid_dan_melewati_duplikat(): void
    {
        $this->actingAs($this->user(1));
        app(MataKuliahDbStore::class)->create([
            'kurikulum' => $this->kurikulumId, 'semester' => 1, 'kode' => 'IF101', 'nama' => 'Lama',
            'sks_teori' => 2, 'sks_praktek' => 0, 'jam_teori' => 2, 'jam_praktek' => 0,
        ]);
        $this->pratinjau('admin', $this->xlsx([
            [1, 'IF101', 'Duplikat Lama', 2, 1, 2, 2],
            [1, 'IF102', 'Baru', 2, 1, 2, 2],
            [1, 'IF102', 'Duplikat Berkas', 2, 1, 2, 2],
            [9, 'IF103', 'Semester Salah', 2, 1, 2, 2],
        ]));
        $this->post(route('admin.kurikulum.mata-kuliah.import.simpan'))
            ->assertRedirect(route('admin.kurikulum.mata-kuliah', ['kurikulum' => $this->kurikulumId]));
        $this->assertSame(2, $this->jumlahMk());
        $this->assertDatabaseHas('mata_kuliah', ['kode_mk' => 'IF102', 'id_kurikulum' => $this->kurikulumId]);
        $this->assertDatabaseMissing('mata_kuliah', ['kode_mk' => 'IF103']);
    }

    public function test_header_salah_ditolak(): void
    {
        $this->actingAs($this->user(1));
        $this->pratinjau('admin', $this->xlsx([[1, 'x']], ['A', 'B']))->assertRedirect();
        $this->assertSame(0, $this->jumlahMk());
        $this->post(route('admin.kurikulum.mata-kuliah.import.simpan'))->assertRedirect();
        $this->assertSame(0, $this->jumlahMk());
    }

    public function test_bukan_xlsx_ditolak(): void
    {
        $this->actingAs($this->user(1));
        $f = UploadedFile::fake()->create('mk.csv', 1, 'text/csv');
        $this->pratinjau('admin', $f)->assertSessionHasErrors('berkas');
    }

    public function test_kaprodi_bisa_impor(): void
    {
        $this->actingAs($this->user(2));
        $this->pratinjau('kaprodi', $this->xlsx([[2, 'IF201', 'Struktur Data', 2, 1, 2, 2]]));
        $this->post(route('kaprodi.kurikulum.mata-kuliah.import.simpan'))
            ->assertRedirect(route('kaprodi.kurikulum.mata-kuliah', ['kurikulum' => $this->kurikulumId]));
        $this->assertSame(1, $this->jumlahMk());
    }
}
