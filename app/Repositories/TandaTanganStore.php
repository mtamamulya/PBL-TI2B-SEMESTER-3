<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Penyimpanan tanda tangan kaprodi (PNG transparan), unggah SEKALI dipakai ulang.
 *
 * - Path aktif disimpan di kolom `users.tanda_tangan_path`.
 * - Gambar disimpan di storage/app/private/kaprodi/tanda-tangan/*.png (disk local, TIDAK publik).
 * - Saat surat ditandatangani, gambar DISALIN ke storage/app/private/surat/tanda-tangan/
 *   (snapshot), sehingga mengganti tanda tangan tidak mengubah surat lama.
 */
class TandaTanganStore
{
    private const FILE = 'kaprodi/tanda-tangan.json';

    private const DIR = 'kaprodi/tanda-tangan';

    /** Kunci kaprodi yang sedang login (id_user, atau email sesi sebagai cadangan). */
    public static function kunci(): string
    {
        if (auth()->check()) {
            $user = auth()->user();
            if ($user !== null && $user->getKey() !== null) {
                return (string) $user->getKey();
            }
            if (! empty($user?->email)) {
                return (string) $user->email;
            }
        }

        $s = session('kaprodi');

        return is_array($s) && ! empty($s['email']) ? (string) $s['email'] : 'kaprodi';
    }

    /** Tanda tangan aktif milik $kunci, atau null bila belum ada / file hilang. */
    public function aktif(string $kunci): ?array
    {
        $user = $this->cariUser($kunci);
        if ($user !== null) {
            $path = $user->tanda_tangan_path;
            if (! $path || ! Storage::disk('local')->exists($path)) {
                return null;
            }

            return [
                'path' => $path,
                'diunggah_pada' => $user->updated_at?->toIso8601String(),
            ];
        }

        $row = $this->semua()[$kunci] ?? null;

        if (! $row || empty($row['path']) || ! Storage::disk('local')->exists($row['path'])) {
            return null;
        }

        return $row;
    }

    /** Simpan (atau ganti) tanda tangan ke kolom users.tanda_tangan_path. */
    public function simpan(string $kunci, UploadedFile $file): array
    {
        $isi = $this->bersihkan($file);
        $disk = Storage::disk('local');
        $path = self::DIR.'/'.Str::uuid().'.png';

        $disk->put($path, $isi);

        $user = $this->cariUser($kunci);
        if ($user !== null) {
            $lama = $user->tanda_tangan_path;
            $user->forceFill(['tanda_tangan_path' => $path])->save();

            if ($lama && $lama !== $path) {
                $disk->delete($lama);
            }

            return [
                'path' => $path,
                'diunggah_pada' => now()->toIso8601String(),
            ];
        }

        $semua = $this->semua();
        $lama = $semua[$kunci]['path'] ?? null;

        $semua[$kunci] = ['path' => $path, 'diunggah_pada' => now()->toIso8601String()];
        $this->tulis($semua);

        if ($lama && $lama !== $path) {
            $disk->delete($lama);
        }

        return $semua[$kunci];
    }

    /** Salin tanda tangan aktif ke folder surat. Mengembalikan path salinan, atau null. */
    public function salinKeSurat(string $kunci, string $idPengajuan): ?string
    {
        $row = $this->aktif($kunci);
        if (! $row) {
            return null;
        }

        $tujuan = 'surat/tanda-tangan/'.$idPengajuan.'-'.Str::random(8).'.png';
        Storage::disk('local')->copy($row['path'], $tujuan);

        return $tujuan;
    }

    /* ---------- internal ---------- */

    private function cariUser(string $kunci): ?User
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'tanda_tangan_path')) {
            return null;
        }

        if (auth()->check()) {
            $authUser = auth()->user();
            if ($authUser instanceof User && ((string) $authUser->getKey() === $kunci || $authUser->email === $kunci)) {
                return $authUser;
            }
        }

        if (ctype_digit($kunci)) {
            return User::find((int) $kunci);
        }

        return User::where('email', $kunci)->first();
    }

    private function bersihkan(UploadedFile $file): string
    {
        $mentah = (string) file_get_contents($file->getRealPath());
        $info = @getimagesizefromstring($mentah);

        if (! $info || $info[2] !== IMAGETYPE_PNG) {
            throw ValidationException::withMessages(['tanda_tangan' => 'File harus berupa gambar PNG yang valid.']);
        }

        if (! function_exists('imagecreatefromstring')) {
            return $mentah;
        }

        $img = @imagecreatefromstring($mentah);
        if (! $img) {
            throw ValidationException::withMessages(['tanda_tangan' => 'Gambar tidak dapat dibaca.']);
        }

        imagealphablending($img, false);
        imagesavealpha($img, true);

        ob_start();
        imagepng($img);
        $out = (string) ob_get_clean();
        imagedestroy($img);

        return $out;
    }

    private function semua(): array
    {
        $disk = Storage::disk('local');

        if (! $disk->exists(self::FILE)) {
            return [];
        }

        return json_decode($disk->get(self::FILE), true) ?: [];
    }

    private function tulis(array $semua): void
    {
        Storage::disk('local')->put(
            self::FILE,
            json_encode((object) $semua, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }
}
