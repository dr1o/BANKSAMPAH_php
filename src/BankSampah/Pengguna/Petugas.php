<?php

declare(strict_types=1);

namespace BankSampah\Pengguna;

use BankSampah\Setoran\Setoran;

/** Petugas bank sampah yang menimbang sampah dan memverifikasi setoran. */
class Petugas extends Pengguna
{
    // ---- Atribut (sesuai diagram) ----
    private readonly string $idPetugas;
    private readonly string $wilayahTugas;

    // ---- Relasi (diagram): Petugas 1 --> 0..* Setoran (setoran yang berhasil diverifikasi petugas ini) ----
    /** @var Setoran[] */
    private array $daftarSetoran = [];

    public function __construct(
        string $idPengguna,
        string $nama,
        string $email,
        string $password,
        string $noHp,
        string $idPetugas,
        string $wilayahTugas
    ) {
        parent::__construct($idPengguna, $nama, $email, $password, $noHp);
        $this->idPetugas = $idPetugas;
        $this->wilayahTugas = $wilayahTugas;
    }

    // ---- Method (sesuai diagram) ----

    /** Menimbang setoran: menghitung total nilai dan poin, lalu status menjadi DITIMBANG. */
    public function timbangSampah(Setoran $setoran): void
    {
        if ($setoran->getStatus() !== 'DIAJUKAN') {
            echo "Setoran tidak dapat ditimbang. Status sekarang: " . $setoran->getStatus() . "\n";
            return;
        }
        $total = $setoran->hitungTotal();
        if ($setoran->getStatus() === 'DIAJUKAN') {
            echo "Setoran tidak berisi sampah, penimbangan dibatalkan.\n";
            return;
        }
        printf(
            "%s menimbang setoran. Total nilai Rp%s. Status: %s\n",
            $this->getNama(),
            number_format($total, 0, ',', '.'),
            $setoran->getStatus()
        );
    }

    /** Memverifikasi setoran yang sudah ditimbang. Mengembalikan true bila berhasil dan saldo masuk ke dompet. */
    public function verifikasiSetoran(Setoran $setoran): bool
    {
        $berhasil = $setoran->verifikasi($this);
        if ($berhasil) {
            $this->daftarSetoran[] = $setoran;
            echo $this->getNama() . " memverifikasi setoran: " . $setoran . "\n";
        } else {
            echo "Verifikasi tidak berhasil. Status setoran: " . $setoran->getStatus() . "\n";
        }
        return $berhasil;
    }

    public function getIdPetugas(): string
    {
        return $this->idPetugas;
    }

    public function getWilayahTugas(): string
    {
        return $this->wilayahTugas;
    }

    /**
     * Setoran yang pernah diverifikasi petugas ini (hanya baca).
     * @return Setoran[]
     */
    public function getDaftarSetoran(): array
    {
        return $this->daftarSetoran;
    }

    /** Menambahkan setoran terverifikasi saat dimuat dari penyimpanan, tanpa mencetak log baru. */
    public function muatSetoranTerverifikasi(Setoran $setoran): void
    {
        $this->daftarSetoran[] = $setoran;
    }

    public function tampilkanMenu(): void
    {
        echo "=== Menu Petugas: " . $this->getNama() . " (" . $this->wilayahTugas . ") ===\n";
        echo "1. Timbang sampah\n";
        echo "2. Verifikasi setoran\n";
        echo "3. Ubah password\n";
        echo "0. Logout\n";
    }

    public function __toString(): string
    {
        return parent::__toString() . " | " . $this->idPetugas . " | wilayah " . $this->wilayahTugas
            . " | " . count($this->daftarSetoran) . " setoran diverifikasi";
    }
}
