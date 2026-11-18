<?php

declare(strict_types=1);

/**
 * Router untuk server bawaan PHP.
 *
 * Menjalankan: php -S 127.0.0.1:8081 -t public router.php
 *
 * Tugasnya dua:
 * 1. Menyajikan berkas frontend dari folder public/ (hasil salinan frontend versi Java).
 * 2. Melayani REST API di /api/* dengan kontrak respons yang sama seperti versi Java:
 *    { ok, message, user, state } untuk sukses dan { ok: false, error } untuk gagal.
 */

require __DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'autoload.php';

use BankSampah\Service\BankSampahService;

const FOLDER_PUBLIK = __DIR__ . DIRECTORY_SEPARATOR . 'public';

// Buffer output: method domain versi PHP masih mencetak ke STDOUT, dan cetakan itu
// ditangkap BankSampahService::tangkapOutputDomain() agar tidak merusak JSON.
ob_start();

$uri = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$metode = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if (str_starts_with($uri, '/api/')) {
    tanganiApi($uri, $metode);
    return;
}

if ($metode === 'OPTIONS') {
    http_response_code(204);
    return;
}

// Berkas statis (JS, CSS, gambar) disajikan apa adanya oleh server bawaan.
$berkas = FOLDER_PUBLIK . str_replace('/', DIRECTORY_SEPARATOR, $uri);
if ($uri !== '/' && is_file($berkas)) {
    return false;
}

