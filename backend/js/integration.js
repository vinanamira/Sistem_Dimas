(function () {
  'use strict';

  const BASE = '';

  // Generate unique session ID per tab
  if (!sessionStorage.getItem('tab_session_id')) {
    const random = Math.random().toString(36).slice(2, 10);
    const tabId = 'tab-' + Date.now().toString(36) + '-' + random;
    sessionStorage.setItem('tab_session_id', tabId);
  }
  const TAB_SESSION_ID = sessionStorage.getItem('tab_session_id');

  async function apiJson(url, options) {
    const opts = Object.assign({ credentials: 'same-origin' }, options || {});
    opts.headers = opts.headers || {};
    opts.headers['X-Tab-Session-ID'] = TAB_SESSION_ID;
    const r = await fetch(BASE + url, opts);
    if (r.status === 401) {
      window.location.href = BASE + 'login.php';
      return null;
    }
    const text = await r.text();
    try {
      return JSON.parse(text);
    } catch (e) {
      console.error('Invalid JSON', url, text);
      return { success: false, error: 'Respons tidak valid' };
    }
  }

  // Fungsi cache menggunakan sessionStorage
  function getCache(key) {
    try {
      const fullKey = TAB_SESSION_ID + '_' + key;
      const data = sessionStorage.getItem(fullKey);
      return data ? JSON.parse(data) : null;
    } catch (e) {
      return null;
    }
  }

  function setCache(key, data) {
    try {
      const fullKey = TAB_SESSION_ID + '_' + key;
      sessionStorage.setItem(fullKey, JSON.stringify(data));
    } catch (e) {
      // Jika storage penuh, abaikan
    }
  }

  function clearCache(key) {
    try {
      const fullKey = TAB_SESSION_ID + '_' + key;
      sessionStorage.removeItem(fullKey);
    } catch (e) {
      // Abaikan
    }
  }

  function formatRupiah(n) {
    const x = Number(n) || 0;
    return 'Rp ' + x.toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  }

  function parseRupiah(str) {
    if (!str) return 0;
    const cleanStr = String(str).replace(/[^0-9]/g, '');
    return Number(cleanStr) || 0;
  }

  document.addEventListener('input', function (e) {
    if (e.target && e.target.classList.contains('format-rupiah')) {
      let val = e.target.value.replace(/[^0-9]/g, '');
      if (val) {
        e.target.value = new Intl.NumberFormat('id-ID').format(val);
      } else {
        e.target.value = '';
      }
    }
  });

  function formatTanggalId(tgl) {
    if (!tgl) return '';
    const d = new Date(tgl + (tgl.length <= 10 ? 'T00:00:00' : ''));
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
  }

  function wireLogout() {
    document.querySelectorAll('button').forEach(function (b) {
      if ((b.textContent || '').trim() === 'Logout') {
        b.addEventListener('click', async function () {
          await apiJson('backend/proses/logout.php');
          sessionStorage.removeItem('tab_session_id');
          window.location.href = BASE + 'login.php';
        });
      }
    });
  }

  async function updateStudentSidebarProfile() {
    const side = document.querySelector('aside p.text-center.font-bold');
    const sub = document.querySelector('aside p.text-center.text-sm.mb-6');
    if (!side || !sub) return;

    const prof = await apiJson('backend/data/get_profile.php');
    if (!prof || !prof.success || !prof.siswa) return;

    side.textContent = prof.siswa.nama_siswa || 'Nama Siswa';
    sub.textContent = prof.siswa.kelas ? 'Kelas ' + prof.siswa.kelas : 'Kelas: -';
  }

  window.openTambahModal = function () {
    const modal = document.getElementById('modalTambah') || document.getElementById('modalTambahSiswa');
    if (modal) {
      modal.classList.remove('hidden');
      modal.classList.add('flex');
    }
  };

  window.closeTambahModal = function () {
    const modal = document.getElementById('modalTambah') || document.getElementById('modalTambahSiswa');
    if (modal) {
      modal.classList.add('hidden');
      modal.classList.remove('flex');
    }
  };

  /* ---------- Laporan Pengeluaran ---------- */
  async function initLaporanPengeluaran() {
    const tbody = document.getElementById('tabelPengeluaran');
    if (!tbody) return;

    let rows = [];
    let selectedId = null;

    async function load() {
      const j = await apiJson('backend/data/get_pengeluaran.php');
      if (!j || !j.success) return;
      rows = j.data || [];
      document.getElementById('totalPengeluaran').textContent = formatRupiah(j.total);
      document.getElementById('totalPendidikan').textContent = formatRupiah(j.total_pendidikan);
      document.getElementById('totalSarana').textContent = formatRupiah(j.total_sarana);
      render();
    }

    function render() {
      tbody.innerHTML = '';
      rows.forEach(function (item) {
        const tr = document.createElement('tr');
        tr.innerHTML =
          '<td class="border px-3 py-2">' +
          formatTanggalId(item.tgl_uang) +
          '</td>' +
          '<td class="border px-3 py-2">' +
          (item.kategori || '') +
          '</td>' +
          '<td class="border px-3 py-2">' +
          (item.ket_uang || '') +
          '</td>' +
          '<td class="border px-3 py-2">' +
          formatRupiah(item.jml_uang) +
          '</td>' +
          '<td class="border px-3 py-2 space-x-2">' +
          '<button type="button" class="bg-yellow-400 px-2 py-1 rounded text-xs btn-edit">Edit</button>' +
          '<button type="button" class="bg-red-500 text-white px-2 py-1 rounded text-xs btn-del">Hapus</button>' +
          '</td>';
        tr.querySelector('.btn-edit').onclick = function () {
          openEdit(item);
        };
        tr.querySelector('.btn-del').onclick = function () {
          selectedId = item.id_uang;
          document.getElementById('modalDelete').classList.remove('hidden');
          document.getElementById('modalDelete').classList.add('flex');
        };
        tbody.appendChild(tr);
      });
    }

    function openEdit(item) {
      selectedId = item.id_uang;
      document.getElementById('editTanggal').value = (item.tgl_uang || '').slice(0, 10);
      document.getElementById('editKategori').value = item.kategori || 'Lainnya';
      document.getElementById('editKeterangan').value = item.ket_uang || '';
      document.getElementById('editJumlah').value = item.jml_uang;
      document.getElementById('modalEdit').classList.remove('hidden');
      document.getElementById('modalEdit').classList.add('flex');
    }

    window.openTambahModal = function () {
      document.getElementById('modalTambah').classList.remove('hidden');
      document.getElementById('modalTambah').classList.add('flex');
    };
    window.closeTambahModal = function () {
      document.getElementById('modalTambah').classList.add('hidden');
      document.getElementById('modalTambah').classList.remove('flex');
    };
    window.closeEditModal = function () {
      document.getElementById('modalEdit').classList.add('hidden');
      document.getElementById('modalEdit').classList.remove('flex');
    };
    window.closeDeleteModal = function () {
      document.getElementById('modalDelete').classList.add('hidden');
      document.getElementById('modalDelete').classList.remove('flex');
    };

    window.simpanPengeluaran = async function () {
      const fd = new FormData();
      fd.append('tgl_uang', document.getElementById('tanggal').value);
      fd.append('ket_uang', document.getElementById('keterangan').value);
      fd.append('jml_uang', document.getElementById('jumlah').value);
      fd.append('jenis_uang', 'pengeluaran');
      fd.append('kategori', document.getElementById('kategori').value);
      const r = await fetch(BASE + 'backend/proses/keuangan/tambah_keuangan.php', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
      });
      if (r.ok) {
        window.location.reload();
      } else {
        alert('Gagal menyimpan');
      }
    };

    window.updatePengeluaran = async function () {
      const fd = new FormData();
      fd.append('id_uang', selectedId);
      fd.append('tgl_uang', document.getElementById('editTanggal').value);
      fd.append('ket_uang', document.getElementById('editKeterangan').value);
      fd.append('jml_uang', document.getElementById('editJumlah').value);
      fd.append('jenis_uang', 'pengeluaran');
      fd.append('kategori', document.getElementById('editKategori').value);
      const r = await fetch(BASE + 'backend/proses/keuangan/edit_keuangan.php', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
      });
      if (r.ok) {
        window.location.reload();
      } else {
        alert('Gagal memperbarui');
      }
    };

    window.hapusPengeluaran = async function () {
      const fd = new FormData();
      fd.append('id_uang', selectedId);
      const r = await fetch(BASE + 'backend/proses/keuangan/hapus_keuangan.php', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
      });
      if (r.ok) {
        window.location.reload();
      } else {
        alert('Gagal menghapus');
      }
    };

    window.openEditModal = function () { };
    window.openDeleteModal = function () { };

    document.querySelectorAll('.fixed').forEach(function (modal) {
      modal.addEventListener('click', function (e) {
        if (e.target === modal) {
          modal.classList.add('hidden');
          modal.classList.remove('flex');
        }
      });
    });

    load();
  }

  /* ---------- data siswa ---------- */
  async function initTagihanBulanan() {
    const tbody = document.getElementById('tabelTagihan');
    if (!tbody) return;

    let list = [];
    const now = new Date();
    const bulanTagihanText = now.toLocaleDateString('id-ID', {
      month: 'long',
      year: 'numeric'
    });
    const lastDayOfMonth = new Date(now.getFullYear(), now.getMonth() + 1, 0).getDate();
    const jatuhTempoText = lastDayOfMonth + ' ' + now.toLocaleDateString('id-ID', {
      month: 'long',
      year: 'numeric'
    });

    async function load() {
      const j = await apiJson('backend/data/get_siswa.php?filter=current_month');
      if (!j || !j.success) return;
      list = j.data || [];
      renderTable(list);

      const selSiswa = document.getElementById('namaSiswa');
      if (selSiswa) {
        selSiswa.innerHTML = '<option value="">-- Pilih Siswa --</option>';
        window.siswaBulananMap = {};
        list.forEach(function (siswa) {
          window.siswaBulananMap[siswa.nama_siswa] = siswa.kelas;
          const opt = document.createElement('option');
          opt.value = siswa.nama_siswa;
          opt.textContent = siswa.nama_siswa + ' (' + (siswa.nis || '-') + ')';
          selSiswa.appendChild(opt);
        });

        selSiswa.addEventListener('change', function () {
          const kelasInput = document.getElementById('kelasSiswa');
          if (kelasInput) {
            kelasInput.value = window.siswaBulananMap[this.value] || '';
          }
        });
      }
    }

    function renderTable(data) {
      tbody.innerHTML = '';

      // Urutan kelas
      const urutanKelas = {
        'X TKJ': 1,
        'X TSM': 2,
        'X Perhotelan': 3,
        'XI TKJ': 4,
        'XI TSM': 5,
        'XI Perhotelan': 6,
        'XII TKJ': 7,
        'XII TSM': 8,
        'XII Perhotelan': 9
      };

      // Sorting kelas dan nama siswa
      data.sort(function (a, b) {

        const kelasA = urutanKelas[(a.kelas || '').trim()] || 999;
        const kelasB = urutanKelas[(b.kelas || '').trim()] || 999;

        if (kelasA !== kelasB) {
          return kelasA - kelasB;
        }

        return (a.nama_siswa || '')
          .localeCompare(b.nama_siswa || '');
      });

      data.forEach(function (item) {

        const tunggakan = Number(item.jml_tunggakan || 0);
        const totalRecord = Number(item.total_tagihan_record || 0);
        const totalAwal = Number(item.total_awal || 0);

        let statusLabel = '';
        let amountText = '';
        let bulanText = bulanTagihanText;
        let jatuhTempo = jatuhTempoText;

        if (totalRecord === 0) {
          statusLabel = '<span class="bg-gray-100 text-gray-500 px-2 py-1 rounded text-xs font-semibold">Belum Ada Tagihan</span>';
          amountText = '-';
          bulanText = '-';
          jatuhTempo = '-';
        } else if (tunggakan > 0) {
          if (tunggakan < totalAwal) {
            statusLabel = '<span class="bg-yellow-100 text-yellow-700 px-2 py-1 rounded text-xs font-semibold">Cicilan</span>';
          } else {
            statusLabel = '<span class="bg-red-100 text-red-600 px-2 py-1 rounded text-xs font-semibold">Belum Bayar</span>';
          }
          amountText = formatRupiah(tunggakan);
        } else {
          statusLabel = '<span class="bg-green-100 text-green-600 px-2 py-1 rounded text-xs font-semibold">Lunas</span>';
          amountText = formatRupiah(0);
        }

        const tr = document.createElement('tr');
        tr.className = 'border-b hover:bg-blue-50';

        tr.innerHTML =
          '<td class="py-3">' +
          (item.nama_siswa || '') +
          '</td>' +
          '<td>' +
          (item.kelas || '') +
          '</td>' +
          '<td>' +
          bulanText +
          '</td>' +
          '<td>' +
          amountText +
          '</td>' +
          '<td>' +
          jatuhTempo +
          '</td>' +
          '<td class="py-3">' +
          statusLabel +
          '</td>';

        tbody.appendChild(tr);
      });
    }

    window.cariSiswa = function () {
      const keyword = (document.getElementById('searchInput').value || '').toLowerCase();
      const selKelas = document.getElementById('filterKelas');
      const selectedKelas = selKelas ? selKelas.value : '';

      const hasil = list.filter(function (item) {
        const matchKeyword = (item.nama_siswa || '').toLowerCase().includes(keyword);
        const k = item.kelas || '';
        const matchKelas = selectedKelas === '' || k.split(' ')[0] === selectedKelas;
        return matchKeyword && matchKelas;
      });

      renderTable(hasil);
    };

    load();
  }

  /* ---------- Data Siswa ---------- */
  async function initDataSiswa() {
    const wrap = document.querySelector('table.min-w-full tbody');
    if (!wrap) return;

    const tambahBtn = document.getElementById('btnTambahSiswa');

    async function load() {
      const j = await apiJson('backend/data/get_siswa.php');
      if (!j || !j.success) return;
      wrap.innerHTML = ''; -
        (j.data || []).forEach(function (s) {
          const tr = document.createElement('tr');
          tr.setAttribute('data-id-siswa', s.id_siswa);
          tr.innerHTML =
            '<td class="px-6 py-4">' +
            (s.nama_siswa || '') +
            '</td>' +
            '<td class="px-6 py-4">' +
            (s.kelas || '') +
            '</td>' +
            '<td class="px-6 py-4">' +
            (s.nis || '') +
            '</td>' +
            '<td class="px-6 py-4">' +
            (s.email || s.username || '') +
            '</td>' +
            '<td class="px-6 py-4 text-right">' +
            '<button type="button" class="text-sm border border-blue-300 px-3 py-1 rounded hover:bg-gray-100 btn-edit-siswa">Edit</button>' +
            '</td>';
          tr.querySelector('.btn-edit-siswa').onclick = function () {
            openModal(tr);
          };
          wrap.appendChild(tr);
        });
    }

    let currentRow = null;

    function openModal(row) {
      currentRow = row;
      const cells = row.querySelectorAll('td');
      document.getElementById('nama').value = cells[0].innerText;
      document.getElementById('kelas').value = cells[1].innerText;
      document.getElementById('nis').value = cells[2].innerText;
      document.getElementById('email').value = cells[3].innerText;
      document.getElementById('idSiswaEdit').value = row.getAttribute('data-id-siswa');
      document.getElementById('modalEdit').classList.remove('hidden');
      document.getElementById('modalEdit').classList.add('flex');
    }

    window.closeModal = function () {
      document.getElementById('modalEdit').classList.add('hidden');
      document.getElementById('modalEdit').classList.remove('flex');
    };

    window.closeTambahModal = function () {
      document.getElementById('modalTambah').classList.add('hidden');
      document.getElementById('modalTambah').classList.remove('flex');
    };

    window.openTambahModal = function () {
      document.getElementById('modalTambah').classList.remove('hidden');
      document.getElementById('modalTambah').classList.add('flex');
    };

    if (tambahBtn) {
      tambahBtn.addEventListener('click', openTambahModal);
    }

    window.saveData = async function () {
      const fd = new FormData();
      fd.append('id_siswa', document.getElementById('idSiswaEdit').value);
      fd.append('nama_siswa', document.getElementById('nama').value);
      fd.append('email', document.getElementById('email').value);
      const r = await fetch(BASE + 'backend/proses/siswa/edit_siswa.php', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
      });
      if (r.ok) {
        window.closeModal();
        load();
      } else {
        alert('Gagal menyimpan');
      }
    };

    window.hapusSiswa = async function () {
      const fd = new FormData();
      fd.append('id_siswa', document.getElementById('idSiswaEdit').value);
      const r = await fetch(BASE + 'backend/proses/siswa/hapus_siswa.php', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
      });
      if (r.ok) {
        window.closeModal();
        load();
      } else {
        alert('Gagal menghapus siswa');
      }
    };

    window.simpanSiswaBaru = async function () {
      const nisInput = document.getElementById('nisTambah').value;
      const nama = document.getElementById('namaTambah').value;
      const kelas = document.getElementById('kelasTambah').value;

      if (!nisInput || !nama || !kelas) {
        alert('NIS, Nama, dan kelas harus diisi');
        return;
      }

      const nis = nisInput.trim();

      if (nis.length !== 10) {
        alert('NIS harus berjumlah tepat 10 digit');
        return;
      }

      if (!/^\d+$/.test(nis)) {
        alert('NIS hanya boleh berisi angka');
        return;
      }

      // Generate email dari nama
      const email = nama.toLowerCase().replace(/\s+/g, '') + '@gmail.com';

      // Generate username dari NIS
      const username = nis;

      const fd = new FormData();
      fd.append('nama_siswa', nama);
      fd.append('nis', nis.toString());
      fd.append('kelas', kelas);
      fd.append('email', email);
      fd.append('username', username);
      fd.append('password', 'admin123'); // Default password

      const r = await fetch(BASE + 'backend/proses/siswa/tambah_siswa.php', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
      });

      if (r.ok) {
        document.getElementById('nisTambah').value = '';
        document.getElementById('namaTambah').value = '';
        document.getElementById('kelasTambah').value = '';
        window.closeTambahModal();
        load();
      } else {
        alert('Gagal menambahkan siswa');
      }
    };

    window.openModal = openModal;

    document.getElementById('modalEdit').addEventListener('click', function (e) {
      if (e.target === this) {
        window.closeModal();
      }
    });

    document.getElementById('modalTambah').addEventListener('click', function (e) {
      if (e.target === this) {
        window.closeTambahModal();
      }
    });

    load();
  }

  /* ---------- Dashboard Admin ---------- */
  async function initIndexAdmin() {
    const chartEl = document.getElementById('chartKelas');
    if (!chartEl || typeof Chart === 'undefined') return;

    // Load from cache first
    let dash = getCache('dashboard_admin');
    let ch = getCache('chart_kelas');
    let tunggakan = getCache('tunggakan_admin');

    // Display cached data if available
    if (dash) {
      updateDashboardAdmin(dash, ch, tunggakan, chartEl);
    }

    // Then fetch from server
    const dashServer = await apiJson('backend/data/get_dashboard.php');
    const chServer = await apiJson('backend/data/get_chart_kelas.php');
    const tunggakanServer = await apiJson('backend/data/get_tunggakan.php');

    if (dashServer && dashServer.success) {
      dash = dashServer;
      setCache('dashboard_admin', dash);
    }
    if (chServer && chServer.success) {
      ch = chServer;
      setCache('chart_kelas', ch);
    }
    if (tunggakanServer && tunggakanServer.success) {
      tunggakan = tunggakanServer;
      setCache('tunggakan_admin', tunggakan);
    }

    // Update display with latest data
    updateDashboardAdmin(dash, ch, tunggakan, chartEl);
  }

  function updateDashboardAdmin(dash, ch, tunggakan, chartEl) {
    if (!dash) return;

    const boxes = document.querySelectorAll('.grid.grid-cols-1.sm\\:grid-cols-2 > div.bg-blue-50');
    if (boxes.length >= 2) {
      const n0 = boxes[0].querySelector('p.text-2xl');
      const n1 = boxes[1].querySelector('p.text-2xl');
      if (n0) {
        n0.textContent = formatRupiah(dash.total_jml_tunggakan || 0);
      }
      if (n1) {
        n1.textContent = String(dash.total_siswa || 0);
      }
      const sub = boxes[1].querySelector('p.text-gray-800');
      if (sub) {
        sub.textContent = dash.total_tunggakan + ' siswa masih berutang';
      }
    }

    const tunggakanBody = document.getElementById('tabelTunggakanAdmin');
    if (tunggakanBody && tunggakan && tunggakan.success) {
      tunggakanBody.innerHTML = '';
      (tunggakan.data || [])
        .filter(function (t) {
          return Number(t.jml_tunggakan) > 0;
        })
        .slice(0, 12)
        .forEach(function (t) {
          const tr = document.createElement('tr');
          tr.className = 'border-t';
          tr.innerHTML =
            '<td class="px-6 py-4 border-r border-gray-200">' +
            (t.nama_siswa || '') +
            '</td>' +
            '<td class="px-6 py-4">' +
            (t.kelas || '') +
            '</td>';
          tunggakanBody.appendChild(tr);
        });
    }

    if (ch && ch.success) {
      new Chart(chartEl, {
        type: 'bar',
        data: {
          labels: ch.labels,
          datasets: [
            {
              label: 'Jumlah Lunas',
              data: ch.data,
              backgroundColor: 'rgba(59, 130, 246, 0.8)',
              borderRadius: 6,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: true } },
          scales: {
            y: {
              beginAtZero: true,
              ticks: { stepSize: 1 },
            },
          },
        },
      });
    }
  }

  /* ---------- Dashboard Siswa ---------- */
  async function initIndexSiswa() {
    await updateStudentSidebarProfile();

    const gridStats = document.querySelector('.grid.grid-cols-1.md\\:grid-cols-4');
    const cards = gridStats ? gridStats.querySelectorAll('h3.text-xl') : [];
    if (cards.length < 4) return;

    // Load from cache first
    let j = getCache('dashboard_siswa');
    let keg = getCache('tagihan_kegiatan');
    if (j) {
      updateDashboardSiswa(j, cards, keg);
      updateTagihanDropdown(j, keg);
    }

    // Then fetch from server
    const [jServer, kegServer] = await Promise.all([
      apiJson('backend/data/get_dashboard_siswa.php'),
      apiJson('backend/data/get_tagihan_kegiatan.php')
    ]);
    if (jServer && jServer.success) {
      j = jServer;
      setCache('dashboard_siswa', j);
    }
    if (kegServer && kegServer.success) {
      keg = kegServer;
      setCache('tagihan_kegiatan', keg);
    }
    updateDashboardSiswa(j, cards, keg);
    updateTagihanDropdown(j, keg);
  }

  function hitungTotalBulanIni(j, keg) {
    const s = j.siswa || {};
    const currentMonthStr = new Date().toLocaleDateString('id-ID', { month: 'long' });
    const currentYearStr = new Date().getFullYear().toString();
    const currentMonthNum = new Date().getMonth() + 1;

    let total = 0;
    if (s.tunggakan_list) {
      s.tunggakan_list.forEach(function (t) {
        if (t.periode_tagihan && t.periode_tagihan.toLowerCase().includes(currentMonthStr.toLowerCase()) && t.periode_tagihan.includes(currentYearStr)) {
          total += Number(t.jml_tunggakan || t.jumlah_tagihan_awal || 0);
        }
      });
    }
    if (keg && keg.data) {
      const kelasStr = (s.kelas) ? s.kelas.toUpperCase() : '';
      const isKelasXI_XII = kelasStr.startsWith('XI') || kelasStr.startsWith('XII');
      keg.data.forEach(function (row) {
        let isHidden = false;
        if (isKelasXI_XII && currentMonthNum === 7 && (row.nama_kegiatan || '').toUpperCase().includes('DSP')) isHidden = true;
        const isKegAkhirTahun = (row.nama_kegiatan || '').trim().toLowerCase() === 'kegiatan akhir tahun';
        if (isKegAkhirTahun && (currentMonthNum < 1 || currentMonthNum > 4)) isHidden = true;
        if (!isHidden) {
          const sisa = row.sisa_tagihan !== undefined && row.sisa_tagihan !== null ? row.sisa_tagihan : row.jumlah;
          total += Number(sisa);
        }
      });
    }
    return total;
  }

  function updateDashboardSiswa(j, cards, keg) {
    const s = j.siswa || {};
    cards[0].textContent = formatRupiah(hitungTotalBulanIni(j, keg));
    cards[1].textContent = Number(s.jml_tunggakan) > 0 ? 'Belum Bayar' : 'Lunas';
    const jt = s.tgl_jatuh_tempo ? formatTanggalId(s.tgl_jatuh_tempo) : '-';
    cards[2].textContent = jt;
    cards[3].textContent = formatRupiah(j.sisa_tunggakan);

    const bar = document.getElementById('progressBarSPP');
    if (bar) {
      bar.style.width = (j.progress_pct || 0) + '%';
    }
    const pct = document.getElementById('progressLabelSPP');
    if (pct) {
      pct.textContent = (j.progress_pct || 0) + '% pembayaran selesai';
    }

    const inpBulan = document.getElementById('inputBulanTagihanSpp');
    if (inpBulan) {
      inpBulan.value = j.siswa.periode_tagihan || '-';
    }

    const tb = document.querySelector('table.w-full.text-sm tbody');
    if (tb) {
      tb.innerHTML = '';
      (j.recent || []).forEach(function (r) {
        const tr = document.createElement('tr');
        tr.className = 'border-b';
        tr.innerHTML =
          '<td class="p-2">' +
          (r.bulan || '') +
          '</td>' +
          '<td class="p-2">' +
          formatTanggalId(r.tgl_transaksi) +
          '</td>' +
          '<td class="p-2 text-green-600 font-bold">Lunas</td>' +
          '<td class="p-2">' +
          formatRupiah(r.jml_bayar) +
          '</td>';
        tb.appendChild(tr);
      });
    }

    const ctx = document.getElementById('chartSPP');
    if (ctx && typeof Chart !== 'undefined') {
      const labels = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
      const data = labels.map(function (_, i) {
        return j.chart_months && j.chart_months[i + 1] ? j.chart_months[i + 1] : 0;
      });
      new Chart(ctx, {
        type: 'bar',
        data: {
          labels: labels,
          datasets: [
            {
              label: 'Pembayaran (Rp)',
              data: data,
              backgroundColor: ['#3b82f6'],
            },
          ],
        },
      });
    }
  }

  window.konfirmasiTransfer = async function () {
    const rawVal = document.getElementById('idTunggakanSpp')?.value;
    const nominal = document.getElementById('inputJumlahTagihanSpp')?.value;
    const bukti = document.getElementById('buktiTransfer')?.files[0];

    if (!rawVal) {
      alert('Silakan pilih tagihan terlebih dahulu.');
      return;
    }
    if (!nominal || Number(nominal) <= 0) {
      alert('Masukkan nominal pembayaran yang valid.');
      return;
    }
    if (!bukti) {
      alert('Silakan upload bukti transfer.');
      return;
    }

    let endpoint = '';
    const fd = new FormData();
    fd.append('tgl_transaksi', new Date().toISOString().slice(0, 10));
    fd.append('jml_bayar', parseRupiah(nominal));
    fd.append('bukti_transfer', bukti);

    if (rawVal.startsWith('kegiatan_')) {
      endpoint = 'backend/proses/siswa/konfirmasi_bayar_kegiatan.php';
      fd.append('id_tagihan_keg', rawVal.replace('kegiatan_', ''));
    } else {
      endpoint = 'backend/proses/siswa/konfirmasi_bayar_spp.php';
      fd.append('id_tunggakan', rawVal.replace('spp_', ''));
    }

    const res = await fetch(BASE + endpoint, {
      method: 'POST',
      body: fd,
      credentials: 'same-origin',
    });
    if (res.ok) {
      alert('Pembayaran berhasil dikonfirmasi.');
      window.location.reload();
    } else {
      const err = await res.json().catch(() => null);
      alert('Gagal mencatat pembayaran: ' + (err?.error || 'Pastikan data valid.'));
    }
  };

  /* ---------- Tagihan SPP (siswa) — sinkron dengan admin (transaksi → Pembayaran Masuk) ---------- */
  async function initTagihanSpp() {
    await updateStudentSidebarProfile();
    const dash = await apiJson('backend/data/get_dashboard_siswa.php');
    const keg = await apiJson('backend/data/get_tagihan_kegiatan.php');
    const prof = await apiJson('backend/data/get_profile.php');

    if (!dash || !dash.success) {
      return;
    }

    const totalEl = document.getElementById('tagihanTotalValue');
    const statusEl = document.getElementById('tagihanStatusValue');
    const progressEl = document.getElementById('progressPembayaran');
    const now = new Date();
    const currentMonthYear = now.toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
    const statusMonth = dash.siswa && dash.siswa.periode_tagihan ? dash.siswa.periode_tagihan : currentMonthYear;

    if (totalEl) {
      totalEl.textContent = formatRupiah(dash.sisa_tunggakan || 0);
    }

    const hasTunggakanRow = Boolean(dash.siswa && dash.siswa.id_tunggakan);
    const unpaid = Number(dash.sisa_tunggakan || 0) > 0 || !hasTunggakanRow;
    const paidMonths = new Set(Object.keys(dash.chart_months || {}).map(function (m) {
      return Number(m);
    }));

    if (statusEl) {
      statusEl.textContent = `${statusMonth} ${unpaid ? 'Belum Bayar' : 'Lunas'}`;
    }

    if (progressEl) {
      progressEl.innerHTML = '';
      const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
      const currentMonth = new Date().getMonth() + 1;

      months.forEach(function (label, index) {
        const monthIndex = index + 1;
        const paid = paidMonths.has(monthIndex);
        const item = document.createElement('div');
        const bubble = document.createElement('div');

        let bubbleClass = 'w-10 h-10 mx-auto flex items-center justify-center rounded-sm text-xs ';

        if (monthIndex === currentMonth) {
          bubbleClass += 'bg-gradient-to-r from-blue-600 to-green-500 text-white font-semibold';
        } else if (paid) {
          bubbleClass += 'bg-blue-300 text-white';
        } else {
          bubbleClass += 'bg-gray-200 text-gray-400';
        }

        bubble.className = bubbleClass;
        bubble.textContent = paid ? '✔' : '-';
        const labelEl = document.createElement('div');
        labelEl.className = 'mt-4 mb-4';
        labelEl.textContent = label;
        item.appendChild(bubble);
        item.appendChild(labelEl);
        progressEl.appendChild(item);
      });
    }

    const idTunEl = document.getElementById('idTunggakanSpp');
    const inpBulan = document.getElementById('inputBulanTagihanSpp');
    const inpJml = document.getElementById('inputJumlahTagihanSpp');
    if (idTunEl && dash.siswa && dash.siswa.id_tunggakan) {
      idTunEl.value = dash.siswa.id_tunggakan;
    }
    if (inpBulan) {
      inpBulan.value = dash.siswa.periode_tagihan || '-';
    }
    if (inpJml) {
      inpJml.value = formatRupiah(dash.sisa_tunggakan);
    }

    if (prof && prof.siswa) {
      await updateStudentSidebarProfile();
    }

    const tbody = document.getElementById('tbodyKegiatanSpp');
    if (tbody && keg && keg.success) {
      tbody.innerHTML = '';
      (keg.data || []).forEach(function (row) {
        const currentMonthNum = new Date().getMonth() + 1;
        let isKegAkhirTahun = (row.nama_kegiatan || '').toLowerCase() === 'kegiatan akhir tahun';
        if (isKegAkhirTahun && (currentMonthNum < 1 || currentMonthNum > 4)) {
            return;
        }

        const lunas = row.status === 'lunas';
        const tr = document.createElement('tr');
        tr.className = 'border-t';

        const td1 = document.createElement('td');
        td1.className = 'px-6 py-4';
        td1.textContent = row.nama_kegiatan || '';
        const td2 = document.createElement('td');
        td2.className = 'px-6 py-4';
        td2.textContent = row.kelas_label || '';
        const td3 = document.createElement('td');
        td3.className = 'px-6 py-4';
        td3.innerHTML = lunas
          ? '<span class="bg-green-100 text-green-600 px-2 py-1 rounded text-xs font-semibold">Lunas</span>'
          : '<span class="bg-red-100 text-red-600 px-2 py-1 rounded text-xs font-semibold">Belum Bayar</span>';
        const td4 = document.createElement('td');
        td4.className = 'px-6 py-4';
        td4.textContent = formatRupiah(row.jumlah);
        const td5 = document.createElement('td');
        td5.className = 'px-6 py-4';
        td5.textContent = row.tgl_bayar ? formatTanggalId(row.tgl_bayar) : '–';
        const td6 = document.createElement('td');
        td6.className = 'px-6 py-4';

        if (lunas) {
          const b = document.createElement('button');
          b.type = 'button';
          b.className = 'bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 text-sm';
          b.textContent = 'Lihat Kwitansi';
          b.addEventListener('click', function () {
            document.getElementById('kwitansiNama').textContent = row.nama_kegiatan || '';
            document.getElementById('kwitansiTgl').textContent = row.tgl_bayar
              ? formatTanggalId(row.tgl_bayar)
              : '–';
            document.getElementById('kwitansiJml').textContent = formatRupiah(row.jumlah);
            document.getElementById('popupKwitansi').classList.remove('hidden');
            document.getElementById('popupKwitansi').classList.add('flex');
          });
          td6.appendChild(b);
        } else {
          const b = document.createElement('button');
          b.type = 'button';
          b.className = 'bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm';
          b.textContent = 'Bayar Sekarang';
          b.addEventListener('click', function () {
            document.getElementById('idTagihanKegiatan').value = row.id_tagihan_keg;
            document.getElementById('namaKegiatan').value = row.nama_kegiatan || '';
            document.getElementById('jumlahKegiatan').value = formatRupiah(row.jumlah);
            document.getElementById('popupBayarKegiatan').classList.remove('hidden');
            document.getElementById('popupBayarKegiatan').classList.add('flex');
          });
          td6.appendChild(b);
        }

        tr.appendChild(td1);
        tr.appendChild(td2);
        tr.appendChild(td3);
        tr.appendChild(td4);
        tr.appendChild(td5);
        tr.appendChild(td6);
        tbody.appendChild(tr);
      });
    }

    window.konfirmasiTransfer = async function () {
      const rawVal = document.getElementById('idTunggakanSpp').value;
      if (!rawVal) {
        alert('Silakan pilih tagihan terlebih dahulu.');
        return;
      }

      let endpoint = '';
      const fd = new FormData();
      fd.append('tgl_transaksi', new Date().toISOString().slice(0, 10));

      if (rawVal.startsWith('kegiatan_')) {
        endpoint = 'backend/proses/siswa/konfirmasi_bayar_kegiatan.php';
        fd.append('id_tagihan_keg', rawVal.replace('kegiatan_', ''));
      } else {
        endpoint = 'backend/proses/siswa/konfirmasi_bayar_spp.php';
        fd.append('id_tunggakan', rawVal.replace('spp_', ''));
      }

      const r = await fetch(BASE + endpoint, {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
      });
      if (r.ok) {
        window.location.reload();
      } else {
        alert('Gagal mencatat pembayaran. Pastikan data valid.');
      }
    };

    window.konfirmasiKegiatan = async function () {
      const id = document.getElementById('idTagihanKegiatan').value;
      if (!id) {
        return;
      }
      const fd = new FormData();
      fd.append('id_tagihan_keg', id);
      fd.append('tgl_transaksi', new Date().toISOString().slice(0, 10));
      const r = await fetch(BASE + 'backend/proses/siswa/konfirmasi_bayar_kegiatan.php', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
      });
      if (r.ok) {
        window.location.reload();
      } else {
        alert('Gagal mencatat pembayaran kegiatan.');
      }
    };
  }

  /* ---------- Riwayat pembayaran ---------- */
  async function initRiwayat() {
    await updateStudentSidebarProfile();

    // Load from cache first
    let j = getCache('laporan_pembayaran');
    if (j) {
      updateRiwayat(j);
    }

    // Then fetch from server
    const jServer = await apiJson('backend/data/get_laporan_pembayaran.php');
    if (jServer && jServer.success) {
      j = jServer;
      setCache('laporan_pembayaran', j);
      updateRiwayat(j);
    }
  }

  function updateRiwayat(j) {
    const tbody = document.getElementById('tbodyRiwayat');
    if (!tbody) return;

    const rows = (j.data || []).slice().sort(function (a, b) {
      return new Date(b.tgl_transaksi) - new Date(a.tgl_transaksi);
    });

    tbody.innerHTML = '';
    rows.forEach(function (r) {
      const d = new Date((r.tgl_transaksi || '') + 'T00:00:00');
      const bulan = d.toLocaleDateString('id-ID', { month: 'long' });
      const tr = document.createElement('tr');
      tr.className = 'border-t';
      tr.innerHTML =
        '<td class="px-6 py-4">' +
        bulan +
        '</td>' +
        '<td class="px-6 py-4">' +
        formatTanggalId(r.tgl_transaksi) +
        '</td>' +
        '<td class="px-6 py-4">Lunas</td>' +
        '<td class="px-6 py-4">' +
        formatRupiah(r.jml_bayar) +
        '</td>';
      tbody.appendChild(tr);
    });
  }

  /* ---------- Pembayaran masuk ---------- */
  async function initPembayaranMasuk() {
    const tb = document.querySelector('main table tbody');
    if (!tb) return;

    const j = await apiJson('backend/data/get_transaksi.php');
    if (!j || !j.success) return;
    tb.innerHTML = '';
    (j.data || []).forEach(function (r) {
      const tr = document.createElement('tr');
      tr.innerHTML =
        '<td class="px-6 py-4 whitespace-nowrap">' +
        (r.nama_siswa || '') +
        '</td>' +
        '<td class="px-6 py-4 whitespace-nowrap">' +
        (r.kelas || '') +
        '</td>' +
        '<td class="px-6 py-4 whitespace-nowrap">' +
        formatTanggalId(r.tgl_transaksi) +
        '</td>' +
        '<td class="px-6 py-4 whitespace-nowrap">' +
        formatRupiah(r.jml_bayar) +
        '</td>' +
        '<td class="px-6 py-4 whitespace-nowrap">' +
        (r.keterangan || '') +
        '</td>' +
        '<td class="px-6 py-4 whitespace-nowrap">' +
        (Number(r.has_bukti) ? '<button onclick="bukaModalBukti(\'' + BASE + 'backend/data/get_bukti.php?id=' + r.id_transaksi + '\')" class="text-blue-500 underline hover:text-blue-700">Lihat</button>' : (r.bukti_transfer ? '<button onclick="bukaModalBukti(\'' + BASE + r.bukti_transfer + '\')" class="text-blue-500 underline hover:text-blue-700">Lihat</button>' : '-')) +
        '</td>';
      tb.appendChild(tr);
    });

    const selSiswa = document.getElementById('namaPembayaran');
    if (selSiswa) {
      const s = await apiJson('backend/data/get_siswa.php');
      if (s && s.success && s.data) {
        selSiswa.innerHTML = '<option value="">-- Pilih Siswa --</option>';
        s.data.forEach(function (siswa) {
          const opt = document.createElement('option');
          opt.value = siswa.id_siswa;
          opt.textContent = siswa.nama_siswa;
          opt.dataset.kelas = siswa.kelas || '';
          opt.dataset.nama = siswa.nama_siswa || '';
          selSiswa.appendChild(opt);
        });

        selSiswa.addEventListener('change', function () {
          const kelasInput = document.getElementById('kelasPembayaran');
          const opt = this.options[this.selectedIndex];
          if (kelasInput) {
            kelasInput.value = opt ? (opt.dataset.kelas || '') : '';
          }
        });
      }
    }

    // Muat data Pemasukan Lain-lain yang tersimpan (agar tetap tampil setelah reload)
    const tbodyPemasukan = document.getElementById('tabelPemasukanLain');
    if (tbodyPemasukan) {
      const p = await apiJson('backend/data/get_pemasukan.php');
      if (p && p.success) {
        tbodyPemasukan.innerHTML = '';
        (p.data || []).forEach(function (r) {
          const tr = document.createElement('tr');
          tr.innerHTML =
            '<td class="px-6 py-4">' + formatTanggalId(r.tgl_uang) + '</td>' +
            '<td class="px-6 py-4">' + (r.sumber_dana || '-') + '</td>' +
            '<td class="px-6 py-4">' + (r.kategori || '') + '</td>' +
            '<td class="px-6 py-4">' + formatRupiah(r.jml_uang) + '</td>' +
            '<td class="px-6 py-4">' + (r.ket_uang || '') + '</td>';
          tbodyPemasukan.appendChild(tr);
        });
      }
    }
  }

  /* ---------- Kepsek dashboard ---------- */
  async function initKepsek() {
    const table = document.getElementById('tabelRingkasan');
    if (!table) return;

    function setSelectOptions(select, values, formatter) {
      if (!select || !Array.isArray(values)) return;
      const currentValue = select.value;
      select.innerHTML = '';
      values.forEach(function (value) {
        const option = document.createElement('option');
        option.value = String(value);
        option.textContent = formatter ? formatter(value) : String(value);
        select.appendChild(option);
      });
      if (currentValue && Array.from(select.options).some(function (opt) { return opt.value === currentValue; })) {
        select.value = currentValue;
      }
    }

    async function load(forceYearMonth) {
      const thn = document.getElementById('filterTahun');
      const bln = document.getElementById('filterBulan');
      const y = thn ? thn.value : 'all';
      const m = bln ? bln.value : 'all';
      const cacheKey = 'ringkasan_kepsek_' + y + '_' + m;
      const yearFilter = y === 'all' ? null : Number(y);
      const monthFilter = m === 'all' ? null : Number(m);

      // Load from cache first
      let j = getCache(cacheKey);
      if (j) {
        updateRingkasan(j, y, m);
      }

      // Then fetch from server
      const jServer = await apiJson('backend/data/get_ringkasan_kepsek.php?tahun=' + y + '&bulan=' + m);
      if (jServer && jServer.success) {
        j = jServer;
        setCache(cacheKey, j);
        updateRingkasan(j, y, m);
      }

      // Also load pemasukan total and merged table rows
      const [jPembayaran, jPengeluaran, jPemasukan] = await Promise.all([
        apiJson('backend/data/get_transaksi.php'),
        apiJson('backend/data/get_pengeluaran.php'),
        apiJson('backend/data/get_pemasukan.php'),
      ]);

      let totalPemasukan = 0;
      let totalPengeluaran = 0;
      let combinedRows = [];

      if (jPembayaran && jPembayaran.success) {
        combinedRows = combinedRows.concat(
          (jPembayaran.data || []).filter(function (item) {
            const d = new Date((item.tgl_transaksi || '') + 'T00:00:00');
            return (yearFilter === null || d.getFullYear() === yearFilter) &&
              (monthFilter === null || d.getMonth() + 1 === monthFilter);
          }).map(function (item) {
            totalPemasukan += Number(item.jml_bayar || 0);
            return {
              tgl: item.tgl_transaksi,
              deskripsi: item.keterangan || 'Pembayaran Masuk',
              pemasukan: Number(item.jml_bayar || 0),
              pengeluaran: 0,
            };
          })
        );
      }

      if (jPemasukan && jPemasukan.success) {
        combinedRows = combinedRows.concat(
          (jPemasukan.data || []).filter(function (item) {
            const d = new Date((item.tgl_uang || '') + 'T00:00:00');
            return (yearFilter === null || d.getFullYear() === yearFilter) &&
              (monthFilter === null || d.getMonth() + 1 === monthFilter);
          }).map(function (item) {
            totalPemasukan += Number(item.jml_uang || 0);
            const sumber = item.sumber_dana ? ' (' + item.sumber_dana + ')' : '';
            return {
              tgl: item.tgl_uang,
              deskripsi: (item.kategori || 'Pemasukan Lain') + sumber + (item.ket_uang ? ' - ' + item.ket_uang : ''),
              pemasukan: Number(item.jml_uang || 0),
              pengeluaran: 0,
            };
          })
        );
      }

      if (jPengeluaran && jPengeluaran.success) {
        combinedRows = combinedRows.concat(
          (jPengeluaran.data || []).filter(function (item) {
            const d = new Date((item.tgl_uang || '') + 'T00:00:00');
            return (yearFilter === null || d.getFullYear() === yearFilter) &&
              (monthFilter === null || d.getMonth() + 1 === monthFilter);
          }).map(function (item) {
            totalPengeluaran += Number(item.jml_uang || 0);
            return {
              tgl: item.tgl_uang,
              deskripsi: (item.kategori || 'Pengeluaran') + (item.ket_uang ? ' - ' + item.ket_uang : ''),
              pemasukan: 0,
              pengeluaran: Number(item.jml_uang || 0),
            };
          })
        );
      }

      renderCombinedTable(combinedRows);

      const elPemasukan = document.getElementById('totalPemasukan');
      const elPengeluaran = document.getElementById('totalPengeluaran');
      const elSaldo = document.getElementById('saldo');

      if (elPemasukan) {
        elPemasukan.textContent = formatRupiah(totalPemasukan);
      }
      if (elPengeluaran) {
        elPengeluaran.textContent = formatRupiah(totalPengeluaran);
      }
      if (elSaldo) {
        elSaldo.textContent = formatRupiah(totalPemasukan - totalPengeluaran);
      }
    }

    function renderCombinedTable(rows) {
      table.innerHTML = '';
      rows.sort(function (a, b) {
        return new Date(b.tgl) - new Date(a.tgl);
      }).forEach(function (row) {
        const tr = document.createElement('tr');
        tr.className = 'text-center';
        tr.innerHTML =
          '<td class="px-4 py-2 border">' +
          formatTanggalId(row.tgl) +
          '</td>' +
          '<td class="px-4 py-2 border">' +
          (row.deskripsi || '') +
          '</td>' +
          '<td class="px-4 py-2 border text-green-600">' +
          (row.pemasukan ? formatRupiah(row.pemasukan) : '-') +
          '</td>' +
          '<td class="px-4 py-2 border text-red-600">' +
          (row.pengeluaran ? formatRupiah(row.pengeluaran) : '-') +
          '</td>';
        table.appendChild(tr);
      });
    }

    function updateRingkasan(j, y, m) {
      const title = document.getElementById('judulRingkasan');
      const monthNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
      let titleText = 'Ringkasan Keuangan';

      if (typeof m !== 'undefined' && m !== null) {
        if (m === 'all') {
          titleText += ' Semua Bulan';
        } else {
          const monthIndex = Number(m) - 1;
          titleText += ' Bulan ' + (monthNames[monthIndex] || m);
        }
      }
      if (typeof y !== 'undefined' && y !== null) {
        if (y === 'all') {
          titleText += ' Semua Tahun';
        } else {
          titleText += ' Tahun ' + y;
        }
      }
      if (title) {
        title.textContent = titleText;
      }

      if (Array.isArray(j.available_years) && j.available_years.length) {
        setSelectOptions(document.getElementById('filterTahun'), ['all'].concat(j.available_years), function (y) {
          return y === 'all' ? 'Semua' : String(y);
        });
      }
      if (Array.isArray(j.available_months) && j.available_months.length) {
        setSelectOptions(document.getElementById('filterBulan'), ['all'].concat(j.available_months), function (m) {
          if (m === 'all') return 'Semua';
          return monthNames[m - 1] || String(m);
        });
      }
    }

    const thn = document.getElementById('filterTahun');
    if (thn) {
      thn.addEventListener('change', load);
    }
    const bln = document.getElementById('filterBulan');
    if (bln) {
      bln.addEventListener('change', load);
    }

    const btn = document.getElementById('btnTampilkanRingkasan');
    if (btn) {
      btn.addEventListener('click', function () {
        load(true);
      });
    }

    load();
  }

  document.addEventListener('DOMContentLoaded', function () {
    wireLogout();

    const p = location.pathname.split('/').pop() || '';

    if (p === 'Laporan_Pengeluaran.html') {
      initLaporanPengeluaran();
    } else if (p === 'Tagihan_Bulanan.html') {
      initTagihanBulanan();
    } else if (p === 'Data_Siswa.html') {
      initDataSiswa();
    } else if (p === 'index_admin.html') {
      initIndexAdmin();
    } else if (p === 'Index_siswa.html') {
      initIndexSiswa();
    } else if (p === 'Tagihan_Spp.html') {
      initTagihanSpp();
    } else if (p === 'Riwayat_pembayaran.html') {
      initRiwayat();
    } else if (p === 'Bantuan_Siswa.html') {
      initBantuan();
    } else if (p === 'Pembayaran_Masuk.html') {
      initPembayaranMasuk();
    } else if (p === 'index_kepsek.html' || p === 'Laporan_Keuangan_Admin.html') {
      initKepsek();
    }
  });

  function updateTagihanDropdown(j, keg) {
    const sel = document.getElementById('idTunggakanSpp');
    if (!sel) return;
    sel.innerHTML = '<option value="">- Tidak ada tagihan -</option>';

    let adaTagihan = false;

    const currentMonthStr = new Date().toLocaleDateString('id-ID', { month: 'long' });
    const currentYearStr = new Date().getFullYear().toString();
    const currentMonthNum = new Date().getMonth() + 1;

    if (j && j.siswa && j.siswa.tunggakan_list) {
      j.siswa.tunggakan_list.forEach(function (t) {
        if (Number(t.jml_tunggakan) > 0) {
          const opt = document.createElement('option');
          opt.value = 'spp_' + t.id_tunggakan;
          let label = t.periode_tagihan || '';
          if (!label.toLowerCase().startsWith('spp')) label = 'SPP ' + label;
          opt.textContent = label;
          opt.dataset.sisa = t.jml_tunggakan;
          opt.dataset.total = t.jumlah_tagihan_awal || t.jml_tunggakan;
          sel.appendChild(opt);
          adaTagihan = true;
        }
      });
    }

    if (keg && keg.success && keg.data) {
      keg.data.forEach(function (k) {
        if (k.status !== 'lunas' && Number(k.jumlah) > 0) {
          const kelasStr = (j.siswa && j.siswa.kelas) ? j.siswa.kelas.toUpperCase() : '';
          const isKelasXI_XII = kelasStr.startsWith('XI') || kelasStr.startsWith('XII');
          
          let isHidden = false;
          if (isKelasXI_XII && currentMonthNum === 7 && (k.nama_kegiatan || '').toUpperCase().includes('DSP')) {
              isHidden = true;
          }
          
          let isKegAkhirTahun = (k.nama_kegiatan || '').trim().toLowerCase() === 'kegiatan akhir tahun';
          if (isKegAkhirTahun && (currentMonthNum < 1 || currentMonthNum > 4)) {
              isHidden = true;
          }
          
          if (!isHidden) {
            const opt = document.createElement('option');
            opt.value = 'kegiatan_' + k.id_tagihan_keg;
            opt.textContent = 'Kegiatan ' + (k.nama_kegiatan || '');
            const sisa = k.sisa_tagihan !== undefined && k.sisa_tagihan !== null ? k.sisa_tagihan : k.jumlah;
            opt.dataset.sisa = sisa;
            opt.dataset.total = k.jumlah;
            sel.appendChild(opt);
            adaTagihan = true;
          }
        }
      });
    }

    sel.onchange = function () {
      const infoTotal = document.getElementById('infoTotalTagihan');
      const infoSisa = document.getElementById('infoSisaTagihan');
      const opt = sel.options[sel.selectedIndex];
      if (opt && opt.value !== '') {
        if (infoTotal) infoTotal.value = formatRupiah(opt.dataset.total || 0);
        if (infoSisa) infoSisa.value = formatRupiah(opt.dataset.sisa || 0);
      } else {
        if (infoTotal) infoTotal.value = '';
        if (infoSisa) infoSisa.value = '';
      }
    };
  }

  async function initTagihanSpp() {
    let j = getCache('dashboard_siswa');
    let keg = getCache('tagihan_kegiatan');

    const [jServer, kegServer] = await Promise.all([
      apiJson('backend/data/get_dashboard_siswa.php'),
      apiJson('backend/data/get_tagihan_kegiatan.php')
    ]);

    if (jServer && jServer.success) { j = jServer; setCache('dashboard_siswa', j); }
    if (kegServer && kegServer.success) { keg = kegServer; setCache('tagihan_kegiatan', keg); }

    if (j && keg) {
      const s = j.siswa || {};

      let totalBulanIni = 0;
      const currentMonthStr = new Date().toLocaleDateString('id-ID', { month: 'long' });
      const currentYearStr = new Date().getFullYear().toString();
      const currentMonthNum = new Date().getMonth() + 1;
      
      if (s.tunggakan_list) {
          s.tunggakan_list.forEach(function(t) {
              if (t.periode_tagihan && t.periode_tagihan.toLowerCase().includes(currentMonthStr.toLowerCase()) && t.periode_tagihan.includes(currentYearStr)) {
                  totalBulanIni += Number(t.jml_tunggakan || t.jumlah_tagihan_awal || 0);
              }
          });
      }
      
      if (keg && keg.data) {
          keg.data.forEach(function(row) {
              const kelasStr = (s.kelas) ? s.kelas.toUpperCase() : '';
              const isKelasXI_XII = kelasStr.startsWith('XI') || kelasStr.startsWith('XII');
              
              let isHidden = false;
              if (isKelasXI_XII && currentMonthNum === 7 && (row.nama_kegiatan || '').toUpperCase().includes('DSP')) {
                  isHidden = true;
              }
              
              let isKegAkhirTahun = (row.nama_kegiatan || '').trim().toLowerCase() === 'kegiatan akhir tahun';
              if (isKegAkhirTahun && (currentMonthNum < 1 || currentMonthNum > 4)) {
                  isHidden = true;
              }
              
              if (!isHidden) {
                  const sisa = row.sisa_tagihan !== undefined && row.sisa_tagihan !== null ? row.sisa_tagihan : row.jumlah;
                  totalBulanIni += Number(sisa);
              }
          });
      }

      const elTotal = document.getElementById('tagihanTotalValue');
      if (elTotal) elTotal.textContent = formatRupiah(totalBulanIni);

      const elStatus = document.getElementById('tagihanStatusValue');
      if (elStatus) {
        if (Number(s.jml_tunggakan) > 0) {
          elStatus.innerHTML = '<span class="text-red-500">Belum Bayar</span>';
        } else {
          elStatus.innerHTML = '<span class="text-green-500">Lunas</span>';
        }
      }

      const progressContainer = document.getElementById('progressPembayaran');
      if (progressContainer) {
        progressContainer.innerHTML = '';
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];
        const chartData = j.chart_months || {};
        const currentMonth = new Date().getMonth() + 1;
        for (let i = 1; i <= 12; i++) {
          const div = document.createElement('div');
          const isPaid = chartData[i] > 0;
          let className = 'rounded py-2 shadow-sm cursor-pointer transition text-center ';

          if (i === currentMonth) {
            className += 'bg-gradient-to-r from-blue-600 to-green-500 text-white font-semibold hover:opacity-90';
          } else if (isPaid) {
            className += 'bg-green-500 text-white font-semibold hover:bg-green-600';
          } else {
            className += 'bg-gray-200 text-gray-500 hover:bg-gray-300';
          }

          div.className = className;
          div.textContent = months[i - 1];
          div.onclick = function () {
            if (typeof window.showMonthProgressDetail === 'function') {
              window.showMonthProgressDetail(i, months[i - 1], j, isPaid, keg);
            }
          };
          progressContainer.appendChild(div);
        }
      }

      updateTagihanDropdown(j, keg);

      const tbody = document.getElementById('tbodyKegiatanSpp');
      if (tbody && keg.success) {
        tbody.innerHTML = '';
        (keg.data || []).forEach(function (row) {
          const currentMonthNum = new Date().getMonth() + 1;
          const kelasStr = (j.siswa && j.siswa.kelas) ? j.siswa.kelas.toUpperCase() : '';
          const isKelasXI_XII = kelasStr.startsWith('XI') || kelasStr.startsWith('XII');
          
          if (isKelasXI_XII && currentMonthNum === 7 && (row.nama_kegiatan || '').toUpperCase().includes('DSP')) {
            return; // Hide DSP in July for Class XI and XII
          }

          let isKegAkhirTahun = (row.nama_kegiatan || '').trim().toLowerCase() === 'kegiatan akhir tahun';
          if (isKegAkhirTahun && (currentMonthNum < 1 || currentMonthNum > 4)) {
            return; // Hide Kegiatan Akhir Tahun if month is not Jan-Apr
          }

          const lunas = row.status === 'lunas';
          const cicilan = row.status === 'cicilan';

          let statBadge = '';
          if (lunas) {
            statBadge = '<span class="bg-green-100 text-green-600 px-2 py-1 rounded text-xs font-semibold">Lunas</span>';
          } else if (cicilan) {
            statBadge = '<span class="bg-yellow-100 text-yellow-700 px-2 py-1 rounded text-xs font-semibold">Cicilan</span>';
          } else {
            statBadge = '<span class="bg-red-100 text-red-600 px-2 py-1 rounded text-xs font-semibold">Belum Bayar</span>';
          }

          const tr = document.createElement('tr');
          tr.className = 'border-t hover:bg-gray-50';

          tr.innerHTML =
            '<td class="px-6 py-4">' + (row.nama_kegiatan || '') + '</td>' +
            '<td class="px-6 py-4">' + statBadge + '</td>' +
            '<td class="px-6 py-4">' + formatRupiah(row.sisa_tagihan !== undefined && row.sisa_tagihan !== null ? row.sisa_tagihan : row.jumlah) + '</td>' +
            '<td class="px-6 py-4">' + (row.tgl_bayar ? formatTanggalId(row.tgl_bayar) : '-') + '</td>';

          tbody.appendChild(tr);
        });
      }

      await updateStudentSidebarProfile();
    }
  }

  async function initBantuan() {
    await updateStudentSidebarProfile();
  }

  window.showMonthProgressDetail = async function (monthNum, monthName, dashData, isPaid, kegData) {
    let modal = document.getElementById('modalMonthProgress');
    if (!modal) {
      modal = document.createElement('div');
      modal.id = 'modalMonthProgress';
      modal.className = 'fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-[100] hidden';
      modal.innerHTML = `
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md overflow-hidden relative m-4 transition-all transform scale-100">
          <div class="bg-gradient-to-r from-blue-600 to-blue-800 text-white px-6 py-4 flex justify-between items-center">
            <h3 id="mmpTitle" class="text-xl font-bold">Detail Bulan</h3>
            <button onclick="document.getElementById('modalMonthProgress').classList.add('hidden')" class="text-white hover:text-gray-200 transition focus:outline-none">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>
          <div class="p-6">
            <div id="mmpStatus" class="mb-5 text-center"></div>
            
            <h4 class="font-semibold text-gray-700 mb-2 border-b pb-1">Tagihan/Tunggakan Bulan Ini</h4>
            <ul id="mmpTunggakanList" class="mb-5 space-y-2"></ul>
            
            <h4 class="font-semibold text-gray-700 mb-2 border-b pb-1">Riwayat Pembayaran di Bulan Ini</h4>
            <ul id="mmpRiwayatList" class="space-y-2 max-h-48 overflow-y-auto"></ul>
          </div>
        </div>
      `;
      document.body.appendChild(modal);
    }

    const monthNamesFull = {
      'Jan': 'Januari', 'Feb': 'Februari', 'Mar': 'Maret', 'Apr': 'April',
      'Mei': 'Mei', 'Jun': 'Juni', 'Jul': 'Juli', 'Ags': 'Agustus',
      'Sep': 'September', 'Okt': 'Oktober', 'Nov': 'November', 'Des': 'Desember'
    };
    const fullMonthName = monthNamesFull[monthName] || monthName;

    document.getElementById('mmpTitle').textContent = 'Detail ' + fullMonthName;

    const statusEl = document.getElementById('mmpStatus');
    if (isPaid) {
      statusEl.innerHTML = '<div class="inline-block px-4 py-1 rounded-full bg-green-100 text-green-700 font-semibold text-sm">Terdapat Transaksi Bulan Ini</div>';
    } else {
      statusEl.innerHTML = '<div class="inline-block px-4 py-1 rounded-full bg-gray-100 text-gray-600 font-semibold text-sm">Belum Ada Transaksi</div>';
    }

    const tList = document.getElementById('mmpTunggakanList');
    tList.innerHTML = '';
    let foundTunggakan = false;
    let hasRealSpp = false;
    
    if (dashData && dashData.siswa && dashData.siswa.tunggakan_list) {
      dashData.siswa.tunggakan_list.forEach(function (t) {
        if (t.periode_tagihan && t.periode_tagihan.toLowerCase().includes(fullMonthName.toLowerCase())) {
          foundTunggakan = true;
          if (t.periode_tagihan.toLowerCase().includes('spp')) hasRealSpp = true;
          let label = t.periode_tagihan;
          if (label.toLowerCase().includes('spp')) {
            label = 'SPP Bulanan';
          }
          tList.innerHTML += '<li class="text-sm flex justify-between bg-red-50 p-3 rounded-lg border border-red-100"><span class="font-medium text-red-700">' + label + '</span> <span class="text-gray-800 font-semibold">' + formatRupiah(t.jml_tunggakan) + '</span></li>';
        }
      });
    }

    const kelasStr = (dashData && dashData.siswa && dashData.siswa.kelas) ? dashData.siswa.kelas.toUpperCase() : '';
    const isKelasXI_XII = kelasStr.startsWith('XI') || kelasStr.startsWith('XII');
    const isKelasX = kelasStr.startsWith('X') && !isKelasXI_XII; // Ensure X is not XI or XII
    
    const isJuli = (fullMonthName.toLowerCase() === 'juli');
    const isAgustus = (fullMonthName.toLowerCase() === 'agustus');
    
    const showKegData = (isKelasXI_XII && isAgustus) || (!isKelasXI_XII && isJuli);
    const isJanToApr = ['januari', 'februari', 'maret', 'april'].includes(fullMonthName.toLowerCase());

    if (kegData && kegData.success && kegData.data) {
      kegData.data.forEach(function (k) {
        let isKegAkhirTahun = (k.nama_kegiatan || '').toLowerCase() === 'kegiatan akhir tahun';
        let showThisKeg = false;
        
        if (isKegAkhirTahun && kelasStr.startsWith('XII') && isJanToApr) {
            showThisKeg = true;
        } else if (showKegData && !isKegAkhirTahun) {
            showThisKeg = true;
        }

        if (showThisKeg && k.status !== 'lunas') {
          foundTunggakan = true;
          const sisa = (k.sisa_tagihan !== undefined && k.sisa_tagihan !== null) ? k.sisa_tagihan : k.jumlah;
          let namaKeg = (k.nama_kegiatan || 'Tagihan Lainnya');
          if (k.status === 'cicilan') {
            namaKeg += ' (Sisa)';
          }
          tList.innerHTML += '<li class="text-sm flex justify-between bg-red-50 p-3 rounded-lg border border-red-100"><span class="font-medium text-red-700">' + namaKeg + '</span> <span class="text-gray-800 font-semibold">' + formatRupiah(sisa) + '</span></li>';
        }
      });
    }
    const hideSpp = isKelasX && isJuli;
    if (!hideSpp && !hasRealSpp && !isPaid) {
      foundTunggakan = true;
      tList.innerHTML += '<li class="text-sm flex justify-between bg-red-50 p-3 rounded-lg border border-red-100"><span class="font-medium text-red-700">SPP Bulanan</span> <span class="text-gray-800 font-semibold">' + formatRupiah(100000) + '</span></li>';
    }
    if (!foundTunggakan) {
      tList.innerHTML = '<li class="text-sm text-gray-500 italic p-3 bg-gray-50 rounded-lg text-center">Tidak ada tunggakan/tagihan di bulan ini.</li>';
    }

    const rList = document.getElementById('mmpRiwayatList');
    rList.innerHTML = '<li class="text-sm text-gray-500 text-center py-4">Memuat data...</li>';

    modal.classList.remove('hidden');

    try {
      const res = await apiJson('backend/data/get_laporan_pembayaran.php');
      rList.innerHTML = '';
      let foundRiwayat = false;
      if (res && res.success && res.data) {
        res.data.forEach(function (r) {
          const d = new Date(r.tgl_transaksi);
          if (d.getMonth() + 1 === monthNum) {
            foundRiwayat = true;
            rList.innerHTML += '<li class="text-sm flex flex-col bg-green-50 p-3 rounded-lg border border-green-100 mb-2"><div class="flex justify-between mb-1"><span class="font-semibold text-green-700">' + formatTanggalId(r.tgl_transaksi) + '</span><span class="font-bold text-gray-800">' + formatRupiah(r.jml_bayar) + '</span></div></li>';
          }
        });
      }

      if (kegData && kegData.success && kegData.data) {
        kegData.data.forEach(function (k) {
          let isKegAkhirTahun = (k.nama_kegiatan || '').toLowerCase() === 'kegiatan akhir tahun';
          if (isKegAkhirTahun && kelasStr.startsWith('XII') && ['januari', 'februari', 'maret', 'april'].includes(fullMonthName.toLowerCase())) {
            if (k.status === 'lunas' || k.status === 'cicilan') {
              let payMonthNum = 0;
              if (k.tgl_bayar) {
                const pd = new Date(k.tgl_bayar);
                payMonthNum = pd.getMonth() + 1;
              }
              if (payMonthNum !== monthNum) {
                let tgl = k.tgl_bayar ? formatTanggalId(k.tgl_bayar) : '-';
                let amount = k.jumlah - (k.sisa_tagihan !== undefined && k.sisa_tagihan !== null ? k.sisa_tagihan : 0);
                if (k.status === 'lunas') amount = k.jumlah;
                
                if (amount > 0) {
                  foundRiwayat = true;
                  rList.innerHTML += '<li class="text-sm flex flex-col bg-green-50 p-3 rounded-lg border border-green-100 mb-2"><div class="flex justify-between mb-1"><span class="font-semibold text-green-700">' + k.nama_kegiatan + ' (' + tgl + ')</span><span class="font-bold text-gray-800">' + formatRupiah(amount) + '</span></div></li>';
                }
              }
            }
          }
        });
      }

      if (!foundRiwayat) {
        rList.innerHTML = '<li class="text-sm text-gray-500 italic p-3 bg-gray-50 rounded-lg text-center">Belum ada riwayat pembayaran di bulan ini.</li>';
      }
    } catch (e) {
      rList.innerHTML = '<li class="text-sm text-red-500 text-center p-3">Gagal mengambil riwayat.</li>';
    }
  };

  window.simpanSiswa = async function () {
    const nama = document.getElementById('namaSiswa')?.value;
    const kelas = document.getElementById('kelasSiswa')?.value;
    const bulan = document.getElementById('bulanTagihan')?.value;
    const jenis = document.getElementById('jenisTagihanManual')?.value || 'spp';
    const jumlahStr = document.getElementById('jumlahTagihan')?.value || '0';
    const jumlah = jumlahStr.replace(/[^0-9]/g, '');

    if (!nama || !kelas || !bulan || !jumlah) {
      alert('Lengkapi semua data tagihan manual.');
      return;
    }

    const fd = new FormData();
    fd.append('nama_siswa', nama);
    fd.append('kelas', kelas);
    fd.append('periode_tagihan', bulan);
    fd.append('jenis_tagihan', jenis);
    fd.append('jumlah_tagihan', jumlah);

    try {
      const r = await fetch(BASE + 'backend/proses/tagihan/simpan_siswa_tagihan.php', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
      });
      const data = await r.json().catch(() => null);
      if (r.ok && data && data.success) {
        alert('Tagihan manual berhasil disimpan.');
        window.location.reload();
      } else {
        alert(data?.error || 'Gagal menyimpan tagihan manual.');
      }
    } catch (e) {
      alert('Terjadi kesalahan jaringan.');
    }
  };

  window.simpanGenerateMassal = async function () {
    const kelas = document.getElementById('generateKelas')?.value;
    const keterangan = document.getElementById('generateKeterangan')?.value;
    const nominalRaw = document.getElementById('generateNominal')?.value;
    const nominal = nominalRaw ? nominalRaw.replace(/\./g, '') : '';

    if (!kelas || !keterangan || !nominal) {
      alert('Lengkapi semua data untuk generate tagihan.');
      return;
    }

    const fd = new FormData();
    fd.append('kelas', kelas);
    fd.append('keterangan', keterangan);
    fd.append('nominal', nominal);

    try {
      const r = await fetch(BASE + 'backend/proses/tagihan/generate_tagihan_massal.php', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
      });
      const data = await r.json().catch(() => null);
      if (r.ok && data && data.success) {
        alert(data.message || 'Tagihan massal berhasil digenerate.');
        window.location.reload();
      } else {
        alert(data?.message || data?.error || 'Gagal menggenerate tagihan massal.');
      }
    } catch (e) {
      alert('Terjadi kesalahan jaringan.');
    }
  };
})();
