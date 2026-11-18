<?php

require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

$email = $argv[1] ?? 'admin.kel1@trk.test';
$u = User::where('email', $email)->firstOrFail();
$token = $u->createToken('uji-status');
$base = rtrim(config('api_client.base_url') ?: url('/api/v1'), '/');
echo "Akun: $email (role {$u->id_role}) | API: $base\n";

foreach (Route::getRoutes() as $r) {
    if (! in_array('GET', $r->methods(), true)) {
        continue;
    }
    $uri = $r->uri();
    if (! str_starts_with($uri, 'api/v1/') || str_contains($uri, '{')) {
        continue;
    }
    if (! preg_match('#^api/v1/(admin|kaprodi)/#', $uri) && ! preg_match('#kegiatan|pengajuan|berita|prestasi#', $uri)) {
        continue;
    }
    $res = Http::withToken($token->plainTextToken)->acceptJson()->timeout(10)->get($base.'/'.substr($uri, 7));
    echo $res->status().'  '.$uri.PHP_EOL;
}
$token->accessToken->delete();
echo "selesai (token uji dihapus)\n";
