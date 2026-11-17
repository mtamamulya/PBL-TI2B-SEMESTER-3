<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('formPrestasi', (id) => ({
            siap: false, menyimpan: false, error: '', errorAnggota: '',
            sumber: '', bentuk: '', nama: '', penyelenggara: '', tingkat: '', jenis: '',
            mulai: '', selesai: '', capaian: '', kategori: '', lokasi: '', deskripsi: '',
            kegiatanLama: [], idKegiatan: '', kandidat: [], anggota: [], pilihanAnggota: '',
            dokumen: [{ id: 1, jenis: '', tautan: '' }], nextId: 2,

            async muatAwal() {
                try {
                    const { data } = await apiPrestasi('/opsi');
                    this.kegiatanLama = data.kegiatan;
                    this.kandidat = data.kandidat;
                    this.anggota = [{ ...data.pengaju, peran: 'Ketua', kunci: true }];
                    if (id) {
                        const { data: p } = await apiPrestasi('/' + id);
                        this.sumber = p.id_kegiatan ? 'kegiatan' : 'mandiri';
                        this.idKegiatan = p.id_kegiatan || '';
                        this.nama = p.nama_lomba;
                        this.penyelenggara = p.penyelenggara;
                        this.tingkat = p.tingkat_kejuaraan?.toLowerCase();
                        this.jenis = p.jenis_prestasi?.toLowerCase();
                        this.mulai = p.tanggal_mulai?.slice(0, 10);
                        this.selesai = p.tanggal_selesai?.slice(0, 10);
                        this.capaian = p.capaian;
                        this.kategori = p.bidang_lomba || '';
                        this.bentuk = p.bentuk_partisipasi === 'tim' ? 'Tim' : 'Individu';
                        this.lokasi = p.lokasi;
                        this.deskripsi = p.deskripsi || '';
                        if (p.anggota.length) this.anggota = p.anggota.map(a => ({
                            id_user: a.id_user, nama: a.user?.dosen?.nama || a.user?.mahasiswa?.nama || a.user?.email,
                            peran: a.peran, status: a.user?.dosen ? 'Dosen' : 'Mahasiswa', kunci: a.id_user === p.id_user
                        }));
                        if (p.bukti_prestasi.length) this.dokumen = p.bukti_prestasi.map((b, i) => ({
                            id: i + 1, jenis: b.jenis_dokumen.nama_jenis_dokumen, tautan: b.link
                        }));
                        this.nextId = this.dokumen.length + 1;
                    }
                    this.siap = true;
                } catch (e) { this.error = e.message; }
            },
            isiKegiatan() {
                const k = this.kegiatanLama.find(k => String(k.id) === String(this.idKegiatan));
                if (k) {
                    this.nama = k.nama;
                    this.penyelenggara = k.penyelenggara || '';
                    this.mulai = k.mulai || '';
                    this.selesai = k.selesai || '';
                }
            },
            tambah() {
                const k = this.kandidat.find(k => String(k.id_user) === String(this.pilihanAnggota));
                if (!k || this.anggota.some(a => a.id_user === k.id_user)) {
                    this.errorAnggota = 'Pilih anggota yang belum ada di tim.';
                    return;
                }
                this.anggota.push({ ...k, peran: 'Anggota', kunci: false });
                this.errorAnggota = ''; this.pilihanAnggota = '';
            },
            hapus(i) { this.anggota.splice(i, 1); },
            tambahDokumen() { this.dokumen.push({ id: this.nextId++, jenis: '', tautan: '' }); },
            hapusDokumen(i) { this.dokumen.splice(i, 1); },

            async simpan() {
                if (!this.siap || this.menyimpan) return;
                this.menyimpan = true; this.error = '';
                try {
                    await apiPrestasi(id ? '/' + id : '', id ? 'PUT' : 'POST', {
                        sumber: this.sumber, id_kegiatan: this.sumber === 'kegiatan' ? this.idKegiatan : null,
                        nama_prestasi: this.nama, penyelenggara: this.penyelenggara, tingkat: this.tingkat,
                        jenis: this.jenis, tanggal_mulai: this.mulai, tanggal_selesai: this.selesai,
                        capaian: this.capaian, kategori: this.kategori, bentuk: this.bentuk,
                        lokasi: this.lokasi, deskripsi: this.deskripsi, anggota: this.anggota, dokumen: this.dokumen
                    });
                    window.location.assign(@js(route('prestasi.index')));
                } catch (e) { this.error = e.message; }
                finally { this.menyimpan = false; }
            }
        }));
    });
</script>
