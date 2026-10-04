<?php
/**
 * ChatHelp — apply-seo (CH_SEO) — tam teknik SEO paketi.
 *  index.php <head>: cok dilli (DE/TR/EN/FR/ES/IT, $geo'ya gore) optimize title +
 *  meta description/keywords, robots, canonical, Open Graph, Twitter Card,
 *  JSON-LD (Organization + WebApplication + FAQPage). <html lang> dinamik.
 *  Ayrica kok dizine robots.txt + sitemap.xml yazar.
 *  Byte-exact str_replace (count-guarded) + php -l dogrulama.
 * KULLANIM: pull2.php?key=...&files=apply-seo.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-seo BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_SEO')!==false) exit("Zaten ekli (CH_SEO).\n");

/* ── ADIM 1: <html lang="de"> dinamik ── */
$htmlOld = '<html lang="de">';
$htmlNew = '<html lang="<?= htmlspecialchars($geo[\'lang\'] ?? \'de\') ?>"><!--CH_SEO-->';
$ch = substr_count($src,$htmlOld);
if ($ch===1) { $src = str_replace($htmlOld,$htmlNew,$src); echo "  ✓ ADIM 1: <html lang> dinamik\n"; }
else { echo "  ⚠ ADIM 1 atlandi: <html lang=\"de\"> $ch kez\n"; }

/* ── ADIM 2: <title> -> tam SEO blogu ── */
$titleOld = '<title>Chat-Help — Legal Documents Europe</title>';
$c = substr_count($src,$titleOld);
if ($c!==1) exit("HATA: <title> anchor $c kez (1 bekleniyordu). index DEGISTIRILMEDI.\n");

