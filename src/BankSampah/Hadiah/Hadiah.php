<?php

declare(strict_types=1);

namespace BankSampah\Hadiah;

use InvalidArgumentException;
use RuntimeException;
use Stringable;

/** Hadiah yang dapat ditukar dengan poin. */
class Hadiah implements Stringable
{
    // ---- Atribut (sesuai diagram) ----
    private readonly string $idHadiah;
    private readonly string $namaHadiah;
    private readonly int $poinDibutuhkan;
    private int $stok;

    public function __construct(string $idHadiah, string $namaHadiah, int $poinDibutuhkan, int $stok)
    {
        if ($poinDibutuhkan <= 0) {
            throw new InvalidArgumentException("Poin yang dibutuhkan harus lebih dari 0.");
        }
        if ($stok < 0) {
            throw new InvalidArgumentException("Stok tidak boleh negatif.");
        }
        $this->idHadiah = $idHadiah;
        $this->namaHadiah = $namaHadiah;
        $this->poinDibutuhkan = $poinDibutuhkan;
        $this->stok = $stok;
    }

    // ---- Method (sesuai diagram) ----

    /** true bila stok hadiah masih tersedia. */
    public function cekStok(): bool
    {
        return $this->stok > 0;
    }

    public function kurangiStok(): void
    {
        if ($this->stok <= 0) {
            throw new RuntimeException("Stok hadiah " . $this->namaHadiah . " sudah habis.");
        }
        $this->stok--;
    }

    public function getPoinDibutuhkan(): int
    {
        return $this->poinDibutuhkan;
    }

    // ---- Penunjang di luar diagram ----

    /** Menambah stok hadiah (dipakai Admin saat restock). */
    public function tambahStok(int $tambahan): void
    {
        if ($tambahan <= 0) {
            throw new InvalidArgumentException("Tambahan stok harus lebih dari 0.");
        }
        $this->stok += $tambahan;
    }

    public function getIdHadiah(): string
    {
        return $this->idHadiah;
    }

    public function getNamaHadiah(): string
    {
        return $this->namaHadiah;
    }

    public function getStok(): int
    {
        return $this->stok;
    }

    public function __toString(): string
    {
        return $this->idHadiah . " - " . $this->namaHadiah . " (" . $this->poinDibutuhkan . " poin, stok " . $this->stok . ")";
    }
}
