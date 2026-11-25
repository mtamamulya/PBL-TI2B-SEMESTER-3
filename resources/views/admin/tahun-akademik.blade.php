@extends('layouts.admin')

@section('title', 'Tahun Akademik')

@section('content')
<link rel="stylesheet" href="https://cdn.datatables.net/2.1.8/css/dataTables.dataTables.min.css">
<style>
    #tabel-wrap .dt-layout-row { margin: 0; }
    #tabel-wrap .dt-layout-row.dt-layout-table {
        border: 1px solid rgb(0 0 47 / .15);
        border-radius: 6px;
        overflow: hidden;
        background: #fff;
    }
    #tabel-wrap .dt-layout-table .dt-layout-cell { width: 100%; padding: 0; overflow-x: auto; }

    #tabel { width: 100%; table-layout: fixed; border-collapse: collapse; margin: 0 !important; font-size: 14px; color: #1C2024; }

    #tabel.dataTable > thead > tr > th:nth-child(1) { width: 15%; }
    #tabel.dataTable > thead > tr > th:nth-child(2) { width: 60%; }
    #tabel.dataTable > thead > tr > th:nth-child(3) { width: 25%; }

    #tabel.dataTable > thead > tr > th {
        padding: 8px; font-weight: 510; text-align: left;
        background: #F9F9FC;
        border-bottom: 1px solid rgb(0 0 47 / .15);
    }
    #tabel.dataTable > thead > tr > th .dt-column-header {
        display: flex; flex-direction: row !important; justify-content: flex-start !important; align-items: center; gap: 6px;
    }
    #tabel.dataTable > thead > tr > th .dt-column-title { flex-grow: 0 !important; }

    #tabel.dataTable > tbody > tr > td {
        height: 56px; padding: 8px; text-align: left;
        border-top: 1px solid rgb(0 0 47 / .15);
        box-shadow: none;
    }
    #tabel.dataTable > tbody > tr:first-child > td { border-top: 0; }
    #tabel.dataTable > tbody > tr > td.dt-empty { padding: 24px; text-align: center; color: #A8A8A8; }

    #tabel.dataTable > thead > tr > th:first-child,
    #tabel.dataTable > tbody > tr > td:first-child:not(.dt-empty) { padding-left: 20px; }

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

    #modalKonfirmasiTA { position: fixed; inset: 0; margin: auto; height: fit-content; width: 400px; max-width: calc(100vw - 32px); padding: 0; border: 0; background: transparent; overflow: visible; }
    #modalKonfirmasiTA::backdrop { background: rgb(10 10 10 / .45); }
    #modalKonfirmasiTA[open] { animation: modalMasuk .15s ease-out; }
    @keyframes modalMasuk { from { opacity: 0; transform: translateY(8px) scale(.98); } to { opacity: 1; transform: none; } }
    .modal-kartu { padding: 24px; border-radius: 12px; background: #fff; text-align: center; box-shadow: 0 20px 50px rgb(0 0 0 / .2); }
    .modal-ikon-biru { display: flex; align-items: center; justify-content: center; width: 48px; height: 48px; margin: 0 auto 16px; border-radius: 9999px; background: #D4E3FF; color: #1F73FF; }
    .modal-judul { font-size: 18px; font-weight: 700; line-height: 24px; color: #0A0A0A; }
    .modal-teks { margin-top: 8px; font-size: 14px; line-height: 20px; color: #55555C; }
    .modal-teks strong { font-weight: 600; color: #0A0A0A; }
    .modal-aksi { display: flex; gap: 12px; margin-top: 24px; }
    .modal-btn { flex: 1; height: 40px; border: 1px solid transparent; border-radius: 8px; font-size: 14px; font-weight: 500; cursor: pointer; transition: background .15s; }
    .modal-btn-batal { background: #fff; border-color: #E8E8EC; color: #2E2E2E; }
    .modal-btn-batal:hover { background: #F9F9FB; }
    .modal-btn-ya { background: #2277FF; color: #fff; }
    .modal-btn-ya:hover { background: #1A66E0; }
    .modal-btn-ya:disabled { opacity: .7; cursor: not-allowed; }
</style>

<main class="p-4 pb-10 sm:p-6" x-data="{
    semester: '{{ old('semester', 'Ganjil') }}',
    tahunAjaran: '{{ old('tahun_ajaran', date('Y') . '/' . (date('Y') + 1)) }}',
    get previewLabel() {
        return `${this.semester} ${this.tahunAjaran}`;
    }
}">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="mt-[6px] text-[32px] font-bold leading-10 tracking-[-0.3px]">Tahun Akademik</h1>
            <x-admin.breadcrumb :items="[['Dashboard', 'admin.dashboard'], ['Kurikulum', 'admin.kurikulum.trk'], ['Tahun Akademik']]" />
        </div>

        @if($tahunAkademikAktif)
            <div class="mt-2 inline-flex items-center gap-2 rounded-md border border-[#D6EDB5] bg-[#E6F3D3] px-4 py-2 text-sm text-[#4C7D08]">
                <span class="h-2 w-2 rounded-full bg-[#3A8A1E]"></span>
                <span>Aktif saat ini: <strong>{{ $tahunAkademikAktif['tahun_akademik'] }}</strong></span>
            </div>
        @endif
    </div>

    @if (session('status'))
        <div class="mt-4 rounded-md border border-[#D6EDB5] bg-[#E6F3D3] px-4 py-3 text-sm text-[#4C7D08]" role="status">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mt-4 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            <ul class="list-disc pl-5 space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mt-6 grid grid-cols-1 items-start gap-6 lg:grid-cols-12">
        {{-- Form Tambah Tahun Akademik --}}
        <section class="rounded-lg bg-white p-5 shadow-[0_1px_4px_rgba(0,0,0,0.06)] lg:col-span-4">
            <h2 class="text-base font-semibold text-[#1C2024]">Tambah Tahun Akademik</h2>
            <p class="mt-1 text-xs text-[#55555C]">
                Periode baru otomatis aktif dan menonaktifkan periode sebelumnya.
            </p>

            <form id="formTahunAkademik" action="{{ route('admin.kurikulum.tahun-akademik.store') }}" method="POST" class="mt-4 space-y-4">
                @csrf

                <div>
                    <label for="semester" class="block text-sm font-medium text-[#1C2024]">Semester</label>
                    <select id="semester" name="semester" x-model="semester"
                            class="mt-1.5 h-10 w-full rounded-md border border-[#E4E4E7] bg-white px-3.5 text-sm text-[#1C2024] outline-none focus:border-[#1F73FF]">
                        <option value="Ganjil">Ganjil</option>
                        <option value="Genap">Genap</option>
                    </select>
                </div>

                <div>
                    <label for="tahun_ajaran" class="block text-sm font-medium text-[#1C2024]">Tahun Ajaran</label>
                    <input type="text" id="tahun_ajaran" name="tahun_ajaran" x-model="tahunAjaran"
                           placeholder="2026/2027"
                           class="mt-1.5 h-10 w-full rounded-md border border-[#E4E4E7] bg-white px-3.5 text-sm text-[#1C2024] outline-none placeholder:text-[#B3B3B8] focus:border-[#1F73FF]"
                           required>
                    <p class="mt-1 text-xs text-[#8A8A90]">Format: YYYY/YYYY (contoh: 2026/2027 atau 2027/2028)</p>
                </div>

                <div class="rounded-md bg-[#F9F9FC] px-3.5 py-2.5 text-xs text-[#55555C]">
                    Pratinjau: <strong class="text-[#1C2024]" x-text="previewLabel"></strong>
                </div>

                <button type="button" id="btnBukaModalTA"
                        class="h-10 w-full cursor-pointer rounded-md bg-[#1F73FF] px-4 text-sm font-medium text-white transition hover:bg-[#1A66E0]">
                    Simpan &amp; Aktifkan
                </button>
            </form>
        </section>

        {{-- Daftar Riwayat Tahun Akademik --}}
        <section class="rounded-lg bg-white p-5 shadow-[0_1px_4px_rgba(0,0,0,0.06)] lg:col-span-8">
            <div class="flex items-center justify-between gap-2">
                <div>
                    <h2 class="text-base font-semibold text-[#1C2024]">Daftar Tahun Akademik</h2>
                    <p class="mt-0.5 text-xs text-[#55555C]">Periode semester berjalan dan riwayat tahun akademik program studi</p>
                </div>
                <span class="text-xs font-medium text-[#8A8A90]">Total: {{ count($tahunAkademikList) }} periode</span>
            </div>

            <div id="tabel-wrap" class="mt-4">
                <table id="tabel">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Tahun Akademik</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </section>
    </div>
</main>

<dialog id="modalKonfirmasiTA" aria-labelledby="modalTAJudul" aria-describedby="modalTATeks">
    <div class="modal-kartu">
        <div class="modal-ikon-biru">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M4 7a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2v-12z" />
                <path d="M16 3v4" />
                <path d="M8 3v4" />
                <path d="M4 11h16" />
                <path d="M9 15l2 2l4 -4" />
            </svg>
        </div>
        <h2 id="modalTAJudul" class="modal-judul">Aktifkan Tahun Akademik Baru?</h2>
        <p id="modalTATeks" class="modal-teks">
            Periode <strong id="modalTALabel"></strong> akan diaktifkan. Tahun akademik sebelumnya otomatis dinonaktifkan dan data ini tidak dapat diubah kembali.
        </p>
        <div class="modal-aksi">
            <button type="button" id="modalTABatal" class="modal-btn modal-btn-batal">Batal</button>
            <button type="button" id="modalTAYa" class="modal-btn modal-btn-ya">Ya, Simpan &amp; Aktifkan</button>
        </div>
    </div>
</dialog>
@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.min.js"></script>
<script>
(function () {
    var data = @json($tahunAkademikList);
    var TXT = DataTable.render.text();

    var table = new DataTable('#tabel', {
        data: data,
        autoWidth: false,
        pageLength: 5,
        ordering: false,
        columns: [
            {
                data: null,
                searchable: false,
                render: function (d, type, r, meta) {
                    return meta.row + 1;
                }
            },
            {
                data: 'tahun_akademik',
                render: TXT
            },
            {
                data: 'status',
                searchable: false,
                render: function (d, type) {
                    if (type !== 'display') return d;
                    var raw = String(d == null ? '' : d).toLowerCase();
                    var on = raw === '1' || raw === 'true' || raw === 'aktif';
                    return '<span class="inline-flex items-center rounded px-2 py-0.5 text-[11px] font-medium ' +
                        (on ? 'bg-[#DDF3D0] text-[#3A8A1E]' : 'bg-[#EDEDF0] text-[#55555C]') + '">' +
                        (on ? 'Aktif' : 'Tidak Aktif') + '</span>';
                }
            }
        ],
        layout: { topStart: null, topEnd: null, bottomStart: null, bottomEnd: null, bottom: 'paging' },
        pagingType: 'simple_numbers',
        language: {
            emptyTable: 'Belum ada data tahun akademik.',
            zeroRecords: 'Belum ada data tahun akademik.',
            paginate: { previous: 'Sebelumnya', next: 'Berikutnya' }
        }
    });

    function togglePager() {
        var p = document.querySelector('#tabel-wrap .dt-paging');
        if (p) p.style.display = table.page.info().pages > 1 ? '' : 'none';
    }
    table.on('draw.dt', togglePager);
    togglePager();

    var form = document.getElementById('formTahunAkademik');
    var btnBuka = document.getElementById('btnBukaModalTA');
    var modal = document.getElementById('modalKonfirmasiTA');
    var btnBatal = document.getElementById('modalTABatal');
    var btnYa = document.getElementById('modalTAYa');
    var labelModal = document.getElementById('modalTALabel');

    if (form && btnBuka && modal) {
        btnBuka.addEventListener('click', function () {
            if (!form.reportValidity()) return;

            var sem = document.getElementById('semester').value;
            var thn = document.getElementById('tahun_ajaran').value.trim();
            labelModal.textContent = '"' + sem + ' ' + thn + '"';

            if (typeof modal.showModal === 'function') {
                modal.showModal();
            } else if (confirm('Aktifkan periode "' + sem + ' ' + thn + '"? Tahun akademik sebelumnya akan otomatis dinonaktifkan.')) {
                form.submit();
            }
        });

        btnBatal.addEventListener('click', function () { modal.close(); });
        modal.addEventListener('click', function (e) { if (e.target === modal) modal.close(); });
        modal.addEventListener('close', function () {
            btnYa.disabled = false;
            btnYa.textContent = 'Ya, Simpan & Aktifkan';
        });
        btnYa.addEventListener('click', function () {
            btnYa.disabled = true;
            btnYa.textContent = 'Menyimpan...';
            form.submit();
        });
    }
})();
</script>
@endpush