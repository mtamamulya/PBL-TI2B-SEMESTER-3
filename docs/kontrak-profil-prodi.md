# Kontrak Data Profil Prodi

Sumber kebenaran kode: `app/Support/ProfilProdi.php` (kunci, aturan validasi, data contoh).
Bentuk yang sama dipakai halaman admin/kaprodi, landing page, dan API (`GET/PUT /api/v1/prodi/profil`, Fase 4).

```json
{
  "kode_prodi": "TRK",
  "nama_prodi": "Teknologi Rekayasa Komputer",
  "jenjang": "D4",
  "deskripsi": "teks atau null",
  "visi": "teks atau null",
  "misi": ["poin 1", "poin 2"],
  "tujuan": ["poin 1"],
  "profil_lulusan": [{ "judul": "Software Engineer", "deskripsi": "teks atau null" }],
  "email": "alamat email atau null",
  "no_wa": "6281234567890 atau null",
  "alamat": "teks atau null",
  "instagram": "https://... atau null",
  "youtube": null,
  "tiktok": null,
  "facebook": null,
  "logo_url": "URL logo utama atau null",
  "logo_footer_url": "URL logo footer atau null (kosong -> pakai logo_url)"
}
```

Aturan:
- `misi`, `tujuan`, `profil_lulusan` selalu array (boleh `[]`), maksimal 20 butir.
- `no_wa` disimpan format `62xxxxxxxxxx` (input `0812…`/`+62…` dinormalkan server). Link WhatsApp: `https://wa.me/{no_wa}`.
- Kolom sosial media boleh `null`; landing menyembunyikan ikon yang kosong.
- `PUT` boleh sebagian (kirim hanya kunci yang diubah). Logo (`logo_url`, `logo_footer_url`) tidak lewat PUT, memakai endpoint unggah terpisah.
- Landing harus tetap tampil memakai `ProfilProdi::bawaan()` bila data tidak bisa diambil.

Pembagian file tampilan (Fase 2): `resources/views/admin/profil-prodi/`
- 2A: `_identitas`, `_visi-deskripsi`, `_kontak-sosial`
- 2B: `_daftar-poin`, `_logo`
