<?php
/**
 * ChatHelp — Persönlicher Assistent im Studio („Lea“ oder ein eigener Name)
 * ==========================================================================
 * Eine realistische, sprechende Assistentin bzw. ein Assistent – Aussehen, Stimme und Persönlichkeit legt jeder Inhaber selbst fest.
 * Der Inhaber erzählt, was erledigt werden soll; der Assistent fragt nach, was er noch lernen muss (Betrieb, Angebot, Zeiten,
 * Kundenfragen, Ton, Kanäle), merkt sich die Antworten und richtet die Agenten mit echten Werkzeugen ein – immer erst nach Rückfrage.
 *
 * Tabellen:  assistant_profiles (Persona je Konto) · assistant_messages (Verlauf) · assistant_memory (gemerkte Fakten)
 * REST (Studio, Bearer-Key):  GET|POST /v1/assistant/profile · GET /v1/assistant/state · POST /v1/assistant/chat
 *                             POST /v1/assistant/speak {mid,i} | {preview:{name,lang,gender,address,tone,rate,pitch,preset}} (audio/mpeg)
 *                             POST /v1/assistant/reset · POST /v1/assistant/forget
 * Sprachen: 13 (ASSISTANT_LANG_TABLE) – Profilsprache, automatische Antwortsprache, Begrüßung, Hörprobe, Stimme und Spracheingabe je Sprache.
 * Stimme: nutzt chat/speech-lib.php (ch_tts_synth …), wenn vorhanden – sonst spricht das Studio-Panel mit der besten Browserstimme.
 * Wichtig: Die Werkzeuge gehören ausschließlich dem Inhaber im Studio – nie in Kundenkanäle (Widget, Telefon, WhatsApp) einbinden.
 */
require_once __DIR__.'/tokens-lib.php';
require_once __DIR__.'/plans.php';
require_once __DIR__.'/appt-lib.php';
require_once __DIR__.'/order-lib.php';
require_once __DIR__.'/agent-tools.php';   // ch_plan_active_any, Fachmodule (Taxi, Hotel, Rückruf, Training) – bereits geladen, wenn agent-tools uns einbindet
if(is_file(__DIR__.'/speech-lib.php')) require_once __DIR__.'/speech-lib.php';
if(is_file(__DIR__.'/profile-lib.php')) require_once __DIR__.'/profile-lib.php';   // Registrierungs-Profil (ch_profile_brief) als kurzer Kontext

const ASSISTANT_COST_TURN = 6;          // Tokens je Antwort
const ASSISTANT_COST_TOOL = 2;          // + je Werkzeugaufruf
const ASSISTANT_HISTORY_AI = 24;        // so viele Nachrichten bekommt die KI als Verlauf
const ASSISTANT_HISTORY_KEEP = 400;     // ältere Nachrichten werden aufgeräumt
const ASSISTANT_MEMORY_MAX = 150;
const ASSISTANT_MSG_MAX = 2000;
const ASSISTANT_PHOTO_MAX = 358400;     // 350 KB (Binärgröße nach dem Verkleinern im Browser)
const ASSISTANT_SPEAK_MAX = 400;
const ASSISTANT_TONES = ['warm','ruhig','energisch','sachlich'];
const ASSISTANT_MOODS = ['herzlich','professionell','locker'];
/**
 * Die 13 Sprachen der Plattform (Deutsch + EN, FR, ES, TR, RU, IT, NL, SV, NO, FI, JA, AZ) – eine Tabelle für Profil, Agenten-Sprache (set_agent_tone),
 * Systemprompt, Begrüßung, Hörprobe, Server-Stimme und das Panel (GET /v1/assistant/profile → langs). de = Name in der deutschen Oberfläche,
 * en = englischer Name (Sprechanweisung der Server-Stimme), tag = BCP-47 (Browserstimme, Spracherkennung), formal = kennt du/Sie, az_f/az_m = native Azure-Stimmen.
 */
const ASSISTANT_LANG_TABLE = [
    'de'=>['de'=>'Deutsch','native'=>'Deutsch','en'=>'German','tag'=>'de-DE','formal'=>true,'az_f'=>'de-DE-SeraphinaMultilingualNeural','az_m'=>'de-DE-FlorianMultilingualNeural'],
    'en'=>['de'=>'Englisch','native'=>'English','en'=>'English','tag'=>'en-GB','formal'=>false,'az_f'=>'en-US-AvaMultilingualNeural','az_m'=>'en-US-AndrewMultilingualNeural'],
    'fr'=>['de'=>'Französisch','native'=>'Français','en'=>'French','tag'=>'fr-FR','formal'=>true,'az_f'=>'fr-FR-DeniseNeural','az_m'=>'fr-FR-HenriNeural'],
    'es'=>['de'=>'Spanisch','native'=>'Español','en'=>'Spanish','tag'=>'es-ES','formal'=>true,'az_f'=>'es-ES-ElviraNeural','az_m'=>'es-ES-AlvaroNeural'],
    'tr'=>['de'=>'Türkisch','native'=>'Türkçe','en'=>'Turkish','tag'=>'tr-TR','formal'=>true,'az_f'=>'tr-TR-EmelNeural','az_m'=>'tr-TR-AhmetNeural'],
    'ru'=>['de'=>'Russisch','native'=>'Русский','en'=>'Russian','tag'=>'ru-RU','formal'=>true,'az_f'=>'ru-RU-SvetlanaNeural','az_m'=>'ru-RU-DmitryNeural'],
    'it'=>['de'=>'Italienisch','native'=>'Italiano','en'=>'Italian','tag'=>'it-IT','formal'=>true,'az_f'=>'it-IT-ElsaNeural','az_m'=>'it-IT-DiegoNeural'],
    'nl'=>['de'=>'Niederländisch','native'=>'Nederlands','en'=>'Dutch','tag'=>'nl-NL','formal'=>true,'az_f'=>'nl-NL-FennaNeural','az_m'=>'nl-NL-MaartenNeural'],
    'sv'=>['de'=>'Schwedisch','native'=>'Svenska','en'=>'Swedish','tag'=>'sv-SE','formal'=>false,'az_f'=>'sv-SE-SofieNeural','az_m'=>'sv-SE-MattiasNeural'],
    'no'=>['de'=>'Norwegisch','native'=>'Norsk','en'=>'Norwegian (Bokmål)','tag'=>'nb-NO','formal'=>false,'az_f'=>'nb-NO-PernilleNeural','az_m'=>'nb-NO-FinnNeural'],
    'fi'=>['de'=>'Finnisch','native'=>'Suomi','en'=>'Finnish','tag'=>'fi-FI','formal'=>true,'az_f'=>'fi-FI-NooraNeural','az_m'=>'fi-FI-HarriNeural'],
    'ja'=>['de'=>'Japanisch','native'=>'日本語','en'=>'Japanese','tag'=>'ja-JP','formal'=>false,'az_f'=>'ja-JP-NanamiNeural','az_m'=>'ja-JP-KeitaNeural'],
    'az'=>['de'=>'Aserbaidschanisch','native'=>'Azərbaycanca','en'=>'Azerbaijani','tag'=>'az-AZ','formal'=>true,'az_f'=>'az-AZ-BanuNeural','az_m'=>'az-AZ-BabekNeural'],
];
const ASSISTANT_LANGS = ['de','en','fr','es','tr','ru','it','nl','sv','no','fi','ja','az'];
/**
 * Begrüßung und Hörprobe je Sprache (Deutsch hat eine eigene, nach Art abgestufte Begrüßung in assistant_greeting).
 * Wert = Text oder ['du'=>…,'sie'=>…] (vertraut/förmlich), darin ggf. ['f'=>…,'m'=>…] (Assistentin/Assistent). {name}, {company} werden ersetzt.
 */
const ASSISTANT_LANG_TEXT = [
    'en'=>['hi'=>'Hello and welcome!','intro'=>'I\'m {name}, your personal assistant here in ChatHelp Studio.',
        'mid'=>'Just tell me what needs doing – I\'ll ask whatever I still need to know and set up your agents with you, step by step.',
        'co'=>'I can see “{company}” is already set up. What shall I take care of first?','new'=>'To start: what kind of business do you run?',
        'preview'=>'Hello, I\'m {name}. This is how I sound – does that suit you?'],
    'fr'=>['hi'=>'Bonjour et bienvenue !',
        'intro'=>['du'=>['f'=>'Je suis {name}, ton assistante personnelle dans ChatHelp Studio.','m'=>'Je suis {name}, ton assistant personnel dans ChatHelp Studio.'],
                  'sie'=>['f'=>'Je suis {name}, votre assistante personnelle dans ChatHelp Studio.','m'=>'Je suis {name}, votre assistant personnel dans ChatHelp Studio.']],
        'mid'=>['du'=>'Dis-moi simplement ce qu\'il faut faire – je te pose les questions nécessaires et je configure tes agents avec toi, étape par étape.',
                'sie'=>'Dites-moi simplement ce qu\'il faut faire – je vous pose les questions nécessaires et je configure vos agents avec vous, étape par étape.'],
        'co'=>['du'=>'Je vois que « {company} » est déjà enregistré. Par quoi veux-tu que je commence ?','sie'=>'Je vois que « {company} » est déjà enregistré. Par quoi voulez-vous que je commence ?'],
        'new'=>['du'=>'Pour commencer : quel type d\'entreprise diriges-tu ?','sie'=>'Pour commencer : quel type d\'entreprise dirigez-vous ?'],
        'preview'=>['du'=>'Bonjour, je suis {name}. Voici ma voix – est-ce qu\'elle te convient ?','sie'=>'Bonjour, je suis {name}. Voici ma voix – est-ce qu\'elle vous convient ?']],
    'es'=>['hi'=>'¡Hola!',
        'intro'=>['du'=>'Soy {name}, tu asistente personal aquí en ChatHelp Studio.','sie'=>'Soy {name}, su asistente personal aquí en ChatHelp Studio.'],
        'mid'=>['du'=>'Cuéntame qué hay que hacer: te preguntaré lo que aún necesito saber y configuraré tus agentes contigo, paso a paso.',
                'sie'=>'Cuénteme qué hay que hacer: le preguntaré lo que aún necesito saber y configuraré sus agentes con usted, paso a paso.'],
        'co'=>['du'=>'Veo que «{company}» ya está creado. ¿Por dónde quieres que empiece?','sie'=>'Veo que «{company}» ya está creado. ¿Por dónde quiere que empiece?'],
        'new'=>['du'=>'Para empezar: ¿qué tipo de negocio tienes?','sie'=>'Para empezar: ¿qué tipo de negocio tiene?'],
        'preview'=>['du'=>'Hola, soy {name}. Así suena mi voz. ¿Te gusta?','sie'=>'Hola, soy {name}. Así suena mi voz. ¿Le gusta?']],
    'tr'=>['hi'=>['du'=>'Merhaba, hoş geldin!','sie'=>'Merhaba, hoş geldiniz!'],
        'intro'=>['du'=>'Ben {name}, ChatHelp Studio\'daki kişisel asistanın.','sie'=>'Ben {name}, ChatHelp Studio\'daki kişisel asistanınız.'],
        'mid'=>['du'=>'Bana yapılması gerekenleri anlatman yeterli – eksik bilgileri sana sorar ve ajanlarını adım adım seninle birlikte kurarım.',
                'sie'=>'Bana yapılması gerekenleri anlatmanız yeterli – eksik bilgileri size sorar ve ajanlarınızı adım adım sizinle birlikte kurarım.'],
        'co'=>['du'=>'“{company}” zaten kayıtlı görünüyor. İlk olarak ne yapmamı istersin?','sie'=>'“{company}” zaten kayıtlı görünüyor. İlk olarak ne yapmamı istersiniz?'],
        'new'=>['du'=>'Başlamak için: Nasıl bir işletmen var?','sie'=>'Başlamak için: Nasıl bir işletmeniz var?'],
        'preview'=>['du'=>'Merhaba, ben {name}. Sesim böyle – sana uygun mu?','sie'=>'Merhaba, ben {name}. Sesim böyle – size uygun mu?']],
    'ru'=>['hi'=>['du'=>'Привет и добро пожаловать!','sie'=>'Здравствуйте и добро пожаловать!'],
        'intro'=>['du'=>'Я {name}, твой личный ассистент в ChatHelp Studio.','sie'=>'Я {name}, ваш личный ассистент в ChatHelp Studio.'],
        'mid'=>['du'=>'Просто расскажи, что нужно сделать, – я уточню всё, что мне ещё нужно знать, и шаг за шагом настрою вместе с тобой твоих агентов.',
                'sie'=>'Просто расскажите, что нужно сделать, – я уточню всё, что мне ещё нужно знать, и шаг за шагом настрою вместе с вами ваших агентов.'],
        'co'=>'Вижу, что «{company}» уже создан. С чего мне начать?',
        'new'=>['du'=>'Для начала: какой у тебя бизнес?','sie'=>'Для начала: какой у вас бизнес?'],
        'preview'=>['du'=>'Привет, я {name}. Вот так звучит мой голос – тебе подходит?','sie'=>'Здравствуйте, я {name}. Вот так звучит мой голос – вам подходит?']],
    'it'=>['hi'=>['du'=>'Ciao e benvenuto!','sie'=>'Buongiorno e benvenuto!'],
        'intro'=>['du'=>['f'=>'Sono {name}, la tua assistente personale qui in ChatHelp Studio.','m'=>'Sono {name}, il tuo assistente personale qui in ChatHelp Studio.'],
                  'sie'=>['f'=>'Sono {name}, la Sua assistente personale qui in ChatHelp Studio.','m'=>'Sono {name}, il Suo assistente personale qui in ChatHelp Studio.']],
        'mid'=>['du'=>'Dimmi semplicemente cosa c\'è da fare: ti chiederò ciò che mi serve ancora sapere e configurerò i tuoi agenti insieme a te, passo dopo passo.',
                'sie'=>'Mi dica semplicemente cosa c\'è da fare: Le chiederò ciò che mi serve ancora sapere e configurerò i Suoi agenti insieme a Lei, passo dopo passo.'],
        'co'=>['du'=>'Vedo che «{company}» è già configurato. Da cosa vuoi che inizi?','sie'=>'Vedo che «{company}» è già configurato. Da cosa vuole che inizi?'],
        'new'=>['du'=>'Per cominciare: che tipo di attività hai?','sie'=>'Per cominciare: che tipo di attività ha?'],
        'preview'=>['du'=>'Ciao, sono {name}. Ecco come suona la mia voce: ti piace?','sie'=>'Buongiorno, sono {name}. Ecco come suona la mia voce: Le piace?']],
    'nl'=>['hi'=>'Hallo en welkom!',
        'intro'=>['du'=>'Ik ben {name}, je persoonlijke assistent hier in ChatHelp Studio.','sie'=>'Ik ben {name}, uw persoonlijke assistent hier in ChatHelp Studio.'],
        'mid'=>['du'=>'Vertel me gewoon wat er moet gebeuren – ik vraag wat ik nog moet weten en richt je agents stap voor stap samen met je in.',
                'sie'=>'Vertelt u mij gewoon wat er moet gebeuren – ik vraag wat ik nog moet weten en richt uw agents stap voor stap samen met u in.'],
        'co'=>'Ik zie dat ‘{company}’ al is aangemaakt. Waarmee zal ik beginnen?',
        'new'=>['du'=>'Om te beginnen: wat voor bedrijf heb je?','sie'=>'Om te beginnen: wat voor bedrijf heeft u?'],
        'preview'=>['du'=>'Hallo, ik ben {name}. Zo klinkt mijn stem – vind je dat prettig?','sie'=>'Hallo, ik ben {name}. Zo klinkt mijn stem – vindt u dat prettig?']],
    'sv'=>['hi'=>'Hej och välkommen!','intro'=>'Jag heter {name} och är din personliga assistent här i ChatHelp Studio.',
        'mid'=>'Berätta bara vad som behöver göras – jag frågar efter det jag fortfarande behöver veta och sätter upp dina agenter tillsammans med dig, steg för steg.',
        'co'=>'Jag ser att ”{company}” redan finns. Vad ska jag ta hand om först?','new'=>'Till att börja med: vilken typ av verksamhet driver du?',
        'preview'=>'Hej, jag heter {name}. Så här låter jag – passar det dig?'],
    'no'=>['hi'=>'Hei og velkommen!','intro'=>'Jeg heter {name} og er din personlige assistent her i ChatHelp Studio.',
        'mid'=>'Bare fortell meg hva som skal gjøres – jeg spør om det jeg fortsatt trenger å vite, og setter opp agentene dine sammen med deg, steg for steg.',
        'co'=>'Jeg ser at «{company}» allerede er opprettet. Hva skal jeg ta meg av først?','new'=>'For å begynne: hva slags bedrift driver du?',
        'preview'=>'Hei, jeg heter {name}. Slik høres jeg ut – passer det for deg?'],
    'fi'=>['hi'=>['du'=>'Hei ja tervetuloa!','sie'=>'Hyvää päivää ja tervetuloa!'],
        'intro'=>['du'=>'Olen {name}, henkilökohtainen assistenttisi täällä ChatHelp Studiossa.','sie'=>'Olen {name}, henkilökohtainen assistenttinne täällä ChatHelp Studiossa.'],
        'mid'=>['du'=>'Kerro vain, mitä pitää tehdä – kysyn, mitä minun vielä tarvitsee tietää, ja otan agenttisi käyttöön yhdessä kanssasi vaihe vaiheelta.',
                'sie'=>'Kertokaa vain, mitä pitää tehdä – kysyn, mitä minun vielä tarvitsee tietää, ja otan agenttinne käyttöön yhdessä kanssanne vaihe vaiheelta.'],
        'co'=>'Näen, että ”{company}” on jo luotu. Mistä aloitan?',
        'new'=>['du'=>'Aluksi: millainen yritys sinulla on?','sie'=>'Aluksi: millainen yritys Teillä on?'],
        'preview'=>['du'=>'Hei, olen {name}. Tältä kuulostan – sopiiko se sinulle?','sie'=>'Hyvää päivää, olen {name}. Tältä kuulostan – sopiiko se Teille?']],
    'ja'=>['hi'=>'こんにちは、ようこそ！','intro'=>'ChatHelp Studio のパーソナルアシスタント、{name}です。',
        'mid'=>'やりたいことをお気軽にお話しください。必要なことをお尋ねしながら、エージェントの設定を一緒に一歩ずつ進めます。',
        'co'=>'「{company}」はすでに登録されていますね。まず何から始めましょうか？','new'=>'まず、どのような事業をされていますか？',
        'preview'=>'こんにちは、{name}です。私の声はこんな感じです。いかがでしょうか？'],
    'az'=>['hi'=>['du'=>'Salam, xoş gəldin!','sie'=>'Salam, xoş gəlmisiniz!'],
        'intro'=>['du'=>'Mən {name}, ChatHelp Studio-da sənin şəxsi assistentinəm.','sie'=>'Mən {name}, ChatHelp Studio-da sizin şəxsi assistentinizəm.'],
        'mid'=>['du'=>'Sadəcə nə edilməli olduğunu de – hələ bilməli olduğum şeyləri soruşacağam və agentlərini səninlə birlikdə addım-addım quracağam.',
                'sie'=>'Sadəcə nə edilməli olduğunu deyin – hələ bilməli olduğum şeyləri soruşacağam və agentlərinizi sizinlə birlikdə addım-addım quracağam.'],
        'co'=>'Görürəm ki, “{company}” artıq yaradılıb. İlk olaraq nə ilə başlayım?',
        'new'=>['du'=>'Başlamaq üçün: necə bir biznesin var?','sie'=>'Başlamaq üçün: necə bir biznesiniz var?'],
        'preview'=>['du'=>'Salam, mən {name}. Səsim belədir – sənə uyğundurmu?','sie'=>'Salam, mən {name}. Səsim belədir – sizə uyğundurmu?']],
];
/**
 * Bestätigungen „ja / passt / mach das“ in allen 13 Sprachen (Anfang der Nachricht, kleingeschrieben). Werkzeuge, die etwas ändern,
 * laufen nur, wenn die letzte Assistenten-Nachricht eine Frage war UND der Inhaber kurz und klar zustimmt (assistant_confirmed).
 */
