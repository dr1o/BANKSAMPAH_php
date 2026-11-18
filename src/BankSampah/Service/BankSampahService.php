<?php

declare(strict_types=1);

namespace BankSampah\Service;

use BankSampah\Hadiah\Hadiah;
use BankSampah\Keuangan\Dompet;
use BankSampah\Keuangan\EWallet;
use BankSampah\Keuangan\MetodePenarikan;
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
use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Lapisan service untuk versi web PHP.
 *
 * Perannya sama dengan BankSampahService.java: mengoordinasikan 15 kelas domain
 * (Pengguna/Nasabah/Petugas/Admin, Sampah/Plastik/Kertas/Logam, Setoran/DetailSetoran,
 * Dompet, MetodePenarikan/TransferBank/EWallet, Hadiah) dan menyusun state JSON yang
 * dikonsumsi frontend.
 *
 * Server bawaan PHP tidak menyimpan state antar request, jadi seluruh state disimpan
 * ke berkas JSON pada setiap perubahan (lihat simpanState()).
 */
class BankSampahService
{
    private const VERSI_STATE = 1;
    private const MAKS_LOG = 120;

    /** @var Pengguna[] */
    private array $daftarPengguna = [];
    /** @var Sampah[] */
    private array $daftarSampah = [];
    /** @var Hadiah[] */
    private array $daftarHadiah = [];
    /** @var Setoran[] */
    private array $daftarSetoran = [];
    /** @var string[] */
    private array $oopLogs = [];

    private bool $dbConnected = false;
    private string $dbStatusMessage = 'Memuat penyimpanan...';
    private string $lokasiState;

    public function __construct(?string $lokasiState = null)
    {
        $this->lokasiState = $lokasiState ?? dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'state.json';
        $this->initialize();
    }

    // =====================================================================
    // Inisialisasi & penyimpanan
    // =====================================================================

    public function initialize(): void
    {
        $data = $this->bacaState();
        if ($data !== null) {
            $this->muatState($data);
            $this->dbConnected = true;
            $this->dbStatusMessage = 'Terhubung ke penyimpanan berkas (storage/state.json)';
            return;
        }

        $this->seedMemoryData();
        $this->dbConnected = true;
        $this->dbStatusMessage = 'Penyimpanan berkas baru dibuat (storage/state.json)';
        $this->logOop('Inisialisasi', 'Data awal 15 kelas domain OOP dibuat dan disimpan ke storage/state.json.');
        $this->simpanState();
    }

    private function seedMemoryData(): void
    {
        $this->daftarPengguna = [];
        $this->daftarSampah = [];
        $this->daftarHadiah = [];
        $this->daftarSetoran = [];

        $this->daftarSampah[] = new Plastik('SMP-01', 'Botol PET', 3000, 'PET', true);
        $this->daftarSampah[] = new Plastik('SMP-04', 'Plastik Kresek/HDPE Kotor', 2500, 'HDPE', false);
        $this->daftarSampah[] = new Kertas('SMP-02', 'Kardus', 1500, 'Kardus', true);
        $this->daftarSampah[] = new Kertas('SMP-05', 'Kertas Koran Lembab', 1200, 'Koran', false);
        $this->daftarSampah[] = new Logam('SMP-03', 'Aluminium', 8000, 'Aluminium', 0.9);

        $this->daftarHadiah[] = new Hadiah('H-01', 'Payung', 100, 2);
        $this->daftarHadiah[] = new Hadiah('H-02', 'Tas Belanja', 50, 5);

        $this->daftarPengguna[] = new Nasabah('N-001', 'Budi', 'budi@mail.com', 'rahasia1', '0812', 'Jl. Mawar 1');
        $this->daftarPengguna[] = new Petugas('P-001', 'Sari', 'sari@mail.com', 'petugas1', '0813', 'PTG-01', 'Wilayah A');
        $this->daftarPengguna[] = new Admin(
            'A-001',
            'Dewi',
            'dewi@mail.com',
            'admin123',
            '0814',
            'PENUH',
            $this->daftarSampah,
            $this->daftarHadiah,
            $this->daftarPengguna,
            $this->daftarSetoran
        );
    }

    private function bacaState(): ?array
    {
        if (!is_file($this->lokasiState)) {
            return null;
        }
        $isi = @file_get_contents($this->lokasiState);
        if ($isi === false || trim($isi) === '') {
            return null;
        }
        $data = json_decode($isi, true);
        if (!is_array($data) || !isset($data['pengguna']) || !is_array($data['pengguna'])) {
            return null;
        }
        return $data;
    }

