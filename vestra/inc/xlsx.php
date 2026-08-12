<?php
/**
 * VESTRA — minimal, dependency-free .xlsx (Office Open XML) writer with EMBEDDED photos.
 *
 * Excel does NOT render <img> tags from the "HTML table saved as .xls" trick — it drops
 * them — so the only reliable way to show product photos inside a spreadsheet is a real
 * .xlsx with drawing anchors pointing at embedded media. This builds one by hand with
 * ZipArchive + literal XML (no composer / PhpSpreadsheet needed).
 *
 * vestra_xlsx_with_photos_file($headers, $rows, $title):
 *   $headers : ['#','Brand','Product',...]  (last column is the photo column)
 *   $rows    : [ ['cells'=>['1','Lacoste',...], 'image'=>'/abs/path.jpg'|''], ... ]
 *   returns  : path to the finished .xlsx on disk (caller streams it, then deletes), or ''.
 * Each row's image (if the file exists + is png/jpeg) is drawn floating over the last cell.
 *
 * Returns a PATH, not the bytes. It used to return the whole file as a string, and with a
 * photo per row that was three copies of the catalogue in memory at once: every photo read
 * into $imgs, the same binaries again in $mediaFiles, then the finished workbook slurped
 * back. /catalog died on it -- "Allowed memory size of 134217728 bytes exhausted". Photos
 * now go into the zip straight from disk via addFile(), so peak memory no longer grows
 * with the size of the catalogue.
 */

