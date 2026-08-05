<?php
/**
 * Ürün ekle / düzenle
 * -------------------
 * Görseller doğrudan buradan yüklenir (inc/uploads.php sertleştirmesiyle).
 * Kaydedilen her satır incelemeye düşer; onay sonrası vitrinde görünür.
 */

declare(strict_types=1);

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/listings.php';
require_once __DIR__ . '/_nav.php';

$seller = vr_seller_require();

$editId  = trim((string)($_GET['id'] ?? ''));
$listing = $editId !== '' ? vr_listing_get($editId, (string)$seller['id']) : null;
if ($editId !== '' && $listing === null) {
    http_response_code(404);
    vr_flash(t('order_not_found'), 'err');
    vr_redirect('seller/index.php');
}

$errors = [];
$in = [
    'brand'     => (string)($listing['brand'] ?? ''),
    'name'      => (string)($listing['name'] ?? ''),
    'cat'       => (string)($listing['cat'] ?? ''),
    'sku'       => (string)($listing['sku'] ?? ''),
    'price'     => $listing !== null ? number_format((float)$listing['retail_price'], 2, ',', '') : '',
    'sizes'     => '',
    'desc'      => (string)($listing['desc'] ?? ''),
    'condition' => (string)($listing['condition'] ?? 'new'),
];
if ($listing !== null) {
    $sz = (array)($listing['size_stock'] ?? []);
    $in['sizes'] = implode(', ', array_map(fn($k, $v) => $k . '×' . (int)$v, array_keys($sz), $sz));
}
$currentImages = (array)($listing['images'] ?? []);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    vr_csrf_check();
    foreach ($in as $k => $_) $in[$k] = (string)($_POST[$k] ?? '');

    [$ok, $res] = vr_listing_save(
        $seller,
        $in + ['keep_images' => (array)($_POST['keep_images'] ?? [])],
        $_FILES['images'] ?? [],
        $listing !== null ? (string)$listing['id'] : null
    );

    if ($ok) {
        vr_flash($listing !== null ? t('l_updated') : t('l_saved'), 'ok');
        vr_redirect('seller/index.php');
    }
    $errors = is_array($res) ? $res : ['general' => t('required_field')];
}

// Kategori önerileri: mevcut katalogda kullanılan adlar — satıcı serbest metin
// yazıp kategori ağacını dağıtmasın diye liste halinde sunuluyor.
$cats = array_keys(vr_facets()['cats']);
sort($cats, SORT_NATURAL | SORT_FLAG_CASE);

