<?php
require __DIR__.'/inc/legal.php';
$docs = vestra_legal();
$doc = $_GET['doc'] ?? 'terms';
if(!isset($docs[$doc])) $doc='terms';
$PAGE = strip_tags($docs[$doc]['title']); $NAV='legal';
require __DIR__.'/inc/head.php';
?>
<div class="wrap">
  <div class="phead"><div class="crumbs"><a href="/">Home</a> · Legal</div>
    <h1>Legal &amp; policies</h1>
    <p>Transparency by design. These documents govern the use of VESTRA.</p>
  </div>

  <div class="legallayout">
    <aside class="legalnav">
      <?php foreach($docs as $k=>$d): ?>
        <a href="?doc=<?=$k?>" class="<?= $k===$doc?'on':'' ?>"><?=$d['title']?></a>
      <?php endforeach; ?>
    </aside>
    <article class="legalbody">
      <h2><?=$docs[$doc]['title']?></h2>
      <div class="legalmeta">Last updated: 26 June 2026 · Please have a US+EU lawyer review before relying on these documents</div>
      <?=$docs[$doc]['html']?>
    </article>
  </div>
</div>
<?php require __DIR__.'/inc/foot.php';