function vestra_xlsx_col(int $i): string { // 0-based -> A,B,...,Z,AA,...
  $s = ''; $i++;
  while ($i > 0) { $m = ($i - 1) % 26; $s = chr(65 + $m) . $s; $i = intdiv($i - 1, 26); }
  return $s;
}
function vestra_xlsx_esc(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

/* Shrink a photo to what the sheet actually displays and return the temp copy's path,
   or '' to say "use the original".
 *
 * The cell shows a 96px box, but the workbook embedded each photo at full camera
 * resolution: the live catalogue came out at 127 MB, which a buyer on a phone cannot
 * download — nearly as broken as the 500 it replaced. Embedding at 2x the display box
 * is indistinguishable on screen, including on a high-DPI one.
 *
 * Falls back to the original on any doubt (no GD, decode failure, already small). A
 * bigger file is a far better outcome than a corrupt one. */
function vestra_xlsx_thumb(string $path, string $ext, int $maxPx, string $tmpDir): string {
  if (!function_exists('imagecreatefromjpeg') || !function_exists('imagescale')) return '';
  $info = @getimagesize($path);
  if (!$info || max((int)$info[0], (int)$info[1]) <= $maxPx) return '';   // already small enough
  $src = $ext === 'png' ? @imagecreatefrompng($path) : @imagecreatefromjpeg($path);
  if (!$src) return '';
  $w = imagesx($src); $h = imagesy($src);
  $nw = $w >= $h ? $maxPx : max(1, (int)round($w * $maxPx / $h));
  $dst = @imagescale($src, $nw);
  imagedestroy($src);
  if (!$dst) return '';
  $out = $tmpDir . '/vx_' . bin2hex(random_bytes(6)) . '.' . $ext;
  $ok  = $ext === 'png'
       ? @imagepng($dst, $out, 8)
       : @imagejpeg($dst, $out, 82);
  imagedestroy($dst);
  if (!$ok || !is_file($out) || filesize($out) < 1) { @unlink($out); return ''; }
  /* Only worth it if it actually got smaller. */
  if (filesize($out) >= filesize($path)) { @unlink($out); return ''; }
  return $out;
}

function vestra_xlsx_with_photos_file(array $headers, array $rows, string $title = 'VESTRA'): string {
  if (!class_exists('ZipArchive')) return '';
  $ncol     = max(1, count($headers));
  $photoCol = $ncol - 1;                 // last column reserved for the photo
  $EMU      = 9525;                       // EMU per pixel @96dpi
  $imgBox   = 96;                         // max photo box (px) inside the cell
  $rowH     = 78;                         // data row height (pt) to fit the photo
  $embedPx  = $imgBox * 2;                // embed at 2x the display box — crisp on high-DPI,
                                          // and nothing like the full-resolution original
  $tmpDir   = sys_get_temp_dir();
  $shrunk   = [];                         // temp thumbnails, removed once the zip is closed

  // ---- resolve images (validate type + dimensions) ---------------------------
  $imgs = [];   // [rowNumber(1-based sheet row) => ['bin'=>,'ext'=>,'w'=>,'h'=>]]
  foreach ($rows as $ri => $row) {
    $path = (string)($row['image'] ?? '');
    if ($path === '' || !is_file($path) || !is_readable($path)) continue;
    $info = @getimagesize($path);
    if (!$info) continue;
    $mime = $info['mime'] ?? '';
    $ext  = $mime === 'image/png' ? 'png' : ($mime === 'image/jpeg' ? 'jpeg' : '');
    if ($ext === '') continue;            // only png/jpeg embed reliably
    // Only the PATH is kept. getimagesize() above already proved the file is readable
    // and a real image, so there is nothing more to learn by loading it here — and
    // loading it here is exactly what exhausted memory once the catalogue grew.
    if (@filesize($path) < 1) continue;
    // scale into the box, keep aspect
    $w = (int)$info[0]; $h = (int)$info[1];
    if ($w < 1 || $h < 1) continue;
    $scale = min($imgBox / $w, $imgBox / $h, 1.0);
    $dw = max(1, (int)round($w * $scale)); $dh = max(1, (int)round($h * $scale));
    $sheetRow = $ri + 2;                  // +1 header row, +1 for 1-based
    /* Embed a copy sized for the cell, not the original camera file. */
    $thumb = vestra_xlsx_thumb($path, $ext, $embedPx, $tmpDir);
    if ($thumb !== '') { $shrunk[] = $thumb; $path = $thumb; }
    $imgs[$sheetRow] = ['path' => $path, 'ext' => $ext, 'w' => $dw, 'h' => $dh];
  }

  // ---- sheet1.xml ------------------------------------------------------------
  $colsXml = '<cols>';
  for ($c = 0; $c < $ncol; $c++) {
    $w = ($c === $photoCol) ? 16 : ($c === 0 ? 5 : 22);
    $colsXml .= '<col min="' . ($c + 1) . '" max="' . ($c + 1) . '" width="' . $w . '" customWidth="1"/>';
  }
  $colsXml .= '</cols>';

  $sheetData = '<sheetData>';
  // header row (styled bold, dark fill via s="1")
  $sheetData .= '<row r="1" ht="20" customHeight="1">';
  for ($c = 0; $c < $ncol; $c++) {
    $ref = vestra_xlsx_col($c) . '1';
    $sheetData .= '<c r="' . $ref . '" s="1" t="inlineStr"><is><t>' . vestra_xlsx_esc((string)($headers[$c] ?? '')) . '</t></is></c>';
  }
  $sheetData .= '</row>';
  foreach ($rows as $ri => $row) {
    $rn = $ri + 2;
    $hasImg = isset($imgs[$rn]);
    $sheetData .= '<row r="' . $rn . '"' . ($hasImg ? ' ht="' . $rowH . '" customHeight="1"' : '') . '>';
    $cells = $row['cells'] ?? [];
    for ($c = 0; $c < $ncol; $c++) {
      $ref = vestra_xlsx_col($c) . $rn;
      if ($c === $photoCol) { $sheetData .= '<c r="' . $ref . '" s="2"/>'; continue; } // photo overlays here
      $val = (string)($cells[$c] ?? '');
      $sheetData .= '<c r="' . $ref . '" s="2" t="inlineStr"><is><t>' . vestra_xlsx_esc($val) . '</t></is></c>';
    }
    $sheetData .= '</row>';
  }
  $sheetData .= '</sheetData>';

  $drawingRef = $imgs ? '<drawing r:id="rId1"/>' : '';
  $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
    . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
    . '<sheetPr><tabColor rgb="FF14110C"/></sheetPr>'
    . $colsXml . $sheetData . $drawingRef . '</worksheet>';

  // ---- drawing1.xml (one anchor per image) -----------------------------------
  $drawing = ''; $drawingRels = ''; $mediaFiles = []; $i = 0;
  if ($imgs) {
    $anchors = '';
    foreach ($imgs as $rn => $im) {
      $i++;
      $rowIdx = $rn - 1;               // 0-based
      $colIdx = $photoCol;             // 0-based
      $cx = $im['w'] * $EMU; $cy = $im['h'] * $EMU;
      $anchors .= '<xdr:oneCellAnchor>'
        . '<xdr:from><xdr:col>' . $colIdx . '</xdr:col><xdr:colOff>' . (4 * $EMU) . '</xdr:colOff>'
        . '<xdr:row>' . $rowIdx . '</xdr:row><xdr:rowOff>' . (4 * $EMU) . '</xdr:rowOff></xdr:from>'
        . '<xdr:ext cx="' . $cx . '" cy="' . $cy . '"/>'
        . '<xdr:pic>'
        . '<xdr:nvPicPr><xdr:cNvPr id="' . ($i + 1) . '" name="p' . $i . '"/><xdr:cNvPicPr/></xdr:nvPicPr>'
        . '<xdr:blipFill><a:blip xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" r:embed="rId' . $i . '"/>'
        . '<a:stretch><a:fillRect/></a:stretch></xdr:blipFill>'
        . '<xdr:spPr><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr>'
        . '</xdr:pic><xdr:clientData/></xdr:oneCellAnchor>';
      $ext = $im['ext'];
      $mediaFiles['xl/media/image' . $i . '.' . $ext] = $im['path'];
      $drawingRels .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/image' . $i . '.' . $ext . '"/>';
    }
    $drawing = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
      . '<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing"'
      . ' xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">' . $anchors . '</xdr:wsDr>';
  }

  // ---- static parts ----------------------------------------------------------
  $ctExtra = '';
  $hasPng = false; $hasJpeg = false;
  foreach ($imgs as $im) { if ($im['ext'] === 'png') $hasPng = true; else $hasJpeg = true; }
  if ($hasPng)  $ctExtra .= '<Default Extension="png" ContentType="image/png"/>';
  if ($hasJpeg) $ctExtra .= '<Default Extension="jpeg" ContentType="image/jpeg"/>';
  $ctDrawing = $imgs ? '<Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>' : '';

  $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
    . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
    . '<Default Extension="xml" ContentType="application/xml"/>'
    . $ctExtra
    . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
    . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
    . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
    . $ctDrawing
    . '</Types>';

  $rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
    . '</Relationships>';

  $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
    . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
    . '<sheets><sheet name="' . vestra_xlsx_esc(mb_substr($title, 0, 31)) . '" sheetId="1" r:id="rId1"/></sheets></workbook>';

  $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
    . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
    . '</Relationships>';

  // styles: s=0 default, s=1 header (bold white on dark), s=2 body (top-aligned, wrapped)
  $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
    . '<fonts count="3">'
    . '<font><sz val="11"/><name val="Calibri"/></font>'
    . '<font><b/><sz val="11"/><color rgb="FFD8BD86"/><name val="Calibri"/></font>'
    . '<font><sz val="11"/><color rgb="FF3A3428"/><name val="Calibri"/></font>'
    . '</fonts>'
    . '<fills count="3"><fill><patternFill patternType="none"/></fill>'
    . '<fill><patternFill patternType="gray125"/></fill>'
    . '<fill><patternFill patternType="solid"><fgColor rgb="FF14110C"/></patternFill></fill></fills>'
    . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
    . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
    . '<cellXfs count="3">'
    . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
    . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment vertical="center"/></xf>'
    . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
    . '</cellXfs>'
    . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
    . '</styleSheet>';

  // ---- zip it ----------------------------------------------------------------
  $tmp = tempnam(sys_get_temp_dir(), 'vxlsx');
  if ($tmp === false) return '';
  $zip = new ZipArchive();
  if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) { @unlink($tmp); return ''; }
  $zip->addFromString('[Content_Types].xml', $contentTypes);
  $zip->addFromString('_rels/.rels', $rootRels);
  $zip->addFromString('xl/workbook.xml', $workbook);
  $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
  $zip->addFromString('xl/styles.xml', $styles);
  $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
  if ($imgs) {
    $zip->addFromString('xl/worksheets/_rels/sheet1.xml.rels',
      '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
      . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
      . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/>'
      . '</Relationships>');
    $zip->addFromString('xl/drawings/drawing1.xml', $drawing);
    $zip->addFromString('xl/drawings/_rels/drawing1.xml.rels',
      '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
      . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $drawingRels . '</Relationships>');
    /* addFile, addFromString'in aksine dosyayi simdi okumaz -- zip kapanirken
       diskten akitir. Katalog buyudukce tepe bellek artik buyumuyor. */
    foreach ($mediaFiles as $name => $src) $zip->addFile($src, $name);
  }
  $closed = $zip->close();
  /* Thumbnails are read during close(), so they can only go afterwards. */
  foreach ($shrunk as $f) @unlink($f);
  if (!$closed) { @unlink($tmp); return ''; }
  /* Biten dosyayi string'e OKUMUYORUZ: cagiran readfile() ile akitip siliyor. */
  return is_file($tmp) && filesize($tmp) > 0 ? $tmp : '';
}