vr_layout_start(['title' => $listing !== null ? t('edit') : t('listing_new_t'), 'robots' => 'noindex,nofollow']);
?>
<section class="sec sec--tight">
  <div class="wrap">
    <h1 class="sechead__t" style="margin-bottom:24px"><?= $listing !== null ? te('edit') : te('listing_new_t') ?></h1>

    <div class="dash">
      <?php vr_dashnav('listing.php', $seller); ?>

      <div>
        <?php vr_connect_box($seller); ?>
        <?php if ($errors): ?>
          <div class="flash flash--err" style="margin-bottom:16px"><?= te('required_field') ?></div>
        <?php endif; ?>

        <form class="form form--wide" method="post" enctype="multipart/form-data"
              action="<?= h(vr_url('seller/listing.php', $listing !== null ? ['id' => (string)$listing['id']] : [])) ?>">
          <?= vr_csrf_field() ?>

          <div class="grid grid--2" style="gap:16px">
            <div class="field<?= isset($errors['brand']) ? ' field--err' : '' ?>">
              <label for="brand"><?= te('l_brand') ?></label>
              <input id="brand" name="brand" required value="<?= h($in['brand']) ?>" placeholder="BALMAIN">
              <?php if (isset($errors['brand'])): ?><p class="field__err"><?= h($errors['brand']) ?></p><?php endif; ?>
            </div>

            <div class="field<?= isset($errors['sku']) ? ' field--err' : '' ?>">
              <label for="sku"><?= te('l_sku') ?></label>
              <input id="sku" name="sku" value="<?= h($in['sku']) ?>" placeholder="AH0EG000BC27">
              <small>Modellcode hilft Käufern beim Vergleich und uns bei der Prüfung.</small>
            </div>
          </div>

          <div class="field<?= isset($errors['name']) ? ' field--err' : '' ?>">
            <label for="name"><?= te('l_name') ?></label>
            <input id="name" name="name" required value="<?= h($in['name']) ?>"
                   placeholder="Embroidered T-Shirt, black">
            <?php if (isset($errors['name'])): ?><p class="field__err"><?= h($errors['name']) ?></p><?php endif; ?>
          </div>

          <div class="grid grid--2" style="gap:16px">
            <div class="field<?= isset($errors['cat']) ? ' field--err' : '' ?>">
              <label for="cat"><?= te('l_cat') ?></label>
              <input id="cat" name="cat" required list="cats" value="<?= h($in['cat']) ?>">
              <datalist id="cats">
                <?php foreach ($cats as $c): ?><option value="<?= h($c) ?>"></option><?php endforeach; ?>
              </datalist>
              <?php if (isset($errors['cat'])): ?><p class="field__err"><?= h($errors['cat']) ?></p><?php endif; ?>
            </div>

            <div class="field<?= isset($errors['price']) ? ' field--err' : '' ?>">
              <label for="price"><?= te('l_price') ?></label>
              <input id="price" name="price" required inputmode="decimal" value="<?= h($in['price']) ?>" placeholder="129,90">
              <?php
              $feeBps = vr_fee_bps((string)$seller['seller_type']);
              ?>
              <small>
                <?= te('our_fee') ?>: <?= h($feeBps / 100) ?>% + <?= h(vr_money((int)vr_config('fee_fixed_cents', 35))) ?>
                — <?= te('vat_incl', ['rate' => (int)vr_config('vat_rate_bps', 1900) / 100]) ?>
              </small>
              <?php if (isset($errors['price'])): ?><p class="field__err"><?= h($errors['price']) ?></p><?php endif; ?>
            </div>
          </div>

          <div class="field<?= isset($errors['sizes']) ? ' field--err' : '' ?>">
            <label for="sizes"><?= te('l_sizes') ?></label>
            <input id="sizes" name="sizes" required value="<?= h($in['sizes']) ?>" placeholder="S×1, M×3, L×2, XL×1">
            <small>Größe×Stückzahl. Einzelstück ohne Größe: <code>ONE×1</code>.</small>
            <?php if (isset($errors['sizes'])): ?><p class="field__err"><?= h($errors['sizes']) ?></p><?php endif; ?>
          </div>

          <div class="field">
            <label for="condition"><?= te('l_condition') ?></label>
            <select id="condition" name="condition">
              <option value="new" <?= $in['condition'] === 'new' ? 'selected' : '' ?>><?= te('condition_new') ?></option>
              <option value="used" <?= $in['condition'] === 'used' ? 'selected' : '' ?>><?= te('condition_used') ?></option>
            </select>
          </div>

          <!-- ------------------------------------------------------ görseller -->
          <div class="field<?= isset($errors['images']) ? ' field--err' : '' ?>">
            <label for="images"><?= te('l_images') ?></label>
            <input id="images" name="images[]" type="file" accept="image/jpeg,image/png,image/webp" multiple>
            <small>
              JPG, PNG oder WebP · max. <?= (int)(VR_UPLOAD_MAX_BYTES / 1048576) ?> MB je Datei ·
              max. <?= (int)VR_UPLOAD_MAX_FILES ?> Bilder. Metadaten (EXIF/GPS) werden beim Speichern entfernt.
            </small>
            <?php if (isset($errors['images'])): ?><p class="field__err"><?= h($errors['images']) ?></p><?php endif; ?>
          </div>

          <?php if ($currentImages): ?>
            <div class="field">
              <label><?= te('l_images') ?> — <?= te('all') ?></label>
              <div class="grid" style="grid-template-columns:repeat(auto-fill,minmax(110px,1fr));gap:10px">
                <?php foreach ($currentImages as $img): ?>
                  <label style="display:block;cursor:pointer">
                    <img src="<?= h($img) ?>" alt="" width="200" height="250"
                         style="aspect-ratio:4/5;object-fit:cover;border:1px solid var(--bone-3)">
                    <span class="check" style="margin-top:6px;font-size:11.5px">
                      <input type="checkbox" name="keep_images[]" value="<?= h($img) ?>" checked>
                      <span><?= te('all') ?></span>
                    </span>
                  </label>
                <?php endforeach; ?>
              </div>
              <small>Häkchen entfernen = Bild aus dem Angebot nehmen.</small>
            </div>
          <?php endif; ?>

          <div class="field">
            <label for="desc"><?= te('l_desc') ?></label>
            <textarea id="desc" name="desc" placeholder="Zustand, Herkunft, Belege, Maße …"><?= h($in['desc']) ?></textarea>
            <small><?= te('sell_rules_b') ?></small>
          </div>

          <button class="btn btn--lg" type="submit"><span><?= te('l_save') ?></span></button>
          <p style="font-size:12.5px;color:var(--muted)"><?= te('l_saved') ?>
            <?php if (empty($seller['charges_enabled'])): ?><br><strong><?= te('l_needs_payout') ?></strong><?php endif; ?>
          </p>
        </form>
      </div>
    </div>
  </div>
</section>
<?php vr_layout_end(); ?>
