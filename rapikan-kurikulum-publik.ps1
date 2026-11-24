# Rapikan halaman publik Kurikulum: halaman PDF 100% lebih kecil, tombol bawah lebih kecil, margin kiri-kanan lebih lega.
# Jalankan dari folder utama proyek (yang ada file artisan). Aman: semua anchor diperiksa dulu.
$ErrorActionPreference = 'Stop'
if (-not (Test-Path 'artisan')) { throw 'Jalankan dari folder utama proyek (yang berisi file artisan).' }
$p = 'resources\views\kurikulum.blade.php'
$utf8 = New-Object System.Text.UTF8Encoding($false)
$t = [System.IO.File]::ReadAllText((Resolve-Path $p).Path, $utf8)
function Hitung($t, $s) { ([regex]::Matches($t, [regex]::Escape($s))).Count }

# [lama, baru, jumlah yang diharapkan]
$ubah = @(
  @('px-4 py-8 sm:px-8 lg:px-12 min-[1728px]:px-56', 'px-4 py-8 sm:px-8 lg:px-20 xl:px-28 min-[1728px]:px-56', 1),
  @('max-w-[1280px]', 'max-w-[1120px]', 5),
  @('sm:h-[656px]', 'sm:h-[560px]', 1),
  @('var BASE_W = 704,', 'var BASE_W = 600,', 1),
  @('flex h-[40px] sm:h-[45px] w-auto shrink-0 items-center gap-1 rounded-full bg-[#2277FF] p-0.5 px-3 text-base sm:text-2xl font-semibold text-white',
    'flex h-[36px] sm:h-[40px] w-auto shrink-0 items-center gap-1 rounded-full bg-[#2277FF] p-0.5 px-3 text-sm sm:text-base font-semibold text-white', 1),
  @('flex size-[32px] sm:size-[41px] items-center', 'flex size-[28px] sm:size-[34px] items-center', 1),
  @('flex h-[44px] sm:h-[52px] w-full items-center justify-center gap-3 rounded-xl border border-[#2277FF] bg-white text-sm sm:text-[17px] font-medium text-[#2277FF] transition-colors hover:bg-[#EAF2FD] sm:w-[220px]',
    'flex h-[38px] sm:h-[42px] w-full items-center justify-center gap-2 rounded-lg border border-[#2277FF] bg-white text-sm font-medium text-[#2277FF] transition-colors hover:bg-[#EAF2FD] sm:w-[170px]', 1)
)

$salah = @()
foreach ($u in $ubah) {
  $n = Hitung $t $u[0]
  if ($n -ne $u[2]) { $salah += "Ditemukan ${n}x (seharusnya $($u[2])x): $($u[0])" }
}
if ($salah.Count) { $salah | ForEach-Object { Write-Host $_ -ForegroundColor Red }; throw 'Dibatalkan, tidak ada file yang diubah. Kirim pesan merah di atas ke Claude.' }

foreach ($u in $ubah) { $t = $t.Replace($u[0], $u[1]) }
[System.IO.File]::WriteAllText((Resolve-Path $p).Path, $t, $utf8)
Write-Host "OK  $p" -ForegroundColor Green
Write-Host 'Muat ulang localhost:8000/kurikulum dengan Ctrl+F5.'
