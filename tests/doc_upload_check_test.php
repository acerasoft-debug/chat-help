<?php
/* Belge dosyasi kontrolu — 2 Eylul 2026'da bir alici belgesini iki gun
 * yukleyemedi ve yol her hatada ayni cumleyi basiyordu. Artik her ret bir
 * SEBEP KODU tasiyor; bu test kodlarin dogru cikmasini ve sinirin sunucunun
 * ini degerinden okunmasini tutar.
 *
 * Fonksiyonlar auth.php'den kaynak duzeyinde alinir (dosyanin tamami oturum ve
 * dosya sistemi ister). move_uploaded_file gercek yukleme olmadan test
 * edilemez; burada yalnizca KARAR (check) ve METIN sinanir.
 */
$src = file_get_contents(__DIR__.'/../vestra/inc/auth.php');
foreach (['auth_doc_allowed_ext','auth_ini_bytes','auth_doc_max_bytes','auth_doc_file_check','auth_doc_error_text'] as $fn) {
    if (!preg_match('/^function '.preg_quote($fn,'/').'\(.*?^}/ms', $src, $m)) { echo "HATA: $fn auth.php'de bulunamadi\n"; exit(1); }
    eval($m[0]);
}

$ok=0; $fail=0;
$t = function (string $n, bool $c) use (&$ok,&$fail) {
    if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; }
};
$MB = 1024*1024;
$f = fn(int $err, int $size = 1000, string $name = 'doc.pdf') => ['error'=>$err, 'size'=>$size, 'name'=>$name, 'tmp_name'=>'/tmp/x', 'type'=>''];

echo "-- ini degerleri --\n";
$t("'2M' = 2 MiB",            auth_ini_bytes('2M') === 2*$MB);
$t("'8M' = 8 MiB",            auth_ini_bytes('8M') === 8*$MB);
$t("'512K' = 512 KiB",        auth_ini_bytes('512K') === 512*1024);
$t("'1G' = 1 GiB",            auth_ini_bytes('1G') === 1024*$MB);
$t("'16' = 16 bayt",          auth_ini_bytes('16') === 16);
$t("'' = 0 (sinir yok)",      auth_ini_bytes('') === 0);
$t("'-1' = 0 (sinirsiz)",     auth_ini_bytes('-1') === 0);
$t("kucuk harf '4m' de okunur", auth_ini_bytes('4m') === 4*$MB);

echo "-- tavan --\n";
$max = auth_doc_max_bytes();
$t("tavan 10 MB'i asmaz",                       $max <= 10*$MB);
$t("tavan 256 KB'in altina inmez",              $max >= 256*1024);
$up = auth_ini_bytes((string)ini_get('upload_max_filesize'));
$t("tavan upload_max_filesize'i asmaz (ini=".ini_get('upload_max_filesize').")", $up === 0 || $max <= $up);

echo "-- PHP hata kodlari --\n";
$t('UPLOAD_ERR_INI_SIZE -> size',    auth_doc_file_check($f(UPLOAD_ERR_INI_SIZE)) === 'size');
$t('UPLOAD_ERR_FORM_SIZE -> size',   auth_doc_file_check($f(UPLOAD_ERR_FORM_SIZE)) === 'size');
$t('UPLOAD_ERR_PARTIAL -> partial',  auth_doc_file_check($f(UPLOAD_ERR_PARTIAL)) === 'partial');
$t('UPLOAD_ERR_NO_FILE -> nofile',   auth_doc_file_check($f(UPLOAD_ERR_NO_FILE)) === 'nofile');
$t('UPLOAD_ERR_NO_TMP_DIR -> server',auth_doc_file_check($f(UPLOAD_ERR_NO_TMP_DIR)) === 'server');
$t('UPLOAD_ERR_CANT_WRITE -> server',auth_doc_file_check($f(UPLOAD_ERR_CANT_WRITE)) === 'server');
$t('UPLOAD_ERR_EXTENSION -> server', auth_doc_file_check($f(UPLOAD_ERR_EXTENSION)) === 'server');
$t('null dosya -> nofile',           auth_doc_file_check(null) === 'nofile');
$t("'error' anahtari yok -> nofile", auth_doc_file_check(['name'=>'a.pdf','size'=>5]) === 'nofile');

echo "-- boyut ve tur --\n";
$t('0 bayt -> empty',                          auth_doc_file_check($f(UPLOAD_ERR_OK, 0)) === 'empty');
$t('tavanin ustu -> size (cap=10MB)',          auth_doc_file_check($f(UPLOAD_ERR_OK, 10*$MB + 1), 10*$MB) === 'size');
$t('tam tavan -> gecer',                       auth_doc_file_check($f(UPLOAD_ERR_OK, 10*$MB), 10*$MB) === '');
$t('.exe -> type',                             auth_doc_file_check($f(UPLOAD_ERR_OK, 1000, 'virus.exe'), 10*$MB) === 'type');
$t('.docx -> type (Word kabul edilmiyor)',     auth_doc_file_check($f(UPLOAD_ERR_OK, 1000, 'scan.docx'), 10*$MB) === 'type');
$t('uzantisiz -> type',                        auth_doc_file_check($f(UPLOAD_ERR_OK, 1000, 'scan'), 10*$MB) === 'type');
$t('.PDF (buyuk harf) -> gecer',               auth_doc_file_check($f(UPLOAD_ERR_OK, 1000, 'Gewerbe.PDF'), 10*$MB) === '');
$t('.jpeg -> gecer',                           auth_doc_file_check($f(UPLOAD_ERR_OK, 1000, 'id.jpeg'), 10*$MB) === '');
$t('.webp -> gecer',                           auth_doc_file_check($f(UPLOAD_ERR_OK, 1000, 'id.webp'), 10*$MB) === '');
$t('.HEIC (iPhone) -> gecer',                  auth_doc_file_check($f(UPLOAD_ERR_OK, 1000, 'IMG_0412.HEIC'), 10*$MB) === '');
$t('.heif -> gecer',                           auth_doc_file_check($f(UPLOAD_ERR_OK, 1000, 'IMG_0412.heif'), 10*$MB) === '');
$t('cift uzanti scan.pdf.exe -> type',         auth_doc_file_check($f(UPLOAD_ERR_OK, 1000, 'scan.pdf.exe'), 10*$MB) === 'type');
$t('boyut hatasi turden ONCE (buyuk .exe -> size)', auth_doc_file_check($f(UPLOAD_ERR_OK, 99*$MB, 'x.exe'), 10*$MB) === 'size');

echo "-- metinler --\n";
foreach (['size','type','partial','empty','nofile','post','server','bilinmeyen'] as $c) {
    $txt = auth_doc_error_text($c);
    $t("'$c' icin metin var ve bir cikis yolu soyluyor", $txt !== '' && (stripos($txt, 'e-mail') !== false || stripos($txt, 'try again') !== false));
}
$t("'size' metni MB rakamini icerir",  preg_match('/\d+ MB/', auth_doc_error_text('size')) === 1);
$t("'post' metni MB rakamini icerir",  preg_match('/\d+ MB/', auth_doc_error_text('post')) === 1);
$t("'type' metni HEIC'i sayar",        stripos(auth_doc_error_text('type'), 'HEIC') !== false);

printf("\n%d ok, %d hata\n", $ok, $fail);
exit($fail ? 1 : 0);
