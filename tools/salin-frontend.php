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
 * 1. Menambahkan src/theme-php.css (palet phtalo green) dan menyuntikkan link-nya ke index.html.
 *    Tujuannya agar versi PHP mudah dibedakan dari versi Java yang bernuansa coklat.
 * 2. Kedua langkah dijalankan ulang setiap skrip ini dipanggil, sehingga hasilnya selalu konsisten.
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

/**
 * Palet phtalo green. Nilai --brown-* milik versi Java diganti dengan tangga hijau
 * yang tingkat gelap-terangnya sepadan, sedangkan krem, status, dan tipografi dibiarkan
 * agar tata letak tetap sama. Aksen caramel digeser menjadi kuningan hangat.
 */
const TEMA_PHP = <<<'CSS'
/* ===== Tema PHP: Phtalo Green & Krem =====
   Berkas ini hanya ada di versi PHP dan disuntikkan otomatis oleh tools/salin-frontend.php.
   Versi Java tetap memakai palet coklat aslinya. Variabel di bawah menimpa palet induk,
   sehingga tidak ada satu pun aturan tata letak yang perlu diubah. */

:root {
  /* Tangga warna phtalo green (menggantikan --brown-*) */
  --brown-950: #08170F;
  --brown-900: #123524;
  --brown-800: #17432F;
  --brown-700: #1D5239;
  --brown-600: #246345;
  --brown-500: #3B7A5C;
  --brown-400: #5E9580;
  --brown-300: #8CB3A3;
  --brown-200: #BBD0C6;
  --brown-100: #DFE9E3;
  --brown-50: #F2F7F4;

  /* Aksen kuningan hangat (menggantikan --caramel-*) */
  --caramel-600: #9A7B4F;
  --caramel-500: #B08F63;
  --caramel-100: #F0E6D2;

  /* Krem tetap dipertahankan, hanya digeser sedikit ke arah hijau agar serasi */
  --surface-ivory: #FAF7F2;
  --surface-cream: #F1EFE4;
  --border-warm: #DCE4DE;
  --border-strong: #C3CFC7;

  /* Teks ikut disesuaikan dari coklat gelap ke hijau gelap.
     --text-muted digelapkan dari rencana awal agar tetap lolos kontras WCAG AA (>= 4.5:1)
     di atas permukaan putih maupun krem. */
  --text-primary: #14251D;
  --text-secondary: #40564B;
  --text-muted: #566A60;

  /* Bayangan memakai rona hijau, bukan coklat */
  --shadow-sm: 0 2px 6px rgba(18, 53, 36, 0.06);
  --shadow-md: 0 8px 24px rgba(18, 53, 36, 0.09);
  --shadow-lg: 0 16px 40px rgba(18, 53, 36, 0.14);
}
CSS;

$jumlah = salinFolder($asal, $tujuan);

// 1. Tulis berkas tema.
$tema = $tujuan . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'theme-php.css';
file_put_contents($tema, TEMA_PHP . PHP_EOL);

// 2. Suntikkan link tema ke index.html (idempoten: tidak menambah dua kali).
$index = $tujuan . DIRECTORY_SEPARATOR . 'index.html';
$html = (string) file_get_contents($index);
$penanda = '<link rel="stylesheet" href="/src/theme-php.css" />';

if (!str_contains($html, $penanda)) {
    $html = str_replace('</head>', "  {$penanda}\n</head>", $html);
    file_put_contents($index, $html);
}

echo "Selesai: {$jumlah} berkas frontend disalin ke public/\n";
echo "Tema phtalo green ditulis ke public/src/theme-php.css dan disuntikkan ke public/index.html\n";
