<?php

declare(strict_types=1);

namespace BankSampah;

use BankSampah\Hadiah\Hadiah;
use BankSampah\Keuangan\EWallet;
use BankSampah\Keuangan\MetodePenarikan;
use BankSampah\Keuangan\TransferBank;
use BankSampah\Pengguna\Admin;
use BankSampah\Pengguna\Nasabah;
use BankSampah\Pengguna\Pengguna;
use BankSampah\Pengguna\Petugas;
use BankSampah\Sampah\Kertas;
use BankSampah\Sampah\Logam;
use BankSampah\Sampah\Plastik;
use BankSampah\Sampah\Sampah;
use BankSampah\Setoran\DetailSetoran;
use BankSampah\Setoran\Setoran;

require_once __DIR__ . '/../autoload.php';

/**
 * Titik masuk aplikasi CLI Bank Sampah (bukan bagian dari class diagram).
 * Hanya mengurus input dan output; aturan bisnis tetap berada di kelas-kelas diagram.
 * Data disimpan di memori, sehingga kembali ke data awal setiap program dijalankan.
 */
class Main
{
    /** @var Pengguna[] */
    private static array $daftarPengguna = [];
    /** @var Sampah[] */
    private static array $daftarSampah = [];
    /** @var Hadiah[] */
    private static array $daftarHadiah = [];
    /** @var Setoran[] */
    private static array $daftarSetoran = [];
    /** @var array<string, bool> Pengguna tidak punya getEmail(), jadi keunikan email dicatat di sini. */
    private static array $daftarEmail = [];

    public static function main(array $args = []): void
    {
        self::siapkanDataAwal();
        echo "=======================================\n";
        echo "        SISTEM BANK SAMPAH\n";
        echo "=======================================\n";
        echo "Akun contoh (email / password):\n";
        echo "  Nasabah : budi@mail.com / rahasia1\n";
        echo "  Petugas : sari@mail.com / petugas1\n";
        echo "  Admin   : dewi@mail.com / admin123\n";

        while (true) {
            echo "\n";
            echo "=== Menu Utama ===\n";
            echo "1. Login\n";
            echo "2. Daftar sebagai nasabah\n";
            echo "0. Keluar\n";
            switch (self::baca("Pilih menu: ")) {
                case "1":
                    self::login();
                    break;
                case "2":
                    self::daftarNasabah();
                    break;
                case "0":
                    echo "Terima kasih telah menggunakan Sistem Bank Sampah.\n";
                    return;
                default:
                    echo "Pilihan tidak tersedia.\n";
            }
        }
    }

    private static function siapkanDataAwal(): void
    {
        self::$daftarSampah[] = new Plastik("SMP-01", "Botol PET", 3000, "PET", true);
        self::$daftarSampah[] = new Kertas("SMP-02", "Kardus", 1500, "Kardus", true);
        self::$daftarSampah[] = new Logam("SMP-03", "Aluminium", 8000, "Aluminium", 0.9);

        self::$daftarHadiah[] = new Hadiah("H-01", "Payung", 100, 2);
        self::$daftarHadiah[] = new Hadiah("H-02", "Tas Belanja", 50, 5);

        self::tambahPengguna(
            new Nasabah("N-001", "Budi", "budi@mail.com", "rahasia1", "0812", "Jl. Mawar 1"),
            "budi@mail.com"
        );
        self::tambahPengguna(
            new Petugas("P-001", "Sari", "sari@mail.com", "petugas1", "0813", "PTG-01", "Wilayah A"),
            "sari@mail.com"
        );
        self::tambahPengguna(
            new Admin(
                "A-001",
                "Dewi",
                "dewi@mail.com",
                "admin123",
                "0814",
                "PENUH",
                self::$daftarSampah,
                self::$daftarHadiah,
                self::$daftarPengguna,
                self::$daftarSetoran
            ),
            "dewi@mail.com"
        );
    }

    private static function tambahPengguna(Pengguna $pengguna, string $email): void
    {
        self::$daftarPengguna[] = $pengguna;
        self::$daftarEmail[$email] = true;
    }

