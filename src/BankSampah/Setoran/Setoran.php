<?php

declare(strict_types=1);

namespace BankSampah\Setoran;

use BankSampah\Keuangan\Dompet;
use BankSampah\Pengguna\Petugas;
use DateTimeImmutable;
use RuntimeException;
use Stringable;

/**
 * Satu transaksi penyetoran sampah.
 * Status (string): DIAJUKAN, DITIMBANG, DIVERIFIKASI, SELESAI, atau DITOLAK.
 * Alur: DIAJUKAN -> (hitungTotal) DITIMBANG -> (verifikasi) DIVERIFIKASI -> SELESAI.
 */
class Setoran implements Stringable
{
    // Konstanta nilai status
    private const DIAJUKAN = 'DIAJUKAN';
    private const DITIMBANG = 'DITIMBANG';
    private const DIVERIFIKASI = 'DIVERIFIKASI';
    private const SELESAI = 'SELESAI';
    private const DITOLAK = 'DITOLAK';

    // ---- Atribut (sesuai diagram) ----
    private readonly string $idSetoran;
    private readonly DateTimeImmutable $tanggalSetor;
    private string $status;
    private float $totalNilai;
    private int $totalPoin;

    // ---- Relasi (diagram): Setoran 1 *-- 1..* DetailSetoran ----
    /** @var DetailSetoran[] */
    private array $daftarDetail = [];

    // ---- Relasi (diagram): Setoran ..> Dompet (dompet milik nasabah penyetor) ----
    private readonly Dompet $dompet;

    public function __construct(string $idSetoran, Dompet $dompet)
    {
        $this->idSetoran = $idSetoran;
        $this->dompet = $dompet;
        $this->tanggalSetor = new DateTimeImmutable();
        $this->status = self::DIAJUKAN;
        $this->totalNilai = 0.0;
        $this->totalPoin = 0;
    }

    // ---- Method (sesuai diagram) ----

    /** Menambah item sampah. Hanya boleh saat status masih DIAJUKAN (sebelum ditimbang). */
    public function tambahDetail(DetailSetoran $detail): void
    {
        if ($this->status !== self::DIAJUKAN) {
            throw new RuntimeException(
                "Detail hanya bisa ditambah saat status DIAJUKAN (status sekarang: " . $this->status . ")."
            );
        }
        $this->daftarDetail[] = $detail;
    }

    /**
     * Menjumlahkan subtotal seluruh detail menjadi totalNilai dan menghitung totalPoin.
     * Dipanggil petugas saat penimbangan; bila status masih DIAJUKAN dan ada detail, status menjadi DITIMBANG.
     */
    public function hitungTotal(): float
    {
        $total = 0.0;
        foreach ($this->daftarDetail as $detail) {
            $total += $detail->getSubtotal();
        }
        $this->totalNilai = $total;
        $this->totalPoin = (int) ($total / 100); // aturan sama dengan Sampah::hitungPoin(): 1 poin per Rp100
        if ($this->status === self::DIAJUKAN && count($this->daftarDetail) > 0) {
            $this->status = self::DITIMBANG;
        }
        return $this->totalNilai;
    }

    /**
     * Verifikasi oleh petugas. Hanya setoran berstatus DITIMBANG yang dapat diverifikasi.
     * Berhasil: saldo dan poin ditambahkan ke dompet nasabah, status menjadi SELESAI, mengembalikan true.
     * Ditolak (total nilai 0): status menjadi DITOLAK, mengembalikan false.
     * Belum ditimbang atau sudah diproses: status tidak berubah, mengembalikan false.
     */
    public function verifikasi(Petugas $petugas): bool
    {
        if ($this->status !== self::DITIMBANG) {
            return false;
        }
        if ($this->totalNilai <= 0) {
            $this->status = self::DITOLAK;
            return false;
        }
        $this->status = self::DIVERIFIKASI;
        $this->dompet->tambahSaldo($this->totalNilai);
        if ($this->totalPoin > 0) {
            $this->dompet->tambahPoin($this->totalPoin);
        }
        $this->status = self::SELESAI;
        return true;
    }

    /** Membatalkan setoran yang belum selesai; statusnya menjadi DITOLAK. */
    public function batalkan(): void
    {
        if ($this->status === self::SELESAI) {
            throw new RuntimeException("Setoran yang sudah SELESAI tidak dapat dibatalkan.");
        }
        $this->status = self::DITOLAK;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function __toString(): string
    {
        return sprintf(
            "%s | %s | Rp%s | %d poin | %d item",
            $this->idSetoran,
            $this->status,
            number_format($this->totalNilai, 0, ',', '.'),
            $this->totalPoin,
            count($this->daftarDetail)
        );
    }
}
