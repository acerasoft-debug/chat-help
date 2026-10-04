<?php
/**
 * ChatHelp — apply-cv-studio (CH_CV_STUDIO)
 *  Ayri "Bewerbung & CV" bolumu — Staatliche mantigi, AI'siz:
 *   • 4 premium A4 sablon (A Executive / B Modern / C Classic / D Bold)
 *   • Kullanici formu KENDISI doldurur + vesikalik yukler -> canli onizleme
 *   • "PDF erstellen" -> A4 baski penceresi (PDF olarak kaydet)
 *   • Etiketler 10 dilde; veriler localStorage'da (ch_cv_data) kalici
 *   • Kapı: basic/pro/elite ucretsiz; plansiz -> 3,99€ (Stripe ID gelince
 *     satin-al butonu aktiflesecek; simdilik paket secimine yonlendirir)
 * KULLANIM: pull-updates.php?files=apply-cv-studio.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-cv-studio BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__ . '/index.php';
$src  = file_get_contents($file);
if ($src === false) exit("index.php okunamadi\n");
if (strpos($src, 'CH_CV_STUDIO') !== false) exit("Zaten ekli (CH_CV_STUDIO).\n");
file_put_contents($file . '.bak-cvstudio-' . date('Ymd-His'), $src);

$bundle = <<<'CVHTML'
<script id="ch-cv-studio">
/* CH_CV_STUDIO — Bewerbung & CV: 4 premium sablon, kullanici doldurur, foto yukler, PDF olusturur */
try{(function(){
  function UIL(){ try{ return (window.chUIL?window.chUIL():(localStorage.getItem("ch_uilang")||"de")); }catch(e){ return "de"; } }
  function esc(s){ return String(s==null?"":s).replace(/[&<>"]/g,function(c){return{"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;"}[c];}); }

  var T={
   de:{ttl:"Bewerbung & CV",sub:"Wählen Sie eine Premium-Vorlage — Sie füllen aus, wir gestalten.",price:"3,99 € einmalig · kostenlos mit Paket",use:"Vorlage wählen",back:"‹",edit:"Lebenslauf ausfüllen",photo:"Foto",upl:"📷 Foto hochladen",name:"Vor- und Nachname",role:"Berufsbezeichnung",addr:"Adresse",phone:"Telefon",email:"E-Mail",birth:"Geburtsdatum, -ort",prof:"Kurzprofil (2–3 Sätze)",exp:"Berufserfahrung",edu:"Ausbildung",skills:"Fähigkeiten",langs:"Sprachen",d:"Zeitraum",r:"Position / Abschluss",f:"Firma / Institution",t:"Beschreibung (optional)",sk:"Fähigkeit",lv:"Niveau (0–100)",ln:"Sprache",ll:"Niveau",add:"+ Hinzufügen",del:"✕",make:"📄 PDF erstellen",hint:"Im Druckdialog «Als PDF speichern» wählen.",gT:"Premium-Vorlage",gP1:"Diese CV-Vorlage kostet einmalig 3,99 € — mit Basic/Pro/Elite ist sie kostenlos.",gPlans:"Pakete ansehen",gBuy:"3,99 € kaufen (in Kürze)",kontakt:"Kontakt",profil:"Profil",geb:"Geburtsdatum"},
   en:{ttl:"Application & CV",sub:"Pick a premium template — you fill it in, we design it.",price:"€3.99 one-time · free with any plan",use:"Use template",back:"‹",edit:"Fill in your CV",photo:"Photo",upl:"📷 Upload photo",name:"Full name",role:"Job title",addr:"Address",phone:"Phone",email:"Email",birth:"Date, place of birth",prof:"Short profile (2–3 sentences)",exp:"Work experience",edu:"Education",skills:"Skills",langs:"Languages",d:"Period",r:"Position / Degree",f:"Company / Institution",t:"Description (optional)",sk:"Skill",lv:"Level (0–100)",ln:"Language",ll:"Level",add:"+ Add",del:"✕",make:"📄 Create PDF",hint:"Choose “Save as PDF” in the print dialog.",gT:"Premium template",gP1:"This CV template costs €3.99 once — free with Basic/Pro/Elite.",gPlans:"See plans",gBuy:"Buy €3.99 (soon)",kontakt:"Contact",profil:"Profile",geb:"Date of birth"},
   tr:{ttl:"Başvuru & CV",sub:"Premium şablon seç — sen doldur, biz tasarlayalım.",price:"Tek seferlik 3,99 € · paketle ücretsiz",use:"Şablonu kullan",back:"‹",edit:"CV'ni doldur",photo:"Fotoğraf",upl:"📷 Fotoğraf yükle",name:"Ad Soyad",role:"Meslek / pozisyon",addr:"Adres",phone:"Telefon",email:"E-posta",birth:"Doğum tarihi, yeri",prof:"Kısa profil (2–3 cümle)",exp:"İş deneyimi",edu:"Eğitim",skills:"Yetenekler",langs:"Diller",d:"Dönem",r:"Pozisyon / Derece",f:"Firma / Kurum",t:"Açıklama (opsiyonel)",sk:"Yetenek",lv:"Seviye (0–100)",ln:"Dil",ll:"Seviye",add:"+ Ekle",del:"✕",make:"📄 PDF oluştur",hint:"Yazdırma penceresinde «PDF olarak kaydet» seç.",gT:"Premium şablon",gP1:"Bu CV şablonu tek seferlik 3,99 € — Basic/Pro/Elite ile ücretsiz.",gPlans:"Paketleri gör",gBuy:"3,99 € satın al (yakında)",kontakt:"İletişim",profil:"Profil",geb:"Doğum tarihi"},
   fr:{ttl:"Candidature & CV",sub:"Choisissez un modèle premium — vous remplissez, nous mettons en forme.",price:"3,99 € une fois · gratuit avec un forfait",use:"Utiliser le modèle",back:"‹",edit:"Remplir votre CV",photo:"Photo",upl:"📷 Charger une photo",name:"Nom complet",role:"Poste",addr:"Adresse",phone:"Téléphone",email:"E-mail",birth:"Date, lieu de naissance",prof:"Profil court (2–3 phrases)",exp:"Expérience",edu:"Formation",skills:"Compétences",langs:"Langues",d:"Période",r:"Poste / Diplôme",f:"Entreprise / Établissement",t:"Description (facultatif)",sk:"Compétence",lv:"Niveau (0–100)",ln:"Langue",ll:"Niveau",add:"+ Ajouter",del:"✕",make:"📄 Créer le PDF",hint:"Choisissez « Enregistrer en PDF » dans la boîte d'impression.",gT:"Modèle premium",gP1:"Ce modèle coûte 3,99 € une fois — gratuit avec Basic/Pro/Elite.",gPlans:"Voir les forfaits",gBuy:"Acheter 3,99 € (bientôt)",kontakt:"Contact",profil:"Profil",geb:"Date de naissance"},
   es:{ttl:"Candidatura & CV",sub:"Elija una plantilla premium — usted la rellena, nosotros la diseñamos.",price:"3,99 € una vez · gratis con un plan",use:"Usar plantilla",back:"‹",edit:"Rellenar su CV",photo:"Foto",upl:"📷 Subir foto",name:"Nombre completo",role:"Puesto",addr:"Dirección",phone:"Teléfono",email:"Correo",birth:"Fecha, lugar de nacimiento",prof:"Perfil breve (2–3 frases)",exp:"Experiencia",edu:"Formación",skills:"Habilidades",langs:"Idiomas",d:"Período",r:"Puesto / Título",f:"Empresa / Institución",t:"Descripción (opcional)",sk:"Habilidad",lv:"Nivel (0–100)",ln:"Idioma",ll:"Nivel",add:"+ Añadir",del:"✕",make:"📄 Crear PDF",hint:"Elija «Guardar como PDF» en el diálogo de impresión.",gT:"Plantilla premium",gP1:"Esta plantilla cuesta 3,99 € una vez — gratis con Basic/Pro/Elite.",gPlans:"Ver planes",gBuy:"Comprar 3,99 € (pronto)",kontakt:"Contacto",profil:"Perfil",geb:"Fecha de nacimiento"},
   it:{ttl:"Candidatura & CV",sub:"Scegli un modello premium — tu compili, noi impaginiamo.",price:"3,99 € una tantum · gratis con un piano",use:"Usa modello",back:"‹",edit:"Compila il tuo CV",photo:"Foto",upl:"📷 Carica foto",name:"Nome e cognome",role:"Ruolo",addr:"Indirizzo",phone:"Telefono",email:"E-mail",birth:"Data, luogo di nascita",prof:"Profilo breve (2–3 frasi)",exp:"Esperienza",edu:"Istruzione",skills:"Competenze",langs:"Lingue",d:"Periodo",r:"Ruolo / Titolo",f:"Azienda / Istituto",t:"Descrizione (opzionale)",sk:"Competenza",lv:"Livello (0–100)",ln:"Lingua",ll:"Livello",add:"+ Aggiungi",del:"✕",make:"📄 Crea PDF",hint:"Scegli «Salva come PDF» nella finestra di stampa.",gT:"Modello premium",gP1:"Questo modello costa 3,99 € una tantum — gratis con Basic/Pro/Elite.",gPlans:"Vedi piani",gBuy:"Acquista 3,99 € (presto)",kontakt:"Contatto",profil:"Profilo",geb:"Data di nascita"},
   nl:{ttl:"Sollicitatie & CV",sub:"Kies een premium sjabloon — u vult in, wij ontwerpen.",price:"€3,99 eenmalig · gratis met een pakket",use:"Sjabloon gebruiken",back:"‹",edit:"CV invullen",photo:"Foto",upl:"📷 Foto uploaden",name:"Volledige naam",role:"Functie",addr:"Adres",phone:"Telefoon",email:"E-mail",birth:"Geboortedatum, -plaats",prof:"Kort profiel (2–3 zinnen)",exp:"Werkervaring",edu:"Opleiding",skills:"Vaardigheden",langs:"Talen",d:"Periode",r:"Functie / Diploma",f:"Bedrijf / Instelling",t:"Omschrijving (optioneel)",sk:"Vaardigheid",lv:"Niveau (0–100)",ln:"Taal",ll:"Niveau",add:"+ Toevoegen",del:"✕",make:"📄 PDF maken",hint:"Kies «Opslaan als PDF» in het afdrukvenster.",gT:"Premium sjabloon",gP1:"Dit sjabloon kost eenmalig €3,99 — gratis met Basic/Pro/Elite.",gPlans:"Bekijk pakketten",gBuy:"Koop €3,99 (binnenkort)",kontakt:"Contact",profil:"Profiel",geb:"Geboortedatum"},
   pl:{ttl:"Aplikacja & CV",sub:"Wybierz szablon premium — Ty wypełniasz, my projektujemy.",price:"3,99 € jednorazowo · bezpłatnie z pakietem",use:"Użyj szablonu",back:"‹",edit:"Wypełnij CV",photo:"Zdjęcie",upl:"📷 Wgraj zdjęcie",name:"Imię i nazwisko",role:"Stanowisko",addr:"Adres",phone:"Telefon",email:"E-mail",birth:"Data, miejsce urodzenia",prof:"Krótki profil (2–3 zdania)",exp:"Doświadczenie",edu:"Wykształcenie",skills:"Umiejętności",langs:"Języki",d:"Okres",r:"Stanowisko / Tytuł",f:"Firma / Instytucja",t:"Opis (opcjonalnie)",sk:"Umiejętność",lv:"Poziom (0–100)",ln:"Język",ll:"Poziom",add:"+ Dodaj",del:"✕",make:"📄 Utwórz PDF",hint:"Wybierz «Zapisz jako PDF» w oknie drukowania.",gT:"Szablon premium",gP1:"Ten szablon kosztuje 3,99 € jednorazowo — bezpłatnie z Basic/Pro/Elite.",gPlans:"Zobacz pakiety",gBuy:"Kup 3,99 € (wkrótce)",kontakt:"Kontakt",profil:"Profil",geb:"Data urodzenia"},
   sv:{ttl:"Ansökan & CV",sub:"Välj en premiummall — du fyller i, vi formger.",price:"3,99 € engång · gratis med paket",use:"Använd mall",back:"‹",edit:"Fyll i ditt CV",photo:"Foto",upl:"📷 Ladda upp foto",name:"Fullständigt namn",role:"Yrkestitel",addr:"Adress",phone:"Telefon",email:"E-post",birth:"Födelsedatum, -ort",prof:"Kort profil (2–3 meningar)",exp:"Arbetslivserfarenhet",edu:"Utbildning",skills:"Färdigheter",langs:"Språk",d:"Period",r:"Roll / Examen",f:"Företag / Institution",t:"Beskrivning (valfritt)",sk:"Färdighet",lv:"Nivå (0–100)",ln:"Språk",ll:"Nivå",add:"+ Lägg till",del:"✕",make:"📄 Skapa PDF",hint:"Välj «Spara som PDF» i utskriftsdialogen.",gT:"Premiummall",gP1:"Denna mall kostar 3,99 € engång — gratis med Basic/Pro/Elite.",gPlans:"Se paket",gBuy:"Köp 3,99 € (snart)",kontakt:"Kontakt",profil:"Profil",geb:"Födelsedatum"},
   pt:{ttl:"Candidatura & CV",sub:"Escolha um modelo premium — você preenche, nós desenhamos.",price:"3,99 € única vez · grátis com plano",use:"Usar modelo",back:"‹",edit:"Preencher o CV",photo:"Foto",upl:"📷 Carregar foto",name:"Nome completo",role:"Cargo",addr:"Morada",phone:"Telefone",email:"E-mail",birth:"Data, local de nascimento",prof:"Perfil breve (2–3 frases)",exp:"Experiência",edu:"Formação",skills:"Competências",langs:"Idiomas",d:"Período",r:"Cargo / Grau",f:"Empresa / Instituição",t:"Descrição (opcional)",sk:"Competência",lv:"Nível (0–100)",ln:"Idioma",ll:"Nível",add:"+ Adicionar",del:"✕",make:"📄 Criar PDF",hint:"Escolha «Guardar como PDF» na janela de impressão.",gT:"Modelo premium",gP1:"Este modelo custa 3,99 € uma vez — grátis com Basic/Pro/Elite.",gPlans:"Ver planos",gBuy:"Comprar 3,99 € (em breve)",kontakt:"Contacto",profil:"Perfil",geb:"Data de nascimento"}
  };
  function L(){ return T[UIL()]||T.en; }
  var PHOTO='data:image/svg+xml;utf8,'+encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" fill="#c9d2e8"/><circle cx="50" cy="38" r="17" fill="#8b98b8"/><path d="M18 92 q0-28 32-28 q32 0 32 28 z" fill="#8b98b8"/></svg>');

  /* ── A4 sablon CSS (sayfada onizleme + baski penceresi ortak) ── */
  var TPLCSS=[
  ".cvA4{width:794px;height:1123px;position:relative;overflow:hidden;background:#fff;font-family:'Inter','Segoe UI',Arial,sans-serif}",
  ".cvA4 *{margin:0;padding:0;box-sizing:border-box}",
  /* A */
  ".cvA4 .tA{display:flex;height:100%}",
  ".cvA4 .tA .side{width:262px;background:linear-gradient(175deg,#0e2758,#0a1a3f 60%,#0a1533);color:#fff;padding:34px 24px;display:flex;flex-direction:column}",
  ".cvA4 .tA .ph{width:132px;height:132px;border-radius:50%;margin:0 auto 20px;border:4px solid rgba(212,168,74,.75);overflow:hidden;background:#dfe6fb}",
  ".cvA4 .tA .ph img{width:100%;height:100%;object-fit:cover}",
  ".cvA4 .tA .sh{font-size:11px;font-weight:800;letter-spacing:2px;text-transform:uppercase;color:#d8b25a;margin:22px 0 10px;border-bottom:1px solid rgba(216,178,90,.35);padding-bottom:6px}",
  ".cvA4 .tA .kv{font-size:11.5px;line-height:1.5;color:#cfd9f2;margin-bottom:7px;display:flex;gap:8px}",
  ".cvA4 .tA .kv b{color:#fff;font-weight:600}",
  ".cvA4 .tA .skill{font-size:11.5px;color:#e6ecfb;margin-bottom:8px}",
  ".cvA4 .tA .bar{height:5px;background:rgba(255,255,255,.14);border-radius:100px;margin-top:4px}",
  ".cvA4 .tA .bar i{display:block;height:100%;background:linear-gradient(90deg,#d8b25a,#f0c860);border-radius:100px}",
  ".cvA4 .tA .main{flex:1;padding:44px 38px 34px;color:#1c1c26}",
  ".cvA4 .tA h1{font-size:34px;font-weight:800;letter-spacing:-.8px;color:#0a1a3f;line-height:1.05}",
  ".cvA4 .tA .role{font-size:14px;color:#8a6a1f;font-weight:700;letter-spacing:1.6px;text-transform:uppercase;margin:8px 0 4px}",
  ".cvA4 .tA .rule{width:64px;height:4px;background:linear-gradient(90deg,#d8b25a,#f0c860);border-radius:100px;margin:14px 0 20px}",
  ".cvA4 .tA h2{font-size:14px;font-weight:800;letter-spacing:1.6px;text-transform:uppercase;color:#0a1a3f;margin:24px 0 12px;display:flex;align-items:center;gap:10px}",
  ".cvA4 .tA h2::after{content:'';flex:1;height:1px;background:#e3e3ea}",
  ".cvA4 .tA p.prof{font-size:12.5px;line-height:1.65;color:#3a3a48}",
  ".cvA4 .tA .xp{display:flex;gap:14px;margin-bottom:15px}",
  ".cvA4 .tA .xd{width:92px;flex-shrink:0;font-size:11px;color:#8a8a9a;font-weight:600;padding-top:2px}",
  ".cvA4 .tA .xr{font-size:13.5px;font-weight:700;color:#16162a}",
  ".cvA4 .tA .xf{font-size:11.5px;color:#8a6a1f;font-weight:600;margin:2px 0 4px}",
  ".cvA4 .tA .xt{font-size:11.5px;line-height:1.55;color:#4a4a58}",
  /* B */
  ".cvA4 .tB{height:100%;display:flex;flex-direction:column}",
  ".cvA4 .tB .hd{background:#f7f7f4;padding:38px 46px;display:flex;align-items:center;gap:26px;border-bottom:3px solid #d8b25a}",
  ".cvA4 .tB .ph{width:110px;height:110px;border-radius:18px;overflow:hidden;background:#e8e8e2;flex-shrink:0}",
  ".cvA4 .tB .ph img{width:100%;height:100%;object-fit:cover}",
  ".cvA4 .tB h1{font-size:32px;font-weight:800;letter-spacing:-.7px;color:#17171f}",
  ".cvA4 .tB .role{font-size:13px;color:#8a6a1f;font-weight:700;letter-spacing:1.8px;text-transform:uppercase;margin-top:5px}",
  ".cvA4 .tB .cont{margin-left:auto;text-align:right;font-size:11px;line-height:1.7;color:#55555f}",
  ".cvA4 .tB .cols{flex:1;display:flex;padding:30px 46px;gap:34px}",
  ".cvA4 .tB .cl{flex:1.55}.cvA4 .tB .cr{flex:1;border-left:1px solid #ececea;padding-left:30px}",
  ".cvA4 .tB h2{font-size:12px;font-weight:800;letter-spacing:2px;text-transform:uppercase;color:#17171f;margin:0 0 12px}",
  ".cvA4 .tB h2 span{color:#c9a24a}",
  ".cvA4 .tB .sec{margin-bottom:24px}",
  ".cvA4 .tB p.prof{font-size:12px;line-height:1.7;color:#45454f}",
  ".cvA4 .tB .xr{font-size:13px;font-weight:700;color:#1c1c24}",
  ".cvA4 .tB .xf{font-size:11px;color:#8a6a1f;font-weight:600;margin:1px 0 3px}",
  ".cvA4 .tB .xd{font-size:10.5px;color:#98989f;font-weight:600}",
  ".cvA4 .tB .xt{font-size:11px;line-height:1.6;color:#4c4c56;margin-top:3px}",
  ".cvA4 .tB .xp{margin-bottom:14px}",
  ".cvA4 .tB .tag{display:inline-block;font-size:10.5px;font-weight:600;color:#584a20;background:#f5edd8;border-radius:100px;padding:5px 12px;margin:0 6px 7px 0}",
  ".cvA4 .tB .lg{font-size:11.5px;color:#45454f;margin-bottom:7px;display:flex;justify-content:space-between}",
  ".cvA4 .tB .lg b{color:#1c1c24}",
  ".cvA4 .tB .lg span{color:#98989f}",
  /* C */
  ".cvA4 .tC{height:100%;font-family:Georgia,'Times New Roman',serif;color:#22222a;padding:48px 56px}",
  ".cvA4 .tC .top{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid #22222a;padding-bottom:22px}",
  ".cvA4 .tC h1{font-size:31px;font-weight:700;letter-spacing:.5px}",
  ".cvA4 .tC .role{font-size:13px;font-style:italic;color:#6a5a2a;margin-top:6px}",
  ".cvA4 .tC .cont{font-size:11px;line-height:1.7;color:#55555d;margin-top:10px;font-family:'Inter',Arial,sans-serif}",
  ".cvA4 .tC .ph{width:104px;height:128px;overflow:hidden;background:#eee;border:1px solid #cfcfd4;flex-shrink:0}",
  ".cvA4 .tC .ph img{width:100%;height:100%;object-fit:cover}",
  ".cvA4 .tC h2{font-size:13px;font-weight:700;letter-spacing:2.5px;text-transform:uppercase;margin:26px 0 12px;color:#22222a}",
  ".cvA4 .tC h2 i{font-style:normal;color:#b28c34}",
  ".cvA4 .tC p.prof{font-size:12.5px;line-height:1.75;text-align:justify;color:#3a3a42}",
  ".cvA4 .tC table{width:100%;border-collapse:collapse}",
  ".cvA4 .tC td{vertical-align:top;padding:5px 0;font-size:12px;line-height:1.6}",
  ".cvA4 .tC td.d{width:150px;color:#77776f;font-family:'Inter',Arial,sans-serif;font-size:10.5px;font-weight:600;padding-top:7px}",
  ".cvA4 .tC .xr{font-weight:700}",
  ".cvA4 .tC .xf{font-style:italic;color:#6a5a2a}",
  ".cvA4 .tC .xt{color:#4a4a52;font-size:11.5px;margin-top:2px}",
  ".cvA4 .tC .foot{position:absolute;bottom:38px;left:56px;right:56px;border-top:1px solid #d9d9de;padding-top:10px;font-size:10px;color:#8a8a90;font-family:'Inter',Arial,sans-serif;display:flex;justify-content:space-between}",
  /* D */
  ".cvA4 .tD{height:100%}",
  ".cvA4 .tD .hd{background:linear-gradient(120deg,#17171f,#232330);color:#fff;padding:42px 46px;position:relative;overflow:hidden}",
  ".cvA4 .tD .hd::after{content:'';position:absolute;right:-70px;top:-70px;width:260px;height:260px;border-radius:50%;background:radial-gradient(circle,rgba(216,178,90,.28),transparent 70%)}",
  ".cvA4 .tD .hrow{display:flex;align-items:center;gap:26px;position:relative;z-index:1}",
  ".cvA4 .tD .ph{width:116px;height:116px;border-radius:50%;overflow:hidden;background:#2c2c38;border:3px solid #d8b25a;flex-shrink:0}",
  ".cvA4 .tD .ph img{width:100%;height:100%;object-fit:cover}",
  ".cvA4 .tD h1{font-size:36px;font-weight:800;letter-spacing:-1px;line-height:1.02}",
  ".cvA4 .tD h1 em{font-style:normal;color:#ecc060}",
  ".cvA4 .tD .role{font-size:12.5px;font-weight:700;letter-spacing:2.4px;text-transform:uppercase;color:#b9b9cc;margin-top:8px}",
  ".cvA4 .tD .strip{display:flex;background:#d8b25a;color:#241c04;font-size:10.5px;font-weight:700;padding:10px 46px;gap:26px;letter-spacing:.2px}",
  ".cvA4 .tD .body{display:flex;gap:32px;padding:30px 46px}",
  ".cvA4 .tD .cl{flex:1.6}.cvA4 .tD .cr{flex:1}",
  ".cvA4 .tD h2{font-size:12.5px;font-weight:800;letter-spacing:2px;text-transform:uppercase;color:#17171f;border-left:4px solid #d8b25a;padding-left:10px;margin:0 0 13px}",
  ".cvA4 .tD .sec{margin-bottom:24px}",
  ".cvA4 .tD p.prof{font-size:12px;line-height:1.7;color:#42424c}",
  ".cvA4 .tD .xp{padding-left:16px;border-left:2px solid #eee5cd;margin-bottom:14px;position:relative}",
  ".cvA4 .tD .xp::before{content:'';position:absolute;left:-6px;top:4px;width:10px;height:10px;border-radius:50%;background:#d8b25a}",
  ".cvA4 .tD .xr{font-size:13px;font-weight:700;color:#1c1c24}",
  ".cvA4 .tD .xf{font-size:11px;color:#8a6a1f;font-weight:700;margin:1px 0 2px}",
  ".cvA4 .tD .xd{font-size:10.5px;color:#98989f;font-weight:600}",
  ".cvA4 .tD .xt{font-size:11px;line-height:1.6;color:#4c4c56;margin-top:3px}",
  ".cvA4 .tD .skill{font-size:11.5px;color:#33333d;margin-bottom:9px}",
  ".cvA4 .tD .bar{height:6px;background:#efefe9;border-radius:100px;margin-top:4px}",
  ".cvA4 .tD .bar i{display:block;height:100%;background:linear-gradient(90deg,#c9a24a,#ecc060);border-radius:100px}",
  ".cvA4 .tD .lg{font-size:11.5px;color:#42424c;margin-bottom:7px;display:flex;justify-content:space-between}"
  ].join("\n");

  /* ── veri ── */
  function defData(){
    return {tpl:"A",photo:"",name:"",role:"",addr:"",phone:"",email:"",birth:"",prof:"",
      exp:[{d:"",r:"",f:"",t:""}],edu:[{d:"",r:"",f:"",t:""}],
      skills:[["",80]],langs:[["",""]]};
  }
  var DATA=null;
  function loadData(){
    try{ var s=localStorage.getItem("ch_cv_data"); if(s){ DATA=JSON.parse(s); } }catch(e){}
    if(!DATA||typeof DATA!=="object") DATA=defData();
    ["exp","edu","skills","langs"].forEach(function(k){ if(!Array.isArray(DATA[k])||!DATA[k].length) DATA[k]=defData()[k]; });
    /* profilden otomatik doldur (bos alanlara) */
    try{
      var P2=window.P||{};
      if(!DATA.name && (P2.f1||P2.f2)) DATA.name=((P2.f1||"")+" "+(P2.f2||"")).trim();
      if(!DATA.addr && (P2.f3||P2.f4)) DATA.addr=[P2.f3,P2.f4].filter(Boolean).join(", ");
      if(!DATA.email && P2.f7) DATA.email=P2.f7;
      if(!DATA.phone && P2.f5) DATA.phone=P2.f5;
    }catch(e){}
  }
  function saveData(){ try{ localStorage.setItem("ch_cv_data", JSON.stringify(DATA)); }catch(e){} }

  /* ── sablon uretici ── */
  function xpRows(list,flat){
    return list.filter(function(x){return x.r||x.f||x.d;}).map(function(x){
      var core='<div class="xr">'+esc(x.r)+'</div><div class="xf">'+esc(x.f)+'</div>'+(x.t?'<div class="xt">'+esc(x.t)+'</div>':'');
      return flat
        ? '<div class="xp"><div class="xd">'+esc(x.d)+'</div>'+core+'</div>'
        : '<div class="xp"><div class="xd">'+esc(x.d)+'</div><div>'+core+'</div></div>';
    }).join("");
  }
  function build(d){
    var l=L(), ph=d.photo||PHOTO, h="";
    var skills=d.skills.filter(function(s){return s[0];});
    var langs=d.langs.filter(function(s){return s[0];});
    var name=d.name||"—", role=d.role||"";
    if(d.tpl==="A"){
      h='<div class="tA"><div class="side">'
       +'<div class="ph"><img src="'+ph+'"></div>'
       +'<div class="sh">'+l.kontakt+'</div>'
       +(d.addr?'<div class="kv">📍 <b>'+esc(d.addr)+'</b></div>':'')+(d.phone?'<div class="kv">📞 <b>'+esc(d.phone)+'</b></div>':'')
       +(d.email?'<div class="kv">✉️ <b>'+esc(d.email)+'</b></div>':'')+(d.birth?'<div class="kv">🎂 <b>'+esc(d.birth)+'</b></div>':'')
       +(skills.length?'<div class="sh">'+l.skills+'</div>'+skills.map(function(s){return '<div class="skill">'+esc(s[0])+'<div class="bar"><i style="width:'+(+s[1]||70)+'%"></i></div></div>';}).join(''):'')
       +(langs.length?'<div class="sh">'+l.langs+'</div>'+langs.map(function(s){return '<div class="skill">'+esc(s[0])+(s[1]?' — <span style="color:#aab8d8">'+esc(s[1])+'</span>':'')+'</div>';}).join(''):'')
       +'</div><div class="main">'
       +'<h1>'+esc(name)+'</h1>'+(role?'<div class="role">'+esc(role)+'</div>':'')+'<div class="rule"></div>'
       +(d.prof?'<h2>'+l.profil+'</h2><p class="prof">'+esc(d.prof)+'</p>':'')
       +'<h2>'+l.exp+'</h2>'+xpRows(d.exp,false)
       +'<h2>'+l.edu+'</h2>'+xpRows(d.edu,false)
       +'</div></div>';
    } else if(d.tpl==="B"){
      h='<div class="tB"><div class="hd">'
       +'<div class="ph"><img src="'+ph+'"></div>'
       +'<div><h1>'+esc(name)+'</h1>'+(role?'<div class="role">'+esc(role)+'</div>':'')+'</div>'
       +'<div class="cont">'+esc(d.addr)+'<br>'+esc(d.phone)+'<br>'+esc(d.email)+'</div>'
       +'</div><div class="cols"><div class="cl">'
       +(d.prof?'<div class="sec"><h2><span>—</span> '+l.profil+'</h2><p class="prof">'+esc(d.prof)+'</p></div>':'')
       +'<div class="sec"><h2><span>—</span> '+l.exp+'</h2>'+xpRows(d.exp,true)+'</div>'
       +'<div class="sec"><h2><span>—</span> '+l.edu+'</h2>'+xpRows(d.edu,true)+'</div>'
       +'</div><div class="cr">'
       +(skills.length?'<div class="sec"><h2><span>—</span> '+l.skills+'</h2>'+skills.map(function(s){return '<span class="tag">'+esc(s[0])+'</span>';}).join('')+'</div>':'')
       +(langs.length?'<div class="sec"><h2><span>—</span> '+l.langs+'</h2>'+langs.map(function(s){return '<div class="lg"><b>'+esc(s[0])+'</b><span>'+esc(s[1])+'</span></div>';}).join('')+'</div>':'')
       +(d.birth?'<div class="sec"><h2><span>—</span> '+l.geb+'</h2><div class="lg"><b>'+esc(d.birth)+'</b></div></div>':'')
       +'</div></div></div>';
    } else if(d.tpl==="C"){
      var tRow=function(x){return '<tr><td class="d">'+esc(x.d)+'</td><td><span class="xr">'+esc(x.r)+'</span>'+(x.f?' · <span class="xf">'+esc(x.f)+'</span>':'')+(x.t?'<div class="xt">'+esc(x.t)+'</div>':'')+'</td></tr>';};
      h='<div class="tC"><div class="top"><div>'
       +'<h1>'+esc(name)+'</h1>'+(role?'<div class="role">'+esc(role)+'</div>':'')
       +'<div class="cont">'+esc(d.addr)+(d.phone?' · '+esc(d.phone):'')+'<br>'+esc(d.email)+(d.birth?' · '+l.geb+': '+esc(d.birth):'')+'</div>'
       +'</div><div class="ph"><img src="'+ph+'"></div></div>'
       +(d.prof?'<h2><i>I.</i> '+l.profil+'</h2><p class="prof">'+esc(d.prof)+'</p>':'')
       +'<h2><i>II.</i> '+l.exp+'</h2><table>'+d.exp.filter(function(x){return x.r||x.f;}).map(tRow).join('')+'</table>'
       +'<h2><i>III.</i> '+l.edu+'</h2><table>'+d.edu.filter(function(x){return x.r||x.f;}).map(tRow).join('')+'</table>'
       +((skills.length||langs.length)?'<h2><i>IV.</i> '+l.skills+' &amp; '+l.langs+'</h2><table>'
         +(skills.length?'<tr><td class="d">'+l.skills+'</td><td>'+skills.map(function(s){return esc(s[0]);}).join(' · ')+'</td></tr>':'')
         +(langs.length?'<tr><td class="d">'+l.langs+'</td><td>'+langs.map(function(s){return esc(s[0])+(s[1]?' ('+esc(s[1])+')':'');}).join(' · ')+'</td></tr>':'')+'</table>':'')
       +'<div class="foot"><span>'+esc(name)+'</span><span>Lebenslauf</span></div>'
       +'</div>';
    } else {
      var parts=String(name).split(" ");
      h='<div class="tD"><div class="hd"><div class="hrow">'
       +'<div class="ph"><img src="'+ph+'"></div>'
       +'<div><h1>'+esc(parts[0])+' <em>'+esc(parts.slice(1).join(" "))+'</em></h1>'+(role?'<div class="role">'+esc(role)+'</div>':'')+'</div>'
       +'</div></div>'
       +'<div class="strip">'+(d.addr?'<span>📍 '+esc(d.addr)+'</span>':'')+(d.phone?'<span>📞 '+esc(d.phone)+'</span>':'')+(d.email?'<span>✉️ '+esc(d.email)+'</span>':'')+'</div>'
       +'<div class="body"><div class="cl">'
       +(d.prof?'<div class="sec"><h2>'+l.profil+'</h2><p class="prof">'+esc(d.prof)+'</p></div>':'')
       +'<div class="sec"><h2>'+l.exp+'</h2>'+xpRows(d.exp,true)+'</div>'
       +'<div class="sec"><h2>'+l.edu+'</h2>'+xpRows(d.edu,true)+'</div>'
       +'</div><div class="cr">'
       +(skills.length?'<div class="sec"><h2>'+l.skills+'</h2>'+skills.map(function(s){return '<div class="skill">'+esc(s[0])+'<div class="bar"><i style="width:'+(+s[1]||70)+'%"></i></div></div>';}).join('')+'</div>':'')
       +(langs.length?'<div class="sec"><h2>'+l.langs+'</h2>'+langs.map(function(s){return '<div class="lg"><b>'+esc(s[0])+'</b><span style="color:#98989f">'+esc(s[1])+'</span></div>';}).join('')+'</div>':'')
       +(d.birth?'<div class="sec"><h2>'+l.geb+'</h2><div class="lg"><b>'+esc(d.birth)+'</b></div></div>':'')
       +'</div></div></div>';
    }
    return '<div class="cvA4">'+h+'</div>';
  }

  /* ── panel CSS ── */
  function addCss(){
    if(document.getElementById("ch-cvst-css")) return;
    var st=document.createElement("style"); st.id="ch-cvst-css";
    st.textContent=TPLCSS+"\n"+[
      "#pnl-cv{position:fixed;inset:0;z-index:520;background:var(--bg,#17171d);display:none;flex-direction:column;overflow:hidden}",
      "#pnl-cv.on{display:flex}",
      ".cvs-topbar{display:flex;align-items:center;gap:10px;padding:14px 16px;border-bottom:1px solid var(--bdr,rgba(255,255,255,.1));background:var(--s1,#1d1d24);flex-shrink:0}",
      ".cvs-back{width:34px;height:34px;border-radius:10px;border:1px solid var(--bdr,rgba(255,255,255,.12));background:rgba(255,255,255,.05);color:var(--t2,#e0e0ff);font-size:20px;cursor:pointer}",
      ".cvs-ttl{font-size:16px;font-weight:800;color:var(--t1,#fff)}",
      ".cvs-price{margin-left:auto;font-size:11px;font-weight:700;color:var(--gold,#d4a84a);border:1px solid rgba(212,168,74,.35);border-radius:100px;padding:5px 12px}",
      ".cvs-scroll{flex:1;overflow-y:auto;padding:16px}",
      ".cvs-wrap{max-width:1060px;margin:0 auto}",
      ".cvs-sub{font-size:13px;color:var(--t3,#a8a8d0);margin-bottom:14px}",
      ".cvs-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px}",
      ".cvs-card{border:1px solid rgba(212,168,74,.18);border-radius:16px;overflow:hidden;background:var(--s2,#25252e);cursor:pointer;transition:transform .14s,box-shadow .2s,border-color .2s}",
      ".cvs-card:hover{transform:translateY(-2px);border-color:var(--gold,#d4a84a);box-shadow:0 14px 34px rgba(0,0,0,.35)}",
      ".cvs-thumb{height:180px;overflow:hidden;position:relative;background:#fff}",
      ".cvs-thumb .cvA4{transform:scale(0.253);transform-origin:top left;position:absolute;top:0;left:50%;margin-left:-100px}",
      ".cvs-card-b{padding:11px 13px}",
      ".cvs-card-n{font-size:14px;font-weight:800;color:var(--t1,#fff)}",
      ".cvs-card-u{font-size:11.5px;color:var(--gold,#d4a84a);font-weight:700;margin-top:3px}",
      ".cvs-ed{display:flex;gap:18px;align-items:flex-start}",
      ".cvs-form{flex:1;min-width:300px;max-width:440px}",
      ".cvs-prev{flex:1;position:sticky;top:0}",
      ".cvs-prevbox{border:1px solid var(--bdr,rgba(255,255,255,.12));border-radius:12px;overflow:hidden;background:#3a3a44;padding:10px;display:flex;justify-content:center}",
      ".cvs-previn{overflow:hidden;position:relative}",
      "@media(max-width:900px){.cvs-ed{flex-direction:column}.cvs-prev{position:static;width:100%}}",
      ".cvs-lbl{font-size:11px;font-weight:800;letter-spacing:.8px;text-transform:uppercase;color:var(--gold,#d4a84a);margin:16px 0 7px}",
      ".cvs-in{width:100%;padding:11px 12px;margin-bottom:8px;background:var(--s2,#25252e);border:1px solid var(--bdr,rgba(255,255,255,.12));border-radius:10px;color:var(--t1,#fff);font-size:13.5px;font-family:inherit}",
      ".cvs-in:focus{outline:none;border-color:rgba(212,168,74,.5)}",
      "textarea.cvs-in{resize:vertical;min-height:64px}",
      ".cvs-row{display:flex;gap:8px}.cvs-row .cvs-in{flex:1}",
      ".cvs-item{border:1px solid var(--bdr,rgba(255,255,255,.1));border-radius:12px;padding:10px;margin-bottom:9px;background:rgba(255,255,255,.02);position:relative}",
      ".cvs-x{position:absolute;top:7px;right:7px;width:24px;height:24px;border-radius:8px;border:1px solid rgba(224,80,80,.4);background:rgba(224,80,80,.1);color:#ff9d9d;font-size:12px;cursor:pointer}",
      ".cvs-add{width:100%;padding:9px;border-radius:10px;border:1px dashed rgba(212,168,74,.4);background:transparent;color:var(--gold,#d4a84a);font-size:12.5px;font-weight:700;cursor:pointer;margin-bottom:8px}",
      ".cvs-tpls{display:flex;gap:7px;margin-bottom:4px}",
      ".cvs-tplb{flex:1;padding:8px;border-radius:10px;border:1.5px solid var(--bdr,rgba(255,255,255,.12));background:var(--s2,#25252e);color:var(--t2,#e0e0ff);font-size:12.5px;font-weight:800;cursor:pointer}",
      ".cvs-tplb.on{border-color:var(--gold,#d4a84a);color:var(--gold,#d4a84a);background:rgba(212,168,74,.1)}",
      ".cvs-photo{display:flex;align-items:center;gap:12px;margin-bottom:6px}",
      ".cvs-photo img{width:58px;height:58px;border-radius:12px;object-fit:cover;border:1px solid var(--bdr,rgba(255,255,255,.15))}",
      ".cvs-upl{padding:10px 14px;border-radius:10px;border:1px solid rgba(212,168,74,.35);background:rgba(212,168,74,.1);color:var(--gold,#d4a84a);font-size:13px;font-weight:700;cursor:pointer}",
      ".cvs-make{width:100%;padding:14px;margin:14px 0 6px;background:linear-gradient(135deg,#d4a84a,#f0c860);color:#1a1400;border:none;border-radius:12px;font-size:15px;font-weight:800;cursor:pointer}",
      ".cvs-hint{font-size:11.5px;color:var(--t3,#a8a8d0);text-align:center;margin-bottom:16px}"
    ].join("\n");
    document.head.appendChild(st);
  }

  /* ── panel ── */
  var VIEW="cat";
  function panel(){
    var p=document.getElementById("pnl-cv");
    if(!p){ p=document.createElement("div"); p.className="pnl"; p.id="pnl-cv"; document.body.appendChild(p); }
    return p;
  }
  function sampleData(tpl){
    var l=L();
    var d=defData(); d.tpl=tpl; d.name="Max Mustermann"; d.role="—";
    return d;
  }
  function renderCat(){
    VIEW="cat"; var l=L(), p=panel();
    var names={A:"Executive",B:"Modern",C:"Classic",D:"Bold"};
    p.innerHTML='<div class="cvs-topbar"><button class="cvs-back" onclick="gP(\'chat\')">‹</button><div class="cvs-ttl">'+esc(l.ttl)+'</div><div class="cvs-price">'+esc(l.price)+'</div></div>'
     +'<div class="cvs-scroll"><div class="cvs-wrap">'
     +'<div class="cvs-sub">'+esc(l.sub)+'</div>'
     +'<div class="cvs-grid">'+["A","B","C","D"].map(function(k){
        return '<div class="cvs-card" data-t="'+k+'"><div class="cvs-thumb">'+build(sampleData(k))+'</div>'
          +'<div class="cvs-card-b"><div class="cvs-card-n">'+k+' — '+names[k]+'</div><div class="cvs-card-u">'+esc(l.use)+' →</div></div></div>';
      }).join("")+'</div></div></div>';
    p.querySelectorAll(".cvs-card").forEach(function(c){
      c.addEventListener("click",function(){ DATA.tpl=c.getAttribute("data-t"); saveData(); renderEd(); });
    });
  }
  var _pt=null;
  function schedPrev(){ clearTimeout(_pt); _pt=setTimeout(updPrev,240); }
  function updPrev(){
    try{
      var box=document.getElementById("cvs-previn"); if(!box) return;
      box.innerHTML=build(DATA);
      var w=box.parentNode.clientWidth-0;
      var sc=Math.min(1,(w)/794);
      var a4=box.querySelector(".cvA4");
      a4.style.transform="scale("+sc+")"; a4.style.transformOrigin="top left";
      box.style.width=(794*sc)+"px"; box.style.height=(1123*sc)+"px";
    }catch(e){}
  }
  function inp(id,val,ph2){ return '<input class="cvs-in" id="'+id+'" value="'+esc(val)+'" placeholder="'+esc(ph2||"")+'">'; }
  function listEd(kind,l){
    var isSk=(kind==="skills"), isLg=(kind==="langs");
    return DATA[kind].map(function(x,i){
      if(isSk) return '<div class="cvs-item"><button class="cvs-x" data-k="'+kind+'" data-i="'+i+'">✕</button><div class="cvs-row">'
        +'<input class="cvs-in" data-k="'+kind+'" data-i="'+i+'" data-f="0" value="'+esc(x[0])+'" placeholder="'+esc(l.sk)+'">'
        +'<input class="cvs-in" style="max-width:90px" type="number" min="0" max="100" data-k="'+kind+'" data-i="'+i+'" data-f="1" value="'+esc(x[1])+'" placeholder="'+esc(l.lv)+'"></div></div>';
      if(isLg) return '<div class="cvs-item"><button class="cvs-x" data-k="'+kind+'" data-i="'+i+'">✕</button><div class="cvs-row">'
        +'<input class="cvs-in" data-k="'+kind+'" data-i="'+i+'" data-f="0" value="'+esc(x[0])+'" placeholder="'+esc(l.ln)+'">'
        +'<input class="cvs-in" data-k="'+kind+'" data-i="'+i+'" data-f="1" value="'+esc(x[1])+'" placeholder="'+esc(l.ll)+'"></div></div>';
      return '<div class="cvs-item"><button class="cvs-x" data-k="'+kind+'" data-i="'+i+'">✕</button>'
        +'<input class="cvs-in" data-k="'+kind+'" data-i="'+i+'" data-f="d" value="'+esc(x.d)+'" placeholder="'+esc(l.d)+'">'
        +'<div class="cvs-row"><input class="cvs-in" data-k="'+kind+'" data-i="'+i+'" data-f="r" value="'+esc(x.r)+'" placeholder="'+esc(l.r)+'">'
        +'<input class="cvs-in" data-k="'+kind+'" data-i="'+i+'" data-f="f" value="'+esc(x.f)+'" placeholder="'+esc(l.f)+'"></div>'
        +'<input class="cvs-in" data-k="'+kind+'" data-i="'+i+'" data-f="t" value="'+esc(x.t)+'" placeholder="'+esc(l.t)+'">'
        +'</div>';
    }).join("")+'<button class="cvs-add" data-add="'+kind+'">'+esc(l.add)+'</button>';
  }
  function renderEd(){
    VIEW="ed"; var l=L(), p=panel();
    p.innerHTML='<div class="cvs-topbar"><button class="cvs-back" id="cvs-b2">‹</button><div class="cvs-ttl">'+esc(l.edit)+'</div><div class="cvs-price">'+esc(l.price)+'</div></div>'
     +'<div class="cvs-scroll"><div class="cvs-wrap"><div class="cvs-ed">'
     +'<div class="cvs-form">'
       +'<div class="cvs-tpls">'+["A","B","C","D"].map(function(k){return '<button class="cvs-tplb'+(DATA.tpl===k?" on":"")+'" data-tpl="'+k+'">'+k+'</button>';}).join("")+'</div>'
       +'<div class="cvs-lbl">'+esc(l.photo)+'</div>'
       +'<div class="cvs-photo"><img id="cvs-phimg" src="'+(DATA.photo||PHOTO)+'"><label class="cvs-upl">'+esc(l.upl)+'<input id="cvs-file" type="file" accept="image/*" style="display:none"></label></div>'
       +'<div class="cvs-lbl">'+esc(l.name)+' / '+esc(l.role)+'</div>'
       +inp("cvs-name",DATA.name,l.name)+inp("cvs-role",DATA.role,l.role)
       +'<div class="cvs-lbl">'+esc(l.kontakt)+'</div>'
       +inp("cvs-addr",DATA.addr,l.addr)
       +'<div class="cvs-row">'+inp("cvs-phone",DATA.phone,l.phone)+inp("cvs-email",DATA.email,l.email)+'</div>'
       +inp("cvs-birth",DATA.birth,l.birth)
       +'<div class="cvs-lbl">'+esc(l.prof)+'</div>'
       +'<textarea class="cvs-in" id="cvs-prof" rows="3" placeholder="'+esc(l.prof)+'">'+esc(DATA.prof)+'</textarea>'
       +'<div class="cvs-lbl">'+esc(l.exp)+'</div><div id="cvs-exp">'+listEd("exp",l)+'</div>'
       +'<div class="cvs-lbl">'+esc(l.edu)+'</div><div id="cvs-edu">'+listEd("edu",l)+'</div>'
       +'<div class="cvs-lbl">'+esc(l.skills)+'</div><div id="cvs-skills">'+listEd("skills",l)+'</div>'
       +'<div class="cvs-lbl">'+esc(l.langs)+'</div><div id="cvs-langs">'+listEd("langs",l)+'</div>'
       +'<button class="cvs-make" id="cvs-make">'+esc(l.make)+'</button>'
       +'<div class="cvs-hint">'+esc(l.hint)+'</div>'
     +'</div>'
     +'<div class="cvs-prev"><div class="cvs-prevbox"><div class="cvs-previn" id="cvs-previn"></div></div></div>'
     +'</div></div></div>';
    document.getElementById("cvs-b2").addEventListener("click",renderCat);
    p.querySelectorAll(".cvs-tplb").forEach(function(b){ b.addEventListener("click",function(){ DATA.tpl=b.getAttribute("data-tpl"); saveData(); renderEd(); }); });
    var bindings=[["cvs-name","name"],["cvs-role","role"],["cvs-addr","addr"],["cvs-phone","phone"],["cvs-email","email"],["cvs-birth","birth"],["cvs-prof","prof"]];
    bindings.forEach(function(bd){
      var el=document.getElementById(bd[0]);
      if(el) el.addEventListener("input",function(){ DATA[bd[1]]=el.value; saveData(); schedPrev(); });
    });
    p.querySelectorAll("input[data-k]").forEach(function(el){
      el.addEventListener("input",function(){
        var k=el.getAttribute("data-k"), i=+el.getAttribute("data-i"), f=el.getAttribute("data-f");
        if(k==="skills"||k==="langs") DATA[k][i][+f]=el.value; else DATA[k][i][f]=el.value;
        saveData(); schedPrev();
      });
    });
    p.querySelectorAll(".cvs-x").forEach(function(b){
      b.addEventListener("click",function(){ var k=b.getAttribute("data-k"); DATA[k].splice(+b.getAttribute("data-i"),1); if(!DATA[k].length) DATA[k]=defData()[k]; saveData(); renderEd(); });
    });
    p.querySelectorAll(".cvs-add").forEach(function(b){
      b.addEventListener("click",function(){ var k=b.getAttribute("data-add"); DATA[k].push(k==="skills"?["",80]:(k==="langs"?["",""]:{d:"",r:"",f:"",t:""})); saveData(); renderEd(); });
    });
    var fi=document.getElementById("cvs-file");
    if(fi) fi.addEventListener("change",function(){
      var f=fi.files&&fi.files[0]; if(!f) return;
      var rd=new FileReader();
      rd.onload=function(){ DATA.photo=String(rd.result); saveData(); var im=document.getElementById("cvs-phimg"); if(im)im.src=DATA.photo; schedPrev(); };
      rd.readAsDataURL(f);
    });
    document.getElementById("cvs-make").addEventListener("click",makePdf);
    updPrev(); setTimeout(updPrev,300);
  }

  /* ── kapı + PDF ── */
  function hasPlan(){
    var pl=""; try{ pl=(window.P&&P.plan)||JSON.parse(localStorage.getItem("ch_user")||"{}").plan||""; }catch(e){}
    return ["basic","pro","elite"].indexOf(pl)>-1;
  }
  function gate(){
    var l=L();
    var m=document.createElement("div"); m.className="jb-modal";
    m.innerHTML='<div class="jb-sheet" style="max-width:420px"><div class="jb-sheet-h"><b>'+esc(l.gT)+'</b></div>'
     +'<div class="jb-sheet-b" style="white-space:normal">'+esc(l.gP1)+'</div>'
     +'<div class="jb-sheet-f"><button class="jb-mbtn" id="cvs-gbuy" disabled style="opacity:.55">'+esc(l.gBuy)+'</button>'
     +'<button class="jb-mbtn p" id="cvs-gplans">'+esc(l.gPlans)+'</button></div></div>';
    m.addEventListener("click",function(e){ if(e.target===m) m.remove(); });
    document.body.appendChild(m);
    document.getElementById("cvs-gplans").addEventListener("click",function(){ m.remove(); try{ gP("konto"); }catch(e){} });
  }
  function makePdf(){
    if(!hasPlan()){ gate(); return; }
    var html='<!doctype html><html><head><meta charset="utf-8"><title>'+esc(DATA.name||"CV")+' — Lebenslauf</title>'
     +'<style>@page{size:A4;margin:0}html,body{margin:0;padding:0}'+TPLCSS+'</style></head><body>'
     +build(DATA)
     +'<script>window.onload=function(){setTimeout(function(){window.print();},350);};<\/script></body></html>';
    var w=window.open("","_blank");
    if(!w){ alert("Popup?"); return; }
    w.document.open(); w.document.write(html); w.document.close();
  }

  /* ── nav ── */
  function addNav(){
    if(document.getElementById("nav-cv")) return;
    var l=L();
    var nav=document.createElement("div"); nav.className="sn sb-photo-btn"; nav.id="nav-cv";
    nav.setAttribute("onclick","gP('cv')");
    nav.style.cssText="cursor:pointer";
    nav.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="var(--gold,#d4a84a)" stroke-width="1.8" stroke-linecap="round" style="width:20px;height:20px;flex-shrink:0"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/><circle cx="10" cy="13" r="2"/><path d="M6 19c0-2 2-3 4-3s4 1 4 3"/></svg>'
      +'<div><div class="sb-photo-t" id="nav-cv-t">'+esc(l.ttl)+'</div></div>';
    var ref=document.getElementById("nav-jobs")||document.querySelector(".sb-photo-btn");
    if(ref&&ref.parentNode){ ref.parentNode.insertBefore(nav, ref.nextSibling); }
    else { var sb=document.getElementById("sb"); if(sb) sb.appendChild(nav); }
  }

  function boot(){
    addCss(); loadData(); saveData(); addNav();
    if(!document.getElementById("pnl-cv")){ panel(); renderCat(); }
  }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",boot); else boot();
  setInterval(function(){
    try{
      addNav();
      var t=document.getElementById("nav-cv-t"); if(t&&t.textContent!==L().ttl) t.textContent=L().ttl;
      var p=document.getElementById("pnl-cv");
      if(p&&p.classList.contains("on")&&!p.__init){ p.__init=1; }
    }catch(e){}
  },1500);
})();}catch(e){}
</script>
CVHTML;

$n = 0;
$src = preg_replace('/<\/body>/', $bundle . "\n</body>", $src, 1, $n);
if (!$n) exit("HATA: </body> bulunamadi — yazilmadi.\n");

$tmp = tempnam(sys_get_temp_dir(), 'idx') . '.php';
file_put_contents($tmp, $src);
$lo = []; $rc = 0;
exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $lo, $rc);
@unlink($tmp);
if ($rc !== 0) { echo "LINT HATASI — index.php degistirilmedi:\n  " . implode("\n  ", $lo) . "\n"; exit; }
file_put_contents($file, $src);
echo "✓ CH_CV_STUDIO uygulandi.\n";
echo "Test: sol menude 'Bewerbung & CV' -> 4 sablon karti -> sec -> formu doldur, foto yukle -> canli onizleme -> PDF erstellen.\n";
echo "Kapı: plansiz kullanicida 3,99€ modali (satin-al Stripe ID bekliyor), paketlilerde direkt PDF.\n";
