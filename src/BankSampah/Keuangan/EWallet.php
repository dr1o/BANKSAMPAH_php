<?php

declare(strict_types=1);

namespace BankSampah\Keuangan;

/** Penarikan saldo lewat dompet digital. Biaya admin 1% dari jumlah penarikan. */
class EWallet implements MetodePenarikan
{
    // ---- Atribut (sesuai diagram) ----
    private readonly string $penyedia;
    private readonly string $noHpTerdaftar;

    public function __construct(string $penyedia, string $noHpTerdaftar)
    {
        $this->penyedia = $penyedia;
        $this->noHpTerdaftar = $noHpTerdaftar;
    }

    // ---- Method (sesuai diagram) ----
    public function proses(float $jumlah): bool
    {
        if ($jumlah <= 0) {
            return false;
        }
        printf(
            "Pengiriman Rp%s ke %s %s berhasil diproses.\n",
            number_format($jumlah, 0, ',', '.'),
            $this->penyedia,
            $this->noHpTerdaftar
        );
        return true;
    }

    public function hitungBiayaAdmin(float $jumlah): float
    {
        return $jumlah * 0.01;
    }

    public function getNamaMetode(): string
    {
        return "E-Wallet " . $this->penyedia;
    }
}
