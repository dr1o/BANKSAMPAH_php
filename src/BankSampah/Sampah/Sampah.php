<?php

declare(strict_types=1);

namespace BankSampah\Sampah;

use InvalidArgumentException;
use Stringable;

/**
 * Abstract class: kelas induk semua jenis sampah (Plastik, Kertas, Logam).
 * Cara menghitung nilai berbeda untuk tiap jenis (polymorphism lewat hitungNilai).
 */
abstract class Sampah implements Stringable
{
    // ---- Atribut (sesuai diagram) ----
    private readonly string $idSampah;
    private readonly string $namaSampah;
    protected float $hargaPerKg; // protected: dipakai subkelas untuk menghitung nilai

    protected function __construct(string $idSampah, string $namaSampah, float $hargaPerKg)
    {
        if ($hargaPerKg < 0) {
            throw new InvalidArgumentException("Harga per kg tidak boleh negatif.");
        }
        $this->idSampah = $idSampah;
        $this->namaSampah = $namaSampah;
        $this->hargaPerKg = $hargaPerKg;
    }

    // ---- Method (sesuai diagram) ----

    public function getNamaSampah(): string
    {
        return $this->namaSampah;
    }

    public function getHargaPerKg(): float
    {
        return $this->hargaPerKg;
    }

    public function setHargaPerKg(float $harga): void
    {
        if ($harga < 0) {
            throw new InvalidArgumentException("Harga per kg tidak boleh negatif.");
        }
        $this->hargaPerKg = $harga;
    }

    /** Method abstrak: nilai rupiah untuk berat (kg) tertentu, diimplementasikan tiap subkelas. */
    abstract public function hitungNilai(float $berat): float;

    /** Poin dari nilai rupiah: 1 poin untuk setiap Rp100 (pembulatan ke bawah). */
    public function hitungPoin(float $nilai): int
    {
        if ($nilai <= 0) {
            return 0;
        }
        return (int) ($nilai / 100);
    }

    public function __toString(): string
    {
        return sprintf(
            "%s - %s (Rp%s/kg)",
            $this->idSampah,
            $this->namaSampah,
            number_format($this->hargaPerKg, 0, ',', '.')
        );
    }
}
