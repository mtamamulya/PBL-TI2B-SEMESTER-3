{{-- FASE 2B — Daftar Poin Dinamis: Misi, Tujuan, dan Profil Lulusan (maks. 20 butir per daftar) --}}
<section id="sec-daftar-poin" class="rounded-lg border border-[#EFEFF1] bg-white p-[25px] shadow-[0_1px_3px_rgba(0,0,0,0.07)]">
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div>
            <h2 class="text-xl font-semibold text-[#111]">Misi, Tujuan &amp; Profil Lulusan</h2>
            <p class="mt-1 text-sm text-[#6E6E76]">Maksimal 20 butir per daftar. Baris yang dibiarkan kosong akan otomatis diabaikan saat disimpan.</p>
        </div>
        <span id="pp-poin-toast" class="hidden rounded-md border border-[#B7EB8F] bg-[#F6FFED] px-3 py-1 text-xs font-medium text-[#237804]" role="status"></span>
    </div>

    <div class="mt-6 space-y-7">
        {{-- 1. MISI --}}
        <div>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h3 class="text-base font-semibold text-[#111]">Misi <span id="pp-misi-count" class="ml-1 text-xs font-normal text-[#6E6E76]"></span></h3>
                    <p class="text-xs text-[#6E6E76]">Pernyataan misi program studi (maks. 500 karakter per butir).</p>
                </div>
                <button type="button" id="pp-misi-add"
                        class="inline-flex h-8 cursor-pointer items-center gap-1.5 rounded-[7px] border border-[#226CFF] bg-white px-3 text-xs font-medium text-[#226CFF] transition-colors hover:bg-[#F0F5FF] disabled:cursor-not-allowed disabled:opacity-50">
                    + Tambah Misi
                </button>
            </div>
            <div id="pp-misi-list" class="mt-3 space-y-2.5"></div>
            <p data-error="misi" class="mt-1 hidden text-xs text-[#E5484D]"></p>
        </div>

        <hr class="border-[#EFEFF1]">

        {{-- 2. TUJUAN --}}
        <div>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h3 class="text-base font-semibold text-[#111]">Tujuan <span id="pp-tujuan-count" class="ml-1 text-xs font-normal text-[#6E6E76]"></span></h3>
                    <p class="text-xs text-[#6E6E76]">Sasaran capaian program studi (maks. 500 karakter per butir).</p>
                </div>
                <button type="button" id="pp-tujuan-add"
                        class="inline-flex h-8 cursor-pointer items-center gap-1.5 rounded-[7px] border border-[#226CFF] bg-white px-3 text-xs font-medium text-[#226CFF] transition-colors hover:bg-[#F0F5FF] disabled:cursor-not-allowed disabled:opacity-50">
                    + Tambah Tujuan
                </button>
            </div>
            <div id="pp-tujuan-list" class="mt-3 space-y-2.5"></div>
            <p data-error="tujuan" class="mt-1 hidden text-xs text-[#E5484D]"></p>
        </div>

        <hr class="border-[#EFEFF1]">

        {{-- 3. PROFIL LULUSAN --}}
        <div>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h3 class="text-base font-semibold text-[#111]">Profil Lulusan <span id="pp-pl-count" class="ml-1 text-xs font-normal text-[#6E6E76]"></span></h3>
                    <p class="text-xs text-[#6E6E76]">Peran/profesi lulusan beserta deskripsi singkatnya.</p>
                </div>
                <button type="button" id="pp-pl-add"
                        class="inline-flex h-8 cursor-pointer items-center gap-1.5 rounded-[7px] border border-[#226CFF] bg-white px-3 text-xs font-medium text-[#226CFF] transition-colors hover:bg-[#F0F5FF] disabled:cursor-not-allowed disabled:opacity-50">
                    + Tambah Profil Lulusan
                </button>
            </div>
            <div id="pp-pl-list" class="mt-3 space-y-3"></div>
            <p data-error="profil_lulusan" class="mt-1 hidden text-xs text-[#E5484D]"></p>
        </div>
    </div>

    <p id="pp-poin-general-error" class="mt-4 hidden text-sm text-[#E5484D]"></p>

    <div class="mt-6 flex justify-end">
        <button type="button" id="pp-poin-simpan"
                class="inline-flex h-[39px] cursor-pointer items-center justify-center rounded-[7px] bg-[#226CFF] px-[17px] text-sm font-medium text-white transition-colors hover:bg-[#1b5ae0] disabled:cursor-not-allowed disabled:opacity-60">
            Simpan Daftar Poin
        </button>
    </div>
</section>

