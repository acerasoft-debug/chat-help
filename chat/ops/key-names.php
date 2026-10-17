<?php
/**
 * ChatHelp ops — key-names: zeigt NUR, welche Konstanten in db.php definiert sind (VAR + Länge / fehlt) – niemals Werte.
 * Zweck: prüfen, welcher Sprachdienst (TTS) für realistische Stimmen im Live-Modus verfügbar ist.
 */
header('Content-Type: text/plain; charset=UTF-8');
require_once __DIR__.'/tokens-lib.php';
$names=['CLAUDE_KEY','DEEPSEEK_KEY','OPENAI_KEY','OPENAI_API_KEY','ELEVENLABS_KEY','ELEVENLABS_API_KEY','GOOGLE_TTS_KEY','GOOGLE_API_KEY','GOOGLE_CLOUD_KEY',
  'AZURE_SPEECH_KEY','AZURE_SPEECH_REGION','AWS_ACCESS_KEY_ID','AWS_SECRET_ACCESS_KEY','AWS_REGION','POLLY_KEY','POLLY_SECRET',
  'TWILIO_ACCOUNT_SID','TWILIO_AUTH_TOKEN','TWILIO_SMS_FROM','TWILIO_WA_NUMBERS','META_APP_SECRET','CH_SECRET_KEY','BREVO_KEY','STRIPE_SK'];
echo "ChatHelp — key-names (".gmdate('c').")  · nur Namen, keine Werte\n\n";
foreach($names as $k) echo str_pad($k,24).': '.(defined($k) ? ((string)constant($k)!=='' ? 'VAR (Länge '.strlen((string)constant($k)).')' : 'leer') : 'fehlt')."\n";
echo "\nPHP ".PHP_VERSION." · curl ".(function_exists('curl_init')?'ja':'nein')." · openssl ".(function_exists('openssl_encrypt')?'ja':'nein')." · temp beschreibbar: ".(is_writable(sys_get_temp_dir())?'ja':'nein')." · chat/ beschreibbar: ".(is_writable(dirname(__DIR__.'/x'))?'ja':'nein')."\n";
