# Fase 1 Profil Prodi: tambah route PUT (simpan dummy) ke routes\admin-kaprodi.php.
# Jalankan dari folder utama proyek, SETELAH menyalin isi zip (app, resources, tests, docs) ke proyek.
# Aman: anchor diperiksa dulu; kalau ada yang tidak cocok, tidak ada file yang diubah.
$ErrorActionPreference = 'Stop'
if (-not (Test-Path 'artisan')) { throw 'Jalankan dari folder utama proyek (yang berisi file artisan).' }
foreach ($f in 'app\Support\ProfilProdi.php','app\Http\Controllers\ProfilProdiController.php','resources\views\admin\profil-prodi\_logo.blade.php','tests\Feature\ProfilProdiTest.php') {
  if (-not (Test-Path $f)) { throw "Belum ada $f. Salin dulu isi zip ke proyek." }
}
$utf8 = New-Object System.Text.UTF8Encoding($false)
$rt = 'routes\admin-kaprodi.php'
$t = [System.IO.File]::ReadAllText((Resolve-Path $rt).Path, $utf8)
$nl = if ($t.Contains("`r`n")) { "`r`n" } else { "`n" }
function Hitung($t, $s) { ([regex]::Matches($t, [regex]::Escape($s))).Count }

$a1 = "->name('admin.prodi.profil');"
$a2 = "->name('kaprodi.prodi.profil');"
$s1 = "        'admin.prodi.profil',"
$s2 = "        'kaprodi.prodi.profil',"
$salah = @()
foreach ($a in @($a1,$a2,$s1,$s2)) { if ((Hitung $t $a) -ne 1) { $salah += "tidak ketemu tepat 1x -> $($a.Trim())  (apakah paket Data Prodi sudah dipasang?)" } }
if ($t.Contains('prodi.profil.update')) { $salah += 'route update sudah ada (Fase 1 sudah pernah dipasang?)' }
if ($salah.Count) { $salah | ForEach-Object { Write-Host $_ -ForegroundColor Red }; throw 'Dibatalkan, tidak ada file yang diubah. Kirim pesan merah di atas ke Claude.' }

$t = $t.Replace($a1, $a1 + $nl + "    Route::put('/prodi/profil', [ProfilProdiController::class, 'update'])->name('admin.prodi.profil.update');")
$t = $t.Replace($a2, $a2 + $nl + "    Route::put('/prodi/profil', [ProfilProdiController::class, 'update'])->name('kaprodi.prodi.profil.update');")
$t = $t.Replace($s1, $s1 + $nl + "        'admin.prodi.profil.update',")
$t = $t.Replace($s2, $s2 + $nl + "        'kaprodi.prodi.profil.update',")
[System.IO.File]::WriteAllText((Resolve-Path $rt).Path, $t, $utf8)
Write-Host "OK  $rt" -ForegroundColor Green
Write-Host ''
Write-Host 'Lanjut:' -ForegroundColor Cyan
Write-Host '  1) php artisan route:list --name=prodi.profil   (4 route: 2 get + 2 put)'
Write-Host '  2) php artisan test --filter=ProfilProdiTest'
Write-Host '  3) Buka /admin/prodi/profil dan /kaprodi/prodi/profil (ada banner "Mode dummy" + 5 kartu data contoh)'
