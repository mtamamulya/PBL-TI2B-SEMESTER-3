<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('formBerita', (id) => ({
        sumber: '', kategori: '', kegiatan: '', prestasi: '', judul: '', isi: '', catatan: '',
        namaFile: '', galatFile: '', error: '', loading: false, siap: false,
        kegiatanList: [], prestasiList: [], kategoriList: [],

        async init() {
            try {
                const [opsi, detail] = await Promise.all([apiBerita('/opsi'), id ? apiBerita('/' + id) : null]);
                this.kegiatanList = opsi.data.kegiatan;
                this.prestasiList = opsi.data.prestasi;
                this.kategoriList = opsi.data.kategori;
                await this.$nextTick();
                if (detail) {
                    const b = detail.data;
                    if (!['draft', 'revisi', 'direvisi'].includes(b.status)) throw new Error('Berita ini sudah dikunci.');
                    this.judul = b.judul_berita;
                    this.isi = b.isi_berita;
                    this.catatan = b.catatan_revisi || '';
                    this.sumber = b.id_prestasi ? 'prestasi' : b.id_kegiatan ? 'kegiatan' : 'mandiri';
                    this.kategori = String(b.id_kategori || '');
                    this.kegiatan = String(b.id_kegiatan || '');
                    this.prestasi = String(b.id_prestasi || '');
                    this.namaFile = b.sampul ? 'Pertahankan sampul saat ini' : '';
                }
                this.siap = true;
            } catch (e) {
                this.error = e.message;
                return;
            }
            this.$nextTick(() => {
                if (!this.$refs.editorIsi || typeof ClassicEditor === 'undefined') return;

                ClassicEditor.create(this.$refs.editorIsi, {
                    placeholder: 'Tuliskan isi berita di sini',
                    toolbar: [
                        'heading', '|',
                        'bold', 'italic', 'link',
                        'bulletedList', 'numberedList', 'blockQuote', '|',
                        'undo', 'redo'
                    ],
                }).then((editor) => {
                    if (this.isi) {
                        editor.setData(this.isi);
                    }
                    editor.model.document.on('change:data', () => {
                        this.isi = editor.getData();
                    });
                }).catch((error) => {
                    console.error('Gagal memuat CKEditor:', error);
                });
            });


        },

        get panjangTeks() {
            const div = document.createElement('div');
            div.innerHTML = this.isi || '';
            return (div.textContent || div.innerText || '').trim().length;
        },

        pilihFile(e) {
            const f = e.target.files[0];
            this.galatFile = '';
            if (!f) { this.namaFile = ''; return; }

            if (f.size > 2 * 1024 * 1024) {
                e.target.value = '';
                this.namaFile = '';
                this.galatFile = 'Ukuran file melebihi 2 MB.';
                return;
            }

            const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
            if (!validTypes.includes(f.type)) {
                e.target.value = '';
                this.namaFile = '';
                this.galatFile = 'Format file harus JPG, JPEG, PNG, atau WebP.';
                return;
            }

            this.namaFile = f.name;
        },

        async simpanDraft() {
            if (this.judul.trim() === '') {
                this.$dispatch('tampil-alert', {
                    tipe: 'peringatan',
                    judul: 'Judul belum diisi',
                    pesan: 'Isi judul berita sebelum menyimpan draf.'
                });
                document.getElementById('judul').focus();
                return;
            }

            await this.submitForm('draft');
        },

        async ajukan() {
            if (this.panjangTeks === 0) {
                this.$dispatch('tampil-alert', {
                    tipe: 'peringatan',
                    judul: 'Isi berita belum diisi',
                    pesan: 'Tuliskan isi berita terlebih dahulu sebelum mengajukan.'
                });
                return;
            }

            if (this.panjangTeks > 1000) {
                this.$dispatch('tampil-alert', {
                    tipe: 'peringatan',
                    judul: 'Isi berita terlalu panjang',
                    pesan: 'Isi berita maksimal 1.000 karakter.'
                });
                return;
            }

            await this.submitForm('diajukan');
        },

        async submitForm(status) {
            if (this.loading || !this.siap) return;
            if (status === 'draft' && !this.sumber) this.sumber = 'mandiri';
            if (!this.sumber || (this.sumber === 'prestasi' && !this.prestasi) || (this.sumber === 'kegiatan' && !this.kegiatan)) {
                this.error = 'Pilih sumber berita yang sesuai.';
                return;
            }
            this.error = '';
            this.loading = true;

            try {
                const formData = new FormData();
                formData.append('sumber', this.sumber);
                formData.append('judul', this.judul);
                formData.append('isi', this.isi);
                formData.append('status', status);

                formData.append('id_kegiatan', this.sumber === 'kegiatan' ? this.kegiatan : '');
                formData.append('id_prestasi', this.sumber === 'prestasi' ? this.prestasi : '');
                formData.append('id_kategori', this.sumber === 'mandiri' ? this.kategori : '');
                if (id) formData.append('_method', 'PATCH');

                const fotoInput = document.querySelector('input[name="foto"]');
                if (fotoInput && fotoInput.files[0]) {
                    formData.append('foto', fotoInput.files[0]);
                }

                await apiBerita(id ? '/' + id : '', 'POST', formData);

                this.$dispatch('tampil-alert', {
                    tipe: 'sukses',
                    judul: status === 'diajukan' ? 'Berita berhasil diajukan' : 'Draf tersimpan',
                    pesan: status === 'diajukan'
                        ? 'Berita Anda sedang menunggu review admin.'
                        : 'Draf berhasil disimpan.'
                });

                window.location.assign(@js(route('berita.index')));

            } catch (e) {
                this.error = e.message;
                this.$dispatch('tampil-alert', {
                    tipe: 'error',
                    judul: 'Gagal menyimpan',
                    pesan: e.message
                });
            } finally {
                this.loading = false;
            }
        }
    }));
});
</script>