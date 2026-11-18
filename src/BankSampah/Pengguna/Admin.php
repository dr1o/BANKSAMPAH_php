<?php

declare(strict_types=1);

namespace BankSampah\Pengguna;

use BankSampah\Hadiah\Hadiah;
use BankSampah\Sampah\Sampah;
use BankSampah\Setoran\Setoran;
use InvalidArgumentException;

/**
 * Pengelola sistem. Mengelola jenis sampah dan hadiah, melihat pengguna, dan membuat laporan.
 * Data yang dikelola diberikan lewat constructor sebagai daftar bersama (shared list via reference),
 * sehingga perubahan yang dilakukan Admin langsung terlihat oleh bagian sistem lain.
 */
class Admin extends Pengguna
{
    // ---- Atribut (sesuai diagram) ----
    private readonly string $levelAkses;

    // ---- Relasi (diagram): Admin ..> Sampah dan Admin ..> Hadiah ("mengelola") ----
    /** @var Sampah[] */
    private array $daftarSampah;
    /** @var Hadiah[] */
    private array $daftarHadiah;

    // ---- Data untuk kelolaPengguna() dan buatLaporan() ----
    /** @var Pengguna[] */
    private array $daftarPengguna;
    /** @var Setoran[] */
    private array $daftarSetoran;

    /**
     * @param Sampah[]   $daftarSampah
     * @param Hadiah[]   $daftarHadiah
     * @param Pengguna[] $daftarPengguna
     * @param Setoran[]  $daftarSetoran
     */
    public function __construct(
        string $idPengguna,
        string $nama,
        string $email,
        string $password,
        string $noHp,
        string $levelAkses,
        array &$daftarSampah,
        array &$daftarHadiah,
        array &$daftarPengguna,
        array &$daftarSetoran
    ) {
        parent::__construct($idPengguna, $nama, $email, $password, $noHp);
        $this->levelAkses = $levelAkses;
        $this->daftarSampah = &$daftarSampah;
        $this->daftarHadiah = &$daftarHadiah;
        $this->daftarPengguna = &$daftarPengguna;
        $this->daftarSetoran = &$daftarSetoran;
    }

    // ---- Method (sesuai diagram) ----

    /** Menampilkan jenis sampah dan mengubah harga per kg lewat input konsol. */
    public function kelolaJenisSampah(): void
    {
        while (true) {
            echo "\n=== Kelola Jenis Sampah ===\n";
            if (empty($this->daftarSampah)) {
                echo "Belum ada jenis sampah.\n";
                return;
            }
            foreach ($this->daftarSampah as $i => $sampah) {
                echo ($i + 1) . ". " . $sampah . "\n";
            }
            echo "Nomor sampah yang diubah harganya (0 = kembali): ";
            $line = fgets(STDIN);
            if ($line === false) {
                return;
            }
            $trimmed = trim($line);
            if (!preg_match('/^-?\d+$/', $trimmed)) {
                echo "Masukan harus berupa angka.\n";
                continue;
            }
            $nomor = (int) $trimmed;
            if ($nomor === 0) {
                return;
            }
            if ($nomor < 1 || $nomor > count($this->daftarSampah)) {
                echo "Nomor tidak valid.\n";
                continue;
            }
            echo "Harga baru per kg (Rp): ";
            $hargaLine = fgets(STDIN);
            if ($hargaLine === false) {
                return;
            }
            $hargaTrimmed = trim($hargaLine);
            if (!is_numeric($hargaTrimmed)) {
                echo "Harga harus berupa angka.\n";
                continue;
            }
            try {
                $harga = (float) $hargaTrimmed;
                $this->daftarSampah[$nomor - 1]->setHargaPerKg($harga);
                echo "Harga berhasil diperbarui.\n";
            } catch (InvalidArgumentException $e) {
                echo $e->getMessage() . "\n";
            }
        }
    }

