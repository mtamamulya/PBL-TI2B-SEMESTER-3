public function simpan(Request $request)
    {
        // 1. VALIDASI
        $validated = $request->validate([
            'sumber' => 'required|in:sebelumnya,mandiri',
            'id_kegiatan' => 'required_if:sumber,sebelumnya',
            'kegiatan' => 'required_if:sumber,sebelumnya',
            'nama_lomba' => 'required|string|max:255',
            'penyelenggara' => 'required|string|max:150',
            'lokasi' => 'required|string|max:150',
            'tingkat' => 'required|in:Kabupaten/Kota,Provinsi,Nasional,Internasional',
            'capaian' => 'required|string|max:100',
            'jenis' => 'required|in:Akademik,Nonakademik',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'bentuk' => 'required|in:Individu,Tim',
            'dokumen' => 'required|array|min:1',
            'dokumen.*.url' => [
                'required',
                'url',
                function ($attribute, $value, $fail) {
                    $host = strtolower(parse_url($value, PHP_URL_HOST) ?? '');
                    $allowed = ['drive.google.com', 'docs.google.com', '1drv.ms', 'onedrive.live.com'];
                    if (!in_array($host, $allowed) && !str_ends_with($host, '.sharepoint.com')) {
                        $fail('Tautan harus berasal dari Google Drive atau OneDrive.');
                    }
                },
            ],
            'dokumen.*.jenis' => 'required|string',
        ]);

        // 2. SIMPAN KE DATABASE DENGAN TRANSACTION
        DB::beginTransaction();
        try {
            $userId = auth()->id();

            // Mapping format Enum dari form ke database
            $jenisPrestasi = $request->jenis === 'Nonakademik' ? 'non-akademik' : 'akademik';
            $tingkatKejuaraan = strtolower($request->tingkat); // Nasional -> nasional
            $bentukPartisipasi = strtolower($request->bentuk); // Individu -> individu

            // Insert tabel prestasi
            $idPrestasi = DB::table('prestasi')->insertGetId([
                'id_user' => $userId,
                'created_by' => $userId,
                'id_kegiatan' => $request->sumber === 'sebelumnya' ? $request->id_kegiatan : null,
                'capaian' => $request->capaian,
                'jenis_prestasi' => $jenisPrestasi,
                'nama_lomba' => $request->nama_lomba,
                'penyelenggara' => $request->penyelenggara,
                'lokasi' => $request->lokasi,
                'tingkat_kejuaraan' => $tingkatKejuaraan,
                'tanggal_mulai' => $request->tanggal_mulai,
                'tanggal_selesai' => $request->tanggal_selesai,
                'bentuk_partisipasi' => $bentukPartisipasi,
                'created_at' => now(),
                'updated_at' => now(),
            ], 'id_prestasi');

            // Insert tabel bukti_prestasi
            foreach ($request->dokumen as $dok) {
                // Cari id_jenis_dokumen berdasarkan nama. Jika tidak ada, buat baru agar tidak error constraint.
                $idJenisDokumen = DB::table('jenis_dokumen')
                    ->where('nama_jenis_dokumen', $dok['jenis'])
                    ->value('id_jenis_dokumen');

                if (!$idJenisDokumen) {
                    $idJenisDokumen = DB::table('jenis_dokumen')->insertGetId([
                        'nama_jenis_dokumen' => $dok['jenis']
                    ], 'id_jenis_dokumen');
                }

                DB::table('bukti_prestasi')->insert([
                    'id_prestasi' => $idPrestasi,
                    'id_jenis_dokumen' => $idJenisDokumen,
                    'link' => $dok['url'],
                    'nama_file' => 'Bukti ' . $dok['jenis'], // Nama default karena link tidak punya nama file fisik
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::commit();

            // 3. REDIRECT
            return redirect()->route('mahasiswa.prestasi')->with('status', 'Prestasi berhasil ditambahkan!');
            
        } catch (\Exception $e) {
            DB::rollBack();
            // Jika ada error database, kembalikan ke form beserta pesan error-nya
            return back()->withInput()->withErrors(['error' => 'Gagal menyimpan data: ' . $e->getMessage()]);
        }
    }