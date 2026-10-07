<?php
/**
 * Right of withdrawal + model withdrawal form — English (courtesy translation).
 * Follows the wording of the official EU model instructions (Directive 2011/83/EU,
 * Annex I) so that the translation stays as close to the statutory text as the
 * German original does. Variables come from legal/widerruf.php.
 */
?>
<div class="notice">
  <strong>Important for marketplace purchases:</strong> The statutory right of withdrawal exists only
  for contracts between a consumer and a <em>trader</em>. For items marked
  "<?= te('seller_private') ?>" on the product page there is therefore <strong>no</strong> right of
  withdrawal. You see this label before paying and must expressly confirm it during checkout.
</div>

<h2>Right of withdrawal</h2>
<p>
  You have the right to withdraw from this contract within <?= (int)$wd ?> days without giving any
  reason.
</p>
<p>
  The withdrawal period will expire after <?= (int)$wd ?> days from the day on which you acquire, or a
  third party other than the carrier and indicated by you acquires, physical possession of the goods.
</p>
<p>
  In the case of a contract relating to multiple goods ordered by you in one order and delivered
  separately, the withdrawal period will expire after <?= (int)$wd ?> days from the day on which you
  acquire, or a third party other than the carrier and indicated by you acquires, physical
  possession of the last good.
</p>
<p>
  To exercise the right of withdrawal, you must inform us
</p>
<div class="doc__box">
  <?= h($co) ?><br>
  <?= h($addrShown) ?><br>
  <?= $email !== '' ? 'E-mail: <a href="mailto:' . h($email) . '">' . h($email) . '</a>' : 'E-mail: [e-mail address]' ?>
</div>
<p>
  of your decision to withdraw from this contract by an unequivocal statement (e.g. a letter sent by
  post or an e-mail). You may use the attached model withdrawal form, but it is not obligatory.
</p>
<p>
  If the withdrawal concerns an item of a commercial third-party seller, a statement to us is
  sufficient; we are authorised to receive it and forward it without delay.
</p>
<p>
  To meet the withdrawal deadline, it is sufficient for you to send your communication concerning
  your exercise of the right of withdrawal before the withdrawal period has expired.
</p>

<h2>Effects of withdrawal</h2>
<p>
  If you withdraw from this contract, we shall reimburse to you all payments received from you,
  including the costs of delivery (with the exception of the supplementary costs resulting from your
  choice of a type of delivery other than the least expensive type of standard delivery offered by
  us), without undue delay and in any event not later than fourteen days from the day on which we are
  informed about your decision to withdraw from this contract. We will carry out such reimbursement
  using the same means of payment as you used for the initial transaction, unless you have expressly
  agreed otherwise; in any event, you will not incur any fees as a result of such reimbursement.
</p>
<p>
  We may withhold reimbursement until we have received the goods back or you have supplied evidence
  of having sent back the goods, whichever is the earliest.
</p>
<p>
  You shall send back the goods or hand them over to us, or to the seller named on the return label,
  without undue delay and in any event not later than fourteen days from the day on which you
  communicate your withdrawal from this contract to us. The deadline is met if you send back the
  goods before the period of fourteen days has expired.
</p>
<p>
  We bear the cost of returning the goods if the return is sent from Germany. For returns from other
  countries you bear the direct cost of returning the goods.
</p>
<p>
  You are only liable for any diminished value of the goods resulting from the handling other than
  what is necessary to establish the nature, characteristics and functioning of the goods. Trying on
  a garment is permitted; wearing, washing or removing the labels goes beyond that.
</p>

<h2>Exclusion of the right of withdrawal</h2>
<p>The right of withdrawal does not exist or expires for the following contracts:</p>
<ul>
  <li>contracts with sellers who are not traders (private sellers);</li>
  <li>contracts for the supply of goods made to the consumer's specifications or clearly
      personalised;</li>
  <li>contracts for the supply of sealed goods which are not suitable for return due to health
      protection or hygiene reasons and were unsealed after delivery (e.g. earrings, swimwear
      without hygiene seal);</li>
  <li>contracts in which you act as a trader (B2B).</li>
</ul>

<h2>Model withdrawal form</h2>
<div class="doc__box">
  <p><em>(Complete and return this form only if you wish to withdraw from the contract.)</em></p>
  <p>
    To<br>
    <?= h($co) ?><br>
    <?= h($addrShown) ?><br>
    <?= $email !== '' ? h($email) : '[e-mail address]' ?>
  </p>
  <p>
    I/We (*) hereby give notice that I/We (*) withdraw from my/our (*) contract of sale of the
    following goods (*):
  </p>
  <p>
    ______________________________________________<br>
    ______________________________________________
  </p>
  <p>
    Order number: ____________________________<br>
    Ordered on (*) / received on (*): ____________________________<br>
    Name of consumer(s): ____________________________<br>
    Address of consumer(s): ____________________________<br>
    ____________________________
  </p>
  <p>
    Signature of consumer(s) <em>(only if this form is notified on paper)</em>: ____________________________<br>
    Date: ____________________________
  </p>
  <p><em>(*) Delete as appropriate.</em></p>
</div>

<p class="doc__related">
  Beyond the statutory right of withdrawal we grant a voluntary right of return for our own goods —
  details under <a href="<?= h(vr_url('legal/rueckgabe.php')) ?>"><?= te('legal_returns') ?></a>.
</p>
