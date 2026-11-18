<?php

declare(strict_types=1);

/**
 * Demo Sistem Bank Sampah (skrip otomatis tanpa input, setara demo.jsh di Java).
 * Jalankan dari folder proyek: php demo.php
 */

require_once __DIR__ . '/src/autoload.php';

use BankSampah\Hadiah\Hadiah;
use BankSampah\Keuangan\EWallet;
use BankSampah\Keuangan\TransferBank;
use BankSampah\Pengguna\Admin;
use BankSampah\Pengguna\Nasabah;
use BankSampah\Pengguna\Pengguna;
use BankSampah\Pengguna\Petugas;
use BankSampah\Sampah\Kertas;
use BankSampah\Sampah\Logam;
use BankSampah\Sampah\Plastik;
use BankSampah\Sampah\Sampah;
use BankSampah\Setoran\DetailSetoran;
use BankSampah\Setoran\Setoran;

// --- Data awal ---
$plastik = new Plastik("SMP-01", "Botol PET", 3000, "PET", true);
$kertas = new Kertas("SMP-02", "Kardus", 1500, "Kardus", true);
$logam = new Logam("SMP-03", "Aluminium", 8000, "Aluminium", 0.9);
/** @var Sampah[] $daftarSampah */
$daftarSampah = [$plastik, $kertas, $logam];
/** @var Hadiah[] $daftarHadiah */
$daftarHadiah = [new Hadiah("H-01", "Payung", 100, 2)];
/** @var Pengguna[] $daftarPengguna */
$daftarPengguna = [];
/** @var Setoran[] $daftarSetoran */
$daftarSetoran = [];

$budi = new Nasabah("N-001", "Budi", "budi@mail.com", "rahasia1", "0812", "Jl. Mawar 1");
$sari = new Petugas("P-001", "Sari", "sari@mail.com", "petugas1", "0813", "PTG-01", "Wilayah A");
$dewi = new Admin(
    "A-001",
    "Dewi",
    "dewi@mail.com",
    "admin123",
    "0814",
    "PENUH",
    $daftarSampah,
    $daftarHadiah,
    $daftarPengguna,
    $daftarSetoran
);
$daftarPengguna[] = $budi;
$daftarPengguna[] = $sari;
$daftarPengguna[] = $dewi;

echo "\n##### 1. Login dan menu (polymorphism tampilkanMenu) #####\n";
echo "Login Budi: " . ($budi->login("budi@mail.com", "rahasia1") ? "true" : "false") . "\n";
foreach ($daftarPengguna as $p) {
    $p->tampilkanMenu();
}

echo "\n##### 2. Setor sampah, timbang, verifikasi #####\n";
$s1 = new Setoran("S-001", $budi->getDompet());
$s1->tambahDetail(new DetailSetoran($plastik, 2));
$s1->tambahDetail(new DetailSetoran($kertas, 3));
$s1->tambahDetail(new DetailSetoran($logam, 1));
$budi->setorSampah($s1);
$daftarSetoran[] = $s1;
$sari->timbangSampah($s1);
$sari->verifikasiSetoran($s1);
echo $budi->getDompet() . "\n";

echo "\n##### 3. Tukar poin dengan hadiah #####\n";
$budi->tukarPoin($daftarHadiah[0]);
$budi->tukarPoin($daftarHadiah[0]);

echo "\n##### 4. Tarik saldo (polymorphism MetodePenarikan) #####\n";
$budi->tarikSaldo(10000, new TransferBank("BRI", "1234567890", "Budi"));
$budi->tarikSaldo(5000, new EWallet("GoPay", "0812"));
$budi->tarikSaldo(1000, new EWallet("GoPay", "0812"));
echo $budi->getDompet() . "\n";

echo "\n##### 5. Admin #####\n";
$dewi->kelolaPengguna();
$dewi->buatLaporan();
