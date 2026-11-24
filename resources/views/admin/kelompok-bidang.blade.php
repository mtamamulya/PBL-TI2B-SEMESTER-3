@extends($layout ?? 'layouts.admin')

@section('title', 'Kelompok Bidang')

@section('content')
{{-- CSS DataTables + penyesuaian tampilan agar identik dengan Jenis Dokumen --}}
<link rel="stylesheet" href="https://cdn.datatables.net/2.1.8/css/dataTables.dataTables.min.css">
<style>
    /* ---------- Kotak tabel ---------- */
    #tabel-wrap .dt-layout-row { margin: 0; }
    #tabel-wrap .dt-layout-row.dt-layout-table {
        border: 1px solid rgb(0 0 47 / .15);
        border-radius: 6px;
        overflow: hidden;
        background: #fff;
    }
    #tabel-wrap .dt-layout-table .dt-layout-cell { width: 100%; padding: 0; overflow-x: auto; }

    #tabel { width: 100%; min-width: 640px; table-layout: fixed; border-collapse: collapse; margin: 0 !important; font-size: 14px; color: #1C2024; }
    #tabel.dataTable > thead > tr > th:nth-child(1) { width: 80px; }
    #tabel.dataTable > thead > tr > th:nth-child(2) { width: 45%; }
    #tabel.dataTable > thead > tr > th:nth-child(4) { width: 140px; }

    /* Header: latar abu penuh, semua kolom rata kiri */
    #tabel.dataTable > thead > tr > th {
        padding: 8px; font-weight: 510; text-align: left;
        background: #F9F9FC;
        border-bottom: 1px solid rgb(0 0 47 / .15);
        color: #1C2024;
    }
    #tabel.dataTable > thead > tr > th .dt-column-header {
        display: flex; flex-direction: row !important; justify-content: flex-start !important; align-items: center; gap: 6px;
    }
    #tabel.dataTable > thead > tr > th .dt-column-title { flex-grow: 0 !important; }

    #tabel.dataTable > tbody > tr > td {
        height: 64px; padding: 8px; text-align: left;
        border-top: 1px solid rgb(0 0 47 / .15);
        box-shadow: none;
        vertical-align: middle;
    }
    #tabel.dataTable > tbody > tr:first-child > td { border-top: 0; }
    #tabel.dataTable > tbody > tr > td.dt-empty { padding: 24px; text-align: center; color: #A8A8A8; }

    /* Kolom No: beri jarak dari tepi kiri kotak */
    #tabel.dataTable > thead > tr > th:first-child,
    #tabel.dataTable > tbody > tr > td:first-child:not(.dt-empty) { padding-left: 20px; }

    /* ---------- Paginasi ---------- */
    #tabel-wrap .dt-layout-row:not(.dt-layout-table) { padding: 24px 0 0; justify-content: center; }
    #tabel-wrap .dt-layout-row:not(.dt-layout-table) .dt-layout-cell { width: 100%; padding: 0; display: flex; justify-content: center; }
    #tabel-wrap .dt-paging nav { display: flex; align-items: center; justify-content: center; gap: 8px; }

    #tabel-wrap .dt-paging .dt-paging-button {
        display: flex !important; align-items: center; justify-content: center;
        width: 32px; height: 32px; min-width: 32px; padding: 0 !important; margin: 0 !important;
        border: 1px solid #DFE3E8; border-radius: 4px !important;
        font-size: 14px; font-weight: 700; letter-spacing: -.16px; line-height: 1;
        box-shadow: none !important; cursor: pointer;
    }
    #tabel-wrap .dt-paging .dt-paging-button.disabled { cursor: not-allowed; opacity: .6; }

    #tabel-wrap .dt-paging .dt-paging-button:not(.previous):not(.next):not(.ellipsis),
    #tabel-wrap .dt-paging .dt-paging-button:not(.previous):not(.next):not(.ellipsis):hover {
        background: #fff !important; color: #0A0A0A !important; border-color: #DFE3E8 !important;
    }
    #tabel-wrap .dt-paging .dt-paging-button:not(.previous):not(.next):not(.ellipsis):hover { border-color: #2277FF !important; }
    #tabel-wrap .dt-paging .dt-paging-button.current:not(.previous):not(.next):not(.ellipsis),
    #tabel-wrap .dt-paging .dt-paging-button.current:not(.previous):not(.next):not(.ellipsis):hover,
    #tabel-wrap .dt-paging .dt-paging-button[aria-current="page"]:not(.previous):not(.next):not(.ellipsis),
    #tabel-wrap .dt-paging .dt-paging-button[aria-current="page"]:not(.previous):not(.next):not(.ellipsis):hover {
        color: #2277FF !important; border-color: #2277FF !important; background: #fff !important;
    }
    #tabel-wrap .dt-paging .dt-paging-button.ellipsis { border-color: transparent !important; background: none !important; color: #55555C !important; opacity: 1; cursor: default; }

    #tabel-wrap .dt-paging .dt-paging-button.previous,
    #tabel-wrap .dt-paging .dt-paging-button.next {
        font-size: 0 !important; background-repeat: no-repeat !important; background-position: center !important; background-size: 20px 20px !important;
    }
    #tabel-wrap .dt-paging .dt-paging-button.previous,
    #tabel-wrap .dt-paging .dt-paging-button.previous.disabled:hover {
        background-color: #2277FF !important; border-color: #2277FF !important;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23fff' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M15 6l-6 6l6 6'/%3E%3C/svg%3E") !important;
    }
    #tabel-wrap .dt-paging .dt-paging-button.next,
    #tabel-wrap .dt-paging .dt-paging-button.next.disabled:hover {
        background-color: #CDDEF9 !important; border-color: #2277FF !important;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%232277FF' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M9 6l6 6l-6 6'/%3E%3C/svg%3E") !important;
    }
    #tabel-wrap .dt-paging .dt-paging-button.previous:hover:not(.disabled) { background-color: #1A66E0 !important; border-color: #1A66E0 !important; }
    #tabel-wrap .dt-paging .dt-paging-button.next:hover:not(.disabled) { background-color: #BBD2F6 !important; }

    /* ---------- Kursor tangan pada tombol ---------- */
    #kb-tambah, #kb-batal, #kb-simpan { cursor: pointer; }
    #kb-simpan:disabled { cursor: not-allowed; }

    /* ---------- Modal konfirmasi hapus ---------- */
    #modalHapus { position: fixed; inset: 0; margin: auto; height: fit-content; width: 380px; max-width: calc(100vw - 32px); padding: 0; border: 0; background: transparent; overflow: visible; }
    #modalHapus::backdrop { background: rgb(10 10 10 / .45); }
    #modalHapus[open] { animation: modalMasuk .15s ease-out; }
    @keyframes modalMasuk { from { opacity: 0; transform: translateY(8px) scale(.98); } to { opacity: 1; transform: none; } }
    .modal-kartu { padding: 24px; border-radius: 12px; background: #fff; text-align: center; box-shadow: 0 20px 50px rgb(0 0 0 / .2); }
    .modal-ikon { display: flex; align-items: center; justify-content: center; width: 48px; height: 48px; margin: 0 auto 16px; border-radius: 9999px; background: #FFD9D9; color: #E5484D; }
    .modal-judul { font-size: 18px; font-weight: 700; line-height: 24px; color: #0A0A0A; }
    .modal-teks { margin-top: 8px; font-size: 14px; line-height: 20px; color: #55555C; }
    .modal-teks strong { font-weight: 600; color: #0A0A0A; word-break: break-word; }
    .modal-error { margin-top: 12px; font-size: 13px; color: #E5484D; }
    .modal-aksi { display: flex; gap: 12px; margin-top: 24px; }
    .modal-btn { flex: 1; height: 40px; border: 1px solid transparent; border-radius: 8px; font-size: 14px; font-weight: 500; cursor: pointer; }
    .modal-btn-batal { background: #fff; border-color: #E8E8EC; color: #2E2E2E; }
    .modal-btn-batal:hover { background: #F9F9FB; }
    .modal-btn-hapus { background: #E5484D; color: #fff; }
    .modal-btn-hapus:hover { background: #D13A3F; }
    .modal-btn-hapus:disabled { opacity: .7; cursor: not-allowed; }
</style>

<div class="px-6 pb-8 pt-4 md:px-8">
    <h1 class="text-[32px] font-bold leading-tight text-[#111]">Kelompok Bidang</h1>
    {{-- Breadcrumb: Dashboard & Kegiatan bisa diklik (hover biru underline), halaman aktif tidak --}}
    <nav aria-label="Breadcrumb" class="mt-[7px] text-xl leading-[26px] text-[#B9B9BF]">
        <a href="{{ route(($prefix ?? 'admin').'.dashboard') }}" class="transition-colors hover:text-[#226CFF] hover:underline">Dashboard</a>
        <span class="mx-1">/</span>
        <a href="{{ route(($prefix ?? 'admin').'.kegiatan.index') }}" class="transition-colors hover:text-[#226CFF] hover:underline">Kegiatan</a>
        <span class="mx-1">/</span>
        <span class="text-[#6E6E76]" aria-current="page">Kelompok Bidang</span>
    </nav>

    {{-- Alert Error API (Jika ada) --}}
    @if(!empty($apiError))
    <div class="mt-5 rounded-lg border border-[#F5B7B1] bg-[#FADBD8] px-4 py-3 text-sm text-[#922B21]">
        {{ $apiError }}
    </div>
    @endif

    <section class="mt-[26px] max-w-[1152px] rounded-lg border border-[#EFEFF1] bg-white p-[25px] shadow-[0_1px_3px_rgba(0,0,0,0.07)]">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="text-base font-medium leading-6 text-[#111]">Daftar Kelompok Bidang</h3>
                <p class="mt-1 text-[13px] leading-[18px] text-[#6E6E76]">Kelompok bidang penelitian yang tersedia untuk dosen</p>
            </div>
            <button type="button" id="kb-tambah"
                    class="flex h-[39px] items-center gap-2 rounded-[7px] bg-[#226CFF] px-[17px] text-sm font-medium text-white hover:bg-[#1b5ae0]">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14" /></svg>
                Kelompok Bidang
            </button>
        </div>

        {{-- Cari --}}
        <div class="relative mt-5">
            <svg class="absolute left-[15px] top-1/2 -translate-y-1/2 text-[#8E8E96]" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="7" /><path d="M21 21l-6-6" /></svg>
            <input id="kb-cari" type="text" placeholder="Cari kelompok bidang..."
                   class="h-10 w-full rounded-[7px] border border-[#DEDEE3] pl-10 pr-3 text-sm outline-none placeholder:text-[#757575] focus:border-[#226CFF]">
        </div>

        {{-- Tabel (isi <tbody> dan paginasi dibuat otomatis oleh DataTables) --}}
        <div id="tabel-wrap" class="mt-[19px]">
            <table id="tabel">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama Kelompok Bidang</th>
                        <th>Sub-bidang</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
            </table>
        </div>
    </section>
</div>

{{-- Pop-up tambah / edit --}}
<div id="kb-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4" role="dialog" aria-modal="true">
    <div class="w-full max-w-[540px] rounded-lg bg-white px-6 pb-5 pt-6 shadow-xl">
        <h2 id="kb-modal-judul" class="text-xl font-bold leading-6 text-[#111]">Tambah Kelompok Bidang</h2>
        <p id="kb-modal-sub" class="mt-1 text-xs text-[#B9B9BF]">Isi data kelompok bidang baru program studi</p>

        <div class="mt-5 space-y-4">
            <div>
                <label for="kb-nama" class="mb-1.5 block text-sm font-semibold text-[#111]">Nama Kelompok Bidang</label>
                <input id="kb-nama" type="text" maxlength="100" placeholder="Contoh: Rekayasa Perangkat Lunak"
                       class="h-9 w-full rounded-[5px] border border-[#DEDEE3] px-3 text-xs outline-none placeholder:text-[#B0B0B8] focus:border-[#226CFF]">
            </div>

            <div>
                <label for="kb-induk" class="mb-1.5 block text-sm font-semibold text-[#111]">Induk Rujukan (Opsional)</label>
                <select id="kb-induk"
                        class="h-9 w-full rounded-[5px] border border-[#DEDEE3] bg-white px-3 text-xs text-[#111] outline-none focus:border-[#226CFF]">
                    <option value="">Tanpa Induk (Bidang Utama)</option>
                </select>
                <p class="mt-1 text-[11px] text-[#8E8E96]">Kosongkan jika ini merupakan bidang utama.</p>
            </div>

            <p id="kb-error" class="hidden text-[13px] text-[#E5484D]"></p>
        </div>

        <div class="mt-4 flex justify-end gap-2">
            <button type="button" id="kb-batal"
                    class="h-8 rounded-md bg-[#FF4B4B] px-4 text-xs font-semibold text-white hover:bg-[#e63e3e]">Batal</button>
            <button type="button" id="kb-simpan"
                    class="h-8 rounded-md bg-[#226CFF] px-4 text-xs font-semibold text-white hover:bg-[#1b5ae0] disabled:opacity-60">Simpan</button>
        </div>
    </div>
</div>

{{-- Modal konfirmasi hapus --}}
<dialog id="modalHapus" aria-labelledby="modalHapusJudul" aria-describedby="modalHapusTeks">
    <div class="modal-kartu">
        <div class="modal-ikon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7l16 0" /><path d="M10 11l0 6" /><path d="M14 11l0 6" /><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" /><path d="M9 7v-3a1 1 0 0 1 1 -1h6a1 1 0 0 1 1 1v3" /></svg>
        </div>
        <h2 id="modalHapusJudul" class="modal-judul">Hapus kelompok bidang?</h2>
        <p id="modalHapusTeks" class="modal-teks"><strong id="modalHapusNama"></strong> akan dihapus permanen.</p>
        <p id="modalHapusError" class="modal-error" hidden></p>
        <div class="modal-aksi">
            <button type="button" id="modalHapusBatal" class="modal-btn modal-btn-batal">Batal</button>
            <button type="button" id="modalHapusYa" class="modal-btn modal-btn-hapus">Hapus</button>
        </div>
    </div>
</dialog>
@endsection

@push('scripts')
{{-- Hapus baris jQuery ini kalau layout admin/kaprodi kamu sudah memuat jQuery 3.x --}}
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.min.js"></script>
<script>
(() => {
    // Data dummy awal (akan otomatis memakai $rows dari controller jika tersedia)
    const serverRows = @json($rows ?? []);
    const rows = serverRows.length > 0 ? serverRows.map((r, idx) => ({
        id: r.id_kelompok_bidang ?? r.id ?? (idx + 1),
        nama_kelompok_bidang: r.nama_kelompok_bidang ?? 'Tanpa Nama',
        id_rujukan: r.id_rujukan ?? null,
        nama_rujukan: r.nama_rujukan ?? (r.id_rujukan === 1 ? 'Rekayasa Perangkat Lunak' : null),
        total_turunan: r.total_turunan ?? (idx === 0 ? 2 : 0)
    })) : [
        { id: 1, nama_kelompok_bidang: 'Rekayasa Perangkat Lunak', id_rujukan: null, nama_rujukan: null, total_turunan: 2 },
        { id: 2, nama_kelompok_bidang: 'Pengembangan Web & Mobile', id_rujukan: 1, nama_rujukan: 'Rekayasa Perangkat Lunak', total_turunan: 0 }
    ];

    let editingId = null;
    let idHapus = null;

    const $ = (id) => document.getElementById(id);
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) =>
        ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    const IKON_EDIT = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h4L18.5 9.5a2.828 2.828 0 1 0-4-4L4 16v4M13.5 6.5l4 4"/></svg>';
    const IKON_HAPUS = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7l16 0" /><path d="M10 11l0 6" /><path d="M14 11l0 6" /><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" /><path d="M9 7v-3a1 1 0 0 1 1 -1h6a1 1 0 0 1 1 1v3" /></svg>';

    // ---------- DataTables ----------
    const table = new DataTable('#tabel', {
        data: rows,
        autoWidth: false,
        pageLength: 10,
        order: [],
        columns: [
            { data: null, orderable: false, searchable: false, defaultContent: '' },
            {
                data: 'nama_kelompok_bidang',
                render: (d, type, r) => {
                    if (type !== 'display') return d ?? '';
                    const badge = r.nama_rujukan
                        ? `<div class="mt-1 inline-flex items-center rounded border border-[#DEDEE3] bg-[#F9F9FC] px-2 py-0.5 text-xs font-medium text-[#6E6E76]">Induk: ${esc(r.nama_rujukan)}</div>`
                        : '';
                    return `<div><div class="font-medium text-[#1C2024]">${esc(d)}</div>${badge}</div>`;
                }
            },
            {
                data: 'total_turunan',
                render: (d, type) => type === 'display'
                    ? `<span class="text-[#6E6E76]">${Number(d ?? 0)} Sub-bidang</span>`
                    : Number(d ?? 0)
            },
            {
                data: null, orderable: false, searchable: false,
                render: (d, type, r) => `
                    <div class="flex items-center gap-3">
                        <button type="button" data-act="edit" data-id="${esc(r.id)}" title="Edit" aria-label="Edit"
                                class="rounded-md bg-[#CDDEF9] p-1.5 text-[#2277FF] hover:bg-[#BBD2F6] cursor-pointer">${IKON_EDIT}</button>
                        <button type="button" data-act="hapus" data-id="${esc(r.id)}" title="Hapus" aria-label="Hapus"
                                class="rounded-md bg-[#FFD9D9] p-1.5 text-[#E5484D] hover:bg-[#FFC9C9] cursor-pointer">${IKON_HAPUS}</button>
                    </div>`
            }
        ],
        layout: { topStart: null, topEnd: null, bottomStart: null, bottomEnd: null, bottom: 'paging' },
        pagingType: 'simple_numbers',
        language: {
            emptyTable: 'Tidak ada kelompok bidang.',
            zeroRecords: 'Tidak ada kelompok bidang.',
            paginate: { previous: 'Sebelumnya', next: 'Berikutnya' }
        }
    });

    function isiNomor() {
        const mulai = table.page.info().start;
        table.column(0, { page: 'current' }).nodes().each((cell, i) => { cell.textContent = mulai + i + 1; });
    }
    function togglePager() {
        const p = document.querySelector('#tabel-wrap .dt-paging');
        if (p) p.style.display = table.page.info().pages > 1 ? '' : 'none';
    }
    table.on('draw.dt', () => { isiNomor(); togglePager(); });
    isiNomor();
    togglePager();

    const cariBaris = (id) => table.row((idx, data) => String(data.id) === String(id));

    function pindahJikaHalamanKosong() {
        const i = table.page.info();
        if (i.pages > 0 && i.page >= i.pages) table.page(i.pages - 1).draw('page');
    }

    function isiDropdownInduk(excludeId) {
        const sel = $('kb-induk');
        sel.innerHTML = '<option value="">Tanpa Induk (Bidang Utama)</option>';
        table.rows().data().each((item) => {
            if (excludeId !== null && String(item.id) === String(excludeId)) return;
            const opt = document.createElement('option');
            opt.value = item.id;
            opt.textContent = item.nama_kelompok_bidang;
            sel.appendChild(opt);
        });
    }

    // ---------- Pop-up tambah / edit (Mode Dummy Frontend) ----------
    function bukaModal(row) {
        editingId = row ? row.id : null;
        $('kb-modal-judul').textContent = row ? 'Edit Kelompok Bidang' : 'Tambah Kelompok Bidang';
        $('kb-modal-sub').textContent = row
            ? 'Ubah data kelompok bidang program studi'
            : 'Isi data kelompok bidang baru program studi';
        isiDropdownInduk(editingId);
        $('kb-nama').value = row ? row.nama_kelompok_bidang : '';
        $('kb-induk').value = row && row.id_rujukan ? row.id_rujukan : '';
        tampilError('');
        $('kb-modal').classList.remove('hidden');
        $('kb-modal').classList.add('flex');
        $('kb-nama').focus();
    }

    function tutupModal() {
        $('kb-modal').classList.add('hidden');
        $('kb-modal').classList.remove('flex');
    }

    function tampilError(pesan) {
        const el = $('kb-error');
        el.textContent = pesan;
        el.classList.toggle('hidden', !pesan);
    }

    function simpanDummy() {
        const nama = $('kb-nama').value.trim();
        const indukVal = $('kb-induk').value;
        if (!nama) { tampilError('Nama kelompok bidang wajib diisi.'); return; }

        let namaInduk = null;
        if (indukVal) {
            const indukRow = cariBaris(indukVal).data();
            namaInduk = indukRow ? indukRow.nama_kelompok_bidang : null;
        }

        if (editingId === null) {
            const newRow = {
                id: Date.now(),
                nama_kelompok_bidang: nama,
                id_rujukan: indukVal ? Number(indukVal) : null,
                nama_rujukan: namaInduk,
                total_turunan: 0
            };
            table.order([]);
            table.row.add(newRow).draw();
            table.page('last').draw('page');
        } else {
            const existing = cariBaris(editingId).data();
            const updatedRow = {
                ...existing,
                nama_kelompok_bidang: nama,
                id_rujukan: indukVal ? Number(indukVal) : null,
                nama_rujukan: namaInduk
            };
            cariBaris(editingId).data(updatedRow).draw(false);
        }
        tutupModal();
    }

    // ---------- Modal konfirmasi hapus (Mode Dummy Frontend) ----------
    const modalHapus = $('modalHapus'), btnHapusYa = $('modalHapusYa');

    function bukaHapus(id) {
        const r = cariBaris(id).data();
        if (!r) return;
        idHapus = id;
        $('modalHapusNama').textContent = `"${r.nama_kelompok_bidang}"`;
        $('modalHapusError').hidden = true;
        modalHapus.showModal();
    }

    function prosesHapusDummy() {
        if (idHapus === null) return;
        cariBaris(idHapus).remove().draw(false);
        pindahJikaHalamanKosong();
        if (modalHapus.open) modalHapus.close();
    }

    $('modalHapusBatal').addEventListener('click', () => modalHapus.close());
    modalHapus.addEventListener('click', (e) => { if (e.target === modalHapus) modalHapus.close(); });
    modalHapus.addEventListener('close', () => { idHapus = null; });
    btnHapusYa.addEventListener('click', prosesHapusDummy);

    // ---------- Event ----------
    $('kb-tambah').addEventListener('click', () => bukaModal(null));
    $('kb-batal').addEventListener('click', tutupModal);
    $('kb-simpan').addEventListener('click', simpanDummy);
    $('kb-modal').addEventListener('click', (e) => { if (e.target === $('kb-modal')) tutupModal(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') tutupModal(); });
    $('kb-nama').addEventListener('keydown', (e) => { if (e.key === 'Enter') simpanDummy(); });

    $('kb-cari').addEventListener('input', (e) => table.column(1).search(e.target.value.trim()).draw());

    $('tabel').addEventListener('click', (e) => {
        const b = e.target.closest('button[data-act]');
        if (!b) return;
        if (b.dataset.act === 'edit') { const r = cariBaris(b.dataset.id).data(); if (r) bukaModal(r); }
        if (b.dataset.act === 'hapus') bukaHapus(b.dataset.id);
    });
})();
</script>
@endpush