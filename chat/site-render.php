<?php
/**
 * ChatHelp — Website-Baukasten: Darstellung (serverseitig, ohne externe Skripte/Schriften)
 * =======================================================================================
 * site_render($site, $lang, $opt) → komplette HTML-Seite (Start, Impressum, Datenschutz) für:
 *   public  veröffentlichte Fassung (chat/site.php), preview  Entwurf mit markierten Platzhaltern (Studio-iframe), demo/thumb  Beispiel-Inhalte je Vorlage,
 *   export  statische Dateien für den ZIP-Export (relative Links, Bilder unter assets/).
 * Alle Inhalte werden escaped (htmlspecialchars); Platzhalter [[…]] erscheinen nur in der Vorschau (markiert), öffentlich nie.
 * Oberflächentexte der Website (SITE_UI) in 13 Sprachen; Inhalte kommen aus den Daten der Website (texts[lang], Fallback Standardsprache).
 */
require_once __DIR__.'/site-lib.php';

const SITE_UI = [
'de'=>['skip'=>'Zum Inhalt springen','menu'=>'Menü','lang'=>'Sprache','call'=>'Anrufen','whatsapp'=>'WhatsApp','email'=>'E-Mail','book'=>'Termin buchen','order'=>'Jetzt bestellen','chat'=>'Chat starten','room'=>'Zimmer anfragen','ride'=>'Taxi bestellen','contact_cta'=>'Kontakt aufnehmen','callback'=>'Rückruf anfordern',
  's_services'=>'Leistungen','s_menu'=>'Speisekarte','s_rooms'=>'Zimmer','s_about'=>'Über uns','s_team'=>'Team','s_gallery'=>'Eindrücke','s_hours'=>'Öffnungszeiten','s_location'=>'Anfahrt','s_reviews'=>'Das sagen unsere Kunden','s_faq'=>'Häufige Fragen','s_contact'=>'Kontakt',
  'closed'=>'geschlossen','hours_none'=>'Öffnungszeiten auf Anfrage','route'=>'Route planen','map'=>'Auf der Karte ansehen','map_note'=>'Karte und Route öffnen sich erst nach Klick auf OpenStreetMap.','from'=>'ab','per_night'=>'pro Nacht','guests'=>'bis {n} Personen','min'=>'{n} Min.',
  'reviews_note'=>'Ausgewählte Kundenstimmen – eigene Angaben des Betriebs.','f_name'=>'Name','f_contact'=>'E-Mail oder Telefon','f_msg'=>'Ihre Nachricht','f_send'=>'Nachricht senden','f_consent'=>'Ihre Angaben werden nur zur Bearbeitung Ihrer Anfrage verwendet. Mehr in der {privacy}.',
  'f_ok'=>'Vielen Dank! Ihre Nachricht ist angekommen – wir melden uns.','f_err'=>'Bitte Name, Kontakt und Nachricht vollständig angeben.','f_rate'=>'Gerade gehen sehr viele Anfragen ein. Bitte versuchen Sie es später erneut.','f_off'=>'Das Kontaktformular ist nach der Veröffentlichung aktiv.',
  'imprint'=>'Impressum','privacy'=>'Datenschutz','privacy_full'=>'Datenschutzerklärung','made'=>'Website erstellt mit ChatHelp','back'=>'Zur Startseite','ph'=>'Bitte ergänzen','draft'=>'Vorschau – Entwurf, nur für dich sichtbar','demo'=>'Beispiel-Website','nf_t'=>'Seite nicht gefunden','nf_p'=>'Diese Website gibt es nicht oder sie ist noch nicht veröffentlicht.',
  'im_provider'=>'Anbieter','im_rep'=>'Vertreten durch','im_contact'=>'Kontakt','im_register'=>'Registereintrag','im_vat'=>'Umsatzsteuer-ID','im_authority'=>'Aufsichtsbehörde / Kammer','im_content'=>'Verantwortlich für den Inhalt','phone'=>'Telefon','address'=>'Adresse',
  'pv_ctrl_h'=>'Verantwortlicher','pv_host_h'=>'Bereitstellung der Website','pv_host'=>'Diese Website wird von ChatHelp (chat-help.com, Hosting in der EU) im Auftrag des oben genannten Anbieters bereitgestellt. Beim Aufruf verarbeitet der Server technisch notwendige Daten (IP-Adresse, Datum und Uhrzeit, aufgerufene Seite, Browser-Angaben), um die Seite auszuliefern und vor Missbrauch zu schützen (Art. 6 Abs. 1 lit. f DSGVO). Es werden keine Werbe- oder Analyse-Cookies gesetzt, kein Tracking eingesetzt und keine externen Schriftarten geladen.',
  'pv_form_h'=>'Kontaktformular','pv_form'=>'Wenn Sie das Kontaktformular nutzen, werden Name, Kontaktangabe und Nachricht verarbeitet, um Ihre Anfrage zu beantworten (Art. 6 Abs. 1 lit. b DSGVO). Die IP-Adresse wird nur als Prüfsumme zum Schutz vor Missbrauch gespeichert. Die Daten werden gelöscht, sobald sie für die Bearbeitung nicht mehr erforderlich sind.',
  'pv_chat_h'=>'Chat-Assistent','pv_chat'=>'Der Chat-Assistent beantwortet Fragen mithilfe künstlicher Intelligenz (Dienstleister: ChatHelp). Ihre Nachrichten werden dafür verarbeitet und dem Anbieter zur Verfügung gestellt. Bitte geben Sie im Chat keine sensiblen Daten (z. B. Gesundheitsdaten) ein.',
  'pv_links_h'=>'Karten und externe Links','pv_links'=>'Links zu Karten (OpenStreetMap), WhatsApp oder Buchungsseiten öffnen externe Angebote erst nach Ihrem Klick. Dort gelten die Datenschutzhinweise des jeweiligen Anbieters.',
  'pv_rights_h'=>'Ihre Rechte','pv_rights'=>'Sie haben das Recht auf Auskunft, Berichtigung, Löschung, Einschränkung der Verarbeitung, Widerspruch und Datenübertragbarkeit sowie auf Beschwerde bei einer Datenschutz-Aufsichtsbehörde. Wenden Sie sich dafür an: {email}.','pv_more'=>'Ergänzende Hinweise',
  'days'=>'Mo,Di,Mi,Do,Fr,Sa,So','stars'=>'{n} von 5 Sternen'],
'en'=>['skip'=>'Skip to content','menu'=>'Menu','lang'=>'Language','call'=>'Call','whatsapp'=>'WhatsApp','email'=>'Email','book'=>'Book an appointment','order'=>'Order now','chat'=>'Start chat','room'=>'Request a room','ride'=>'Book a taxi','contact_cta'=>'Get in touch','callback'=>'Request a call back',
  's_services'=>'Services','s_menu'=>'Menu','s_rooms'=>'Rooms','s_about'=>'About us','s_team'=>'Team','s_gallery'=>'Impressions','s_hours'=>'Opening hours','s_location'=>'How to find us','s_reviews'=>'What our customers say','s_faq'=>'Frequently asked questions','s_contact'=>'Contact',
  'closed'=>'closed','hours_none'=>'Opening hours on request','route'=>'Get directions','map'=>'View on map','map_note'=>'Map and directions open on OpenStreetMap only after you click.','from'=>'from','per_night'=>'per night','guests'=>'up to {n} guests','min'=>'{n} min',
  'reviews_note'=>'Selected customer feedback – provided by the business.','f_name'=>'Name','f_contact'=>'Email or phone','f_msg'=>'Your message','f_send'=>'Send message','f_consent'=>'Your details are only used to handle your enquiry. More in our {privacy}.',
  'f_ok'=>'Thank you! Your message has arrived – we will get back to you.','f_err'=>'Please fill in your name, contact details and message.','f_rate'=>'We are receiving a lot of enquiries right now. Please try again later.','f_off'=>'The contact form will be active once the site is published.',
  'imprint'=>'Legal notice','privacy'=>'Privacy','privacy_full'=>'privacy policy','made'=>'Website made with ChatHelp','back'=>'Back to home','ph'=>'Please complete','draft'=>'Preview – draft, only visible to you','demo'=>'Example website','nf_t'=>'Page not found','nf_p'=>'This website does not exist or has not been published yet.',
  'im_provider'=>'Provider','im_rep'=>'Represented by','im_contact'=>'Contact','im_register'=>'Register entry','im_vat'=>'VAT ID','im_authority'=>'Supervisory authority / chamber','im_content'=>'Responsible for content','phone'=>'Phone','address'=>'Address',
  'pv_ctrl_h'=>'Controller','pv_host_h'=>'Hosting of this website','pv_host'=>'This website is provided by ChatHelp (chat-help.com, hosted in the EU) on behalf of the provider named above. When you visit it, the server processes technically necessary data (IP address, date and time, page requested, browser information) to deliver the page and protect it against misuse (Art. 6(1)(f) GDPR). No advertising or analytics cookies, no tracking and no external fonts are used.',
  'pv_form_h'=>'Contact form','pv_form'=>'If you use the contact form, your name, contact details and message are processed to answer your enquiry (Art. 6(1)(b) GDPR). The IP address is only stored as a checksum to prevent misuse. The data is deleted as soon as it is no longer needed for handling your enquiry.',
  'pv_chat_h'=>'Chat assistant','pv_chat'=>'The chat assistant answers questions using artificial intelligence (service provider: ChatHelp). Your messages are processed for this purpose and made available to the provider. Please do not enter sensitive data (e.g. health data) in the chat.',
  'pv_links_h'=>'Maps and external links','pv_links'=>'Links to maps (OpenStreetMap), WhatsApp or booking pages only open external services after you click. The privacy notices of the respective provider apply there.',
  'pv_rights_h'=>'Your rights','pv_rights'=>'You have the right to access, rectification, erasure, restriction of processing, objection and data portability, and the right to lodge a complaint with a data protection authority. Please contact: {email}.','pv_more'=>'Additional information',
  'days'=>'Mon,Tue,Wed,Thu,Fri,Sat,Sun','stars'=>'{n} out of 5 stars'],
'fr'=>['skip'=>'Aller au contenu','menu'=>'Menu','lang'=>'Langue','call'=>'Appeler','whatsapp'=>'WhatsApp','email'=>'E-mail','book'=>'Prendre rendez-vous','order'=>'Commander','chat'=>'Démarrer le chat','room'=>'Demander une chambre','ride'=>'Réserver un taxi','contact_cta'=>'Nous contacter','callback'=>'Être rappelé',
  's_services'=>'Prestations','s_menu'=>'La carte','s_rooms'=>'Chambres','s_about'=>'À propos','s_team'=>'L’équipe','s_gallery'=>'En images','s_hours'=>'Horaires d’ouverture','s_location'=>'Accès','s_reviews'=>'Ce que disent nos clients','s_faq'=>'Questions fréquentes','s_contact'=>'Contact',
  'closed'=>'fermé','hours_none'=>'Horaires sur demande','route'=>'Itinéraire','map'=>'Voir sur la carte','map_note'=>'La carte et l’itinéraire s’ouvrent sur OpenStreetMap uniquement après votre clic.','from'=>'à partir de','per_night'=>'par nuit','guests'=>'jusqu’à {n} personnes','min'=>'{n} min',
  'reviews_note'=>'Avis clients sélectionnés – informations fournies par l’établissement.','f_name'=>'Nom','f_contact'=>'E-mail ou téléphone','f_msg'=>'Votre message','f_send'=>'Envoyer le message','f_consent'=>'Vos données servent uniquement au traitement de votre demande. En savoir plus dans notre {privacy}.',
  'f_ok'=>'Merci ! Votre message est bien arrivé – nous revenons vers vous.','f_err'=>'Veuillez indiquer votre nom, vos coordonnées et votre message.','f_rate'=>'Nous recevons beaucoup de demandes en ce moment. Veuillez réessayer plus tard.','f_off'=>'Le formulaire de contact sera actif après la publication.',
  'imprint'=>'Mentions légales','privacy'=>'Confidentialité','privacy_full'=>'politique de confidentialité','made'=>'Site créé avec ChatHelp','back'=>'Retour à l’accueil','ph'=>'À compléter','draft'=>'Aperçu – brouillon, visible uniquement par vous','demo'=>'Site d’exemple','nf_t'=>'Page introuvable','nf_p'=>'Ce site n’existe pas ou n’est pas encore publié.',
  'im_provider'=>'Éditeur','im_rep'=>'Représenté par','im_contact'=>'Contact','im_register'=>'Immatriculation','im_vat'=>'N° de TVA','im_authority'=>'Autorité de contrôle / chambre','im_content'=>'Responsable du contenu','phone'=>'Téléphone','address'=>'Adresse',
  'pv_ctrl_h'=>'Responsable du traitement','pv_host_h'=>'Hébergement du site','pv_host'=>'Ce site est fourni par ChatHelp (chat-help.com, hébergement dans l’UE) pour le compte de l’éditeur mentionné ci-dessus. Lors de votre visite, le serveur traite des données techniquement nécessaires (adresse IP, date et heure, page consultée, informations du navigateur) afin d’afficher la page et de la protéger contre les abus (art. 6, par. 1, f RGPD). Aucun cookie publicitaire ou d’analyse, aucun suivi et aucune police externe ne sont utilisés.',
  'pv_form_h'=>'Formulaire de contact','pv_form'=>'Si vous utilisez le formulaire de contact, votre nom, vos coordonnées et votre message sont traités pour répondre à votre demande (art. 6, par. 1, b RGPD). L’adresse IP n’est conservée que sous forme d’empreinte pour prévenir les abus. Les données sont supprimées dès qu’elles ne sont plus nécessaires.',
  'pv_chat_h'=>'Assistant de chat','pv_chat'=>'L’assistant de chat répond aux questions à l’aide de l’intelligence artificielle (prestataire : ChatHelp). Vos messages sont traités à cette fin et mis à disposition de l’éditeur. Merci de ne pas saisir de données sensibles (par ex. de santé) dans le chat.',
  'pv_links_h'=>'Cartes et liens externes','pv_links'=>'Les liens vers les cartes (OpenStreetMap), WhatsApp ou les pages de réservation n’ouvrent des services externes qu’après votre clic. Les règles de confidentialité du prestataire concerné s’y appliquent.',
  'pv_rights_h'=>'Vos droits','pv_rights'=>'Vous disposez d’un droit d’accès, de rectification, d’effacement, de limitation du traitement, d’opposition et de portabilité, ainsi que du droit d’introduire une réclamation auprès d’une autorité de contrôle. Contact : {email}.','pv_more'=>'Informations complémentaires',
  'days'=>'lun.,mar.,mer.,jeu.,ven.,sam.,dim.','stars'=>'{n} sur 5 étoiles'],
'tr'=>['skip'=>'İçeriğe geç','menu'=>'Menü','lang'=>'Dil','call'=>'Ara','whatsapp'=>'WhatsApp','email'=>'E-posta','book'=>'Randevu al','order'=>'Hemen sipariş ver','chat'=>'Sohbeti başlat','room'=>'Oda talep et','ride'=>'Taksi çağır','contact_cta'=>'İletişime geç','callback'=>'Geri arama iste',
  's_services'=>'Hizmetler','s_menu'=>'Menü','s_rooms'=>'Odalar','s_about'=>'Hakkımızda','s_team'=>'Ekibimiz','s_gallery'=>'Görseller','s_hours'=>'Çalışma saatleri','s_location'=>'Ulaşım','s_reviews'=>'Müşterilerimiz ne diyor','s_faq'=>'Sık sorulan sorular','s_contact'=>'İletişim',
  'closed'=>'kapalı','hours_none'=>'Çalışma saatleri için bize ulaşın','route'=>'Yol tarifi al','map'=>'Haritada gör','map_note'=>'Harita ve yol tarifi yalnızca tıkladıktan sonra OpenStreetMap’te açılır.','from'=>'başlayan fiyatlarla','per_night'=>'gecelik','guests'=>'en fazla {n} kişi','min'=>'{n} dk',
  'reviews_note'=>'Seçilmiş müşteri yorumları – işletmenin kendi beyanıdır.','f_name'=>'Ad Soyad','f_contact'=>'E-posta veya telefon','f_msg'=>'Mesajınız','f_send'=>'Mesajı gönder','f_consent'=>'Bilgileriniz yalnızca talebinizi yanıtlamak için kullanılır. Ayrıntılar: {privacy}.',
  'f_ok'=>'Teşekkürler! Mesajınız ulaştı – size dönüş yapacağız.','f_err'=>'Lütfen ad, iletişim bilgisi ve mesaj alanlarını doldurun.','f_rate'=>'Şu anda çok sayıda talep alıyoruz. Lütfen daha sonra tekrar deneyin.','f_off'=>'İletişim formu yayınlandıktan sonra etkin olur.',
  'imprint'=>'Künye','privacy'=>'Gizlilik','privacy_full'=>'gizlilik politikası','made'=>'Bu site ChatHelp ile oluşturuldu','back'=>'Ana sayfaya dön','ph'=>'Lütfen tamamlayın','draft'=>'Önizleme – taslak, yalnızca size görünür','demo'=>'Örnek web sitesi','nf_t'=>'Sayfa bulunamadı','nf_p'=>'Bu web sitesi mevcut değil veya henüz yayınlanmadı.',
  'im_provider'=>'Hizmet sağlayıcı','im_rep'=>'Temsilci','im_contact'=>'İletişim','im_register'=>'Sicil kaydı','im_vat'=>'Vergi numarası','im_authority'=>'Denetim makamı / meslek odası','im_content'=>'İçerikten sorumlu','phone'=>'Telefon','address'=>'Adres',
  'pv_ctrl_h'=>'Veri sorumlusu','pv_host_h'=>'Web sitesinin sunulması','pv_host'=>'Bu web sitesi, yukarıda adı geçen hizmet sağlayıcı adına ChatHelp (chat-help.com, AB’de barındırma) tarafından sunulmaktadır. Ziyaretiniz sırasında sunucu, sayfayı göstermek ve kötüye kullanıma karşı korumak için teknik olarak gerekli verileri (IP adresi, tarih ve saat, açılan sayfa, tarayıcı bilgileri) işler (GDPR md. 6/1-f). Reklam veya analiz çerezi, takip aracı ya da harici yazı tipi kullanılmaz.',
  'pv_form_h'=>'İletişim formu','pv_form'=>'İletişim formunu kullandığınızda adınız, iletişim bilginiz ve mesajınız talebinizi yanıtlamak için işlenir (GDPR md. 6/1-b). IP adresi yalnızca kötüye kullanımı önlemek amacıyla sağlama değeri olarak saklanır. Veriler, artık gerekli olmadığında silinir.',
  'pv_chat_h'=>'Sohbet asistanı','pv_chat'=>'Sohbet asistanı soruları yapay zekâ yardımıyla yanıtlar (hizmet sağlayıcı: ChatHelp). Mesajlarınız bu amaçla işlenir ve işletmeye iletilir. Lütfen sohbete hassas veriler (ör. sağlık bilgileri) yazmayın.',
  'pv_links_h'=>'Haritalar ve harici bağlantılar','pv_links'=>'Harita (OpenStreetMap), WhatsApp veya rezervasyon sayfalarına giden bağlantılar harici hizmetleri yalnızca tıklamanızdan sonra açar. Orada ilgili sağlayıcının gizlilik koşulları geçerlidir.',
  'pv_rights_h'=>'Haklarınız','pv_rights'=>'Bilgi alma, düzeltme, silme, işlemenin kısıtlanması, itiraz ve veri taşınabilirliği haklarına ve bir veri koruma denetim makamına şikâyette bulunma hakkına sahipsiniz. İletişim: {email}.','pv_more'=>'Ek bilgiler',
  'days'=>'Pzt,Sal,Çar,Per,Cum,Cmt,Paz','stars'=>'5 üzerinden {n} yıldız'],
'az'=>['skip'=>'Məzmuna keç','menu'=>'Menyu','lang'=>'Dil','call'=>'Zəng et','whatsapp'=>'WhatsApp','email'=>'E-poçt','book'=>'Növbə götür','order'=>'İndi sifariş et','chat'=>'Söhbətə başla','room'=>'Otaq sorğusu','ride'=>'Taksi sifariş et','contact_cta'=>'Əlaqə saxla','callback'=>'Geri zəng istə',
  's_services'=>'Xidmətlər','s_menu'=>'Menyu','s_rooms'=>'Otaqlar','s_about'=>'Haqqımızda','s_team'=>'Komandamız','s_gallery'=>'Görüntülər','s_hours'=>'İş saatları','s_location'=>'Necə gəlmək olar','s_reviews'=>'Müştərilərimiz nə deyir','s_faq'=>'Tez-tez verilən suallar','s_contact'=>'Əlaqə',
  'closed'=>'bağlıdır','hours_none'=>'İş saatları sorğu ilə','route'=>'Marşrut qur','map'=>'Xəritədə bax','map_note'=>'Xəritə və marşrut yalnız kliklədikdən sonra OpenStreetMap-də açılır.','from'=>'başlayaraq','per_night'=>'bir gecə','guests'=>'{n} nəfərədək','min'=>'{n} dəq',
  'reviews_note'=>'Seçilmiş müştəri rəyləri – müəssisənin öz məlumatıdır.','f_name'=>'Ad','f_contact'=>'E-poçt və ya telefon','f_msg'=>'Mesajınız','f_send'=>'Mesajı göndər','f_consent'=>'Məlumatlarınız yalnız sorğunuzu cavablandırmaq üçün istifadə olunur. Ətraflı: {privacy}.',
  'f_ok'=>'Təşəkkür edirik! Mesajınız çatdı – sizinlə əlaqə saxlayacağıq.','f_err'=>'Zəhmət olmasa ad, əlaqə məlumatı və mesajı doldurun.','f_rate'=>'Hazırda çoxlu sorğu daxil olur. Zəhmət olmasa bir az sonra yenidən cəhd edin.','f_off'=>'Əlaqə forması dərc edildikdən sonra aktiv olacaq.',
  'imprint'=>'Hüquqi məlumat','privacy'=>'Məxfilik','privacy_full'=>'məxfilik siyasəti','made'=>'Sayt ChatHelp ilə yaradılıb','back'=>'Ana səhifəyə qayıt','ph'=>'Zəhmət olmasa tamamlayın','draft'=>'Önbaxış – qaralama, yalnız sizə görünür','demo'=>'Nümunə sayt','nf_t'=>'Səhifə tapılmadı','nf_p'=>'Bu sayt mövcud deyil və ya hələ dərc edilməyib.',
  'im_provider'=>'Xidmət göstərən','im_rep'=>'Təmsil edən','im_contact'=>'Əlaqə','im_register'=>'Reyestr qeydi','im_vat'=>'VÖEN','im_authority'=>'Nəzarət orqanı / palata','im_content'=>'Məzmuna görə məsul','phone'=>'Telefon','address'=>'Ünvan',
  'pv_ctrl_h'=>'Məsul şəxs','pv_host_h'=>'Saytın təqdim edilməsi','pv_host'=>'Bu sayt yuxarıda göstərilən xidmət göstərənin adından ChatHelp (chat-help.com, Aİ-də hostinq) tərəfindən təqdim olunur. Ziyarət zamanı server səhifəni göstərmək və sui-istifadədən qorumaq üçün texniki cəhətdən zəruri məlumatları (IP ünvanı, tarix və saat, açılan səhifə, brauzer məlumatları) emal edir (GDPR, maddə 6(1)(f)). Reklam və ya analitika kukiləri, izləmə və xarici şriftlər istifadə edilmir.',
  'pv_form_h'=>'Əlaqə forması','pv_form'=>'Əlaqə formasından istifadə etdikdə adınız, əlaqə məlumatınız və mesajınız sorğunuzu cavablandırmaq üçün emal olunur (GDPR, maddə 6(1)(b)). IP ünvanı yalnız sui-istifadənin qarşısını almaq üçün yoxlama dəyəri kimi saxlanılır. Məlumatlar artıq lazım olmadıqda silinir.',
  'pv_chat_h'=>'Söhbət köməkçisi','pv_chat'=>'Söhbət köməkçisi sualları süni intellektin köməyi ilə cavablandırır (xidmət göstərən: ChatHelp). Mesajlarınız bu məqsədlə emal olunur və müəssisəyə təqdim edilir. Zəhmət olmasa söhbətdə həssas məlumatlar (məs. sağlamlıq məlumatları) yazmayın.',
  'pv_links_h'=>'Xəritələr və xarici keçidlər','pv_links'=>'Xəritə (OpenStreetMap), WhatsApp və ya rezervasiya səhifələrinə keçidlər xarici xidmətləri yalnız kliklədikdən sonra açır. Orada müvafiq xidmət göstərənin məxfilik qaydaları tətbiq olunur.',
  'pv_rights_h'=>'Hüquqlarınız','pv_rights'=>'Məlumat almaq, düzəliş, silinmə, emalın məhdudlaşdırılması, etiraz və məlumatların daşınması hüquqlarınız, həmçinin məlumatların qorunması üzrə nəzarət orqanına şikayət etmək hüququnuz var. Əlaqə: {email}.','pv_more'=>'Əlavə məlumat',
  'days'=>'B.e.,Ç.a.,Ç.,C.a.,C.,Ş.,B.','stars'=>'5 ulduzdan {n}'],
'es'=>['skip'=>'Ir al contenido','menu'=>'Menú','lang'=>'Idioma','call'=>'Llamar','whatsapp'=>'WhatsApp','email'=>'Correo','book'=>'Pedir cita','order'=>'Pedir ahora','chat'=>'Iniciar chat','room'=>'Solicitar habitación','ride'=>'Pedir taxi','contact_cta'=>'Contactar','callback'=>'Solicitar llamada',
  's_services'=>'Servicios','s_menu'=>'La carta','s_rooms'=>'Habitaciones','s_about'=>'Sobre nosotros','s_team'=>'Equipo','s_gallery'=>'Imágenes','s_hours'=>'Horario','s_location'=>'Cómo llegar','s_reviews'=>'Lo que dicen nuestros clientes','s_faq'=>'Preguntas frecuentes','s_contact'=>'Contacto',
  'closed'=>'cerrado','hours_none'=>'Horario bajo consulta','route'=>'Cómo llegar','map'=>'Ver en el mapa','map_note'=>'El mapa y la ruta se abren en OpenStreetMap solo después de hacer clic.','from'=>'desde','per_night'=>'por noche','guests'=>'hasta {n} personas','min'=>'{n} min',
  'reviews_note'=>'Opiniones seleccionadas – información facilitada por el negocio.','f_name'=>'Nombre','f_contact'=>'Correo o teléfono','f_msg'=>'Su mensaje','f_send'=>'Enviar mensaje','f_consent'=>'Sus datos solo se usan para tramitar su consulta. Más información en nuestra {privacy}.',
  'f_ok'=>'¡Gracias! Hemos recibido su mensaje y le responderemos pronto.','f_err'=>'Indique su nombre, un dato de contacto y su mensaje.','f_rate'=>'Ahora mismo recibimos muchas consultas. Inténtelo de nuevo más tarde.','f_off'=>'El formulario de contacto se activará tras la publicación.',
  'imprint'=>'Aviso legal','privacy'=>'Privacidad','privacy_full'=>'política de privacidad','made'=>'Web creada con ChatHelp','back'=>'Volver al inicio','ph'=>'Completar, por favor','draft'=>'Vista previa – borrador, solo visible para ti','demo'=>'Web de ejemplo','nf_t'=>'Página no encontrada','nf_p'=>'Esta web no existe o aún no se ha publicado.',
  'im_provider'=>'Titular','im_rep'=>'Representado por','im_contact'=>'Contacto','im_register'=>'Inscripción registral','im_vat'=>'NIF/CIF','im_authority'=>'Autoridad de control / colegio profesional','im_content'=>'Responsable del contenido','phone'=>'Teléfono','address'=>'Dirección',
  'pv_ctrl_h'=>'Responsable del tratamiento','pv_host_h'=>'Alojamiento de la web','pv_host'=>'Esta web la ofrece ChatHelp (chat-help.com, alojamiento en la UE) por encargo del titular indicado arriba. Al visitarla, el servidor trata datos técnicamente necesarios (dirección IP, fecha y hora, página visitada, datos del navegador) para mostrar la página y protegerla frente a abusos (art. 6.1.f RGPD). No se usan cookies publicitarias ni de análisis, ni seguimiento, ni fuentes externas.',
  'pv_form_h'=>'Formulario de contacto','pv_form'=>'Si utiliza el formulario de contacto, se tratan su nombre, su dato de contacto y su mensaje para responder a su consulta (art. 6.1.b RGPD). La dirección IP solo se guarda como suma de verificación para evitar abusos. Los datos se eliminan cuando ya no son necesarios.',
  'pv_chat_h'=>'Asistente de chat','pv_chat'=>'El asistente de chat responde preguntas con ayuda de inteligencia artificial (proveedor: ChatHelp). Sus mensajes se tratan con este fin y se ponen a disposición del titular. Por favor, no introduzca datos sensibles (p. ej., de salud) en el chat.',
  'pv_links_h'=>'Mapas y enlaces externos','pv_links'=>'Los enlaces a mapas (OpenStreetMap), WhatsApp o páginas de reserva solo abren servicios externos después de hacer clic. Allí se aplica la política de privacidad del proveedor correspondiente.',
  'pv_rights_h'=>'Sus derechos','pv_rights'=>'Tiene derecho de acceso, rectificación, supresión, limitación del tratamiento, oposición y portabilidad, así como a presentar una reclamación ante una autoridad de protección de datos. Contacto: {email}.','pv_more'=>'Información adicional',
  'days'=>'lun,mar,mié,jue,vie,sáb,dom','stars'=>'{n} de 5 estrellas'],
'it'=>['skip'=>'Vai al contenuto','menu'=>'Menu','lang'=>'Lingua','call'=>'Chiama','whatsapp'=>'WhatsApp','email'=>'E-mail','book'=>'Prenota un appuntamento','order'=>'Ordina ora','chat'=>'Avvia la chat','room'=>'Richiedi una camera','ride'=>'Prenota un taxi','contact_cta'=>'Contattaci','callback'=>'Richiedi una richiamata',
  's_services'=>'Servizi','s_menu'=>'Il menù','s_rooms'=>'Camere','s_about'=>'Chi siamo','s_team'=>'Il team','s_gallery'=>'Immagini','s_hours'=>'Orari di apertura','s_location'=>'Come raggiungerci','s_reviews'=>'Cosa dicono i nostri clienti','s_faq'=>'Domande frequenti','s_contact'=>'Contatti',
  'closed'=>'chiuso','hours_none'=>'Orari su richiesta','route'=>'Indicazioni stradali','map'=>'Vedi sulla mappa','map_note'=>'Mappa e percorso si aprono su OpenStreetMap solo dopo il tuo clic.','from'=>'da','per_night'=>'a notte','guests'=>'fino a {n} persone','min'=>'{n} min',
  'reviews_note'=>'Recensioni selezionate – indicazioni fornite dall’attività.','f_name'=>'Nome','f_contact'=>'E-mail o telefono','f_msg'=>'Il tuo messaggio','f_send'=>'Invia messaggio','f_consent'=>'I tuoi dati sono usati solo per gestire la richiesta. Maggiori informazioni nella {privacy}.',
  'f_ok'=>'Grazie! Il tuo messaggio è arrivato – ti risponderemo presto.','f_err'=>'Indica nome, recapito e messaggio.','f_rate'=>'Stiamo ricevendo molte richieste. Riprova più tardi.','f_off'=>'Il modulo di contatto sarà attivo dopo la pubblicazione.',
  'imprint'=>'Note legali','privacy'=>'Privacy','privacy_full'=>'informativa sulla privacy','made'=>'Sito creato con ChatHelp','back'=>'Torna alla home','ph'=>'Da completare','draft'=>'Anteprima – bozza, visibile solo a te','demo'=>'Sito di esempio','nf_t'=>'Pagina non trovata','nf_p'=>'Questo sito non esiste o non è ancora stato pubblicato.',
  'im_provider'=>'Titolare','im_rep'=>'Rappresentato da','im_contact'=>'Contatti','im_register'=>'Iscrizione al registro','im_vat'=>'Partita IVA','im_authority'=>'Autorità di vigilanza / ordine','im_content'=>'Responsabile dei contenuti','phone'=>'Telefono','address'=>'Indirizzo',
  'pv_ctrl_h'=>'Titolare del trattamento','pv_host_h'=>'Hosting del sito','pv_host'=>'Questo sito è fornito da ChatHelp (chat-help.com, hosting nell’UE) per conto del titolare indicato sopra. Durante la visita il server tratta dati tecnicamente necessari (indirizzo IP, data e ora, pagina visitata, dati del browser) per mostrare la pagina e proteggerla da abusi (art. 6, par. 1, lett. f GDPR). Non vengono usati cookie pubblicitari o di analisi, tracciamento o font esterni.',
  'pv_form_h'=>'Modulo di contatto','pv_form'=>'Se usi il modulo di contatto, nome, recapito e messaggio vengono trattati per rispondere alla tua richiesta (art. 6, par. 1, lett. b GDPR). L’indirizzo IP viene conservato solo come checksum per prevenire abusi. I dati vengono cancellati quando non sono più necessari.',
  'pv_chat_h'=>'Assistente chat','pv_chat'=>'L’assistente chat risponde alle domande con l’aiuto dell’intelligenza artificiale (fornitore: ChatHelp). I tuoi messaggi vengono trattati a questo scopo e messi a disposizione del titolare. Non inserire dati sensibili (ad es. sanitari) nella chat.',
  'pv_links_h'=>'Mappe e link esterni','pv_links'=>'I link a mappe (OpenStreetMap), WhatsApp o pagine di prenotazione aprono servizi esterni solo dopo il tuo clic. Lì si applica l’informativa privacy del rispettivo fornitore.',
  'pv_rights_h'=>'I tuoi diritti','pv_rights'=>'Hai diritto di accesso, rettifica, cancellazione, limitazione del trattamento, opposizione e portabilità dei dati, nonché di proporre reclamo a un’autorità di controllo. Contatto: {email}.','pv_more'=>'Informazioni aggiuntive',
  'days'=>'lun,mar,mer,gio,ven,sab,dom','stars'=>'{n} su 5 stelle'],
'ru'=>['skip'=>'Перейти к содержанию','menu'=>'Меню','lang'=>'Язык','call'=>'Позвонить','whatsapp'=>'WhatsApp','email'=>'E-mail','book'=>'Записаться','order'=>'Заказать','chat'=>'Открыть чат','room'=>'Запросить номер','ride'=>'Заказать такси','contact_cta'=>'Связаться','callback'=>'Заказать звонок',
  's_services'=>'Услуги','s_menu'=>'Меню','s_rooms'=>'Номера','s_about'=>'О нас','s_team'=>'Команда','s_gallery'=>'Фотографии','s_hours'=>'Часы работы','s_location'=>'Как добраться','s_reviews'=>'Отзывы клиентов','s_faq'=>'Частые вопросы','s_contact'=>'Контакты',
  'closed'=>'выходной','hours_none'=>'Часы работы по запросу','route'=>'Построить маршрут','map'=>'Открыть на карте','map_note'=>'Карта и маршрут открываются в OpenStreetMap только после нажатия.','from'=>'от','per_night'=>'за ночь','guests'=>'до {n} гостей','min'=>'{n} мин',
  'reviews_note'=>'Избранные отзывы клиентов – данные предоставлены компанией.','f_name'=>'Имя','f_contact'=>'E-mail или телефон','f_msg'=>'Ваше сообщение','f_send'=>'Отправить','f_consent'=>'Ваши данные используются только для обработки запроса. Подробнее: {privacy}.',
  'f_ok'=>'Спасибо! Ваше сообщение получено – мы свяжемся с вами.','f_err'=>'Пожалуйста, укажите имя, контакт и сообщение.','f_rate'=>'Сейчас поступает много запросов. Пожалуйста, попробуйте позже.','f_off'=>'Форма обратной связи заработает после публикации.',
  'imprint'=>'Выходные данные','privacy'=>'Конфиденциальность','privacy_full'=>'политике конфиденциальности','made'=>'Сайт создан с ChatHelp','back'=>'На главную','ph'=>'Заполните, пожалуйста','draft'=>'Предпросмотр – черновик, виден только вам','demo'=>'Пример сайта','nf_t'=>'Страница не найдена','nf_p'=>'Такого сайта нет или он ещё не опубликован.',
  'im_provider'=>'Владелец сайта','im_rep'=>'Представитель','im_contact'=>'Контакты','im_register'=>'Регистрационные данные','im_vat'=>'ИНН / VAT','im_authority'=>'Надзорный орган / палата','im_content'=>'Ответственный за содержание','phone'=>'Телефон','address'=>'Адрес',
  'pv_ctrl_h'=>'Ответственное лицо','pv_host_h'=>'Размещение сайта','pv_host'=>'Сайт предоставляется сервисом ChatHelp (chat-help.com, хостинг в ЕС) по поручению указанного выше владельца. При посещении сервер обрабатывает технически необходимые данные (IP-адрес, дату и время, открытую страницу, данные браузера), чтобы показать страницу и защитить её от злоупотреблений (ст. 6(1)(f) GDPR). Рекламные и аналитические cookie, трекинг и внешние шрифты не используются.',
  'pv_form_h'=>'Форма обратной связи','pv_form'=>'При использовании формы ваше имя, контакт и сообщение обрабатываются для ответа на запрос (ст. 6(1)(b) GDPR). IP-адрес сохраняется только в виде контрольной суммы для защиты от злоупотреблений. Данные удаляются, когда они больше не нужны.',
  'pv_chat_h'=>'Чат-ассистент','pv_chat'=>'Чат-ассистент отвечает на вопросы с помощью искусственного интеллекта (поставщик: ChatHelp). Ваши сообщения обрабатываются для этой цели и передаются владельцу сайта. Пожалуйста, не вводите в чат чувствительные данные (например, о здоровье).',
  'pv_links_h'=>'Карты и внешние ссылки','pv_links'=>'Ссылки на карты (OpenStreetMap), WhatsApp или страницы бронирования открывают внешние сервисы только после нажатия. Там действуют правила конфиденциальности соответствующего поставщика.',
  'pv_rights_h'=>'Ваши права','pv_rights'=>'Вы имеете право на доступ, исправление, удаление, ограничение обработки, возражение и переносимость данных, а также право подать жалобу в надзорный орган. Контакт: {email}.','pv_more'=>'Дополнительная информация',
  'days'=>'Пн,Вт,Ср,Чт,Пт,Сб,Вс','stars'=>'{n} из 5 звёзд'],
'nl'=>['skip'=>'Naar de inhoud','menu'=>'Menu','lang'=>'Taal','call'=>'Bellen','whatsapp'=>'WhatsApp','email'=>'E-mail','book'=>'Afspraak maken','order'=>'Nu bestellen','chat'=>'Chat starten','room'=>'Kamer aanvragen','ride'=>'Taxi bestellen','contact_cta'=>'Contact opnemen','callback'=>'Terugbelverzoek',
  's_services'=>'Diensten','s_menu'=>'Menukaart','s_rooms'=>'Kamers','s_about'=>'Over ons','s_team'=>'Team','s_gallery'=>'Impressies','s_hours'=>'Openingstijden','s_location'=>'Route','s_reviews'=>'Wat onze klanten zeggen','s_faq'=>'Veelgestelde vragen','s_contact'=>'Contact',
  'closed'=>'gesloten','hours_none'=>'Openingstijden op aanvraag','route'=>'Route plannen','map'=>'Bekijk op de kaart','map_note'=>'Kaart en route openen pas na je klik op OpenStreetMap.','from'=>'vanaf','per_night'=>'per nacht','guests'=>'tot {n} personen','min'=>'{n} min',
  'reviews_note'=>'Geselecteerde klantreacties – eigen opgave van het bedrijf.','f_name'=>'Naam','f_contact'=>'E-mail of telefoon','f_msg'=>'Je bericht','f_send'=>'Bericht versturen','f_consent'=>'Je gegevens worden alleen gebruikt om je aanvraag te behandelen. Meer in onze {privacy}.',
  'f_ok'=>'Bedankt! Je bericht is aangekomen – we nemen contact met je op.','f_err'=>'Vul je naam, contactgegevens en bericht in.','f_rate'=>'We ontvangen nu veel aanvragen. Probeer het later opnieuw.','f_off'=>'Het contactformulier is actief na publicatie.',
  'imprint'=>'Colofon','privacy'=>'Privacy','privacy_full'=>'privacyverklaring','made'=>'Website gemaakt met ChatHelp','back'=>'Naar de startpagina','ph'=>'Aanvullen a.u.b.','draft'=>'Voorbeeld – concept, alleen zichtbaar voor jou','demo'=>'Voorbeeldwebsite','nf_t'=>'Pagina niet gevonden','nf_p'=>'Deze website bestaat niet of is nog niet gepubliceerd.',
  'im_provider'=>'Aanbieder','im_rep'=>'Vertegenwoordigd door','im_contact'=>'Contact','im_register'=>'Inschrijving (KvK)','im_vat'=>'Btw-nummer','im_authority'=>'Toezichthouder / beroepsorganisatie','im_content'=>'Verantwoordelijk voor de inhoud','phone'=>'Telefoon','address'=>'Adres',
  'pv_ctrl_h'=>'Verwerkingsverantwoordelijke','pv_host_h'=>'Hosting van de website','pv_host'=>'Deze website wordt in opdracht van de hierboven genoemde aanbieder geleverd door ChatHelp (chat-help.com, hosting in de EU). Bij een bezoek verwerkt de server technisch noodzakelijke gegevens (IP-adres, datum en tijd, opgevraagde pagina, browsergegevens) om de pagina te tonen en tegen misbruik te beschermen (art. 6 lid 1 sub f AVG). Er worden geen advertentie- of analysecookies, geen tracking en geen externe lettertypen gebruikt.',
  'pv_form_h'=>'Contactformulier','pv_form'=>'Als je het contactformulier gebruikt, worden je naam, contactgegevens en bericht verwerkt om je aanvraag te beantwoorden (art. 6 lid 1 sub b AVG). Het IP-adres wordt alleen als controlegetal opgeslagen om misbruik te voorkomen. De gegevens worden verwijderd zodra ze niet meer nodig zijn.',
  'pv_chat_h'=>'Chatassistent','pv_chat'=>'De chatassistent beantwoordt vragen met behulp van kunstmatige intelligentie (dienstverlener: ChatHelp). Je berichten worden daarvoor verwerkt en aan de aanbieder ter beschikking gesteld. Voer geen gevoelige gegevens (bijv. over gezondheid) in de chat in.',
  'pv_links_h'=>'Kaarten en externe links','pv_links'=>'Links naar kaarten (OpenStreetMap), WhatsApp of boekingspagina’s openen externe diensten pas na je klik. Daar geldt het privacybeleid van de betreffende aanbieder.',
  'pv_rights_h'=>'Je rechten','pv_rights'=>'Je hebt recht op inzage, rectificatie, verwijdering, beperking van de verwerking, bezwaar en gegevensoverdraagbaarheid, en het recht een klacht in te dienen bij een toezichthouder. Contact: {email}.','pv_more'=>'Aanvullende informatie',
  'days'=>'ma,di,wo,do,vr,za,zo','stars'=>'{n} van 5 sterren'],
'sv'=>['skip'=>'Hoppa till innehållet','menu'=>'Meny','lang'=>'Språk','call'=>'Ring','whatsapp'=>'WhatsApp','email'=>'E-post','book'=>'Boka tid','order'=>'Beställ nu','chat'=>'Starta chatt','room'=>'Förfrågan om rum','ride'=>'Beställ taxi','contact_cta'=>'Kontakta oss','callback'=>'Be om att bli uppringd',
  's_services'=>'Tjänster','s_menu'=>'Meny','s_rooms'=>'Rum','s_about'=>'Om oss','s_team'=>'Teamet','s_gallery'=>'Bilder','s_hours'=>'Öppettider','s_location'=>'Hitta hit','s_reviews'=>'Det säger våra kunder','s_faq'=>'Vanliga frågor','s_contact'=>'Kontakt',
  'closed'=>'stängt','hours_none'=>'Öppettider på förfrågan','route'=>'Vägbeskrivning','map'=>'Visa på kartan','map_note'=>'Karta och vägbeskrivning öppnas på OpenStreetMap först när du klickar.','from'=>'från','per_night'=>'per natt','guests'=>'upp till {n} personer','min'=>'{n} min',
  'reviews_note'=>'Utvalda kundomdömen – uppgifter från företaget.','f_name'=>'Namn','f_contact'=>'E-post eller telefon','f_msg'=>'Ditt meddelande','f_send'=>'Skicka meddelande','f_consent'=>'Dina uppgifter används bara för att hantera din förfrågan. Mer i vår {privacy}.',
  'f_ok'=>'Tack! Ditt meddelande har kommit fram – vi hör av oss.','f_err'=>'Fyll i namn, kontaktuppgift och meddelande.','f_rate'=>'Vi får många förfrågningar just nu. Försök igen senare.','f_off'=>'Kontaktformuläret aktiveras när webbplatsen publiceras.',
  'imprint'=>'Juridisk information','privacy'=>'Integritet','privacy_full'=>'integritetspolicy','made'=>'Webbplats skapad med ChatHelp','back'=>'Till startsidan','ph'=>'Fyll i','draft'=>'Förhandsvisning – utkast, bara synligt för dig','demo'=>'Exempelwebbplats','nf_t'=>'Sidan hittades inte','nf_p'=>'Webbplatsen finns inte eller är inte publicerad ännu.',
  'im_provider'=>'Ansvarig utgivare','im_rep'=>'Företrädd av','im_contact'=>'Kontakt','im_register'=>'Registrering','im_vat'=>'Momsregistreringsnummer','im_authority'=>'Tillsynsmyndighet / branschorgan','im_content'=>'Ansvarig för innehållet','phone'=>'Telefon','address'=>'Adress',
  'pv_ctrl_h'=>'Personuppgiftsansvarig','pv_host_h'=>'Drift av webbplatsen','pv_host'=>'Webbplatsen tillhandahålls av ChatHelp (chat-help.com, drift inom EU) på uppdrag av ovan nämnda utgivare. Vid besöket behandlar servern tekniskt nödvändiga uppgifter (IP-adress, datum och tid, besökt sida, webbläsaruppgifter) för att visa sidan och skydda den mot missbruk (art. 6.1 f GDPR). Inga reklam- eller analyscookies, ingen spårning och inga externa typsnitt används.',
  'pv_form_h'=>'Kontaktformulär','pv_form'=>'När du använder kontaktformuläret behandlas namn, kontaktuppgift och meddelande för att besvara din förfrågan (art. 6.1 b GDPR). IP-adressen sparas endast som kontrollsumma för att förhindra missbruk. Uppgifterna raderas när de inte längre behövs.',
  'pv_chat_h'=>'Chattassistent','pv_chat'=>'Chattassistenten besvarar frågor med hjälp av artificiell intelligens (tjänsteleverantör: ChatHelp). Dina meddelanden behandlas för detta och görs tillgängliga för utgivaren. Ange inga känsliga uppgifter (t.ex. hälsouppgifter) i chatten.',
  'pv_links_h'=>'Kartor och externa länkar','pv_links'=>'Länkar till kartor (OpenStreetMap), WhatsApp eller bokningssidor öppnar externa tjänster först när du klickar. Där gäller respektive leverantörs integritetspolicy.',
  'pv_rights_h'=>'Dina rättigheter','pv_rights'=>'Du har rätt till tillgång, rättelse, radering, begränsning av behandling, invändning och dataportabilitet samt rätt att klaga hos en tillsynsmyndighet. Kontakt: {email}.','pv_more'=>'Kompletterande information',
  'days'=>'mån,tis,ons,tor,fre,lör,sön','stars'=>'{n} av 5 stjärnor'],
'no'=>['skip'=>'Gå til innholdet','menu'=>'Meny','lang'=>'Språk','call'=>'Ring','whatsapp'=>'WhatsApp','email'=>'E-post','book'=>'Bestill time','order'=>'Bestill nå','chat'=>'Start chat','room'=>'Forespør rom','ride'=>'Bestill taxi','contact_cta'=>'Ta kontakt','callback'=>'Be om å bli ringt opp',
  's_services'=>'Tjenester','s_menu'=>'Meny','s_rooms'=>'Rom','s_about'=>'Om oss','s_team'=>'Teamet','s_gallery'=>'Bilder','s_hours'=>'Åpningstider','s_location'=>'Veibeskrivelse','s_reviews'=>'Dette sier kundene våre','s_faq'=>'Vanlige spørsmål','s_contact'=>'Kontakt',
  'closed'=>'stengt','hours_none'=>'Åpningstider på forespørsel','route'=>'Veibeskrivelse','map'=>'Vis på kartet','map_note'=>'Kart og rute åpnes på OpenStreetMap først når du klikker.','from'=>'fra','per_night'=>'per natt','guests'=>'opptil {n} personer','min'=>'{n} min',
  'reviews_note'=>'Utvalgte kundeomtaler – opplysninger fra bedriften.','f_name'=>'Navn','f_contact'=>'E-post eller telefon','f_msg'=>'Din melding','f_send'=>'Send melding','f_consent'=>'Opplysningene dine brukes kun til å behandle henvendelsen. Mer i vår {privacy}.',
  'f_ok'=>'Takk! Meldingen din har kommet fram – vi tar kontakt.','f_err'=>'Fyll inn navn, kontaktinformasjon og melding.','f_rate'=>'Vi får mange henvendelser akkurat nå. Prøv igjen senere.','f_off'=>'Kontaktskjemaet blir aktivt når nettstedet er publisert.',
  'imprint'=>'Juridisk informasjon','privacy'=>'Personvern','privacy_full'=>'personvernerklæring','made'=>'Nettsted laget med ChatHelp','back'=>'Til forsiden','ph'=>'Fyll inn','draft'=>'Forhåndsvisning – utkast, bare synlig for deg','demo'=>'Eksempelnettsted','nf_t'=>'Siden ble ikke funnet','nf_p'=>'Nettstedet finnes ikke eller er ikke publisert ennå.',
  'im_provider'=>'Tilbyder','im_rep'=>'Representert ved','im_contact'=>'Kontakt','im_register'=>'Registrering','im_vat'=>'Organisasjonsnummer / MVA','im_authority'=>'Tilsynsmyndighet / bransjeorgan','im_content'=>'Ansvarlig for innholdet','phone'=>'Telefon','address'=>'Adresse',
  'pv_ctrl_h'=>'Behandlingsansvarlig','pv_host_h'=>'Drift av nettstedet','pv_host'=>'Nettstedet leveres av ChatHelp (chat-help.com, drift i EU) på vegne av tilbyderen nevnt ovenfor. Ved besøk behandler serveren teknisk nødvendige opplysninger (IP-adresse, dato og klokkeslett, besøkt side, nettleserinformasjon) for å vise siden og beskytte den mot misbruk (GDPR art. 6 nr. 1 f). Det brukes ingen reklame- eller analysecookies, ingen sporing og ingen eksterne skrifttyper.',
  'pv_form_h'=>'Kontaktskjema','pv_form'=>'Når du bruker kontaktskjemaet, behandles navn, kontaktinformasjon og melding for å svare på henvendelsen (GDPR art. 6 nr. 1 b). IP-adressen lagres kun som kontrollsum for å hindre misbruk. Opplysningene slettes når de ikke lenger er nødvendige.',
  'pv_chat_h'=>'Chatassistent','pv_chat'=>'Chatassistenten svarer på spørsmål ved hjelp av kunstig intelligens (tjenesteleverandør: ChatHelp). Meldingene dine behandles for dette formålet og gjøres tilgjengelige for tilbyderen. Ikke skriv sensitive opplysninger (f.eks. helseopplysninger) i chatten.',
  'pv_links_h'=>'Kart og eksterne lenker','pv_links'=>'Lenker til kart (OpenStreetMap), WhatsApp eller bookingsider åpner eksterne tjenester først etter at du klikker. Der gjelder personvernerklæringen til den aktuelle leverandøren.',
  'pv_rights_h'=>'Dine rettigheter','pv_rights'=>'Du har rett til innsyn, retting, sletting, begrensning av behandlingen, innsigelse og dataportabilitet, og rett til å klage til en tilsynsmyndighet. Kontakt: {email}.','pv_more'=>'Utfyllende informasjon',
  'days'=>'man,tir,ons,tor,fre,lør,søn','stars'=>'{n} av 5 stjerner'],
'fi'=>['skip'=>'Siirry sisältöön','menu'=>'Valikko','lang'=>'Kieli','call'=>'Soita','whatsapp'=>'WhatsApp','email'=>'Sähköposti','book'=>'Varaa aika','order'=>'Tilaa nyt','chat'=>'Aloita chat','room'=>'Kysy huonetta','ride'=>'Tilaa taksi','contact_cta'=>'Ota yhteyttä','callback'=>'Pyydä soittopyyntö',
  's_services'=>'Palvelut','s_menu'=>'Ruokalista','s_rooms'=>'Huoneet','s_about'=>'Meistä','s_team'=>'Tiimi','s_gallery'=>'Kuvia','s_hours'=>'Aukioloajat','s_location'=>'Saapuminen','s_reviews'=>'Asiakkaidemme kokemuksia','s_faq'=>'Usein kysyttyä','s_contact'=>'Yhteystiedot',
  'closed'=>'suljettu','hours_none'=>'Aukioloajat pyynnöstä','route'=>'Reittiohjeet','map'=>'Näytä kartalla','map_note'=>'Kartta ja reitti avautuvat OpenStreetMapissa vasta napsautuksen jälkeen.','from'=>'alkaen','per_night'=>'yöltä','guests'=>'enintään {n} henkeä','min'=>'{n} min',
  'reviews_note'=>'Valittuja asiakaspalautteita – yrityksen omia tietoja.','f_name'=>'Nimi','f_contact'=>'Sähköposti tai puhelin','f_msg'=>'Viestisi','f_send'=>'Lähetä viesti','f_consent'=>'Tietojasi käytetään vain yhteydenottosi käsittelyyn. Lisää: {privacy}.',
  'f_ok'=>'Kiitos! Viestisi on perillä – otamme yhteyttä.','f_err'=>'Täytä nimi, yhteystieto ja viesti.','f_rate'=>'Saamme juuri nyt paljon yhteydenottoja. Yritä myöhemmin uudelleen.','f_off'=>'Yhteydenottolomake on käytössä julkaisun jälkeen.',
  'imprint'=>'Oikeudelliset tiedot','privacy'=>'Tietosuoja','privacy_full'=>'tietosuojaselosteessa','made'=>'Sivusto tehty ChatHelpillä','back'=>'Etusivulle','ph'=>'Täydennä','draft'=>'Esikatselu – luonnos, näkyy vain sinulle','demo'=>'Esimerkkisivusto','nf_t'=>'Sivua ei löytynyt','nf_p'=>'Sivustoa ei ole tai sitä ei ole vielä julkaistu.',
  'im_provider'=>'Palveluntarjoaja','im_rep'=>'Edustaja','im_contact'=>'Yhteystiedot','im_register'=>'Rekisteröinti','im_vat'=>'Y-tunnus / ALV-numero','im_authority'=>'Valvova viranomainen / järjestö','im_content'=>'Sisällöstä vastaa','phone'=>'Puhelin','address'=>'Osoite',
  'pv_ctrl_h'=>'Rekisterinpitäjä','pv_host_h'=>'Sivuston ylläpito','pv_host'=>'Sivuston tarjoaa ChatHelp (chat-help.com, ylläpito EU:ssa) edellä mainitun palveluntarjoajan puolesta. Käynnin aikana palvelin käsittelee teknisesti välttämättömiä tietoja (IP-osoite, päivämäärä ja kellonaika, avattu sivu, selaintiedot) sivun näyttämiseksi ja väärinkäytön estämiseksi (GDPR 6 art. 1 kohta f). Mainos- tai analytiikkaevästeitä, seurantaa tai ulkoisia fontteja ei käytetä.',
  'pv_form_h'=>'Yhteydenottolomake','pv_form'=>'Kun käytät lomaketta, nimeäsi, yhteystietoasi ja viestiäsi käsitellään yhteydenottoosi vastaamiseksi (GDPR 6 art. 1 kohta b). IP-osoite tallennetaan vain tarkistussummana väärinkäytön estämiseksi. Tiedot poistetaan, kun niitä ei enää tarvita.',
  'pv_chat_h'=>'Chat-avustaja','pv_chat'=>'Chat-avustaja vastaa kysymyksiin tekoälyn avulla (palveluntarjoaja: ChatHelp). Viestejäsi käsitellään tätä varten ja ne välitetään palveluntarjoajalle. Älä kirjoita chattiin arkaluonteisia tietoja (esim. terveystietoja).',
  'pv_links_h'=>'Kartat ja ulkoiset linkit','pv_links'=>'Linkit karttoihin (OpenStreetMap), WhatsAppiin tai varaussivuille avaavat ulkoiset palvelut vasta napsautuksesi jälkeen. Niihin sovelletaan kyseisen palveluntarjoajan tietosuojakäytäntöä.',
  'pv_rights_h'=>'Oikeutesi','pv_rights'=>'Sinulla on oikeus saada pääsy tietoihin, oikaista ja poistaa ne, rajoittaa käsittelyä, vastustaa käsittelyä ja siirtää tiedot sekä oikeus tehdä valitus valvontaviranomaiselle. Yhteys: {email}.','pv_more'=>'Lisätietoja',
  'days'=>'ma,ti,ke,to,pe,la,su','stars'=>'{n}/5 tähteä'],
'ja'=>['skip'=>'本文へスキップ','menu'=>'メニュー','lang'=>'言語','call'=>'電話する','whatsapp'=>'WhatsApp','email'=>'メール','book'=>'予約する','order'=>'注文する','chat'=>'チャットを開始','room'=>'客室を問い合わせる','ride'=>'タクシーを呼ぶ','contact_cta'=>'お問い合わせ','callback'=>'折り返し電話を依頼',
  's_services'=>'サービス','s_menu'=>'メニュー','s_rooms'=>'客室','s_about'=>'私たちについて','s_team'=>'スタッフ','s_gallery'=>'ギャラリー','s_hours'=>'営業時間','s_location'=>'アクセス','s_reviews'=>'お客様の声','s_faq'=>'よくある質問','s_contact'=>'お問い合わせ',
  'closed'=>'定休日','hours_none'=>'営業時間はお問い合わせください','route'=>'経路を調べる','map'=>'地図で見る','map_note'=>'地図と経路はクリック後に OpenStreetMap で開きます。','from'=>'〜','per_night'=>'1泊','guests'=>'最大{n}名','min'=>'{n}分',
  'reviews_note'=>'お客様の声（抜粋）– 事業者による掲載です。','f_name'=>'お名前','f_contact'=>'メールまたは電話番号','f_msg'=>'メッセージ','f_send'=>'送信する','f_consent'=>'ご入力いただいた情報はお問い合わせへの対応にのみ使用します。詳しくは{privacy}をご覧ください。',
  'f_ok'=>'ありがとうございます。メッセージを受け付けました。追ってご連絡します。','f_err'=>'お名前、連絡先、メッセージをご入力ください。','f_rate'=>'ただいまお問い合わせが集中しています。しばらくしてから再度お試しください。','f_off'=>'お問い合わせフォームは公開後に有効になります。',
  'imprint'=>'運営者情報','privacy'=>'プライバシー','privacy_full'=>'プライバシーポリシー','made'=>'ChatHelpで作成したウェブサイト','back'=>'トップへ戻る','ph'=>'入力してください','draft'=>'プレビュー – 下書き（あなただけに表示）','demo'=>'サンプルサイト','nf_t'=>'ページが見つかりません','nf_p'=>'このウェブサイトは存在しないか、まだ公開されていません。',
  'im_provider'=>'運営者','im_rep'=>'代表者','im_contact'=>'連絡先','im_register'=>'登記情報','im_vat'=>'登録番号','im_authority'=>'監督官庁・所属団体','im_content'=>'コンテンツ責任者','phone'=>'電話','address'=>'住所',
  'pv_ctrl_h'=>'管理者','pv_host_h'=>'ウェブサイトの提供','pv_host'=>'本サイトは、上記の運営者に代わり ChatHelp（chat-help.com、EU 内でホスティング）が提供しています。閲覧時、サーバーはページの表示と不正利用防止のため、技術的に必要なデータ（IPアドレス、日時、閲覧ページ、ブラウザ情報）を処理します（GDPR 第6条1項f）。広告・分析用 Cookie、トラッキング、外部フォントは使用していません。',
  'pv_form_h'=>'お問い合わせフォーム','pv_form'=>'お問い合わせフォームをご利用の場合、ご返信のためにお名前・連絡先・メッセージを処理します（GDPR 第6条1項b）。IPアドレスは不正利用防止のためチェックサムとしてのみ保存します。データは不要になった時点で削除します。',
  'pv_chat_h'=>'チャットアシスタント','pv_chat'=>'チャットアシスタントは人工知能（提供：ChatHelp）を用いて質問に回答します。メッセージはそのために処理され、運営者に共有されます。健康情報などの機微な情報はチャットに入力しないでください。',
  'pv_links_h'=>'地図と外部リンク','pv_links'=>'地図（OpenStreetMap）、WhatsApp、予約ページへのリンクは、クリックして初めて外部サービスを開きます。各提供者のプライバシーポリシーが適用されます。',
  'pv_rights_h'=>'お客様の権利','pv_rights'=>'お客様には、開示、訂正、削除、処理の制限、異議申立て、データポータビリティの権利、および監督機関に苦情を申し立てる権利があります。連絡先：{email}。','pv_more'=>'補足情報',
  'days'=>'月,火,水,木,金,土,日','stars'=>'5つ星中{n}'],
];

