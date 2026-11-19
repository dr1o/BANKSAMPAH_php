# Sistem Bank Sampah (PHP OOP CLI)

Implementasi 1-ke-1 Class Diagram Sistem Bank Sampah (15 kelas, versi dengan `Admin` terpisah) menggunakan **PHP 8+ Object-Oriented Programming (PBO)**.
Satu file PHP per kelas di diagram, dikelompokkan ke dalam folder (namespace) sesuai kegunaannya.

Ada dua lapisan di luar diagram, keduanya tidak mengubah aturan bisnis 15 kelas tersebut:

- `BankSampah\Main` dan `demo.php` untuk aplikasi CLI.
- `BankSampah\Service\BankSampahService` dan `router.php` untuk versi web (REST API + penyajian frontend).

## Cara menjalankan

Dibutuhkan PHP 8.1 atau lebih baru (diuji dengan PHP 8.4).

### Versi Web (disarankan untuk demo)

```bash
php -S 127.0.0.1:8081 -t public router.php
```

Atau klik dua kali **`run-web.bat`** di Windows, atau dari PowerShell jalankan
**`.\run-web.bat`** (PowerShell tidak menjalankan berkas di folder aktif tanpa awalan `.\`).
Browser akan terbuka otomatis di `http://127.0.0.1:8081`.

Tampilan versi web ini memakai berkas frontend yang sama dengan versi Java
(`public/` adalah salinan `frontend/` dari repo `BankSampah_KEL03_PBO`), lalu ditimpa
tema datar versi PHP: font sistem, permukaan putih, garis tipis, dan aksen hijau tua.
Versi Java tetap memakai palet coklat dan heading serif, sehingga kedua versi mudah
dibedakan saat didemokan berdampingan.

Sumber tema ada di `tools/theme-php.css`. Bila frontend versi Java diperbarui, salin
ulang dengan:

```bash
php tools/salin-frontend.php
```

Skrip tersebut menyalin berkas Java, menyalin tema PHP, menghapus tautan Google Fonts,
lalu menyuntikkan link tema ke `index.html`. Catatan: skrip ini juga mengembalikan
berkas JavaScript frontend ke versi Java, jadi penyesuaian teks khusus PHP di
`public/src/**/*.js` akan tertimpa.

Data versi web disimpan pada `storage/state.json`, jadi tidak hilang saat server
di-restart. Hapus berkas itu bila ingin kembali ke data awal.

### Versi CLI interaktif

```bash
php src/BankSampah/Main.php
```

Atau klik dua kali **`run-cli.bat`** di Windows.

Untuk menjalankan demo otomatis tanpa input (setara `demo.jsh`):

```bash
php demo.php
```

Atau klik dua kali **`run-demo.bat`** di Windows.

### Akun Contoh (Default)

| Peran | Email | Password |
| --- | --- | --- |
| Nasabah | `budi@mail.com` | `rahasia1` |
| Petugas | `sari@mail.com` | `petugas1` |
| Admin | `dewi@mail.com` | `admin123` |

### Konfigurasi Database (Neon PostgreSQL)

Versi CLI saat ini berjalan langsung di memori (tanpa perlu setup database terlebih dahulu). Jika ingin menghubungkan ke **Neon PostgreSQL**:

1. Salin `.env.example` menjadi `.env` lalu isi `DATABASE_URL` dari Neon Console (jangan commit `.env` ke Git).
2. Jalankan `schema.sql` di Neon SQL Editor untuk membuat 6 tabel beserta data awal.
3. Pastikan ekstensi PHP `pdo_pgsql` aktif di `php.ini`.

## Daftar kelas dan file

| Kelas di diagram | Jenis | File (di dalam `src/BankSampah/`) |
| --- | --- | --- |
| Pengguna | abstract class | `Pengguna/Pengguna.php` |
| Nasabah, Petugas, Admin | extends Pengguna | `Pengguna/Nasabah.php`, `Pengguna/Petugas.php`, `Pengguna/Admin.php` |
| Sampah | abstract class | `Sampah/Sampah.php` |
| Plastik, Kertas, Logam | extends Sampah | `Sampah/Plastik.php`, `Sampah/Kertas.php`, `Sampah/Logam.php` |
| Setoran | class | `Setoran/Setoran.php` |
| DetailSetoran | class | `Setoran/DetailSetoran.php` |
| Dompet | class | `Keuangan/Dompet.php` |
| MetodePenarikan | interface | `Keuangan/MetodePenarikan.php` |
| TransferBank, EWallet | implements MetodePenarikan | `Keuangan/TransferBank.php`, `Keuangan/EWallet.php` |
| Hadiah | class | `Hadiah/Hadiah.php` |

## Struktur versi web

| Berkas | Peran |
| --- | --- |
| `router.php` | Router server bawaan PHP: menyajikan `public/` dan melayani `/api/*` |
| `src/BankSampah/Service/BankSampahService.php` | Koordinator transaksi, menyusun state JSON, menyimpan `storage/state.json` |
| `public/` | Salinan `frontend/` versi Java (HTML, CSS, JS), tidak diubah isinya |
| `public/src/theme-php.css` | Palet phtalo green khusus versi PHP (ditulis otomatis) |
| `storage/state.json` | Penyimpanan state (dibuat otomatis, diabaikan Git) |
| `tools/salin-frontend.php` | Menyalin ulang frontend dari repo Java |

Endpoint mengikuti versi Java: `/api/state`, `/api/login`, `/api/logout`, `/api/register`,
`/api/pengguna/tambah|password|nama`, `/api/setoran/ajukan|timbang|verifikasi|batalkan`,
`/api/dompet/tarik`, `/api/hadiah/tukar|tambah|stok`, `/api/sampah/tambah|harga`,
dan `/api/reload`. Semua respons sukses berbentuk `{ ok, message, user, state }`,
respons gagal berbentuk `{ ok: false, error }`.

Catatan keamanan: sama seperti versi CLI dan versi Java, password disimpan apa adanya
(tanpa hash) karena fokus tugas adalah pemodelan OOP, bukan keamanan produksi.

## Aturan bisnis yang dipilih

| Hal | Aturan |
| --- | --- |
| Poin | 1 poin untuk setiap Rp100 nilai setoran (dibulatkan ke bawah) |
| Plastik | nilai dikurangi 20% bila tidak bersih |
| Kertas | nilai dikurangi 30% bila tidak kering |
| Logam | nilai dikalikan kadar kemurnian (0.0 sampai 1.0) |
| Biaya admin | Transfer Bank tetap Rp2.500, E-Wallet 1% dari jumlah |
| Penarikan | saldo dipotong sejumlah penarikan ditambah biaya admin |
| Password | minimal 6 karakter saat diubah |
| Harga sampah | dikunci saat DetailSetoran dibuat, perubahan harga hanya berlaku untuk setoran berikutnya |

## Alur status Setoran

`DIAJUKAN`, lalu `hitungTotal()` (dipanggil saat penimbangan) menjadi `DITIMBANG`, lalu `verifikasi()` menjadi
`DIVERIFIKASI` lalu `SELESAI`. `verifikasi()` menghasilkan `DITOLAK` bila total nilai 0. `batalkan()` mengubah
status menjadi `DITOLAK` untuk setoran yang belum selesai.
