import {
  state,
  apiCall,
  showToast,
  quickLogin,
  syncUrlWithState,
  triggerRender
} from '../state.js';

export function renderLoginPage() {
  return `
    <div class="auth-wrapper">
      <section class="auth-showcase">
        <div>
          <p class="auth-kicker">Kelompok 03 &middot; PBO</p>
          <h1 class="auth-hero-title">Bank Sampah</h1>
          <p class="auth-hero-desc">
            Pencatatan setoran sampah, saldo dompet, dan penukaran poin.
            Pilih akun demo di bawah untuk mencoba tiap peran.
          </p>

          <div class="demo-accounts-box">
            <h3>Akun demo</h3>
            <div class="demo-role-list">
              <button class="demo-role-row" data-quick-role="NASABAH">
                <span class="demo-role-role">Nasabah</span>
                <span class="demo-role-name">Budi</span>
                <span class="demo-role-cred">budi@mail.com / rahasia1</span>
              </button>
              <button class="demo-role-row" data-quick-role="PETUGAS">
                <span class="demo-role-role">Petugas</span>
                <span class="demo-role-name">Sari</span>
                <span class="demo-role-cred">sari@mail.com / petugas1</span>
              </button>
              <button class="demo-role-row" data-quick-role="ADMIN">
                <span class="demo-role-role">Admin</span>
                <span class="demo-role-name">Dewi</span>
                <span class="demo-role-cred">dewi@mail.com / admin123</span>
              </button>
            </div>
          </div>
        </div>
      </section>

      <section class="auth-form-side">
        <div class="auth-card">
          <div class="auth-tabs">
            <button class="auth-tab-btn ${state.authTab === 'login' ? 'active' : ''}" data-auth-tab="login">
              Masuk
            </button>
            <button class="auth-tab-btn ${state.authTab === 'register' ? 'active' : ''}" data-auth-tab="register">
              Daftar
            </button>
          </div>

          ${
            state.authTab === 'login'
              ? `
            <form id="form-login">
              <div style="margin-bottom:1.25rem;">
                <h2 class="card-title">Masuk ke akun</h2>
                <p class="card-subtitle">Gunakan email dan password akun yang terdaftar.</p>
              </div>
              <div class="form-group">
                <label class="form-label">Alamat Email</label>
                <input type="email" class="form-input" id="login-email" placeholder="contoh: budi@mail.com" value="budi@mail.com" required />
              </div>
              <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" class="form-input" id="login-password" placeholder="Masukkan password" value="rahasia1" required />
              </div>
              <button type="submit" class="btn btn-primary btn-block" style="margin-top:0.5rem;padding:0.65rem;">
                Masuk
              </button>
            </form>
          `
              : `
            <form id="form-register">
              <div style="margin-bottom:1.1rem;">
                <h2 class="card-title">Daftar nasabah</h2>
              </div>
              <div class="form-group">
                <label class="form-label">Nama Lengkap</label>
                <input type="text" class="form-input" id="reg-nama" placeholder="Nama lengkap warga" required />
              </div>
              <div class="form-row-2">
                <div class="form-group">
                  <label class="form-label">Email</label>
                  <input type="email" class="form-input" id="reg-email" placeholder="nama@mail.com" required />
                </div>
                <div class="form-group">
                  <label class="form-label">Nomor HP</label>
                  <input type="text" class="form-input" id="reg-hp" placeholder="0812xxxx" required />
                </div>
              </div>
              <div class="form-group">
                <label class="form-label">Password (minimal 6 karakter)</label>
                <input type="password" class="form-input" id="reg-password" placeholder="Minimal 6 karakter" minlength="6" required />
              </div>
              <div class="form-group">
                <label class="form-label">Alamat Tempat Tinggal</label>
                <input type="text" class="form-input" id="reg-alamat" placeholder="Jl. Melati No. 10" required />
              </div>
              <button type="submit" class="btn btn-primary btn-block" style="margin-top:0.5rem;padding:0.65rem;">
                Buat akun
              </button>
            </form>
          `
          }
        </div>
      </section>
    </div>
  `;
}

export function bindLoginEvents() {
  document.querySelectorAll('[data-auth-tab]').forEach((btn) => {
    btn.addEventListener('click', () => {
      state.authTab = btn.getAttribute('data-auth-tab');
      syncUrlWithState(false);
      triggerRender();
    });
  });

  document.getElementById('form-login')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const email = document.getElementById('login-email').value;
    const password = document.getElementById('login-password').value;
    await quickLogin(email, password);
  });

  document.getElementById('form-register')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    try {
      const res = await apiCall('/api/register', 'POST', {
        nama: document.getElementById('reg-nama').value,
        email: document.getElementById('reg-email').value,
        password: document.getElementById('reg-password').value,
        noHp: document.getElementById('reg-hp').value,
        alamat: document.getElementById('reg-alamat').value
      });
      state.nasabahTab = 'setor';
      showToast(res.message);
      syncUrlWithState(false);
      triggerRender();
    } catch (err) {
      showToast(err.message, true);
    }
  });
}