    private function simpanState(): void
    {
        $folder = dirname($this->lokasiState);
        if (!is_dir($folder)) {
            @mkdir($folder, 0775, true);
        }

        $data = [
            'versi' => self::VERSI_STATE,
            'disimpanPada' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            'pengguna' => array_map(fn (Pengguna $p): array => $this->serialisasiPengguna($p), $this->daftarPengguna),
            'sampah' => array_map(fn (Sampah $s): array => $this->serialisasiSampah($s), $this->daftarSampah),
            'hadiah' => array_map(fn (Hadiah $h): array => $this->serialisasiHadiah($h), $this->daftarHadiah),
            'setoran' => array_map(fn (Setoran $s): array => $this->serialisasiSetoran($s), $this->daftarSetoran),
            'oopLogs' => $this->oopLogs,
        ];

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json !== false) {
            @file_put_contents($this->lokasiState, $json, LOCK_EX);
        }
    }

    private function serialisasiPengguna(Pengguna $p): array
    {
        $dasar = [
            'role' => $this->namaRole($p),
            'idPengguna' => $p->getIdPengguna(),
            'nama' => $p->getNama(),
            'email' => $p->getEmail(),
            'password' => $p->getPassword(),
            'noHp' => $p->getNoHp(),
        ];

        if ($p instanceof Nasabah) {
            return $dasar + [
                'alamat' => $p->getAlamat(),
                'tanggalDaftar' => $p->getTanggalDaftar()->format('Y-m-d H:i:s'),
                'saldoRupiah' => $p->getDompet()->getSaldoRupiah(),
                'totalPoin' => $p->getDompet()->getTotalPoin(),
            ];
        }
        if ($p instanceof Petugas) {
            return $dasar + [
                'idPetugas' => $p->getIdPetugas(),
                'wilayahTugas' => $p->getWilayahTugas(),
                'setoranDiverifikasi' => array_map(
                    fn (Setoran $s): string => $s->getIdSetoran(),
                    $p->getDaftarSetoran()
                ),
            ];
        }
        /** @var Admin $p */
        return $dasar + ['levelAkses' => $p->getLevelAkses()];
    }

    private function serialisasiSampah(Sampah $s): array
    {
        $dasar = [
            'subkelas' => $this->namaKelas($s),
            'idSampah' => $s->getIdSampah(),
            'namaSampah' => $s->getNamaSampah(),
            'hargaPerKg' => $s->getHargaPerKg(),
        ];
        if ($s instanceof Plastik) {
            return $dasar + ['jenisSpesifik' => $s->getJenisPlastik(), 'bersih' => $s->isBersih()];
        }
        if ($s instanceof Kertas) {
            return $dasar + ['jenisSpesifik' => $s->getJenisKertas(), 'kering' => $s->isKering()];
        }
        /** @var Logam $s */
        return $dasar + ['jenisSpesifik' => $s->getJenisLogam(), 'kadarKemurnian' => $s->getKadarKemurnian()];
    }

    private function serialisasiHadiah(Hadiah $h): array
    {
        return [
            'idHadiah' => $h->getIdHadiah(),
            'namaHadiah' => $h->getNamaHadiah(),
            'poinDibutuhkan' => $h->getPoinDibutuhkan(),
            'stok' => $h->getStok(),
        ];
    }

    private function serialisasiSetoran(Setoran $s): array
    {
        $penyetor = $this->findPenyetor($s);
        $verifikator = $this->findVerifikator($s);

        return [
            'idSetoran' => $s->getIdSetoran(),
            'tanggalSetor' => $s->getTanggalSetor()->format('Y-m-d H:i:s'),
            'status' => $s->getStatus(),
            'totalNilai' => $s->getTotalNilai(),
            'totalPoin' => $s->getTotalPoin(),
            'idDompet' => $s->getDompet()->getIdDompet(),
            'idNasabah' => $penyetor?->getIdPengguna() ?? '',
            'idPetugas' => $verifikator?->getIdPengguna() ?? '',
            'detail' => array_map(
                fn (DetailSetoran $d): array => [
                    'idSampah' => $d->getSampah()->getIdSampah(),
                    'berat' => $d->getBerat(),
                    'subtotal' => $d->getSubtotal(),
                ],
                $s->getDaftarDetail()
            ),
        ];
    }

    private function muatState(array $data): void
    {
        $this->daftarPengguna = [];
        $this->daftarSampah = [];
        $this->daftarHadiah = [];
        $this->daftarSetoran = [];

        foreach ($data['sampah'] ?? [] as $row) {
            $this->daftarSampah[] = $this->bangunSampah($row);
        }
        foreach ($data['hadiah'] ?? [] as $row) {
            $this->daftarHadiah[] = new Hadiah(
                (string) $row['idHadiah'],
                (string) $row['namaHadiah'],
                (int) $row['poinDibutuhkan'],
                (int) $row['stok']
            );
        }

        // Pengguna dimuat lebih dulu karena Setoran membutuhkan Dompet milik Nasabah.
        foreach ($data['pengguna'] ?? [] as $row) {
            $this->daftarPengguna[] = $this->bangunPengguna($row);
        }

        foreach ($data['setoran'] ?? [] as $row) {
            $dompet = null;
            foreach ($this->daftarPengguna as $p) {
                if ($p instanceof Nasabah && $p->getIdPengguna() === (string) ($row['idNasabah'] ?? '')) {
                    $dompet = $p->getDompet();
                    break;
                }
            }
            if ($dompet === null) {
                continue;
            }

            $detail = [];
            foreach ($row['detail'] ?? [] as $d) {
                $sampah = $this->findSampahById((string) $d['idSampah']);
                if ($sampah === null) {
                    continue;
                }
                $detail[] = new DetailSetoran($sampah, (float) $d['berat'], (float) $d['subtotal']);
            }

            $setoran = Setoran::dariPenyimpanan(
                (string) $row['idSetoran'],
                $dompet,
                new DateTimeImmutable((string) $row['tanggalSetor']),
                (string) $row['status'],
                (float) ($row['totalNilai'] ?? 0),
                (int) ($row['totalPoin'] ?? 0),
                $detail
            );
            $this->daftarSetoran[] = $setoran;
        }

        // Sambungkan kembali relasi: Nasabah 1 --> 0..* Setoran, Petugas 1 --> 0..* Setoran.
        foreach ($this->daftarPengguna as $p) {
            if ($p instanceof Nasabah) {
                foreach ($this->daftarSetoran as $s) {
                    if ($s->getDompet()->getIdDompet() === $p->getDompet()->getIdDompet()) {
                        $p->muatRiwayatSetoran($s);
                    }
                }
            }
        }
        foreach ($data['pengguna'] ?? [] as $row) {
            if (($row['role'] ?? '') !== 'PETUGAS') {
                continue;
            }
            $petugas = $this->findPenggunaById((string) $row['idPengguna']);
            if (!$petugas instanceof Petugas) {
                continue;
            }
            foreach ($row['setoranDiverifikasi'] ?? [] as $idSetoran) {
                $setoran = $this->findSetoranById((string) $idSetoran);
                if ($setoran !== null) {
                    $petugas->muatSetoranTerverifikasi($setoran);
                }
            }
        }

        $this->oopLogs = array_values(array_map('strval', $data['oopLogs'] ?? []));
    }

    private function bangunSampah(array $row): Sampah
    {
        $subkelas = $row['subkelas'] ?? 'Plastik';
        return match ($subkelas) {
            'Kertas' => new Kertas(
                (string) $row['idSampah'],
                (string) $row['namaSampah'],
                (float) $row['hargaPerKg'],
                (string) $row['jenisSpesifik'],
                (bool) $row['kering']
            ),
            'Logam' => new Logam(
                (string) $row['idSampah'],
                (string) $row['namaSampah'],
                (float) $row['hargaPerKg'],
                (string) $row['jenisSpesifik'],
                (float) $row['kadarKemurnian']
            ),
            default => new Plastik(
                (string) $row['idSampah'],
                (string) $row['namaSampah'],
                (float) $row['hargaPerKg'],
                (string) $row['jenisSpesifik'],
                (bool) $row['bersih']
            ),
        };
    }

    private function bangunPengguna(array $row): Pengguna
    {
        $role = $row['role'] ?? 'NASABAH';

        if ($role === 'PETUGAS') {
            return new Petugas(
                (string) $row['idPengguna'],
                (string) $row['nama'],
                (string) $row['email'],
                (string) $row['password'],
                (string) $row['noHp'],
                (string) ($row['idPetugas'] ?? ''),
                (string) ($row['wilayahTugas'] ?? '')
            );
        }

        if ($role === 'ADMIN') {
            return new Admin(
                (string) $row['idPengguna'],
                (string) $row['nama'],
                (string) $row['email'],
                (string) $row['password'],
                (string) $row['noHp'],
                (string) ($row['levelAkses'] ?? 'PENUH'),
                $this->daftarSampah,
                $this->daftarHadiah,
                $this->daftarPengguna,
                $this->daftarSetoran
            );
        }

        $nasabah = new Nasabah(
            (string) $row['idPengguna'],
            (string) $row['nama'],
            (string) $row['email'],
            (string) $row['password'],
            (string) $row['noHp'],
            (string) ($row['alamat'] ?? '-'),
            isset($row['tanggalDaftar']) ? new DateTimeImmutable((string) $row['tanggalDaftar']) : null
        );
        $saldo = (float) ($row['saldoRupiah'] ?? 0);
        if ($saldo > 0) {
            $nasabah->getDompet()->tambahSaldo($saldo);
        }
        $poin = (int) ($row['totalPoin'] ?? 0);
        if ($poin > 0) {
            $nasabah->getDompet()->tambahPoin($poin);
        }
        return $nasabah;
    }

    // =====================================================================
    // Log konsep OOP
    // =====================================================================

    public function logOop(string $concept, string $message): void
    {
        $entry = sprintf('[%s] [%s] %s', (new DateTimeImmutable())->format('H:i:s'), $concept, $message);
        array_unshift($this->oopLogs, $entry);
        if (count($this->oopLogs) > self::MAKS_LOG) {
            $this->oopLogs = array_slice($this->oopLogs, 0, self::MAKS_LOG);
        }
    }

    /**
     * Method domain di versi PHP mencetak ke STDOUT (warisan versi CLI), dan cetakan itu
     * akan merusak JSON bila dibiarkan. Output ditangkap lalu dialihkan ke log server,
     * sama seperti versi Java yang mencetak ke konsol server. Isi oopLogs tetap berasal
     * dari pemanggilan logOop() agar sebanding dengan versi Java.
     */
    public function tangkapOutputDomain(): void
    {
        if (ob_get_level() === 0) {
            return;
        }
        $keluaran = (string) ob_get_clean();
        ob_start();
        foreach (preg_split('/\r?\n/', trim($keluaran)) ?: [] as $baris) {
            $baris = trim($baris);
            if ($baris !== '') {
                error_log('[domain-php] ' . $baris);
            }
        }
    }

    // =====================================================================
    // Autentikasi & pengguna
    // =====================================================================

    public function login(?string $email, ?string $password): Pengguna
    {
        if ($email === null || $password === null || $email === '' || $password === '') {
            throw new InvalidArgumentException('Email dan password wajib diisi.');
        }
        foreach ($this->daftarPengguna as $p) {
            if ($p->login(trim($email), $password)) {
                $p->tampilkanMenu(); // abstract method tampilkanMenu() dijalankan sesuai subkelas
                $this->logOop(
                    'Inheritance & Polymorphism',
                    $this->namaKelas($p) . '.login() & tampilkanMenu() berhasil untuk '
                    . $p->getNama() . ' (' . $p->getIdPengguna() . ')'
                );
                $this->simpanState();
                return $p;
            }
        }
        throw new InvalidArgumentException('Email atau password salah.');
    }

    public function logout(?string $idPengguna): void
    {
        $p = $this->findPenggunaById($idPengguna);
        if ($p !== null) {
            $p->logout();
            $this->logOop('Pengguna.logout()', $p->getNama() . ' (' . $this->namaKelas($p) . ') telah logout.');
            $this->simpanState();
        }
    }

    public function registerNasabah(
        ?string $nama,
        ?string $email,
        ?string $password,
        ?string $noHp,
        ?string $alamat
    ): Nasabah {
        $this->validateCommonUserFields($nama, $email, $password, $noHp);
        if ($alamat === null || trim($alamat) === '') {
            throw new InvalidArgumentException('Alamat nasabah wajib diisi.');
        }

        $newId = $this->generateNextId('N-', array_map(fn (Pengguna $p): string => $p->getIdPengguna(), $this->daftarPengguna), 3);
        $nasabah = new Nasabah($newId, trim($nama), trim($email), $password, trim($noHp), trim($alamat));
        $this->daftarPengguna[] = $nasabah;
        $this->logOop(
            'Composition (Nasabah *-- Dompet)',
            'Instansiasi Nasabah ' . $newId . ' otomatis membentuk objek Dompet '
            . $nasabah->getDompet()->getIdDompet() . '.'
        );
        $this->simpanState();
        return $nasabah;
    }

    public function tambahPenggunaOlehAdmin(
        ?string $role,
        ?string $nama,
        ?string $email,
        ?string $password,
        ?string $noHp,
        ?string $extra1,
        ?string $extra2
    ): Pengguna {
        $this->validateCommonUserFields($nama, $email, $password, $noHp);
        $cleanRole = $role !== null ? strtoupper(trim($role)) : 'NASABAH';
        $ids = array_map(fn (Pengguna $p): string => $p->getIdPengguna(), $this->daftarPengguna);

        switch ($cleanRole) {
            case 'NASABAH':
                $alamat = ($extra1 !== null && trim($extra1) !== '') ? trim($extra1) : '-';
                return $this->registerNasabah($nama, $email, $password, $noHp, $alamat);

            case 'PETUGAS':
                $newId = $this->generateNextId('P-', $ids, 3);
                $idPetugas = ($extra1 !== null && trim($extra1) !== '') ? trim($extra1) : ('PTG-' . substr($newId, 2));
                $wilayah = ($extra2 !== null && trim($extra2) !== '') ? trim($extra2) : 'Wilayah A';
                $petugas = new Petugas($newId, trim($nama), trim($email), $password, trim($noHp), $idPetugas, $wilayah);
                $this->daftarPengguna[] = $petugas;
                $this->logOop(
                    'Inheritance (Petugas extends Pengguna)',
                    'Admin menambahkan Petugas baru ' . $newId . ' (' . $idPetugas . ' - ' . $wilayah . ').'
                );
                $this->simpanState();
                return $petugas;

            case 'ADMIN':
                $newId = $this->generateNextId('A-', $ids, 3);
                $levelAkses = ($extra1 !== null && trim($extra1) !== '') ? trim($extra1) : 'PENUH';
                $admin = new Admin(
                    $newId,
                    trim($nama),
                    trim($email),
                    $password,
                    trim($noHp),
                    $levelAkses,
                    $this->daftarSampah,
                    $this->daftarHadiah,
                    $this->daftarPengguna,
                    $this->daftarSetoran
                );
                $this->daftarPengguna[] = $admin;
                $this->logOop(
                    'Inheritance (Admin extends Pengguna)',
                    'Admin menambahkan Admin baru ' . $newId . ' dengan levelAkses=' . $levelAkses . '.'
                );
                $this->simpanState();
                return $admin;

            default:
                throw new InvalidArgumentException('Role pengguna tidak valid: ' . $role);
        }
    }

    private function validateCommonUserFields(
        ?string $nama,
        ?string $email,
        ?string $password,
        ?string $noHp
    ): void {
        if ($nama === null || trim($nama) === '' || $email === null || trim($email) === ''
            || $password === null || $noHp === null || trim($noHp) === '') {
            throw new InvalidArgumentException('Nama, email, password, dan nomor HP wajib diisi.');
        }
        if (!str_contains($email, '@')) {
            throw new InvalidArgumentException('Format email tidak valid.');
        }
        if (strlen($password) < 6) {
            throw new InvalidArgumentException('Password minimal 6 karakter (aturan Pengguna.ubahPassword).');
        }
        foreach ($this->daftarPengguna as $p) {
            if (strcasecmp(trim($email), $p->getEmail()) === 0) {
                throw new InvalidArgumentException('Email sudah terdaftar di sistem.');
            }
        }
    }

    public function ubahPassword(?string $idPengguna, ?string $passwordLama, ?string $passwordBaru): void
    {
        $p = $this->findPenggunaById($idPengguna);
        if ($p === null) {
            throw new InvalidArgumentException('Pengguna tidak ditemukan.');
        }
        if (!$p->ubahPassword((string) $passwordLama, (string) $passwordBaru)) {
            throw new InvalidArgumentException(
                'Gagal mengubah password: password lama salah atau password baru kurang dari 6 karakter.'
            );
        }
        $this->logOop(
            'Encapsulation (Pengguna.ubahPassword)',
            'Password milik ' . $p->getNama() . ' (' . $p->getIdPengguna() . ') berhasil diperbarui.'
        );
        $this->simpanState();
    }

    public function ubahNama(?string $idPengguna, ?string $namaBaru): void
    {
        $p = $this->findPenggunaById($idPengguna);
        if ($p === null) {
            throw new InvalidArgumentException('Pengguna tidak ditemukan.');
        }
        $p->setNama(trim((string) $namaBaru));
        $this->logOop(
            'Encapsulation (Pengguna.setNama)',
            'Nama pengguna ' . $p->getIdPengguna() . ' diperbarui menjadi ' . $p->getNama() . '.'
        );
        $this->simpanState();
    }

    // =====================================================================
    // Alur setoran
    // =====================================================================

    public function ajukanSetoran(?string $idNasabah, array $rawItems): Setoran
    {
        $p = $this->findPenggunaById($idNasabah);
        if (!$p instanceof Nasabah) {
            throw new InvalidArgumentException('Hanya Nasabah yang dapat mengajukan setoran sampah.');
        }
        if (count($rawItems) === 0) {
            throw new InvalidArgumentException('Setoran harus memiliki minimal 1 DetailSetoran.');
        }

        $ids = array_map(fn (Setoran $s): string => $s->getIdSetoran(), $this->daftarSetoran);
        $newId = $this->generateNextId('S-', $ids, 3);
        $setoran = new Setoran($newId, $p->getDompet());

        foreach ($rawItems as $item) {
            $idSampah = isset($item['idSampah']) ? (string) $item['idSampah'] : '';
            $rawBerat = $item['berat'] ?? '0';
            if (!is_numeric($rawBerat)) {
                throw new InvalidArgumentException('Berat sampah harus berupa angka yang valid.');
            }
            $sampah = $this->findSampahById($idSampah);
            if ($sampah === null) {
                throw new InvalidArgumentException('Jenis sampah tidak ditemukan: ' . $idSampah);
            }
            $detail = new DetailSetoran($sampah, (float) $rawBerat);
            $setoran->tambahDetail($detail);
            $this->logOop(
                'Polymorphism (' . $this->namaKelas($sampah) . '.hitungNilai)',
                'DetailSetoran mengunci subtotal ' . $sampah->getNamaSampah() . ' (' . (float) $rawBerat
                . ' kg) = ' . $this->formatRupiah($detail->getSubtotal())
            );
        }

        $p->setorSampah($setoran);
        array_unshift($this->daftarSetoran, $setoran);
        $this->logOop(
            'Composition (Setoran *-- DetailSetoran)',
            'Nasabah ' . $p->getNama() . ' mengajukan Setoran ' . $newId . ' (' . count($rawItems)
            . ' item) dengan status [DIAJUKAN].'
        );
        $this->simpanState();
        return $setoran;
    }

    public function timbangSetoran(?string $idPetugas, ?string $idSetoran): Setoran
    {
        $p = $this->findPenggunaById($idPetugas);
        if (!$p instanceof Petugas) {
            throw new InvalidArgumentException('Hanya Petugas yang dapat menimbang setoran.');
        }
        $setoran = $this->findSetoranById($idSetoran);
        if ($setoran === null) {
            throw new InvalidArgumentException('Setoran tidak ditemukan: ' . $idSetoran);
        }
        if ($setoran->getStatus() !== 'DIAJUKAN') {
            throw new InvalidArgumentException(
                'Hanya setoran berstatus DIAJUKAN yang dapat ditimbang (status saat ini: '
                . $setoran->getStatus() . ').'
            );
        }

        $p->timbangSampah($setoran);
        $this->logOop(
            'Petugas.timbangSampah -> Setoran.hitungTotal()',
            'Petugas ' . $p->getNama() . ' menimbang ' . $setoran->getIdSetoran()
            . ' -> Total: ' . $this->formatRupiah($setoran->getTotalNilai())
            . ', Poin: ' . $setoran->getTotalPoin() . ' (1 poin/Rp100) -> Status [DITIMBANG].'
        );
        $this->simpanState();
        return $setoran;
    }

    public function verifikasiSetoran(?string $idPetugas, ?string $idSetoran): Setoran
    {
        $p = $this->findPenggunaById($idPetugas);
        if (!$p instanceof Petugas) {
            throw new InvalidArgumentException('Hanya Petugas yang dapat memverifikasi setoran.');
        }
        $setoran = $this->findSetoranById($idSetoran);
        if ($setoran === null) {
            throw new InvalidArgumentException('Setoran tidak ditemukan: ' . $idSetoran);
        }
        if ($setoran->getStatus() !== 'DITIMBANG') {
            throw new InvalidArgumentException(
                'Hanya setoran berstatus DITIMBANG yang dapat diverifikasi (status saat ini: '
                . $setoran->getStatus() . ').'
            );
        }

        $berhasil = $p->verifikasiSetoran($setoran);
        if ($berhasil) {
            $this->logOop(
                'Petugas.verifikasiSetoran -> Dompet',
                'Setoran ' . $setoran->getIdSetoran() . ' melewati DIVERIFIKASI -> SELESAI. Dompet '
                . $setoran->getDompet()->getIdDompet() . ' bertambah +'
                . $this->formatRupiah($setoran->getTotalNilai()) . ' & +' . $setoran->getTotalPoin() . ' poin.'
            );
            $this->simpanState();
            return $setoran;
        }

        $this->logOop('Setoran.verifikasi()', 'Verifikasi menghasilkan status [' . $setoran->getStatus() . '].');
        $this->simpanState();
        if ($setoran->getStatus() === 'DITOLAK') {
            throw new InvalidArgumentException('Setoran ditolak otomatis karena total nilai = 0.');
        }
        throw new InvalidArgumentException('Verifikasi gagal. Status setoran: ' . $setoran->getStatus());
    }

    public function batalkanSetoran(?string $idSetoran, ?string $idPengguna): Setoran
    {
        $setoran = $this->findSetoranById($idSetoran);
        if ($setoran === null) {
            throw new InvalidArgumentException('Setoran tidak ditemukan: ' . $idSetoran);
        }
        $actor = $this->findPenggunaById($idPengguna);
        $actorName = $actor !== null
            ? ($this->namaKelas($actor) . ' ' . $actor->getNama())
            : 'Sistem';

        $setoran->batalkan();
        $this->logOop(
            'Setoran.batalkan()',
            $actorName . ' membatalkan/menolak setoran ' . $setoran->getIdSetoran()
            . ' -> Status berubah menjadi [DITOLAK].'
        );
        $this->simpanState();
        return $setoran;
    }

    // =====================================================================
    // Dompet & hadiah
    // =====================================================================

    public function tarikSaldo(
        ?string $idNasabah,
        float $jumlah,
        ?string $tipeMetode,
        ?string $field1,
        ?string $field2,
        ?string $field3
    ): void {
        $p = $this->findPenggunaById($idNasabah);
        if (!$p instanceof Nasabah) {
            throw new InvalidArgumentException('Hanya Nasabah yang dapat menarik saldo dompet.');
        }
        if ($jumlah <= 0) {
            throw new InvalidArgumentException('Jumlah penarikan harus lebih dari Rp0.');
        }

        if (strcasecmp((string) $tipeMetode, 'EWALLET') === 0) {
            if ($field1 === null || trim($field1) === '' || $field2 === null || trim($field2) === '') {
                throw new InvalidArgumentException('Penyedia E-Wallet dan Nomor HP Terdaftar wajib diisi.');
            }
            $metode = new EWallet(trim($field1), trim($field2));
        } else {
            if ($field1 === null || trim($field1) === '' || $field2 === null || trim($field2) === ''
                || $field3 === null || trim($field3) === '') {
                throw new InvalidArgumentException('Nama Bank, Nomor Rekening, dan Nama Pemilik wajib diisi.');
            }
            $metode = new TransferBank(trim($field1), trim($field2), trim($field3));
        }

        $biayaAdmin = $metode->hitungBiayaAdmin($jumlah);
        $totalPotong = $jumlah + $biayaAdmin;
        $saldoSebelum = $p->getDompet()->getSaldoRupiah();
        if ($saldoSebelum < $totalPotong) {
            throw new InvalidArgumentException(
                'Saldo tidak mencukupi! Butuh ' . $this->formatRupiah($totalPotong)
                . ' (Penarikan ' . $this->formatRupiah($jumlah) . ' + Biaya Admin ' . $this->formatRupiah($biayaAdmin)
                . '), sedangkan saldo Dompet Anda ' . $this->formatRupiah($saldoSebelum) . '.'
            );
        }

        if (!$p->tarikSaldo($jumlah, $metode)) {
            throw new InvalidArgumentException('Penarikan gagal diproses oleh ' . $metode->getNamaMetode() . '.');
        }

        $this->logOop(
            'Interface Polymorphism (MetodePenarikan -> ' . $this->namaKelas($metode) . ')',
            $p->getNama() . ' menarik ' . $this->formatRupiah($jumlah) . ' via ' . $metode->getNamaMetode()
            . ' (Biaya admin: ' . $this->formatRupiah($biayaAdmin) . '). Sisa saldo: '
            . $this->formatRupiah($p->getDompet()->getSaldoRupiah()) . '.'
        );
        $this->simpanState();
    }

    public function tukarPoin(?string $idNasabah, ?string $idHadiah): void
    {
        $p = $this->findPenggunaById($idNasabah);
        if (!$p instanceof Nasabah) {
            throw new InvalidArgumentException('Hanya Nasabah yang dapat menukar poin.');
        }
        $hadiah = $this->findHadiahById($idHadiah);
        if ($hadiah === null) {
            throw new InvalidArgumentException('Hadiah tidak ditemukan: ' . $idHadiah);
        }
        if (!$hadiah->cekStok()) {
            throw new InvalidArgumentException('Stok hadiah ' . $hadiah->getNamaHadiah() . ' sudah habis.');
        }
        if ($p->getDompet()->getTotalPoin() < $hadiah->getPoinDibutuhkan()) {
            throw new InvalidArgumentException(
                'Poin tidak mencukupi! Butuh ' . $hadiah->getPoinDibutuhkan()
                . ' poin, sedangkan poin Anda ' . $p->getDompet()->getTotalPoin() . ' poin.'
            );
        }

        if (!$p->tukarPoin($hadiah)) {
            throw new InvalidArgumentException('Penukaran poin gagal.');
        }

        $this->logOop(
            'Nasabah.tukarPoin -> Dompet & Hadiah',
            $p->getNama() . ' menukar ' . $hadiah->getPoinDibutuhkan() . ' poin dengan '
            . $hadiah->getNamaHadiah() . '. Sisa poin: ' . $p->getDompet()->getTotalPoin()
            . ', sisa stok: ' . $hadiah->getStok() . '.'
        );
        $this->simpanState();
    }

    // =====================================================================
    // Kelola sampah & hadiah (Admin)
    // =====================================================================

    public function tambahSampah(
        ?string $kategori,
        ?string $namaSampah,
        float $hargaPerKg,
        ?string $jenisSpesifik,
        bool $kondisiBool,
        float $kadarKemurnian
    ): Sampah {
        if ($namaSampah === null || trim($namaSampah) === ''
            || $jenisSpesifik === null || trim($jenisSpesifik) === '') {
            throw new InvalidArgumentException('Nama sampah dan jenis spesifik wajib diisi.');
        }
        if ($hargaPerKg < 0) {
            throw new InvalidArgumentException('Harga per kg tidak boleh negatif.');
        }

        $ids = array_map(fn (Sampah $s): string => $s->getIdSampah(), $this->daftarSampah);
        $newId = $this->generateNextId('SMP-', $ids, 2);
        $kat = $kategori !== null ? strtoupper(trim($kategori)) : 'PLASTIK';

        $baru = match ($kat) {
            'PLASTIK' => new Plastik($newId, trim($namaSampah), $hargaPerKg, trim($jenisSpesifik), $kondisiBool),
            'KERTAS' => new Kertas($newId, trim($namaSampah), $hargaPerKg, trim($jenisSpesifik), $kondisiBool),
            'LOGAM' => new Logam($newId, trim($namaSampah), $hargaPerKg, trim($jenisSpesifik), $kadarKemurnian),
            default => throw new InvalidArgumentException('Kategori sampah tidak valid: ' . $kategori),
        };

        $this->daftarSampah[] = $baru;
        $this->logOop(
            'Inheritance (' . $this->namaKelas($baru) . ' extends Sampah)',
            'Admin menambahkan jenis sampah baru: ' . $baru
        );
        $this->simpanState();
        return $baru;
    }

    public function ubahHargaSampah(?string $idSampah, float $hargaBaru): Sampah
    {
        $s = $this->findSampahById($idSampah);
        if ($s === null) {
            throw new InvalidArgumentException('Sampah tidak ditemukan: ' . $idSampah);
        }
        $hargaLama = $s->getHargaPerKg();
        $s->setHargaPerKg($hargaBaru);
        $this->logOop(
            'Encapsulation (Sampah.setHargaPerKg)',
            'Admin mengubah harga ' . $s->getNamaSampah() . ' dari ' . $this->formatRupiah($hargaLama)
            . ' menjadi ' . $this->formatRupiah($hargaBaru)
            . '/kg (setoran lama tetap terkunci pada subtotal DetailSetoran).'
        );
        $this->simpanState();
        return $s;
    }

    public function tambahHadiah(?string $namaHadiah, int $poinDibutuhkan, int $stok): Hadiah
    {
        if ($namaHadiah === null || trim($namaHadiah) === '') {
            throw new InvalidArgumentException('Nama hadiah wajib diisi.');
        }
        $ids = array_map(fn (Hadiah $h): string => $h->getIdHadiah(), $this->daftarHadiah);
        $newId = $this->generateNextId('H-', $ids, 2);
        $baru = new Hadiah($newId, trim($namaHadiah), $poinDibutuhkan, $stok);
        $this->daftarHadiah[] = $baru;
        $this->logOop('Admin.kelolaHadiah -> new Hadiah()', 'Admin menambahkan katalog hadiah baru: ' . $baru);
        $this->simpanState();
        return $baru;
    }

    public function tambahStokHadiah(?string $idHadiah, int $tambahan): Hadiah
    {
        $h = $this->findHadiahById($idHadiah);
        if ($h === null) {
            throw new InvalidArgumentException('Hadiah tidak ditemukan: ' . $idHadiah);
        }
        $h->tambahStok($tambahan);
        $this->logOop(
            'Encapsulation (Hadiah.tambahStok)',
            'Admin menambah stok ' . $h->getNamaHadiah() . ' sebanyak +' . $tambahan
            . ' -> Total stok: ' . $h->getStok() . '.'
        );
        $this->simpanState();
        return $h;
    }

    // =====================================================================
    // Pencarian
    // =====================================================================

    public function findPenggunaById(?string $id): ?Pengguna
    {
        if ($id === null || $id === '') {
            return null;
        }
        foreach ($this->daftarPengguna as $p) {
            if ($p->getIdPengguna() === $id) {
                return $p;
            }
        }
        return null;
    }

    public function findSampahById(?string $id): ?Sampah
    {
        if ($id === null || $id === '') {
            return null;
        }
        foreach ($this->daftarSampah as $s) {
            if ($s->getIdSampah() === $id) {
                return $s;
            }
        }
        return null;
    }

    public function findHadiahById(?string $id): ?Hadiah
    {
        if ($id === null || $id === '') {
            return null;
        }
        foreach ($this->daftarHadiah as $h) {
            if ($h->getIdHadiah() === $id) {
                return $h;
            }
        }
        return null;
    }

    public function findSetoranById(?string $id): ?Setoran
    {
        if ($id === null || $id === '') {
            return null;
        }
        foreach ($this->daftarSetoran as $s) {
            if ($s->getIdSetoran() === $id) {
                return $s;
            }
        }
        return null;
    }

    public function findPenyetor(Setoran $setoran): ?Nasabah
    {
        foreach ($this->daftarPengguna as $p) {
            if ($p instanceof Nasabah && in_array($setoran, $p->lihatRiwayat(), true)) {
                return $p;
            }
        }
        return null;
    }

    public function findVerifikator(Setoran $setoran): ?Petugas
    {
        foreach ($this->daftarPengguna as $p) {
            if ($p instanceof Petugas && in_array($setoran, $p->getDaftarSetoran(), true)) {
                return $p;
            }
        }
        return null;
    }

    // =====================================================================
    // Serialisasi state untuk frontend
    // =====================================================================

    public function toJsonPengguna(Pengguna $p): array
    {
        $data = [
            'idPengguna' => $p->getIdPengguna(),
            'nama' => $p->getNama(),
            'email' => $p->getEmail(),
            'noHp' => $p->getNoHp(),
            'role' => $this->namaRole($p),
            'className' => $this->namaKelas($p),
        ];

        if ($p instanceof Nasabah) {
            $d = $p->getDompet();
            $data['alamat'] = $p->getAlamat();
            $data['tanggalDaftar'] = $this->formatIsoDate($p->getTanggalDaftar());
            $data['jumlahSetoran'] = count($p->lihatRiwayat());
            $data['dompet'] = [
                'idDompet' => $d->getIdDompet(),
                'saldoRupiah' => $d->getSaldoRupiah(),
                'totalPoin' => $d->getTotalPoin(),
            ];
        } elseif ($p instanceof Petugas) {
            $data['idPetugas'] = $p->getIdPetugas();
            $data['wilayahTugas'] = $p->getWilayahTugas();
            $data['jumlahVerifikasi'] = count($p->getDaftarSetoran());
        } else {
            /** @var Admin $p */
            $data['levelAkses'] = $p->getLevelAkses();
        }

        return $data;
    }

    public function toJsonSampah(Sampah $s): array
    {
        $hargaEfektif = $s->hitungNilai(1.0);
        $data = [
            'idSampah' => $s->getIdSampah(),
            'namaSampah' => $s->getNamaSampah(),
            'hargaPerKg' => $s->getHargaPerKg(),
            'hargaEfektifPerKg' => $hargaEfektif,
            'poinPerKg' => $s->hitungPoin($hargaEfektif),
            'subkelas' => $this->namaKelas($s),
        ];

        if ($s instanceof Plastik) {
            $data['kategori'] = 'PLASTIK';
            $data['jenisSpesifik'] = $s->getJenisPlastik();
            $data['bersih'] = $s->isBersih();
            $data['faktorNilai'] = $s->isBersih() ? 1.0 : 0.8;
            $data['keteranganKondisi'] = $s->isBersih()
                ? 'Bersih (100% harga penuh)'
                : 'Kotor (Potongan 20% -> 80% harga)';
        } elseif ($s instanceof Kertas) {
            $data['kategori'] = 'KERTAS';
            $data['jenisSpesifik'] = $s->getJenisKertas();
            $data['kering'] = $s->isKering();
            $data['faktorNilai'] = $s->isKering() ? 1.0 : 0.7;
            $data['keteranganKondisi'] = $s->isKering()
                ? 'Kering (100% harga penuh)'
                : 'Basah/Lembab (Potongan 30% -> 70% harga)';
        } else {
            /** @var Logam $s */
            $data['kategori'] = 'LOGAM';
            $data['jenisSpesifik'] = $s->getJenisLogam();
            $data['kadarKemurnian'] = $s->getKadarKemurnian();
            $data['faktorNilai'] = $s->getKadarKemurnian();
            $data['keteranganKondisi'] = 'Kemurnian ' . (int) round($s->getKadarKemurnian() * 100)
                . '% (Nilai x ' . $s->getKadarKemurnian() . ')';
        }

        return $data;
    }

    public function toJsonHadiah(Hadiah $h): array
    {
        return [
            'idHadiah' => $h->getIdHadiah(),
            'namaHadiah' => $h->getNamaHadiah(),
            'poinDibutuhkan' => $h->getPoinDibutuhkan(),
            'stok' => $h->getStok(),
            'tersedia' => $h->cekStok(),
        ];
    }

    public function toJsonSetoran(Setoran $s): array
    {
        $penyetor = $this->findPenyetor($s);
        $verifikator = $this->findVerifikator($s);

        $estimasiSubtotal = 0.0;
        foreach ($s->getDaftarDetail() as $d) {
            $estimasiSubtotal += $d->getSubtotal();
        }

        $detail = [];
        foreach ($s->getDaftarDetail() as $d) {
            $sampah = $d->getSampah();
            $detail[] = [
                'idSampah' => $sampah->getIdSampah(),
                'namaSampah' => $sampah->getNamaSampah(),
                'subkelas' => $this->namaKelas($sampah),
                'berat' => $d->getBerat(),
                'subtotal' => $d->getSubtotal(),
                'hargaSaatIni' => $sampah->getHargaPerKg(),
            ];
        }

        return [
            'idSetoran' => $s->getIdSetoran(),
            'tanggalSetor' => $this->formatIsoDate($s->getTanggalSetor()),
            'status' => $s->getStatus(),
            'totalNilai' => $s->getTotalNilai(),
            'totalPoin' => $s->getTotalPoin(),
            'estimasiNilai' => $estimasiSubtotal,
            'estimasiPoin' => (int) ($estimasiSubtotal / 100),
            'idDompet' => $s->getDompet()->getIdDompet(),
            'idNasabah' => $penyetor?->getIdPengguna() ?? '',
            'namaNasabah' => $penyetor?->getNama() ?? '-',
            'idPetugas' => $verifikator?->getIdPengguna() ?? '',
            'kodePetugas' => $verifikator?->getIdPetugas() ?? '',
            'namaPetugas' => $verifikator?->getNama() ?? '-',
            'daftarDetail' => $detail,
        ];
    }

    public function buildFullState(): array
    {
        $this->tangkapOutputDomain();

        $cNasabah = 0;
        $cPetugas = 0;
        $cAdmin = 0;
        $totalSaldoDompet = 0.0;
        $totalPoinDompet = 0;

        foreach ($this->daftarPengguna as $p) {
            if ($p instanceof Nasabah) {
                $cNasabah++;
                $totalSaldoDompet += $p->getDompet()->getSaldoRupiah();
                $totalPoinDompet += $p->getDompet()->getTotalPoin();
            } elseif ($p instanceof Petugas) {
                $cPetugas++;
            } elseif ($p instanceof Admin) {
                $cAdmin++;
            }
        }

        $sDiajukan = 0;
        $sDitimbang = 0;
        $sDiverifikasi = 0;
        $sSelesai = 0;
        $sDitolak = 0;
        $totalNilaiSelesai = 0.0;
        $totalPoinSelesai = 0;

        foreach ($this->daftarSetoran as $s) {
            switch ($s->getStatus()) {
                case 'DIAJUKAN':
                    $sDiajukan++;
                    break;
                case 'DITIMBANG':
                    $sDitimbang++;
                    break;
                case 'DIVERIFIKASI':
                    $sDiverifikasi++;
                    break;
                case 'SELESAI':
                    $sSelesai++;
                    $totalNilaiSelesai += $s->getTotalNilai();
                    $totalPoinSelesai += $s->getTotalPoin();
                    break;
                case 'DITOLAK':
                    $sDitolak++;
                    break;
            }
        }

        return [
            'dbConnected' => $this->dbConnected,
            'dbStatusMessage' => $this->dbStatusMessage,
            'daftarPengguna' => array_map(fn (Pengguna $p): array => $this->toJsonPengguna($p), $this->daftarPengguna),
            'daftarSampah' => array_map(fn (Sampah $s): array => $this->toJsonSampah($s), $this->daftarSampah),
            'daftarHadiah' => array_map(fn (Hadiah $h): array => $this->toJsonHadiah($h), $this->daftarHadiah),
            'daftarSetoran' => array_map(fn (Setoran $s): array => $this->toJsonSetoran($s), $this->daftarSetoran),
            'laporan' => [
                'totalPengguna' => count($this->daftarPengguna),
                'jumlahNasabah' => $cNasabah,
                'jumlahPetugas' => $cPetugas,
                'jumlahAdmin' => $cAdmin,
                'jumlahJenisSampah' => count($this->daftarSampah),
                'jumlahHadiah' => count($this->daftarHadiah),
                'jumlahSetoran' => count($this->daftarSetoran),
                'statusDiajukan' => $sDiajukan,
                'statusDitimbang' => $sDitimbang,
                'statusDiverifikasi' => $sDiverifikasi,
                'statusSelesai' => $sSelesai,
                'statusDitolak' => $sDitolak,
                'totalNilaiSelesai' => $totalNilaiSelesai,
                'totalPoinSelesai' => $totalPoinSelesai,
                'totalSaldoBeredar' => $totalSaldoDompet,
                'totalPoinBeredar' => $totalPoinDompet,
            ],
            'oopLogs' => $this->oopLogs,
        ];
    }

    // =====================================================================
    // Utilitas
    // =====================================================================

    private function namaKelas(object $objek): string
    {
        $kelas = $objek::class;
        $posisi = strrpos($kelas, '\\');
        return $posisi === false ? $kelas : substr($kelas, $posisi + 1);
    }

    private function namaRole(Pengguna $pengguna): string
    {
        return strtoupper($this->namaKelas($pengguna));
    }

    private function formatRupiah(float $amount): string
    {
        return 'Rp' . number_format($amount, 0, ',', '.');
    }

    private function formatIsoDate(?DateTimeImmutable $date): string
    {
        return $date !== null ? $date->format('Y-m-d H:i') : '';
    }

    /**
     * @param string[] $existingIds
     */
    private function generateNextId(string $prefix, array $existingIds, int $digits): string
    {
        $max = 0;
        foreach ($existingIds as $id) {
            if (str_starts_with($id, $prefix)) {
                $suffix = substr($id, strlen($prefix));
                if (preg_match('/^\d+$/', $suffix) === 1) {
                    $max = max($max, (int) $suffix);
                }
            }
        }
        return $prefix . str_pad((string) ($max + 1), $digits, '0', STR_PAD_LEFT);
    }
}
