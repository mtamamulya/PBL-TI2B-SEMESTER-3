# Struktur Folder `sikap-kelompok3`

Dokumen ini mencatat struktur proyek berdasarkan isi folder saat dibuat. Folder dependensi (`vendor/`, `node_modules/`), metadata Git (`.git/`), dan berkas cache/runtime yang dibuat otomatis tidak dirinci satu per satu.

```text
sikap-kelompok3/
├── .githooks/
│   └── pre-commit
├── .github/
│   ├── ISSUE_TEMPLATE/
│   │   ├── bug_report.md
│   │   └── task.md
│   ├── workflows/
│   │   └── ci.yml
│   └── pull_request_template.md
├── app/
│   ├── BackendKel1/
│   │   ├── Akreditasi/
│   │   │   ├── AkreditasiController.php
│   │   │   └── routes.php
│   │   ├── Autentikasi/
│   │   │   ├── AuthController.php
│   │   │   ├── PeranApi.php
│   │   │   └── routes.php
│   │   ├── Berita/
│   │   │   ├── BeritaController.php
│   │   │   └── routes.php
│   │   ├── Dosen/
│   │   │   ├── DosenController.php
│   │   │   └── routes.php
│   │   ├── JenisDokumen/
│   │   │   ├── JenisDokumenController.php
│   │   │   └── routes.php
│   │   ├── KategoriKegiatan/
│   │   │   ├── KategoriKegiatanController.php
│   │   │   ├── PilihanBaku.php
│   │   │   └── routes.php
│   │   ├── Kurikulum/
│   │   │   ├── KurikulumAngkatan.php
│   │   │   ├── KurikulumController.php
│   │   │   └── routes.php
│   │   ├── Mahasiswa/
│   │   │   ├── MahasiswaController.php
│   │   │   └── routes.php
│   │   ├── MataKuliah/
│   │   │   ├── MataKuliahController.php
│   │   │   └── routes.php
│   │   ├── PengajuanAdmin/
│   │   │   ├── PengajuanAdminController.php
│   │   │   └── routes.php
│   │   ├── Prestasi/
│   │   │   ├── PrestasiController.php
│   │   │   └── routes.php
│   │   ├── IsiKolomAda.php
│   │   └── routes.php
│   ├── BackendKel2/
│   │   ├── Berita/
│   │   │   ├── AdminBeritaController.php
│   │   │   ├── BeritaController.php
│   │   │   ├── README.md
│   │   │   └── routes.php
│   │   ├── Dashboard/
│   │   │   ├── DashboardController.php
│   │   │   ├── README.md
│   │   │   └── routes.php
│   │   ├── Laporan/
│   │   │   ├── LaporanController.php
│   │   │   ├── README.md
│   │   │   └── routes.php
│   │   ├── Support/
│   │   │   └── RoleGuard.php
│   │   ├── README.md
│   │   ├── README.txt
│   │   └── routes.php
│   ├── BackendKel3/
│   │   ├── Autentikasi/
│   │   │   ├── PastikanDosenAktif.php
│   │   │   └── README.md
│   │   ├── Dashboard/
│   │   │   ├── DosenDashboardController.php
│   │   │   ├── README.md
│   │   │   └── routes.php
│   │   ├── DokumenSurat/
│   │   │   ├── README.md
│   │   │   └── routes.php
│   │   ├── Penelitian/
│   │   │   ├── README.md
│   │   │   └── routes.php
│   │   ├── Pengabdian/
│   │   │   ├── README.md
│   │   │   └── routes.php
│   │   ├── Pengajaran/
│   │   │   ├── DosenPengajaranController.php
│   │   │   ├── README.md
│   │   │   └── routes.php
│   │   ├── Pengajuan/
│   │   │   ├── AlurPengajuan.php
│   │   │   ├── PengajuanController.php
│   │   │   ├── README.md
│   │   │   └── routes.php
│   │   ├── PrestasiBerita/
│   │   │   ├── PrestasiController.php
│   │   │   ├── PrestasiRequest.php
│   │   │   ├── README.md
│   │   │   └── routes.php
│   │   ├── Profil/
│   │   │   ├── README.md
│   │   │   └── routes.php
│   │   ├── README.md
│   │   └── routes.php
│   ├── Console/
│   │   └── Commands/
│   │       ├── BuatTokenLayanan.php
│   │       └── CekDokumentasiApi.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/
│   │   │   │   └── MahasiswaController.php
│   │   │   ├── AdminAkreditasiController.php
│   │   │   ├── AdminAuthController.php
│   │   │   ├── AdminBeritaController.php
│   │   │   ├── AdminKegiatanController.php
│   │   │   ├── AdminKegiatanDosenDetailShowController.php
│   │   │   ├── AdminKegiatanMahasiswaDetailShowController.php
│   │   │   ├── AdminKegiatanTambahController.php
│   │   │   ├── AdminKurikulumController.php
│   │   │   ├── AdminMataKuliahController.php
│   │   │   ├── AdminPengajuanController.php
│   │   │   ├── AdminPrestasiController.php
│   │   │   ├── AdminProfilController.php
│   │   │   ├── AdminTinjauPengajuanController.php
│   │   │   ├── AkreditasiController.php
│   │   │   ├── ApiDokumentasiController.php
│   │   │   ├── BeritaController.php
│   │   │   ├── Controller.php
│   │   │   ├── DashboardController.php
│   │   │   ├── DosenController.php
│   │   │   ├── FasilitasController.php
│   │   │   ├── HomeController.php
│   │   │   ├── JenisDokumenController.php
│   │   │   ├── KaprodiBeritaController.php
│   │   │   ├── KaprodiDashboardController.php
│   │   │   ├── KaprodiDosenController.php
│   │   │   ├── KaprodiJenisDokumenController.php
│   │   │   ├── KaprodiKategoriKegiatanController.php
│   │   │   ├── KaprodiKegiatanController.php
│   │   │   ├── KaprodiKegiatanDosenController.php
│   │   │   ├── KaprodiKegiatanDosenDetailShowController.php
│   │   │   ├── KaprodiKegiatanMahasiswaController.php
│   │   │   ├── KaprodiKegiatanMahasiswaDetailShowController.php
│   │   │   ├── KaprodiKurikulumController.php
│   │   │   ├── KaprodiMahasiswaController.php
│   │   │   ├── KaprodiMataKuliahController.php
│   │   │   ├── KaprodiPengajuanController.php
│   │   │   ├── KaprodiPrestasiController.php
│   │   │   ├── KaprodiProfilController.php
│   │   │   ├── KaprodiTandaTanganController.php
│   │   │   ├── KategoriKegiatanController.php
│   │   │   ├── KegiatanController.php
│   │   │   ├── KegiatanDosenController.php
│   │   │   ├── KegiatanHapusController.php
│   │   │   ├── KegiatanLaporanController.php
│   │   │   ├── KegiatanMahasiswaController.php
│   │   │   ├── KurikulumController.php
│   │   │   ├── LandingDashboardController.php
│   │   │   ├── LandingMahasiswaController.php
│   │   │   ├── MahasiswaController.php
│   │   │   ├── MataKuliahController.php
│   │   │   ├── PemulihanController.php
│   │   │   ├── PengajuanController.php
│   │   │   ├── PrestasiController.php
│   │   │   └── PublicBeritaController.php
│   │   └── Middleware/
│   │       ├── AdminOnly.php
│   │       ├── KaprodiOnly.php
│   │       ├── MahasiswaOnly.php
│   │       └── PastikanAkunAktif.php
│   ├── Mail/
│   │   └── PasswordResetOtp.php
│   ├── Models/
│   │   ├── Akreditasi.php
│   │   ├── AnggotaKegiatan.php
│   │   ├── Berita.php
│   │   ├── BuktiHistory.php
│   │   ├── BuktiPrestasi.php
│   │   ├── DetailKegiatan.php
│   │   ├── Dokumen.php
│   │   ├── Dosen.php
│   │   ├── EnrollmentKelasMataKuliah.php
│   │   ├── EnrollmentMahasiswaTingkatKelas.php
│   │   ├── EnrollmentMataKuliah.php
│   │   ├── EnrollmentTingkatKelas.php
│   │   ├── HistoriPangkatGolongan.php
│   │   ├── IsianKategori.php
│   │   ├── JenisDokumen.php
│   │   ├── JenisKegiatan.php
│   │   ├── Kategori.php
│   │   ├── Kegiatan.php
│   │   ├── Kelas.php
│   │   ├── KelompokBidang.php
│   │   ├── Kurikulum.php
│   │   ├── Mahasiswa.php
│   │   ├── MataKuliah.php
│   │   ├── Pengajaran.php
│   │   ├── Prestasi.php
│   │   ├── Prodi.php
│   │   ├── Role.php
│   │   ├── Skim.php
│   │   ├── TahunAkademik.php
│   │   ├── Timeline.php
│   │   ├── Tingkat.php
│   │   └── User.php
│   ├── Providers/
│   │   ├── AppServiceProvider.php
│   │   └── DataSourceServiceProvider.php
│   ├── Repositories/
│   │   ├── AdminBeritaStore.php
│   │   ├── AkreditasiDbStore.php
│   │   ├── AkreditasiStore.php
│   │   ├── ConfigKurikulumRepository.php
│   │   ├── ConfigMataKuliahRepository.php
│   │   ├── DosenDbStore.php
│   │   ├── DosenStore.php
│   │   ├── EloquentKurikulumRepository.php
│   │   ├── EloquentMataKuliahRepository.php
│   │   ├── JenisDokumenStore.php
│   │   ├── JsonKurikulumRepository.php
│   │   ├── JsonMataKuliahRepository.php
│   │   ├── KategoriKegiatanStore.php
│   │   ├── KegiatanData.php
│   │   ├── KegiatanProdiData.php
│   │   ├── KurikulumDbStore.php
│   │   ├── KurikulumRepository.php
│   │   ├── KurikulumStore.php
│   │   ├── MahasiswaDbStore.php
│   │   ├── MahasiswaStore.php
│   │   ├── MataKuliahDbStore.php
│   │   ├── MataKuliahRepository.php
│   │   ├── MataKuliahStore.php
│   │   ├── PenempatanKelasMahasiswa.php
│   │   ├── PengajuanStore.php
│   │   ├── SuratPersetujuanStore.php
│   │   └── TandaTanganStore.php
│   └── Support/
│       ├── Laporan/
│       │   ├── LaporanKegiatan.php
│       │   ├── PdfTabel.php
│       │   └── XlsxTabel.php
│       ├── ApiClient.php
│       ├── ApiDokumentasi.php
│       ├── ApiException.php
│       ├── ApiResponse.php
│       ├── ApiTokenSesi.php
│       ├── KolomOpsional.php
│       ├── LinkFile.php
│       ├── NimMahasiswa.php
│       └── SemesterAkademik.php
├── bootstrap/
│   ├── cache/
│   │   ├── .gitignore
│   │   ├── packages.php
│   │   └── services.php
│   ├── app.php
│   └── providers.php
├── config/
│   ├── admin.php
│   ├── api_client.php
│   ├── api_docs.php
│   ├── app.php
│   ├── auth.php
│   ├── berita.php
│   ├── cache.php
│   ├── database.php
│   ├── fasilitas.php
│   ├── filesystems.php
│   ├── kaprodi.php
│   ├── kurikulum.php
│   ├── l5-swagger.php
│   ├── logging.php
│   ├── mahasiswa.php
│   ├── mail.php
│   ├── matkul.php
│   ├── prestasi.php
│   ├── queue.php
│   ├── services.php
│   └── session.php
├── database/
│   ├── factories/
│   │   └── UserFactory.php
│   ├── migrations/
│   │   ├── 0001_01_01_000001_create_cache_table.php
│   │   ├── 0001_01_01_000002_create_jobs_table.php
│   │   ├── 2026_10_22_000001_create_prodi_table.php
│   │   ├── 2026_10_22_000002_create_tahun_akademik_table.php
│   │   ├── 2026_10_22_000003_create_tingkat_table.php
│   │   ├── 2026_10_22_000004_create_kelas_table.php
│   │   ├── 2026_10_22_000005_create_roles_table.php
│   │   ├── 2026_10_22_000006_create_skim_table.php
│   │   ├── 2026_10_22_000007_create_kelompok_bidang_table.php
│   │   ├── 2026_10_22_000008_create_jenis_kegiatan_table.php
│   │   ├── 2026_10_22_000009_create_jenis_dokumen_table.php
│   │   ├── 2026_10_22_000101_create_users_table.php
│   │   ├── 2026_10_22_000102_create_kurikulum_table.php
│   │   ├── 2026_10_22_000103_create_kategori_table.php
│   │   ├── 2026_10_22_000104_create_enrollment_tingkat_kelas_table.php
│   │   ├── 2026_10_22_000201_create_dosen_table.php
│   │   ├── 2026_10_22_000202_create_mahasiswa_table.php
│   │   ├── 2026_10_22_000203_create_akreditasi_table.php
│   │   ├── 2026_10_22_000204_create_mata_kuliah_table.php
│   │   ├── 2026_10_22_000205_create_kegiatan_table.php
│   │   ├── 2026_10_22_000206_create_isian_kategori_table.php
│   │   ├── 2026_10_22_000301_create_histori_pangkat_golongan_table.php
│   │   ├── 2026_10_22_000302_create_enrollment_mata_kuliah_table.php
│   │   ├── 2026_10_22_000303_create_enrollment_mahasiswa_tingkat_kelas_table.php
│   │   ├── 2026_10_22_000304_create_kegiatan_dosen_table.php
│   │   ├── 2026_10_22_000305_create_detail_kegiatan_table.php
│   │   ├── 2026_10_22_000306_create_anggota_kegiatan_table.php
│   │   ├── 2026_10_22_000307_create_prestasi_table.php
│   │   ├── 2026_10_22_000308_create_berita_table.php
│   │   ├── 2026_10_22_000309_create_timeline_table.php
│   │   ├── 2026_10_22_000310_create_dokumen_table.php
│   │   ├── 2026_10_22_000401_create_enrollment_kelas_mata_kuliah_table.php
│   │   ├── 2026_10_22_000402_create_bukti_history_table.php
│   │   ├── 2026_10_22_000403_create_bukti_prestasi_table.php
│   │   ├── 2026_10_22_000501_create_pengajaran_table.php
│   │   ├── 2026_11_03_000001_add_surat_fields_to_kategori_table.php
│   │   ├── 2026_11_03_000002_add_tanda_tangan_path_to_users_table.php
│   │   ├── 2026_11_03_000003_add_is_lampiran_to_dokumen_table.php
│   │   ├── 2026_11_03_000004_add_validity_fields_to_akreditasi_table.php
│   │   ├── 2026_11_03_000005_create_personal_access_tokens_table.php
│   │   ├── 2026_11_04_000001_apply_dynamic_activity_erd.php
│   │   ├── 2026_11_10_000001_create_login_logs_table.php
│   │   ├── 2026_11_10_000001_extend_admin_prestasi_form.php
│   │   ├── 2026_11_10_000002_create_otp_codes_table.php
│   │   ├── 2026_11_16_000001_add_tahun_terbit_dan_deskripsi_to_kurikulum_table.php
│   │   └── 2026_11_16_000003_add_backend_kel2_fields_to_berita.php
│   ├── seeders/
│   │   ├── DatabaseSeeder.php
│   │   ├── DataMasterSeeder.php
│   │   ├── JenisDokumenSeeder.php
│   │   └── RiwayatAkademikSeeder.php
│   └── .gitignore
├── docs/
│   ├── API-CONTRACT.md
│   ├── DATABASE.md
│   ├── ERD-REVISI-2026-11-04.md
│   ├── frontend-mahasiswa-dosen.md
│   ├── openapi.yaml
│   └── PEMBARUAN-DATABASE-SURAT.md
├── postman/
│   ├── SIKAP-API-v1.postman_collection.json
│   └── SIKAP-Lokal.postman_environment.json
├── public/
│   ├── images/
│   │   ├── login/
│   │   │   ├── gedung-polines.png
│   │   │   └── logo-polines-login.png
│   │   ├── slides/
│   │   │   ├── KKL.jpg
│   │   │   ├── magang.jpg
│   │   │   ├── PBL.jpeg
│   │   │   └── tefa.jpg
│   │   ├── kuwat.jpeg
│   │   ├── logo.png
│   │   ├── logo_trk remove.png
│   │   ├── logo_trk.jpeg
│   │   ├── logo-polines.png
│   │   ├── logo-ti.png
│   │   ├── logo-trk new.png
│   │   ├── mahasiswa trk.jpeg
│   │   ├── mahasiswa.jpg
│   │   └── mhs.png.jpeg
│   ├── .htaccess
│   ├── favicon.ico
│   ├── hot
│   ├── index.php
│   └── robots.txt
├── resources/
│   ├── css/
│   │   ├── app.css
│   │   └── frontend.css
│   ├── images/
│   │   └── logo-trk.png
│   ├── js/
│   │   ├── app.js
│   │   └── bootstrap.js
│   └── views/
│       ├── admin/
│       │   ├── partials/
│       │   │   ├── berita-detail.blade.php
│       │   │   ├── berita-form.blade.php
│       │   │   └── prestasi-form.blade.php
│       │   ├── admin-profil.blade.php
│       │   ├── akreditasi.blade.php
│       │   ├── berita.blade.php
│       │   ├── berita-detail.blade.php
│       │   ├── berita-edit.blade.php
│       │   ├── berita-tambah.blade.php
│       │   ├── dashboard.blade.php
│       │   ├── data-dosen.blade.php
│       │   ├── dosen.blade.php
│       │   ├── dosen-detail.blade.php
│       │   ├── dosen-edit.blade.php
│       │   ├── dosen-tambah.blade.php
│       │   ├── jenis-dokumen.blade.php
│       │   ├── kategori-kegiatan.blade.php
│       │   ├── kategori-kegiatan-form.blade.php
│       │   ├── kegiatan.blade.php
│       │   ├── kegiatan-dosen.blade.php
│       │   ├── kegiatan-dosen-detail-show.blade.php
│       │   ├── kegiatan-mahasiswa.blade.php
│       │   ├── kegiatan-mahasiswa-detail-show.blade.php
│       │   ├── kegiatan-tambah.blade.php
│       │   ├── kurikulum.blade.php
│       │   ├── mahasiswa.blade.php
│       │   ├── mahasiswa-detail.blade.php
│       │   ├── mahasiswa-edit.blade.php
│       │   ├── mahasiswa-tambah.blade.php
│       │   ├── mata-kuliah.blade.php
│       │   ├── pengajuan.blade.php
│       │   ├── placeholder.blade.php
│       │   ├── prestasi.blade.php
│       │   ├── prestasi-detail.blade.php
│       │   ├── prestasi-edit.blade.php
│       │   ├── prestasi-tambah.blade.php
│       │   ├── tambah-mata-kuliah.blade.php
│       │   └── tinjau-pengajuan.blade.php
│       ├── backendkel2/
│       │   └── laporan/
│       │       └── pdf.blade.php
│       ├── components/
│       │   ├── admin/
│       │   │   ├── breadcrumb.blade.php
│       │   │   └── sidebar.blade.php
│       │   ├── kaprodi/
│       │   │   └── sidebar.blade.php
│       │   ├── mahasiswa/
│       │   │   └── stat-card.blade.php
│       │   ├── pengajuan/
│       │   │   └── field.blade.php
│       │   ├── profil/
│       │   │   └── field.blade.php
│       │   ├── alert.blade.php
│       │   ├── auth-layout.blade.php
│       │   ├── detail-field.blade.php
│       │   ├── footer.blade.php
│       │   ├── header.blade.php
│       │   ├── header-dosen.blade.php
│       │   ├── icon.blade.php
│       │   ├── modal-hapus.blade.php
│       │   ├── navbar.blade.php
│       │   ├── navbar-publik.blade.php
│       │   ├── paginasi.blade.php
│       │   ├── preview-dokumen.blade.php
│       │   ├── section-header.blade.php
│       │   ├── sidebar.blade.php
│       │   ├── tabel-footer.blade.php
│       │   ├── tabel-js.blade.php
│       │   ├── th-urut.blade.php
│       │   ├── tool-button.blade.php
│       │   └── user-menu.blade.php
│       ├── Dashboard/
│       │   └── dashboard.blade.php
│       ├── dosen/
│       │   ├── berita/
│       │   │   ├── _form.blade.php
│       │   │   ├── create.blade.php
│       │   │   ├── data.php
│       │   │   ├── edit.blade.php
│       │   │   ├── index.blade.php
│       │   │   └── show.blade.php
│       │   ├── kegiatan/
│       │   │   ├── _detail-kategori.blade.php
│       │   │   ├── _medan-kategori.php
│       │   │   ├── create.blade.php
│       │   │   ├── edit.blade.php
│       │   │   ├── index.blade.php
│       │   │   ├── kegiatan.blade.php
│       │   │   └── show.blade.php
│       │   ├── notifikasi/
│       │   │   └── index.blade.php
│       │   ├── penelitian/
│       │   │   ├── create.blade.php
│       │   │   ├── edit.blade.php
│       │   │   ├── index.blade.php
│       │   │   └── show.blade.php
│       │   ├── pengabdian/
│       │   │   ├── create.blade.php
│       │   │   ├── edit.blade.php
│       │   │   ├── index.blade.php
│       │   │   └── show.blade.php
│       │   ├── pengajaran/
│       │   │   ├── _form.blade.php
│       │   │   ├── create.blade.php
│       │   │   ├── edit.blade.php
│       │   │   ├── index.blade.php
│       │   │   └── show.blade.php
│       │   ├── pengajuan/
│       │   │   ├── create.blade.php
│       │   │   ├── dosen.blade.php
│       │   │   ├── index.blade.php
│       │   │   └── show.blade.php
│       │   ├── prestasi/
│       │   │   ├── _api.blade.php
│       │   │   ├── _form-script.blade.php
│       │   │   ├── create.blade.php
│       │   │   ├── edit.blade.php
│       │   │   ├── form.blade.php
│       │   │   ├── index.blade.php
│       │   │   └── show.blade.php
│       │   ├── profil/
│       │   │   └── show.blade.php
│       │   └── dashboard.blade.php
│       ├── emails/
│       │   └── password-reset-otp.blade.php
│       ├── kaprodi/
│       │   ├── partials/
│       │   │   └── mata-kuliah-form.blade.php
│       │   ├── berita.blade.php
│       │   ├── berita-detail.blade.php
│       │   ├── berita-edit.blade.php
│       │   ├── berita-tambah.blade.php
│       │   ├── dashboard.blade.php
│       │   ├── dosen.blade.php
│       │   ├── dosen-detail.blade.php
│       │   ├── jenis-dokumen.blade.php
│       │   ├── kaprodi-kegiatan-mahasiswa-detail-show.blade.php
│       │   ├── kaprodi-profil.blade.php
│       │   ├── kategori-kegiatan.blade.php
│       │   ├── kegiatan.blade.php
│       │   ├── kegiatan-dosen.blade.php
│       │   ├── kegiatan-dosen-detail-show.blade.php
│       │   ├── kegiatan-mahasiswa.blade.php
│       │   ├── kurikulum.blade.php
│       │   ├── mahasiswa.blade.php
│       │   ├── mahasiswa-detail.blade.php
│       │   ├── mata-kuliah.blade.php
│       │   ├── mata-kuliah-edit.blade.php
│       │   ├── mata-kuliah-tambah.blade.php
│       │   ├── pengajuan.blade.php
│       │   ├── placeholder.blade.php
│       │   ├── prestasi.blade.php
│       │   ├── prestasi-detail.blade.php
│       │   └── tinjau-pengajuan.blade.php
│       ├── layouts/
│       │   ├── partials/
│       │   │   ├── pengajuan-header.blade.php
│       │   │   ├── pengajuan-sidebar.blade.php
│       │   │   └── sidebar.blade.php
│       │   ├── admin.blade.php
│       │   ├── app.blade.php
│       │   ├── kaprodi.blade.php
│       │   ├── mahasiswa.blade.php
│       │   ├── mahasiswa-tim.blade.php
│       │   ├── pengajuan-app.blade.php
│       │   └── publik.blade.php
│       ├── mahasiswa/
│       │   ├── dashboard/
│       │   │   └── dashboard.blade.php
│       │   ├── kegiatan/
│       │   │   └── index.blade.php
│       │   ├── mahasiswa/
│       │   │   ├── pagination/
│       │   │   │   └── mahasiswa.blade.php
│       │   │   ├── dashboard-mhs.blade.php
│       │   │   ├── mata-kuliah.blade.php
│       │   │   ├── prestasi.blade.php
│       │   │   ├── prestasi-tambah.blade.php
│       │   │   └── profil.blade.php
│       │   └── pengajuan/
│       │       ├── berita/
│       │       │   ├── form.blade.php
│       │       │   ├── index.blade.php
│       │       │   └── show.blade.php
│       │       ├── create.blade.php
│       │       ├── dashboard.blade.php
│       │       ├── index.blade.php
│       │       └── show.blade.php
│       ├── mata-kuliah/
│       │   ├── _stats.blade.php
│       │   └── _tabel.blade.php
│       ├── pengajuan/
│       │   ├── berita/
│       │   │   └── create.blade.php
│       │   ├── partials/
│       │   │   ├── advisor.blade.php
│       │   │   └── team.blade.php
│       │   ├── create.blade.php
│       │   ├── dosen.blade.php
│       │   ├── index.blade.php
│       │   ├── mahasiswa.blade.php
│       │   └── show.blade.php
│       ├── akreditasi.blade.php
│       ├── api-docs.blade.php
│       ├── app.blade.php
│       ├── Backup file
│       ├── berita-index.blade.php
│       ├── berita-show.blade.php
│       ├── dashboard.blade.php
│       ├── fasilitas.blade.php
│       ├── header.blade.php
│       ├── home.blade.php
│       ├── kegiatan.blade.php
│       ├── kurikulum.blade.php
│       ├── login.blade.php
│       ├── mata-kuliah.blade.php
│       ├── navbar.blade.php
│       ├── pemulihan.blade.php
│       ├── prestasi-index.blade.php
│       ├── prestasi-show.blade.php
│       ├── profil.blade.php
│       └── welcome.blade.php
├── routes/
│   ├── admin-kaprodi.php
│   ├── api.php
│   ├── api-docs.php
│   ├── console.php
│   ├── mahasiswa.php
│   ├── pengajuan.php
│   └── web.php
├── storage/
│   ├── app/
│   │   ├── private/
│   │   │   ├── admin/
│   │   │   │   ├── akreditasi.json
│   │   │   │   ├── kurikulum.json
│   │   │   │   ├── mahasiswa.json
│   │   │   │   └── mata-kuliah.json
│   │   │   └── .gitignore
│   │   ├── public/
│   │   │   └── .gitignore
│   │   └── .gitignore
│   ├── framework/
│   │   ├── cache/
│   │   ├── sessions/
│   │   ├── testing/
│   │   └── views/
│   └── logs/
│       ├── .gitignore
│       └── laravel.log
├── tests/
│   ├── Feature/
│   │   ├── Api/
│   │   │   ├── AngkatanApiTest.php
│   │   │   ├── AuthApiTest.php
│   │   │   ├── KategoriKegiatanApiTest.php
│   │   │   ├── PengajuanAdminApiTest.php
│   │   │   ├── PublikApiTest.php
│   │   │   └── TulisApiTest.php
│   │   ├── BackendKel3/
│   │   │   ├── AksesDosenTest.php
│   │   │   └── PrestasiDosenTest.php
│   │   ├── ApiClientTest.php
│   │   ├── ApiDokumentasiTest.php
│   │   ├── ApiTokenSesiTest.php
│   │   ├── AuthenticationSecurityTest.php
│   │   ├── DatabaseRollbackBaselineTest.php
│   │   ├── DatabaseSeederTest.php
│   │   ├── DatabaseSuratAkreditasiTest.php
│   │   ├── DbStoreTest.php
│   │   ├── DosenAccountMenuTest.php
│   │   ├── DosenRoutesTest.php
│   │   ├── DynamicActivityErdTest.php
│   │   ├── ExampleTest.php
│   │   ├── FrontendLayoutTest.php
│   │   ├── KaprodiJenisDokumenTest.php
│   │   ├── KegiatanKategoriRisetTest.php
│   │   ├── KegiatanTanggalCompatibilityTest.php
│   │   ├── LoginAkunNonaktifTest.php
│   │   ├── MahasiswaDashboardIdentityTest.php
│   │   ├── MahasiswaPrestasiDriveLinkTest.php
│   │   ├── PenempatanKelasMahasiswaTest.php
│   │   ├── PengajaranTest.php
│   │   └── PengajuanFormTest.php
│   ├── Unit/
│   │   └── ExampleTest.php
│   └── TestCase.php
├── vendor/                         # Dependensi PHP (Composer), tidak dirinci
├── node_modules/                   # Dependensi JavaScript, tidak dirinci
├── .editorconfig
├── .env
├── .env.example
├── .gitattributes
├── .gitignore
├── .phpunit.result.cache
├── artisan
├── beda.txt
├── composer.json
├── composer.lock
├── Composer-Setup.exe
├── CONTRIBUTING.md
├── package.json
├── package-lock.json
├── phpunit.xml
├── README.md
└── vite.config.js
```

## Ringkasan

- `app/`: kode aplikasi Laravel (controller, model, middleware, repository, dan dukungan API).
- `resources/views/`: tampilan Blade untuk admin, dosen, kaprodi, mahasiswa, dan halaman publik.
- `routes/`: definisi rute web, API, autentikasi/kaprodi, mahasiswa, pengajuan, dan dokumentasi API.
- `database/`: migration, factory, dan seeder.
- `docs/` dan `postman/`: dokumentasi teknis/API serta koleksi untuk pengujian API.
- `tests/`: pengujian fitur dan unit.
- `public/` dan `resources/`: aset publik, CSS, JavaScript, dan sumber tampilan.
- `storage/`: data aplikasi dan keluaran runtime Laravel; isi cache/session/view terkompilasi dapat berubah otomatis.

> Catatan: `.env` tercantum sebagai nama file saja. Jangan membagikan nilai rahasia di dalamnya. Struktur ini dapat berubah saat file proyek ditambah atau dihapus.
