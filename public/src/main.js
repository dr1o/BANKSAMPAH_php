import {
  state,
  setRenderCallback,
  formatRp,
  escapeHtml,
  apiCall,
  showToast,
  showModal,
  quickLogin,
  applyRouteFromLocation,
  syncUrlWithState
} from './state.js';
import { renderLoginPage, bindLoginEvents } from './pages/loginPage.js';
import { renderNasabahPage, bindNasabahEvents } from './pages/nasabahPage.js';
import { renderPetugasPage, bindPetugasEvents } from './pages/petugasPage.js';
import { renderAdminPage, bindAdminEvents } from './pages/adminPage.js';

function renderTopNavbar() {
  const u = state.currentUser;

  return `
    <header class="top-navbar">
      <div class="brand-group">
        <div class="brand-logo">BS</div>
        <div class="brand-title">Bank Sampah &middot; Kelompok 03</div>
      </div>

      <div class="nav-actions">
        ${
          u
            ? `
          <div class="quick-switcher" title="Pindah cepat antar peran untuk demonstrasi">
            <button class="quick-role-btn ${u.role === 'NASABAH' ? 'active' : ''}" data-quick-role="NASABAH">Nasabah</button>
            <button class="quick-role-btn ${u.role === 'PETUGAS' ? 'active' : ''}" data-quick-role="PETUGAS">Petugas</button>
            <button class="quick-role-btn ${u.role === 'ADMIN' ? 'active' : ''}" data-quick-role="ADMIN">Admin</button>
          </div>

          <div class="user-chip">
            <span class="user-chip-name">${escapeHtml(u.nama)}</span>
            <span class="role-badge">${escapeHtml(u.role)}</span>
          </div>
          <button class="btn btn-danger btn-sm" id="btn-logout">Keluar</button>
        `
            : ''
        }
      </div>
    </header>
  `;
}

function render() {
  const app = document.getElementById('app');
  if (!app) return;

  if (!state.appData) {
    app.innerHTML = `
      <div class="loading-screen">
        <div class="brand-logo">BS</div>
        <p>Memuat data&hellip;</p>
      </div>
    `;
    return;
  }

  const topBarHtml = renderTopNavbar();
  let mainHtml = '';

  if (!state.currentUser) {
    mainHtml = renderLoginPage();
  } else if (state.currentUser.role === 'NASABAH') {
    mainHtml = renderNasabahPage();
  } else if (state.currentUser.role === 'PETUGAS') {
    mainHtml = renderPetugasPage();
  } else if (state.currentUser.role === 'ADMIN') {
    mainHtml = renderAdminPage();
  }

  app.innerHTML = topBarHtml + mainHtml;
  bindSharedEvents();

  if (!state.currentUser) {
    bindLoginEvents();
  } else if (state.currentUser.role === 'NASABAH') {
    bindNasabahEvents();
  } else if (state.currentUser.role === 'PETUGAS') {
    bindPetugasEvents();
  } else if (state.currentUser.role === 'ADMIN') {
    bindAdminEvents();
  }
}

function bindSharedEvents() {
  document.querySelectorAll('[data-quick-role]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const role = btn.getAttribute('data-quick-role');
      if (role === 'NASABAH') quickLogin('budi@mail.com', 'rahasia1');
      if (role === 'PETUGAS') quickLogin('sari@mail.com', 'petugas1');
      if (role === 'ADMIN') quickLogin('dewi@mail.com', 'admin123');
    });
  });

  document.getElementById('btn-logout')?.addEventListener('click', async () => {
    try {
      await apiCall('/api/logout', 'POST', { idPengguna: state.currentUser?.idPengguna });
      state.currentUser = null;
      state.cart = [];
      state.authTab = 'login';
      localStorage.removeItem('bs_current_user_id');
      syncUrlWithState(false);
      showToast('Berhasil logout.');
      render();
    } catch (err) {
      showToast(err.message, true);
    }
  });

  document.querySelectorAll('[data-cancel-setoran]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const idSetoran = btn.getAttribute('data-cancel-setoran');
      try {
        const res = await apiCall('/api/setoran/batalkan', 'POST', {
          idSetoran,
          idPengguna: state.currentUser.idPengguna
        });
        showToast(res.message);
        render();
      } catch (err) {
        showToast(err.message, true);
      }
    });
  });

  document.querySelectorAll('[data-detail-setoran]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const idSetoran = btn.getAttribute('data-detail-setoran');
      const s = (state.appData.daftarSetoran || []).find((x) => x.idSetoran === idSetoran);
      if (!s) return;
      const html = `
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;background:var(--surface-cream);padding:1rem;border-radius:3px;margin-bottom:1rem;font-size:0.85rem;">
          <div><strong>ID Setoran:</strong> <span class="mono">${escapeHtml(s.idSetoran)}</span></div>
          <div><strong>Status:</strong> <span class="badge badge-${escapeHtml(s.status)}">${escapeHtml(s.status)}</span></div>
          <div><strong>Nasabah:</strong> ${escapeHtml(s.namaNasabah)} (${escapeHtml(s.idNasabah)})</div>
          <div><strong>Tanggal Setor:</strong> ${escapeHtml(s.tanggalSetor)}</div>
          <div><strong>Petugas Verifikator:</strong> ${escapeHtml(s.namaPetugas)}</div>
        </div>
        <div class="table-wrap" style="margin-bottom:1rem;">
          <table>
            <thead>
              <tr>
                <th>ID Sampah</th>
                <th>Nama Sampah</th>
                <th>Kategori</th>
                <th>Berat</th>
                <th>Subtotal Terkunci</th>
              </tr>
            </thead>
            <tbody>
              ${s.daftarDetail
                .map(
                  (d) => `
                <tr>
                  <td class="mono">${escapeHtml(d.idSampah)}</td>
                  <td><strong>${escapeHtml(d.namaSampah)}</strong></td>
                  <td><span class="badge badge-brown">${escapeHtml(d.subkelas)}</span></td>
                  <td class="mono">${d.berat} kg</td>
                  <td class="mono"><strong>${formatRp(d.subtotal)}</strong></td>
                </tr>
              `
                )
                .join('')}
            </tbody>
          </table>
        </div>
        <div style="display:flex;justify-content:space-between;align-items:center;font-size:1rem;">
          <span>Total Nilai Setoran: <strong class="mono">${formatRp(s.status === 'DIAJUKAN' ? s.estimasiNilai : s.totalNilai)}</strong></span>
          <span>Total Poin: <strong class="mono">${s.status === 'DIAJUKAN' ? s.estimasiPoin : s.totalPoin} poin</strong></span>
        </div>
      `;
      showModal(`Rincian Transaksi Setoran ${s.idSetoran}`, html);
    });
  });
}

async function initApp() {
  setRenderCallback(render);
  window.addEventListener('popstate', () => {
    applyRouteFromLocation();
  });

  try {
    await apiCall('/api/state', 'GET');
  } catch (err) {
    showToast('Gagal memuat data dari server: ' + err.message, true);
  }
  applyRouteFromLocation();
}

initApp();
