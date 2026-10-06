<?php
/**
 * VESTRA — FIYAT LISTESI DUVARI (operator karari, 8 Eyl 2026:
 * *"giris olmadan price list acilmasin anmeldung a zorla"*).
 *
 * NE DEGISTI. /price-list ve /price-lists o gune kadar BILEREK herkese acikti
 * (dosyalarin kendi yorumu "PUBLIC ON PURPOSE" diyordu): rakamlar zaten
 * gizliydi -- fiyat sutununda 🔒 vardi -- ama katalogun kendisi (urun adi,
 * artikel numarasi, MOQ, bedenler, stok derinligi, marka basina kac urun)
 * girissiz okunuyordu. Operator artik listenin ACILMAMASINI istiyor: kayit
 * zorunlu.
 *
 * KAPI YENIDEN TANIMLANMIYOR. Karar head.php'nin zaten hesapladigi $PRICES
 * (= $IS_ADMIN || auth_prices_unlocked()) ile veriliyor; bu dosya yalnizca
 * KILITLIYKEN NE GORUNECEGINI ciziyor. Bu depoda kapinin ikinci bir kopyasi
 * alti kez yanlis yere bakti (bkz. CLAUDE.md, "Teshis ciktisina koru korune
 * guvenme"); yedincisini yazmiyoruz.
 *
 * METIN NEDEN MEVCUT ANAHTARLARDAN. KURAL 10: yeni bir t('...') metni 7
 * sozluge birden eklenmezse sessizce Ingilizceye duser. Buradaki her cumle
 * de/fr/it/es/pt/ru/ar sozluklerinde ZATEN duran bir anahtar -- duvar ilk
 * gunden 8 dilde dogru cikiyor. (Almanca karsiligi "Gewerbeanmeldung", yani
 * operatorun istedigi "Anmeldung" tam olarak bu.)
 */

/**
 * Kilitli ziyaretciye fiyat listesi yerine kayit duvarini basar.
 *
 * @param string      $gate  head.php'deki $PRICE_GATE: 'guest' | 'approval'
 * @param string      $kyc   head.php'deki $KYC_URL (belge sekmesi)
 * @param array|null  $user  head.php'deki $AUTH_USER
 * @param string      $back  giristen sonra donulecek yol (marka suzgeci korunur)
 * @param string      $title sayfanin H1'i (marka adi ya da liste basligi)
 */
function vestra_price_wall(string $gate, string $kyc, ?array $user, string $back, string $title): void {
    require_once __DIR__.'/auth.php';
    $pending = ($gate === 'approval');
    /* Belgesini vermis olana "belgenizi yukleyin" demek, yaptigi isi tekrar
       yaptirmaktir -- KURAL 2b'nin aynen kaydettigi hata. */
    $hasDoc  = in_array(auth_trade_doc_status($user), ['uploaded','approved'], true);
    ?>
<style>
  .pw-wrap{max-width:820px;margin:0 auto;padding:56px 24px 90px}
  .pw-wrap h1{font-size:clamp(28px,4.6vw,42px);margin:0 0 10px}
  .pw-lead{color:var(--mut);line-height:1.6;margin:0 0 30px;max-width:60ch}
  .pw-card{border:1px solid var(--line);border-radius:12px;padding:30px 28px;
    background:rgba(255,255,255,.02)}
  .pw-lock{font-size:30px;line-height:1;margin-bottom:14px}
  .pw-card h2{font-size:20px;margin:0 0 10px}
  .pw-card p{color:var(--mut);line-height:1.65;margin:0 0 8px;max-width:58ch}
  .pw-btns{display:flex;gap:10px;flex-wrap:wrap;margin-top:22px}
  .pw-list{margin:26px 0 0;padding:0;list-style:none;color:var(--mut);line-height:1.9;font-size:14px}
  .pw-list li:before{content:"—　";color:var(--acc)}
</style>
<div class="pw-wrap">
  <h1><?= htmlspecialchars($title) ?></h1>

  <div class="pw-card">
    <div class="pw-lock"><?= $pending ? '⏳' : '🔒' ?></div>
    <?php if ($pending): ?>
      <h2><?= t('Awaiting approval') ?></h2>
      <p><?= $hasDoc
            ? t('We have your trade licence. Wholesale prices and ordering open as soon as it is approved.')
            : t('Your trade licence is being checked. Wholesale prices open as soon as it is approved.') ?></p>
      <?php if (!$hasDoc): ?>
      <div class="pw-btns">
        <a class="btn btn-p" href="<?= htmlspecialchars($kyc) ?>"><?= t('Add document') ?></a>
      </div>
      <?php endif; ?>
    <?php else: ?>
      <h2><?= t('Sign in as a verified business buyer to see pricing') ?></h2>
      <p><?= t('Wholesale prices open once we have checked your trade licence / business registration.') ?></p>
      <div class="pw-btns">
        <a class="btn btn-p" href="/register"><?= t('Register free') ?></a>
        <a class="btn btn-o" href="/login?back=<?= rawurlencode($back) ?>"><?= t('Sign in') ?></a>
      </div>
    <?php endif; ?>
    <?php /* Kayittan sonra ACILAN sey: fiyat, stok derinligi ve indirilebilir
             liste. Her ikisi de 7 sozlukte DURAN anahtar -- duvarin yarisi
             Ingilizce kalsaydi kayit istegi de yarim gorunurdu. */ ?>
    <ul class="pw-list">
      <li><?= t('Stock is shown per article and broken down by size, so you can see the depth of a run before you ask. This is standing stock, not open production: quantities move, and an article can sell out and not return.') ?></li>
      <li><?= t('Sign in to download the line sheet (PDF & Excel).') ?></li>
    </ul>
  </div>
</div>
<?php
}