function sr_t(string $l, string $k, array $v=[]): string {
    $s=SITE_UI[$l][$k] ?? (SITE_UI['en'][$k] ?? (SITE_UI['de'][$k] ?? $k));
    foreach($v as $a=>$b) $s=str_replace('{'.$a.'}',(string)$b,$s);
    return $s;
}
function sr_e($s): string { return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
/** Text (escaped) mit Platzhaltern: Vorschau markiert sie, öffentlich werden sie entfernt. $para=true → Absätze. */
function sr_x(string $s, bool $preview, bool $para=false, string $phLabel='Bitte ergänzen'): string {
    $h=sr_e($s);
    $h=preg_replace_callback('/\[\[(.{1,80}?)\]\]/u',fn($m)=>$preview ? '<mark class="ph" title="'.sr_e($phLabel).'">'.$m[1].'</mark>' : '',$h) ?? $h;
    if(!$preview) $h=trim(preg_replace('/ {2,}/',' ',$h) ?? $h);
    if(!$para) return nl2br($h,false);
    $ps=array_filter(array_map('trim',preg_split("/\n{2,}/",$h) ?: []),fn($p)=>$p!=='');
    return implode('',array_map(fn($p)=>'<p>'.nl2br($p,false).'</p>',$ps));
}
function sr_has(string $s, bool $preview): bool { return trim($preview ? $s : (preg_replace('/\[\[.{1,80}?\]\]/u','',$s) ?? '')) !== ''; }
/** Farbe → [r,g,b] */
function sr_rgb(string $hex): array { $h=ltrim($hex,'#'); return [hexdec(substr($h,0,2)),hexdec(substr($h,2,2)),hexdec(substr($h,4,2))]; }
function sr_lum(string $hex): float { $f=function($c){ $c/=255; return $c<=0.03928 ? $c/12.92 : (($c+0.055)/1.055)**2.4; }; [$r,$g,$b]=sr_rgb($hex); return 0.2126*$f($r)+0.7152*$f($g)+0.0722*$f($b); }
function sr_contrast(string $a, string $b): float { $x=sr_lum($a); $y=sr_lum($b); return (max($x,$y)+0.05)/(min($x,$y)+0.05); }
function sr_mix(string $a, string $b, float $t): string { $x=sr_rgb($a); $y=sr_rgb($b); return sprintf('#%02x%02x%02x',...array_map(fn($i)=>(int)round($x[$i]*(1-$t)+$y[$i]*$t),[0,1,2])); }
function sr_rgba(string $hex, float $a): string { [$r,$g,$b]=sr_rgb($hex); return "rgba($r,$g,$b,$a)"; }
/** Akzent für Text auf hellem/dunklem Grund so lange abdunkeln/aufhellen, bis der Kontrast reicht (WCAG AA). */
function sr_readable(string $fg, string $bg, float $min=4.5): string { $c=$fg; $toward=sr_lum($bg)>0.4 ? '#000000' : '#ffffff'; for($i=0;$i<12 && sr_contrast($c,$bg)<$min;$i++) $c=sr_mix($c,$toward,0.12); return $c; }

/** CSS-Variablen aus dem Thema. */
function sr_vars(array $th): string {
    $P=SITE_PRESETS[$th['preset'] ?? 'gold'] ?? SITE_PRESETS['gold']; $g=fn($k)=>site_hex($th[$k] ?? '',$P[$k]);
    $bg=$g('bg'); $sur=$g('sur'); $ink=$g('ink'); $acc=$g('accent'); $acc2=$g('accent2'); $dark=sr_lum($bg)<0.18;
    if(sr_contrast($ink,$bg)<7) $ink=$dark ? '#f4f2ec' : '#121418';
    $F=SITE_FONTS[$th['font'] ?? 'classic'] ?? SITE_FONTS['classic'];
    $on=sr_contrast('#ffffff',$acc)>=sr_contrast('#111111',$acc) ? '#ffffff' : '#111111'; $on2=sr_contrast('#ffffff',$acc2)>=sr_contrast('#111111',$acc2) ? '#ffffff' : '#111111';
    $accT=sr_readable($acc,$bg); $accTs=sr_readable($acc,$sur);
    return ':root{--bg:'.$bg.';--sur:'.$sur.';--ink:'.$ink.';--ink2:'.sr_mix($ink,$bg,0.22).';--mute:'.sr_readable(sr_mix($ink,$bg,0.42),$bg,4.6).';--line:'.sr_mix($ink,$bg,$dark ? 0.82 : 0.88).';'
      .'--acc:'.$acc.';--acc2:'.$acc2.';--onacc:'.$on.';--onacc2:'.$on2.';--acct:'.$accT.';--accts:'.$accTs.';--bga:'.sr_rgba($bg,0.86).';--acc12:'.sr_rgba($acc,0.12).';--acc2a:'.sr_rgba($acc2,0.35).';'
      .'--ftbg:'.($dark ? '#07080a' : $ink).';--ftink:'.($dark ? '#e9e6dd' : $bg).';--hf:'.$F['h'].';--bf:'.$F['b'].';--hw:'.(int)$F['w'].'}';
}
/** Gemeinsames CSS + Vorlagen-Varianten. */
function sr_css(): string {
    return <<<'CSS'
*,*::before,*::after{box-sizing:border-box}html{-webkit-text-size-adjust:100%;scroll-behavior:smooth;scroll-padding-top:88px}
body{margin:0;background:var(--bg);color:var(--ink);font:400 17px/1.65 var(--bf);-webkit-font-smoothing:antialiased;overflow-x:hidden;text-rendering:optimizeLegibility}
img{max-width:100%;display:block;height:auto}a{color:inherit}p{margin:0}
h1,h2,h3{font-family:var(--hf);font-weight:var(--hw);line-height:1.1;letter-spacing:-.015em;margin:0;overflow-wrap:break-word;hyphens:auto}
:focus-visible{outline:3px solid var(--acc);outline-offset:3px;border-radius:6px}
.w{max-width:1180px;margin:0 auto;padding:0 20px}
.skip{position:absolute;left:-9999px;top:8px;z-index:100;background:var(--sur);color:var(--ink);padding:10px 14px;border-radius:10px}.skip:focus{left:12px}
.draftbar{background:#111318;color:#fff;font:600 13px/1.4 system-ui,sans-serif;text-align:center;padding:8px 12px}.draftbar b{color:#f1c76b}
mark.ph{background:#fff1b8;color:#5a4300;border-radius:6px;padding:0 .3em;box-shadow:inset 0 0 0 1.5px #e9b949;font:inherit;font-size:.92em}
mark.ph::before{content:"✎ ";opacity:.7}
/* Kopf */
.hd{position:sticky;top:0;z-index:40;background:var(--bga);backdrop-filter:saturate(1.4) blur(14px);-webkit-backdrop-filter:saturate(1.4) blur(14px);border-bottom:1px solid var(--line)}
.hd .w{display:flex;align-items:center;gap:14px;min-height:70px}
.brand{display:flex;align-items:center;gap:11px;text-decoration:none;min-width:0;font:var(--hw) 21px/1.1 var(--hf);color:var(--ink)}
.brand img{height:42px;width:auto;max-width:150px;object-fit:contain}.brand .bn{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.mono{flex:0 0 auto;width:40px;height:40px;border-radius:13px;display:grid;place-items:center;background:var(--acc);color:var(--onacc);font:700 18px/1 var(--bf)}
.nv{margin-left:auto;display:flex;gap:2px}.nv a{padding:9px 12px;border-radius:999px;text-decoration:none;font-size:15px;color:var(--ink2);white-space:nowrap}.nv a:hover{background:var(--acc12);color:var(--ink)}
.hdr{display:flex;align-items:center;gap:8px;margin-left:auto}.nv+.hdr{margin-left:10px}
.dd{position:relative}.dd>summary{list-style:none;cursor:pointer;display:inline-flex;align-items:center;gap:6px;min-height:44px;padding:0 12px;border-radius:999px;border:1px solid var(--line);font:600 14px var(--bf);color:var(--ink)}
.dd>summary::-webkit-details-marker{display:none}.dd>summary::after{content:"";width:7px;height:7px;border:solid currentColor;border-width:0 1.8px 1.8px 0;transform:rotate(45deg) translateY(-2px);opacity:.6}
.dd[open]>summary{background:var(--sur)}.dd .ddm{position:absolute;right:0;top:calc(100% + 8px);min-width:200px;background:var(--sur);border:1px solid var(--line);border-radius:16px;padding:6px;box-shadow:0 20px 50px rgba(0,0,0,.16);z-index:50}
.dd .ddm a{display:flex;justify-content:space-between;gap:12px;padding:10px 12px;border-radius:10px;text-decoration:none;font-size:15px}.dd .ddm a:hover,.dd .ddm a[aria-current]{background:var(--acc12)}.dd .ddm a small{color:var(--mute);font-weight:600}
.mnav{display:none}
@media(max-width:940px){.nv{display:none}.mnav{display:block}.hd .btn.hb{display:none}}
@media(max-width:420px){.brand{font-size:18px}.mono{width:36px;height:36px}.dd>summary{padding:0 10px}}
/* Knöpfe */
.btn{display:inline-flex;align-items:center;justify-content:center;gap:9px;min-height:50px;padding:13px 24px;border-radius:999px;font:600 16px/1.2 var(--bf);text-decoration:none;border:1.5px solid transparent;cursor:pointer;transition:transform .15s,box-shadow .15s,background .15s;text-align:center;max-width:100%}
.btn:hover{transform:translateY(-1px)}.btn svg{flex:0 0 auto}
.btn.p{background:var(--acc);color:var(--onacc);box-shadow:0 12px 28px -12px var(--acc)}.btn.o{border-color:currentColor;background:transparent;color:inherit}.btn.l{background:var(--sur);color:var(--ink);border-color:var(--line)}
.btn.s{min-height:44px;padding:9px 16px;font-size:15px}
/* Hero */
.hero{position:relative;padding:clamp(48px,8vw,116px) 0 clamp(52px,8vw,112px);overflow:hidden}
.kick{display:inline-flex;align-items:center;gap:10px;font:700 12.5px/1.3 var(--bf);letter-spacing:.16em;text-transform:uppercase;color:var(--acct)}
.kick::before{content:"";width:26px;height:2px;background:currentColor;border-radius:2px}
.hero h1{font-size:clamp(40px,6.4vw,80px);margin:16px 0 20px}.lead{font-size:clamp(17.5px,1.7vw,21px);color:var(--ink2);max-width:56ch}
.ctas{display:flex;flex-wrap:wrap;gap:12px;margin-top:30px}
.facts{display:flex;flex-wrap:wrap;gap:10px;margin-top:26px;padding:0;list-style:none}.facts li{display:inline-flex;align-items:center;gap:8px;padding:8px 14px;border-radius:999px;background:var(--sur);border:1px solid var(--line);font-size:14.5px;color:var(--ink2)}
.facts a{text-decoration:none;display:inline-flex;align-items:center;gap:8px}
.hv{position:relative;border-radius:30px;overflow:hidden;background:var(--sur);min-height:240px}.hv img{width:100%;height:100%;object-fit:cover;position:absolute;inset:0}
.art{position:absolute;inset:0}.art .mg{position:absolute;inset:0;display:grid;place-items:center;font:var(--hw) clamp(90px,16vw,200px)/1 var(--hf);color:rgba(255,255,255,.88);text-shadow:0 10px 40px rgba(0,0,0,.18)}
.h-split .hero .w{display:grid;grid-template-columns:1.08fr .92fr;gap:clamp(30px,5vw,76px);align-items:center}.h-split .hv{aspect-ratio:4/5}
.h-card .hero .w{display:grid;grid-template-columns:1fr 1fr;align-items:center}.h-card .hcard{position:relative;z-index:2;background:var(--sur);border-radius:28px;padding:clamp(26px,4vw,52px);box-shadow:0 30px 80px -30px rgba(0,0,0,.35);margin-right:-90px;border:1px solid var(--line)}
.h-card .hv{aspect-ratio:1/1.05}
.h-full .hero{min-height:min(90vh,860px);display:flex;align-items:flex-end;color:#fff;padding-top:140px}.h-full .hero>.hv{position:absolute;inset:0;border-radius:0;min-height:0}
.h-full .hero>.hv::after{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,.18) 0%,rgba(0,0,0,.22) 40%,rgba(0,0,0,.72) 100%)}
.h-full .hero .w{position:relative;z-index:2;width:100%}.h-full .hero .lead{color:rgba(255,255,255,.9)}.h-full .kick{color:#fff}.h-full .facts li{background:rgba(255,255,255,.14);border-color:rgba(255,255,255,.25);color:#fff;backdrop-filter:blur(6px)}
.h-full .btn.o{color:#fff}.h-full .hero .btn.p{background:var(--acc2);color:var(--onacc2);box-shadow:0 12px 30px -12px rgba(0,0,0,.6)}
.h-center .hero{text-align:center}.h-center .lead{margin:0 auto}.h-center .ctas,.h-center .facts{justify-content:center}.h-center .kick::before{display:none}
@media(max-width:880px){.h-split .hero .w,.h-card .hero .w{grid-template-columns:1fr}.h-split .hv{aspect-ratio:4/3;order:-1}.h-card .hcard{margin:-70px 0 0;order:2}.h-card .hv{aspect-ratio:4/3}}
/* Abschnitte */
.sec{padding:clamp(64px,9vw,124px) 0}.sec.alt{background:var(--sur)}
.sh{max-width:760px;margin-bottom:clamp(30px,4vw,52px)}.sh h2{font-size:clamp(31px,4.4vw,52px)}.sh p{color:var(--ink2);margin-top:14px;font-size:18.5px}
.sh .kick{margin-bottom:14px}
.grid{display:grid;gap:18px}.cards{grid-template-columns:repeat(auto-fill,minmax(min(100%,260px),1fr))}
.card{background:var(--bg);border:1px solid var(--line);border-radius:22px;padding:26px;min-width:0}.sec:not(.alt) .card{background:var(--sur)}
.card h3{font-size:22px}.card p{color:var(--ink2);margin-top:8px}.card .pr{margin-top:14px;font-weight:700;color:var(--accts)}
.sec:not(.alt) .card .pr{color:var(--accts)}
.ci{width:46px;height:46px;border-radius:14px;display:grid;place-items:center;background:var(--acc12);color:var(--acct);margin-bottom:16px;font:700 17px var(--bf)}
.menu{columns:2 340px;column-gap:56px}.mcat{break-inside:avoid;margin-bottom:34px}.mcat h3{font-size:25px;margin-bottom:6px;color:var(--acct)}
.mi{display:flex;align-items:baseline;gap:10px;padding:13px 0;border-bottom:1px solid var(--line)}.mi .mn{min-width:0}.mi b{font-weight:600}.mi small{display:block;color:var(--mute);font-size:14.5px;line-height:1.45;margin-top:2px}
.mi .ld{flex:1;border-bottom:2px dotted var(--line);transform:translateY(-5px);min-width:18px}.mi .pr{white-space:nowrap;font-weight:700}
.plist{max-width:860px}.pl{display:flex;justify-content:space-between;align-items:baseline;gap:18px;padding:18px 0;border-bottom:1px solid var(--line)}.pl b{font:var(--hw) 21px/1.25 var(--hf)}.pl small{display:block;color:var(--mute);font-size:14.5px;margin-top:3px}.pl .pr{white-space:nowrap;font-weight:700;color:var(--accts)}
.rooms{grid-template-columns:repeat(auto-fill,minmax(min(100%,330px),1fr))}.room{padding:0;overflow:hidden}.room .rv{aspect-ratio:16/10;position:relative}.room .rb{padding:24px}.room .meta{display:flex;flex-wrap:wrap;gap:8px 16px;margin-top:12px;color:var(--mute);font-size:14.5px}
.about{display:grid;grid-template-columns:1.05fr .95fr;gap:clamp(30px,5vw,80px);align-items:center}.about .tx p+p{margin-top:16px}.about .tx p{font-size:18px;color:var(--ink2)}.about .hv{aspect-ratio:5/4}
.about.noimg{grid-template-columns:1fr;max-width:820px}
@media(max-width:880px){.about{grid-template-columns:1fr}}
.team{grid-template-columns:repeat(auto-fill,minmax(min(100%,220px),1fr))}.tm{text-align:center}.tm .av{width:116px;height:116px;border-radius:50%;margin:0 auto 16px;overflow:hidden;background:var(--acc12);display:grid;place-items:center;font:700 32px var(--hf);color:var(--acct)}.tm .av img{width:100%;height:100%;object-fit:cover}.tm h3{font-size:20px}.tm p{color:var(--mute);margin-top:4px}
.gal{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,230px),1fr));gap:12px}.gal figure{margin:0;border-radius:18px;overflow:hidden;background:var(--line);aspect-ratio:4/3}.gal img{width:100%;height:100%;object-fit:cover}
@media(min-width:760px){.gal figure:first-child{grid-column:span 2;grid-row:span 2;aspect-ratio:auto}}
.gal .gph{display:grid;place-items:center;color:var(--mute);font-size:14px;text-align:center;padding:14px;border:2px dashed var(--line);background:transparent}
.hrs{display:grid;grid-template-columns:1fr 1fr;gap:clamp(24px,4vw,56px);align-items:start}@media(max-width:820px){.hrs{grid-template-columns:1fr}}
.ht{width:100%;border-collapse:collapse;font-size:17px}.ht th{font-weight:600;text-align:left;padding:13px 0;border-bottom:1px solid var(--line);width:34%}.ht td{text-align:right;padding:13px 0;border-bottom:1px solid var(--line);color:var(--ink2)}.ht .cl{color:var(--mute)}
.loc{display:grid;grid-template-columns:.9fr 1.1fr;gap:clamp(24px,4vw,56px);align-items:stretch}@media(max-width:820px){.loc{grid-template-columns:1fr}}
.loc .adr{font-size:19px;line-height:1.55}.loc .adr b{font:var(--hw) 24px/1.25 var(--hf);display:block;margin-bottom:6px}.loc .ctas{margin-top:22px}.loc .note{color:var(--mute);font-size:14px;margin-top:14px}
.mapart{position:relative;border-radius:24px;overflow:hidden;min-height:260px;background:linear-gradient(135deg,var(--acc12),var(--acc2a)),var(--sur);border:1px solid var(--line);text-decoration:none;display:block}
.mapart svg{position:absolute;inset:0;width:100%;height:100%}.mapart .pin{position:absolute;left:50%;top:46%;transform:translate(-50%,-100%);width:46px;height:46px;border-radius:50% 50% 50% 0;rotate:-45deg;background:var(--acc);box-shadow:0 10px 24px -6px var(--acc)}
.mapart .pin::after{content:"";position:absolute;inset:14px;border-radius:50%;background:var(--onacc)}.mapart span.lb{position:absolute;left:16px;bottom:14px;right:16px;font-weight:600;font-size:14.5px;background:var(--sur);border-radius:12px;padding:9px 12px;color:var(--ink)}
.revs{grid-template-columns:repeat(auto-fill,minmax(min(100%,300px),1fr))}.rev blockquote{margin:0;font-size:17.5px;color:var(--ink)}.rev .st{color:var(--acct);letter-spacing:2px;font-size:18px;margin-bottom:12px}.rev cite{display:block;margin-top:16px;font-style:normal;font-weight:600;color:var(--mute);font-size:15px}
.rnote{color:var(--mute);font-size:14px;margin-top:22px}
.faq{max-width:860px}.faq details{border-bottom:1px solid var(--line)}.faq summary{list-style:none;cursor:pointer;padding:22px 46px 22px 0;font:600 19px/1.4 var(--bf);position:relative}.faq summary::-webkit-details-marker{display:none}
.faq summary::after{content:"+";position:absolute;right:6px;top:50%;transform:translateY(-50%);font:400 30px/1 var(--bf);color:var(--acct)}.faq details[open] summary::after{content:"–"}.faq .an{padding:0 0 22px;color:var(--ink2);max-width:70ch}.faq .an p+p{margin-top:10px}
.ct{display:grid;grid-template-columns:.85fr 1.15fr;gap:clamp(26px,5vw,72px);align-items:start}@media(max-width:880px){.ct{grid-template-columns:1fr}}
.cm{list-style:none;margin:24px 0 0;padding:0;display:grid;gap:12px}.cm a,.cm span.it{display:flex;align-items:center;gap:14px;padding:14px 16px;border-radius:16px;background:var(--bg);border:1px solid var(--line);text-decoration:none;min-height:56px;overflow-wrap:anywhere}
.sec:not(.alt) .cm a,.sec:not(.alt) .cm span.it{background:var(--sur)}.cm i{flex:0 0 auto;width:38px;height:38px;border-radius:12px;display:grid;place-items:center;background:var(--acc12);color:var(--acct);font-style:normal}.cm small{display:block;color:var(--mute);font-size:13px}
form.cf{background:var(--bg);border:1px solid var(--line);border-radius:26px;padding:clamp(20px,3vw,34px);display:grid;gap:14px}.sec:not(.alt) form.cf{background:var(--sur)}
.cf label{font:600 14px var(--bf);display:grid;gap:7px}.cf input,.cf textarea{width:100%;font:inherit;font-size:16px;color:var(--ink);background:var(--sur);border:1.5px solid var(--line);border-radius:14px;padding:13px 15px;outline:0}
.sec:not(.alt) .cf input,.sec:not(.alt) .cf textarea{background:var(--bg)}.cf input:focus,.cf textarea:focus{border-color:var(--acc);box-shadow:0 0 0 4px var(--acc12)}.cf textarea{min-height:140px;resize:vertical}
.cf .hp{position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden}.cf .fine{color:var(--mute);font-size:13.5px}.cf .fine a{color:var(--acct)}.cf .btn{justify-self:start}
.msg{padding:13px 16px;border-radius:14px;font-weight:600}.msg.ok{background:#e4f5ea;color:#14663a}.msg.err{background:#fde7ee;color:#8f1f42}.msg.info{background:var(--acc12);color:var(--ink)}
/* Fuß */
.ft{background:var(--ftbg);color:var(--ftink);padding:56px 0 calc(40px + env(safe-area-inset-bottom))}.ft .w{display:grid;grid-template-columns:1.2fr 1fr;gap:28px}.ft a{color:inherit}.ft .fb{font:var(--hw) 24px/1.2 var(--hf)}.ft p{opacity:.78;margin-top:10px;max-width:46ch}
.ft nav{display:flex;flex-wrap:wrap;gap:10px 22px;justify-content:flex-end;align-content:start}.ft nav a{text-decoration:none;opacity:.85}.ft nav a:hover{opacity:1;text-decoration:underline}
.ft .made{grid-column:1/-1;border-top:1px solid rgba(127,127,127,.25);padding-top:18px;font-size:13.5px;opacity:.7;display:flex;flex-wrap:wrap;gap:8px 18px;justify-content:space-between}
@media(max-width:720px){.ft .w{grid-template-columns:1fr}.ft nav{justify-content:flex-start}}
/* Aktionsleiste (Handy) */
.abar{position:fixed;left:0;right:0;bottom:0;z-index:45;display:none;gap:8px;padding:10px 12px calc(10px + env(safe-area-inset-bottom));background:var(--bga);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border-top:1px solid var(--line)}
.abar .btn{flex:1 1 0;min-height:48px;padding:10px 12px;font-size:15px;min-width:0}.abar .btn span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.abar.wg{padding-right:92px}
@media(max-width:720px){.abar{display:flex}body.hasbar{padding-bottom:78px}}
/* Rechtliches */
.legal{padding:clamp(40px,6vw,80px) 0 90px}.legal .w{max-width:820px}.legal h1{font-size:clamp(34px,5vw,52px);margin-bottom:28px}.legal h2{font-size:24px;margin:34px 0 10px}.legal p,.legal address{color:var(--ink2);font-style:normal;line-height:1.7}.legal p+p{margin-top:10px}
.legal .back{display:inline-flex;margin-top:40px}
.nf{min-height:70vh;display:grid;place-items:center;text-align:center;padding:40px 20px}.nf h1{font-size:clamp(34px,6vw,60px);margin-bottom:14px}.nf p{color:var(--ink2)}
.demo-rib{position:fixed;left:12px;top:82px;z-index:60;background:#111318;color:#fff;font:600 12px/1 system-ui,sans-serif;padding:8px 12px;border-radius:999px;opacity:.88}
/* ===== Vorlagen ===== */
.t-gastro .hero h1{font-style:italic;letter-spacing:-.02em}.t-gastro .sh h2{font-style:italic}.t-gastro .kick{letter-spacing:.22em}
.t-gastro .sec.alt{background:linear-gradient(180deg,var(--sur),var(--bg))}
.t-beauty .hero h1,.t-beauty .sh h2{font-weight:400;letter-spacing:-.02em}.t-beauty .kick{letter-spacing:.3em}.t-beauty .kick::before{display:none}
.t-beauty .h-split .hv,.t-beauty.h-split .hv{border-radius:999px 999px 30px 30px}.t-beauty .card{border-radius:28px}
.t-praxis .hv{border-radius:34px}.t-praxis .card{border-radius:20px;box-shadow:0 1px 2px rgba(0,0,0,.04),0 16px 36px -24px rgba(0,0,0,.25)}.t-praxis .kick::before{width:10px;height:10px;border-radius:50%}
.t-handwerk .hero h1,.t-handwerk .sh h2{text-transform:uppercase;letter-spacing:-.01em;line-height:1}.t-handwerk .sh h2::after{content:"";display:block;width:72px;height:6px;background:var(--acc);margin-top:18px;border-radius:2px}
.t-handwerk .card{border-radius:6px;border-top:5px solid var(--acc)}.t-handwerk .btn{border-radius:8px}.t-handwerk .cards{counter-reset:svc}.t-handwerk .cards .card::before{counter-increment:svc;content:"0" counter(svc);display:block;font:800 15px var(--bf);color:var(--acct);letter-spacing:.1em;margin-bottom:10px}
.t-handwerk .hv{border-radius:8px}.t-handwerk .h-card .hcard,.t-handwerk.h-card .hcard{border-radius:8px}
.t-hotel .hero h1{font-weight:400}.t-hotel .h-full .hero .w,.t-hotel.h-full .hero .w{text-align:center}.t-hotel.h-full .lead{margin:0 auto}.t-hotel.h-full .ctas,.t-hotel.h-full .facts{justify-content:center}.t-hotel.h-full .kick::before{display:none}
.t-hotel .sh h2{font-weight:400}.t-hotel .card{border-radius:4px}
.t-taxi .hero h1{text-transform:uppercase;font-weight:800;letter-spacing:-.02em}.t-taxi .bigtel{display:inline-block;margin-top:8px;font:800 clamp(38px,8vw,84px)/1 var(--bf);color:var(--acc);text-decoration:none;letter-spacing:-.02em;overflow-wrap:anywhere}
.t-taxi .hero::after{content:"";position:absolute;left:0;right:0;bottom:0;height:22px;background:repeating-linear-gradient(90deg,var(--acc) 0 22px,transparent 22px 44px),repeating-linear-gradient(90deg,transparent 0 22px,var(--acc) 22px 44px);background-size:44px 11px;background-repeat:repeat-x;background-position:0 0,0 11px;opacity:.9}
.t-taxi .card{border-radius:14px}.t-taxi .btn.p{color:#111}
.t-shop .hero h1{letter-spacing:-.035em}.t-shop .card{border:0;border-radius:18px}.t-shop .hv{border-radius:20px}.t-shop .kick::before{display:none}
.t-personal .hero h1{font-size:clamp(46px,8.4vw,108px);letter-spacing:-.03em}.t-personal .pmono{width:116px;height:116px;border-radius:50%;margin:0 auto 22px;overflow:hidden;background:var(--acc);color:var(--onacc);display:grid;place-items:center;font:var(--hw) 44px var(--hf);box-shadow:0 0 0 8px var(--acc12)}
.t-personal .pmono img{width:100%;height:100%;object-fit:cover}.t-personal .about .tx p:first-child{font:var(--hw) clamp(22px,2.4vw,30px)/1.4 var(--hf);color:var(--ink)}
@media (prefers-reduced-motion:reduce){*{scroll-behavior:auto!important;transition:none!important}}
CSS;
}
/** Hintergrund-Kunst je Vorlage (ohne Fotos: Verläufe + Muster, keine externen Bilder). */
function sr_art_css(string $tpl): string {
    $svg=fn(string $s)=>'url("data:image/svg+xml,'.rawurlencode($s).'")';
    $pat=[
      'gastro'=>"radial-gradient(circle at 18% 22%,rgba(255,214,150,.55),transparent 42%),radial-gradient(circle at 82% 70%,rgba(255,120,60,.45),transparent 46%),linear-gradient(135deg,#3a2216,#7b3a22 55%,#2a160e)",
      'beauty'=>"radial-gradient(circle at 70% 25%,rgba(255,255,255,.55),transparent 38%),radial-gradient(circle at 25% 80%,var(--acc2),transparent 55%),linear-gradient(160deg,var(--acc2),var(--acc))",
      'praxis'=>$svg('<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64"><path d="M29 22h6v7h7v6h-7v7h-6v-7h-7v-6h7z" fill="#fff" fill-opacity=".16"/></svg>').",linear-gradient(150deg,var(--acc),var(--acc2))",
      'handwerk'=>"repeating-linear-gradient(-45deg,rgba(255,255,255,.08) 0 18px,transparent 18px 36px),linear-gradient(135deg,#1d2026,var(--acc) 140%)",
      'hotel'=>$svg('<svg xmlns="http://www.w3.org/2000/svg" width="120" height="40"><path d="M0 20q15-12 30 0t30 0 30 0 30 0" fill="none" stroke="#fff" stroke-opacity=".14" stroke-width="2"/></svg>').",radial-gradient(circle at 70% 20%,rgba(255,231,180,.45),transparent 45%),linear-gradient(180deg,#0f2742,var(--acc) 70%,#0b1b2e)",
      'taxi'=>"radial-gradient(circle at 50% 120%,var(--acc),transparent 60%),linear-gradient(180deg,#141518,#0b0c0d)",
      'shop'=>$svg('<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48"><path d="M0 .5H48M.5 0V48" stroke="#000" stroke-opacity=".06"/></svg>').",linear-gradient(140deg,var(--acc2),var(--sur))",
      'personal'=>"radial-gradient(circle at 30% 30%,var(--acc2),transparent 55%),linear-gradient(135deg,var(--acc),var(--ink))"];
    return '.art{background:'.($pat[$tpl] ?? $pat['personal']).'}'.($tpl==='shop' ? '.art .mg{color:var(--acc)}' : '');
}
function sr_icon(string $k): string {
    $p=['phone'=>'<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/>','wa'=>'<path d="M21 11.5a8.5 8.5 0 0 1-12.6 7.4L3 20l1.2-5.1A8.5 8.5 0 1 1 21 11.5Z"/><path d="M9 9.5c.3 2 2 4 4.5 4.8"/>',
       'mail'=>'<rect x="3" y="5" width="18" height="14" rx="3"/><path d="m4 7 8 6 8-6"/>','chat'=>'<path d="M4 5h16v11H9l-5 4z"/>','cal'=>'<rect x="3" y="5" width="18" height="16" rx="3"/><path d="M8 3v4M16 3v4M3 10h18"/>',
       'pin'=>'<path d="M12 21s7-6.2 7-11.5A7 7 0 0 0 5 9.5C5 14.8 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.5"/>','arrow'=>'<path d="M5 12h14M13 6l6 6-6 6"/>','car'=>'<path d="M5 16V11l2-5h10l2 5v5"/><path d="M3 16h18v3H3z"/>','bed'=>'<path d="M3 18V8M3 14h18v4M21 14v-2a3 3 0 0 0-3-3h-7v5"/>','clock'=>'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>'];
    return '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($p[$k] ?? $p['arrow']).'</svg>';
}
function sr_money(int $cents, string $l): string {
    $dot=in_array($l,['en','ja'],true); $n=number_format($cents/100,($cents%100===0) ? 0 : 2,$dot ? '.' : ',',$dot ? ',' : '.');
    return $l==='en' || $l==='ja' ? '€'.$n : $n.' €';
}
/** Wochentags-Zeilen (Mo–So) aus {0..6}. */
function sr_hours_rows(?array $h, string $l): array {
    $names=explode(',',sr_t($l,'days')); $o=[];
    foreach([1,2,3,4,5,6,0] as $i=>$d){ $iv=$h[(string)$d] ?? []; $o[]=[$names[$i] ?? (string)$d,$iv]; }
    return $o;
}
/** Text je Sprache mit Fallback auf die Standardsprache. */
function sr_lm(array $m, string $l, string $def): string { return (string)($m[$l] ?? ($m[$def] ?? (reset($m) ?: ''))); }

/**
 * Beispiel-Website je Vorlage (Startseite, Studio-Vorschaubilder). Kein Datenbankzugriff, keine Bilder (Verläufe), Kennzeichnung „Beispiel“.
 */
function site_demo(string $tpl, string $lang): array {
    $tpl=isset(SITE_TEMPLATES[$tpl]) ? $tpl : 'gastro'; $T=SITE_TEMPLATES[$tpl]; $P=SITE_PRESETS[$T['preset']];
    $D=[
     'gastro'=>['name'=>'Trattoria Sole','city'=>'Köln','phone'=>'+49 221 0000000',
        'de'=>['Italienische Küche · Köln','Pasta, die nach Sonntag schmeckt.','Hausgemachte Pasta, Pizza aus dem Steinofen und Weine kleiner Weingüter – im Restaurant, zum Abholen oder geliefert.','Seit Generationen am Herd','Unsere Familie kocht, wie man in Apulien kocht: wenige, gute Zutaten und viel Zeit. Der Teig ruht 48 Stunden, die Tomaten kommen aus San Marzano.'],
        'en'=>['Italian kitchen · Cologne','Pasta that tastes like Sunday.','Homemade pasta, stone-oven pizza and wines from small vineyards – dine in, take away or delivered.','Cooking for generations','Our family cooks the way they do in Apulia: few, good ingredients and plenty of time. The dough rests 48 hours, the tomatoes come from San Marzano.'],
        'fr'=>['Cuisine italienne · Cologne','Des pâtes qui ont le goût du dimanche.','Pâtes maison, pizza au feu de bois et vins de petits domaines – sur place, à emporter ou en livraison.'],
        'tr'=>['İtalyan mutfağı · Köln','Pazar sofrası tadında makarna.','Ev yapımı makarna, taş fırın pizza ve küçük bağların şarapları – restoranda, gel-al veya paket servis.'],
        'az'=>['İtalyan mətbəxi · Köln','Bazar günü dadında makaron.','Ev makaronu, daş sobada pizza və kiçik üzüm bağlarının şərabları – restoranda, götürmək və ya çatdırılma.'],
        'es'=>['Cocina italiana · Colonia','Pasta que sabe a domingo.','Pasta casera, pizza al horno de piedra y vinos de pequeñas bodegas – en el local, para llevar o a domicilio.'],
        'menu'=>[['Antipasti','Burrata pugliese','Tomaten, Basilikum, Olivenöl',1250],['Antipasti','Vitello tonnato','',1450],['Pasta','Orecchiette alle cime di rapa','Stängelkohl, Knoblauch, Chili',1690],['Pasta','Tagliatelle al ragù','6 Stunden geschmort',1790],['Pizza','Margherita','San Marzano, Fior di latte',1090],['Pizza','Diavola','Scharfe Salami',1350],['Dolci','Tiramisù della casa','',790]]],
     'beauty'=>['name'=>'Atelier Lumière','city'=>'München','phone'=>'+49 89 0000000',
        'de'=>['Beauty & Hautpflege · München','Zeit für dich. Sichtbar schön.','Gesichtsbehandlungen, Wimpern und Nails mit Ruhe und Sorgfalt – online buchen, auch am Wochenende.','Ein Ort zum Durchatmen','Bei uns gibt es keinen Takt am Fließband. Jede Behandlung beginnt mit einem Gespräch über deine Haut und deine Wünsche.'],
        'en'=>['Beauty & skincare · Munich','Time for you. Visibly radiant.','Facials, lashes and nails with calm and care – book online, weekends included.','A place to breathe','There is no conveyor belt here. Every treatment starts with a conversation about your skin and your wishes.'],
        'fr'=>['Beauté & soins · Munich','Du temps pour vous. Une beauté visible.','Soins du visage, cils et ongles avec calme et attention – réservation en ligne, même le week-end.'],
        'tr'=>['Güzellik & cilt bakımı · Münih','Kendinize zaman ayırın.','Cilt bakımı, kirpik ve tırnak – özenle ve sakinlikle. Hafta sonu dahil online randevu.'],
        'az'=>['Gözəllik & dəri baxımı · Münhen','Özünüzə vaxt ayırın.','Üz baxımı, kirpik və dırnaq – sakit və diqqətli. Həftəsonu da daxil onlayn növbə.'],
        'es'=>['Belleza y cuidado de la piel · Múnich','Tiempo para ti. Belleza que se nota.','Faciales, pestañas y uñas con calma y mimo – reserva online, también en fin de semana.'],
        'svc'=>[['Klassische Gesichtsbehandlung',60,6900],['Hydra-Facial',75,11900],['Wimpernlifting',45,5900],['Maniküre mit Shellac',60,4500]]],
     'praxis'=>['name'=>'Praxis am Park','city'=>'Hamburg','phone'=>'+49 40 0000000',
        'de'=>['Allgemeinmedizin · Hamburg','Gut betreut – vom ersten Anruf an.','Termine online oder telefonisch, auch für Neupatienten. Unser digitaler Assistent hilft rund um die Uhr bei Fragen zur Organisation.','Medizin mit Zeit','Wir nehmen uns Zeit für Ihre Anliegen – vom Check-up bis zur Begleitung bei chronischen Erkrankungen.'],
        'en'=>['General practice · Hamburg','Well cared for – from the first call.','Appointments online or by phone, new patients welcome. Our digital assistant helps with organisational questions around the clock.','Medicine that takes time','We take time for your concerns – from check-ups to long-term care for chronic conditions.'],
        'fr'=>['Médecine générale · Hambourg','Bien suivi, dès le premier appel.','Rendez-vous en ligne ou par téléphone, nouveaux patients bienvenus.'],
        'tr'=>['Genel tıp · Hamburg','İlk aramadan itibaren iyi ellerdesiniz.','Online veya telefonla randevu, yeni hastalar dahil.'],
        'az'=>['Ümumi təbabət · Hamburq','İlk zəngdən qayğı ilə.','Onlayn və ya telefonla növbə, yeni pasiyentlər də daxil.'],
        'es'=>['Medicina general · Hamburgo','Bien atendido desde la primera llamada.','Citas online o por teléfono, también para nuevos pacientes.'],
        'cards'=>[['Vorsorge & Check-up','Gesundheits-Check, Hautkrebs-Screening, Impfberatung.'],['Akutsprechstunde','Täglich für dringende Anliegen.'],['Hausbesuche','Für Patientinnen und Patienten, die nicht in die Praxis kommen können.'],['Online-Termin','Freie Zeiten sehen und direkt buchen.']]],
     'handwerk'=>['name'=>'Schneider Haustechnik','city'=>'Stuttgart','phone'=>'+49 711 0000000',
        'de'=>['Meisterbetrieb · Heizung & Sanitär','Wir machen das. Sauber. Pünktlich. Fair.','Wartung, Reparatur und neue Bäder aus einer Hand – mit festem Ansprechpartner und klarem Angebot vorab.','Handwerk mit Haltung','Wir hinterlassen jede Baustelle so, wie wir sie gern vorfinden würden. Termine werden gehalten – und wenn nicht, rufen wir vorher an.'],
        'en'=>['Master craftsmen · Heating & plumbing','We get it done. Clean. On time. Fair.','Maintenance, repairs and new bathrooms from one team – with a fixed contact and a clear quote up front.','Craft with integrity','We leave every site the way we would like to find it. Appointments are kept – and if not, we call ahead.'],
        'fr'=>['Artisans qualifiés · Chauffage & sanitaire','Propre. Ponctuel. Honnête.','Entretien, réparation et salles de bains neuves – avec un interlocuteur unique et un devis clair.'],
        'tr'=>['Usta firma · Isıtma & tesisat','Temiz. Dakik. Adil.','Bakım, onarım ve yeni banyolar tek elden – sabit muhatap ve önceden net teklif.'],
        'az'=>['Usta müəssisə · İstilik və santexnika','Təmiz. Vaxtında. Ədalətli.','Texniki xidmət, təmir və yeni hamamlar bir yerdən – sabit əlaqə şəxsi və əvvəlcədən aydın təklif.'],
        'es'=>['Empresa artesana · Calefacción y fontanería','Limpio. Puntual. Justo.','Mantenimiento, reparaciones y baños nuevos – con un contacto fijo y presupuesto claro.'],
        'cards'=>[['Heizungswartung','Jährlicher Check für sichere, effiziente Anlagen.'],['Badsanierung','Planung und Umsetzung aus einer Hand.'],['Notdienst','Rohrbruch oder Heizungsausfall? Wir kommen.']]],
     'hotel'=>['name'=>'Hotel Seeblick','city'=>'Konstanz','phone'=>'+49 7531 000000',
        'de'=>['Boutique-Hotel am See','Aufwachen mit Blick aufs Wasser.','18 Zimmer, ein Frühstück mit regionalen Produkten und Ruhe direkt am Ufer – Anfragen beantwortet unser Assistent sofort.','Ankommen, durchatmen','Das Haus von 1912 wurde behutsam renoviert: viel Holz, Leinen und Licht. Die Altstadt erreichen Sie zu Fuß in zehn Minuten.'],
        'en'=>['Boutique hotel by the lake','Wake up to a view of the water.','18 rooms, breakfast with regional produce and peace right on the shore – our assistant answers enquiries instantly.','Arrive and unwind','The 1912 house has been carefully renovated: lots of wood, linen and light. The old town is a ten-minute walk away.'],
        'fr'=>['Hôtel de charme au bord du lac','Se réveiller face à l’eau.','18 chambres, un petit-déjeuner régional et le calme au bord de l’eau.'],
        'tr'=>['Göl kenarında butik otel','Suya karşı uyanın.','18 oda, yöresel kahvaltı ve kıyıda huzur.'],
        'az'=>['Göl sahilində butik otel','Suya baxan səhər.','18 otaq, yerli məhsullarla səhər yeməyi və sahildə sakitlik.'],
        'es'=>['Hotel boutique junto al lago','Despertar frente al agua.','18 habitaciones, desayuno con productos locales y calma junto a la orilla.'],
        'rooms'=>[['Doppelzimmer Seeblick','Balkon, Blick auf den See',2,15900],['Junior Suite','Wohnbereich, freistehende Wanne',3,21900],['Einzelzimmer Garten','Ruhig, zum Garten',1,9900]]],
     'taxi'=>['name'=>'Taxi Rhein','city'=>'Düsseldorf','phone'=>'+49 211 000000',
        'de'=>['24/7 · Düsseldorf','In 8 Minuten bei dir.','Taxi, Flughafen-Transfer und Kurierfahrten – per Anruf, WhatsApp oder Chat. Rund um die Uhr.','Fahrer, die die Stadt kennen','Seit über 20 Jahren fahren wir Düsseldorf: freundlich, ortskundig und mit gepflegten Fahrzeugen.'],
        'en'=>['24/7 · Düsseldorf','With you in 8 minutes.','Taxi, airport transfers and courier rides – by phone, WhatsApp or chat. Around the clock.','Drivers who know the city','Friendly, local drivers and well-kept vehicles.'],
        'fr'=>['24h/24 · Düsseldorf','Chez vous en 8 minutes.','Taxi, transferts aéroport et courses express – par téléphone, WhatsApp ou chat.'],
        'tr'=>['7/24 · Düsseldorf','8 dakikada kapınızda.','Taksi, havalimanı transferi ve kurye – telefon, WhatsApp veya sohbetle.'],
        'az'=>['24/7 · Düsseldorf','8 dəqiqəyə yanınızda.','Taksi, hava limanı transferi və kuryer – zəng, WhatsApp və ya söhbətlə.'],
        'es'=>['24/7 · Düsseldorf','En 8 minutos contigo.','Taxi, traslados al aeropuerto y mensajería – por teléfono, WhatsApp o chat.'],
        'cards'=>[['Stadtfahrt','Abholung in wenigen Minuten'],['Flughafen DUS','Festpreis aus der Innenstadt'],['Kurierfahrt','Dokumente & Pakete, direkt']]],
     'shop'=>['name'=>'Linnea Concept Store','city'=>'Berlin','phone'=>'+49 30 0000000',
        'de'=>['Design & Wohnaccessoires · Berlin','Schöne Dinge für jeden Tag.','Keramik, Leinen und Papeterie von kleinen Manufakturen – im Laden in Prenzlauer Berg und auf Anfrage per Versand.','Ausgewählt, nicht ausgedacht','Wir kennen fast alle Menschen, deren Dinge wir verkaufen. Das merkt man an der Qualität – und am Preis, der fair bleibt.'],
        'en'=>['Design & homeware · Berlin','Beautiful things for every day.','Ceramics, linen and stationery from small makers – in our Prenzlauer Berg store and shipped on request.','Chosen, not invented','We know almost every maker whose products we sell.'],
        'fr'=>['Design & déco · Berlin','De belles choses pour chaque jour.','Céramique, lin et papeterie de petits ateliers.'],
        'tr'=>['Tasarım & ev aksesuarları · Berlin','Her gün için güzel şeyler.','Küçük atölyelerden seramik, keten ve kırtasiye.'],
        'az'=>['Dizayn & ev aksesuarları · Berlin','Hər gün üçün gözəl əşyalar.','Kiçik emalatxanalardan keramika, kətan və dəftərxana.'],
        'es'=>['Diseño y hogar · Berlín','Cosas bonitas para cada día.','Cerámica, lino y papelería de pequeños talleres.'],
        'cards'=>[['Keramik','Handgedreht, spülmaschinenfest'],['Leinen','Tischwäsche & Plaids'],['Papeterie','Notizbücher, Karten, Stifte']]],
     'personal'=>['name'=>'Mara Klein','city'=>'Leipzig','phone'=>'',
        'de'=>['Fotografin · Hochzeiten & Porträts','Geschichten, in Licht erzählt.','Ich begleite Hochzeiten, Familien und Menschen mit Ideen – natürlich, unaufgeregt und mit einem Blick für die leisen Momente.','Hallo, ich bin Mara.','Fotografie ist für mich Zuhören mit der Kamera. Ich arbeite am liebsten mit vorhandenem Licht und viel Zeit.'],
        'en'=>['Photographer · Weddings & portraits','Stories told in light.','I photograph weddings, families and people with ideas – natural, calm and with an eye for the quiet moments.','Hi, I’m Mara.','For me, photography is listening with a camera. I prefer available light and plenty of time.'],
        'fr'=>['Photographe · Mariages & portraits','Des histoires racontées en lumière.','Mariages, familles et portraits – naturels, sereins, attentifs aux moments discrets.'],
        'tr'=>['Fotoğrafçı · Düğün & portre','Işıkla anlatılan hikâyeler.','Düğünler, aileler ve portreler – doğal, sakin ve sessiz anlara dikkatle.'],
        'az'=>['Fotoqraf · Toy və portret','İşıqla danışılan hekayələr.','Toylar, ailələr və portretlər – təbii, sakit və səssiz anlara diqqətlə.'],
        'es'=>['Fotógrafa · Bodas y retratos','Historias contadas con luz.','Bodas, familias y retratos – naturales, tranquilos y atentos a los momentos sutiles.'],
        'cards'=>[['Hochzeiten','Ganztägig oder in Etappen'],['Porträts','Draußen oder im Studio'],['Familien','Zu Hause, entspannt']]],
    ][$tpl];
    $L=in_array($lang,SITE_LANGS,true) ? $lang : 'en'; $base=$D[$L] ?? $D['en']; $en=$D['de'] ?? $D['en'];
    $src=in_array($L,['de'],true) ? $D['de'] : $D['en'];
    $tx=['hero_kicker'=>$base[0],'hero_title'=>$base[1],'hero_text'=>$base[2],'about_title'=>$base[3] ?? $src[3],'about_text'=>$base[4] ?? $src[4]];
    $sv=[]; $sl=$L==='de' ? 'de' : 'en';
    foreach($D['cards'] ?? [] as $c) $sv[]=['name'=>[$sl=>$c[0]],'desc'=>[$sl=>$c[1]],'price'=>''];
    $data=['biz'=>['name'=>$D['name'],'phone'=>$D['phone'],'whatsapp'=>$D['phone'],'email'=>'','street'=>'Musterstraße 1','zip'=>'00000','city'=>$D['city'],'country'=>'DE','lat'=>null,'lon'=>null],
      'hours'=>$tpl==='taxi' ? array_fill_keys(['0','1','2','3','4','5','6'],[['00:00','24:00']]) : ['1'=>[['09:00','18:00']],'2'=>[['09:00','18:00']],'3'=>[['09:00','18:00']],'4'=>[['09:00','20:00']],'5'=>[['09:00','18:00']],'6'=>[['10:00','14:00']],'0'=>[]],
      'sections'=>array_map(fn($s)=>['id'=>$s,'on'=>in_array($s,$T['sections'],true) && !in_array($s,['team','gallery','reviews','faq'],true)],SITE_SECTIONS),'services'=>$sv,'services_auto'=>true,'faq'=>[],'team'=>[],'reviews'=>[],'gallery'=>[],
      'hero_img'=>null,'about_img'=>null,'logo'=>null,'cta'=>['primary'=>'auto'],'widget'=>false,'legal'=>['owner'=>$D['name']],'texts'=>[$L=>$tx],
      '_demo'=>['menu'=>$D['menu'] ?? [],'svc'=>$D['svc'] ?? [],'rooms'=>$D['rooms'] ?? []]];
    return ['id'=>0,'account_id'=>0,'slug'=>'demo-'.$tpl,'status'=>'draft','template'=>$tpl,'lang_default'=>$L,'langs'=>[$L],'theme'=>['preset'=>$T['preset']]+$P+['font'=>$T['font']],'data'=>$data,
      'seo'=>[$L=>['title'=>$D['name'].' · '.$base[0],'desc'=>$base[2]]],'custom_domain'=>'','pub'=>null,'created_at'=>null,'updated_at'=>null,'published_at'=>null,'exported_at'=>null];
}

/**
 * Seite rendern. $o: mode public|preview|demo|thumb|export, page home|impressum|datenschutz, token (Vorschau), flash (f_ok|f_err|f_rate|f_off),
 * assets (id → URL; sonst aus der DB), widget_key, mods (site_modules), msvc (site_module_services), ver (Cache-Version für Links), host (eigene Domain).
 */
function site_render(array $S, string $lang, array $o=[]): string {
    $mode=$o['mode'] ?? 'public'; $page=$o['page'] ?? 'home'; $pv=in_array($mode,['preview'],true); $demo=in_array($mode,['demo','thumb'],true); $export=$mode==='export';
    $L=in_array($lang,$S['langs'],true) ? $lang : $S['lang_default']; $def=$S['lang_default']; $d=$S['data']; $tpl=$S['template']; $T=SITE_TEMPLATES[$tpl];
    $b=$d['biz'] ?? []; $tx=(array)($d['texts'][$L] ?? []); $txd=(array)($d['texts'][$def] ?? []);
    $t=function(string $k, string $ui='') use($tx,$txd,$L){ $v=(string)($tx[$k] ?? ($txd[$k] ?? '')); return $v!=='' ? $v : ($ui!=='' ? sr_t($L,$ui) : ''); };
    $u=fn(string $k, array $v=[])=>sr_t($L,$k,$v); $phl=$u('ph');
    $X=fn(string $s, bool $para=false)=>sr_x($s,$pv,$para,$phl);
    /* Bilder */
    if(isset($o['assets'])) $AU=$o['assets']; else { $AU=[]; if($S['id']>0) foreach(site_assets($S['id']) as $a) $AU[$a['id']]=$a['url']; }
    $img=fn($id)=>($id && isset($AU[(int)$id])) ? (string)$AU[(int)$id] : '';
    /* Links */
    $ver=$o['ver'] ?? '';
    $href=function(string $l, string $p='home') use($S,$mode,$o,$def,$ver,$tpl): string {
        if(($o['mode'] ?? '')==='export'){ $f=['home'=>'index','impressum'=>'impressum','datenschutz'=>'datenschutz'][$p] ?? 'index'; return $f.($l===$def ? '' : '-'.$l).'.html'; }
        if(in_array($o['mode'] ?? '',['demo','thumb'],true)) return '/chat/site.php?demo='.$tpl.'&lang='.$l.($p!=='home' ? '&page='.$p : '');
        $q='s='.rawurlencode($S['slug']).($l!==$def ? '&lang='.$l : '').($p!=='home' ? '&page='.$p : '').(($o['mode'] ?? '')==='preview' ? '&preview='.rawurlencode((string)($o['token'] ?? '')) : ($ver!=='' ? '&v='.$ver : ''));
        return '/chat/site.php?'.$q; };
    $home=$href($L,'home');
    /* Module & Knöpfe */
    $mods=$o['mods'] ?? ($S['account_id']>0 ? site_modules($S['account_id']) : ['appt'=>false,'order'=>false,'hotel'=>false,'ride'=>false,'callback'=>false,'book_url'=>'','callback_url'=>'']);
    $wk=!empty($d['widget']) ? ($o['widget_key'] ?? null) : null;
    $country=(string)($b['country'] ?? 'DE'); $tel=site_e164((string)($b['phone'] ?? ''),$country); $wa=site_e164((string)(($b['whatsapp'] ?? '')!=='' ? $b['whatsapp'] : ''),$country);
    $mail=(string)($b['email'] ?? '');
    $act=function(string $k) use($u,$tel,$wa,$wk,$mods,$home,$mail): ?array {
        $chat=fn(string $lab)=>$wk ? ['href'=>'#contact','label'=>$lab,'icon'=>'chat','chat'=>true] : null;
        switch($k){
          case 'book': return ($mods['book_url'] ?? '')!=='' ? ['href'=>$mods['book_url'],'label'=>$u('book'),'icon'=>'cal','ext'=>true] : null;
          case 'callback': return ($mods['callback_url'] ?? '')!=='' ? ['href'=>$mods['callback_url'],'label'=>$u('callback'),'icon'=>'phone','ext'=>true] : null;
          case 'order': return $chat($u('order')) ?? ($wa!=='' ? ['href'=>'https://wa.me/'.ltrim($wa,'+'),'label'=>$u('order'),'icon'=>'wa','ext'=>true] : ($tel!=='' ? ['href'=>'tel:'.$tel,'label'=>$u('order'),'icon'=>'phone'] : null));
          case 'room': return $chat($u('room')) ?? ($wa!=='' ? ['href'=>'https://wa.me/'.ltrim($wa,'+'),'label'=>$u('room'),'icon'=>'wa','ext'=>true] : null);
          case 'ride': return $tel!=='' ? ['href'=>'tel:'.$tel,'label'=>$u('ride'),'icon'=>'car'] : ($wa!=='' ? ['href'=>'https://wa.me/'.ltrim($wa,'+'),'label'=>$u('ride'),'icon'=>'wa','ext'=>true] : null);
          case 'call': return $tel!=='' ? ['href'=>'tel:'.$tel,'label'=>$u('call'),'icon'=>'phone'] : null;
          case 'whatsapp': return $wa!=='' ? ['href'=>'https://wa.me/'.ltrim($wa,'+'),'label'=>$u('whatsapp'),'icon'=>'wa','ext'=>true] : null;
          case 'chat': return $chat($u('chat'));
          case 'contact': return ['href'=>'#contact','label'=>$u('contact_cta'),'icon'=>'arrow'];
        } return null; };
    $prim=(string)($d['cta']['primary'] ?? 'auto');
    $order=$prim!=='auto' ? [$prim] : [];
    $auto=array_values(array_filter([$mods['appt'] ? 'book' : '',$mods['order'] ? 'order' : '',$mods['hotel'] ? 'room' : '',$mods['ride'] ? 'ride' : '',$T['cta'],'whatsapp','call','chat','contact']));
    $cands=[]; foreach(array_merge($order,$auto) as $k){ $a=$act($k); if($a && !in_array($a['href'],array_column($cands,'href'),true)) $cands[]=$a+['k'=>$k]; }
    $P1=$cands[0] ?? $act('contact'); if(($lab=$t('cta_label'))!=='' && sr_has($lab,false)) $P1['label']=$lab;
    $P2=null; foreach(array_slice($cands,1) as $c) if(in_array($c['k'],['call','whatsapp','chat','book'],true)){ $P2=$c; break; }
    $btn=function(?array $a, string $cls='p') use($export): string { if(!$a) return ''; $ext=!empty($a['ext']);
        return '<a class="btn '.$cls.'" href="'.sr_e($a['href']).'"'.($ext ? ' target="_blank" rel="noopener"' : '').(!empty($a['chat']) ? ' data-chat="1"' : '').'>'.sr_icon($a['icon'] ?? 'arrow').'<span>'.sr_e($a['label']).'</span></a>'; };
    /* Abschnitte */
    $on=[]; foreach((array)($d['sections'] ?? []) as $s) if(!empty($s['on']) && in_array($s['id'],SITE_SECTIONS,true)) $on[]=$s['id'];
    $msvc=$o['msvc'] ?? ($S['account_id']>0 ? site_module_services($S['account_id'],$mods) : ['menu'=>[],'services'=>[],'rooms'=>[]]);
    if(isset($d['_demo'])){ $msvc=['menu'=>array_map(fn($x)=>['category'=>$x[0],'name'=>$x[1],'desc'=>$x[2],'price_cents'=>$x[3]],$d['_demo']['menu']),'services'=>array_map(fn($x)=>['name'=>$x[0],'duration'=>$x[1],'price_cents'=>$x[2]],$d['_demo']['svc']),
        'rooms'=>array_map(fn($x)=>['name'=>$x[0],'desc'=>$x[1],'capacity'=>$x[2],'price_cents'=>$x[3],'amenities'=>''],$d['_demo']['rooms'])]; }
    if(empty($d['services_auto'])) $msvc=['menu'=>[],'services'=>[],'rooms'=>[]];
    $name=(string)($b['name'] ?? ''); $nameShow=$name!=='' ? $name : ($pv ? '' : 'ChatHelp');
    $logo=$img($d['logo'] ?? null); $hero=$img($d['hero_img'] ?? null);
    $initial=mb_strtoupper(mb_substr(trim(preg_replace('/[^\p{L}\p{N}]+/u','',$name) ?? '') ?: 'C',0,1));
    $secTitle=['services'=>$msvc['menu'] ? 's_menu' : ($msvc['rooms'] ? 's_rooms' : 's_services'),'about'=>'s_about','team'=>'s_team','gallery'=>'s_gallery','hours'=>'s_hours','location'=>'s_location','reviews'=>'s_reviews','faq'=>'s_faq','contact'=>'s_contact'];
    $addr=trim(implode(', ',array_filter([(string)($b['street'] ?? ''),trim(($b['zip'] ?? '').' '.($b['city'] ?? ''))])));
    $osmQ=rawurlencode(trim($addr.' '.($country ?? '')));
    $osm=($b['lat'] ?? null)!==null ? 'https://www.openstreetmap.org/?mlat='.(float)$b['lat'].'&mlon='.(float)$b['lon'].'#map=17/'.(float)$b['lat'].'/'.(float)$b['lon'] : 'https://www.openstreetmap.org/search?query='.$osmQ;
    $route=($b['lat'] ?? null)!==null ? 'https://www.openstreetmap.org/directions?route=%3B'.(float)$b['lat'].'%2C'.(float)$b['lon'] : 'https://www.openstreetmap.org/search?query='.$osmQ;
    $hasHours=is_array($d['hours'] ?? null) && $d['hours'];
    /* Inhalt je Abschnitt (leer → im öffentlichen Modus ausgelassen) */
    $SEC=[];
    $SEC['services']=function() use($d,$msvc,$L,$def,$X,$t,$u,$T,$pv): string {
        $h=''; $man=(array)($d['services'] ?? []);
        if($msvc['menu']){ $cats=[]; foreach($msvc['menu'] as $x) $cats[$x['category'] ?: '–'][]=$x;
            $h.='<div class="menu">'; foreach($cats as $c=>$items){ $h.='<div class="mcat">'.($c!=='–' ? '<h3>'.sr_e($c).'</h3>' : '');
                foreach($items as $x) $h.='<div class="mi"><div class="mn"><b>'.sr_e($x['name']).'</b>'.($x['desc']!=='' ? '<small>'.sr_e($x['desc']).'</small>' : '').'</div><span class="ld" aria-hidden="true"></span><span class="pr">'.($x['price_cents']>0 ? sr_e(sr_money($x['price_cents'],$L)) : '').'</span></div>';
                $h.='</div>'; } $h.='</div>'; }
        if($msvc['services']){ $h.='<div class="plist">'; foreach($msvc['services'] as $x) $h.='<div class="pl"><div><b>'.sr_e($x['name']).'</b>'.($x['duration']>0 ? '<small>'.sr_e($u('min',['n'=>$x['duration']])).'</small>' : '').'</div><span class="pr">'.($x['price_cents']>0 ? sr_e(sr_money($x['price_cents'],$L)) : '').'</span></div>'; $h.='</div>'; }
        if($msvc['rooms']){ $h.='<div class="grid rooms">'; foreach($msvc['rooms'] as $x) $h.='<article class="card room"><div class="rv"><div class="art" aria-hidden="true"></div></div><div class="rb"><h3>'.sr_e($x['name']).'</h3>'.($x['desc']!=='' ? '<p>'.sr_e($x['desc']).'</p>' : '')
            .'<div class="meta"><span>'.sr_icon('bed').' '.sr_e($u('guests',['n'=>$x['capacity']])).'</span>'.($x['amenities']!=='' ? '<span>'.sr_e($x['amenities']).'</span>' : '').'</div>'.($x['price_cents']>0 ? '<div class="pr">'.sr_e($u('from').' '.sr_money($x['price_cents'],$L).' '.$u('per_night')).'</div>' : '').'</div></article>'; $h.='</div>'; }
        $man=array_values(array_filter($man,fn($x)=>sr_has(sr_lm($x['name'],$L,$def),$pv)));
        if($man){ $list=$T['services']==='list' || $T['services']==='tariff';
            if($list){ $h.='<div class="plist"'.($h!=='' ? ' style="margin-top:34px"' : '').'>'; foreach($man as $x){ $ds=sr_lm($x['desc'] ?? [],$L,$def); $h.='<div class="pl"><div><b>'.$X(sr_lm($x['name'],$L,$def)).'</b>'.($ds!=='' && sr_has($ds,$pv) ? '<small>'.$X($ds).'</small>' : '').'</div><span class="pr">'.$X((string)($x['price'] ?? '')).'</span></div>'; } $h.='</div>'; }
            else { $h.='<div class="grid cards"'.($h!=='' ? ' style="margin-top:34px"' : '').'>'; $i=0; foreach($man as $x){ $i++; $ds=sr_lm($x['desc'] ?? [],$L,$def);
                $h.='<article class="card">'.($T['services']==='cards' && !in_array($T['name'],['Werkstatt'],true) ? '<div class="ci" aria-hidden="true">'.sprintf('%02d',$i).'</div>' : '').'<h3>'.$X(sr_lm($x['name'],$L,$def)).'</h3>'.($ds!=='' && sr_has($ds,$pv) ? '<p>'.$X($ds).'</p>' : '').(($x['price'] ?? '')!=='' ? '<div class="pr">'.$X((string)$x['price']).'</div>' : '').'</article>'; } $h.='</div>'; } }
        return $h; };
    $SEC['about']=function() use($t,$X,$img,$d,$pv,$initial): string { $txt=$t('about_text'); if(!sr_has($txt,$pv)) return ''; $ai=$img($d['about_img'] ?? null);
        return '<div class="about'.($ai==='' ? ' noimg' : '').'"><div class="tx">'.$X($txt,true).'</div>'.($ai!=='' ? '<div class="hv"><img src="'.sr_e($ai).'" alt="'.sr_e(preg_replace('/\[\[.*?\]\]/u','',$t('about_title','s_about')) ?? '').'" loading="lazy" decoding="async"></div>' : '').'</div>'; };
    $SEC['team']=function() use($d,$L,$def,$img,$X): string { $tm=(array)($d['team'] ?? []); if(!$tm) return ''; $h='<div class="grid team">';
        foreach($tm as $x){ $im=$img($x['img'] ?? null); $ini=mb_strtoupper(mb_substr((string)$x['name'],0,1));
            $h.='<article class="tm"><div class="av">'.($im!=='' ? '<img src="'.sr_e($im).'" alt="'.sr_e($x['name']).'" loading="lazy" decoding="async">' : '<span aria-hidden="true">'.sr_e($ini).'</span>').'</div><h3>'.sr_e($x['name']).'</h3><p>'.$X(sr_lm((array)($x['role'] ?? []),$L,$def)).'</p></article>'; }
        return $h.'</div>'; };
    $SEC['gallery']=function() use($d,$img,$L,$def,$name,$t,$pv,$u): string { $g=array_values(array_filter((array)($d['gallery'] ?? []),fn($x)=>$img($x['img'] ?? null)!=='')); if(!$g){ return $pv ? '<div class="gal"><figure class="gph">'.sr_e($u('ph')).' · 📷</figure><figure class="gph">📷</figure><figure class="gph">📷</figure></div>' : ''; }
        $h='<div class="gal">'; $gt=preg_replace('/\[\[.*?\]\]/u','',$t('gallery_title','s_gallery')) ?? '';
        foreach($g as $i=>$x){ $alt=sr_lm((array)($x['alt'] ?? []),$L,$def); if($alt==='') $alt=trim($name.' – '.$gt.' '.($i+1)); $h.='<figure><img src="'.sr_e($img($x['img'])).'" alt="'.sr_e($alt).'" loading="lazy" decoding="async"></figure>'; }
        return $h.'</div>'; };
    $SEC['hours']=function() use($d,$L,$u,$t,$X,$hasHours,$pv,$tel,$b): string {
        $note=$t('hours_note');
        if(!$hasHours) return '<div class="hrs"><div><p class="lead">'.($pv ? '<mark class="ph">'.sr_e($u('ph')).': '.sr_e($u('s_hours')).'</mark>' : sr_e($u('hours_none'))).'</p>'.($note!=='' ? '<p style="margin-top:12px">'.$X($note).'</p>' : '').'</div></div>';
        $rows=''; foreach(sr_hours_rows($d['hours'],$L) as [$dn,$iv]) $rows.='<tr><th scope="row">'.sr_e($dn).'</th>'.($iv ? '<td>'.sr_e(implode(' · ',array_map(fn($x)=>$x[0].'–'.$x[1],$iv))).'</td>' : '<td class="cl">'.sr_e($u('closed')).'</td>').'</tr>';
        return '<div class="hrs"><table class="ht"><caption class="skip">'.sr_e($u('s_hours')).'</caption><tbody>'.$rows.'</tbody></table><div>'.($note!=='' && sr_has($note,$pv) ? '<p class="lead">'.$X($note).'</p>' : '').($tel!=='' ? '<p style="margin-top:18px"><a class="btn l" href="tel:'.sr_e($tel).'">'.sr_icon('phone').'<span>'.sr_e((string)$b['phone']).'</span></a></p>' : '').'</div></div>'; };
    $SEC['location']=function() use($addr,$b,$u,$t,$X,$osm,$route,$name,$pv): string { if($addr==='') return $pv ? '<p class="lead"><mark class="ph">'.sr_e($u('ph')).': '.sr_e($u('address')).'</mark></p>' : '';
        $lt=$t('location_text');
        return '<div class="loc"><div><address class="adr"><b>'.sr_e($name).'</b>'.sr_e((string)($b['street'] ?? '')).'<br>'.sr_e(trim(($b['zip'] ?? '').' '.($b['city'] ?? ''))).'</address>'.($lt!=='' && sr_has($lt,$pv) ? '<p style="margin-top:14px;color:var(--ink2)">'.$X($lt).'</p>' : '')
          .'<div class="ctas"><a class="btn p" href="'.sr_e($route).'" target="_blank" rel="noopener nofollow">'.sr_icon('pin').'<span>'.sr_e($u('route')).'</span></a><a class="btn l" href="'.sr_e($osm).'" target="_blank" rel="noopener nofollow"><span>'.sr_e($u('map')).'</span></a></div><p class="note">'.sr_e($u('map_note')).'</p></div>'
          .'<a class="mapart" href="'.sr_e($osm).'" target="_blank" rel="noopener nofollow" aria-label="'.sr_e($u('map').': '.$addr).'"><svg viewBox="0 0 400 260" preserveAspectRatio="xMidYMid slice" aria-hidden="true"><g fill="none" stroke="currentColor" stroke-opacity=".18" stroke-width="10" stroke-linecap="round"><path d="M-20 70 C 80 90 160 40 260 80 S 420 150 440 140"/><path d="M40 300 C 80 200 120 160 210 130 S 330 40 360 -20"/></g><g fill="none" stroke="currentColor" stroke-opacity=".1" stroke-width="3"><path d="M0 190h400M0 30h400M70 0v260M300 0v260M150 0v260"/></g></svg><span class="pin" aria-hidden="true"></span><span class="lb">'.sr_icon('pin').' '.sr_e($addr).'</span></a></div>'; };
    $SEC['reviews']=function() use($d,$u): string { $r=(array)($d['reviews'] ?? []); if(!$r) return ''; $h='<div class="grid revs">';
        foreach($r as $x){ $n=max(1,min(5,(int)$x['stars'])); $h.='<figure class="card rev" style="margin:0"><div class="st" role="img" aria-label="'.sr_e($u('stars',['n'=>$n])).'">'.str_repeat('★',$n).str_repeat('☆',5-$n).'</div><blockquote>'.nl2br(sr_e($x['text']),false).'</blockquote>'.($x['author']!=='' ? '<figcaption><cite>'.sr_e($x['author']).'</cite></figcaption>' : '').'</figure>'; }
        return $h.'</div><p class="rnote">'.sr_e($u('reviews_note')).'</p>'; };
    $SEC['faq']=function() use($d,$L,$def,$X,$pv): string { $f=array_values(array_filter((array)($d['faq'] ?? []),fn($x)=>sr_has(sr_lm($x['q'],$L,$def),$pv))); if(!$f) return ''; $h='<div class="faq">';
        foreach($f as $x) $h.='<details><summary>'.$X(sr_lm($x['q'],$L,$def)).'</summary><div class="an">'.$X(sr_lm((array)($x['a'] ?? []),$L,$def),true).'</div></details>';
        return $h.'</div>'; };
    $SEC['contact']=function() use($S,$b,$u,$t,$X,$tel,$wa,$mail,$wk,$mode,$o,$L,$href,$pv,$demo,$export,$mods): string {
        $ct=$t('contact_text'); $m='';
        if($tel!=='') $m.='<li><a href="tel:'.sr_e($tel).'"><i>'.sr_icon('phone').'</i><span><small>'.sr_e($u('phone')).'</small>'.sr_e((string)$b['phone']).'</span></a></li>';
        if($wa!=='') $m.='<li><a href="https://wa.me/'.sr_e(ltrim($wa,'+')).'" target="_blank" rel="noopener"><i>'.sr_icon('wa').'</i><span><small>WhatsApp</small>'.sr_e((string)(($b['whatsapp'] ?? '')!=='' ? $b['whatsapp'] : $b['phone'])).'</span></a></li>';
        if($mail!=='') $m.='<li><a href="mailto:'.sr_e($mail).'"><i>'.sr_icon('mail').'</i><span><small>'.sr_e($u('email')).'</small>'.sr_e($mail).'</span></a></li>';
        if($wk) $m.='<li><a href="#contact" data-chat="1"><i>'.sr_icon('chat').'</i><span><small>Chat</small>'.sr_e($u('chat')).'</span></a></li>';
        if(($mods['callback_url'] ?? '')!=='') $m.='<li><a href="'.sr_e($mods['callback_url']).'" target="_blank" rel="noopener"><i>'.sr_icon('phone').'</i><span>'.sr_e($u('callback')).'</span></a></li>';
        $fl=(string)($o['flash'] ?? ''); $msg=$fl==='f_ok' ? '<div class="msg ok" role="status">'.sr_e($u('f_ok')).'</div>' : (in_array($fl,['f_err','f_rate','f_off'],true) ? '<div class="msg err" role="alert">'.sr_e($u($fl)).'</div>' : '');
        $live=in_array($mode,['public','export'],true);
        $action=$export ? SITE_BASE.'/chat/site.php?s='.rawurlencode($S['slug']).'&a=lead&r=export' : '/chat/site.php?s='.rawurlencode($S['slug']).'&a=lead';
        $priv='<a href="'.sr_e($href($L,'datenschutz')).'">'.sr_e($u('privacy_full')).'</a>';
        $form='<form class="cf" method="post" action="'.sr_e($action).'" id="cform">'.$msg.(!$live ? '<div class="msg info">'.sr_e($u('f_off')).'</div>' : '')
          .'<label>'.sr_e($u('f_name')).'<input name="name" required maxlength="120" autocomplete="name"'.(!$live ? ' disabled' : '').'></label>'
          .'<label>'.sr_e($u('f_contact')).'<input name="contact" required maxlength="190" autocomplete="email"'.(!$live ? ' disabled' : '').'></label>'
          .'<label>'.sr_e($u('f_msg')).'<textarea name="message" required maxlength="3000"'.(!$live ? ' disabled' : '').'></textarea></label>'
          .'<span class="hp" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></span>'
          .'<input type="hidden" name="lang" value="'.sr_e($L).'"><input type="hidden" name="t" value="'.($live && $S['id']>0 ? sr_e(site_form_token($S)) : '').'">'
          .'<p class="fine">'.str_replace('{privacy}',$priv,sr_e(str_replace('{privacy}','__P__',$u('f_consent')))).'</p>'
          .'<button class="btn p" type="submit"'.(!$live ? ' disabled' : '').'>'.sr_icon('arrow').'<span>'.sr_e($u('f_send')).'</span></button></form>';
        $form=str_replace('__P__',$priv,$form);
        return '<div class="ct"><div>'.($ct!=='' && sr_has($ct,$pv) ? '<p class="lead">'.$X($ct).'</p>' : '').($m!=='' ? '<ul class="cm">'.$m.'</ul>' : '').'</div>'.$form.'</div>'; };
    /* ---- Seite zusammensetzen ---- */
    $seo=(array)($S['seo'][$L] ?? []); $seoD=(array)($S['seo'][$def] ?? []);
    $strip=fn(string $s)=>trim(preg_replace('/\s+/u',' ',preg_replace('/\[\[.*?\]\]/u','',$s) ?? '') ?? '');
    $title=$strip((string)($seo['title'] ?? '')) ?: $strip((string)($seoD['title'] ?? '')) ?: $strip($nameShow.($b['city'] ?? '' ? ' · '.$b['city'] : ''));
    $desc=$strip((string)($seo['desc'] ?? '')) ?: $strip($t('hero_text')) ?: $strip($nameShow);
    if($page==='impressum') $title=$u('imprint').' · '.$strip($nameShow); if($page==='datenschutz') $title=$u('privacy_full').' · '.$strip($nameShow);
    $title=mb_substr($title,0,90); $desc=mb_substr($desc,0,200);
    $canon=$mode==='public' ? site_url($S,$L,$page==='home' ? '' : $page) : '';
    if($mode==='public' && ($o['host'] ?? '')!=='') $canon='https://'.$o['host'].'/chat/site.php?s='.rawurlencode($S['slug']).($L!==$def ? '&lang='.$L : '').($page!=='home' ? '&page='.$page : '');
    $noindex=$mode!=='public' && !$export || $page!=='home';
    $h='<!DOCTYPE html><html lang="'.sr_e(SITE_LANG_TAGS[$L] ?? $L).'" dir="ltr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    $h.='<title>'.sr_e($title).'</title><meta name="description" content="'.sr_e($desc).'">'.($noindex ? '<meta name="robots" content="noindex,follow">' : '<meta name="robots" content="index,follow,max-image-preview:large">');
    $h.='<meta name="theme-color" content="'.sr_e(site_hex($S['theme']['accent'] ?? '','#b8862b')).'">';
    if($canon!==''){ $h.='<link rel="canonical" href="'.sr_e($canon).'">';
        if($page==='home' && count($S['langs'])>1){ foreach($S['langs'] as $l) $h.='<link rel="alternate" hreflang="'.sr_e($l==='no' ? 'nb' : $l).'" href="'.sr_e(site_url($S,$l)).'">'; $h.='<link rel="alternate" hreflang="x-default" href="'.sr_e(site_url($S)).'">'; } }
    $ogImg=$hero!=='' ? (preg_match('#^https?://#',$hero) ? $hero : SITE_BASE.$hero) : '';
    $h.='<meta property="og:type" content="website"><meta property="og:title" content="'.sr_e($title).'"><meta property="og:description" content="'.sr_e($desc).'"><meta property="og:locale" content="'.sr_e(str_replace('-','_',SITE_LANG_TAGS[$L] ?? $L)).'">'
      .($canon!=='' ? '<meta property="og:url" content="'.sr_e($canon).'">' : '').($ogImg!=='' && !$export ? '<meta property="og:image" content="'.sr_e($ogImg).'"><meta name="twitter:card" content="summary_large_image">' : '');
    $h.='<link rel="icon" href="'.($logo!=='' ? sr_e($logo) : ($export ? 'data:,' : '/chat/icons/icon-32.png')).'">';
    $h.='<style>'.sr_vars($S['theme']).sr_css().sr_art_css($tpl).'</style>';
    /* schema.org */
    if($page==='home' && !$demo){
        $type=$tpl==='personal' ? 'Person' : (SITE_SECTOR_SCHEMA[(site_profile($S['account_id'])['sectors'][0] ?? '')] ?? $T['schema']);
        $ld=['@context'=>'https://schema.org','@type'=>$type,'name'=>$strip($name),'url'=>$canon ?: null,'description'=>$desc];
        if($tel!=='') $ld['telephone']=$tel; if($mail!=='') $ld['email']=$mail; if($ogImg!=='') $ld['image']=$ogImg; if($logo!=='' && $type!=='Person') $ld['logo']=preg_match('#^https?://#',$logo) ? $logo : SITE_BASE.$logo;
        if($addr!=='' && $type!=='Person') $ld['address']=['@type'=>'PostalAddress','streetAddress'=>(string)($b['street'] ?? ''),'postalCode'=>(string)($b['zip'] ?? ''),'addressLocality'=>(string)($b['city'] ?? ''),'addressCountry'=>$country];
        if(($b['lat'] ?? null)!==null && $type!=='Person') $ld['geo']=['@type'=>'GeoCoordinates','latitude'=>(float)$b['lat'],'longitude'=>(float)$b['lon']];
        if($hasHours && $type!=='Person'){ $dn=['0'=>'Sunday','1'=>'Monday','2'=>'Tuesday','3'=>'Wednesday','4'=>'Thursday','5'=>'Friday','6'=>'Saturday']; $sp=[];
            foreach($d['hours'] as $k=>$iv) foreach($iv as $x) $sp[]=['@type'=>'OpeningHoursSpecification','dayOfWeek'=>$dn[(string)$k] ?? 'Monday','opens'=>$x[0],'closes'=>$x[1]==='24:00' ? '23:59' : $x[1]]; if($sp) $ld['openingHoursSpecification']=$sp; }
        if($type==='Person' && ($kick=$strip($t('hero_kicker')))!=='') $ld['jobTitle']=$kick;
        $ld=array_filter($ld,fn($v)=>$v!==null && $v!=='');
        $h.='<script type="application/ld+json">'.json_encode($ld,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).'</script>';
    }
    $h.='</head><body class="t-'.sr_e($tpl).' h-'.sr_e($T['hero']).($P1 ? ' hasbar' : '').'">';
    $h.='<a class="skip" href="#main">'.sr_e($u('skip')).'</a>';
    if($pv) $h.='<div class="draftbar"><b>●</b> '.sr_e($u('draft')).'</div>';
    /* Kopf */
    $navItems=[]; foreach(['services','about','gallery','hours','location','contact'] as $k) if(in_array($k,$on,true) && $page==='home') $navItems[$k]=preg_replace('/\[\[.*?\]\]/u','',$t($k==='services' ? 'services_title' : $k.'_title',$secTitle[$k])) ?: $u($secTitle[$k]);
    $navItems=array_slice($navItems,0,5,true);
    $langDd='';
    if(count($S['langs'])>1){ $langDd='<details class="dd lsw"><summary aria-label="'.sr_e($u('lang')).'">'.strtoupper(sr_e($L)).'</summary><div class="ddm">';
        foreach($S['langs'] as $l) $langDd.='<a href="'.sr_e($href($l,$page)).'" lang="'.sr_e(SITE_LANG_TAGS[$l] ?? $l).'" hreflang="'.sr_e($l==='no' ? 'nb' : $l).'"'.($l===$L ? ' aria-current="true"' : '').'>'.sr_e(SITE_LANG_NAMES[$l]).'<small>'.strtoupper(sr_e($l)).'</small></a>';
        $langDd.='</div></details>'; }
    $h.='<header class="hd"><div class="w"><a class="brand" href="'.sr_e($home).'">'.($logo!=='' ? '<img src="'.sr_e($logo).'" alt="'.sr_e($strip($name) ?: 'Logo').'" width="120" height="42">' : '<span class="mono" aria-hidden="true">'.sr_e($initial).'</span>').'<span class="bn">'.($name!=='' ? sr_e($name) : ($pv ? '<mark class="ph">'.sr_e($u('ph')).'</mark>' : '')).'</span></a>';
    if($navItems){ $h.='<nav class="nv" aria-label="'.sr_e($u('menu')).'">'; foreach($navItems as $k=>$lab) $h.='<a href="#'.$k.'">'.sr_e($lab).'</a>'; $h.='</nav>'; }
    $h.='<div class="hdr">'.$langDd;
    if($navItems){ $h.='<details class="dd mnav"><summary>'.sr_e($u('menu')).'</summary><div class="ddm">'; foreach($navItems as $k=>$lab) $h.='<a href="#'.$k.'">'.sr_e($lab).'</a>'; $h.='</div></details>'; }
    if($page==='home' && $P1) $h.=str_replace('class="btn p"','class="btn p s hb"',$btn($P1));
    $h.='</div></div></header><main id="main">';
    if($page==='impressum' || $page==='datenschutz'){
        $h.=sr_legal($S,$L,$page,$u,$href,$pv); $h.='</main>';
    } else {
        /* Hero */
        $kick=$t('hero_kicker'); $ht=$t('hero_title'); if(!sr_has($ht,$pv)) $ht=$name; $hx=$t('hero_text');
        $facts='';
        if(($b['city'] ?? '')!=='') $facts.='<li>'.sr_icon('pin').sr_e((string)$b['city']).'</li>';
        if($hasHours && in_array('hours',$on,true)) $facts.='<li><a href="#hours">'.sr_icon('clock').sr_e($u('s_hours')).'</a></li>';
        if($tel!=='' && $tpl!=='taxi') $facts.='<li><a href="tel:'.sr_e($tel).'">'.sr_icon('phone').sr_e((string)$b['phone']).'</a></li>';
        $visual=$hero!=='' ? '<div class="hv"><img src="'.sr_e($hero).'" alt="'.sr_e($strip($ht) ?: $strip($name)).'" fetchpriority="high" decoding="async"></div>' : '<div class="hv"><div class="art" aria-hidden="true">'.(in_array($T['hero'],['split','card'],true) ? '<span class="mg">'.sr_e($initial).'</span>' : '').'</div></div>';
        $copy=($kick!=='' && sr_has($kick,$pv) ? '<span class="kick">'.$X($kick).'</span>' : '').'<h1>'.($ht!=='' ? $X($ht) : '<mark class="ph">'.sr_e($u('ph')).'</mark>').'</h1>'.($hx!=='' && sr_has($hx,$pv) ? '<p class="lead">'.$X($hx).'</p>' : '')
          .($tpl==='taxi' && $tel!=='' ? '<a class="bigtel" href="tel:'.sr_e($tel).'">'.sr_e((string)$b['phone']).'</a>' : '')
          .'<div class="ctas">'.$btn($P1,'p').($P2 ? $btn($P2,$T['hero']==='full' ? 'o' : 'l') : '').'</div>'.($facts!=='' ? '<ul class="facts">'.$facts.'</ul>' : '');
        if(in_array('hero',$on,true)){
            if($T['hero']==='full') $h.='<section class="hero" aria-label="'.sr_e($strip($name) ?: 'Start').'">'.$visual.'<div class="w">'.$copy.'</div></section>';
            elseif($T['hero']==='center') $h.='<section class="hero" aria-label="'.sr_e($strip($name) ?: 'Start').'"><div class="w">'.($tpl==='personal' ? '<div class="pmono">'.($hero!=='' ? '<img src="'.sr_e($hero).'" alt="'.sr_e($strip($name)).'" fetchpriority="high">' : '<span aria-hidden="true">'.sr_e($initial).'</span>').'</div>' : '').$copy.'</div></section>';
            elseif($T['hero']==='card') $h.='<section class="hero" aria-label="'.sr_e($strip($name) ?: 'Start').'"><div class="w"><div class="hcard">'.$copy.'</div>'.$visual.'</div></section>';
            else $h.='<section class="hero" aria-label="'.sr_e($strip($name) ?: 'Start').'"><div class="w"><div>'.$copy.'</div>'.$visual.'</div></section>';
        } else $h.='<h1 class="skip">'.sr_e($strip($name)).'</h1>';
        $alt=false;
        foreach($on as $k){ if($k==='hero' || !isset($SEC[$k])) continue; $body=($SEC[$k])(); if($body==='') continue; $alt=!$alt;
            $titleKey=$k==='services' ? 'services_title' : $k.'_title'; $st=$t($titleKey,$secTitle[$k]); $sub=in_array($k,['services','team'],true) ? $t($k.'_text') : '';
            $h.='<section class="sec'.($alt ? ' alt' : '').'" id="'.$k.'" aria-labelledby="h-'.$k.'"><div class="w"><div class="sh"><h2 id="h-'.$k.'">'.$X($st).'</h2>'.($sub!=='' && sr_has($sub,$pv) ? '<p>'.$X($sub).'</p>' : '').'</div>'.$body.'</div></section>'; }
        if(!in_array('contact',$on,true)) $h.='<div id="contact"></div>';
        $h.='</main>';
    }
    /* Fuß */
    $ft=$t('footer_text');
    $h.='<footer class="ft"><div class="w"><div><div class="fb">'.sr_e($name).'</div>'.($ft!=='' && sr_has($ft,$pv) ? '<p>'.$X($ft).'</p>' : ($addr!=='' ? '<p>'.sr_e($addr).'</p>' : '')).'</div>'
      .'<nav aria-label="'.sr_e($u('imprint').' · '.$u('privacy')).'"><a href="'.sr_e($href($L,'impressum')).'">'.sr_e($u('imprint')).'</a><a href="'.sr_e($href($L,'datenschutz')).'">'.sr_e($u('privacy')).'</a>'.($page!=='home' ? '<a href="'.sr_e($home).'">'.sr_e($u('back')).'</a>' : '').'</nav>'
      .'<div class="made"><span>© '.date('Y').' '.sr_e($name).'</span><a href="https://chat-help.com/?ref=site" rel="noopener">'.sr_e($u('made')).'</a></div></div></footer>';
    /* Aktionsleiste (Handy) */
    if($P1 && $page==='home'){ $bar=$btn($P1,'p'); $sec=$P2 ?? ($tel!=='' && ($P1['k'] ?? '')!=='call' && ($P1['k'] ?? '')!=='ride' ? $act('call') : null); if($sec) $bar.=$btn($sec,'l');
        $h.='<div class="abar'.($wk ? ' wg' : '').'">'.$bar.'</div>'; }
    if($demo && $mode==='demo') $h.='<div class="demo-rib">'.sr_e($u('demo')).' · ChatHelp</div>';
    if($mode!=='thumb'){ $js=$export ? 'assets/site.js' : '/chat/site-page.js?v='.substr(md5((string)@filemtime(__DIR__.'/site-page.js')),0,8); $h.='<script src="'.sr_e($js).'" defer></script>'; }
    if($wk && in_array($mode,['public','preview','export'],true)) $h.='<script src="'.($export ? SITE_BASE : '').'/chat/widget.js" data-key="'.sr_e($wk).'" data-color="'.sr_e(site_hex($S['theme']['accent'] ?? '','#2a2a3a')).'" async></script>';
    return $h.'</body></html>';
}
/** Impressum / Datenschutz aus den Angaben des Betriebs. */
function sr_legal(array $S, string $L, string $page, callable $u, callable $href, bool $pv): string {
    $l=$S['data']['legal'] ?? []; $b=$S['data']['biz'] ?? [];
    $miss=fn(string $k)=>$pv ? '<mark class="ph">'.sr_e($u('ph')).'</mark>' : '';
    $v=fn(string $k)=>trim((string)($l[$k] ?? ''));
    $adr=($v('owner')!=='' ? sr_e($v('owner')).($v('form')!=='' ? ' '.sr_e($v('form')) : '') : $miss('owner')).'<br>'.($v('street')!=='' ? sr_e($v('street')) : $miss('street')).'<br>'.trim(sr_e($v('zip')).' '.sr_e($v('city'))).($v('country')!=='' && $v('country')!=='DE' ? '<br>'.sr_e($v('country')) : '');
    $mail=$v('email')!=='' ? '<a href="mailto:'.sr_e($v('email')).'">'.sr_e($v('email')).'</a>' : $miss('email');
    $h='<section class="legal"><div class="w">';
    if($page==='impressum'){
        $h.='<h1>'.sr_e($u('imprint')).'</h1><h2>'.sr_e($u('im_provider')).'</h2><address>'.$adr.'</address>';
        if($v('rep')!=='') $h.='<h2>'.sr_e($u('im_rep')).'</h2><p>'.sr_e($v('rep')).'</p>';
        $h.='<h2>'.sr_e($u('im_contact')).'</h2><p>'.sr_e($u('email')).': '.$mail.($v('phone')!=='' ? '<br>'.sr_e($u('phone')).': '.sr_e($v('phone')) : '').'</p>';
        if($v('register')!=='') $h.='<h2>'.sr_e($u('im_register')).'</h2><p>'.sr_e($v('register')).'</p>';
        if($v('vat')!=='') $h.='<h2>'.sr_e($u('im_vat')).'</h2><p>'.sr_e($v('vat')).'</p>';
        if($v('authority')!=='') $h.='<h2>'.sr_e($u('im_authority')).'</h2><p>'.nl2br(sr_e($v('authority')),false).'</p>';
        if($v('responsible')!=='') $h.='<h2>'.sr_e($u('im_content')).'</h2><p>'.sr_e($v('responsible')).'</p>';
        if($v('extra')!=='') $h.='<div style="margin-top:28px">'.sr_x($v('extra'),$pv,true).'</div>';
    } else {
        $h.='<h1>'.sr_e(mb_strtoupper(mb_substr($u('privacy_full'),0,1)).mb_substr($u('privacy_full'),1)).'</h1><h2>'.sr_e($u('pv_ctrl_h')).'</h2><address>'.$adr.'<br>'.sr_e($u('email')).': '.$mail.'</address>';
        $h.='<h2>'.sr_e($u('pv_host_h')).'</h2><p>'.sr_e($u('pv_host')).'</p><h2>'.sr_e($u('pv_form_h')).'</h2><p>'.sr_e($u('pv_form')).'</p>';
        if(!empty($S['data']['widget'])) $h.='<h2>'.sr_e($u('pv_chat_h')).'</h2><p>'.sr_e($u('pv_chat')).'</p>';
        $h.='<h2>'.sr_e($u('pv_links_h')).'</h2><p>'.sr_e($u('pv_links')).'</p><h2>'.sr_e($u('pv_rights_h')).'</h2><p>'.str_replace('{email}',$mail,sr_e(str_replace('{email}','__E__',$u('pv_rights')))).'</p>';
        $h=str_replace('__E__',$mail,$h);
        if($v('privacy_extra')!=='') $h.='<h2>'.sr_e($u('pv_more')).'</h2>'.sr_x($v('privacy_extra'),$pv,true);
    }
    return $h.'<a class="btn l back" href="'.sr_e($href($L,'home')).'">← '.sr_e($u('back')).'</a></div></section>';
}
/** Sitemap einer veröffentlichten Website (alle Sprachen + Alternates). */
function site_sitemap(array $S): string {
    $x='<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">';
    $lm=$S['published_at'] ? gmdate('Y-m-d',strtotime((string)$S['published_at'])) : gmdate('Y-m-d');
    foreach($S['langs'] as $l){ $x.='<url><loc>'.sr_e(site_url($S,$l)).'</loc><lastmod>'.$lm.'</lastmod>';
        if(count($S['langs'])>1) foreach($S['langs'] as $a) $x.='<xhtml:link rel="alternate" hreflang="'.($a==='no' ? 'nb' : $a).'" href="'.sr_e(site_url($S,$a)).'"/>';
        $x.='</url>'; }
    return $x.'</urlset>';
}
/** Fehlerseite (404, nicht veröffentlicht) im Stil der ChatHelp-Seiten. */
function site_notfound(string $L): string {
    $L=in_array($L,SITE_LANGS,true) ? $L : 'de';
    return '<!DOCTYPE html><html lang="'.sr_e(SITE_LANG_TAGS[$L]).'"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.sr_e(sr_t($L,'nf_t')).'</title><meta name="robots" content="noindex">'
      .'<style>'.sr_vars(['preset'=>'gold']+SITE_PRESETS['gold']+['font'=>'classic']).sr_css().'</style></head><body><main class="nf"><div><h1>'.sr_e(sr_t($L,'nf_t')).'</h1><p>'.sr_e(sr_t($L,'nf_p')).'</p><p style="margin-top:26px"><a class="btn p" href="https://chat-help.com/">ChatHelp</a></p></div></main></body></html>';
}
