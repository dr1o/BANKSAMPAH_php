<?php

declare(strict_types=1);

namespace BankSampah\Keuangan;

/** Penarikan saldo lewat rekening bank. Biaya admin tetap Rp2.500. */
class TransferBank implements MetodePenarikan
{
    // ---- Atribut (sesuai diagram) ----
    private readonly string $namaBank;
    private readonly string $noRekening;
    private readonly string $namaPemilik;

    public function __construct(string $namaBank, string $noRekening, string $namaPemilik)
    {
        $this->namaBank = $namaBank;
        $this->noRekening = $noRekening;
        $this->namaPemilik = $namaPemilik;
    }

    // ---- Method (sesuai diagram) ----
    public function proses(float $jumlah): bool
    {
        if ($jumlah <= 0) {
            return false;
        }
        printf(
            "Transfer Rp%s ke %s %s a.n. %s berhasil diproses.\n",
            number_format($jumlah, 0, ',', '.'),
            $this->namaBank,
            $this->noRekening,
            $this->namaPemilik
        );
        return true;
    }

    public function hitungBiayaAdmin(float $jumlah): float
    {
        return 2500.0;
    }

    public function getNamaMetode(): string
    {
        return "Transfer Bank " . $this->namaBank;
    }
}