<script>
(() => {
    const URL_UPDATE = @json(route(($prefix ?? 'admin').'.prodi.profil.update'));
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || @json(csrf_token());
    const MAX_ITEM = 20;

    const root = document.getElementById('sec-daftar-poin');
    if (!root) return;

    const state = {
        misi: @json(array_values($profil['misi'] ?? [])),
        tujuan: @json(array_values($profil['tujuan'] ?? [])),
        profil_lulusan: (@json(array_values($profil['profil_lulusan'] ?? []))).map((item) => ({
            judul: item?.judul ?? '',
            deskripsi: item?.deskripsi ?? '',
        })),
    };

    const toast = document.getElementById('pp-poin-toast');
    const generalErr = document.getElementById('pp-poin-general-error');
    const btnSimpan = document.getElementById('pp-poin-simpan');

    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) =>
        ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    let toastTimer = null;
    function showToast(msg) {
        toast.textContent = msg;
        toast.classList.remove('hidden');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => toast.classList.add('hidden'), 3500);
    }

    function clearErrors() {
        generalErr.textContent = '';
        generalErr.classList.add('hidden');
        root.querySelectorAll('[data-error]').forEach((el) => {
            el.textContent = '';
            el.classList.add('hidden');
        });
        root.querySelectorAll('input, textarea').forEach((el) => {
            el.classList.remove('border-[#E5484D]');
            el.classList.add('border-[#DEDEE3]');
        });
    }

    function moveItem(arr, index, dir) {
        const target = index + dir;
        if (target < 0 || target >= arr.length) return;
        [arr[index], arr[target]] = [arr[target], arr[index]];
    }

    function renderSimpleList(key, listId, countId, addBtnId, placeholder) {
        const listEl = document.getElementById(listId);
        const countEl = document.getElementById(countId);
        const addBtn = document.getElementById(addBtnId);
        const items = state[key];

        countEl.textContent = `(${items.length}/${MAX_ITEM})`;
        addBtn.disabled = items.length >= MAX_ITEM;

        if (items.length === 0) {
            listEl.innerHTML = `<p class="rounded-md border border-dashed border-[#DEDEE3] px-3 py-2.5 text-xs text-[#8E8E96]">Belum ada poin. Klik tombol tambah di atas untuk mengisi.</p>`;
            return;
        }

        listEl.innerHTML = items.map((val, idx) => `
            <div data-row="${key}-${idx}">
                <div class="flex items-center gap-2">
                    <span class="w-6 shrink-0 text-right text-xs font-medium text-[#6E6E76]">${idx + 1}.</span>
                    <input type="text" maxlength="500"
                           data-key="${key}" data-idx="${idx}"
                           value="${esc(val)}"
                           placeholder="${esc(placeholder)}"
                           class="h-10 min-w-0 flex-1 rounded-[7px] border border-[#DEDEE3] px-3 text-sm text-[#111] outline-none placeholder:text-[#9E9EA6] focus:border-[#226CFF]">
                    <div class="flex shrink-0 items-center gap-1">
                        <button type="button" data-act="up" data-key="${key}" data-idx="${idx}" title="Geser naik"
                                ${idx === 0 ? 'disabled' : ''}
                                class="flex h-8 w-8 cursor-pointer items-center justify-center rounded-md border border-[#DEDEE3] bg-white text-xs text-[#55555C] hover:bg-[#F4F4F6] disabled:cursor-not-allowed disabled:opacity-40">&uarr;</button>
                        <button type="button" data-act="down" data-key="${key}" data-idx="${idx}" title="Geser turun"
                                ${idx === items.length - 1 ? 'disabled' : ''}
                                class="flex h-8 w-8 cursor-pointer items-center justify-center rounded-md border border-[#DEDEE3] bg-white text-xs text-[#55555C] hover:bg-[#F4F4F6] disabled:cursor-not-allowed disabled:opacity-40">&darr;</button>
                        <button type="button" data-act="del" data-key="${key}" data-idx="${idx}" title="Hapus poin"
                                class="flex h-8 w-8 cursor-pointer items-center justify-center rounded-md bg-[#FFD9D9] text-xs font-semibold text-[#E5484D] hover:bg-[#FFC9C9]">&times;</button>
                    </div>
                </div>
                <p data-error="${key}.${idx}" class="mt-1 hidden pl-8 text-xs text-[#E5484D]"></p>
            </div>
        `).join('');
    }

    function renderProfilLulusan() {
        const listEl = document.getElementById('pp-pl-list');
        const countEl = document.getElementById('pp-pl-count');
        const addBtn = document.getElementById('pp-pl-add');
        const items = state.profil_lulusan;

        countEl.textContent = `(${items.length}/${MAX_ITEM})`;
        addBtn.disabled = items.length >= MAX_ITEM;

        if (items.length === 0) {
            listEl.innerHTML = `<p class="rounded-md border border-dashed border-[#DEDEE3] px-3 py-2.5 text-xs text-[#8E8E96]">Belum ada profil lulusan. Klik "+ Tambah Profil Lulusan" untuk menambahkan.</p>`;
            return;
        }

        listEl.innerHTML = items.map((item, idx) => `
            <div data-row="profil_lulusan-${idx}" class="rounded-lg border border-[#EFEFF1] bg-[#FAFAFB] p-3.5">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-xs font-semibold text-[#6E6E76]">Profil Lulusan #${idx + 1}</span>
                    <div class="flex items-center gap-1">
                        <button type="button" data-act="up" data-key="profil_lulusan" data-idx="${idx}" title="Geser naik"
                                ${idx === 0 ? 'disabled' : ''}
                                class="flex h-7 w-7 cursor-pointer items-center justify-center rounded-md border border-[#DEDEE3] bg-white text-xs text-[#55555C] hover:bg-[#F4F4F6] disabled:cursor-not-allowed disabled:opacity-40">&uarr;</button>
                        <button type="button" data-act="down" data-key="profil_lulusan" data-idx="${idx}" title="Geser turun"
                                ${idx === items.length - 1 ? 'disabled' : ''}
                                class="flex h-7 w-7 cursor-pointer items-center justify-center rounded-md border border-[#DEDEE3] bg-white text-xs text-[#55555C] hover:bg-[#F4F4F6] disabled:cursor-not-allowed disabled:opacity-40">&darr;</button>
                        <button type="button" data-act="del" data-key="profil_lulusan" data-idx="${idx}" title="Hapus profil lulusan"
                                class="flex h-7 w-7 cursor-pointer items-center justify-center rounded-md bg-[#FFD9D9] text-xs font-semibold text-[#E5484D] hover:bg-[#FFC9C9]">&times;</button>
                    </div>
                </div>
                <div class="mt-2.5 grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-[#6E6E76]">Judul Profil <span class="text-[#E5484D]">*</span></label>
                        <input type="text" maxlength="150"
                               data-key="profil_lulusan" data-field="judul" data-idx="${idx}"
                               value="${esc(item.judul)}"
                               placeholder="Contoh: Software Engineer"
                               class="h-9 w-full rounded-[7px] border border-[#DEDEE3] bg-white px-3 text-sm text-[#111] outline-none placeholder:text-[#9E9EA6] focus:border-[#226CFF]">
                        <p data-error="profil_lulusan.${idx}.judul" class="mt-1 hidden text-xs text-[#E5484D]"></p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-[#6E6E76]">Deskripsi Singkat</label>
                        <input type="text" maxlength="1000"
                               data-key="profil_lulusan" data-field="deskripsi" data-idx="${idx}"
                               value="${esc(item.deskripsi)}"
                               placeholder="Contoh: Merancang dan membangun perangkat lunak aplikatif."
                               class="h-9 w-full rounded-[7px] border border-[#DEDEE3] bg-white px-3 text-sm text-[#111] outline-none placeholder:text-[#9E9EA6] focus:border-[#226CFF]">
                        <p data-error="profil_lulusan.${idx}.deskripsi" class="mt-1 hidden text-xs text-[#E5484D]"></p>
                    </div>
                </div>
            </div>
        `).join('');
    }

    function renderAll() {
        renderSimpleList('misi', 'pp-misi-list', 'pp-misi-count', 'pp-misi-add', 'Tuliskan poin misi...');
        renderSimpleList('tujuan', 'pp-tujuan-list', 'pp-tujuan-count', 'pp-tujuan-add', 'Tuliskan poin tujuan...');
        renderProfilLulusan();
    }

    document.getElementById('pp-misi-add').addEventListener('click', () => {
        if (state.misi.length >= MAX_ITEM) return;
        state.misi.push('');
        renderAll();
    });

    document.getElementById('pp-tujuan-add').addEventListener('click', () => {
        if (state.tujuan.length >= MAX_ITEM) return;
        state.tujuan.push('');
        renderAll();
    });

    document.getElementById('pp-pl-add').addEventListener('click', () => {
        if (state.profil_lulusan.length >= MAX_ITEM) return;
        state.profil_lulusan.push({ judul: '', deskripsi: '' });
        renderAll();
    });

    root.addEventListener('input', (e) => {
        const el = e.target;
        const key = el.dataset.key;
        if (!key) return;
        const idx = Number(el.dataset.idx);
        if (key === 'profil_lulusan') {
            state.profil_lulusan[idx][el.dataset.field] = el.value;
        } else {
            state[key][idx] = el.value;
        }
    });

    root.addEventListener('click', (e) => {
        const btn = e.target.closest('button[data-act]');
        if (!btn) return;
        const { act, key } = btn.dataset;
        const idx = Number(btn.dataset.idx);
        if (act === 'up') moveItem(state[key], idx, -1);
        if (act === 'down') moveItem(state[key], idx, 1);
        if (act === 'del') state[key].splice(idx, 1);
        renderAll();
    });

    btnSimpan.addEventListener('click', async () => {
        clearErrors();

        // Petakan baris non-kosong agar indeks error 422 server tepat menunjuk baris di layar
        const misiMap = [];
        const misiPayload = [];
        state.misi.forEach((v, idx) => {
            if (v.trim() !== '') {
                misiMap.push(idx);
                misiPayload.push(v);
            }
        });

        const tujuanMap = [];
        const tujuanPayload = [];
        state.tujuan.forEach((v, idx) => {
            if (v.trim() !== '') {
                tujuanMap.push(idx);
                tujuanPayload.push(v);
            }
        });

        const plMap = [];
        const plPayload = [];
        state.profil_lulusan.forEach((item, idx) => {
            if (item.judul.trim() !== '' || item.deskripsi.trim() !== '') {
                plMap.push(idx);
                plPayload.push({ judul: item.judul, deskripsi: item.deskripsi });
            }
        });

        btnSimpan.disabled = true;
        const labelAsli = btnSimpan.textContent;
        btnSimpan.textContent = 'Menyimpan...';

        try {
            const res = await fetch(URL_UPDATE, {
                method: 'PUT',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF,
                },
                body: JSON.stringify({
                    misi: misiPayload,
                    tujuan: tujuanPayload,
                    profil_lulusan: plPayload,
                }),
            });
            const json = await res.json().catch(() => ({}));

            if (res.status === 422 && json.errors) {
                Object.entries(json.errors).forEach(([rawKey, messages]) => {
                    let domKey = rawKey;
                    const mMisi = rawKey.match(/^misi\.(\d+)$/);
                    const mTujuan = rawKey.match(/^tujuan\.(\d+)$/);
                    const mPl = rawKey.match(/^profil_lulusan\.(\d+)\.(judul|deskripsi)$/);

                    if (mMisi && misiMap[Number(mMisi[1])] !== undefined) {
                        domKey = `misi.${misiMap[Number(mMisi[1])]}`;
                    } else if (mTujuan && tujuanMap[Number(mTujuan[1])] !== undefined) {
                        domKey = `tujuan.${tujuanMap[Number(mTujuan[1])]}`;
                    } else if (mPl && plMap[Number(mPl[1])] !== undefined) {
                        domKey = `profil_lulusan.${plMap[Number(mPl[1])]}.${mPl[2]}`;
                    }

                    const errEl = root.querySelector(`[data-error="${domKey}"]`);
                    if (errEl) {
                        errEl.textContent = messages[0] ?? 'Input tidak valid.';
                        errEl.classList.remove('hidden');
                        const inputEl = errEl.previousElementSibling?.querySelector('input') || errEl.previousElementSibling;
                        if (inputEl && (inputEl.tagName === 'INPUT' || inputEl.tagName === 'TEXTAREA')) {
                            inputEl.classList.remove('border-[#DEDEE3]');
                            inputEl.classList.add('border-[#E5484D]');
                        }
                    } else {
                        generalErr.textContent = messages[0] ?? 'Periksa kembali daftar poin.';
                        generalErr.classList.remove('hidden');
                    }
                });
                return;
            }

            if (!res.ok || !json.ok) {
                throw new Error(json.message || 'Gagal menyimpan daftar poin.');
            }

            if (json.data) {
                state.misi = Array.isArray(json.data.misi) ? [...json.data.misi] : [];
                state.tujuan = Array.isArray(json.data.tujuan) ? [...json.data.tujuan] : [];
                state.profil_lulusan = Array.isArray(json.data.profil_lulusan)
                    ? json.data.profil_lulusan.map((it) => ({ judul: it?.judul ?? '', deskripsi: it?.deskripsi ?? '' }))
                    : [];
                renderAll();
            }
            showToast('Daftar misi, tujuan & profil lulusan berhasil disimpan.');
        } catch (err) {
            generalErr.textContent = err.message || 'Terjadi kesalahan saat menyimpan.';
            generalErr.classList.remove('hidden');
        } finally {
            btnSimpan.disabled = false;
            btnSimpan.textContent = labelAsli;
        }
    });

    renderAll();
})();
</script>