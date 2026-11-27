# Fase 4 Profil Prodi: daftarkan API ke app\BackendKel1\routes.php dan tambah dokumentasi ke docs\openapi.yaml.
# Jalankan dari folder utama proyek, SETELAH menyalin folder app, database, tests dari zip.
# Aman: semua anchor diperiksa dulu; kalau ada yang tidak cocok, tidak ada file yang diubah.
$ErrorActionPreference = 'Stop'
if (-not (Test-Path 'artisan')) { throw 'Jalankan dari folder utama proyek (yang berisi file artisan).' }
foreach ($f in 'app\BackendKel1\ProfilProdi\ProfilProdiController.php','app\BackendKel1\ProfilProdi\routes.php','tests\Feature\Api\ProfilProdiApiTest.php','database\migrations\2026_11_25_200000_tambah_kolom_profil_ke_tabel_prodi.php') {
  if (-not (Test-Path $f)) { throw "Belum ada $f. Salin dulu isi zip ke proyek." }
}
$utf8 = New-Object System.Text.UTF8Encoding($false)
function Baca($p)  { [System.IO.File]::ReadAllText((Resolve-Path $p).Path, $utf8) }
function Tulis($p, $t) { [System.IO.File]::WriteAllText((Resolve-Path $p).Path, $t, $utf8) }
function Hitung($t, $s) { ([regex]::Matches($t, [regex]::Escape($s))).Count }

$pr = 'app\BackendKel1\routes.php'; $dy = 'docs\openapi.yaml'
$tR = Baca $pr; $tD = Baca $dy
$nlR = if ($tR.Contains("`r`n")) { "`r`n" } else { "`n" }
$nl  = if ($tD.Contains("`r`n")) { "`r`n" } else { "`n" }

$salah = @()
$ancR = "require __DIR__.'/Berita/routes.php';"
if ((Hitung $tR $ancR) -ne 1) { $salah += 'routes.php: anchor Berita tidak ketemu tepat 1x' }
if ($tR.Contains('ProfilProdi/routes.php')) { $salah += 'routes.php: ProfilProdi sudah terdaftar' }
$ancTag    = "  - name: Berita$nl"
$ancPath   = "  /berita:$nl"
$ancSchema = "    Berita:$nl      type: object$nl"
foreach ($a in @($ancTag,$ancPath,$ancSchema)) { if ((Hitung $tD $a) -ne 1) { $salah += "openapi.yaml: anchor tidak ketemu tepat 1x -> $($a.Trim())" } }
if ($tD.Contains('tags: [Profil Prodi]')) { $salah += 'openapi.yaml: Profil Prodi sudah ada' }
if ($salah.Count) { $salah | ForEach-Object { Write-Host $_ -ForegroundColor Red }; throw 'Dibatalkan, tidak ada file yang diubah. Kirim pesan merah di atas ke Claude.' }

$tag = @'
  - name: Profil Prodi
    description: Profil prodi tunggal (identitas, visi, misi, kontak, sosial media, logo). Baca publik, tulis admin dan kaprodi.
'@
$path = @'
  /prodi/profil:
    get:
      tags: [Profil Prodi]
      summary: Ambil profil prodi
      description: PUBLIK (tanpa token), dipakai landing page. Jika data belum ada, semua kolom berisi `null` dan daftar `[]`.
      security: []
      responses:
        '200':
          description: Profil prodi.
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ProfilProdiResponse'
    put:
      tags: [Profil Prodi]
      summary: Ubah profil prodi
      description: Peran **admin** atau **kaprodi**. Boleh sebagian (kirim hanya kunci yang diubah). `no_wa` dinormalkan ke `62xxxxxxxxxx`. Logo tidak lewat endpoint ini.
      security:
        - bearerAuth: []
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/ProfilProdiInput'
      responses:
        '200':
          description: Profil tersimpan.
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ProfilProdiResponse'
        '401':
          $ref: '#/components/responses/TidakLogin'
        '403':
          $ref: '#/components/responses/DilarangPeran'
        '422':
          $ref: '#/components/responses/ValidasiGagal'

  /prodi/profil/logo:
    post:
      tags: [Profil Prodi]
      summary: Unggah logo prodi
      description: Peran **admin** atau **kaprodi**. Format png, jpg, jpeg, webp, maks 2 MB. Logo lama diganti dan filenya dihapus.
      security:
        - bearerAuth: []
      requestBody:
        required: true
        content:
          multipart/form-data:
            schema:
              type: object
              required: [jenis, file]
              properties:
                jenis:
                  type: string
                  enum: [utama, footer]
                file:
                  type: string
                  format: binary
      responses:
        '201':
          description: Logo tersimpan.
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ProfilProdiResponse'
        '401':
          $ref: '#/components/responses/TidakLogin'
        '403':
          $ref: '#/components/responses/DilarangPeran'
        '404':
          $ref: '#/components/responses/TidakDitemukan'
        '422':
          $ref: '#/components/responses/ValidasiGagal'

  /prodi/profil/logo/{jenis}:
    delete:
      tags: [Profil Prodi]
      summary: Hapus logo prodi
      description: Peran **admin** atau **kaprodi**. Untuk `footer`, setelah dihapus landing memakai logo utama.
      security:
        - bearerAuth: []
      parameters:
        - name: jenis
          in: path
          required: true
          schema:
            type: string
            enum: [utama, footer]
      responses:
        '200':
          description: Logo dihapus.
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ProfilProdiResponse'
        '401':
          $ref: '#/components/responses/TidakLogin'
        '403':
          $ref: '#/components/responses/DilarangPeran'
        '404':
          $ref: '#/components/responses/TidakDitemukan'