    /** Menampilkan katalog hadiah dan menambah hadiah baru lewat input konsol. */
    public function kelolaHadiah(): void
    {
        echo "\n=== Kelola Hadiah ===\n";
        if (empty($this->daftarHadiah)) {
            echo "Belum ada hadiah.\n";
        }
        foreach ($this->daftarHadiah as $i => $hadiah) {
            echo ($i + 1) . ". " . $hadiah . "\n";
        }
        echo "Tambah hadiah baru? (y/n): ";
        $jawab = fgets(STDIN);
        if ($jawab === false || strtolower(trim($jawab)) !== 'y') {
            return;
        }
        echo "ID hadiah: ";
        $idLine = fgets(STDIN);
        if ($idLine === false) {
            echo "Input berhenti. Hadiah tidak ditambahkan.\n";
            return;
        }
        $id = trim($idLine);

        echo "Nama hadiah: ";
        $namaLine = fgets(STDIN);
        if ($namaLine === false) {
            echo "Input berhenti. Hadiah tidak ditambahkan.\n";
            return;
        }
        $nama = trim($namaLine);

        echo "Poin dibutuhkan: ";
        $poinLine = fgets(STDIN);
        if ($poinLine === false) {
            echo "Input berhenti. Hadiah tidak ditambahkan.\n";
            return;
        }
        $poinTrimmed = trim($poinLine);

        echo "Stok: ";
        $stokLine = fgets(STDIN);
        if ($stokLine === false) {
            echo "Input berhenti. Hadiah tidak ditambahkan.\n";
            return;
        }
        $stokTrimmed = trim($stokLine);

        if (!preg_match('/^-?\d+$/', $poinTrimmed) || !preg_match('/^-?\d+$/', $stokTrimmed)) {
            echo "Poin dan stok harus berupa angka. Hadiah tidak ditambahkan.\n";
            return;
        }

        try {
            $poin = (int) $poinTrimmed;
            $stok = (int) $stokTrimmed;
            $this->daftarHadiah[] = new Hadiah($id, $nama, $poin, $stok);
            echo "Hadiah berhasil ditambahkan.\n";
        } catch (InvalidArgumentException $e) {
            echo $e->getMessage() . " Hadiah tidak ditambahkan.\n";
        }
    }

    /** Menampilkan seluruh pengguna terdaftar. */
    public function kelolaPengguna(): void
    {
        echo "\n=== Kelola Pengguna ===\n";
        if (empty($this->daftarPengguna)) {
            echo "Belum ada pengguna.\n";
            return;
        }
        foreach ($this->daftarPengguna as $i => $pengguna) {
            echo ($i + 1) . ". " . $pengguna . "\n";
        }
    }

    /** Menampilkan ringkasan kegiatan bank sampah. */
    public function buatLaporan(): void
    {
        $jumlahNasabah = 0;
        $jumlahPetugas = 0;
        $jumlahAdmin = 0;
        foreach ($this->daftarPengguna as $p) {
            if ($p instanceof Nasabah) {
                $jumlahNasabah++;
            } elseif ($p instanceof Petugas) {
                $jumlahPetugas++;
            } elseif ($p instanceof Admin) {
                $jumlahAdmin++;
            }
        }
        echo "\n=== Laporan Bank Sampah ===\n";
        echo "Pengguna        : " . count($this->daftarPengguna) . " (nasabah " . $jumlahNasabah
            . ", petugas " . $jumlahPetugas . ", admin " . $jumlahAdmin . ")\n";
        echo "Jenis sampah    : " . count($this->daftarSampah) . "\n";
        echo "Hadiah          : " . count($this->daftarHadiah) . "\n";
        echo "Jumlah setoran  : " . count($this->daftarSetoran) . "\n";
        $semuaStatus = ['DIAJUKAN', 'DITIMBANG', 'DIVERIFIKASI', 'SELESAI', 'DITOLAK'];
        foreach ($semuaStatus as $status) {
            $jumlah = 0;
            foreach ($this->daftarSetoran as $s) {
                if ($s->getStatus() === $status) {
                    $jumlah++;
                }
            }
            echo "  - " . $status . ": " . $jumlah . "\n";
        }
        foreach ($this->daftarSetoran as $s) {
            echo "  * " . $s . "\n";
        }
    }

    public function tampilkanMenu(): void
    {
        echo "=== Menu Admin: " . $this->getNama() . " (akses " . $this->levelAkses . ") ===\n";
        echo "1. Kelola jenis sampah\n";
        echo "2. Kelola hadiah\n";
        echo "3. Kelola pengguna\n";
        echo "4. Buat laporan\n";
        echo "5. Ubah password\n";
        echo "0. Logout\n";
    }
}