const ASSISTANT_YES = [
    'ja','jawohl','jap','jep','jo','joa','jupp','passt','genau','gerne','gern','ok','okay','oki','okey','einverstanden','richtig','stimmt','klar','sicher','perfekt','super','top','prima','alles klar','in ordnung','mach das','mach es','mach ruhig','mach so','übernimm das','übernimm es','übernehmen','bitte übernehmen','trag es ein','speichern','leg los',
    'yes','yeah','yep','yup','sure','go ahead','do it','please do','correct','right','fine','sounds good','perfect','absolutely','of course',
    'oui','ouais','d\'accord','vas-y','allez-y','parfait','exactement','c\'est bon','bien sûr','volontiers',
    'sí','si','vale','claro','perfecto','de acuerdo','hazlo','adelante','correcto','por supuesto',
    'certo','va bene','perfetto','esatto','procedi','fallo','d\'accordo','certamente',
    'evet','tamam','olur','tabii','tabi','aynen','doğru','kaydet','peki','tamamdır',
    'да','конечно','хорошо','давай','давайте','верно','согласен','согласна','ок','отлично',
    'graag','goed','oké','akkoord','doe maar','klopt','zeker',
    'japp','okej','kör','gör det','absolut','stämmer','visst','gärna','jepp','greit','kjør','gjør det','stemmer','gjerne','absolutt',
    'kyllä','joo','juu','selvä','sopii','tee se','käy','totta kai','hyvä',
    'bəli','hə','oldu','yaxşı','razıyam','düzdür','əlbəttə','olar',
];
const ASSISTANT_YES_JA = ['はい','ええ','うん','お願いします','お願い','大丈夫','いいです','いいよ','それで','オッケー','了解','承知'];
/** Werkzeuge, die Einstellungen ändern – nur nach Bestätigung (Rest: remember, get_overview, recommend_agent, open_studio_tab). */
const ASSISTANT_CHANGING_TOOLS = ['save_business_profile','update_agent_knowledge','add_faq','set_agent_tone','configure_appointments','configure_menu','configure_taxi','configure_hotel','configure_callbacks'];
const ASSISTANT_AGENT_TONES = ['freundlich-professionell','seriös-zugewandt','sachlich-respektvoll','locker-modern','einfühlsam-präzise','kompetent-ehrlich'];
const ASSISTANT_TABS = ['home'=>'Übersicht','agents'=>'Agenten','training'=>'Training','phone'=>'Telefon','rueckruf'=>'Rückrufe','termine'=>'Termine','bestellungen'=>'Bestellungen','taxi'=>'Taxi','hotel'=>'Hotel','benachrichtigungen'=>'Benachrichtigungen','tokens'=>'Tokens & API','assistant'=>'Assistent'];
/** Eingebaute Porträts, solange site/media/avatars/avatars.json fehlt: dezente SVG-Platzhalter (Initiale + weicher Verlauf). */
const ASSISTANT_FALLBACK_PORTRAITS = [
    ['id'=>'f1','gender'=>'f','label'=>'Lea','age'=>32,'c'=>['#f6e3c4','#d9a95b','#8a5a1c']],
    ['id'=>'f2','gender'=>'f','label'=>'Sophie','age'=>41,'c'=>['#f3dfe6','#c98aa3','#7c3b57']],
    ['id'=>'f3','gender'=>'f','label'=>'Amira','age'=>28,'c'=>['#e2ecf6','#8fb0d4','#34557d']],
    ['id'=>'m1','gender'=>'m','label'=>'Jonas','age'=>34,'c'=>['#e4ecef','#88a6b3','#2f4f5c']],
    ['id'=>'m2','gender'=>'m','label'=>'David','age'=>45,'c'=>['#ece6dc','#b39a78','#5b4428']],
    ['id'=>'m3','gender'=>'m','label'=>'Emre','age'=>29,'c'=>['#e6e7f4','#9a9fd1','#3c3f7a']],
];
/**
 * Stimm-Voreinstellungen (gleiche IDs wie ch_tts_voices() in speech-lib). Die speech-lib-Liste hat Vorrang, diese Tabelle ist der Ersatz,
 * solange speech-lib (noch) keine Voreinstellungen liefert. browser = Namensbestandteile guter Browserstimmen (Edge/Chrome/Safari).
 */
const ASSISTANT_VOICE_PRESETS = [
    'f_warm'=>['gender'=>'f','label'=>'Warm','desc'=>'Herzlich und ruhig – wie eine vertraute Kollegin','rate'=>1.0,'pitch'=>1.0,'openai'=>'coral','google'=>'de-DE-Chirp3-HD-Aoede','azure'=>'de-DE-SeraphinaMultilingualNeural','browser'=>['Seraphina','Katja','Amala','Anna','Petra','Helena']],
    'f_klar'=>['gender'=>'f','label'=>'Klar','desc'=>'Deutlich, sachlich und souverän','rate'=>1.0,'pitch'=>1.0,'openai'=>'sage','google'=>'de-DE-Chirp3-HD-Kore','azure'=>'de-DE-SeraphinaMultilingualNeural','browser'=>['Katja','Seraphina','Petra','Anna','Helena']],
    'f_frisch'=>['gender'=>'f','label'=>'Frisch','desc'=>'Lebendig, positiv und schwungvoll','rate'=>1.05,'pitch'=>1.05,'openai'=>'shimmer','google'=>'de-DE-Chirp3-HD-Leda','azure'=>'de-DE-SeraphinaMultilingualNeural','browser'=>['Amala','Seraphina','Katja','Anna','Petra']],
    'm_ruhig'=>['gender'=>'m','label'=>'Ruhig','desc'=>'Tief, gelassen und vertrauenswürdig','rate'=>0.97,'pitch'=>0.95,'openai'=>'onyx','google'=>'de-DE-Chirp3-HD-Orus','azure'=>'de-DE-FlorianMultilingualNeural','browser'=>['Florian','Conrad','Killian','Markus','Yannick']],
    'm_freundlich'=>['gender'=>'m','label'=>'Freundlich','desc'=>'Warm, nahbar und gut gelaunt','rate'=>1.0,'pitch'=>1.0,'openai'=>'ash','google'=>'de-DE-Chirp3-HD-Charon','azure'=>'de-DE-FlorianMultilingualNeural','browser'=>['Conrad','Florian','Killian','Markus','Yannick']],
    'm_souveraen'=>['gender'=>'m','label'=>'Souverän','desc'=>'Klar, professionell und bestimmt','rate'=>1.0,'pitch'=>0.97,'openai'=>'verse','google'=>'de-DE-Chirp3-HD-Puck','azure'=>'de-DE-FlorianMultilingualNeural','browser'=>['Killian','Conrad','Florian','Markus','Yannick']],
];
/** Ton der Sprechweise → passende Voreinstellung je Geschlecht. */
const ASSISTANT_TONE_PRESET = ['f'=>['warm'=>['f_warm'],'ruhig'=>['f_sanft','f_warm'],'energisch'=>['f_frisch'],'sachlich'=>['f_klar']],
                                'm'=>['warm'=>['m_freundlich'],'ruhig'=>['m_ruhig','m_tief'],'energisch'=>['m_freundlich'],'sachlich'=>['m_souveraen']]];
/** Branche (Stichwort) → passende Agenten, erste Wahl zuerst. */
const ASSISTANT_SECTOR_MAP = [
    ['/pizz|restaurant|imbiss|liefer|döner|doener|burger|sushi|gastro|trattoria|grill|kebap|kebab|asia/iu',['agent-bestellung','gastro-agent']],
    ['/café|cafe|bistro|bar\b|kneipe|brunch/iu',['gastro-agent','agent-bestellung']],
    ['/friseur|frisör|salon|kosmetik|nagel|nail|massage|physio|tattoo|fitness|yoga|barber|beauty|wellness|spa\b|fußpflege|podolog/iu',['agent-termine','agent-phone']],
    ['/zahnarzt|arztpraxis|praxis|ärzt|arzt|therapeut|heilpraktik|tierarzt/iu',['agent-termine','praxis-agent']],
    ['/taxi|mietwagen|shuttle|chauffeur|fahrdienst|krankenfahrt/iu',['agent-taxi']],
    ['/hotel|pension|apartment|ferienwohnung|hostel|gasthof|gästehaus|b&b/iu',['agent-hotel']],
    ['/kanzlei|anwalt|anwält|rechtsanw|notar/iu',['agent-kanzlei']],
    ['/steuer|buchhaltung|lohnbüro/iu',['agent-steuer']],
    ['/inkasso|forderung/iu',['agent-inkasso']],
    ['/hausverwalt|weg-verwalt|mietverwalt/iu',['agent-hausverwaltung']],
    ['/immobil|makler|wohnung|vermiet/iu',['immo-agent']],
    ['/versicher/iu',['agent-versicherung']],
    ['/autohaus|werkstatt|kfz|reifen|\bauto(s|händler|service|vermietung)?\b/iu',['agent-autohaus']],
    ['/shop|onlineshop|e-commerce|ecommerce|handel|versand|boutique/iu',['agent-support']],
    ['/website|homepage|webseite/iu',['agent-website']],
    ['/handwerk|elektr|sanitär|maler|dachdeck|tischler|schreiner|installat/iu',['agent-phone','agent-backoffice']],
];

