<?php

declare(strict_types=1);

namespace BankSampah\Pengguna;

use BankSampah\Hadiah\Hadiah;
use BankSampah\Keuangan\Dompet;
use BankSampah\Keuangan\MetodePenarikan;
use BankSampah\Setoran\Setoran;
use DateTimeImmutable;

/** Warga penyetor sampah. Mewarisi data akun dari Pengguna. */
class Nasabah extends Pengguna
{
    // ---- Atribut (sesuai diagram) ----
    private readonly string $alamat;
    private readonly DateTimeImmutable $tanggalDaftar;

    // ---- Relasi (diagram): Nasabah 1 *-- 1 Dompet (composition: dompet dibuat bersama nasabah) ----
    private readonly Dompet $dompet;

    // ---- Relasi (diagram): Nasabah 1 --> 0..* Setoran ----
    /** @var Setoran[] */
    private array $riwayatSetoran = [];

    public function __construct(
        string $idPengguna,
        string $nama,
        string $email,
        string $password,
        string $noHp,
        string $alamat,
        ?DateTimeImmutable $tanggalDaftar = null
    ) {
        parent::__construct($idPengguna, $nama, $email, $password, $noHp);
        $this->alamat = $alamat;
        $this->tanggalDaftar = $tanggalDaftar ?? new DateTimeImmutable();
        $this->dompet = new Dompet("DMP-" . $idPengguna);
    }

    // ---- Method (sesuai diagram) ----

    /**
     * Mengajukan setoran. Setoran sebaiknya dibuat dengan dompet milik nasabah ini:
     * new Setoran($id, $nasabah->getDompet()).
     */
    public function setorSampah(Setoran $setoran): void
    {
        $this->riwayatSetoran[] = $setoran;
        echo $this->getNama() . " mengajukan setoran: " . $setoran . "\n";
    }

    /** Menukar poin dengan hadiah. Berhasil bila stok tersedia dan poin cukup. */
    public function tukarPoin(Hadiah $hadiah): bool
    {
        if (!$hadiah->cekStok()) {
            echo "Penukaran gagal: stok hadiah habis.\n";
            return false;
        }
        if (!$this->dompet->kurangiPoin($hadiah->getPoinDibutuhkan())) {
            echo "Penukaran gagal: poin tidak cukup (butuh "
                . $hadiah->getPoinDibutuhkan() . ", punya " . $this->dompet->getTotalPoin() . ").\n";
            return false;
        }
        $hadiah->kurangiStok();
        echo "Penukaran berhasil. Sisa poin: " . $this->dompet->getTotalPoin() . "\n";
        return true;
    }

    /**
     * Menarik saldo lewat metode yang dipilih (polymorphism lewat interface MetodePenarikan).
     * Saldo dipotong sebesar jumlah ditambah biaya admin metode tersebut.
     */
    public function tarikSaldo(float $jumlah, MetodePenarikan $metode): bool
    {
        if ($jumlah <= 0) {
            echo "Penarikan gagal: jumlah harus lebih dari 0.\n";
            return false;
        }
        $biayaAdmin = $metode->hitungBiayaAdmin($jumlah);
        $totalPotongan = $jumlah + $biayaAdmin;
        if (!$this->dompet->kurangiSaldo($totalPotongan)) {
            printf(
                "Penarikan gagal: saldo tidak cukup (butuh Rp%s termasuk biaya admin, saldo Rp%s).\n",
                number_format($totalPotongan, 0, ',', '.'),
                number_format($this->dompet->getSaldoRupiah(), 0, ',', '.')
            );
            return false;
        }
        if (!$metode->proses($jumlah)) {
            $this->dompet->tambahSaldo($totalPotongan); // pengiriman gagal: saldo dikembalikan
            echo "Penarikan gagal diproses oleh " . $metode->getNamaMetode() . ". Saldo dikembalikan.\n";
            return false;
        }
        printf(
            "Penarikan via %s berhasil. Biaya admin Rp%s. Sisa saldo Rp%s.\n",
            $metode->getNamaMetode(),
            number_format($biayaAdmin, 0, ',', '.'),
            number_format($this->dompet->getSaldoRupiah(), 0, ',', '.')
        );
        return true;
    }

    /**
     * Daftar setoran yang pernah diajukan nasabah ini (hanya baca).
     * @return Setoran[]
     */
    public function lihatRiwayat(): array
    {
        return $this->riwayatSetoran;
    }

    public function getDompet(): Dompet
    {
        return $this->dompet;
    }

    public function tampilkanMenu(): void
    {
        echo "=== Menu Nasabah: " . $this->getNama() . " ===\n";
        echo "1. Setor sampah\n";
        echo "2. Lihat riwayat setoran\n";
        echo "3. Lihat saldo dan poin\n";
        echo "4. Tukar poin dengan hadiah\n";
        echo "5. Tarik saldo\n";
        echo "6. Ubah password\n";
        echo "0. Logout\n";
    }

    // ---- Penunjang di luar diagram ----

    /** Menambahkan riwayat setoran saat dimuat dari penyimpanan, tanpa mencetak log transaksi baru. */
    public function muatRiwayatSetoran(Setoran $setoran): void
    {
        $this->riwayatSetoran[] = $setoran;
    }

    public function getAlamat(): string
    {
        return $this->alamat;
    }

    public function getTanggalDaftar(): DateTimeImmutable
    {
        return $this->tanggalDaftar;
    }

    public function __toString(): string
    {
        return parent::__toString() . " | " . $this->alamat . " | daftar " . $this->tanggalDaftar->format('D M d H:i:s T Y');
    }
}
