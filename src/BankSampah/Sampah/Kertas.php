<?php

declare(strict_types=1);

namespace BankSampah\Sampah;

use InvalidArgumentException;

/** Sampah kertas. Nilai dikurangi 30% bila kertas basah (tidak kering). */
class Kertas extends Sampah
{
    // ---- Atribut (sesuai diagram) ----
    private readonly string $jenisKertas;
    private readonly bool $kering;

    public function __construct(
        string $idSampah,
        string $namaSampah,
        float $hargaPerKg,
        string $jenisKertas,
        bool $kering
    ) {
        parent::__construct($idSampah, $namaSampah, $hargaPerKg);
        $this->jenisKertas = $jenisKertas;
        $this->kering = $kering;
    }

    // ---- Method (sesuai diagram) ----
    public function hitungNilai(float $berat): float
    {
        if ($berat < 0) {
            throw new InvalidArgumentException("Berat tidak boleh negatif.");
        }
        $nilai = $this->hargaPerKg * $berat;
        return $this->kering ? $nilai : $nilai * 0.7; // kertas basah dipotong 30%
    }

    public function __toString(): string
    {
        return parent::__toString() . " [" . $this->jenisKertas . ", " . ($this->kering ? "kering" : "basah") . "]";
    }
}
