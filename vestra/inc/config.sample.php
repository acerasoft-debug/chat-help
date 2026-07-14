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

  // Require a click-to-verify email before a new account can sign in?
  // Keep FALSE on hosts where outgoing mail can't be delivered (blocked SMTP +
  // strict domain DMARC) — otherwise every new account is locked out forever.
  // Access is still gated by admin KYB approval. Set true once real email works.
  'require_email_verify' => false,

  // ── BEST option: transactional email over HTTPS (port 443) ──────────────────
  // Works even when the host blocks outbound SMTP ports (25/465/587). The
  // provider signs mail with its own SPF/DKIM, so it lands in the inbox without
  // touching your domain's DNS. When mail_api_key is set it takes priority over
  // SMTP and mail() below.
  //   Brevo (free 300/day):  https://app.brevo.com → SMTP & API → API Keys
  //     Verify 'mail_from' as a sender: Senders → Add a sender → click the
  //     confirmation link delivered to that mailbox (no DNS required).
  //   Resend: set mail_api_provider => 'resend' and a re_… key.
  'mail_api_provider' => 'brevo',   // 'brevo' | 'resend'
  'mail_api_key'      => '',        // empty = API transport off

  // Optional: authenticated SMTP (used only if mail_api_key is empty above).
  // Needs an outbound SMTP port open — many shared hosts block these, in which
  // case use the HTTP API above instead. Example (Gmail):
  //   smtp_host = smtp.gmail.com, smtp_port = 587, smtp_user = you@gmail.com,
  //   smtp_pass = an App Password from myaccount.google.com/apppasswords.
  // Leave smtp_host empty ('') to fall back to local mail().
  'smtp_host'    => '',
  'smtp_port'    => 587,
  'smtp_user'    => '',
  'smtp_pass'    => '',
  'smtp_from'    => '',   // usually same as smtp_user
  'smtp_name'    => 'VESTRA',
];
