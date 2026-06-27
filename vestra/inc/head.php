<?php
/** VESTRA shared header — start session, member gate, nav. Set $PAGE before include. */
require_once __DIR__.'/i18n.php';
require_once __DIR__.'/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (isset($_GET['demo_member']))  { $_SESSION['member'] = true; }
if (isset($_GET['demo_signout'])) { auth_logout(); unset($_SESSION['member']); }
$AUTH_USER = auth_user();
$MEMBER = $AUTH_USER !== null || !empty($_SESSION['member']);
$BRAND  = 'VESTRA';
$PAGE   = $PAGE ?? $BRAND;
$ACC    = '#c9a86a';
$fav = 'data:image/svg+xml,' . rawurlencode("<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='7' fill='#0e0e11'/><path d='M9 10l7 13 7-13' fill='none' stroke='{$ACC}' stroke-width='2.6' stroke-linecap='round' stroke-linejoin='round'/></svg>");
?><!DOCTYPE html>
<html lang="<?= vlang() ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($PAGE) ?> — <?= $BRAND ?></title>
<link rel="icon" href="<?= $fav ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/inc/style.css">
</head>
<body>
<header>
  <div class="wrap"><nav>
    <a class="logo" href="/">
      <svg class="mark" viewBox="0 0 32 32" fill="none" aria-hidden="true">
        <rect x="1.2" y="1.2" width="29.6" height="29.6" rx="8" stroke="var(--acc)" stroke-width="1.4"/>
        <path d="M9 10l7 13 7-13" stroke="var(--acc)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      <span><?= $BRAND ?></span>
    </a>
    <div class="nav-links">
      <a href="/shop" class="<?= ($NAV ?? '')==='shop'?'on':'' ?>"><?= t('Catalog') ?></a>
      <a href="/groups" class="<?= ($NAV ?? '')==='groups'?'on':'' ?>"><?= t('Group buys') ?></a>
      <a href="/requests" class="hidem <?= ($NAV ?? '')==='requests'?'on':'' ?>"><?= t('Requests') ?></a>
      <a href="/faq" class="<?= ($NAV ?? '')==='faq'?'on':'' ?>"><?= t('FAQ') ?></a>
      <a href="/seller" class="<?= ($NAV ?? '')==='sell'?'on':'' ?>"><?= t('Sell') ?></a>
      <?php if ($MEMBER): ?>
        <?php $panel = ($AUTH_USER['type']??'buyer')==='seller' ? '/seller' : '/buyer'; ?>
        <a href="<?= $panel ?>" class="hidem <?= ($NAV ?? '')==='account'?'on':'' ?>"><?= t('Account') ?></a>
        <span class="memberpill">✓</span>
      <?php else: ?>
        <a href="/login"><?= t('Sign in') ?></a>
      <?php endif; ?>
      <?= vlang_switcher() ?>
      <a class="cartlink" href="/cart" aria-label="Cart">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 7h14l-1.2 11H6.2L5 7z"/><path d="M9 7a3 3 0 0 1 6 0"/></svg>
        <span class="badge" id="cartCount" style="display:none">0</span>
      </a>
    </div>
  </nav></div>
</header>
