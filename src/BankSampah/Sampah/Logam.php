<?php

declare(strict_types=1);

namespace BankSampah\Sampah;

use InvalidArgumentException;

/** Sampah logam. Nilai dikalikan kadar kemurnian (0.0 sampai 1.0, misalnya 0.85 = 85%). */
class Logam extends Sampah
{
    // ---- Atribut (sesuai diagram) ----
    private readonly string $jenisLogam;
    private readonly float $kadarKemurnian;

    public function __construct(
        string $idSampah,
        string $namaSampah,
        float $hargaPerKg,
        string $jenisLogam,
        float $kadarKemurnian
    ) {
        parent::__construct($idSampah, $namaSampah, $hargaPerKg);
        if ($kadarKemurnian < 0 || $kadarKemurnian > 1) {
            throw new InvalidArgumentException("Kadar kemurnian harus antara 0.0 dan 1.0.");
        }
        $this->jenisLogam = $jenisLogam;
        $this->kadarKemurnian = $kadarKemurnian;
    }

    // ---- Method (sesuai diagram) ----
    public function hitungNilai(float $berat): float
    {
        if ($berat < 0) {
            throw new InvalidArgumentException("Berat tidak boleh negatif.");
        }
        return $this->hargaPerKg * $berat * $this->kadarKemurnian;
    }

    public function __toString(): string
    {
        return parent::__toString() . " [" . $this->jenisLogam . ", kemurnian " . (int) round($this->kadarKemurnian * 100) . "%]";
    }
}
