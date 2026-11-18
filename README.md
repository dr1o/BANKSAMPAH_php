# Sistem Bank Sampah (PHP OOP CLI)

Implementasi 1-ke-1 Class Diagram Sistem Bank Sampah (15 kelas, versi dengan `Admin` terpisah) menggunakan **PHP 8+ Object-Oriented Programming (PBO)**.
Satu file PHP per kelas di diagram, dikelompokkan ke dalam folder (namespace) sesuai kegunaannya.
Satu-satunya kelas di luar diagram adalah `BankSampah\Main`, yaitu aplikasi CLI untuk menjalankan sistem.

## Cara menjalankan

Dibutuhkan PHP 8.1 atau lebih baru (diuji dengan PHP 8.4).

Menjalankan aplikasi CLI interaktif:

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
