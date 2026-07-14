<?php
/**
 * VESTRA configuration TEMPLATE.
 * Copy this file to inc/config.php (same folder) and fill in your values.
 * inc/config.php is private (git-ignored) — never commit real secrets.
 */
return [
  // Admin panel password. SET a strong value to enable /admin. Empty = admin locked.
  'admin_pass'   => '',

  // Where new signup / order / offer notifications are emailed (one or more).
  'notify'       => ['acerasoft@gmail.com', 'support@vestrasales.com'],

  // From address for outgoing mail — use a real mailbox on your domain.
  'mail_from'    => 'register@vestrasales.com',

  // Send an automatic acknowledgement email to the person who registers / orders.
  'confirm_user' => true,

  // Master switch for outgoing email (false = nothing is sent).
  'mail_enabled' => true,
];