    // ---- Login dan pendaftaran ----

    private static function login(): void
    {
        $email = self::baca("Email: ");
        $password = self::baca("Password: ");
        foreach (self::$daftarPengguna as $pengguna) {
            if ($pengguna->login($email, $password)) {
                echo "Login berhasil. Selamat datang, " . $pengguna->getNama() . ".\n";
                self::jalankanSesi($pengguna);
                return;
            }
        }
        echo "Email atau password salah.\n";
    }

    private static function daftarNasabah(): void
    {
        echo "\n=== Pendaftaran Nasabah ===\n";
        $nama = self::bacaTeks("Nama: ");
        $email = self::bacaTeks("Email: ");
        if (!str_contains($email, "@")) {
            echo "Pendaftaran gagal: format email tidak valid.\n";
            return;
        }
        if (isset(self::$daftarEmail[$email])) {
            echo "Pendaftaran gagal: email sudah terdaftar.\n";
            return;
        }
        $password = self::baca("Password (minimal 6 karakter): ");
        if (strlen($password) < 6) {
            echo "Pendaftaran gagal: password minimal 6 karakter.\n";
            return;
        }
        $noHp = self::bacaTeks("No. HP: ");
        $alamat = self::bacaTeks("Alamat: ");
        $id = sprintf("N-%03d", count(self::$daftarPengguna) + 1);
        self::tambahPengguna(new Nasabah($id, $nama, $email, $password, $noHp, $alamat), $email);
        echo "Pendaftaran berhasil dengan ID " . $id . ". Silakan login.\n";
    }

    /** Mengulang menu pengguna sampai logout. Menu dicetak oleh tampilkanMenu() (polymorphism). */
    private static function jalankanSesi(Pengguna $pengguna): void
    {
        $aktif = true;
        while ($aktif) {
            echo "\n";
            $pengguna->tampilkanMenu();
            $pilihan = self::baca("Pilih menu: ");
            if ($pengguna instanceof Nasabah) {
                $aktif = self::menuNasabah($pengguna, $pilihan);
            } elseif ($pengguna instanceof Petugas) {
                $aktif = self::menuPetugas($pengguna, $pilihan);
            } elseif ($pengguna instanceof Admin) {
                $aktif = self::menuAdmin($pengguna, $pilihan);
            }
        }
        $pengguna->logout();
    }

    private static function ubahPassword(Pengguna $pengguna): void
    {
        $lama = self::baca("Password lama: ");
        $baru = self::baca("Password baru (minimal 6 karakter): ");
        if ($pengguna->ubahPassword($lama, $baru)) {
            echo "Password berhasil diubah.\n";
        } else {
            echo "Gagal: password lama salah atau password baru kurang dari 6 karakter.\n";
        }
    }

    // ---- Menu Nasabah ----

    /** Mengembalikan false bila pengguna memilih logout. */
    private static function menuNasabah(Nasabah $nasabah, string $pilihan): bool
    {
        switch ($pilihan) {
            case "1":
                self::setorSampah($nasabah);
                break;
            case "2":
                self::lihatRiwayat($nasabah);
                break;
            case "3":
                echo $nasabah->getDompet() . "\n";
                break;
            case "4":
                self::tukarPoin($nasabah);
                break;
            case "5":
                self::tarikSaldo($nasabah);
                break;
            case "6":
                self::ubahPassword($nasabah);
                break;
            case "0":
                return false;
            default:
                echo "Pilihan tidak tersedia.\n";
        }
        return true;
    }

