<?php

declare(strict_types=1);

namespace BankSampah\Pengguna;

use InvalidArgumentException;
use ReflectionClass;
use Stringable;

/**
 * Abstract class: kelas induk semua pengguna sistem (Nasabah, Petugas, Admin).
 * Menyimpan data akun yang sama untuk semua peran.
 */
abstract class Pengguna implements Stringable
{
    // ---- Atribut (sesuai diagram) ----
    private readonly string $idPengguna;
    private string $nama;
    private readonly string $email;
    private string $password;
    private readonly string $noHp;

    protected function __construct(
        string $idPengguna,
        string $nama,
        string $email,
        string $password,
        string $noHp
    ) {
        $this->idPengguna = $idPengguna;
        $this->nama = $nama;
        $this->email = $email;
        $this->password = $password;
        $this->noHp = $noHp;
    }

    // ---- Method (sesuai diagram) ----

    /** Mengembalikan true jika email dan password cocok dengan data akun ini. */
    public function login(string $email, string $password): bool
    {
        return $this->email === $email && $this->password === $password;
    }

    public function logout(): void
    {
        echo $this->nama . " telah logout.\n";
    }

    public function getNama(): string
    {
        return $this->nama;
    }

    public function setNama(string $nama): void
    {
        if (trim($nama) === '') {
            throw new InvalidArgumentException("Nama tidak boleh kosong.");
        }
        $this->nama = $nama;
    }

    /** Mengubah password jika password lama benar dan password baru minimal 6 karakter. */
    public function ubahPassword(string $lama, string $baru): bool
    {
        if ($this->password !== $lama || strlen($baru) < 6) {
            return false;
        }
        $this->password = $baru;
        return true;
    }

    /** Method abstrak: setiap peran menampilkan menu yang berbeda (polymorphism). */
    abstract public function tampilkanMenu(): void;

    // ---- Penunjang di luar diagram (dipakai penyimpanan/tampilan) ----

    /**
     * Password akun apa adanya. Hanya dipakai lapisan penyimpanan (storage/state.json)
     * agar akun bisa dimuat ulang, tidak pernah dikirim ke frontend.
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function getIdPengguna(): string
    {
        return $this->idPengguna;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getNoHp(): string
    {
        return $this->noHp;
    }

    public function __toString(): string
    {
        $shortName = (new ReflectionClass($this))->getShortName();
        return "[" . $shortName . "] " . $this->idPengguna . " | " . $this->nama . " | " . $this->email . " | " . $this->noHp;
    }
}
