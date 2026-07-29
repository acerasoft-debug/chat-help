<?php
require __DIR__.'/inc/faq.php';
$cats = vestra_faq();
$cat  = $_GET['cat'] ?? 'about';
if(!isset($cats[$cat])) $cat = array_key_first($cats);
$PAGE = t('Frequently Asked Questions'); $NAV='faq';
$META = 'VESTRA FAQ — how B2B wholesale works on a KYC-verified marketplace: seller verification, invoice-based ordering, minimum orders, authenticity guarantees, shipping and returns for boutiques and multi-brand retailers.';
// FAQPage rich-result schema, built from the live Q&A so Google can show an FAQ snippet.
$_faqQ = [];
foreach ($cats as $c) foreach (($c['items'] ?? []) as $it) {
  $q = trim((string)($it['q'] ?? '')); $a = trim(strip_tags((string)($it['a'] ?? '')));
  if ($q === '' || $a === '') continue;
  $_faqQ[] = ['@type'=>'Question','name'=>$q,'acceptedAnswer'=>['@type'=>'Answer','text'=>$a]];
}
if ($_faqQ) $JSONLD = [['@context'=>'https://schema.org','@type'=>'FAQPage','mainEntity'=>$_faqQ]];
require __DIR__.'/inc/head.php';
?>
<div class="wrap">
  <div class="phead">
    <div class="crumbs"><a href="/">Home</a> · <?= t('FAQ') ?></div>
    <h1><?= t('Frequently Asked Questions') ?></h1>
    <p><?= t('Find your answer') ?>.</p>
  </div>

  <div class="legallayout">
    <aside class="legalnav">
      <?php foreach($cats as $k=>$c): ?>
        <a href="?cat=<?=$k?>" class="<?= $k===$cat?'on':'' ?>"><?=htmlspecialchars($c['title'])?></a>
      <?php endforeach; ?>
    </aside>
    <article class="legalbody">
      <h2><?=htmlspecialchars($cats[$cat]['title'])?></h2>
      <div class="faq-list">
      <?php foreach($cats[$cat]['items'] as $i=>$item): ?>
        <details class="faq-item" <?= $i===0?'open':'' ?>>
          <summary><?=htmlspecialchars($item['q'])?></summary>
          <div class="faq-ans"><?=nl2br(htmlspecialchars($item['a']))?></div>
        </details>
      <?php endforeach; ?>
      </div>
    </article>
  </div>
</div>
<?php require __DIR__.'/inc/foot.php';