    private static function setorSampah(Nasabah $nasabah): void
    {
        $id = sprintf("S-%03d", count(self::$daftarSetoran) + 1);
        $setoran = new Setoran($id, $nasabah->getDompet());
        $jumlahItem = 0;
        echo "\n=== Setor Sampah ===\n";
        while (true) {
            self::tampilkanDaftar(self::$daftarSampah);
            $nomor = self::bacaNomor("Nomor sampah (0 = selesai): ", count(self::$daftarSampah));
            if ($nomor === 0) {
                break;
            }
            $berat = self::bacaDesimal("Berat dalam kg, misalnya 2,5 (0 = batal): ");
            if ($berat == 0.0) {
                continue;
            }
            $detail = new DetailSetoran(self::$daftarSampah[$nomor - 1], $berat);
            $setoran->tambahDetail($detail);
            $jumlahItem++;
            echo "Ditambahkan: " . $detail . "\n";
        }
        if ($jumlahItem === 0) {
            echo "Setoran dibatalkan karena tidak ada sampah yang dipilih.\n";
            return;
        }
        $nasabah->setorSampah($setoran);
        self::$daftarSetoran[] = $setoran;
        echo "Setoran menunggu ditimbang oleh petugas.\n";
    }

    private static function lihatRiwayat(Nasabah $nasabah): void
    {
        $riwayat = $nasabah->lihatRiwayat();
        echo "\n=== Riwayat Setoran ===\n";
        if (empty($riwayat)) {
            echo "Belum ada setoran.\n";
            return;
        }
        self::tampilkanDaftar($riwayat);
        $nomor = self::bacaNomor("Nomor setoran yang dibatalkan (0 = kembali): ", count($riwayat));
        if ($nomor === 0) {
            return;
        }
        $setoran = $riwayat[$nomor - 1];
        $status = $setoran->getStatus();
        if ($status === "DIAJUKAN" || $status === "DITIMBANG") {
            $setoran->batalkan();
            echo "Setoran dibatalkan. Status: " . $setoran->getStatus() . "\n";
        } else {
            echo "Setoran berstatus " . $status . " tidak dapat dibatalkan.\n";
        }
    }

    private static function tukarPoin(Nasabah $nasabah): void
    {
        echo "\n=== Tukar Poin ===\n";
        if (empty(self::$daftarHadiah)) {
            echo "Belum ada hadiah.\n";
            return;
        }
        echo "Poin kamu: " . $nasabah->getDompet()->getTotalPoin() . "\n";
        self::tampilkanDaftar(self::$daftarHadiah);
        $nomor = self::bacaNomor("Nomor hadiah (0 = kembali): ", count(self::$daftarHadiah));
        if ($nomor !== 0) {
            $nasabah->tukarPoin(self::$daftarHadiah[$nomor - 1]);
        }
    }

    private static function tarikSaldo(Nasabah $nasabah): void
    {
        echo "\n=== Tarik Saldo ===\n";
        echo $nasabah->getDompet() . "\n";
        echo "1. Transfer Bank (biaya admin Rp2.500)\n";
        echo "2. E-Wallet (biaya admin 1%)\n";
        $nomor = self::bacaNomor("Metode penarikan (0 = kembali): ", 2);
        if ($nomor === 0) {
            return;
        }
        /** @var MetodePenarikan $metode */
        if ($nomor === 1) {
            $metode = new TransferBank(
                self::bacaTeks("Nama bank: "),
                self::bacaTeks("No. rekening: "),
                self::bacaTeks("Nama pemilik: ")
            );
        } else {
            $metode = new EWallet(
                self::bacaTeks("Penyedia e-wallet: "),
                self::bacaTeks("No. HP terdaftar: ")
            );
        }
        $jumlah = self::bacaDesimal("Jumlah penarikan dalam rupiah, tanpa titik, misalnya 10000 (0 = batal): ");
        if ($jumlah > 0) {
            $nasabah->tarikSaldo($jumlah, $metode);
        }
    }

    // ---- Menu Petugas ----

    /** Mengembalikan false bila pengguna memilih logout. */
    private static function menuPetugas(Petugas $petugas, string $pilihan): bool
    {
        switch ($pilihan) {
            case "1":
                $untukDitimbang = self::pilihSetoran("DIAJUKAN", "ditimbang");
                if ($untukDitimbang !== null) {
                    $petugas->timbangSampah($untukDitimbang);
                }
                break;
            case "2":
                $untukDiverifikasi = self::pilihSetoran("DITIMBANG", "diverifikasi");
                if ($untukDiverifikasi !== null) {
                    $petugas->verifikasiSetoran($untukDiverifikasi);
                }
                break;
            case "3":
                self::ubahPassword($petugas);
                break;
            case "0":
                return false;
            default:
                echo "Pilihan tidak tersedia.\n";
        }
        return true;
    }

