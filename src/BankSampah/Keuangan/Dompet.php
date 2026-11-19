<?php

declare(strict_types=1);

namespace BankSampah\Keuangan;

use InvalidArgumentException;
use Stringable;

/**
 * Dompet milik satu Nasabah (relasi composition). Menyimpan saldo rupiah dan poin.
 * Encapsulation: tidak ada setSaldo()/setPoin(); nilai hanya berubah lewat method tambah dan kurangi,
 * sehingga saldo dan poin tidak pernah negatif.
 */
class Dompet implements Stringable
{
    // ---- Atribut (sesuai diagram) ----
    private readonly string $idDompet;
    private float $saldoRupiah;
    private int $totalPoin;

    public function __construct(string $idDompet)
    {
        $this->idDompet = $idDompet;
        $this->saldoRupiah = 0.0;
        $this->totalPoin = 0;
    }

    // ---- Method (sesuai diagram) ----

    public function tambahSaldo(float $jumlah): void
    {
        if ($jumlah <= 0) {
            throw new InvalidArgumentException("Jumlah saldo yang ditambahkan harus lebih dari 0.");
        }
        $this->saldoRupiah += $jumlah;
    }

    /** Mengembalikan false (saldo tidak berubah) bila jumlah tidak valid atau saldo tidak cukup. */
    public function kurangiSaldo(float $jumlah): bool
    {
        if ($jumlah <= 0 || $jumlah > $this->saldoRupiah) {
            return false;
        }
        $this->saldoRupiah -= $jumlah;
        return true;
    }

    public function tambahPoin(int $poin): void
    {
        if ($poin <= 0) {
            throw new InvalidArgumentException("Poin yang ditambahkan harus lebih dari 0.");
        }
        $this->totalPoin += $poin;
    }

    /** Mengembalikan false (poin tidak berubah) bila poin tidak valid atau tidak cukup. */
    public function kurangiPoin(int $poin): bool
    {
        if ($poin <= 0 || $poin > $this->totalPoin) {
            return false;
        }
        $this->totalPoin -= $poin;
        return true;
    }

    public function getSaldoRupiah(): float
    {
        return $this->saldoRupiah;
    }

    public function getTotalPoin(): int
    {
        return $this->totalPoin;
    }

    // ---- Penunjang di luar diagram ----

    public function getIdDompet(): string
    {
        return $this->idDompet;
    }

    public function __toString(): string
    {
        return sprintf(
            "Dompet %s | saldo Rp%s | poin %d",
            $this->idDompet,
            number_format($this->saldoRupiah, 0, ',', '.'),
            $this->totalPoin
        );
    }
}