$seoBlock = <<<'SEOPHP'
<?php /* CH_SEO — cok dilli teknik SEO */
$__seo = [
  'de'=>['t'=>'ChatHelp – Rechtsdokumente per KI: Kündigung, Widerspruch & Verträge | Europa','d'=>'Erstellen Sie rechtssichere Dokumente in Minuten: Kündigungen, Widersprüche, Mietrecht, Verträge & Bewerbungen. KI-gestützt, DSGVO-konform – für DE, AT, CH und ganz Europa.','k'=>'Rechtsdokumente, Kündigung schreiben, Widerspruch einlegen, Mietrecht, Vertrag erstellen, Musterbrief, Bewerbung, KI Rechtshilfe, DSGVO'],
  'tr'=>['t'=>'ChatHelp – Yapay Zeka ile Dilekçe, İtiraz ve Sözleşme Oluşturucu | Avrupa','d'=>'Dakikalar içinde hukuki belge oluşturun: dilekçe, itiraz, kira, sözleşme ve başvuru. Yapay zeka destekli, GDPR/KVKK uyumlu – Türkiye ve tüm Avrupa için.','k'=>'dilekçe örneği, itiraz dilekçesi, kira sözleşmesi, iş sözleşmesi, hukuki belge, yapay zeka avukat, başvuru, CV'],
  'en'=>['t'=>'ChatHelp – AI Legal Documents: Cancellations, Appeals & Contracts | Europe','d'=>'Create legally sound documents in minutes: cancellations, appeals, tenancy, contracts & job applications. AI-powered, GDPR-compliant – for the UK and all of Europe.','k'=>'legal documents, cancellation letter, appeal, tenancy agreement, contract template, cover letter, AI legal help, GDPR'],
  'fr'=>['t'=>'ChatHelp – Documents juridiques par IA : résiliations, contrats, recours | Europe','d'=>'Créez des documents juridiques en quelques minutes : résiliations, recours, bail, contrats et candidatures. Assisté par IA, conforme RGPD – pour toute l\'Europe.','k'=>'modèle de lettre, résiliation, recours, bail, contrat, candidature, aide juridique IA, RGPD'],
  'es'=>['t'=>'ChatHelp – Documentos legales con IA: cancelaciones, contratos, recursos | Europa','d'=>'Cree documentos legales en minutos: cancelaciones, recursos, alquiler, contratos y candidaturas. Con IA, conforme al RGPD – para España y toda Europa.','k'=>'documentos legales, carta de baja, recurso, contrato de alquiler, contrato, candidatura, ayuda legal IA, RGPD'],
  'it'=>['t'=>'ChatHelp – Documenti legali con IA: disdette, contratti, ricorsi | Europa','d'=>'Crea documenti legali in pochi minuti: disdette, ricorsi, locazione, contratti e candidature. Basato su IA, conforme al GDPR – per l\'Italia e tutta l\'Europa.','k'=>'documenti legali, disdetta, ricorso, contratto di locazione, contratto, candidatura, aiuto legale IA, GDPR'],
];
$__l = (isset($geo['lang']) && isset($__seo[$geo['lang']])) ? $geo['lang'] : 'de';
$__s = $__seo[$__l];
$__host = $_SERVER['HTTP_HOST'] ?? 'chat-help.com';
$__base = 'https://'.$__host;
$__url  = $__base.'/chat/';
$__img  = $__base.'/chat/og-image.jpg';
$__faq = [
  'de'=>[
    ['Was ist ChatHelp?','ChatHelp erstellt mit KI rechtssichere Dokumente wie Kündigungen, Widersprüche, Verträge und Bewerbungen – für Deutschland, Österreich, die Schweiz und ganz Europa.'],
    ['Ist ChatHelp DSGVO-konform?','Ja. Alle Daten werden auf EU-Servern verarbeitet und ChatHelp ist vollständig DSGVO-konform.'],
    ['Welche Dokumente kann ich erstellen?','Kündigungen, Widersprüche, Mietrecht-Schreiben, Verträge, Bewerbungen, Lebensläufe und viele weitere rechtliche Dokumente.'],
    ['Was kostet ChatHelp?','Es gibt drei Pläne: Basic (13,99 €/Monat), Pro (29,99 €/Monat) und Elite (99,99 €/Monat). Jederzeit kündbar.'],
  ],
  'tr'=>[
    ['ChatHelp nedir?','ChatHelp, yapay zeka ile dilekçe, itiraz, sözleşme ve başvuru gibi hukuki belgeler oluşturur – Türkiye ve tüm Avrupa için.'],
    ['ChatHelp GDPR/KVKK uyumlu mu?','Evet. Tüm veriler AB sunucularında işlenir ve ChatHelp tamamen GDPR uyumludur.'],
    ['Hangi belgeleri oluşturabilirim?','Dilekçe, itiraz, kira sözleşmesi, iş sözleşmesi, başvuru, CV ve daha birçok hukuki belge.'],
    ['ChatHelp ücreti nedir?','Üç plan var: Basic (13,99 €/ay), Pro (29,99 €/ay), Elite (99,99 €/ay). İstediğiniz zaman iptal edebilirsiniz.'],
  ],
  'en'=>[
    ['What is ChatHelp?','ChatHelp uses AI to create legally sound documents such as cancellations, appeals, contracts and job applications – for the UK and all of Europe.'],
    ['Is ChatHelp GDPR-compliant?','Yes. All data is processed on EU servers and ChatHelp is fully GDPR-compliant.'],
    ['Which documents can I create?','Cancellations, appeals, tenancy letters, contracts, job applications, CVs and many more legal documents.'],
    ['How much does ChatHelp cost?','Three plans: Basic (EUR 13.99/month), Pro (EUR 29.99/month), Elite (EUR 99.99/month). Cancel anytime.'],
  ],
];
$__fl = isset($__faq[$__l]) ? $__l : 'de';
$__faqEnt = [];
foreach($__faq[$__fl] as $qa){ $__faqEnt[] = ['@type'=>'Question','name'=>$qa[0],'acceptedAnswer'=>['@type'=>'Answer','text'=>$qa[1]]]; }
$__ld = ['@context'=>'https://schema.org','@graph'=>[
  ['@type'=>'Organization','@id'=>$__base.'/#org','name'=>'ChatHelp','legalName'=>'Acerasoft LLC','url'=>$__base.'/','logo'=>$__img,'description'=>$__s['d']],
  ['@type'=>'WebApplication','name'=>'ChatHelp','url'=>$__url,'applicationCategory'=>'BusinessApplication','operatingSystem'=>'Web, Android','inLanguage'=>['de','tr','en','fr','es','it'],'publisher'=>['@id'=>$__base.'/#org'],'offers'=>[
    ['@type'=>'Offer','name'=>'Basic','price'=>'13.99','priceCurrency'=>'EUR','category'=>'subscription'],
    ['@type'=>'Offer','name'=>'Pro','price'=>'29.99','priceCurrency'=>'EUR','category'=>'subscription'],
    ['@type'=>'Offer','name'=>'Elite','price'=>'99.99','priceCurrency'=>'EUR','category'=>'subscription'],
  ]],
  ['@type'=>'FAQPage','mainEntity'=>$__faqEnt],
]];
?>
<title><?= htmlspecialchars($__s['t']) ?></title>
<meta name="description" content="<?= htmlspecialchars($__s['d']) ?>">
<meta name="keywords" content="<?= htmlspecialchars($__s['k']) ?>">
<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
<meta name="author" content="Acerasoft LLC">
<meta name="publisher" content="Acerasoft LLC">
<meta name="theme-color" content="#0d0d1c">
<link rel="canonical" href="<?= htmlspecialchars($__url) ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="ChatHelp">
<meta property="og:title" content="<?= htmlspecialchars($__s['t']) ?>">
<meta property="og:description" content="<?= htmlspecialchars($__s['d']) ?>">
<meta property="og:url" content="<?= htmlspecialchars($__url) ?>">
<meta property="og:locale" content="<?= htmlspecialchars($__l) ?>">
<meta property="og:image" content="<?= htmlspecialchars($__img) ?>">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= htmlspecialchars($__s['t']) ?>">
<meta name="twitter:description" content="<?= htmlspecialchars($__s['d']) ?>">
<meta name="twitter:image" content="<?= htmlspecialchars($__img) ?>">
<link rel="alternate" hreflang="x-default" href="<?= htmlspecialchars($__url) ?>">
<script type="application/ld+json"><?= json_encode($__ld, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?></script>
SEOPHP;

$src = str_replace($titleOld,$seoBlock,$src);
echo "  ✓ ADIM 2: <title> -> tam SEO blogu (cok dilli)\n";

/* ── LINT + YAZ ── */
$tmp = tempnam(sys_get_temp_dir(),'seo').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-seo-'.date('Ymd-His'), (string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if ($w===false || $w<strlen($src)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_SEO')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_SEO diskte (".strlen($chk)." bayt)\n";