    /** Menampilkan setoran berstatus tertentu dan mengembalikan pilihan, atau null bila tidak ada atau kembali. */
    private static function pilihSetoran(string $status, string $tindakan): ?Setoran
    {
        /** @var Setoran[] $pilihan */
        $pilihan = [];
        foreach (self::$daftarSetoran as $setoran) {
            if ($setoran->getStatus() === $status) {
                $pilihan[] = $setoran;
            }
        }
        if (empty($pilihan)) {
            echo "Tidak ada setoran yang perlu " . $tindakan . ".\n";
            return null;
        }
        foreach ($pilihan as $i => $s) {
            echo ($i + 1) . ". " . $s . " | nasabah " . self::namaPenyetor($s) . "\n";
        }
        $nomor = self::bacaNomor("Nomor setoran yang " . $tindakan . " (0 = kembali): ", count($pilihan));
        return $nomor === 0 ? null : $pilihan[$nomor - 1];
    }

    private static function namaPenyetor(Setoran $setoran): string
    {
        foreach (self::$daftarPengguna as $pengguna) {
            if ($pengguna instanceof Nasabah && in_array($setoran, $pengguna->lihatRiwayat(), true)) {
                return $pengguna->getNama();
            }
        }
        return "-";
    }

    // ---- Menu Admin ----

    /** Mengembalikan false bila pengguna memilih logout. */
    private static function menuAdmin(Admin $admin, string $pilihan): bool
    {
        switch ($pilihan) {
            case "1":
                $admin->kelolaJenisSampah();
                break;
            case "2":
                $admin->kelolaHadiah();
                break;
            case "3":
                $admin->kelolaPengguna();
                break;
            case "4":
                $admin->buatLaporan();
                break;
            case "5":
                self::ubahPassword($admin);
                break;
            case "0":
                return false;
            default:
                echo "Pilihan tidak tersedia.\n";
        }
        return true;
    }

    // ---- Pembantu input dan output ----

    /** @param array<int, mixed> $daftar */
    private static function tampilkanDaftar(array $daftar): void
    {
        foreach ($daftar as $i => $item) {
            echo ($i + 1) . ". " . $item . "\n";
        }
    }

    /** Membaca satu baris. Program berakhir dengan rapi bila input ditutup (Ctrl+Z / Ctrl+D). */
    private static function baca(string $prompt): string
    {
        echo $prompt;
        $line = fgets(STDIN);
        if ($line === false) {
            echo "\nInput berakhir. Program ditutup.\n";
            exit(0);
        }
        return trim($line);
    }

    private static function bacaTeks(string $prompt): string
    {
        while (true) {
            $teks = self::baca($prompt);
            if ($teks !== '') {
                return $teks;
            }
            echo "Masukan tidak boleh kosong.\n";
        }
    }

    /** Membaca angka dari 0 sampai maksimum. */
    private static function bacaNomor(string $prompt, int $maksimum): int
    {
        while (true) {
            $raw = self::baca($prompt);
            if (preg_match('/^-?\d+$/', $raw)) {
                $nomor = (int) $raw;
                if ($nomor >= 0 && $nomor <= $maksimum) {
                    return $nomor;
                }
            }
            echo "Masukkan angka dari 0 sampai " . $maksimum . ".\n";
        }
    }

    /** Membaca angka desimal yang tidak negatif. Koma diterima sebagai pemisah desimal. */
    private static function bacaDesimal(string $prompt): float
    {
        while (true) {
            $raw = str_replace(',', '.', self::baca($prompt));
            if (is_numeric($raw)) {
                $nilai = (float) $raw;
                if ($nilai >= 0 && !is_infinite($nilai) && !is_nan($nilai)) {
                    return $nilai;
                }
            }
            echo "Masukkan angka yang valid dan tidak negatif.\n";
        }
    }
}

if (PHP_SAPI === 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    Main::main($argv ?? []);
}
