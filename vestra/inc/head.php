<?php
/** VESTRA shared header — start session, member gate, nav. Set $PAGE before include. */
if (session_status() === PHP_SESSION_NONE) session_start();
if (isset($_GET['demo_member']))  { $_SESSION['member'] = true; }
if (isset($_GET['demo_signout'])) { unset($_SESSION['member']); }
$MEMBER = !empty($_SESSION['member']);
$BRAND  = 'VESTRA';
$PAGE   = $PAGE ?? $BRAND;
$ACC    = '#c9a86a';
$fav = 'data:image/svg+xml,' . rawurlencode("<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='7' fill='#0e0e11'/><path d='M9 10l7 13 7-13' fill='none' stroke='{$ACC}' stroke-width='2.6' stroke-linecap='round' stroke-linejoin='round'/></svg>");
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($PAGE) ?> — <?= $BRAND ?></title>
<link rel="icon" href="<?= $fav ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="inc/style.css">
</head>
<body>
<header>
  <div class="wrap"><nav>
    <a class="logo" href="index.php">
      <svg class="mark" viewBox="0 0 32 32" fill="none" aria-hidden="true">
        <rect x="1.2" y="1.2" width="29.6" height="29.6" rx="8" stroke="var(--acc)" stroke-width="1.4"/>
        <path d="M9 10l7 13 7-13" stroke="var(--acc)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      <span><?= $BRAND ?></span>
    </a>
    <div class="nav-links">
      <a href="shop.php" class="<?= ($NAV ?? '')==='shop'?'on':'' ?>">Catalog</a>
      <a href="requests.php" class="<?= ($NAV ?? '')==='requests'?'on':'' ?>">Requests</a>
      <a class="hidem" href="index.php#how">How it works</a>
      <?php if ($MEMBER): ?>
        <span class="memberpill">✓ Verified buyer</span>
      <?php else: ?>
        <a href="?demo_member=1">Sign in</a>
      <?php endif; ?>
      <a class="cartlink" href="cart.php" aria-label="Cart">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 7h14l-1.2 11H6.2L5 7z"/><path d="M9 7a3 3 0 0 1 6 0"/></svg>
        <span class="badge" id="cartCount" style="display:none">0</span>
      </a>
    </div>
  </nav></div>
</header>
