<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MahasiswaDashboardContohTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_demo_api_returns_card_summary_and_course_rows(): void
    {
        $this->getJson('/api/v1/mahasiswa/dashboard/contoh')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.ringkasan.jumlah_mk', 3)
            ->assertJsonPath('data.ringkasan.sks_semester', 11)
            ->assertJsonPath('data.mata_kuliah.0.mk', 'Pemrograman Web Lanjut')
            ->assertJsonPath('data.mata_kuliah.0.dosen', 'Dwi Rahmawati, S.Kom., M.Kom.');
    }

    public function test_demo_query_shows_example_data_without_changing_default_empty_dashboard(): void
    {
        $user = new User;
        $user->id_role = 4;
        $user->status = true;

        $this->actingAs($user)
            ->get('/mahasiswa/mahasiswa/dashboard?contoh=1')
            ->assertOk()
            ->assertViewHas('mataKuliah', fn ($courses) => $courses->total() === 5)
            ->assertSee('Pemrograman Web Lanjut')
            ->assertSee('18')
            ->assertViewHas('ringkasan', fn (array $summary) => $summary['jumlah_mk'] === 3);

        $this->actingAs($user)
            ->get('/mahasiswa/mahasiswa/dashboard')
            ->assertOk()
            ->assertViewHas('ringkasan', fn (array $summary) => $summary['sks_tempuh'] === 0)
            ->assertViewHas('mataKuliah', fn ($courses) => $courses->isEmpty());
    }
}