/* ── Shared export helpers ─────────────────────────────────────────────────
   Both catalogue exports need these. They lived in catalog.php, which meant a second
   exporter could not use them without copying -- and copying a path helper is how two
   exports end up disagreeing about where photos are. */
/* A never-empty identification code for a row: variant article code → product SKU →
   uppercased id. Lets every product/colour carry a code buyers can reference. */
function vestra_export_code(array $p, array $v = []): string {
    $art = trim((string)($v['art'] ?? ''));
    if ($art !== '') return $art;
    $sku = trim((string)($p['sku'] ?? ''));
    if ($sku !== '') return $sku;
    $id = trim((string)($p['id'] ?? ''));
    return $id !== '' ? strtoupper($id) : '';
}
/* Resolve a web image path ("/uploads/..") to a local file for embedding, or '' if absent.
   dirname(__DIR__), NOT __DIR__: this used to live in catalog.php at the web root, where
   __DIR__ was that root. From inc/ the same expression would resolve to inc/uploads/... ,
   which exists nowhere -- every photo would vanish from every export without one error. */
function vestra_export_local(string $img): string {
    $img = trim($img);
    if ($img === '') return '';
    /* A photograph stored as "https://vestrasales.com/uploads/x.jpg" is the same file on
       this disk as "/uploads/x.jpg", but only the second form used to resolve — the first
       returned '' and the row went out photograph-less, silently. Sellers enter both
       forms, so the host is stripped rather than the entry being called wrong. */
    if (preg_match('~^https?://~i', $img)) {
        $path = (string)parse_url($img, PHP_URL_PATH);
        if ($path === '') return '';
        $img = '/'.ltrim(rawurldecode($path), '/');
    }
    if ($img[0] !== '/') return '';
    $c = dirname(__DIR__).$img;
    return is_file($c) ? $c : '';
}