'@
$schema = @'
    ProfilProdi:
      type: object
      properties:
        kode_prodi: { type: string, nullable: true, example: TRK }
        nama_prodi: { type: string, nullable: true, example: Teknologi Rekayasa Komputer }
        jenjang: { type: string, nullable: true, enum: [D3, D4], example: D4 }
        deskripsi: { type: string, nullable: true }
        visi: { type: string, nullable: true }
        misi:
          type: array
          items: { type: string }
        tujuan:
          type: array
          items: { type: string }
        profil_lulusan:
          type: array
          items:
            type: object
            properties:
              judul: { type: string, example: Software Engineer }
              deskripsi: { type: string, nullable: true }
        email: { type: string, nullable: true }
        no_wa: { type: string, nullable: true, example: '6281234567890' }
        alamat: { type: string, nullable: true }
        instagram: { type: string, nullable: true }
        youtube: { type: string, nullable: true }
        tiktok: { type: string, nullable: true }
        facebook: { type: string, nullable: true }
        logo_url: { type: string, nullable: true }
        logo_footer_url: { type: string, nullable: true }

    ProfilProdiInput:
      type: object
      description: Semua kunci opsional (boleh sebagian). URL sosial media hanya http/https.
      properties:
        kode_prodi: { type: string, maxLength: 20 }
        nama_prodi: { type: string, maxLength: 100 }
        jenjang: { type: string, enum: [D3, D4] }
        deskripsi: { type: string, nullable: true, maxLength: 2000 }
        visi: { type: string, nullable: true, maxLength: 1000 }
        misi:
          type: array
          maxItems: 20
          items: { type: string, maxLength: 500 }
        tujuan:
          type: array
          maxItems: 20
          items: { type: string, maxLength: 500 }
        profil_lulusan:
          type: array
          maxItems: 20
          items:
            type: object
            required: [judul]
            properties:
              judul: { type: string, maxLength: 150 }
              deskripsi: { type: string, nullable: true, maxLength: 1000 }
        email: { type: string, nullable: true, maxLength: 100 }
        no_wa: { type: string, nullable: true, example: '0812-3456-7890' }
        alamat: { type: string, nullable: true, maxLength: 500 }
        instagram: { type: string, nullable: true, maxLength: 255 }
        youtube: { type: string, nullable: true, maxLength: 255 }
        tiktok: { type: string, nullable: true, maxLength: 255 }
        facebook: { type: string, nullable: true, maxLength: 255 }

    ProfilProdiResponse:
      type: object
      properties:
        success:
          type: boolean
          example: true
        message:
          type: string
          example: Data berhasil diambil
        data:
          $ref: '#/components/schemas/ProfilProdi'

'@
function Nl($s, $n) { ($s -replace "`r?`n", $n) }
$tR = $tR.Replace($ancR, "require __DIR__.'/ProfilProdi/routes.php';" + $nlR + $ancR)
$tD = $tD.Replace($ancTag,    (Nl $tag $nl)    + $nl + $ancTag)
$tD = $tD.Replace($ancPath,   (Nl $path $nl)         + $ancPath)
$tD = $tD.Replace($ancSchema, (Nl $schema $nl)       + $ancSchema)
Tulis $pr $tR; Tulis $dy $tD
Write-Host "OK  $pr" -ForegroundColor Green
Write-Host "OK  $dy" -ForegroundColor Green
Write-Host ''
Write-Host 'Lanjut:' -ForegroundColor Cyan
Write-Host '  1) php artisan migrate   (menambah 14 kolom nullable di tabel prodi)'
Write-Host '  2) php artisan route:list --path=api/v1/prodi   (4 route)'
Write-Host '  3) php artisan api:dokumentasi-cek'
Write-Host '  4) php artisan test --filter=ProfilProdiApiTest'
Write-Host '  5) php artisan storage:link   (sekali saja, bila belum, agar logo bisa dibuka)'
