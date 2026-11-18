<?php

declare(strict_types=1);

namespace BankSampah\Sampah;

use InvalidArgumentException;

/** Sampah plastik. Nilai dikurangi 20% bila plastik kotor. */
class Plastik extends Sampah
{
    // ---- Atribut (sesuai diagram) ----
    private readonly string $jenisPlastik;
    private readonly bool $bersih;

    public function __construct(
        string $idSampah,
        string $namaSampah,
        float $hargaPerKg,
        string $jenisPlastik,
        bool $bersih
    ) {
        parent::__construct($idSampah, $namaSampah, $hargaPerKg);
        $this->jenisPlastik = $jenisPlastik;
        $this->bersih = $bersih;
    }

    // ---- Method (sesuai diagram) ----
    public function hitungNilai(float $berat): float
    {
        if ($berat < 0) {
            throw new InvalidArgumentException("Berat tidak boleh negatif.");
        }
        $nilai = $this->hargaPerKg * $berat;
        return $this->bersih ? $nilai : $nilai * 0.8; // plastik kotor dipotong 20%
    }

    public function __toString(): string
    {
        return parent::__toString() . " [" . $this->jenisPlastik . ", " . ($this->bersih ? "bersih" : "kotor") . "]";
    }
}
