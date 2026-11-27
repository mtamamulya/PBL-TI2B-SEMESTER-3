<?php

namespace Tests\Feature\BackendKel3;

use App\Mail\PasswordResetOtp;
use App\Models\Dosen;
use App\Models\HistoriPangkatGolongan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfilTest extends TestCase
{
    use RefreshDatabase;

    private function buatUserDosen(string $email = 'dosen.uji@polines.ac.id'): User
    {
        DB::table('roles')->insertOrIgnore([
            'id_role' => 2,
            'nama_role' => 'dosen',
        ]);

        return User::factory()->create([
            'id_role' => 2,
            'email' => $email,
            'status' => true,
        ]);
    }

    public function test_dosen_mengubah_sandi_dengan_otp_email_melalui_sesi_profil(): void
    {
        Mail::fake();
        $user = $this->buatUserDosen();
        $url = '/api/v1/dosen/profil/ganti-sandi';
        $this->actingAs($user, 'web')->withHeader('Origin', 'http://localhost')
            ->withSession(['api_token' => $user->createToken('web')->plainTextToken]);
        $this->postJson("$url/kirim-otp")->assertOk();
        $mail = Mail::sent(PasswordResetOtp::class)->sole();
        $this->assertTrue($mail->hasTo($user->email));
        $password = 'SandiDosenBaru26!';
        $data = ['password' => $password, 'password_confirmation' => $password];
        $otpId = DB::table('otp_codes')->where('id_user', $user->getKey())->value('id_otp_code');
        $this->postJson("$url/simpan", $data + ['otp_id' => $otpId])->assertUnprocessable();
        $this->postJson("$url/verifikasi", ['otp' => $mail->code])->assertOk()->assertJsonPath('data.otp_id', $otpId);
        $response = $this->postJson("$url/simpan", $data + ['otp_id' => $otpId])->assertOk();
        $response->assertSessionHas('api_token', $response->json('data.token'));
        $this->assertTrue(Hash::check($password, $user->fresh()->password));
        $this->postJson("$url/simpan", $data + ['otp_id' => $otpId])->assertUnprocessable();
    }

    public function test_dosen_dapat_melihat_profil_dan_pangkat_terakhir(): void
    {
        $user = $this->buatUserDosen('dosen.uji@polines.ac.id');
        $dosen = Dosen::create([
            'id_user' => $user->id_user,
            'nip' => '198501012010121001',
            'nama' => 'Dr. Budi Santoso, M.Kom.',
        ]);

        HistoriPangkatGolongan::create([
            'id_dosen' => $dosen->id_dosen,
            'pangkat' => 'Asisten Ahli',
            'golongan' => '3B',
            'nomor_sk' => 'SK/001/2020',
            'tanggal_sk' => '2020-05-01',
        ]);

        HistoriPangkatGolongan::create([
            'id_dosen' => $dosen->id_dosen,
            'pangkat' => 'Lektor',
            'golongan' => '3C',
            'nomor_sk' => 'SK/099/2024',
            'tanggal_sk' => '2024-10-01',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/dosen/profil');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id_dosen' => $dosen->id_dosen,
                    'nama' => 'Dr. Budi Santoso, M.Kom.',
                    'nip' => '198501012010121001',
                    'email' => 'dosen.uji@polines.ac.id',
                    'pangkat_terakhir' => [
                        'pangkat' => 'Lektor',
                        'golongan' => '3C',
                        'nomor_sk' => 'SK/099/2024',
                    ],
                ],
            ]);
    }

    public function test_dosen_dapat_mengubah_nama_dan_field_lain_diabaikan(): void
    {
        $user = $this->buatUserDosen('dosen.edit@polines.ac.id');
        $dosen = Dosen::create([
            'id_user' => $user->id_user,
            'nip' => '199002022015041002',
            'nama' => 'Nama Lama',
        ]);

        Sanctum::actingAs($user);

        $response = $this->patchJson('/api/v1/dosen/profil', [
            'nama' => 'Nama Baru Gelar Baru',
            'nip' => '999999999999999999',
            'id_dosen' => 99999,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.nama', 'Nama Baru Gelar Baru')
            ->assertJsonPath('data.nip', '199002022015041002')
            ->assertJsonPath('data.id_dosen', $dosen->id_dosen);
    }

    public function test_riwayat_pangkat_diurutkan_dari_terbaru_dan_kosong_mengembalikan_array_kosong(): void
    {
        $user = $this->buatUserDosen('dosen.kosong@polines.ac.id');
        $dosen = Dosen::create([
            'id_user' => $user->id_user,
            'nip' => '199203032018031003',
            'nama' => 'Dosen Tanpa Riwayat',
        ]);

        Sanctum::actingAs($user);

        // Cek saat riwayat kosong
        $this->getJson('/api/v1/dosen/riwayat-pangkat')
            ->assertStatus(200)
            ->assertExactJson([
                'success' => true,
                'message' => 'Riwayat pangkat dan golongan berhasil diambil.',
                'data' => [],
            ]);

        // Tambahkan 2 riwayat dengan tanggal berbeda untuk cek urutan terbaru dulu
        HistoriPangkatGolongan::create([
            'id_dosen' => $dosen->id_dosen,
            'pangkat' => 'Asisten Ahli',
            'golongan' => '3B',
            'nomor_sk' => 'SK/LAMA/2021',
            'tanggal_sk' => '2021-01-10',
        ]);

        HistoriPangkatGolongan::create([
            'id_dosen' => $dosen->id_dosen,
            'pangkat' => 'Lektor Kepala',
            'golongan' => '4A',
            'nomor_sk' => 'SK/BARU/2025',
            'tanggal_sk' => '2025-08-15',
        ]);

        $this->getJson('/api/v1/dosen/riwayat-pangkat')
            ->assertStatus(200)
            ->assertJsonPath('data.0.nomor_sk', 'SK/BARU/2025')
            ->assertJsonPath('data.1.nomor_sk', 'SK/LAMA/2021');
    }
}
