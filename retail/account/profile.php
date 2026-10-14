<?php
/** Ad, telefon, tercih edilen dil. Adresler ayrı sayfada (addresses.php). */

declare(strict_types=1);

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/customers.php';
require_once __DIR__ . '/_nav.php';

$me = vr_customer_require();
$langs = (array)vr_config('languages', ['de', 'en']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    vr_csrf_check();
    $lang = (string)($_POST['lang'] ?? '');
    vr_customer_update((string)$me['id'], [
        'name'  => mb_substr(trim((string)($_POST['name'] ?? '')), 0, 120),
        'phone' => mb_substr(preg_replace('/[^0-9+()\s\/-]/', '', (string)($_POST['phone'] ?? '')) ?? '', 0, 32),
        'lang'  => in_array($lang, $langs, true) ? $lang : (string)($me['lang'] ?? ''),
    ]);
    // Dil tercihi siteye de geçsin: ?lang= çerezi yazıyor (inc/i18n.php).
    vr_redirect('account/profile.php', ['saved' => 1, 'lang' => (string)(vr_current_customer()['lang'] ?? vr_lang())]);
}
$saved = !empty($_GET['saved']);

/** Dil adı kendi dilinde (Deutsch, English, Français …). */
function vr_acc_lang_name(string $l): string
{
    if (class_exists('Locale')) {
        $n = (string)\Locale::getDisplayLanguage($l, $l);
        if ($n !== '' && $n !== $l) return mb_convert_case(mb_substr($n, 0, 1), MB_CASE_UPPER) . mb_substr($n, 1);
    }
    return strtoupper($l);
}

vr_acc_open($me, 'profile.php', t('acc_nav_profile'), t('acc_profile_lead'));
?>
<?php if ($saved): ?><div class="flash flash--ok accflash"><?= vr_icon('check', 16) ?> <?= te('acc_saved') ?></div><?php endif; ?>

<form class="form accform" method="post" action="<?= h(vr_url('account/profile.php')) ?>">
  <?= vr_csrf_field() ?>
  <fieldset class="acccard">
    <legend class="acccard__h"><h2><?= vr_icon('user', 18) ?> <?= te('acc_profile_who') ?></h2></legend>
    <div class="field">
      <label for="name"><?= te('your_name') ?></label>
      <input id="name" name="name" type="text" autocomplete="name" value="<?= h((string)$me['name']) ?>">
    </div>
    <div class="field">
      <label for="email"><?= te('email') ?></label>
      <input id="email" type="email" value="<?= h((string)$me['email']) ?>" disabled>
      <small><?= te('acc_email_note') ?></small>
    </div>
    <div class="field">
      <label for="phone"><?= te('acc_phone') ?></label>
      <input id="phone" name="phone" type="tel" autocomplete="tel" value="<?= h((string)($me['phone'] ?? '')) ?>">
      <small><?= te('acc_phone_note') ?></small>
    </div>
    <div class="field">
      <label for="lang"><?= te('acc_lang') ?></label>
      <select id="lang" name="lang">
        <?php $cur = (string)($me['lang'] ?? vr_lang());
        foreach ($langs as $l): ?>
          <option value="<?= h($l) ?>"<?= $cur === $l ? ' selected' : '' ?>><?= h(vr_acc_lang_name($l)) ?></option>
        <?php endforeach; ?>
      </select>
      <small><?= te('acc_lang_note') ?></small>
    </div>
  </fieldset>
  <div class="accform__bar">
    <button class="btn" type="submit"><span><?= te('acc_save') ?></span></button>
  </div>
</form>
<?php vr_acc_close(); ?>
