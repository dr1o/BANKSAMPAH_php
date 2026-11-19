<?php

declare(strict_types=1);

/**
 * Menyalin frontend versi Java ke folder public/ milik versi PHP, lalu menyesuaikannya.
 *
 * Frontend Java ditulis sebagai ES module murni dengan path relatif (/src/... dan /api/...),
 * sehingga bisa disajikan apa adanya oleh server PHP. Berkat ini tampilan kedua versi
 * identik tanpa menulis ulang UI dan tanpa mengubah berkas milik versi Java.
 *
 * Penyesuaian khusus PHP:
 * 1. Menyalin tools/theme-php.css menjadi public/src/theme-php.css dan menyuntikkan link-nya
 *    ke index.html. Tema ini membuat tampilan PHP lebih datar dan administratif.
 * 2. Menghapus tautan Google Fonts dari index.html karena tema PHP memakai font sistem.
 * 3. Semua langkah dijalankan ulang setiap skrip ini dipanggil, sehingga hasilnya konsisten.
 *
 * Jalankan: php tools/salin-frontend.php
 */

$asal = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'BankSampah_KEL03_PBO' . DIRECTORY_SEPARATOR . 'frontend';
$tujuan = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'public';

if (!is_dir($asal)) {
    fwrite(STDERR, "Folder frontend Java tidak ditemukan: {$asal}\n");
    fwrite(STDERR, "Letakkan kedua repo berdampingan di folder yang sama.\n");
    exit(1);
}

/**
 * Menyalin isi folder secara rekursif.
 */
function salinFolder(string $asal, string $tujuan): int
{
    if (!is_dir($tujuan) && !mkdir($tujuan, 0775, true) && !is_dir($tujuan)) {
        throw new RuntimeException("Gagal membuat folder: {$tujuan}");
    }

    $jumlah = 0;
    $item = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($asal, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($item as $berkas) {
        /** @var SplFileInfo $berkas */
        $relatif = substr($berkas->getPathname(), strlen($asal) + 1);
        $target = $tujuan . DIRECTORY_SEPARATOR . $relatif;

        if ($berkas->isDir()) {
            if (!is_dir($target)) {
                mkdir($target, 0775, true);
            }
            continue;
        }

        copy($berkas->getPathname(), $target);
        $jumlah++;
    }

    return $jumlah;
}

$temaSumber = __DIR__ . DIRECTORY_SEPARATOR . 'theme-php.css';
if (!is_file($temaSumber)) {
    fwrite(STDERR, "Berkas tema tidak ditemukan: {$temaSumber}\n");
    exit(1);
}

$jumlah = salinFolder($asal, $tujuan);

// 1. Salin tema datar versi PHP (sumbernya tools/theme-php.css).
$tema = $tujuan . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'theme-php.css';
copy($temaSumber, $tema);

// 2. Hapus tautan Google Fonts bawaan frontend Java; tema PHP memakai font sistem.
$index = $tujuan . DIRECTORY_SEPARATOR . 'index.html';
$html = (string) file_get_contents($index);
$html = (string) preg_replace(
    [
        '~<link\s+rel="preconnect"[^>]*>\s*~i',
        '~<link[^>]*fonts\.googleapis\.com[^>]*>\s*~i',
    ],
    '',
    $html
);

// 3. Suntikkan link tema ke index.html (idempoten: tidak menambah dua kali).
$penanda = '<link rel="stylesheet" href="/src/theme-php.css" />';
if (!str_contains($html, $penanda)) {
    $html = str_replace('</head>', "  {$penanda}\n</head>", $html);
}
file_put_contents($index, $html);

echo "Selesai: {$jumlah} berkas frontend disalin ke public/\n";
echo "Tema datar disalin ke public/src/theme-php.css dan disuntikkan ke public/index.html\n";
