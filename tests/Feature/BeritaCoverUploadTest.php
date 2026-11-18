<?php

namespace Tests\Feature;

use App\Http\Controllers\PengajuanController;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BeritaCoverUploadTest extends TestCase
{
    public function test_student_can_upload_and_preview_a_news_cover(): void
    {
        Storage::fake('public');
        $this->mock(PengajuanController::class)
            ->shouldReceive('activityOptions')
            ->once()
            ->andReturn(collect());

        $student = new User;
        $student->id_user = 1;
        $student->id_role = 4;
        $student->status = true;
        $this->actingAs($student);

        $this->post(route('pengajuan.berita.store'), [
            'intent' => 'submit',
            'source_type' => 'new',
            'activity_name' => 'Seminar Nasional',
            'category' => 'Info Akademik',
            'title' => 'Seminar Nasional TRK',
            'content' => 'Informasi kegiatan seminar.',
            'cover' => UploadedFile::fake()->image('sampul.png'),
        ])->assertRedirect(route('pengajuan.berita.show', 1));

        $saved = session('pengajuan.berita')[0];
        $this->assertStringStartsWith('berita/', $saved['cover']);
        $this->assertSame('png', pathinfo($saved['cover'], PATHINFO_EXTENSION));
        Storage::disk('public')->assertExists($saved['cover']);

        $this->get(route('pengajuan.berita.show', 1))
            ->assertOk()
            ->assertSee('storage/'.$saved['cover'], false);
    }
}