// Sisa rute ditangani router sisi klien (History API), jadi selalu kirim index.html.
$index = FOLDER_PUBLIK . DIRECTORY_SEPARATOR . 'index.html';
if (!is_file($index)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Folder public/ belum berisi frontend. Jalankan: php tools/salin-frontend.php\n";
    return;
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
readfile($index);

// =====================================================================
// Penanganan API
// =====================================================================

function tanganiApi(string $path, string $metode): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');

    if ($metode === 'OPTIONS') {
        http_response_code(204);
        return;
    }

    try {
        $service = new BankSampahService();

        if ($metode === 'GET' && $path === '/api/state') {
            kirimJson(200, ['ok' => true, 'state' => $service->buildFullState()]);
            return;
        }

        $body = bacaBody();

        switch ($path) {
            case '/api/reload':
                $service->initialize();
                kirimSukses($service, 'Data berhasil disinkronkan ulang.', null);
                break;

            case '/api/login':
                $user = $service->login(teks($body['email'] ?? null), teks($body['password'] ?? null));
                kirimSukses(
                    $service,
                    'Selamat datang kembali, ' . $user->getNama() . '!',
                    $service->toJsonPengguna($user)
                );
                break;

            case '/api/logout':
                $service->logout(teks($body['idPengguna'] ?? null));
                kirimSukses($service, 'Anda telah keluar dari sesi.', null);
                break;

            case '/api/register':
                $baru = $service->registerNasabah(
                    teks($body['nama'] ?? null),
                    teks($body['email'] ?? null),
                    teks($body['password'] ?? null),
                    teks($body['noHp'] ?? null),
                    teks($body['alamat'] ?? null)
                );
                kirimSukses(
                    $service,
                    'Pendaftaran Nasabah ' . $baru->getIdPengguna() . ' berhasil! Dompet '
                    . $baru->getDompet()->getIdDompet() . ' telah dibuat.',
                    $service->toJsonPengguna($baru)
                );
                break;

            case '/api/pengguna/tambah':
                $baru = $service->tambahPenggunaOlehAdmin(
                    teks($body['role'] ?? null),
                    teks($body['nama'] ?? null),
                    teks($body['email'] ?? null),
                    teks($body['password'] ?? null),
                    teks($body['noHp'] ?? null),
                    teks($body['extra1'] ?? null),
                    teks($body['extra2'] ?? null)
                );
                kirimSukses(
                    $service,
                    'Pengguna baru (' . $service->toJsonPengguna($baru)['className'] . ' - '
                    . $baru->getIdPengguna() . ') berhasil ditambahkan.',
                    $service->toJsonPengguna($baru)
                );
                break;

            case '/api/pengguna/password':
                $service->ubahPassword(
                    teks($body['idPengguna'] ?? null),
                    teks($body['passwordLama'] ?? null),
                    teks($body['passwordBaru'] ?? null)
                );
                $updated = $service->findPenggunaById(teks($body['idPengguna'] ?? null));
                kirimSukses(
                    $service,
                    'Password berhasil diperbarui.',
                    $updated !== null ? $service->toJsonPengguna($updated) : null
                );
                break;

            case '/api/pengguna/nama':
                $service->ubahNama(teks($body['idPengguna'] ?? null), teks($body['namaBaru'] ?? null));
                $updated = $service->findPenggunaById(teks($body['idPengguna'] ?? null));
                kirimSukses(
                    $service,
                    'Nama profil berhasil diperbarui.',
                    $updated !== null ? $service->toJsonPengguna($updated) : null
                );
                break;

            case '/api/setoran/ajukan':
                $items = bacaItems($body['items'] ?? []);
                $setoran = $service->ajukanSetoran(teks($body['idNasabah'] ?? null), $items);
                $updated = $service->findPenggunaById(teks($body['idNasabah'] ?? null));
                kirimSukses(
                    $service,
                    'Setoran ' . $setoran->getIdSetoran()
                    . ' berhasil diajukan dengan status DIAJUKAN (menunggu penimbangan Petugas).',
                    $updated !== null ? $service->toJsonPengguna($updated) : null
                );
                break;

            case '/api/setoran/timbang':
                $setoran = $service->timbangSetoran(
                    teks($body['idPetugas'] ?? null),
                    teks($body['idSetoran'] ?? null)
                );
                $updated = $service->findPenggunaById(teks($body['idPetugas'] ?? null));
                kirimSukses(
                    $service,
                    'Setoran ' . $setoran->getIdSetoran()
                    . ' selesai ditimbang! Total nilai dan poin telah dihitung (status: DITIMBANG).',
                    $updated !== null ? $service->toJsonPengguna($updated) : null
                );
                break;

            case '/api/setoran/verifikasi':
                $setoran = $service->verifikasiSetoran(
                    teks($body['idPetugas'] ?? null),
                    teks($body['idSetoran'] ?? null)
                );
                $updated = $service->findPenggunaById(teks($body['idPetugas'] ?? null));
                kirimSukses(
                    $service,
                    'Setoran ' . $setoran->getIdSetoran()
                    . ' berhasil diverifikasi (SELESAI)! Saldo dan poin telah masuk ke Dompet Nasabah.',
                    $updated !== null ? $service->toJsonPengguna($updated) : null
                );
                break;

            case '/api/setoran/batalkan':
                $setoran = $service->batalkanSetoran(
                    teks($body['idSetoran'] ?? null),
                    teks($body['idPengguna'] ?? null)
                );
                $updated = $service->findPenggunaById(teks($body['idPengguna'] ?? null));
                kirimSukses(
                    $service,
                    'Setoran ' . $setoran->getIdSetoran() . ' telah dibatalkan/ditolak (status: DITOLAK).',
                    $updated !== null ? $service->toJsonPengguna($updated) : null
                );
                break;

            case '/api/dompet/tarik':
                $service->tarikSaldo(
                    teks($body['idNasabah'] ?? null),
                    angka($body['jumlah'] ?? 0),
                    teks($body['tipeMetode'] ?? null),
                    teks($body['field1'] ?? null),
                    teks($body['field2'] ?? null),
                    teks($body['field3'] ?? null)
                );
                $updated = $service->findPenggunaById(teks($body['idNasabah'] ?? null));
                kirimSukses(
                    $service,
                    'Penarikan saldo berhasil diproses!',
                    $updated !== null ? $service->toJsonPengguna($updated) : null
                );
                break;

            case '/api/hadiah/tukar':
                $service->tukarPoin(
                    teks($body['idNasabah'] ?? null),
                    teks($body['idHadiah'] ?? null)
                );
                $updated = $service->findPenggunaById(teks($body['idNasabah'] ?? null));
                kirimSukses(
                    $service,
                    'Penukaran poin hadiah berhasil!',
                    $updated !== null ? $service->toJsonPengguna($updated) : null
                );
                break;

            case '/api/sampah/tambah':
                $baru = $service->tambahSampah(
                    teks($body['kategori'] ?? null),
                    teks($body['namaSampah'] ?? null),
                    angka($body['hargaPerKg'] ?? 0),
                    teks($body['jenisSpesifik'] ?? null),
                    boolean($body['kondisi'] ?? true),
                    angka($body['kadarKemurnian'] ?? 1.0, 1.0)
                );
                kirimSukses(
                    $service,
                    'Jenis sampah baru (' . $baru->getIdSampah() . ' - ' . $baru->getNamaSampah()
                    . ') berhasil ditambahkan.',
                    null
                );
                break;

            case '/api/sampah/harga':
                $sampah = $service->ubahHargaSampah(
                    teks($body['idSampah'] ?? null),
                    angka($body['hargaBaru'] ?? 0)
                );
                kirimSukses($service, 'Harga ' . $sampah->getNamaSampah() . ' berhasil diperbarui.', null);
                break;

            case '/api/hadiah/tambah':
                $hadiah = $service->tambahHadiah(
                    teks($body['namaHadiah'] ?? null),
                    bulat($body['poinDibutuhkan'] ?? 0),
                    bulat($body['stok'] ?? 0)
                );
                kirimSukses(
                    $service,
                    'Hadiah baru (' . $hadiah->getIdHadiah() . ' - ' . $hadiah->getNamaHadiah()
                    . ') berhasil ditambahkan.',
                    null
                );
                break;

            case '/api/hadiah/stok':
                $hadiah = $service->tambahStokHadiah(
                    teks($body['idHadiah'] ?? null),
                    bulat($body['tambahan'] ?? 0)
                );
                kirimSukses(
                    $service,
                    'Stok hadiah ' . $hadiah->getNamaHadiah() . ' berhasil ditambah menjadi '
                    . $hadiah->getStok() . '.',
                    null
                );
                break;

            default:
                kirimJson(404, ['ok' => false, 'error' => 'Endpoint tidak ditemukan: ' . $path]);
        }
    } catch (InvalidArgumentException | RuntimeException $e) {
        kirimJson(400, ['ok' => false, 'error' => $e->getMessage()]);
    } catch (Throwable $e) {
        kirimJson(500, ['ok' => false, 'error' => 'Kesalahan sistem: ' . $e->getMessage()]);
    }
}

