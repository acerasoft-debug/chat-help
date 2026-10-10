<?php
/**
 * Favicon / marka işareti — satır içi SVG.
 * Ayrı bir .ico dosyası tutmak yerine burada üretiyoruz: tek kaynak, tek renk
 * tanımı, deploy'da senkron sorunu yok.
 */

declare(strict_types=1);

$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'
    . '<rect width="64" height="64" rx="6" fill="#000000"/>'
    // S harfi (SARVESTO): tek kalem darbesi, koseli, ust ucu bir noktayla
    // kapatilmis — eski M isaretiyle ayni dil.
    . '<path d="M48 18 L16 18 L16 32 L48 32 L48 46 L16 46" fill="none" stroke="#ffffff" stroke-width="4.4"'
    . ' stroke-linecap="square" stroke-linejoin="miter"/>'
    . '<circle cx="48" cy="18" r="3.4" fill="#ffffff"/>'
    . '</svg>';

header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: public, max-age=604800');
echo $svg;
