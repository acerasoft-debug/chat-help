<?php
/**
 * vestra_attr_channel(): ziyaretcinin dis kaynagini kanal adina cevirir
 * (inc/attribution.php, operator 8 Eki 2026: "musterilerin nereden geldigini olc").
 * Kullanim: php tests/attribution_test.php
 */
require_once __DIR__.'/../vestra/inc/attribution.php';
$ok = 0; $bad = 0;
$is = function (string $label, string $got, string $want) use (&$ok, &$bad) {
  if ($got === $want) { $ok++; echo "  ok   $label\n"; } else { $bad++; echo "  FAIL $label -> '$got' (beklenen '$want')\n"; }
};
$c = fn(string $ref, array $q = []) => vestra_attr_channel($ref, $q);
$is('chatgpt.com referrer', $c('chatgpt.com'), 'ai:chatgpt');
$is('chat.openai.com', $c('chat.openai.com'), 'ai:chatgpt');
$is('utm_source=chatgpt.com (referrer yok)', $c('', ['utm_source' => 'chatgpt.com']), 'ai:chatgpt');
$is('utm, referrer\'dan once gelir', $c('www.google.com', ['utm_source' => 'chatgpt.com']), 'ai:chatgpt');
$is('perplexity', $c('www.perplexity.ai'), 'ai:perplexity');
$is('gemini', $c('gemini.google.com'), 'ai:gemini');
$is('gemini google aramasi DEGIL', $c('gemini.google.com') === 'search:google' ? 'yanlis' : 'dogru', 'dogru');
$is('copilot', $c('copilot.microsoft.com'), 'ai:copilot');
$is('google.fr', $c('www.google.fr'), 'search:google');
$is('google.co.uk', $c('google.co.uk'), 'search:google');
$is('bing', $c('www.bing.com'), 'search:bing');
$is('duckduckgo', $c('duckduckgo.com'), 'search:other');
$is('brevo tiklama izi', $c('bbhbedhd.r.bh.d.sendibt3.com'), 'email');
$is('webmail outlook', $c('outlook.office365.com'), 'email');
$is('gmail web', $c('mail.google.com'), 'email');
$is('utm_medium=email', $c('', ['utm_source' => 'promo', 'utm_medium' => 'email']), 'email');
$is('instagram (l.)', $c('l.instagram.com'), 'social:instagram');
$is('facebook (lm.)', $c('lm.facebook.com'), 'social:facebook');
$is('fbclid', $c('', ['fbclid' => 'x']), 'social:facebook');
$is('gclid', $c('www.google.com', ['gclid' => 'x']), 'ads:google');
$is('utm cpc', $c('', ['utm_source' => 'google', 'utm_medium' => 'cpc']), 'ads:google');
$is('linkedin', $c('www.linkedin.com'), 'social:linkedin');
$is('referrer yok = direct', $c(''), 'direct');
$is('baska site', $c('www.boutique-blog.fr'), 'referral:boutique-blog.fr');
$is('bilinmeyen utm', $c('', ['utm_source' => 'Partner<script>']), 'utm:partnerscript');
$is('etiket ChatGPT', vestra_attr_label('ai:chatgpt'), 'ChatGPT');
$is('etiket referral', vestra_attr_label('referral:example.com'), 'example.com');
$is('beyan sozlugu ai', array_key_exists('ai', vestra_attr_how_options()) ? 'var' : 'yok', 'var');
echo "\nTOPLAM: $ok gecti, $bad kaldi\n";
exit($bad ? 1 : 0);