/* ── ADIM 3: robots.txt + sitemap.xml (kok dizin) ── */
$root = dirname(__DIR__);
$today = date('Y-m-d');
$robots = "User-agent: *\nAllow: /\n"
  ."Disallow: /chat/api.php\nDisallow: /chat/auth.php\nDisallow: /chat/fall-api.php\n"
  ."Disallow: /chat/intake-api.php\nDisallow: /chat/stripe-checkout.php\nDisallow: /chat/stripe-webhook.php\n"
  ."Disallow: /chat/pull2.php\nDisallow: /chat/admin.php\nDisallow: /chat/admin-users.php\nDisallow: /chat/yonetim.php\n"
  ."\nSitemap: https://chat-help.com/sitemap.xml\n";
$sitemap = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
  .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n"
  ."  <url><loc>https://chat-help.com/</loc><lastmod>$today</lastmod><changefreq>weekly</changefreq><priority>1.0</priority></url>\n"
  ."  <url><loc>https://chat-help.com/chat/</loc><lastmod>$today</lastmod><changefreq>weekly</changefreq><priority>0.9</priority></url>\n"
  ."</urlset>\n";
$rok = @file_put_contents($root.'/robots.txt',$robots);
$sok = @file_put_contents($root.'/sitemap.xml',$sitemap);
echo "\n  robots.txt (kok): ".($rok!==false?"YAZILDI ✓ ($rok b)":"✗ YAZILAMADI (izin?)")."\n";
echo "  sitemap.xml (kok): ".($sok!==false?"YAZILDI ✓ ($sok b)":"✗ YAZILAMADI (izin?)")."\n";
/* yazilmazsa chat/ altina yedek */
if($rok===false){ $r2=@file_put_contents(__DIR__.'/robots.txt',$robots); echo "  robots.txt (chat/): ".($r2!==false?"YAZILDI ✓":"✗")."\n"; }
if($sok===false){ $s2=@file_put_contents(__DIR__.'/sitemap.xml',$sitemap); echo "  sitemap.xml (chat/): ".($s2!==false?"YAZILDI ✓":"✗")."\n"; }

echo "\n✓ SEO tamam: cok dilli title/description, OG/Twitter, JSON-LD (Org+App+FAQ), canonical, robots+sitemap.\n";
echo "  NOT: 1200x630 paylasim gorseli /chat/og-image.jpg olarak eklenirse sosyal onizleme tamamlanir.\n";
