<?php

namespace Tests\Feature;

use App\BackendKel2\Dashboard\DashboardController;
use App\Models\Kegiatan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use ReflectionMethod;
use Tests\TestCase;

class MahasiswaDashboardActivityIntegrationTest extends TestCase
{
    public function test_kegiatan_page_uses_only_backend_activities_and_eight_semesters(): void
    {
        $semesters = collect(range(1, 8))->map(fn (int $number) => [
            'label' => 'Sem '.$number,
            'value' => $number === 5 ? 1 : 0,
        ]);

        $this->view('mahasiswa.kegiatan.index', [
            'allActivities' => collect([[
                'id' => 1,
                'name' => 'Kegiatan dari database',
                'type' => 'Lomba',
                'date' => '17 Nov 2026',
                'raw_date' => '2026-11-17',
                'status' => 'Belum diajukan',
                'note' => null,
                'capaian' => '—',
                'tingkat' => '—',
                'penyelenggara' => '—',
                'members' => [],
                'documents' => [],
            ]]),
            'categoryCounts' => ['LOMBA' => 1],
            'categories' => collect(['Lomba']),
            'semesters' => $semesters,
            'chartMax' => 20,
        ])
            ->assertSee('Kegiatan dari database')
            ->assertSee('Sem 8')
            ->assertDontSee('FKBSE 2025')
            ->assertDontSee('Hackathon Nasional 2025');
    }

    public function test_backend_semester_counts_use_cohort_and_activity_start_date(): void
    {
        $activities = new Collection([
            $this->activityWithStartDate('2025-01-31'),
            $this->activityWithStartDate('2025-02-01'),
            $this->activityWithStartDate('2026-11-18'),
        ]);
        $method = new ReflectionMethod(DashboardController::class, 'semesterCounts');
        $method->setAccessible(true);

        $cohort2024 = $method->invoke(new DashboardController, $activities, 2024);
        $cohort2025 = $method->invoke(new DashboardController, $activities, 2025);

        $this->assertCount(8, $cohort2024);
        $this->assertSame(1, $cohort2024[0]['jumlah']);
        $this->assertSame(1, $cohort2024[1]['jumlah']);
        $this->assertSame(1, $cohort2024[4]['jumlah']);
        $this->assertSame(1, $cohort2025[2]['jumlah']);
    }

    private function activityWithStartDate(string $date): Kegiatan
    {
        $activity = new Kegiatan;
        $activity->setRawAttributes(['tanggal_mulai' => Carbon::parse($date)->toDateString()]);

        return $activity;
    }
}
