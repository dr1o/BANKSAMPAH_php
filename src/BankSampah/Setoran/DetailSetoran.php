<?php

declare(strict_types=1);

namespace BankSampah\Setoran;

use BankSampah\Sampah\Sampah;
use InvalidArgumentException;
use Stringable;

/**
 * Satu baris item dalam Setoran (relasi composition dengan Setoran).
 * Subtotal dihitung dengan memanggil hitungNilai() milik Sampah, sehingga rumus yang berjalan
 * mengikuti jenis sampah sebenarnya (polymorphism).
 */
class DetailSetoran implements Stringable
{
    // ---- Atribut (sesuai diagram) ----
    private readonly float $berat;
    private float $subtotal;

    // ---- Relasi (diagram): DetailSetoran 0..* --> 1 Sampah ----
    private readonly Sampah $sampah;

    public function __construct(Sampah $sampah, float $berat)
    {
        if ($berat <= 0) {
            throw new InvalidArgumentException("Berat harus lebih dari 0 kg.");
        }
        $this->sampah = $sampah;
        $this->berat = $berat;
        $this->subtotal = $sampah->hitungNilai($berat); // harga dikunci pada saat setoran diajukan
    }

    // ---- Method (sesuai diagram) ----

    public function hitungSubtotal(): float
    {
        $this->subtotal = $this->sampah->hitungNilai($this->berat);
        return $this->subtotal;
    }

    public function getSubtotal(): float
    {
        return $this->subtotal;
    }

    public function __toString(): string
    {
        $beratStr = floor($this->berat) == $this->berat
            ? number_format($this->berat, 1, '.', '')
            : (string) $this->berat;

        return sprintf(
            "%s %s kg = Rp%s",
            $this->sampah->getNamaSampah(),
            $beratStr,
            number_format($this->subtotal, 0, ',', '.')
        );
    }
}
