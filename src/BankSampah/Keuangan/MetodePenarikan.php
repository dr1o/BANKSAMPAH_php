<?php

declare(strict_types=1);

namespace BankSampah\Keuangan;

/**
 * Interface: kontrak yang wajib dipenuhi semua metode penarikan saldo
 * (TransferBank, EWallet). Metode baru dapat ditambahkan tanpa mengubah Nasabah.
 */
interface MetodePenarikan
{
    /** Memproses pengiriman dana sebesar jumlah. Mengembalikan true bila berhasil. */
    public function proses(float $jumlah): bool;

    /** Biaya admin untuk penarikan sebesar jumlah (berbeda tiap metode). */
    public function hitungBiayaAdmin(float $jumlah): float;

    public function getNamaMetode(): string;
}