/* ======================================================================= Tabellen */
function assistant_bootstrap(): void {
    static $done=false; if($done) return; $done=true; $p=th_db();
    $p->exec("CREATE TABLE IF NOT EXISTS assistant_profiles (
        account_id INT NOT NULL PRIMARY KEY, name VARCHAR(30) NOT NULL DEFAULT 'Lea', gender CHAR(1) NOT NULL DEFAULT 'f',
        portrait VARCHAR(40) NOT NULL DEFAULT '', portrait_data MEDIUMTEXT NULL, voice TEXT NULL, personality TEXT NULL,
        lang VARCHAR(8) NOT NULL DEFAULT 'de', updated_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $p->exec("CREATE TABLE IF NOT EXISTS assistant_messages (
        id INT AUTO_INCREMENT PRIMARY KEY, account_id INT NOT NULL, role VARCHAR(12) NOT NULL, content MEDIUMTEXT NOT NULL, meta TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP, INDEX(account_id, id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $p->exec("CREATE TABLE IF NOT EXISTS assistant_memory (
        id INT AUTO_INCREMENT PRIMARY KEY, account_id INT NOT NULL, topic VARCHAR(60) NOT NULL, fact VARCHAR(500) NOT NULL,
        source VARCHAR(20) NOT NULL DEFAULT 'chat', created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_fact (account_id, topic, fact(190)), INDEX(account_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/* ======================================================================= Kleine Helfer */
function assistant_str($v, int $max=0): string { $s=is_scalar($v) ? trim(str_replace("\r",'',(string)$v)) : ''; $s=preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u','',$s) ?? ''; return $max>0 ? mb_substr($s,0,$max) : $s; }
function assistant_line($v, int $max): string { return mb_substr(trim(preg_replace('/\s+/u',' ',assistant_str($v)) ?? ''),0,$max); }
function assistant_eur(int $c): string { return number_format($c/100,2,',','.').' €'; }
function assistant_cents($v): ?int {
    if(is_int($v) || is_float($v)) return $v<0 ? null : (int)round($v*100);
    $s=str_replace(['€','EUR','eur',' '],'',assistant_str($v)); if($s==='') return null;
    if(preg_match('/^\d{1,3}(\.\d{3})+(,\d{1,2})?$/',$s)) $s=str_replace(['.',','],['','.'],$s); else $s=str_replace(',','.',$s);
    return is_numeric($s) && (float)$s>=0 ? (int)round((float)$s*100) : null;
}
function assistant_truthy($v): ?bool { if($v===null || $v==='') return null; if(is_bool($v)) return $v; if(is_numeric($v)) return (float)$v>0; return in_array(mb_strtolower(trim((string)$v)),['1','true','ja','yes','on','an','ein'],true); }
function assistant_tz(): DateTimeZone { return new DateTimeZone('Europe/Berlin'); }
function assistant_now_text(): string {
    $d=new DateTimeImmutable('now',assistant_tz()); $w=['Sonntag','Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag'][(int)$d->format('w')];
    return $w.', '.$d->format('d.m.Y, H:i').' Uhr';
}
function assistant_plan_name(string $id): string { $p=ch_plan($id); return $p ? $p['name'] : $id; }
/* ---- Sprachen */
function assistant_lang(string $l): string { $l=strtolower(trim($l)); if(in_array($l,['nb','nn'],true)) $l='no'; return isset(ASSISTANT_LANG_TABLE[$l]) ? $l : 'de'; }
function assistant_lang_name(string $l): string { return ASSISTANT_LANG_TABLE[assistant_lang($l)]['de']; }
function assistant_lang_tag(string $l): string { return ASSISTANT_LANG_TABLE[assistant_lang($l)]['tag']; }
/** Text aus ASSISTANT_LANG_TEXT (fehlt die Sprache: Englisch). */
function assistant_lt(string $lang, string $key, bool $sie, bool $fem, array $vars=[]): string {
    $v=ASSISTANT_LANG_TEXT[$lang][$key] ?? ASSISTANT_LANG_TEXT['en'][$key] ?? '';
    if(is_array($v) && isset($v['du'])) $v=$sie ? $v['sie'] : $v['du'];
    if(is_array($v)) $v=$fem ? ($v['f'] ?? reset($v)) : ($v['m'] ?? reset($v));
    return strtr((string)$v,array_combine(array_map(fn($k)=>'{'.$k.'}',array_keys($vars)),array_values($vars)) ?: []);
}
/** Hörprobe (immer vom Server gebaut – /speak liest keine beliebigen Texte vor). */
function assistant_preview_text(string $lang, string $name, bool $sie, bool $fem): string {
    $lang=assistant_lang($lang);
    if($lang==='de') return 'Hallo, ich bin '.$name.'. So klinge ich – passt das für '.($sie ? 'Sie' : 'dich').'?';
    return assistant_lt($lang,'preview',$sie,$fem,['name'=>$name]);
}
/** Sprachliste fürs Panel. */
function assistant_langs_public(): array {
    $o=[]; foreach(ASSISTANT_LANGS as $c){ $t=ASSISTANT_LANG_TABLE[$c]; $pv=[];
        foreach(['du'=>false,'sie'=>true] as $a=>$sie) foreach(['f'=>true,'m'=>false] as $gk=>$fem) $pv[$a][$gk]=assistant_preview_text($c,'{name}',$sie,$fem);   // für die Browserstimme
        $o[]=['code'=>$c,'name'=>$t['de'],'native'=>$t['native'],'tag'=>$t['tag'],'formal'=>$t['formal'],'preview'=>$pv]; }
    return $o;
}
/**
 * Sprache eines Textes grob erkennen (für Stimme und Spracheingabe; die KI antwortet in der Sprache des Inhabers). Schrift zuerst (ja, ru),
 * dann typische Wörter und Sonderzeichen. Unsicher (kurzer Text, knappes Ergebnis) → $fallback.
 */
function assistant_detect_lang(string $t, string $fallback='de'): string {
    $fallback=assistant_lang($fallback);
    if(preg_match_all('/[\p{Hiragana}\p{Katakana}]/u',$t)>=2 || (preg_match_all('/\p{Han}/u',$t)>=4 && !preg_match('/\p{Cyrillic}/u',$t))) return 'ja';
    $cyr=preg_match_all('/\p{Cyrillic}/u',$t); $lat=preg_match_all('/[a-zA-Z\x{00C0}-\x{024F}\x{0259}]/u',$t);
    if($cyr>=3 && $cyr>=$lat) return 'ru';
    $l=' '.mb_strtolower((string)preg_replace('/[^\p{L}\s\']+/u',' ',$t)).' '; $l=(string)preg_replace('/\s+/u',' ',$l);
    $W=['de'=>['der','die','das','und','ist','ich','nicht','mit','eine','für','wir','auf','zu','den','dem','haben','kann','noch','oder','uhr','bitte','gerne','soll','dein','deine','ihre','sind','wie','was','schon','auch'],
        'en'=>['the','and','is','you','your','we','are','with','to','of','can','please','will','this','that','have','our','it','what','how','should','shall','first','business'],
        'fr'=>['le','la','les','et','est','vous','nous','pour','avec','une','des','pas','merci','votre','je','que','qui','dans','sur','ce','tu','ton','quel','quelle'],
        'es'=>['el','los','las','y','usted','para','con','una','por','gracias','su','que','qué','del','tu','tus','muy','está','cómo','tienes','tiene','hay'],
        'it'=>['il','gli','e','è','per','con','una','che','di','non','sono','ti','tuo','tua','grazie','come','della','vuoi','hai','cosa'],
        'nl'=>['de','het','een','en','niet','ik','je','wat','voor','met','van','op','zijn','we','dat','er','uw','heb','bent','graag'],
        'sv'=>['och','att','är','jag','inte','vad','till','ett','för','dig','mig','hur','vill','din','dina','vi','har','det','som'],
        'no'=>['og','er','jeg','ikke','hva','til','et','for','deg','meg','hvordan','vil','din','dine','vi','har','det','som'],
        'fi'=>['ja','on','ei','se','että','mitä','minä','sinä','olen','kun','tai','mutta','voit','sinun','haluat','kanssa','myös','mikä','millainen'],
        'tr'=>['ve','bir','bu','için','ile','mı','mi','değil','evet','lütfen','size','sizin','çok','var','yok','ne','nasıl','ben','sen','misiniz','musun'],
        'az'=>['və','bir','bu','üçün','ilə','deyil','bəli','zəhmət','sizə','sizin','çox','var','yox','nə','necə','mən','sən','olaraq']];
    $sc=[]; foreach($W as $k=>$ws){ $n=0; foreach($ws as $w) $n+=substr_count($l,' '.$w.' '); $sc[$k]=$n; }
    $sc['az']+=3*preg_match_all('/ə/u',$l); $sc['tr']+=preg_match_all('/[ğış]/u',$l)-2*preg_match_all('/ə/u',$l);
    $sc['de']+=preg_match_all('/ß/u',$l)+preg_match_all('/ü/u',$l); $sc['sv']+=preg_match_all('/å/u',$l); $sc['no']+=2*preg_match_all('/[æø]/u',$l);
    $sc['fi']+=preg_match_all('/(aa|ee|ii|uu|yy|ää|öö)/u',$l)>2 ? 2 : 0;
    $sc['es']+=2*preg_match_all('/[¿¡ñ]/u',$t); $sc['fr']+=preg_match_all('/[çœ]|\b(c|d|j|l|qu)\'/u',$l);
    arsort($sc); $keys=array_keys($sc); $best=$keys[0]; $top=$sc[$best]; $second=$sc[$keys[1]] ?? 0;
    if($top<2 || $top-$second<1) return $fallback;
    if(($sc[$fallback] ?? 0)>=$top) return $fallback;
    return $best;
}
/** Studio-Bereiche, die es in dieser Installation gibt (Modul-Panels nur, wenn ihre Dateien vorhanden sind). */
function assistant_tabs_available(): array { return array_values(array_filter(array_keys(ASSISTANT_TABS),fn($k)=>$k!=='assistant' && assistant_has_tab($k))); }
function assistant_has_tab(string $tab): bool {
    if(in_array($tab,['home','agents','phone','termine','bestellungen','tokens'],true)) return true;
    return isset(ASSISTANT_TABS[$tab]) && is_file(__DIR__.'/studio-panels/'.$tab.'.html') && is_file(__DIR__.'/studio-panels/'.$tab.'.js');
}

/* ======================================================================= Porträts */
/** Ordner mit avatars.json: live /html/media/avatars (neben /html/chat), im Repository site/media/avatars. ASSISTANT_AVATAR_DIR nur für Tests. */
function assistant_avatar_dir(): ?string {
    $dirs=defined('ASSISTANT_AVATAR_DIR') ? [(string)ASSISTANT_AVATAR_DIR] : [__DIR__.'/../media/avatars',__DIR__.'/../site/media/avatars'];
    foreach($dirs as $d) if(is_file($d.'/avatars.json')) return $d;
    return null;
}
/** SVG-Platzhalter als data-URL (keine fremden Bilder). */
function assistant_svg_portrait(array $c, string $initial): string {
    $i=htmlspecialchars(mb_strtoupper(mb_substr($initial!=='' ? $initial : 'A',0,1)),ENT_QUOTES|ENT_XML1);
    $svg='<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 500"><defs>'
        .'<radialGradient id="g" cx="32%" cy="22%" r="95%"><stop offset="0" stop-color="#ffffff"/><stop offset=".38" stop-color="'.$c[0].'"/><stop offset="1" stop-color="'.$c[1].'"/></radialGradient>'
        .'<linearGradient id="b" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="'.$c[2].'" stop-opacity=".30"/><stop offset="1" stop-color="'.$c[2].'" stop-opacity=".62"/></linearGradient></defs>'
        .'<rect width="400" height="500" fill="url(#g)"/>'
        .'<circle cx="200" cy="205" r="74" fill="url(#b)"/>'
        .'<path d="M58 500c6-92 66-150 142-150s136 58 142 150z" fill="url(#b)"/>'
        .'<text x="200" y="232" text-anchor="middle" font-family="Fraunces,Georgia,serif" font-size="82" font-weight="600" fill="#ffffff" fill-opacity=".92">'.$i.'</text></svg>';
    return 'data:image/svg+xml;base64,'.base64_encode($svg);
}
/** Liste [{id,url,gender,label,age}] – aus avatars.json (nur vorhandene Dateien) oder die eingebauten Platzhalter. */
function assistant_portraits(): array {
    static $list=null; if($list!==null) return $list; $list=[];
    $dir=assistant_avatar_dir();
    if($dir){ $j=json_decode((string)@file_get_contents($dir.'/avatars.json'),true);
        foreach(is_array($j) ? $j : [] as $a){
            if(!is_array($a)) continue; $id=(string)($a['id'] ?? ''); $file=(string)($a['file'] ?? ''); $g=(string)($a['gender'] ?? '');
            if(!preg_match('/^[A-Za-z0-9_-]{1,40}$/',$id) || !preg_match('/^[A-Za-z0-9_.-]{1,80}\.(jpe?g|png|webp)$/i',$file) || !in_array($g,['f','m'],true) || !is_file($dir.'/'.$file)) continue;
            $list[]=['id'=>$id,'url'=>'/media/avatars/'.rawurlencode($file),'gender'=>$g,'label'=>assistant_line($a['label'] ?? '',40),'age'=>(int)($a['age'] ?? 0),'stock'=>true];
            if(count($list)>=60) break; } }
    if(!$list) foreach(ASSISTANT_FALLBACK_PORTRAITS as $f) $list[]=['id'=>$f['id'],'url'=>assistant_svg_portrait($f['c'],$f['label']),'gender'=>$f['gender'],'label'=>$f['label'],'age'=>$f['age'],'stock'=>false];
    return $list;
}
function assistant_portrait(string $id): ?array { foreach(assistant_portraits() as $p) if($p['id']===$id) return $p; return null; }
function assistant_default_portrait(string $gender): string { foreach(assistant_portraits() as $p) if($p['gender']===$gender) return $p['id']; return assistant_portraits()[0]['id'] ?? ''; }
/** Bild-URL fürs Profil: eigenes Foto, echtes Porträt oder Platzhalter mit der Initiale des Assistenten-Namens. */
function assistant_portrait_url(array $pr): string {
    if($pr['portrait']==='custom' && !empty($pr['portrait_data'])) return (string)$pr['portrait_data'];
    $p=assistant_portrait($pr['portrait']) ?? assistant_portrait(assistant_default_portrait($pr['gender']));
    if(!$p) return '';
    if(!$p['stock']){ foreach(ASSISTANT_FALLBACK_PORTRAITS as $f) if($f['id']===$p['id']) return assistant_svg_portrait($f['c'],$pr['name']); }
    return $p['url'];
}
/**
 * Eigenes Foto prüfen: data-URL (jpeg/png/webp), ≤ 350 KB, echtes Rasterbild (getimagesizefromstring), 64–2048 px.
 * Mit GD: auf höchstens 512 px verkleinert und als JPEG neu gespeichert (entfernt Metadaten). Rückgabe [dataUrl|null, Fehlertext|null].
 */
function assistant_photo_check($raw): array {
    $s=is_string($raw) ? trim($raw) : '';
    if(!preg_match('#^data:image/(jpeg|jpg|png|webp);base64,([A-Za-z0-9+/=\s]+)$#',$s,$m)) return [null,'Bitte ein Foto als JPEG, PNG oder WebP hochladen.'];
    if(strlen($m[2])>(int)ceil(ASSISTANT_PHOTO_MAX*4/3)+64) return [null,'Das Foto ist zu groß (höchstens 350 KB).'];
    $bin=base64_decode(preg_replace('/\s+/','',$m[2]),true);
    if($bin===false || $bin==='') return [null,'Das Foto ist beschädigt – bitte erneut auswählen.'];
    if(strlen($bin)>ASSISTANT_PHOTO_MAX) return [null,'Das Foto ist zu groß (höchstens 350 KB).'];
    $info=@getimagesizefromstring($bin);
    if(!$info || !in_array($info['mime'] ?? '',['image/jpeg','image/png','image/webp'],true)) return [null,'Das ist kein gültiges JPEG-, PNG- oder WebP-Bild.'];
    [$w,$h]=[(int)$info[0],(int)$info[1]];
    if($w<64 || $h<64) return [null,'Das Foto ist zu klein (mindestens 64 × 64 Pixel).'];
    if($w>2048 || $h>2048) return [null,'Das Foto ist zu groß (höchstens 2048 Pixel Kantenlänge).'];
    $mime=$info['mime'];
    if(function_exists('imagecreatefromstring') && function_exists('imagejpeg')){
        $im=@imagecreatefromstring($bin);
        if($im!==false){
            $sc=min(1,512/max($w,$h)); $nw=max(1,(int)round($w*$sc)); $nh=max(1,(int)round($h*$sc));
            $out=imagecreatetruecolor($nw,$nh); imagefill($out,0,0,imagecolorallocate($out,255,255,255));
            imagecopyresampled($out,$im,0,0,0,0,$nw,$nh,$w,$h); ob_start(); imagejpeg($out,null,86); $jpg=(string)ob_get_clean();
            imagedestroy($im); imagedestroy($out);
            if($jpg!=='' && strlen($jpg)<=ASSISTANT_PHOTO_MAX){ $bin=$jpg; $mime='image/jpeg'; }
        }
    }
    return ['data:'.$mime.';base64,'.base64_encode($bin),null];
}

/* ======================================================================= Profil (Persona) */
function assistant_profile_defaults(int $acc): array {
    return ['account_id'=>$acc,'name'=>'Lea','gender'=>'f','portrait'=>assistant_default_portrait('f'),'portrait_data'=>null,
        'voice'=>['tone'=>'warm','rate'=>1.0,'pitch'=>1.0,'preset'=>assistant_preset_for('f','warm')],'personality'=>['address'=>'du','mood'=>'herzlich','humor'=>1,'detail'=>'kurz'],'lang'=>'de','saved'=>false];
}
/**
 * Stimm-Voreinstellungen: id → {gender,label,title,desc,rate,pitch,brate,bpitch,openai,google,azure,instructions,elevenlabs,eleven,browser[]}.
 * speech-lib (ch_tts_voices) hat Vorrang – deren browser kann {names,rate,pitch} oder eine Namensliste sein; browser bleibt hier die Namensliste,
 * brate/bpitch = Tempo/Tonhöhe nur für die Browserstimme (sonst rate/pitch).
 */
function assistant_voice_presets(): array {
    static $out=null; if($out!==null) return $out; $out=ASSISTANT_VOICE_PRESETS;
    if(function_exists('ch_tts_voices')){
        try{ $list=ch_tts_voices(); }catch(Throwable $e){ $list=[]; }
        foreach(is_array($list) ? $list : [] as $k=>$v){
            if(!is_array($v)) continue; $id=(string)($v['id'] ?? (is_string($k) ? $k : ''));
            if(!preg_match('/^[a-z]_[a-z0-9_]{2,30}$/',$id) || !in_array($v['gender'] ?? '',['f','m'],true)) continue;
            $base=$out[$id] ?? ASSISTANT_VOICE_PRESETS[$v['gender']==='m' ? 'm_freundlich' : 'f_warm'];
            $br=$v['browser'] ?? null; $bRate=null; $bPitch=null;
            if(is_array($br) && isset($br['names'])){ $bRate=is_numeric($br['rate'] ?? null) ? (float)$br['rate'] : null; $bPitch=is_numeric($br['pitch'] ?? null) ? (float)$br['pitch'] : null; $br=$br['names']; }
            if(is_string($br) && $br!=='') $br=preg_split('/\s*[,|]\s*/',$br);
            if(!is_array($br) || !$br || !array_filter($br,fn($x)=>is_string($x) && !is_numeric($x))) $br=$base['browser'];
            $rate=is_numeric($v['rate'] ?? null) ? (float)$v['rate'] : $base['rate']; $pitch=is_numeric($v['pitch'] ?? null) ? (float)$v['pitch'] : $base['pitch'];
            $lab=assistant_line($v['label'] ?? $base['label'],40);
            $out[$id]=['gender'=>$v['gender'],'label'=>$lab,'desc'=>assistant_line($v['desc'] ?? $base['desc'],140),
                /* persona = Vorname der Stimme („Lea · warm & herzlich“ → Lea) – fürs Panel: „Lea, Sophie, Mia und Hanna haben eigene Stimmen“ */
                'persona'=>str_contains($lab,' · ') ? trim(explode(' · ',$lab,2)[0]) : '',
                'rate'=>$rate,'pitch'=>$pitch,
                /* Browserstimme: eigenes Tempo/Tonhöhe (weiter gespreizt als beim Sprachdienst), begrenzt auf 0,7–1,3 */
                'brate'=>max(0.7,min(1.3,$bRate ?? $rate)),'bpitch'=>max(0.7,min(1.3,$bPitch ?? $pitch)),
                'openai'=>(string)($v['openai'] ?? $base['openai']),'google'=>(string)($v['google'] ?? $base['google']),'azure'=>(string)($v['azure'] ?? $base['azure']),
                'instructions'=>assistant_str($v['instructions'] ?? '',800),'elevenlabs'=>(string)($v['elevenlabs'] ?? ''),'eleven'=>is_array($v['eleven'] ?? null) ? $v['eleven'] : null,
                'browser'=>array_values(array_filter(array_map(fn($x)=>is_string($x) ? assistant_line($x,40) : '',$br),fn($x)=>$x!==''))];
        }
    }
    foreach($out as $id=>&$v){ $t=$v['label']; if(str_contains($t,' · ')) $t=trim(explode(' · ',$t,2)[1]); $v['title']=mb_strtoupper(mb_substr($t,0,1)).mb_substr($t,1);
        $v['brate']=$v['brate'] ?? $v['rate']; $v['bpitch']=$v['bpitch'] ?? $v['pitch']; $v['persona']=$v['persona'] ?? ''; } unset($v);
    return $out;
}
function assistant_preset_for(string $gender, string $tone): string {
    $g=$gender==='m' ? 'm' : 'f'; $pre=assistant_voice_presets();
    foreach(ASSISTANT_TONE_PRESET[$g][$tone] ?? [] as $id) if(isset($pre[$id]) && $pre[$id]['gender']===$g) return $id;
    return $g==='m' ? 'm_freundlich' : 'f_warm';
}
/** Sprechweise (warm/ruhig/energisch/sachlich) zu einer Voreinstellung – Umkehrung von ASSISTANT_TONE_PRESET, erste Zuordnung gewinnt. */
function assistant_preset_tone(string $id): ?string {
    foreach(ASSISTANT_TONE_PRESET as $tones) foreach($tones as $tone=>$ids) if(in_array($id,$ids,true)) return $tone;
    return null;
}
/** Liste fürs Panel (ohne Anbieter-Stimmnamen). tone = passende Sprechweise, damit Stimmkarte und Server-Sprechanweisung zusammenpassen. */
function assistant_voice_presets_public(): array {
    $o=[]; foreach(assistant_voice_presets() as $id=>$v) $o[]=['id'=>$id,'gender'=>$v['gender'],'label'=>$v['title'],'desc'=>$v['desc'],'rate'=>$v['rate'],'pitch'=>$v['pitch'],'browser'=>$v['browser'],'brate'=>$v['brate'],'bpitch'=>$v['bpitch'],'persona'=>$v['persona'],'tone'=>assistant_preset_tone($id)];
    return $o;
}
function assistant_profile(int $acc): array {
    assistant_bootstrap(); $d=assistant_profile_defaults($acc);
    if($acc<=0) return $d;
    $s=th_db()->prepare("SELECT * FROM assistant_profiles WHERE account_id=?"); $s->execute([$acc]); $r=$s->fetch(PDO::FETCH_ASSOC);
    if(!$r) return $d;
    $v=json_decode((string)($r['voice'] ?? ''),true); $p=json_decode((string)($r['personality'] ?? ''),true);
    $o=['account_id'=>$acc,'name'=>(string)$r['name'] ?: 'Lea','gender'=>$r['gender']==='m' ? 'm' : 'f','portrait'=>(string)$r['portrait'],'portrait_data'=>$r['portrait_data'] ?: null,
        'voice'=>array_merge($d['voice'],is_array($v) ? $v : []),'personality'=>array_merge($d['personality'],is_array($p) ? $p : []),'lang'=>in_array($r['lang'],ASSISTANT_LANGS,true) ? $r['lang'] : 'de','saved'=>true];
    if(!in_array($o['voice']['tone'],ASSISTANT_TONES,true)) $o['voice']['tone']='warm';
    $o['voice']['rate']=max(0.7,min(1.3,round((float)$o['voice']['rate'],2))); $o['voice']['pitch']=max(0.7,min(1.3,round((float)$o['voice']['pitch'],2)));
    if(!in_array($o['personality']['address'],['du','sie'],true)) $o['personality']['address']='du';
    if(!in_array($o['personality']['mood'],ASSISTANT_MOODS,true)) $o['personality']['mood']='herzlich';
    $o['personality']['humor']=max(0,min(2,(int)$o['personality']['humor']));
    if(!in_array($o['personality']['detail'],['kurz','ausführlich'],true)) $o['personality']['detail']='kurz';
    $pre=assistant_voice_presets(); $pid=(string)($o['voice']['preset'] ?? '');
    if(!isset($pre[$pid]) || $pre[$pid]['gender']!==$o['gender']) $o['voice']['preset']=assistant_preset_for($o['gender'],$o['voice']['tone']);
    if($o['portrait']!=='custom' && !assistant_portrait($o['portrait'])) $o['portrait']=assistant_default_portrait($o['gender']);
    if($o['portrait']==='custom' && !$o['portrait_data']) $o['portrait']=assistant_default_portrait($o['gender']);
    return $o;
}
/** Rollenbezeichnung für die Oberfläche. */
function assistant_role_label(array $pr): string {
    $sie=$pr['personality']['address']==='sie';
    return $pr['gender']==='m' ? ($sie ? 'Ihr persönlicher Assistent' : 'Dein persönlicher Assistent') : ($sie ? 'Ihre persönliche Assistentin' : 'Deine persönliche Assistentin');
}
function assistant_profile_public(array $pr): array {
    $pre=assistant_voice_presets()[$pr['voice']['preset']] ?? null;
    return ['name'=>$pr['name'],'gender'=>$pr['gender'],'portrait'=>$pr['portrait'],'portrait_url'=>assistant_portrait_url($pr),'has_photo'=>!empty($pr['portrait_data']),
        'photo_url'=>!empty($pr['portrait_data']) && $pr['portrait']!=='custom' ? (string)$pr['portrait_data'] : null,'voice_label'=>$pre ? $pre['title'] : '',
        'voice'=>$pr['voice'],'personality'=>$pr['personality'],'lang'=>$pr['lang'],'lang_tag'=>assistant_lang_tag($pr['lang']),'lang_name'=>assistant_lang_name($pr['lang']),
        'role'=>assistant_role_label($pr),'saved'=>$pr['saved'],'chosen'=>(bool)$pr['saved']];   // chosen: Person & Stimme schon einmal gewählt (jedes gespeicherte Profil) – sonst zeigt das Panel die Auswahl
}
/**
 * Profil speichern (nur übergebene Felder ändern sich). Felder: name, gender, portrait, photo (data-URL), remove_photo,
 * voice{tone,rate,pitch}, personality{address,mood,humor,detail}, lang. Fehler → $err=['error'=>'invalid','field'=>…,'message'=>…], nichts gespeichert.
 */
function assistant_profile_save(int $acc, array $d, ?array &$err=null): array {
    assistant_bootstrap(); $err=null; $c=assistant_profile($acc);
    if($acc<=0){ $err=['error'=>'demo_readonly','message'=>'In der Demo kann nichts gespeichert werden.']; return $c; }
    $bad=function(string $f,string $m) use(&$err,$c){ $err=['error'=>'invalid','field'=>$f,'message'=>$m]; return $c; };
    $v=$c;
    if(array_key_exists('name',$d)){
        $n=trim(preg_replace('/\s+/u',' ',assistant_str($d['name'])) ?? '');
        if($n==='' || mb_strlen($n)>30 || !preg_match('/^\p{L}[\p{L}\p{M} \-]*$/u',$n)) return $bad('name','Name: 1–30 Zeichen, nur Buchstaben, Leerzeichen und Bindestrich.');
        $v['name']=$n; }
    if(array_key_exists('gender',$d)){ $g=assistant_str($d['gender']); if(!in_array($g,['f','m'],true)) return $bad('gender','Bitte „Frau“ oder „Mann“ wählen.'); $v['gender']=$g; }
    if(!empty($d['remove_photo'])){ $v['portrait_data']=null; if($v['portrait']==='custom') $v['portrait']=assistant_default_portrait($v['gender']); }
    if(array_key_exists('photo',$d) && $d['photo']!==null && $d['photo']!==''){
        [$url,$perr]=assistant_photo_check($d['photo']); if($perr!==null) return $bad('photo',$perr);
        $v['portrait_data']=$url; $v['portrait']='custom'; }
    if(array_key_exists('portrait',$d) && !(array_key_exists('photo',$d) && $d['photo'])){
        $pid=assistant_str($d['portrait'],40);
        if($pid==='custom'){ if(empty($v['portrait_data'])) return $bad('portrait','Bitte zuerst ein eigenes Foto hochladen.'); $v['portrait']='custom'; }
        elseif($pid!==''){ $pp=assistant_portrait($pid); if(!$pp) return $bad('portrait','Dieses Porträt gibt es nicht.'); $v['portrait']=$pid; }
    } elseif($v['gender']!==$c['gender'] && $v['portrait']!=='custom'){ $pp=assistant_portrait($v['portrait']); if(!$pp || $pp['gender']!==$v['gender']) $v['portrait']=assistant_default_portrait($v['gender']); }
    if(array_key_exists('voice',$d)){
        if(!is_array($d['voice'])) return $bad('voice','Ungültige Stimmeinstellung.'); $vo=$d['voice'];
        if(isset($vo['tone'])){ if(!in_array($vo['tone'],ASSISTANT_TONES,true)) return $bad('voice.tone','Stimme: warm, ruhig, energisch oder sachlich.'); $v['voice']['tone']=$vo['tone']; }
        foreach(['rate','pitch'] as $k) if(isset($vo[$k])){ if(!is_numeric($vo[$k])) return $bad('voice.'.$k,'Ungültiger Wert für '.($k==='rate' ? 'das Tempo' : 'die Tonhöhe').'.'); $v['voice'][$k]=max(0.7,min(1.3,round((float)$vo[$k],2))); }
        if(isset($vo['preset']) && $vo['preset']!==''){ $pre=assistant_voice_presets(); $pid=assistant_str($vo['preset'],40);
            if(!isset($pre[$pid])) return $bad('voice.preset','Diese Stimme gibt es nicht.');
            if($pre[$pid]['gender']!==$v['gender']){
                /* Geschlecht wird gerade gewechselt und die alte Stimme mitgeschickt → passende Stimme zum Ton wählen statt abzulehnen */
                if($v['gender']===$c['gender']) return $bad('voice.preset','Die Stimme passt nicht zum gewählten Geschlecht.');
                $pid=assistant_preset_for($v['gender'],$v['voice']['tone']); }
            $v['voice']['preset']=$pid;
            /* Nur die Stimme gewählt (Stimm-Chip im Panel)? Dann folgt die Sprechweise der Stimme – sonst widersprächen sich Stimme und Sprechanweisung */
            if(!isset($vo['tone']) && $v['gender']===$c['gender'] && ($t=assistant_preset_tone($pid))!==null) $v['voice']['tone']=$t; }
    }
    $pre=assistant_voice_presets(); $pid=(string)($v['voice']['preset'] ?? '');
    if(!isset($pre[$pid]) || $pre[$pid]['gender']!==$v['gender']) $v['voice']['preset']=assistant_preset_for($v['gender'],$v['voice']['tone']);
    if(array_key_exists('personality',$d)){
        if(!is_array($d['personality'])) return $bad('personality','Ungültige Persönlichkeit.'); $pe=$d['personality'];
        if(isset($pe['address'])){ if(!in_array($pe['address'],['du','sie'],true)) return $bad('personality.address','Anrede: du oder Sie.'); $v['personality']['address']=$pe['address']; }
        if(isset($pe['mood'])){ if(!in_array($pe['mood'],ASSISTANT_MOODS,true)) return $bad('personality.mood','Persönlichkeit: herzlich, professionell oder locker.'); $v['personality']['mood']=$pe['mood']; }
        if(isset($pe['humor'])){ if(!is_numeric($pe['humor']) || (int)$pe['humor']<0 || (int)$pe['humor']>2) return $bad('personality.humor','Humor: 0, 1 oder 2.'); $v['personality']['humor']=(int)$pe['humor']; }
        if(isset($pe['detail'])){ if(!in_array($pe['detail'],['kurz','ausführlich'],true)) return $bad('personality.detail','Antwortlänge: kurz oder ausführlich.'); $v['personality']['detail']=$pe['detail']; }
    }
    if(array_key_exists('lang',$d)){ $l=strtolower(assistant_str($d['lang'],8)); if($l==='nb') $l='no'; if(!in_array($l,ASSISTANT_LANGS,true)) return $bad('lang','Diese Sprache wird nicht unterstützt.'); $v['lang']=$l; }
    th_db()->prepare("INSERT INTO assistant_profiles(account_id,name,gender,portrait,portrait_data,voice,personality,lang) VALUES(?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE name=VALUES(name),gender=VALUES(gender),portrait=VALUES(portrait),portrait_data=VALUES(portrait_data),voice=VALUES(voice),personality=VALUES(personality),lang=VALUES(lang),updated_at=NOW()")
      ->execute([$acc,$v['name'],$v['gender'],$v['portrait'],$v['portrait_data'],json_encode($v['voice']),json_encode($v['personality'],JSON_UNESCAPED_UNICODE),$v['lang']]);
    return assistant_profile($acc);
}
/* Modul-Vertrag: Einstellungen = Profil */
function assistant_settings(int $acc): array { return assistant_profile($acc); }
function assistant_settings_save(int $acc, array $d): array { return assistant_profile_save($acc,$d); }
function assistant_settings_public(array $s): array { return assistant_profile_public($s); }
/** Kein Kundenkanal-Modul: die Werkzeuge gehören nur dem Inhaber im Studio (Widget/Telefon/WhatsApp bekommen sie nie). */
function assistant_enabled(int $acc): bool { return false; }
function assistant_system_hint(int $acc, string $channel='chat'): string { return ''; }

/* ======================================================================= Stimme */
function assistant_tts_provider(): ?string {
    if(!function_exists('ch_tts_synth') || !function_exists('ch_tts_provider')) return null;
    try{ return ch_tts_provider(); }catch(Throwable $e){ return null; }
}
/**
 * Sprachen, die die Server-Stimme beim aktuellen Anbieter wirklich sprechen kann. OpenAI und Azure (native Stimmen je Sprache): alle 13;
 * ElevenLabs multilingual_v2: ohne Norwegisch und Aserbaidschanisch; Google: speech-lib lässt nur de-DE-Stimmen zu → nur Deutsch.
 * Für die übrigen Sprachen spricht die Browserstimme der jeweiligen Sprache.
 */
function assistant_tts_langs(): array {
    $p=assistant_tts_provider(); if($p===null) return [];
    if($p==='google') return ['de'];
    if($p==='elevenlabs') return array_values(array_diff(ASSISTANT_LANGS,['no','az']));
    return ASSISTANT_LANGS;
}
function assistant_tts_ready(string $lang=''): bool { $l=assistant_tts_langs(); return $lang==='' ? (bool)$l : in_array(assistant_lang($lang),$l,true); }
/** Stimmparameter für ch_tts_synth aus Geschlecht, Ton, Persönlichkeit und Sprache des Satzes. $over = ungespeicherte Vorschau (Hörprobe). */
function assistant_voice(array $pr, array $over=[], string $lang=''): array {
    $lang=assistant_lang($lang!=='' ? $lang : $pr['lang']); $L=ASSISTANT_LANG_TABLE[$lang];
    $g=in_array($over['gender'] ?? '',['f','m'],true) ? $over['gender'] : $pr['gender'];
    $tone=in_array($over['tone'] ?? '',ASSISTANT_TONES,true) ? $over['tone'] : $pr['voice']['tone'];
    $rate=isset($over['rate']) && is_numeric($over['rate']) ? max(0.7,min(1.3,(float)$over['rate'])) : (float)$pr['voice']['rate'];
    $pitch=isset($over['pitch']) && is_numeric($over['pitch']) ? max(0.7,min(1.3,(float)$over['pitch'])) : (float)$pr['voice']['pitch'];
    $pre=assistant_voice_presets(); $pid=(string)($over['preset'] ?? ($g===$pr['gender'] ? ($pr['voice']['preset'] ?? '') : ''));
    if(!isset($pre[$pid]) || $pre[$pid]['gender']!==$g) $pid=assistant_preset_for($g,$tone);
    $vm=$pre[$pid];
    $who=$g==='m' ? 'a friendly man in his thirties and a native '.$L['en'].' speaker' : 'a friendly woman in her thirties and a native '.$L['en'].' speaker';
    $toneTxt=['warm'=>'warm, kind and reassuring, with a gentle smile in the voice','ruhig'=>'calm, soft and unhurried, with natural pauses','energisch'=>'bright, upbeat and energetic, but never rushed','sachlich'=>'clear, composed and matter-of-fact, precise articulation'][$tone];
    $mood=['herzlich'=>'Sound heartfelt and personal.','professionell'=>'Sound polished and professional.','locker'=>'Sound relaxed and casual, like talking to a colleague.'][$pr['personality']['mood']] ?? '';
    $humor=(int)$pr['personality']['humor']>=2 ? ' A light, playful undertone is welcome.' : '';
    $base=trim((string)($vm['instructions'] ?? ''));
    /* Preset-Anweisungen von speech-lib sind auf Deutsch zugeschnitten („native German“) – für andere Sprachen die Sprache ausdrücklich ersetzen */
    if($lang!=='de' && $base!=='') $base=trim((string)preg_replace('/\b(native |natural, )*(casual )?(native )?German( from Germany)?\b/u','native '.$L['en'],$base));
    $ins=($base!=='' ? $base.' ' : 'Speak natural, native '.$L['en'].'. Never sound robotic; vary intonation like a real conversation. ')
        .($lang!=='de' ? 'The text is '.$L['en'].' – pronounce it like a native '.$L['en'].' speaker, with no foreign accent. ' : '')
        .'Role: you are '.$pr['name'].', '.$who.' and the owner\'s personal business assistant, talking one-to-one with the owner. Delivery: '.$toneTxt.'. '.$mood.$humor;
    $azure=$lang==='de' ? $vm['azure'] : $L[$g==='m' ? 'az_m' : 'az_f'];
    $v=['gender'=>$g,'preset'=>$pid,'openai'=>$vm['openai'],'google'=>$vm['google'],'azure'=>$azure,'lang'=>$L['tag'],'instructions'=>mb_substr($ins,0,1500),
        'rate'=>round(max(0.7,min(1.3,$rate*(float)$vm['rate'])),2),'pitch'=>round(max(0.7,min(1.3,$pitch*(float)$vm['pitch'])),2)];
    if(!empty($vm['elevenlabs'])) $v['elevenlabs']=$vm['elevenlabs']; if(!empty($vm['eleven'])) $v['eleven']=$vm['eleven'];
    return $v;
}
/** Antworttext → vorlesbare Sätze (ohne Markdown, Links, Emojis). */
function assistant_speech(string $text, string $lang='de', bool $du=true): array {
    if($lang==='ja'){   // Japanisch: keine Leerzeichen – an 。！？ teilen, lange Teile an 、 kürzen
        $t=preg_replace(['#https?://\S+#u','/\*\*|__|`+|^#+\s*/mu','/^\s*[-•*]\s+/mu','/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}]/u'],['','','',' '],$text) ?? $text;
        $t=trim(preg_replace('/\s+/u',' ',$t) ?? $t); $out=[];
        foreach(preg_split('/(?<=[。！？!?])\s*/u',$t,-1,PREG_SPLIT_NO_EMPTY) ?: [] as $p){ $p=trim($p);
            while(mb_strlen($p)>160){ $cut=mb_strrpos(mb_substr($p,0,160),'、'); if($cut===false || $cut<20) $cut=159; $out[]=mb_substr($p,0,$cut+1); $p=trim(mb_substr($p,$cut+1)); }
            if($p!=='') $out[]=$p; }
        return array_slice($out,0,24);
    }
    if(function_exists('ch_speech_sentences')){ try{ $r=ch_speech_sentences($text,$lang,$du); if(is_array($r) && $r) return array_values(array_filter(array_map(fn($x)=>mb_substr(trim((string)$x),0,ASSISTANT_SPEAK_MAX),$r),fn($x)=>$x!=='')); }catch(Throwable $e){} }
    $t=preg_replace('#https?://\S+#u','',$text) ?? $text;
    $t=preg_replace(['/\*\*|__|`+|^#+\s*/mu','/^\s*[-•*]\s+/mu','/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}\x{2705}\x{2728}]/u','/\(\s*\)/u'],['','',' ',''],$t) ?? $t;
    $t=trim(preg_replace('/\s+/u',' ',$t) ?? $t); if($t==='') return [];
    $prot=['z. B.'=>'zum Beispiel','z.B.'=>'zum Beispiel','ca.'=>'circa','bzw.'=>'beziehungsweise','usw.'=>'und so weiter','inkl.'=>'inklusive','ggf.'=>'gegebenenfalls','Nr.'=>'Nummer','Str.'=>'Straße','Dr.'=>'Doktor','evtl.'=>'eventuell','d. h.'=>'das heißt','u. a.'=>'unter anderem'];
    $t=str_replace(array_keys($prot),array_values($prot),$t);
    $parts=preg_split('/(?<=[.!?…])\s+(?=[\p{Lu}\p{N}„"»(])/u',$t) ?: [$t]; $out=[];
    foreach($parts as $p){ $p=trim($p); if($p==='') continue;
        while(mb_strlen($p)>ASSISTANT_SPEAK_MAX){ $cut=mb_strrpos(mb_substr($p,0,ASSISTANT_SPEAK_MAX),', '); if($cut===false || $cut<60) $cut=mb_strrpos(mb_substr($p,0,ASSISTANT_SPEAK_MAX),' '); if($cut===false || $cut<30) $cut=ASSISTANT_SPEAK_MAX-1; $out[]=trim(mb_substr($p,0,$cut+1)); $p=trim(mb_substr($p,$cut+1)); }
        if($p!=='') $out[]=$p; }
    return $out;
}

/* ======================================================================= Gedächtnis */
function assistant_memory(int $acc, int $limit=100): array {
    assistant_bootstrap(); if($acc<=0) return [];
    $s=th_db()->prepare("SELECT id,topic,fact,source FROM assistant_memory WHERE account_id=? ORDER BY id DESC LIMIT ".max(1,min(300,$limit))); $s->execute([$acc]);
    return array_map(fn($r)=>['id'=>(int)$r['id'],'topic'=>$r['topic'],'fact'=>$r['fact'],'source'=>$r['source']],$s->fetchAll(PDO::FETCH_ASSOC));
}
/** Fakt merken. $replace = ältere Fakten zum selben Thema ersetzen. Rückgabe ['id','new'] oder Fehler. */
function assistant_remember(int $acc, string $topic, string $fact, string $source='chat', bool $replace=false): array {
    assistant_bootstrap(); if($acc<=0) return ['error'=>'demo_readonly','hint'=>'In der Demo wird nichts gespeichert.'];
    $topic=assistant_line($topic,60); $fact=assistant_line($fact,500);
    if($topic==='' || $fact==='') return ['error'=>'topic_and_fact_required','hint'=>'Bitte ein kurzes Thema und den Fakt angeben.'];
    $p=th_db();
    if($replace) $p->prepare("DELETE FROM assistant_memory WHERE account_id=? AND topic=? AND fact<>?")->execute([$acc,$topic,$fact]);
    $s=$p->prepare("INSERT IGNORE INTO assistant_memory(account_id,topic,fact,source) VALUES(?,?,?,?)"); $s->execute([$acc,$topic,$fact,mb_substr($source,0,20)]);
    $new=$s->rowCount()>0;
    $q=$p->prepare("SELECT id FROM assistant_memory WHERE account_id=? AND topic=? AND fact=? LIMIT 1"); $q->execute([$acc,$topic,$fact]); $id=(int)$q->fetchColumn();
    $c=$p->prepare("SELECT COUNT(*) FROM assistant_memory WHERE account_id=?"); $c->execute([$acc]); $n=(int)$c->fetchColumn();
    if($n>ASSISTANT_MEMORY_MAX){ $o=$p->prepare("SELECT id FROM assistant_memory WHERE account_id=? ORDER BY id ASC LIMIT ".($n-ASSISTANT_MEMORY_MAX)); $o->execute([$acc]); $ids=array_map('intval',$o->fetchAll(PDO::FETCH_COLUMN));
        if($ids) $p->prepare("DELETE FROM assistant_memory WHERE account_id=? AND id IN (".implode(',',$ids).")")->execute([$acc]); }
    return ['id'=>$id,'new'=>$new];
}
function assistant_forget(int $acc, int $id): bool {
    assistant_bootstrap(); if($acc<=0 || $id<=0) return false;
    $s=th_db()->prepare("DELETE FROM assistant_memory WHERE id=? AND account_id=?"); $s->execute([$id,$acc]); return $s->rowCount()>0;
}

/* ======================================================================= Verlauf */
function assistant_history(int $acc, int $limit=30): array {
    assistant_bootstrap(); if($acc<=0) return [];
    $s=th_db()->prepare("SELECT id,role,content,meta,created_at FROM assistant_messages WHERE account_id=? ORDER BY id DESC LIMIT ".max(1,min(200,$limit))); $s->execute([$acc]);
    $rows=array_reverse($s->fetchAll(PDO::FETCH_ASSOC));
    return array_map(function($r){ $m=json_decode((string)($r['meta'] ?? ''),true); $m=is_array($m) ? $m : [];
        return ['id'=>(int)$r['id'],'role'=>$r['role'],'content'=>$r['content'],'actions'=>array_values((array)($m['actions'] ?? [])),'greeting'=>!empty($m['greeting']),
            'lang'=>isset($m['lang']) && is_string($m['lang']) && isset(ASSISTANT_LANG_TABLE[$m['lang']]) ? $m['lang'] : null,'created_at'=>th_dbiso($r['created_at'])]; },$rows);
}
function assistant_add_message(int $acc, string $role, string $content, array $meta=[]): int {
    assistant_bootstrap(); $p=th_db();
    $p->prepare("INSERT INTO assistant_messages(account_id,role,content,meta) VALUES(?,?,?,?)")->execute([$acc,$role==='assistant' ? 'assistant' : 'user',mb_substr($content,0,20000),$meta ? json_encode($meta,JSON_UNESCAPED_UNICODE) : null]);
    $id=(int)$p->lastInsertId();
    if(random_int(1,20)===1){ $q=$p->prepare("SELECT id FROM assistant_messages WHERE account_id=? ORDER BY id DESC LIMIT 1 OFFSET ".ASSISTANT_HISTORY_KEEP); $q->execute([$acc]); $cut=(int)$q->fetchColumn();
        if($cut>0) $p->prepare("DELETE FROM assistant_messages WHERE account_id=? AND id<=?")->execute([$acc,$cut]); }
    return $id;
}
/** Verlauf für die KI: erledigte Aktionen werden als kurze Notiz an die Antwort gehängt, damit der Assistent weiß, was er schon getan hat. */
function assistant_ai_messages(int $acc): array {
    $out=[];
    foreach(assistant_history($acc,ASSISTANT_HISTORY_AI) as $h){
        $c=mb_substr((string)$h['content'],0,4000);
        if($h['role']==='assistant' && $h['actions']) $c.="\n(Ausgeführt: ".implode('; ',array_map(fn($a)=>($a['ok'] ? '' : 'FEHLGESCHLAGEN: ').($a['summary'] ?? $a['name'] ?? ''),$h['actions'])).')';
        $out[]=['role'=>$h['role'],'content'=>$c];
    }
    return $out;
}
/** Persönliche Begrüßung beim ersten Besuch (ohne KI, kostenlos). */
function assistant_greeting(int $acc, array $pr, ?array $ov=null): string {
    $ov=$ov ?? assistant_overview($acc); $sie=$pr['personality']['address']==='sie'; $n=$pr['name']; $f=$pr['gender']!=='m';
    $company=''; foreach($ov['configs'] as $c) if($c['company']!==''){ $company=$c['company']; break; }
    $lang=assistant_lang($pr['lang']);
    if($lang!=='de'){
        $T=fn(string $k)=>assistant_lt($lang,$k,$sie,$f,['name'=>$n,'company'=>$company]);
        $sep=$lang==='ja' ? '' : ' ';
        return $T('hi').$sep.$T('intro').$sep.$T('mid').$sep.$T($company!=='' ? 'co' : 'new');
    }
    $hi=['herzlich'=>'Hallo und herzlich willkommen!','professionell'=>'Guten Tag!','locker'=>($sie ? 'Hallo, schön, dass Sie da sind!' : 'Hey, schön, dass du da bist!')][$pr['personality']['mood']] ?? 'Hallo!';
    $role=$f ? ($sie ? 'Ihre persönliche Assistentin' : 'deine persönliche Assistentin') : ($sie ? 'Ihr persönlicher Assistent' : 'dein persönlicher Assistent');
    $mid=$sie ? 'Erzählen Sie mir einfach, was erledigt werden soll – ich frage nach, was ich noch wissen muss, und richte Ihre Agenten Schritt für Schritt mit Ihnen ein.'
              : 'Erzähl mir einfach, was erledigt werden soll – ich frage nach, was ich noch wissen muss, und richte deine Agenten Schritt für Schritt mit dir ein.';
    $end=$company!=='' ? ($sie ? 'Ich sehe, „'.$company.'“ ist schon angelegt. Was soll ich als Erstes für Sie übernehmen?' : 'Ich sehe, „'.$company.'“ ist schon angelegt. Was soll ich als Erstes für dich übernehmen?')
                       : ($sie ? 'Zum Start: Was für einen Betrieb führen Sie?' : 'Zum Start: Was für einen Betrieb hast du?');
    return $hi.' Ich bin '.$n.', '.$role.' hier im ChatHelp Studio. '.$mid.' '.$end;
}
/** Begrüßung einmalig in den Verlauf legen, wenn er leer ist. */
function assistant_ensure_greeting(int $acc, array $pr): void {
    if($acc<=0) return; $s=th_db()->prepare("SELECT COUNT(*) FROM assistant_messages WHERE account_id=?"); $s->execute([$acc]);
    if((int)$s->fetchColumn()===0) assistant_add_message($acc,'assistant',assistant_greeting($acc,$pr),['greeting'=>true,'lang'=>assistant_lang($pr['lang'])]);
}

/* ======================================================================= Konto-Überblick */
function assistant_configs(int $acc): array {
    $s=th_db()->prepare("SELECT * FROM agent_configs WHERE account_id=? ORDER BY updated_at DESC, id DESC"); $s->execute([$acc]); return $s->fetchAll(PDO::FETCH_ASSOC);
}
function assistant_active_plans(int $acc): array {
    $o=[]; foreach(th_plans($acc) as $p){ if(!in_array($p['status'],['active','trialing','past_due'],true)) continue; $pl=ch_plan($p['plan']); if($pl) $o[$p['plan']]=['id'=>$p['plan'],'name'=>$pl['name'],'kind'=>$pl['kind'],'base'=>$pl['base'],'period_end'=>$p['period_end']]; }
    return array_values($o);
}
/** Passende Agenten zu einer Branchenbeschreibung (erste Wahl zuerst). */
function assistant_sector_plans(string $text): array {
    foreach(ASSISTANT_SECTOR_MAP as [$re,$plans]) if(preg_match($re,$text)) return array_values(array_filter($plans,fn($p)=>isset(CH_PLANS[$p])));
    return [];
}
/**
 * Agent, auf den sich Werkzeuge ohne „plan“ beziehen – stabil, damit Profil, FAQ und Ton beim selben Agenten landen:
 * aktiver Plan → Konfiguration mit Betriebsprofil → zuletzt bearbeitete Konfiguration → Branche aus dem Gedächtnis → Telefon-Agent.
 */
function assistant_main_plan(int $acc): string {
    foreach(assistant_active_plans($acc) as $p){ $b=$p['base']; if((CH_PLANS[$b][4] ?? '')==='agent') return $b; }
    $cfgs=array_values(array_filter(assistant_configs($acc),fn($c)=>(ch_plan((string)$c['plan'])['kind'] ?? '')==='agent'));
    foreach($cfgs as $c) if(assistant_kb_section_get((string)($c['kb'] ?? ''),'Betriebsprofil')!==null) return (string)$c['plan'];
    if($cfgs) return (string)$cfgs[0]['plan'];
    $mem=implode(' ',array_map(fn($m)=>$m['topic'].' '.$m['fact'],assistant_memory($acc,60)));
    $sp=assistant_sector_plans($mem); if($sp && (CH_PLANS[$sp[0]][4] ?? '')==='agent') return $sp[0];
    return 'agent-phone';
}
/** Plan-Eingabe der KI auflösen (buy-… → zugrunde liegender Agent). Rückgabe [plan|null, Fehler|null]. */
function assistant_resolve_plan(int $acc, $raw): array {
    $id=preg_replace('/[^a-z0-9-]/','',strtolower(assistant_str($raw)));
    if($id==='') return [assistant_main_plan($acc),null];
    $id=CH_PLAN_BASE[$id] ?? $id; $pl=ch_plan($id);
    if(!$pl || $pl['kind']!=='agent'){ $known=array_map(fn($c)=>(string)$c['plan'],assistant_configs($acc));
        return [null,['error'=>'unknown_plan','hint'=>'Diesen Agenten gibt es nicht. Ohne „plan“ wird der Haupt-Agent ('.assistant_main_plan($acc).') verwendet.'.($known ? ' Vorhandene Agenten: '.implode(', ',$known).'.' : '')]]; }
    return [$id,null];
}
function assistant_db_count(string $sql, array $args): int { try{ $s=th_db()->prepare($sql); $s->execute($args); return (int)$s->fetchColumn(); }catch(Throwable $e){ return 0; } }
/** Kompletter Überblick (Agenten, Module, Guthaben) – Grundlage für Systemprompt, Checkliste und get_overview. */
function assistant_overview(int $acc): array {
    $cfgs=[]; $faqTrain=[];
    if(function_exists('train_bootstrap')){ try{ train_bootstrap(); $s=th_db()->prepare("SELECT plan,COUNT(*) n FROM agent_faq WHERE account_id=? AND active=1 GROUP BY plan"); $s->execute([$acc]); foreach($s->fetchAll(PDO::FETCH_ASSOC) as $r) $faqTrain[$r['plan']]=(int)$r['n']; }catch(Throwable $e){} }
    foreach(assistant_configs($acc) as $c){ $kb=(string)($c['kb'] ?? ''); $plan=(string)$c['plan'];
        $sec=assistant_kb_section_get($kb,'Häufige Fragen'); $kbFaq=$sec===null ? 0 : preg_match_all('/^F:\s*\S/mu',$sec);
        $cfgs[]=['plan'=>$plan,'name'=>assistant_plan_name($plan),'company'=>(string)$c['company'],'tone'=>(string)$c['tone'],'lang'=>(string)$c['lang'],'kb'=>$kb,'kb_chars'=>mb_strlen($kb),
            'widget_on'=>(int)$c['widget_on'],'phone_on'=>(int)$c['phone_on'],'phone_number'=>(string)($c['phone_number'] ?? ''),'phone_hours'=>(string)($c['phone_hours'] ?? ''),
            'has_profile'=>assistant_kb_section_get($kb,'Betriebsprofil')!==null,'faq'=>($faqTrain[$plan] ?? 0)+$kbFaq,'active'=>ch_plan_active_any($acc,$plan),'updated_at'=>$c['updated_at']]; }
    $mods=[];
    try{ $a=appt_settings($acc); $svc=appt_services($acc,true); $hRow=assistant_db_count("SELECT COUNT(*) FROM appt_settings WHERE account_id=? AND hours IS NOT NULL",[$acc]);
        /* appt_settings_save schreibt immer auch die Standardzeiten – „eingerichtet“ erst, wenn eingeschaltet oder von den Standardzeiten abweichend */
        $custom=defined('APPT_DEFAULT_HOURS') ? assistant_hours_sig($a['hours'])!==assistant_hours_sig(APPT_DEFAULT_HOURS) : true;
        $mods['appt']=['label'=>'Termine','tab'=>'termine','on'=>(int)$a['appt_on']===1,'company'=>(string)$a['company'],'address'=>(string)$a['address'],'services'=>count($svc),'service_names'=>array_slice(array_map(fn($x)=>$x['name'],$svc),0,8),
            'hours_saved'=>$hRow>0 && ((int)$a['appt_on']===1 || $custom),'hours_text'=>$hRow>0 ? assistant_hours_text($a['hours']) : '']; }catch(Throwable $e){}
    try{ $o=ord_settings($acc); $menu=ord_menu($acc,true); $hRow=assistant_db_count("SELECT COUNT(*) FROM order_settings WHERE account_id=? AND hours IS NOT NULL",[$acc]);
        $mods['order']=['label'=>'Bestellungen','tab'=>'bestellungen','on'=>(int)$o['order_on']===1,'company'=>(string)$o['name'],'address'=>(string)$o['address'],'items'=>count($menu),'whatsapp'=>(string)$o['wa_number']!=='' || (string)$o['wa_phone_id']!=='','hours_saved'=>$hRow>0]; }catch(Throwable $e){}
    if(function_exists('ride_settings')) try{ $r=ride_settings($acc); $mods['ride']=['label'=>'Taxi','tab'=>'taxi','on'=>(int)$r['ride_on']===1,'company'=>(string)$r['name'],'address'=>(string)$r['address'],'saved'=>!empty($r['saved'])]; }catch(Throwable $e){}
    if(function_exists('hotel_settings')) try{ $h=hotel_settings($acc); $rooms=function_exists('hotel_rooms') ? count(hotel_rooms($acc,true)) : 0; $mods['hotel']=['label'=>'Hotel','tab'=>'hotel','on'=>(int)$h['hotel_on']===1,'company'=>(string)$h['name'],'address'=>(string)$h['address'],'rooms'=>$rooms]; }catch(Throwable $e){}
    if(function_exists('cb_settings')) try{ $cb=cb_settings($acc); $mods['callback']=['label'=>'Rückrufe','tab'=>'rueckruf','on'=>(int)$cb['cb_on']===1,'ai_callback'=>(int)$cb['ai_callback']===1]; }catch(Throwable $e){}
    if(function_exists('train_faq')) $mods['train']=['label'=>'Training','tab'=>'training','on'=>true,'faq'=>array_sum($faqTrain)];
    $tested=assistant_db_count("SELECT COUNT(*) FROM api_usage WHERE account_id=? AND (task LIKE 'agent:%' OR task IN ('widget','phone','whatsapp','booking','train:test'))",[$acc])
           +assistant_db_count("SELECT COUNT(*) FROM phone_calls WHERE account_id=?",[$acc]);
    return ['balance'=>th_balance($acc),'plans'=>assistant_active_plans($acc),'configs'=>$cfgs,'modules'=>$mods,'main_plan'=>assistant_main_plan($acc),'tested'=>$tested];
}
/** Überblick als kompakter Text für den Systemprompt / das Werkzeug get_overview. */
function assistant_overview_text(array $ov): string {
    $l=[];
    $l[]='Guthaben: '.number_format($ov['balance'],0,',','.').' Tokens.';
    $l[]='Gemietete/gekaufte Agenten: '.($ov['plans'] ? implode(', ',array_map(fn($p)=>$p['name'].' ('.$p['id'].')',$ov['plans'])) : 'noch keiner – alles läuft im Studio-Test mit Tokens.');
    if($ov['configs']) foreach($ov['configs'] as $c) $l[]='Agent '.$c['name'].' ('.$c['plan'].'): Firma „'.($c['company'] ?: '–').'“, Ton '.$c['tone'].', Sprache '.$c['lang'].', Wissensbasis '.number_format($c['kb_chars'],0,',','.').' Zeichen'
        .($c['has_profile'] ? ' (mit Betriebsprofil)' : '').', FAQ '.$c['faq'].', Widget '.($c['widget_on'] ? 'an' : 'aus').', Telefon '.($c['phone_on'] ? 'an'.($c['phone_number'] ? ' ('.$c['phone_number'].')' : '') : 'aus').($c['active'] ? ', aktiv' : '').'.';
    else $l[]='Agent-Konfigurationen: noch keine.';
    $l[]='Haupt-Agent für Werkzeuge ohne „plan“: '.$ov['main_plan'].' ('.assistant_plan_name($ov['main_plan']).').';
    foreach($ov['modules'] as $k=>$m){
        $x=$m['label'].': '.($m['on'] ? 'eingeschaltet' : 'aus');
        if($k==='appt') $x.=', '.$m['services'].' Leistungen'.($m['service_names'] ? ' ('.implode(', ',$m['service_names']).')' : '');
        if($k==='order') $x.=', '.$m['items'].' Artikel auf der Karte'.($m['whatsapp'] ? ', WhatsApp verbunden' : '');
        if($k==='hotel') $x.=', '.$m['rooms'].' Zimmerkategorien';
        if($k==='callback') $x.=$m['ai_callback'] ? ', KI-Rückruf an' : '';
        if($k==='train') $x='Training: '.$m['faq'].' FAQ-Antworten';
        $l[]=$x.'.';
    }
    $l[]='Testgespräche bisher: '.($ov['tested']>0 ? 'ja' : 'noch keine').'.';
    return '- '.implode("\n- ",$l);
}

/* ======================================================================= Wissensbasis-Abschnitte (## Titel) */
function assistant_section_title(string $t): string { return mb_substr(trim(preg_replace('/[#\s]+/u',' ',$t) ?? ''),0,60); }
/** Freitext für die Wissensbasis: Zeilen, die wie eine Abschnittsüberschrift („# …“ / „## …“) aussehen, zu „### …“ entschärfen – sonst entstünden falsche Abschnitte. */
function assistant_kb_text(string $s): string { return (string)(preg_replace('/^[ \t]*#{1,2}(?=[ \t]|$)/mu','###',$s) ?? $s); }
function assistant_kb_section_get(string $kb, string $title): ?string {
    $t=preg_quote(assistant_section_title($title),'/');
    if(!preg_match('/^##[ \t]+'.$t.'[ \t]*$/miu',$kb,$m,PREG_OFFSET_CAPTURE)) return null;
    $start=$m[0][1]+strlen($m[0][0]); $rest=substr($kb,$start);
    $end=preg_match('/^##[ \t]+\S/mu',$rest,$n,PREG_OFFSET_CAPTURE) ? $n[0][1] : strlen($rest);
    return trim(substr($rest,0,$end));
}
/** Abschnitt setzen: mode replace (ersetzen) oder append (anhängen); fehlt er, wird er angelegt ($top = an den Anfang). */
function assistant_kb_section_set(string $kb, string $title, string $text, string $mode='replace', bool $top=false): string {
    $title=assistant_section_title($title); $text=trim($text); $t=preg_quote($title,'/'); $head='## '.$title;
    if(preg_match('/^##[ \t]+'.$t.'[ \t]*$/miu',$kb,$m,PREG_OFFSET_CAPTURE)){
        $start=$m[0][1]; $bodyStart=$start+strlen($m[0][0]); $rest=substr($kb,$bodyStart);
        $end=preg_match('/^##[ \t]+\S/mu',$rest,$n,PREG_OFFSET_CAPTURE) ? $n[0][1] : strlen($rest);
        $old=trim(substr($rest,0,$end)); $body=$mode==='append' && $old!=='' ? $old."\n".$text : $text;
        $after=ltrim(substr($rest,$end));
        return rtrim(substr($kb,0,$start)).($start>0 ? "\n\n" : '').$head."\n".$body.($after!=='' ? "\n\n".$after : '');
    }
    $kb=trim($kb); $sec=$head."\n".$text;
    if($kb==='') return $sec;
    return $top ? $sec."\n\n".$kb : $kb."\n\n".$sec;
}
/** Wissensbasis speichern, ohne Widget/Telefon versehentlich abzuschalten. */
function assistant_cfg_save(int $acc, string $plan, array $d): void {
    $cur=th_agent_cfg($acc,$plan);
    $keep=ch_plan_active_any($acc,$plan) || !empty($cur['widget_on']) || !empty($cur['phone_on']);
    th_agent_cfg_save($acc,$plan,$d,$keep);
    if(function_exists('train_cache_bust')) train_cache_bust();
}

/* ======================================================================= Checkliste */
/** key => [Bezeichnung, Hinweis (neutral formuliert, passt zu du und Sie), Vorschlag des Inhabers an den Assistenten] */
const ASSISTANT_CHECK = [
    'profil'=>['Betriebsprofil','Name, Adresse, Telefon und was der Betrieb macht.','Ich erzähle dir kurz von meinem Betrieb.'],
    'zeiten'=>['Öffnungszeiten','Wann ist der Betrieb für Kundinnen und Kunden da?','Trag bitte unsere Öffnungszeiten ein.'],
    'angebot'=>['Leistungen, Preise & Karte','Was bietet der Betrieb an – und zu welchem Preis?','Lass uns meine Leistungen und Preise anlegen.'],
    'faq'=>['Häufige Fragen (mind. 5)','Die Fragen, die Kunden ständig stellen – mit den passenden Antworten.','Welche Fragen stellen meine Kunden am häufigsten?'],
    'ton'=>['Ton & Sprache','Wie soll der Agent klingen – du oder Sie, locker oder seriös?','Wie soll mein Agent mit Kunden sprechen?'],
    'kanaele'=>['Website, Telefon oder WhatsApp','Wo sollen Kunden den Agenten erreichen?','Wie bringe ich den Agenten auf meine Website oder ans Telefon?'],
    'aktiv'=>['Agent aktiv','Gemietet oder gekauft – dann läuft er rund um die Uhr.','Welcher Agent passt am besten zu meinem Betrieb?'],
    'test'=>['Testgespräch geführt','Einmal selbst ausprobieren, wie der Agent antwortet.','Wie teste ich meinen Agenten am besten?'],
];
function assistant_mem_has(array $mem, string $re): bool { foreach($mem as $m) if(preg_match($re,$m['topic'])) return true; return false; }
/** Öffnungszeiten im Freitext der Wissensbasis (mit Uhrzeiten, nicht nur die Überschrift einer Vorlage). */
const ASSISTANT_RE_HOURS = '/(öffnungszeit|geöffnet|sprechzeit|sprechstunde)[^\n]{0,80}\d|\b(mo|di|mi|do|fr|sa|so)[a-z]*\.?\s*[–-]\s*(mo|di|mi|do|fr|sa|so)[a-z]*\.?:?\s+\d|\d{1,2}([:.]\d{2})?\s*[–-]\s*\d{1,2}([:.]\d{2})?\s*uhr/iu';
/** Leistungen/Preise in der Wissensbasis: eigener Abschnitt oder Leistungen mit echten Euro-Preisen. */
const ASSISTANT_RE_OFFER = '/^##[ \t]+(Leistungen|Angebot|Preise|Speisekarte|Sortiment|Zimmer|Tarife)|(leistung|preis|angebot|karte|tarif)[^\n]{0,200}\d+([,.]\d{1,2})?\s*(€|eur\b|euro\b)/miu';
/** Checkliste aus echten Daten (Agenten, Module, Training) + Gedächtnis. Rückgabe [items, progress 0–100]. */
function assistant_checklist(int $acc, ?array $ov=null, ?array $mem=null): array {
    $ov=$ov ?? assistant_overview($acc); $mem=$mem ?? assistant_memory($acc,150); $m=$ov['modules']; $c=$ov['configs']; $done=[]; $info=[];
    $kbAll=implode("\n",array_map(fn($x)=>$x['kb'],$c));
    $co=''; foreach($c as $x) if($x['company']!=='' && ($x['has_profile'] || $x['kb_chars']>=300)){ $co=$x['company']; break; }
    if($co==='') foreach(['appt','order','hotel','ride'] as $k) if(!empty($m[$k]['company']) && !empty($m[$k]['address'])){ $co=$m[$k]['company']; break; }
    $done['profil']=$co!==''; if($co!=='') $info['profil']='„'.$co.'“';
    $done['zeiten']=!empty($m['appt']['hours_saved']) || (!empty($m['order']['hours_saved']) && !empty($m['order']['on']))
        || (bool)preg_match(ASSISTANT_RE_HOURS,$kbAll)
        || (bool)array_filter($c,fn($x)=>$x['phone_hours']!=='') || assistant_mem_has($mem,'/öffnungszeit|zeiten|geöffnet|sprechzeit/iu');
    if(!empty($m['appt']['hours_saved']) && $m['appt']['hours_text']!=='') $info['zeiten']=$m['appt']['hours_text'];
    $offer=(int)($m['appt']['services'] ?? 0)+(int)($m['order']['items'] ?? 0)+(int)($m['hotel']['rooms'] ?? 0);
    $done['angebot']=$offer>0 || (!empty($m['ride']['saved']) && !empty($m['ride']['on'])) || (bool)preg_match(ASSISTANT_RE_OFFER,$kbAll)
        || assistant_mem_has($mem,'/angebot|leistung|preis|karte|sortiment|produkt|tarif/iu');
    if($offer>0) $info['angebot']=$offer.' '.($offer===1 ? 'Eintrag' : 'Einträge').' angelegt';
    $faq=array_sum(array_map(fn($x)=>$x['faq'],$c)); $done['faq']=$faq>=5; $info['faq']=$faq>=5 ? $faq.' Antworten hinterlegt' : ($faq>0 ? $faq.' von 5 – noch '.(5-$faq).' Antwort'.(5-$faq===1 ? '' : 'en') : null);
    $done['ton']=(bool)array_filter($c,fn($x)=>$x['tone']!=='' && $x['tone']!=='freundlich-professionell') || assistant_mem_has($mem,'/^(ton|tonalität|anrede|sprache)/iu');
    $chan=[]; foreach($c as $x){ if($x['widget_on']) $chan['Website']=1; if($x['phone_on']) $chan['Telefon']=1; } if(!empty($m['order']['whatsapp'])) $chan['WhatsApp']=1;
    $done['kanaele']=(bool)$chan; if($chan) $info['kanaele']=implode(', ',array_keys($chan));
    $agents=array_values(array_filter($ov['plans'],fn($p)=>in_array($p['kind'],['agent','buy'],true)));
    $done['aktiv']=(bool)$agents; if($agents) $info['aktiv']=$agents[0]['name'];
    $done['test']=$ov['tested']>0;
    $items=[]; foreach(ASSISTANT_CHECK as $k=>[$label,$hint,$prompt]) $items[]=['key'=>$k,'label'=>$label,'done'=>$done[$k],'hint'=>$done[$k] ? ($info[$k] ?? 'Erledigt') : ($info[$k] ?? $hint),'prompt'=>$prompt];
    $n=count(array_filter($items,fn($i)=>$i['done']));
    return [$items,(int)round($n/count($items)*100)];
}
/** 3–4 Vorschläge für die nächsten Schritte (anklickbare Chips). */
function assistant_suggestions(array $items, array $mem): array {
    $o=[]; foreach($items as $i){ if(!$i['done']) $o[]=$i['prompt']; if(count($o)>=3) break; }
    if(!$o) $o=['Was kann ich an meinem Agenten noch verbessern?','Fasse den Stand meiner Agenten zusammen.','Schreib mir drei neue FAQ-Antworten.'];
    $o[]=$mem ? 'Was weißt du schon über meinen Betrieb?' : 'Was kannst du alles für mich erledigen?';
    return array_slice($o,0,4);
}

/* ======================================================================= Systemprompt */
function assistant_system(int $acc, array $pr, array $ov, array $items, int $progress, array $mem, bool $voice, bool $toolsReady=true): string {
    $f=$pr['gender']!=='m'; $n=$pr['name']; $sie=$pr['personality']['address']==='sie'; $pe=$pr['personality'];
    $s="Du bist {$n}, ".($f ? 'die persönliche Assistentin' : 'der persönliche Assistent')." des Inhabers bzw. der Inhaberin dieses Kontos im ChatHelp Studio. ChatHelp vermietet KI-Agenten für kleine und mittlere Betriebe (Website-Chat, Telefon, WhatsApp, Termine, Bestellungen, Taxi, Hotel, Rückrufe). Du bist kein Kundenservice für Endkunden, sondern die rechte Hand des Inhabers: Du hörst zu, lernst seinen Betrieb kennen und richtest seine Agenten mit ihm ein.";
    $s.="\n\nPERSONA\n• Du bist ".($f ? 'eine Frau. Sprich über dich in weiblichen Formen („ich bin deine Assistentin“, „deine Ansprechpartnerin“).' : 'ein Mann. Sprich über dich in männlichen Formen („ich bin dein Assistent“, „dein Ansprechpartner“).');
    $s.="\n• Anrede: ".($sie ? 'Du siezt den Inhaber konsequent („Sie“, „Ihr“).' : 'Du duzt den Inhaber konsequent („du“, „dein“).');
    $s.="\n• Art: ".['herzlich'=>'herzlich, warm und ermutigend – du freust dich ehrlich über Fortschritte.','professionell'=>'professionell, ruhig und präzise – seriös wie eine erfahrene Büroleitung.','locker'=>'locker, entspannt und nahbar – wie eine gute Kollegin bzw. ein guter Kollege.'][$pe['mood']];
    $s.="\n• Humor: ".[0=>'kein Humor, freundlich-sachlich.',1=>'ab und zu ein leichtes Augenzwinkern, nie albern.',2=>'gern humorvoll und schlagfertig, aber nie auf Kosten anderer und nie bei ernsten Themen.'][(int)$pe['humor']];
    $s.="\n• Antwortlänge: ".($pe['detail']==='kurz' ? 'kurz – meist 1 bis 3 Sätze.' : 'ausführlicher – bis 6 Sätze, mit kurzer Erklärung, wo es hilft.');
    $s.="\n• Ehrlichkeit: Fragt dich jemand, ob du ein Mensch bist, sagst du freundlich, dass du eine KI-".($f ? 'Assistentin' : 'Assistent')." bist. Behaupte nie, ein Mensch zu sein oder Dinge außerhalb des Studios getan zu haben.";
    $ln=assistant_lang_name($pr['lang']);
    $s.="\n• Sprache: Antworte auf ".$ln.", außer der Inhaber schreibt oder spricht erkennbar in einer anderen Sprache – dann antwortest du in seiner Sprache, so natürlich wie eine Muttersprachlerin bzw. ein Muttersprachler. Du verstehst und sprichst fließend: "
        .implode(', ',array_map('assistant_lang_name',ASSISTANT_LANGS)).'. Die Anrede-Regel gilt sinngemäß in jeder Sprache ('.($sie ? 'förmlich, z. B. vous, usted, Lei, siz, вы, u, Te' : 'vertraut, z. B. tu, tú, sen, ты, je, sinä').'). Werkzeug-Eingaben (Abschnitte, FAQ, Leistungen) schreibst du in der Sprache, in der die Kunden des Betriebs bedient werden – im Zweifel Deutsch.';
    $s.=$voice ? "\n• SPRACHAUSGABE IST AN: Deine Antwort wird mit deiner Stimme vorgelesen. Sprich natürlich wie im echten Gespräch: kurze Sätze, Alltagswörter, keine Listen, kein Markdown, keine Emojis, keine Klammern, keine URLs (Links stehen ohnehin im Chat). Zahlen und Uhrzeiten so, wie man sie spricht."
               : "\n• Schreibstil: natürlich und persönlich. Kurze Aufzählungen mit „•“ sind erlaubt, wenn du etwas zusammenfasst; sonst Fließtext. Kein Markdown außer **fett** für einzelne Wörter.";
    $s.="\n\nARBEITSWEISE";
    $s.="\n1. Zuhören: Reagiere zuerst kurz und konkret auf das, was der Inhaber gesagt hat.";
    $s.="\n2. Interview: Frag aktiv nach dem, was du noch nicht weißt – immer nur EINE konkrete, freundliche Frage pro Antwort. Orientiere dich an der Checkliste: Art des Betriebs, Angebot und Preise, Öffnungszeiten, typische Kundenfragen (mit Antworten), Ton und Anrede, Kanäle (Telefon, WhatsApp, Website). Frag nie nach etwas, das schon im Gedächtnis oder im Konto-Überblick steht – bestätige es höchstens kurz. Erzählt der Inhaber mehrere Dinge auf einmal, nimm alles auf und frag dann nach dem wichtigsten fehlenden Punkt.";
    $s.="\n3. Merken: Jede neue, dauerhafte Information über Betrieb, Wünsche oder Vorlieben des Inhabers sofort mit remember speichern (kurzes Thema wie „Branche“, „Öffnungszeiten“, „Zielgruppe“ + Fakt). Ändert sich etwas, mit replace=true ersetzen. Nichts doppelt merken.";
    $s.="\n4. Einrichten: BEVOR du Einstellungen änderst (save_business_profile, update_agent_knowledge, add_faq, set_agent_tone, configure_appointments, configure_menu, configure_taxi, configure_hotel, configure_callbacks), fasse in einem Satz zusammen, was du eintragen willst, und frag: „Soll ich das so übernehmen?“. Erst nach einem klaren Ja (ja, passt, mach, übernehmen …) das Werkzeug aufrufen. Ohne Rückfrage erlaubt: remember, get_overview, recommend_agent, open_studio_tab.";
    $s.="\n5. Danach: kurz bestätigen, was geändert wurde, und den nächsten offenen Punkt der Checkliste ansprechen.";
    $s.="\n6. Fakten: Erfinde nie Preise, Zeiten, Adressen oder Leistungen – nur, was der Inhaber gesagt hat. Fehlt etwas, frag nach.";
    $s.="\n7. Grenzen: Du kannst keine Zahlungen auslösen, keine Pläne kaufen und keine Telefonnummern schalten. Für einen Agenten empfiehlst du mit recommend_agent den passenden Plan (Preis + Link im Chat, nie vorlesen). Eine eigene Rufnummer richtet support@chat-help.com ein. Keine Rechts- oder Steuerberatung.";
    $s.="\n8. Tabs: Will der Inhaber etwas selbst sehen oder bearbeiten, öffne mit open_studio_tab den passenden Bereich (".implode(', ',assistant_tabs_available()).').';
    $s.="\n\nKONTO-ÜBERBLICK (live)\n".assistant_overview_text($ov);
    $pb=function_exists('ch_profile_brief') ? ch_profile_brief($acc) : ''; if($pb!=='') $s.="\nProfil aus der Registrierung (vom Inhaber angegeben, nicht erneut erfragen): ".$pb;
    $s.="\n\nWAS DU SCHON WEISST (Gedächtnis)\n".($mem ? '- '.implode("\n- ",array_map(fn($m)=>$m['topic'].': '.$m['fact'],array_slice($mem,0,80))) : '- noch nichts – lerne den Betrieb kennen.');
    $s.="\n\nCHECKLISTE (".$progress." % erledigt)";
    foreach($items as $i) $s.="\n[".($i['done'] ? 'x' : ' ').'] '.$i['label'].($i['done'] ? '' : ' – '.$i['hint']);
    $next=null; foreach($items as $i) if(!$i['done']){ $next=$i; break; }
    $s.="\nNächster offener Punkt: ".($next ? $next['label'] : 'alles erledigt – hilf beim Feinschliff (mehr FAQ, bessere Texte, Testgespräch).');
    if(!$toolsReady) $s.="\n\nWICHTIG – WERKZEUGE GERADE NICHT VERFÜGBAR: In diesem Gespräch kannst du nichts speichern, merken oder einstellen (keine Werkzeuge). Behaupte NIE, etwas gespeichert, eingetragen, gemerkt oder geändert zu haben. Hilf beim Formulieren und Planen, sag offen, dass das automatische Einrichten gerade nicht verfügbar ist, und nenne den Studio-Bereich, in dem der Inhaber es selbst eintragen kann.";
    $s.="\n\nJetzt (Europe/Berlin): ".assistant_now_text().'.';
    return $s;
}

/* ======================================================================= Werkzeuge (nur Studio, nur Inhaber) */
function assistant_tools(): array {
    $plan=['type'=>'string','description'=>'Agent-ID, z. B. agent-termine, agent-bestellung, agent-phone. Leer = Haupt-Agent des Kontos.'];
    $hoursDesc='Öffnungszeiten je Wochentag: Objekt mit Schlüsseln "0" (Sonntag) bis "6" (Samstag), Wert = Liste von [von,bis] im Format HH:MM, z. B. {"1":[["09:00","18:00"]],"6":[["09:00","14:00"]],"0":[]}. Alle 7 Tage angeben; fehlende Tage gelten als geschlossen.';
    return [
        ['name'=>'get_overview','description'=>'Aktueller Stand des Kontos: Agenten, Wissensbasis, Module (Termine, Bestellungen, Taxi, Hotel, Rückrufe, Training), Guthaben und Checkliste. Nutzen, bevor du etwas behauptest, das sich geändert haben könnte.','input_schema'=>['type'=>'object']],
        ['name'=>'remember','description'=>'Einen dauerhaften Fakt über den Betrieb oder die Wünsche des Inhabers merken (z. B. Branche, Zielgruppe, Öffnungszeiten, Besonderheiten).','input_schema'=>['type'=>'object','properties'=>[
            'topic'=>['type'=>'string','description'=>'Kurzes Thema, max. 60 Zeichen, z. B. „Branche“'],'fact'=>['type'=>'string','description'=>'Der Fakt in einem Satz, max. 500 Zeichen'],
            'replace'=>['type'=>'boolean','description'=>'true = ersetzt ältere Fakten zum selben Thema']],'required'=>['topic','fact']]],
        ['name'=>'save_business_profile','description'=>'Betriebsprofil in den Agenten übernehmen (Firmenname + Abschnitt „Betriebsprofil“ in der Wissensbasis; der Rest der Wissensbasis bleibt). Nur nach Bestätigung.','input_schema'=>['type'=>'object','properties'=>[
            'company'=>['type'=>'string','description'=>'Name des Betriebs'],'address'=>['type'=>'string'],'phone'=>['type'=>'string'],'hours_text'=>['type'=>'string','description'=>'Öffnungszeiten als Text, z. B. „Mo–Fr 9–18 Uhr, Sa 9–14 Uhr“'],
            'description'=>['type'=>'string','description'=>'Was der Betrieb macht, Besonderheiten, Zielgruppe (2–5 Sätze)'],'plan'=>$plan],'required'=>['company']]],
        ['name'=>'update_agent_knowledge','description'=>'Einen Abschnitt der Wissensbasis schreiben (z. B. „Leistungen & Preise“, „Anfahrt & Parken“, „Regeln“). Nur nach Bestätigung.','input_schema'=>['type'=>'object','properties'=>[
            'plan'=>$plan,'section'=>['type'=>'string','description'=>'Überschrift des Abschnitts'],'text'=>['type'=>'string','description'=>'Inhalt, sachlich, mit allen Fakten'],
            'mode'=>['type'=>'string','enum'=>['append','replace_section'],'description'=>'append = anhängen, replace_section = Abschnitt ersetzen']],'required'=>['section','text']]],
        ['name'=>'add_faq','description'=>'Eine verbindliche FAQ-Antwort für den Agenten speichern (gleiche Frage wird aktualisiert). Mehrere auf einmal über items. Nur nach Bestätigung.','input_schema'=>['type'=>'object','properties'=>[
            'question'=>['type'=>'string'],'answer'=>['type'=>'string'],'plan'=>$plan,
            'items'=>['type'=>'array','description'=>'Weitere Fragen/Antworten (max. 10)','items'=>['type'=>'object','properties'=>['question'=>['type'=>'string'],'answer'=>['type'=>'string']],'required'=>['question','answer']]]],'required'=>['question','answer']]],
        ['name'=>'set_agent_tone','description'=>'Tonalität und Sprache eines Agenten festlegen. Nur nach Bestätigung.','input_schema'=>['type'=>'object','properties'=>[
            'plan'=>$plan,'tone'=>['type'=>'string','enum'=>ASSISTANT_AGENT_TONES],'lang'=>['type'=>'string','enum'=>ASSISTANT_LANGS]],'required'=>['tone']]],
        ['name'=>'configure_appointments','description'=>'Terminbuchung einrichten: Leistungen (Name, Dauer, Preis), Öffnungszeiten, Online-Buchung einschalten. Nur nach Bestätigung.','input_schema'=>['type'=>'object','properties'=>[
            'services'=>['type'=>'array','items'=>['type'=>'object','properties'=>['name'=>['type'=>'string'],'duration_min'=>['type'=>'integer'],'price_eur'=>['type'=>'number']],'required'=>['name']]],
            'mode'=>['type'=>'string','enum'=>['append','replace'],'description'=>'append = ergänzen/aktualisieren (Standard), replace = Liste ersetzen'],
            'hours'=>['type'=>'object','description'=>$hoursDesc],'enable'=>['type'=>'boolean','description'=>'Online-Terminbuchung einschalten']]]],
        ['name'=>'configure_menu','description'=>'Speisekarte für die Bestellannahme anlegen oder ergänzen. Optionen-Format: „Größe: Normal 0 | Groß +3,00; Extra: Käse +1,50“. Nur nach Bestätigung.','input_schema'=>['type'=>'object','properties'=>[
            'items'=>['type'=>'array','items'=>['type'=>'object','properties'=>['category'=>['type'=>'string'],'name'=>['type'=>'string'],'price_eur'=>['type'=>'number'],'description'=>['type'=>'string'],'options'=>['type'=>'string']],'required'=>['name','price_eur']]],
            'mode'=>['type'=>'string','enum'=>['append','replace']],'enable'=>['type'=>'boolean','description'=>'Bestellannahme einschalten']],'required'=>['items']]],
        ['name'=>'configure_taxi','description'=>'Grunddaten der Taxi-Zentrale (Name, Telefon, Adresse, Wartezeit, Einsatzgebiet) setzen und einschalten. Nur nach Bestätigung.','input_schema'=>['type'=>'object','properties'=>[
            'name'=>['type'=>'string'],'phone'=>['type'=>'string'],'address'=>['type'=>'string'],'eta_min'=>['type'=>'integer','description'=>'übliche Wartezeit in Minuten'],'area_km'=>['type'=>'integer','description'=>'Einsatzradius km'],'enable'=>['type'=>'boolean']]]],
        ['name'=>'configure_hotel','description'=>'Grunddaten der Hotel-Rezeption (Name, Adresse, Telefon, Check-in/-out, Hausregeln) setzen und einschalten. Nur nach Bestätigung.','input_schema'=>['type'=>'object','properties'=>[
            'name'=>['type'=>'string'],'address'=>['type'=>'string'],'phone'=>['type'=>'string'],'checkin_from'=>['type'=>'string','description'=>'HH:MM'],'checkout_until'=>['type'=>'string','description'=>'HH:MM'],'policies'=>['type'=>'string'],'enable'=>['type'=>'boolean']]]],
        ['name'=>'configure_callbacks','description'=>'Rückrufliste einstellen: ein/aus, Benachrichtigung, KI-Rückruf, Sofortrückruf für Leads, Anrufzeiten. Nur nach Bestätigung.','input_schema'=>['type'=>'object','properties'=>[
            'enable'=>['type'=>'boolean'],'notify_email'=>['type'=>'string'],'ai_callback'=>['type'=>'boolean'],'lead_instant'=>['type'=>'boolean'],'call_window'=>['type'=>'object','description'=>$hoursDesc]]]],
        ['name'=>'recommend_agent','description'=>'Passende Agenten (Plan, Preis, Mietlink) für eine Branche und die Wünsche des Inhabers ermitteln.','input_schema'=>['type'=>'object','properties'=>[
            'sector'=>['type'=>'string','description'=>'Branche, z. B. „Pizzeria“, „Friseursalon“, „Steuerkanzlei“'],'needs'=>['type'=>'string','description'=>'Wünsche, z. B. „Telefon annehmen, Termine buchen“']],'required'=>['sector']]],
        ['name'=>'open_studio_tab','description'=>'Einen Bereich des Studios für den Inhaber öffnen.','input_schema'=>['type'=>'object','properties'=>['tab'=>['type'=>'string','enum'=>assistant_tabs_available()]],'required'=>['tab']]],
    ];
}
/** Öffnungszeiten aus der KI (Schlüssel 0–6 oder Tagesnamen, [von,bis] / {from,to} / "9-18") → {"0":[["HH:MM","HH:MM"]],…}. */
function assistant_hours_norm($raw): ?array {
    if(!is_array($raw)) return null;
    $names=['so'=>0,'sonntag'=>0,'sun'=>0,'sunday'=>0,'mo'=>1,'montag'=>1,'mon'=>1,'monday'=>1,'di'=>2,'dienstag'=>2,'tue'=>2,'tuesday'=>2,'mi'=>3,'mittwoch'=>3,'wed'=>3,'wednesday'=>3,
        'do'=>4,'donnerstag'=>4,'thu'=>4,'thursday'=>4,'fr'=>5,'freitag'=>5,'fri'=>5,'friday'=>5,'sa'=>6,'samstag'=>6,'sat'=>6,'saturday'=>6];
    $hm=function($t){ if(!is_scalar($t)) return null; $t=trim(str_replace('.',':',preg_replace('/\s*uhr$/iu','',trim((string)$t)) ?? '')); if(preg_match('/^(\d{1,2})(?::(\d{2}))?$/',$t,$m) && ((int)$m[1]<24 || ((int)$m[1]===24 && (int)($m[2] ?? 0)===0)) && (int)($m[2] ?? 0)<60) return sprintf('%02d:%02d',(int)$m[1],(int)($m[2] ?? 0)); return null; };
    $out=['0'=>[],'1'=>[],'2'=>[],'3'=>[],'4'=>[],'5'=>[],'6'=>[]]; $any=false;
    foreach($raw as $k=>$list){
        $kk=mb_strtolower(trim((string)$k)); $w=ctype_digit($kk) ? (int)$kk : ($names[rtrim($kk,'.')] ?? null); if($w===null || $w<0 || $w>6) continue; $any=true;
        if(is_string($list)) $list=preg_match('/^\s*(geschlossen|closed|zu|ruhetag|frei|-|–)?\s*$/iu',$list) ? [] : array_map(fn($x)=>preg_split('/\s*[–-]\s*/u',trim($x)),explode(',',$list));
        foreach((array)$list as $iv){ if(is_string($iv)) $iv=preg_split('/\s*[–-]\s*/u',trim($iv)); if(!is_array($iv)) return null;
            $a=$hm($iv['from'] ?? ($iv[0] ?? '')); $b=$hm($iv['to'] ?? ($iv[1] ?? ''));
            if($a===null || $b===null || $a==='24:00') return null;   // ungültige Uhrzeit → Fehler an die KI statt stillschweigend „geschlossen“
            $out[(string)$w][]=[$a,$b]; }
    }
    return $any ? $out : null;
}
/** Vergleichsform für Öffnungszeiten (Schlüsselreihenfolge egal). */
function assistant_hours_sig($h): string { $o=[]; for($w=0;$w<=6;$w++) $o[]=json_encode(array_values((array)(is_array($h) ? ($h[(string)$w] ?? ($h[$w] ?? [])) : []))); return implode('|',$o); }
/** Öffnungszeiten kompakt für Menschen: „Di–Fr 9–19 Uhr · Sa 9–15 Uhr · Mo, So geschlossen“ (gleiche Folgetage zusammengefasst). */
function assistant_hours_text(array $h): string {
    $wd=['So','Mo','Di','Mi','Do','Fr','Sa']; $t=fn(string $x)=>preg_replace('/^0(\d)/','$1',str_ends_with($x,':00') ? substr($x,0,-3) : $x);
    $sig=[]; foreach([1,2,3,4,5,6,0] as $w){ $iv=(array)($h[(string)$w] ?? ($h[$w] ?? [])); $sig[$w]=$iv ? implode(', ',array_map(fn($x)=>$t((string)$x[0]).'–'.$t((string)$x[1]),$iv)).' Uhr' : ''; }
    $runs=[]; foreach([1,2,3,4,5,6,0] as $w){ $n=count($runs); if($n && $sig[$runs[$n-1][0]]===$sig[$w]) $runs[$n-1][]=$w; else $runs[]=[$w]; }
    $lbl=fn(array $r)=>count($r)===1 ? $wd[$r[0]] : $wd[$r[0]].'–'.$wd[end($r)];
    $open=[]; $closed=[];
    foreach($runs as $r){ if($sig[$r[0]]==='') $closed[]=$lbl($r); else $open[]=$lbl($r).' '.$sig[$r[0]]; }
    if($closed) $open[]=implode(', ',$closed).' geschlossen';
    return implode(' · ',$open);
}
/** Ergebnis eines Werkzeugs als Aktions-Chip fürs Panel. */
function assistant_action(string $name, array $r): array {
    $ok=empty($r['error']);
    $link=null; if($ok && !empty($r['agents'][0]['checkout_url']) && empty($r['agents'][0]['active'])) $link=['url'=>(string)$r['agents'][0]['checkout_url'],'label'=>'Mieten · '.(string)$r['agents'][0]['price']];
    return ['name'=>$name,'ok'=>$ok,'summary'=>mb_substr((string)($ok ? ($r['summary'] ?? 'Erledigt.') : ($r['message'] ?? $r['hint'] ?? 'Das hat nicht geklappt.')),0,300),
        'tab'=>isset($r['tab']) && assistant_has_tab((string)$r['tab']) ? (string)$r['tab'] : null,'plan'=>$r['plan'] ?? null,'open'=>!empty($r['open']),'link'=>$link];
}
/**
 * Werkzeug ausführen. Nur Kanal 'studio' (bzw. 'selftest'): der angemeldete Inhaber selbst. Fehler als ['error','hint'] (nie Exceptions für Nutzerfehler).
 */
function assistant_exec(int $acc, string $name, array $in, string $channel='studio', string $from=''): array {
    if(!in_array($channel,['studio','selftest'],true)) return ['error'=>'owner_only','hint'=>'Dieses Werkzeug steht nur dem Inhaber im Studio zur Verfügung.'];
    if($acc<=0) return ['error'=>'demo_readonly','hint'=>'In der Demo wird nichts gespeichert.'];
    assistant_bootstrap();
    try{
        switch($name){
            case 'get_overview':
                $ov=assistant_overview($acc); [$items,$pg]=assistant_checklist($acc,$ov);
                return ['overview'=>assistant_overview_text($ov),'checklist'=>array_map(fn($i)=>['key'=>$i['key'],'label'=>$i['label'],'done'=>$i['done'],'hint'=>$i['hint']],$items),'progress'=>$pg,'summary'=>'Überblick geladen'];
            case 'remember':
                $topic=assistant_line($in['topic'] ?? '',60); $fact=assistant_line($in['fact'] ?? '',500);   // assistant_line: Arrays/Objekte → '' (kein „Array“)
                $r=assistant_remember($acc,$topic,$fact,'chat',assistant_truthy($in['replace'] ?? null)===true);
                if(isset($r['error'])) return $r;
                return ['ok'=>true,'id'=>$r['id'],'new'=>$r['new'],'summary'=>'Gemerkt: '.$topic.' – '.mb_substr($fact,0,160)];
            case 'save_business_profile': return assistant_tool_profile($acc,$in);
            case 'update_agent_knowledge': return assistant_tool_knowledge($acc,$in);
            case 'add_faq': return assistant_tool_faq($acc,$in);
            case 'set_agent_tone': return assistant_tool_tone($acc,$in);
            case 'configure_appointments': return assistant_tool_appt($acc,$in);
            case 'configure_menu': return assistant_tool_menu($acc,$in);
            case 'configure_taxi': return assistant_tool_taxi($acc,$in);
            case 'configure_hotel': return assistant_tool_hotel($acc,$in);
            case 'configure_callbacks': return assistant_tool_callbacks($acc,$in);
            case 'recommend_agent': return assistant_tool_recommend($acc,$in);
            case 'open_studio_tab':
                $t=assistant_str($in['tab'] ?? '',20);
                if(!in_array($t,assistant_tabs_available(),true)) return ['error'=>'unknown_tab','hint'=>'Diesen Bereich gibt es nicht. Möglich: '.implode(', ',assistant_tabs_available())];
                return ['ok'=>true,'open'=>true,'tab'=>$t,'summary'=>'Bereich „'.ASSISTANT_TABS[$t].'“ geöffnet'];
        }
    }catch(Throwable $e){ error_log('CH assistant tool '.$name.': '.$e->getMessage()); return ['error'=>'internal','hint'=>'Technischer Fehler beim Speichern – bitte dem Inhaber sagen, dass es gerade nicht geklappt hat.']; }
    return ['error'=>'unknown_tool','hint'=>'Dieses Werkzeug gibt es nicht.'];
}
function assistant_tool_profile(int $acc, array $in): array {
    [$plan,$e]=assistant_resolve_plan($acc,$in['plan'] ?? ''); if($e) return $e;
    $company=assistant_line($in['company'] ?? '',120); if($company==='') return ['error'=>'company_required','hint'=>'Bitte zuerst nach dem Namen des Betriebs fragen.'];
    $f=['Unternehmen'=>$company,'Adresse'=>assistant_line($in['address'] ?? '',200),'Telefon'=>assistant_line($in['phone'] ?? '',60),'Öffnungszeiten'=>assistant_line($in['hours_text'] ?? '',300),'Über uns'=>trim(assistant_kb_text(assistant_str($in['description'] ?? '',1500)))];
    $text=implode("\n",array_map(fn($k,$v)=>$k.': '.$v,array_keys(array_filter($f,fn($v)=>$v!=='')),array_filter($f,fn($v)=>$v!=='')));
    $cur=th_agent_cfg($acc,$plan); $kb=assistant_kb_section_set((string)$cur['kb'],'Betriebsprofil',$text,'replace',true);
    if(mb_strlen($kb)>60000) return ['error'=>'kb_full','hint'=>'Die Wissensbasis ist voll (60.000 Zeichen). Bitte im Training kürzen.'];
    $d=['company'=>$company,'kb'=>$kb]; if($f['Öffnungszeiten']!=='' && trim((string)$cur['phone_hours'])==='') $d['phone_hours']=mb_substr($f['Öffnungszeiten'],0,160);
    assistant_cfg_save($acc,$plan,$d);
    $parts=array_keys(array_filter(['Firma'=>1,'Adresse'=>$f['Adresse'],'Telefon'=>$f['Telefon'],'Öffnungszeiten'=>$f['Öffnungszeiten'],'Beschreibung'=>$f['Über uns']]));
    $synced=assistant_sync_modules($acc,$company,$f['Adresse'],$f['Telefon']);
    assistant_remember($acc,'Betriebsprofil',$company.($f['Adresse']!=='' ? ', '.$f['Adresse'] : '').($f['Telefon']!=='' ? ', Tel. '.$f['Telefon'] : ''),'tool',true);
    return ['ok'=>true,'plan'=>$plan,'tab'=>'agents','synced'=>$synced,'summary'=>'Betriebsprofil „'.$company.'“ im '.assistant_plan_name($plan).' gespeichert ('.implode(', ',$parts).')'.($synced ? ' · auch in '.implode(', ',$synced).' eingetragen' : '')];
}
/**
 * Firmenname/Adresse/Telefon in eingeschaltete Fachmodule übernehmen – nur leere Felder, nie überschreiben.
 * Ausgeschaltete Module bleiben unberührt (kein ungewolltes Anlegen). Adressen nur dort, wo kein Geocoding nötig ist (Termine, Hotel).
 */
function assistant_sync_modules(int $acc, string $company, string $address, string $phone): array {
    $done=[];
    try{ $a=appt_settings($acc); if((int)$a['appt_on']===1){ $d=[]; if(trim((string)$a['company'])==='') $d['company']=$company; if($address!=='' && trim((string)$a['address'])==='') $d['address']=$address;
        if($d){ $e=null; appt_settings_save($acc,$d,$e); if(!$e) $done[]='Termine'; } } }catch(Throwable $e){}
    try{ $o=ord_settings($acc); if((int)$o['order_on']===1){ $d=[]; if(trim((string)$o['name'])==='') $d['name']=$company; if($phone!=='' && trim((string)$o['phone'])==='') $d['phone']=$phone;
        if($d){ ord_settings_save($acc,$d); $done[]='Bestellungen'; } } }catch(Throwable $e){}
    if(function_exists('ride_settings_save')) try{ $r=ride_settings($acc,true); if((int)$r['ride_on']===1){ $d=[]; if(trim((string)$r['name'])==='') $d['name']=$company; if($phone!=='' && trim((string)$r['phone'])==='') $d['phone']=$phone;
        if($d){ ride_settings_save($acc,$d); $done[]='Taxi'; } } }catch(Throwable $e){}
    if(function_exists('hotel_settings_save')) try{ $h=hotel_settings($acc); if((int)$h['hotel_on']===1){ $d=[]; if(trim((string)$h['name'])==='') $d['name']=$company; if($address!=='' && trim((string)$h['address'])==='') $d['address']=$address; if($phone!=='' && trim((string)$h['phone'])==='') $d['phone']=$phone;
        if($d){ hotel_settings_save($acc,$d); $done[]='Hotel'; } } }catch(Throwable $e){}
    return $done;
}
function assistant_tool_knowledge(int $acc, array $in): array {
    [$plan,$e]=assistant_resolve_plan($acc,$in['plan'] ?? ''); if($e) return $e;
    $sec=assistant_section_title(assistant_str($in['section'] ?? '')); $text=trim(assistant_kb_text(assistant_str($in['text'] ?? '',8000)));
    if($sec==='' || $text==='') return ['error'=>'section_and_text_required','hint'=>'Bitte Überschrift und Inhalt angeben.'];
    $mode=($in['mode'] ?? 'append')==='replace_section' ? 'replace' : 'append';
    $cur=th_agent_cfg($acc,$plan); $kb=assistant_kb_section_set((string)$cur['kb'],$sec,$text,$mode,false);
    if(mb_strlen($kb)>60000) return ['error'=>'kb_full','hint'=>'Die Wissensbasis wäre zu groß (max. 60.000 Zeichen). Kürzer fassen oder einen Abschnitt ersetzen.'];
    assistant_cfg_save($acc,$plan,['kb'=>$kb]);
    return ['ok'=>true,'plan'=>$plan,'tab'=>assistant_has_tab('training') ? 'training' : 'agents','kb_chars'=>mb_strlen($kb),
        'summary'=>'Wissensbasis: Abschnitt „'.$sec.'“ '.($mode==='replace' ? 'ersetzt' : 'ergänzt').' ('.assistant_plan_name($plan).')'];
}
function assistant_tool_faq(int $acc, array $in): array {
    [$plan,$e]=assistant_resolve_plan($acc,$in['plan'] ?? ''); if($e) return $e;
    $list=[['question'=>$in['question'] ?? '','answer'=>$in['answer'] ?? '']];
    foreach(array_slice(is_array($in['items'] ?? null) ? $in['items'] : [],0,10) as $it) if(is_array($it)) $list[]=['question'=>$it['question'] ?? '','answer'=>$it['answer'] ?? ''];
    $new=[]; foreach($list as $it){ $q=assistant_line($it['question'],500); $a=assistant_str($it['answer'],2000); if($q!=='' && $a!=='') $new[]=['question'=>$q,'answer'=>$a]; }
    if(!$new) return ['error'=>'faq_required','hint'=>'Bitte Frage und Antwort angeben.'];
    $norm=function_exists('train_norm_q') ? fn($s)=>train_norm_q((string)$s) : fn($s)=>trim(preg_replace('/\s+/u',' ',preg_replace('/[^\p{L}\p{N}]+/u',' ',mb_strtolower((string)$s)) ?? '') ?? '');
    if(function_exists('train_faq_save') && function_exists('train_faq')){
        $items=array_map(fn($f)=>['id'=>$f['id'],'question'=>$f['question'],'answer'=>$f['answer'],'source'=>$f['source'],'active'=>$f['active']],train_faq($acc,$plan)); $upd=0; $add=0;
        foreach($new as $n){ $hit=false; foreach($items as &$it){ if($norm($it['question'])===$norm($n['question'])){ $it['answer']=$n['answer']; $it['active']=1; $hit=true; $upd++; break; } } unset($it);
            if(!$hit){ $items[]=['question'=>$n['question'],'answer'=>$n['answer'],'source'=>'manual','active'=>1]; $add++; } }
        $r=train_faq_save($acc,$plan,$items,[]);   // $known=[]: der Assistent löscht nie bestehende Einträge
        if(isset($r['error'])) return ['error'=>$r['error'],'hint'=>$r['message'] ?? 'FAQ konnte nicht gespeichert werden.'];
        $total=count(array_filter($r['items'] ?? [],fn($f)=>(int)$f['active']===1));
        return ['ok'=>true,'plan'=>$plan,'tab'=>assistant_has_tab('training') ? 'training' : 'agents','faq_total'=>$total,
            'summary'=>'FAQ: '.($add ? $add.' neu' : '').($add && $upd ? ', ' : '').($upd ? $upd.' aktualisiert' : '').' – „'.mb_substr($new[0]['question'],0,70).'“ ('.$total.' insgesamt)'];
    }
    $cur=th_agent_cfg($acc,$plan); $kb=(string)$cur['kb']; $sec=assistant_kb_section_get($kb,'Häufige Fragen') ?? '';
    $blocks=$sec==='' ? [] : preg_split('/\n\s*\n/u',$sec);
    foreach($new as $n){ $line="F: ".$n['question']."\nA: ".preg_replace('/\s*\n\s*/u',' ',$n['answer']); $hit=false;
        foreach($blocks as &$b){ if(preg_match('/^F:\s*(.+)$/mu',$b,$m) && $norm($m[1])===$norm($n['question'])){ $b=$line; $hit=true; break; } } unset($b);
        if(!$hit) $blocks[]=$line; }
    $kb=assistant_kb_section_set($kb,'Häufige Fragen',implode("\n\n",array_filter(array_map('trim',$blocks))),'replace');
    if(mb_strlen($kb)>60000) return ['error'=>'kb_full','hint'=>'Die Wissensbasis ist voll (60.000 Zeichen).'];
    assistant_cfg_save($acc,$plan,['kb'=>$kb]);
    $total=preg_match_all('/^F:\s*\S/mu',(string)assistant_kb_section_get($kb,'Häufige Fragen'));
    return ['ok'=>true,'plan'=>$plan,'tab'=>'agents','faq_total'=>$total,'summary'=>'FAQ gespeichert – „'.mb_substr($new[0]['question'],0,70).'“ ('.$total.' insgesamt)'];
}
function assistant_tool_tone(int $acc, array $in): array {
    [$plan,$e]=assistant_resolve_plan($acc,$in['plan'] ?? ''); if($e) return $e;
    $tone=assistant_str($in['tone'] ?? ''); if(!in_array($tone,ASSISTANT_AGENT_TONES,true)) return ['error'=>'tone_invalid','hint'=>'Erlaubt: '.implode(', ',ASSISTANT_AGENT_TONES)];
    $d=['tone'=>$tone]; $lang=strtolower(assistant_str($in['lang'] ?? '')); if($lang==='nb') $lang='no';
    if($lang!==''){ if(!in_array($lang,ASSISTANT_LANGS,true)) return ['error'=>'lang_invalid','hint'=>'Erlaubt: '.implode(', ',ASSISTANT_LANGS)]; $d['lang']=$lang; }
    assistant_cfg_save($acc,$plan,$d);
    $lname=assistant_lang_name((string)($d['lang'] ?? th_agent_cfg($acc,$plan)['lang']));
    assistant_remember($acc,'Ton & Sprache',assistant_plan_name($plan).': '.$tone.', '.$lname,'tool',true);
    return ['ok'=>true,'plan'=>$plan,'tab'=>'agents','summary'=>'Ton „'.$tone.'“, Sprache '.$lname.' für '.assistant_plan_name($plan)];
}
function assistant_tool_appt(int $acc, array $in): array {
    if(!function_exists('appt_settings_save')) return ['error'=>'module_unavailable','hint'=>'Die Terminbuchung ist hier nicht verfügbar.'];
    $svcIn=is_array($in['services'] ?? null) ? $in['services'] : []; $hours=isset($in['hours']) ? assistant_hours_norm($in['hours']) : null; $en=assistant_truthy($in['enable'] ?? null);
    if(isset($in['hours']) && $hours===null) return ['error'=>'hours_invalid','hint'=>'Öffnungszeiten bitte als {"1":[["09:00","18:00"]],…} (0 = Sonntag) angeben.'];
    if(!$svcIn && $hours===null && $en===null) return ['error'=>'nothing_to_do','hint'=>'Bitte Leistungen, Öffnungszeiten oder enable angeben.'];
    $sum=[];
    if($svcIn){
        $clean=[]; foreach(array_slice($svcIn,0,40) as $s){ if(!is_array($s)) continue; $n=assistant_line($s['name'] ?? '',120); if($n==='') continue;
            $dur=isset($s['duration_min']) && is_numeric($s['duration_min']) && (int)$s['duration_min']>0 ? max(5,min(600,(int)$s['duration_min'])) : null; $pc=assistant_cents($s['price_eur'] ?? ($s['price'] ?? null));
            $clean[mb_strtolower($n)]=['name'=>$n,'duration_min'=>$dur,'price_cents'=>$pc]; }
        if(!$clean) return ['error'=>'services_invalid','hint'=>'Jede Leistung braucht einen Namen.'];
        $replace=($in['mode'] ?? 'append')==='replace'; $list=[]; $seen=[];
        foreach(appt_services($acc,false) as $ex){ $k=mb_strtolower($ex['name']);
            if(isset($clean[$k])){ $c=$clean[$k]; $clean[$k]['duration_min']=$c['duration_min'] ?? $ex['duration_min']; $clean[$k]['price_cents']=$c['price_cents'] ?? $ex['price_cents'];
                $list[]=['id'=>$ex['id'],'name'=>$c['name'],'duration_min'=>$clean[$k]['duration_min'],'price_cents'=>$clean[$k]['price_cents'],'staff'=>$ex['staff'],'active'=>1]; $seen[$k]=1; }
            elseif(!$replace) $list[]=$ex; }
        foreach($clean as $k=>$c) if(!isset($seen[$k])){ $clean[$k]['duration_min']=$c['duration_min'] ?? 30; $list[]=['name'=>$c['name'],'duration_min'=>$clean[$k]['duration_min'],'price_cents'=>$c['price_cents'] ?? 0,'active'=>1]; }
        appt_services_save($acc,$list);
        $sum[]=count($clean).' Leistung'.(count($clean)===1 ? '' : 'en').' ('.implode(', ',array_map(fn($c)=>$c['name'].' '.$c['duration_min'].' Min'.($c['price_cents']!==null ? ' · '.assistant_eur($c['price_cents']) : ''),array_slice(array_values($clean),0,4))).(count($clean)>4 ? ' …' : '').')';
    }
    $d=[]; if($hours!==null) $d['hours']=$hours; if($en!==null) $d['appt_on']=$en ? 1 : 0;
    $cur=appt_settings($acc); if(trim((string)$cur['company'])===''){ $mc=th_agent_cfg($acc,assistant_main_plan($acc))['company'] ?? ''; if($mc!=='') $d['company']=$mc; }
    if($d){ $err=null; $st=appt_settings_save($acc,$d,$err); if($err) return ['error'=>$err['error'] ?? 'hours_invalid','hint'=>$err['message'] ?? 'Öffnungszeiten ungültig.'];
        if($hours!==null){ $sum[]='Öffnungszeiten '.assistant_hours_text($st['hours']); assistant_remember($acc,'Öffnungszeiten',assistant_hours_text($st['hours']),'tool',true); }
        if($en!==null) $sum[]='Online-Buchung '.($en ? 'eingeschaltet' : 'ausgeschaltet'); }
    return ['ok'=>true,'tab'=>'termine','services'=>count(appt_services($acc,true)),'summary'=>'Termine: '.implode(' · ',$sum)];
}
function assistant_tool_menu(int $acc, array $in): array {
    if(!function_exists('ord_menu_save')) return ['error'=>'module_unavailable','hint'=>'Die Bestellannahme ist hier nicht verfügbar.'];
    $itemsIn=is_array($in['items'] ?? null) ? $in['items'] : []; $clean=[]; $skipped=[];
    foreach(array_slice($itemsIn,0,120) as $it){ if(!is_array($it)){ $skipped[]='(ungültiger Eintrag)'; continue; }
        $n=assistant_line($it['name'] ?? '',120); $pc=assistant_cents($it['price_eur'] ?? ($it['price'] ?? null));
        if($n===''){ $skipped[]='(ohne Namen)'; continue; }
        if($pc===null){ $skipped[]=$n; continue; }   // Preis fehlt, ist keine Zahl oder negativ
        $clean[mb_strtolower($n)]=['category'=>assistant_line($it['category'] ?? '',60),'name'=>$n,'description'=>assistant_line($it['description'] ?? '',300),'price_cents'=>$pc,'options'=>is_string($it['options'] ?? null) ? assistant_line($it['options'],600) : (is_array($it['options'] ?? null) ? $it['options'] : [])]; }
    if(!$clean) return ['error'=>'items_invalid','skipped'=>array_slice($skipped,0,20),'hint'=>'Nichts gespeichert: Jeder Artikel braucht einen Namen und einen gültigen Preis in Euro.'.($skipped ? ' Betroffen: '.implode(', ',array_slice($skipped,0,10)).'. Bitte den Inhaber nach den richtigen Preisen fragen.' : '')];
    $replace=($in['mode'] ?? 'append')==='replace'; $list=[]; $seen=[];
    foreach(ord_menu($acc,false) as $ex){ $k=mb_strtolower($ex['name']);
        if(isset($clean[$k])){ $c=$clean[$k]; $list[]=['id'=>$ex['id'],'category'=>$c['category'] ?: $ex['category'],'name'=>$c['name'],'description'=>$c['description'] ?: $ex['description'],'price_cents'=>$c['price_cents'],'options'=>$c['options'] ?: $ex['options'],'tags'=>$ex['tags'],'active'=>1]; $seen[$k]=1; }
        elseif(!$replace) $list[]=$ex; }
    foreach($clean as $k=>$c) if(!isset($seen[$k])) $list[]=$c+['active'=>1];
    ord_menu_save($acc,$list); $sum=[count($clean).' Artikel '.($replace ? 'als neue Karte' : 'ergänzt').' ('.implode(', ',array_map(fn($c)=>$c['name'].' '.assistant_eur($c['price_cents']),array_slice(array_values($clean),0,4))).(count($clean)>4 ? ' …' : '').')'];
    $en=assistant_truthy($in['enable'] ?? null); $d=[];
    if($en!==null) $d['order_on']=$en ? 1 : 0;
    $cur=ord_settings($acc); if(trim((string)$cur['name'])===''){ $mc=th_agent_cfg($acc,assistant_main_plan($acc))['company'] ?? ''; if($mc!=='') $d['name']=$mc; }
    if($d){ ord_settings_save($acc,$d); if($en!==null) $sum[]='Bestellannahme '.($en ? 'eingeschaltet' : 'ausgeschaltet'); }
    $total=count(ord_menu($acc,true)); $sum[]=$total.' Artikel auf der Karte';
    if($skipped) $sum[]='nicht übernommen (Preis fehlt/ungültig): '.implode(', ',array_slice($skipped,0,6)).(count($skipped)>6 ? ' …' : '');
    $r=['ok'=>true,'tab'=>'bestellungen','items'=>$total,'summary'=>'Speisekarte: '.implode(' · ',$sum)];
    if($skipped){ $r['skipped']=array_slice($skipped,0,20); $r['hint']='Diese Artikel wurden NICHT übernommen, weil der Preis fehlt oder ungültig ist: '.implode(', ',array_slice($skipped,0,10)).'. Sag das dem Inhaber und frag nach den richtigen Preisen.'; }
    return $r;
}
/** Nur übergebene Grundfelder übernehmen. */
function assistant_pick(array $in, array $keys, int $max=200): array { $o=[]; foreach($keys as $k) if(array_key_exists($k,$in) && is_scalar($in[$k]) && trim((string)$in[$k])!=='') $o[$k]=mb_substr(trim((string)$in[$k]),0,$max); return $o; }
function assistant_tool_taxi(int $acc, array $in): array {
    if(!function_exists('ride_settings_save')) return ['error'=>'module_unavailable','hint'=>'Das Taxi-Modul ist hier nicht verfügbar.'];
    $d=assistant_pick($in,['name','phone','address']); foreach(['eta_min','area_km'] as $k) if(isset($in[$k]) && is_numeric($in[$k])) $d[$k]=(int)$in[$k];
    $en=assistant_truthy($in['enable'] ?? null); if($en!==null) $d['ride_on']=$en ? 1 : 0;
    if(!$d) return ['error'=>'nothing_to_do','hint'=>'Bitte mindestens ein Feld angeben.'];
    $s=ride_settings_save($acc,$d);
    $sum=array_filter(['Name'=>isset($d['name']) ? $s['name'] : null,'Telefon'=>isset($d['phone']) ? $s['phone'] : null,'Adresse'=>isset($d['address']) ? $s['address'] : null,'Wartezeit'=>isset($d['eta_min']) ? $s['eta_min'].' Min' : null,'Gebiet'=>isset($d['area_km']) ? $s['area_km'].' km' : null]);
    return ['ok'=>true,'tab'=>'taxi','summary'=>'Taxi: '.implode(' · ',array_map(fn($k,$v)=>$k.' '.$v,array_keys($sum),$sum)).($en!==null ? ($sum ? ' · ' : '').'Fahrtannahme '.($en ? 'eingeschaltet' : 'ausgeschaltet') : '')];
}
function assistant_tool_hotel(int $acc, array $in): array {
    if(!function_exists('hotel_settings_save')) return ['error'=>'module_unavailable','hint'=>'Das Hotel-Modul ist hier nicht verfügbar.'];
    $d=assistant_pick($in,['name','address','phone','checkin_from','checkout_until']); if(isset($in['policies']) && is_string($in['policies']) && trim($in['policies'])!=='') $d['policies']=mb_substr(trim($in['policies']),0,4000);
    $en=assistant_truthy($in['enable'] ?? null); if($en!==null) $d['hotel_on']=$en ? 1 : 0;
    if(!$d) return ['error'=>'nothing_to_do','hint'=>'Bitte mindestens ein Feld angeben.'];
    $s=hotel_settings_save($acc,$d);
    $sum=array_filter(['Name'=>isset($d['name']) ? $s['name'] : null,'Adresse'=>isset($d['address']) ? $s['address'] : null,'Telefon'=>isset($d['phone']) ? $s['phone'] : null,'Check-in ab'=>isset($d['checkin_from']) ? $s['checkin_from'] : null,'Check-out bis'=>isset($d['checkout_until']) ? $s['checkout_until'] : null,'Hausregeln'=>isset($d['policies']) ? 'gespeichert' : null]);
    return ['ok'=>true,'tab'=>'hotel','summary'=>'Hotel: '.implode(' · ',array_map(fn($k,$v)=>$k.' '.$v,array_keys($sum),$sum)).($en!==null ? ($sum ? ' · ' : '').'Rezeption '.($en ? 'eingeschaltet' : 'ausgeschaltet') : '')];
}
function assistant_tool_callbacks(int $acc, array $in): array {
    if(!function_exists('cb_settings_save')) return ['error'=>'module_unavailable','hint'=>'Das Rückruf-Modul ist hier nicht verfügbar.'];
    $d=[]; $sum=[];
    foreach(['enable'=>'cb_on','ai_callback'=>'ai_callback','lead_instant'=>'lead_instant'] as $k=>$col){ $b=assistant_truthy($in[$k] ?? null); if($b!==null){ $d[$col]=$b ? 1 : 0; $sum[]=['cb_on'=>'Rückrufliste','ai_callback'=>'KI-Rückruf','lead_instant'=>'Sofortrückruf für Leads'][$col].' '.($b ? 'an' : 'aus'); } }
    if(isset($in['notify_email']) && is_string($in['notify_email']) && trim($in['notify_email'])!==''){ if(!filter_var(trim($in['notify_email']),FILTER_VALIDATE_EMAIL)) return ['error'=>'email_invalid','hint'=>'Bitte eine gültige E-Mail-Adresse nennen lassen.']; $d['notify_email']=trim($in['notify_email']); $sum[]='Benachrichtigung an '.$d['notify_email']; }
    if(isset($in['call_window'])){ $w=assistant_hours_norm($in['call_window']); if($w===null) return ['error'=>'hours_invalid','hint'=>'Anrufzeiten bitte als {"1":[["09:00","18:00"]],…} angeben.']; $d['call_window']=$w; $sum[]='Anrufzeiten '.assistant_hours_text($w); }
    if(!$d) return ['error'=>'nothing_to_do','hint'=>'Bitte mindestens eine Einstellung angeben.'];
    cb_settings_save($acc,$d);
    return ['ok'=>true,'tab'=>'rueckruf','summary'=>'Rückrufe: '.implode(' · ',$sum)];
}
function assistant_tool_recommend(int $acc, array $in): array {
    $sector=assistant_line($in['sector'] ?? '',120); $needs=assistant_line($in['needs'] ?? '',300);
    $ids=assistant_sector_plans($sector) ?: assistant_sector_plans($needs);   // Branche zuerst – „Lieferung“ in den Wünschen macht noch keine Pizzeria
    if(preg_match('/telefon|anruf|anrufe|erreichbar/iu',$needs) && !in_array('agent-phone',$ids,true)) $ids[]='agent-phone';
    if(preg_match('/whatsapp|website|webseite|chat|online/iu',$needs) && !$ids) $ids[]='agent-support';
    if(!$ids) $ids=['agent-phone','agent-support'];
    $buyOf=array_flip(CH_PLAN_BASE); $out=[];
    foreach(array_slice(array_values(array_unique($ids)),0,3) as $id){ $pl=ch_plan($id); if(!$pl) continue;
        $r=['id'=>$id,'name'=>$pl['name'],'price'=>assistant_eur($pl['cents']).'/Monat','tokens_month'=>$pl['tokens'],'desc'=>$pl['desc'],'active'=>ch_plan_active_any($acc,$id),
            'checkout_url'=>'https://chat-help.com/chat/tokens-checkout.php?plan='.rawurlencode($id)];
        if(isset($buyOf[$id]) && ($b=ch_plan($buyOf[$id]))) $r['lifetime']=['id'=>$b['id'],'price'=>assistant_eur($b['cents']).' einmalig','checkout_url'=>'https://chat-help.com/chat/tokens-checkout.php?plan='.rawurlencode($b['id'])];
        $out[]=$r; }
    return ['ok'=>true,'agents'=>$out,'tab'=>'agents','summary'=>'Empfehlung: '.implode(', ',array_map(fn($a)=>$a['name'].' ('.$a['price'].')',$out))];
}

/* ======================================================================= Gespräch */
/** Lokaler Test-Haken (nur Kommandozeile / php -S, nur wenn ein Testskript assistant_test_ai definiert). */
function assistant_test_hook(): bool { return in_array(PHP_SAPI,['cli','cli-server'],true) && function_exists('assistant_test_ai'); }
/** Werkzeuge gibt es nur mit Claude: ohne CLAUDE_KEY fällt th_ai_tools auf th_ai (z. B. DeepSeek) ohne Werkzeuge zurück. */
function assistant_tools_ready(): bool { return assistant_test_hook() || (defined('CLAUDE_KEY') && (string)CLAUDE_KEY!==''); }
function assistant_last_message(int $acc): ?array {
    $s=th_db()->prepare("SELECT id,role,content FROM assistant_messages WHERE account_id=? ORDER BY id DESC LIMIT 1"); $s->execute([$acc]); $r=$s->fetch(PDO::FETCH_ASSOC); return $r ?: null;
}
/** Hat der Assistent zuletzt etwas gefragt? Sprachneutral: Fragezeichen (?, ？, ؟) im Schluss der Nachricht. */
function assistant_asked(string $t): bool { $t=trim($t); return $t!=='' && (bool)preg_match('/[?？؟]/u',mb_substr($t,-160)); }
/** Kurze, klare Zustimmung am Anfang der Nachricht – in allen 13 Sprachen (ASSISTANT_YES / ASSISTANT_YES_JA) oder 👍 ✅ 👌. */
function assistant_affirmative(string $m): bool {
    $m=trim(mb_strtolower($m)); if($m==='' || mb_strlen($m)>240) return false;
    if(preg_match('/^(\x{1F44D}|\x{1F44C}|\x{2705}|\x{2714}|\x{1F197})/u',$m)) return true;
    $m=trim((string)preg_replace('/^[\s„“"\'«»(\-–—…]+/u','',$m));
    foreach(ASSISTANT_YES_JA as $w) if(str_starts_with($m,$w)) return true;
    foreach(ASSISTANT_YES as $w){
        if(!str_starts_with($m,$w)) continue; $rest=mb_substr($m,mb_strlen($w));
        if($rest==='') return true;
        if($w==='si'){ if(preg_match('/^[,.!;:]/u',$rest)) return true; continue; }   // „si“ ohne Akzent heißt auch „wenn“
        if(preg_match('/^[\s,.!;:)\-–—…]/u',$rest)) return true;
    }
    return false;
}
/** Verlaufseinträge fürs Panel; Assistenten-Nachrichten mit Sprache und vorlesbaren Sätzen (dieselben, die /speak vorliest). */
function assistant_history_public(array $hist, array $pr): array {
    $du=$pr['personality']['address']!=='sie';
    return array_map(function($m) use($pr,$du){
        $o=['id'=>$m['id'],'role'=>$m['role'],'content'=>$m['content'],'actions'=>$m['actions'],'greeting'=>$m['greeting'],'created_at'=>$m['created_at'],'lang'=>$m['lang']];
        if($m['role']==='assistant'){ $o['lang']=$m['lang'] ?? assistant_detect_lang((string)$m['content'],$pr['lang']); $o['speech']=assistant_speech((string)$m['content'],$o['lang'],$du); }
        return $o; },$hist);
}
/**
 * Eine Nachricht des Inhabers beantworten. Rückgabe [status, json]. Abrechnung nur, wenn die KI geantwortet hat:
 * 6 Tokens + 2 je ausgeführtem Werkzeug (th_charge, höchstens Restguthaben).
 * Werkzeuge, die etwas ändern (ASSISTANT_CHANGING_TOOLS), laufen nur nach Bestätigung: letzte Assistenten-Nachricht war eine Frage
 * UND die neue Nachricht ist eine kurze, klare Zustimmung – sonst bekommt die KI confirm_first und muss erst fragen.
 */
function assistant_chat(int $acc, string $message, bool $voice=false): array {
    assistant_bootstrap(); $message=trim(str_replace("\r",'',$message));
    $pr=assistant_profile($acc); $sie=$pr['personality']['address']==='sie'; $S=fn(string $du,string $s)=>$sie ? $s : $du;
    if($message==='') return [400,['error'=>'message_required','message'=>'Bitte eine Nachricht eingeben.']];
    if(mb_strlen($message)>ASSISTANT_MSG_MAX) return [400,['error'=>'message_too_long','message'=>'Die Nachricht ist zu lang (höchstens 2.000 Zeichen).']];
    $bal=th_balance($acc);
    if($bal<ASSISTANT_COST_TURN) return [402,['error'=>'insufficient_tokens','need'=>ASSISTANT_COST_TURN,'balance'=>$bal,'buy'=>'/#preise','message'=>$S('Dein','Ihr').' Guthaben reicht nicht für eine Antwort ('.ASSISTANT_COST_TURN.' Tokens). Bitte Tokens nachkaufen.']];
    if(!th_rate('as:chat:'.$acc,150,3600)) return [429,['error'=>'rate_limited','message'=>'Sehr viele Nachrichten in kurzer Zeit – bitte einen Moment warten.']];
    if(!th_lock('as:'.$acc,8)) return [429,['error'=>'busy','message'=>'Ich beantworte gerade noch '.$S('deine','Ihre').' letzte Nachricht – einen Moment bitte.']];
    try{
        $t0=microtime(true);
        assistant_ensure_greeting($acc,$pr);
        $prev=assistant_last_message($acc);
        $confirmed=$prev && $prev['role']==='assistant' && assistant_asked((string)$prev['content']) && assistant_affirmative($message);
        $uid=assistant_add_message($acc,'user',$message,['lang'=>assistant_detect_lang($message,$pr['lang'])]);
        $ov=assistant_overview($acc); $mem=assistant_memory($acc,120); [$items,$pg]=assistant_checklist($acc,$ov,$mem);
        $toolsReady=assistant_tools_ready();
        $sys=assistant_system($acc,$pr,$ov,$items,$pg,$mem,$voice,$toolsReady);
        $msgs=assistant_ai_messages($acc); $tools=$toolsReady ? assistant_tools() : []; $actions=[]; $calls=0; $tries=0;
        $exec=function(string $n, array $i) use($acc,$confirmed,&$actions,&$calls,&$tries): array {
            if(++$tries>12) return ['error'=>'too_many_tools','hint'=>'Genug Werkzeuge für diese Antwort – fasse zusammen und frag nach.'];
            foreach(array_keys($i) as $k) if($k==='force' || $k==='account_id' || str_starts_with((string)$k,'_')) unset($i[$k]);
            if(in_array($n,ASSISTANT_CHANGING_TOOLS,true) && !$confirmed)
                return ['error'=>'confirm_first','hint'=>'NICHT ausgeführt – der Inhaber hat diese Änderung noch nicht bestätigt. Fasse in einem Satz zusammen, was du eintragen willst, und frag in seiner Sprache: „Soll ich das so übernehmen?“. Erst nach seinem klaren Ja in der nächsten Nachricht ausführen. Behaupte nicht, es sei schon gespeichert.'];
            $calls++;
            $r=assistant_exec($acc,$n,$i,'studio');
            if($n!=='get_overview') $actions[]=assistant_action($n,$r);
            return $r;
        };
        $reply=assistant_test_hook() ? assistant_test_ai($sys,$msgs,$tools,$exec) : th_ai_tools($sys,$msgs,$tools,$exec,$voice ? 500 : 900,60,6);
        $reply=is_string($reply) ? trim($reply) : '';
        if($reply===''){
            if(!$actions) th_db()->prepare("DELETE FROM assistant_messages WHERE id=? AND account_id=?")->execute([$uid,$acc]);
            else assistant_add_message($acc,'assistant','Ich habe Folgendes erledigt, aber meine Antwort ist unterwegs verloren gegangen: '.implode('; ',array_map(fn($a)=>$a['summary'],$actions)).'.',['actions'=>$actions,'lang'=>'de']);
            return [503,['error'=>'ai_unavailable','message'=>'Ich bin gerade nicht erreichbar – bitte '.$S('versuch es','versuchen Sie es').' in einem Moment noch einmal. Es wurden keine Tokens berechnet.','actions'=>$actions]];
        }
        $lang=assistant_detect_lang($reply,$pr['lang']);
        $mid=assistant_add_message($acc,'assistant',$reply,($actions ? ['actions'=>$actions] : [])+['lang'=>$lang]);
        $cost=ASSISTANT_COST_TURN+ASSISTANT_COST_TOOL*$calls;
        $charged=th_charge($acc,$cost,'assistant:chat'); th_log_usage($acc,'assistant','assistant',$charged,(int)((microtime(true)-$t0)*1000));
        $ov=assistant_overview($acc); $mem=assistant_memory($acc,100); [$items,$pg]=assistant_checklist($acc,$ov,$mem);
        return [200,['reply'=>$reply,'id'=>$mid,'lang'=>$lang,'speech'=>assistant_speech($reply,$lang,!$sie),'actions'=>$actions,'checklist'=>$items,'progress'=>$pg,
            'memory'=>array_map(fn($m)=>['id'=>$m['id'],'topic'=>$m['topic'],'fact'=>$m['fact']],$mem),'suggestions'=>assistant_suggestions($items,$mem),
            'tools_ready'=>$toolsReady,'tokens_used'=>$charged,'balance'=>th_balance($acc)]];
    } finally { th_unlock('as:'.$acc); }
}
/** Zustand fürs Panel: Verlauf, Gedächtnis, Checkliste, Vorschläge, Guthaben. Legt beim ersten Besuch die Begrüßung an. */
function assistant_state(int $acc): array {
    $pr=assistant_profile($acc); assistant_ensure_greeting($acc,$pr);
    $ov=assistant_overview($acc); $mem=assistant_memory($acc,100); [$items,$pg]=assistant_checklist($acc,$ov,$mem);
    return ['history'=>assistant_history_public(assistant_history($acc,30),$pr),'memory'=>array_map(fn($m)=>['id'=>$m['id'],'topic'=>$m['topic'],'fact'=>$m['fact']],$mem),'checklist'=>$items,'progress'=>$pg,
        'suggestions'=>assistant_suggestions($items,$mem),'balance'=>$ov['balance'],'cost'=>['turn'=>ASSISTANT_COST_TURN,'tool'=>ASSISTANT_COST_TOOL],'tools_ready'=>assistant_tools_ready()];
}
/**
 * Was /speak vorlesen darf: nur Sätze aus den letzten 60 Nachrichten des eigenen Verlaufs ({mid,i}) oder die vom Server gebaute Hörprobe ({preview}).
 * Rückgabe [text, lang, voice-override] oder [null, Fehler-Antwort].
 */
function assistant_speak_source(int $aid, array $pr, array $body): array {
    $sie=$pr['personality']['address']==='sie';
    if(isset($body['preview'])){
        $pv=is_array($body['preview']) ? $body['preview'] : [];
        $lang=assistant_lang(is_string($pv['lang'] ?? null) ? $pv['lang'] : $pr['lang']);
        $name=trim((string)preg_replace('/\s+/u',' ',assistant_str($pv['name'] ?? '')));
        if($name==='' || mb_strlen($name)>30 || !preg_match('/^\p{L}[\p{L}\p{M} \-]*$/u',$name)) $name=$pr['name'];
        $g=in_array($pv['gender'] ?? '',['f','m'],true) ? $pv['gender'] : $pr['gender'];
        $psie=in_array($pv['address'] ?? '',['du','sie'],true) ? $pv['address']==='sie' : $sie;
        $over=array_intersect_key($pv,array_flip(['gender','tone','rate','pitch','preset']));
        return [assistant_preview_text($lang,$name,$psie,$g!=='m'),$lang,$over];
    }
    $mid=is_numeric($body['mid'] ?? null) ? (int)$body['mid'] : 0; $i=is_numeric($body['i'] ?? null) ? (int)$body['i'] : -1;
    if($mid<=0 || $i<0) return [null,[400,['error'=>'sentence_required','message'=>'Kein Satz zum Vorlesen.']],null];
    $p=th_db();
    $q=$p->prepare("SELECT id FROM assistant_messages WHERE account_id=? ORDER BY id DESC LIMIT 1 OFFSET 59"); $q->execute([$aid]); $floor=(int)$q->fetchColumn();
    $s=$p->prepare("SELECT content,meta FROM assistant_messages WHERE id=? AND account_id=? AND role='assistant'"); $s->execute([$mid,$aid]); $r=$s->fetch(PDO::FETCH_ASSOC);
    if(!$r || $mid<=$floor) return [null,[404,['error'=>'not_found','message'=>'Diese Nachricht gibt es nicht (mehr).']],null];
    $m=json_decode((string)($r['meta'] ?? ''),true);
    $lang=is_array($m) && is_string($m['lang'] ?? null) && isset(ASSISTANT_LANG_TABLE[$m['lang']]) ? $m['lang'] : assistant_detect_lang((string)$r['content'],$pr['lang']);
    $list=assistant_speech((string)$r['content'],$lang,!$sie);
    if(!isset($list[$i])) return [null,[404,['error'=>'not_found','message'=>'Diesen Satz gibt es nicht.']],null];
    return [$list[$i],$lang,[]];
}
/** MP3 direkt ausliefern (nur Web); in der Kommandozeile (Tests) als JSON-Beschreibung zurück. */
function assistant_emit_audio(string $mp3): array {
    if(PHP_SAPI==='cli') return [200,['audio'=>true,'content_type'=>'audio/mpeg','bytes'=>strlen($mp3)]];
    th_nocache(); http_response_code(200); header('Content-Type: audio/mpeg'); header('Content-Length: '.strlen($mp3)); echo $mp3; exit;
}

/* ======================================================================= Studio-REST */
function assistant_api(string $route, string $method, int $aid, array $body, array $account): ?array {
    if($route!=='assistant' && !str_starts_with($route,'assistant/')) return null;
    if($aid<=0) return [403,['error'=>'forbidden']];
    assistant_bootstrap();
    /* tts_active: Server-Stimme eingerichtet UND nutzbar (Guthaben oder aktiver Agent, wie bei /speak) – für die ehrliche Statuszeile im Panel */
    $pub=fn()=>['profile'=>assistant_profile_public(assistant_profile($aid)),'tts_active'=>assistant_tts_ready() && (th_balance($aid)>0 || (bool)assistant_active_plans($aid)),'portraits'=>array_map(fn($p)=>['id'=>$p['id'],'url'=>$p['url'],'gender'=>$p['gender'],'label'=>$p['label']],assistant_portraits()),
        'voices'=>assistant_voice_presets_public(),'langs'=>assistant_langs_public(),'tts'=>assistant_tts_ready(),'tts_langs'=>assistant_tts_langs(),'tools_ready'=>assistant_tools_ready(),'photo_max'=>ASSISTANT_PHOTO_MAX];
    switch(true){
        case $route==='assistant/profile' && $method==='GET': return [200,$pub()];
        case $route==='assistant/profile' && $method==='POST':
            $err=null; assistant_profile_save($aid,$body,$err); if($err) return [422,$err];
            return [200,['ok'=>true]+$pub()];
        case $route==='assistant/state' && $method==='GET': return [200,assistant_state($aid)];
        case $route==='assistant/chat' && $method==='POST':
            return assistant_chat($aid,assistant_str($body['message'] ?? ''),assistant_truthy($body['voice'] ?? null)===true);
        case $route==='assistant/speak' && $method==='POST':
            $pr=assistant_profile($aid); $sie=$pr['personality']['address']==='sie';
            if(!assistant_tts_ready()) return [501,['error'=>'tts_unavailable','message'=>'Server-Stimme nicht eingerichtet – das Studio nutzt die Stimme '.($sie ? 'Ihres' : 'deines').' Browsers.']];
            /* Keine kostenlose Vorlese-Schnittstelle: nur mit Guthaben oder aktivem Agenten */
            if(th_balance($aid)<=0 && !assistant_active_plans($aid)) return [402,['error'=>'insufficient_tokens','message'=>'Die Studio-Stimme braucht Guthaben oder einen aktiven Agenten – bis dahin liest die Browserstimme vor.']];
            [$text,$lang,$over]=assistant_speak_source($aid,$pr,$body);
            if($text===null) return $lang;
            if(!assistant_tts_ready($lang)) return [501,['error'=>'tts_lang_unavailable','lang'=>$lang,'message'=>'Die Studio-Stimme spricht '.assistant_lang_name($lang).' noch nicht – dafür liest die Browserstimme vor.']];
            if(!th_rate('as:tts:'.$aid,300,86400)) return [429,['error'=>'rate_limited','message'=>'Tageslimit für die Sprachausgabe erreicht – ab jetzt spricht die Browserstimme.']];
            $mp3=null;
            try{ $mp3=ch_tts_synth($text,assistant_voice($pr,is_array($over) ? $over : [],$lang)); }catch(Throwable $e){ error_log('CH assistant tts: '.$e->getMessage()); }
            if(!is_string($mp3) || $mp3==='') return [502,['error'=>'tts_failed','message'=>'Die Stimme konnte gerade nicht erzeugt werden.']];
            return assistant_emit_audio($mp3);
        case $route==='assistant/reset' && $method==='POST':
            th_db()->prepare("DELETE FROM assistant_messages WHERE account_id=?")->execute([$aid]);
            return [200,['ok'=>true]+assistant_state($aid)];
        case $route==='assistant/forget' && $method==='POST':
            $ok=assistant_forget($aid,is_numeric($body['id'] ?? null) ? (int)$body['id'] : 0);
            if(!$ok) return [404,['error'=>'not_found','message'=>'Diesen Eintrag gibt es nicht (mehr).']];
            $ov=assistant_overview($aid); $mem=assistant_memory($aid,100); [$items,$pg]=assistant_checklist($aid,$ov,$mem);
            return [200,['ok'=>true,'memory'=>array_map(fn($m)=>['id'=>$m['id'],'topic'=>$m['topic'],'fact'=>$m['fact']],$mem),'checklist'=>$items,'progress'=>$pg,'suggestions'=>assistant_suggestions($items,$mem)]];
    }
    return [404,['error'=>'not_found','routes'=>['GET|POST /v1/assistant/profile','GET /v1/assistant/state','POST /v1/assistant/chat','POST /v1/assistant/speak','POST /v1/assistant/reset','POST /v1/assistant/forget']]];
}
