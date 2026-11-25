<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('formBerita', (awal) => ({
        sumber: awal.sumber,
        kategori: awal.kategori,
        judul: awal.judul,
        isi: awal.isi,
        namaFile: '',
        galatFile: '',
        loading: false,
        kegiatanList: [],

        async init() {
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

            // Load daftar kegiatan dari API
            try {
                console.log('Memuat daftar kegiatan dari API...');
                const response = await apiBerita('/opsi');
                console.log('Response dari API:', response);
                
                if (response.success && response.data) {
                    this.kegiatanList = response.data.kegiatan || [];
                    console.log('Daftar kegiatan:', this.kegiatanList);
                } else {
                    console.error('Response tidak valid:', response);
                    this.kegiatanList = [];
                }
            } catch (e) {
                console.error('Gagal memuat daftar kegiatan:', e);
                this.kegiatanList = [];
                
                // Tampilkan error ke user
                this.$dispatch('tampil-alert', { 
                    tipe: 'error', 
                    judul: 'Gagal memuat data', 
                    pesan: 'Tidak dapat memuat daftar kegiatan. Silakan refresh halaman.' 
                });
            }
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
            if (this.loading) return;
            this.loading = true;

            try {
                const formData = new FormData();
                formData.append('sumber', this.sumber);
                formData.append('judul', this.judul);
                formData.append('isi', this.isi);
                formData.append('status', status);

                if (this.sumber === 'kegiatan') {
                    const kegiatanInput = document.getElementById('kegiatan-lama');
                    if (kegiatanInput && kegiatanInput.value) {
                        formData.append('kegiatan_sebelumnya', kegiatanInput.value);
                    }
                }

                if (this.sumber === 'mandiri' && this.kategori) {
                    formData.append('kategori', this.kategori);
                }

                const fotoInput = document.querySelector('input[name="foto"]');
                if (fotoInput && fotoInput.files[0]) {
                    formData.append('foto', fotoInput.files[0]);
                }

                console.log('Mengirim data ke API:', {
                    sumber: this.sumber,
                    judul: this.judul,
                    status: status,
                    kegiatan_sebelumnya: this.sumber === 'kegiatan' ? document.getElementById('kegiatan-lama')?.value : null,
                    kategori: this.sumber === 'mandiri' ? this.kategori : null,
                    foto: fotoInput?.files[0]?.name || null
                });

                const response = await apiBerita('', 'POST', formData);
                console.log('Response dari API:', response);

                this.$dispatch('tampil-alert', { 
                    tipe: 'sukses', 
                    judul: status === 'diajukan' ? 'Berita berhasil diajukan' : 'Draf tersimpan', 
                    pesan: status === 'diajukan' 
                        ? 'Berita Anda sedang menunggu review admin.' 
                        : 'Draf berhasil disimpan.' 
                });

                setTimeout(() => {
                    window.location.href = '{{ route("berita.index") }}';
                }, 1500);

            } catch (e) {
                console.error('Error saat submit:', e);
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