/**
 * Transactional mail. Zero dependencies:
 *   - LUMEA_RESEND_KEY set  → delivered through the Resend HTTP API
 *   - LUMEA_MAIL_WEBHOOK set → POSTed as JSON to your own relay (Postmark, Brevo, n8n …)
 *   - otherwise              → written to <data dir>/outbox/*.eml and logged (dev / preview)
 * Every message is localised in the recipient's language.
 */
import { mkdirSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { site } from '../data/site.mjs';

const DATA_DIR = process.env.LUMEA_DATA_DIR || path.resolve(process.cwd(), '.data');
const OUTBOX = path.join(DATA_DIR, 'outbox');
const FROM = process.env.LUMEA_MAIL_FROM || `${site.brand} <concierge@lumea.spa>`;
const ORIGIN = `${site.origin}${site.basePath}`;

const T = {
  de: {
    greet: (n) => `Guten Tag${n ? ' ' + n : ''},`,
    sign: 'Ihr Luméa Concierge',
    bookingRequested: { s: 'Ihre Anfrage ist eingegangen', b: (v) => `Ihre Anfrage für ${v.service} am ${v.date} um ${v.time} in ${v.city} ist bei uns. Eine passende Therapeutin bestätigt in der Regel innerhalb von ${v.minutes} Minuten. Ihre Zahlung wird treuhänderisch gehalten und erst nach der Behandlung ausgezahlt.` },
    bookingConfirmed: { s: 'Bestätigt: Ihre Behandlung', b: (v) => `${v.therapist} hat Ihren Termin bestätigt: ${v.service}, ${v.date} um ${v.time}. Kalender-Datei: ${v.ics}` },
    bookingDone: { s: 'Wie war Ihre Behandlung?', b: (v) => `Ihre Behandlung ${v.service} ist abgeschlossen und die Auszahlung an ${v.therapist} ausgelöst. Bewerten Sie Ihre Erfahrung in Ihrem Konto: ${v.link}` },
    bookingCancelled: { s: 'Termin storniert', b: (v) => `Ihr Termin ${v.service} am ${v.date} wurde storniert. Eine vorausbezahlte Summe wird vollständig erstattet.` },
    applicationReceived: { s: 'Bewerbung eingegangen', b: (v) => `Danke für Ihre Bewerbung. Laden Sie Ausweis, Ausbildungsnachweis und Berufshaftpflicht in Ihrem Konto hoch: ${v.link} — erst nach Freigabe wird Ihr Profil buchbar.` },
    documentReviewed: { s: 'Dokument geprüft', b: (v) => `Ihr Dokument „${v.type}“ wurde ${v.status === 'approved' ? 'freigegeben' : 'abgelehnt'}${v.note ? ' — ' + v.note : ''}. Profilstatus: ${v.profile}.` },
    resetPassword: { s: 'Passwort zurücksetzen', b: (v) => `Über diesen Link setzen Sie ein neues Passwort (gültig 1 Stunde): ${v.link}` },
    verifyEmail: { s: 'E-Mail-Adresse bestätigen', b: (v) => `Bitte bestätigen Sie Ihre E-Mail-Adresse: ${v.link}` },
    voucher: { s: 'Ihr Luméa Gutschein', b: (v) => `${v.message ? '„' + v.message + '“\n\n' : ''}Gutscheincode: ${v.code} — einlösbar für jede Behandlung in allen 20 Städten, gültig bis ${v.expires}. Einlösen unter ${v.link}` },
    newLogin: { s: 'Neue Anmeldung in Ihrem Konto', b: (v) => `Eine Anmeldung von einem neuen Gerät (${v.ip}, ${v.city || '–'}) wurde registriert. Waren Sie das nicht, setzen Sie Ihr Passwort sofort zurück: ${v.link}` },
    priveWelcome: { s: 'Willkommen bei Luméa Privé', b: (v) => `Ihre Mitgliedschaft ${v.tier} ist aktiv. Ihr Concierge meldet sich innerhalb von 24 Stunden, um Ihre feste Therapeutin auszuwählen.` }
  },
  en: {
    greet: (n) => `Hello${n ? ' ' + n : ''},`,
    sign: 'Your Luméa concierge',
    bookingRequested: { s: 'Your request has been received', b: (v) => `Your request for ${v.service} on ${v.date} at ${v.time} in ${v.city} is with us. A matching therapist usually confirms within ${v.minutes} minutes. Your payment is held in escrow and released only after the treatment.` },
    bookingConfirmed: { s: 'Confirmed: your treatment', b: (v) => `${v.therapist} has confirmed your appointment: ${v.service}, ${v.date} at ${v.time}. Calendar file: ${v.ics}` },
    bookingDone: { s: 'How was your treatment?', b: (v) => `Your ${v.service} is complete and the payout to ${v.therapist} has been released. Rate your experience in your account: ${v.link}` },
    bookingCancelled: { s: 'Appointment cancelled', b: (v) => `Your appointment ${v.service} on ${v.date} was cancelled. Any prepaid amount is refunded in full.` },
    applicationReceived: { s: 'Application received', b: (v) => `Thank you for applying. Upload your ID, qualification and liability insurance in your account: ${v.link} — your profile becomes bookable only after approval.` },
    documentReviewed: { s: 'Document reviewed', b: (v) => `Your document "${v.type}" was ${v.status}${v.note ? ' — ' + v.note : ''}. Profile status: ${v.profile}.` },
    resetPassword: { s: 'Reset your password', b: (v) => `Set a new password via this link (valid for 1 hour): ${v.link}` },
    verifyEmail: { s: 'Confirm your email address', b: (v) => `Please confirm your email address: ${v.link}` },
    voucher: { s: 'Your Luméa gift voucher', b: (v) => `${v.message ? '"' + v.message + '"\n\n' : ''}Voucher code: ${v.code} — redeemable for any treatment in all 20 cities, valid until ${v.expires}. Redeem at ${v.link}` },
    newLogin: { s: 'New sign-in to your account', b: (v) => `A sign-in from a new device (${v.ip}, ${v.city || '–'}) was recorded. If this wasn't you, reset your password now: ${v.link}` },
    priveWelcome: { s: 'Welcome to Luméa Privé', b: (v) => `Your ${v.tier} membership is active. Your concierge will be in touch within 24 hours to select your dedicated therapist.` }
  },
  es: {
    greet: (n) => `Hola${n ? ' ' + n : ''},`,
    sign: 'Tu concierge Luméa',
    bookingRequested: { s: 'Hemos recibido tu solicitud', b: (v) => `Tu solicitud de ${v.service} el ${v.date} a las ${v.time} en ${v.city} está con nosotros. Una terapeuta suele confirmar en ${v.minutes} minutos. El pago queda en depósito y solo se libera tras el tratamiento.` },
    bookingConfirmed: { s: 'Confirmado: tu tratamiento', b: (v) => `${v.therapist} ha confirmado tu cita: ${v.service}, ${v.date} a las ${v.time}. Archivo de calendario: ${v.ics}` },
    bookingDone: { s: '¿Qué tal tu tratamiento?', b: (v) => `Tu ${v.service} ha terminado y el pago a ${v.therapist} se ha liberado. Valora tu experiencia en tu cuenta: ${v.link}` },
    bookingCancelled: { s: 'Cita cancelada', b: (v) => `Tu cita ${v.service} del ${v.date} se ha cancelado. Cualquier importe prepagado se reembolsa íntegramente.` },
    applicationReceived: { s: 'Candidatura recibida', b: (v) => `Gracias por tu candidatura. Sube tu DNI, titulación y seguro en tu cuenta: ${v.link} — tu perfil solo será reservable tras la aprobación.` },
    documentReviewed: { s: 'Documento revisado', b: (v) => `Tu documento «${v.type}» ha sido ${v.status === 'approved' ? 'aprobado' : 'rechazado'}${v.note ? ' — ' + v.note : ''}. Estado del perfil: ${v.profile}.` },
    resetPassword: { s: 'Restablecer contraseña', b: (v) => `Define una nueva contraseña con este enlace (válido 1 hora): ${v.link}` },
    verifyEmail: { s: 'Confirma tu correo', b: (v) => `Confirma tu dirección de correo: ${v.link}` },
    voucher: { s: 'Tu tarjeta regalo Luméa', b: (v) => `${v.message ? '«' + v.message + '»\n\n' : ''}Código: ${v.code} — canjeable por cualquier tratamiento en las 20 ciudades, válido hasta ${v.expires}. Canjear en ${v.link}` },
    newLogin: { s: 'Nuevo inicio de sesión', b: (v) => `Se ha registrado un inicio de sesión desde un dispositivo nuevo (${v.ip}, ${v.city || '–'}). Si no fuiste tú, restablece tu contraseña: ${v.link}` },
    priveWelcome: { s: 'Bienvenido a Luméa Privé', b: (v) => `Tu membresía ${v.tier} está activa. Tu concierge te contactará en 24 horas para elegir tu terapeuta fija.` }
  },
  fr: {
    greet: (n) => `Bonjour${n ? ' ' + n : ''},`,
    sign: 'Votre concierge Luméa',
    bookingRequested: { s: 'Votre demande a bien été reçue', b: (v) => `Votre demande de ${v.service} le ${v.date} à ${v.time} à ${v.city} est chez nous. Une thérapeute confirme généralement sous ${v.minutes} minutes. Votre paiement est conservé sous séquestre et versé uniquement après le soin.` },
    bookingConfirmed: { s: 'Confirmé : votre soin', b: (v) => `${v.therapist} a confirmé votre rendez-vous : ${v.service}, le ${v.date} à ${v.time}. Fichier calendrier : ${v.ics}` },
    bookingDone: { s: 'Comment était votre soin ?', b: (v) => `Votre ${v.service} est terminé et le versement à ${v.therapist} a été libéré. Notez votre expérience dans votre compte : ${v.link}` },
    bookingCancelled: { s: 'Rendez-vous annulé', b: (v) => `Votre rendez-vous ${v.service} du ${v.date} a été annulé. Tout montant prépayé est intégralement remboursé.` },
    applicationReceived: { s: 'Candidature reçue', b: (v) => `Merci pour votre candidature. Téléversez pièce d’identité, diplôme et assurance dans votre compte : ${v.link} — votre profil ne devient réservable qu’après approbation.` },
    documentReviewed: { s: 'Document vérifié', b: (v) => `Votre document « ${v.type} » a été ${v.status === 'approved' ? 'approuvé' : 'refusé'}${v.note ? ' — ' + v.note : ''}. Statut du profil : ${v.profile}.` },
    resetPassword: { s: 'Réinitialiser votre mot de passe', b: (v) => `Définissez un nouveau mot de passe via ce lien (valable 1 heure) : ${v.link}` },
    verifyEmail: { s: 'Confirmez votre adresse e-mail', b: (v) => `Merci de confirmer votre adresse e-mail : ${v.link}` },
    voucher: { s: 'Votre carte cadeau Luméa', b: (v) => `${v.message ? '« ' + v.message + ' »\n\n' : ''}Code : ${v.code} — valable pour tout soin dans les 20 villes, jusqu’au ${v.expires}. À utiliser sur ${v.link}` },
    newLogin: { s: 'Nouvelle connexion à votre compte', b: (v) => `Une connexion depuis un nouvel appareil (${v.ip}, ${v.city || '–'}) a été enregistrée. Si ce n’est pas vous, réinitialisez votre mot de passe : ${v.link}` },
    priveWelcome: { s: 'Bienvenue chez Luméa Privé', b: (v) => `Votre abonnement ${v.tier} est actif. Votre concierge vous contacte sous 24 heures pour choisir votre thérapeute attitrée.` }
  },
  it: {
    greet: (n) => `Buongiorno${n ? ' ' + n : ''},`,
    sign: 'Il tuo concierge Luméa',
    bookingRequested: { s: 'Richiesta ricevuta', b: (v) => `La tua richiesta di ${v.service} il ${v.date} alle ${v.time} a ${v.city} è da noi. Un terapista di solito conferma entro ${v.minutes} minuti. Il pagamento resta in deposito e viene versato solo dopo il trattamento.` },
    bookingConfirmed: { s: 'Confermato: il tuo trattamento', b: (v) => `${v.therapist} ha confermato l’appuntamento: ${v.service}, ${v.date} alle ${v.time}. File calendario: ${v.ics}` },
    bookingDone: { s: 'Com’è andato il trattamento?', b: (v) => `Il tuo ${v.service} è completato e il pagamento a ${v.therapist} è stato liberato. Valuta l’esperienza nel tuo account: ${v.link}` },
    bookingCancelled: { s: 'Appuntamento annullato', b: (v) => `L’appuntamento ${v.service} del ${v.date} è stato annullato. Qualsiasi importo prepagato viene rimborsato per intero.` },
    applicationReceived: { s: 'Candidatura ricevuta', b: (v) => `Grazie per la candidatura. Carica documento, diploma e assicurazione nel tuo account: ${v.link} — il profilo diventa prenotabile solo dopo l’approvazione.` },
    documentReviewed: { s: 'Documento verificato', b: (v) => `Il documento «${v.type}» è stato ${v.status === 'approved' ? 'approvato' : 'rifiutato'}${v.note ? ' — ' + v.note : ''}. Stato del profilo: ${v.profile}.` },
    resetPassword: { s: 'Reimposta la password', b: (v) => `Imposta una nuova password con questo link (valido 1 ora): ${v.link}` },
    verifyEmail: { s: 'Conferma il tuo indirizzo e-mail', b: (v) => `Conferma il tuo indirizzo e-mail: ${v.link}` },
    voucher: { s: 'Il tuo buono regalo Luméa', b: (v) => `${v.message ? '«' + v.message + '»\n\n' : ''}Codice: ${v.code} — valido per qualsiasi trattamento in tutte le 20 città fino al ${v.expires}. Usalo su ${v.link}` },
    newLogin: { s: 'Nuovo accesso al tuo account', b: (v) => `È stato registrato un accesso da un nuovo dispositivo (${v.ip}, ${v.city || '–'}). Se non sei stato tu, reimposta subito la password: ${v.link}` },
    priveWelcome: { s: 'Benvenuto in Luméa Privé', b: (v) => `Il tuo abbonamento ${v.tier} è attivo. Il tuo concierge ti contatterà entro 24 ore per scegliere la tua terapista fissa.` }
  }
};

const SEG = {
  account: { de: 'konto', en: 'account', es: 'cuenta', fr: 'compte', it: 'account' },
  reset: { de: 'passwort-zuruecksetzen', en: 'reset-password', es: 'restablecer', fr: 'reinitialiser', it: 'reimposta-password' },
  book: { de: 'buchen', en: 'book', es: 'reservar', fr: 'reserver', it: 'prenota' }
};
export const link = (locale, page, query = '') => `${ORIGIN}/${locale}/${SEG[page][locale]}/${query}`;

async function deliver(msg) {
  if (process.env.LUMEA_RESEND_KEY) {
    const res = await fetch('https://api.resend.com/emails', {
      method: 'POST',
      headers: { authorization: `Bearer ${process.env.LUMEA_RESEND_KEY}`, 'content-type': 'application/json' },
      body: JSON.stringify({ from: FROM, to: [msg.to], subject: msg.subject, text: msg.text }),
      signal: AbortSignal.timeout(5000)
    });
    if (!res.ok) throw new Error(`resend ${res.status}`);
    return 'resend';
  }
  if (process.env.LUMEA_MAIL_WEBHOOK) {
    const res = await fetch(process.env.LUMEA_MAIL_WEBHOOK, { method: 'POST', headers: { 'content-type': 'application/json' }, body: JSON.stringify({ from: FROM, ...msg }), signal: AbortSignal.timeout(5000) });
    if (!res.ok) throw new Error(`webhook ${res.status}`);
    return 'webhook';
  }
  mkdirSync(OUTBOX, { recursive: true });
  const file = path.join(OUTBOX, `${Date.now()}-${msg.template}-${msg.to.replace(/[^a-z0-9]/gi, '_')}.eml`);
  writeFileSync(file, `From: ${FROM}\nTo: ${msg.to}\nSubject: ${msg.subject}\nContent-Type: text/plain; charset=utf-8\n\n${msg.text}\n`);
  return 'outbox';
}

/** Fire-and-forget: a mail failure must never break the request that triggered it. */
export function sendMail(template, { to, locale = 'de', name, vars = {} }) {
  const L = T[locale] || T.de;
  const tpl = L[template];
  if (!tpl || !to) return Promise.resolve('skipped');
  const text = `${L.greet(name)}\n\n${tpl.b(vars)}\n\n${L.sign}\n${site.brand} · ${ORIGIN}`;
  return deliver({ template, to, subject: `${site.brand} — ${tpl.s}`, text })
    .then((via) => { if (via === 'outbox') console.log(`[mail→outbox] ${template} → ${to}`); return via; })
    .catch((err) => { console.error('[mail]', template, to, err.message); return 'failed'; });
}
