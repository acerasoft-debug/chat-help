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
  'mail_from'    => 'support@vestrasales.com',

  // Send an automatic acknowledgement email to the person who registers / orders.
  'confirm_user' => true,

  // Master switch for outgoing email (false = nothing is sent).
  'mail_enabled' => true,

  // Optional: send via authenticated SMTP instead of the server's local mail().
  // Recommended whenever the hosting server's IP isn't authorized in the
  // sending domain's SPF/DKIM/DMARC records — e.g. use your own Gmail:
  //   smtp_host = smtp.gmail.com, smtp_port = 587, smtp_user = you@gmail.com,
  //   smtp_pass = an App Password from myaccount.google.com/apppasswords
  //   (requires 2-Step Verification enabled on that Google account).
  // Leave smtp_host empty ('') to keep using local mail() as before.
  'smtp_host'    => '',
  'smtp_port'    => 587,
  'smtp_user'    => '',
  'smtp_pass'    => '',
  'smtp_from'    => '',   // usually same as smtp_user
  'smtp_name'    => 'VESTRA',
];
