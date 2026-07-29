<?php
/**
 * VESTRA — minimal, dependency-free .xlsx (Office Open XML) writer with EMBEDDED photos.
 *
 * Excel does NOT render <img> tags from the "HTML table saved as .xls" trick — it drops
 * them — so the only reliable way to show product photos inside a spreadsheet is a real
 * .xlsx with drawing anchors pointing at embedded media. This builds one by hand with
 * ZipArchive + literal XML (no composer / PhpSpreadsheet needed).
 *
 * vestra_xlsx_with_photos($headers, $rows, $title):
 *   $headers : ['#','Brand','Product',...]  (last column is the photo column)
 *   $rows    : [ ['cells'=>['1','Lacoste',...], 'image'=>'/abs/path.jpg'|''], ... ]
 *   returns  : the .xlsx binary string, or '' on failure.
 * Each row's image (if the file exists + is png/jpeg) is drawn floating over the last cell.
 */

function vestra_xlsx_col(int $i): string { // 0-based -> A,B,...,Z,AA,...
  $s = ''; $i++;
  while ($i > 0) { $m = ($i - 1) % 26; $s = chr(65 + $m) . $s; $i = intdiv($i - 1, 26); }
  return $s;
}
function vestra_xlsx_esc(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function vestra_xlsx_with_photos(array $headers, array $rows, string $title = 'VESTRA'): string {
  if (!class_exists('ZipArchive')) return '';
  $ncol     = max(1, count($headers));
  $photoCol = $ncol - 1;                 // last column reserved for the photo
  $EMU      = 9525;                       // EMU per pixel @96dpi
  $imgBox   = 96;                         // max photo box (px) inside the cell
  $rowH     = 78;                         // data row height (pt) to fit the photo

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
    $bin = @file_get_contents($path);
    if ($bin === false || $bin === '') continue;
    // scale into the box, keep aspect
    $w = (int)$info[0]; $h = (int)$info[1];
    if ($w < 1 || $h < 1) continue;
    $scale = min($imgBox / $w, $imgBox / $h, 1.0);
    $dw = max(1, (int)round($w * $scale)); $dh = max(1, (int)round($h * $scale));
    $sheetRow = $ri + 2;                  // +1 header row, +1 for 1-based
    $imgs[$sheetRow] = ['bin' => $bin, 'ext' => $ext, 'w' => $dw, 'h' => $dh];
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
      $mediaFiles['xl/media/image' . $i . '.' . $ext] = $im['bin'];
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
    foreach ($mediaFiles as $name => $bin) $zip->addFromString($name, $bin);
  }
  $zip->close();
  $out = @file_get_contents($tmp);
  @unlink($tmp);
  return $out === false ? '' : $out;
}