function kirimSukses(BankSampahService $service, string $message, ?array $user): void
{
    kirimJson(200, [
        'ok' => true,
        'message' => $message,
        'user' => $user,
        'state' => $service->buildFullState(),
    ]);
}

function kirimJson(int $status, array $payload): void
{
    http_response_code($status);
    // JSON_PRESERVE_ZERO_FRACTION menjaga angka desimal tetap ditulis 17220.0 seperti versi Java,
    // sehingga kedua versi menghasilkan keluaran JSON yang identik.
    $json = json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
    );
    echo $json === false ? '{"ok":false,"error":"Gagal menyusun JSON."}' : $json;
}

function bacaBody(): array
{
    $mentah = file_get_contents('php://input');
    if ($mentah === false || trim($mentah) === '') {
        return [];
    }
    $data = json_decode($mentah, true);
    return is_array($data) ? $data : [];
}

/**
 * items bisa dikirim sebagai array objek (frontend) atau string JSON (klien lain).
 */
function bacaItems(mixed $items): array
{
    if (is_string($items)) {
        $items = json_decode($items, true);
    }
    if (!is_array($items)) {
        return [];
    }
    $hasil = [];
    foreach ($items as $item) {
        if (is_array($item)) {
            $hasil[] = $item;
        }
    }
    return $hasil;
}

function teks(mixed $nilai): ?string
{
    if ($nilai === null) {
        return null;
    }
    return is_scalar($nilai) ? (string) $nilai : null;
}

function angka(mixed $nilai, float $bawaan = 0.0): float
{
    if ($nilai === null || $nilai === '') {
        return $bawaan;
    }
    if (!is_numeric($nilai)) {
        throw new InvalidArgumentException('Nilai numerik tidak valid: ' . teks($nilai));
    }
    return (float) $nilai;
}

function bulat(mixed $nilai): int
{
    if ($nilai === null || $nilai === '') {
        return 0;
    }
    if (!is_numeric($nilai)) {
        throw new InvalidArgumentException('Nilai numerik tidak valid: ' . teks($nilai));
    }
    return (int) $nilai;
}

function boolean(mixed $nilai): bool
{
    if (is_bool($nilai)) {
        return $nilai;
    }
    return filter_var($nilai, FILTER_VALIDATE_BOOLEAN);
}
