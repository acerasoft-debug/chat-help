export const meta = {
  name: 'chathelp-4sorun-analiz',
  description: 'Foto okuma, mikrofon, abonelik tanıma ve TR vize belge sorunlarını yerel kaynaklardan paralel analiz et',
  whenToUse: 'ChatHelp canlı sitesinde bir/birden çok alanda sorun bildirildiğinde: 4 alan uzmanı (foto, mikrofon, abonelik, TR vize) chat/ altındaki apply-*.php kaynaklarını paralel analiz eder; kök neden hipotezleri + canlı kodda aranacak kesin anchorlar + düzeltme taslağı döner. args.ek verilirse her uzmana güncel şikayet metni olarak iletilir.',
  phases: [
    { title: 'Analiz', detail: '4 alan uzmanı paralel kaynak analizi' },
  ],
}
const EK = (typeof args === 'object' && args && args.ek) ? ('\n\nGUNCEL SIKAYET (kullanicidan, oncelikle bunu acikla): ' + args.ek) : ''
phase('Analiz')
const SCHEMA = {
  type: 'object',
  required: ['summary','hypotheses','needles','fix_outline'],
  properties: {
    summary: { type: 'string', description: 'Alanın mevcut deploy edilmiş mimarisinin 5-8 cümlelik özeti' },
    hypotheses: { type: 'array', items: { type: 'object', required: ['cause','confidence','evidence'], properties: {
      cause: { type: 'string' }, confidence: { type: 'string', enum: ['high','medium','low'] }, evidence: { type: 'string' } } } },
    needles: { type: 'array', description: 'Canlı index.php/api.php içinde aranacak KESİN string anchorlar (dump scripti için)', items: { type: 'object', required: ['label','needle','purpose'], properties: {
      label: { type: 'string' }, needle: { type: 'string' }, purpose: { type: 'string' } } } },
    fix_outline: { type: 'string', description: 'Önerilen düzeltmenin somut taslağı (hangi mekanizma, hangi anchor, ek riskler)' }
  }
}
const COMMON = `Depo: /home/user/chat-help/chat/ — bu dosyalar ChatHelp canlı sitesine (chat-help.com) deploy edilmiş yamaların KAYNAKLARIDIR. apply-*.php dosyaları canlı index.php/auth.php'ye eklenen kod bloklarını içerir (heredoc HTMLBLOCK içinde), dump-*.php dosyaları geçmiş teşhislerdir. Canlı siteye doğrudan erişimin YOK; analizin bu kaynaklara dayanacak. Görevin: alanının deploy edilmiş mimarisini kaynaklardan çıkar, kullanıcının bildirdiği sorunun olası kök nedenlerini sırala (kanıtla), canlı kodda aranacak KESİN string anchorları öner (dump için), ve somut düzeltme taslağı ver. Kod parçalarını memoryden uydurma — mutlaka dosyaları Read/Grep ile doğrula. Yanıtın SADECE StructuredOutput ile.`
const results = await parallel([
  () => agent(COMMON + `
ALAN: FOTO OKUMASI (chat'te fotoğraf gönderince analiz/çeviri gelmiyor veya dilekçede kullanılmıyor — kullanıcı "foto okumasında sorun var" dedi).
İncele: chat/apply-photo-backend.php, apply-photo2.php, apply-photo-upload.php, apply-photo-lang.php, apply-fotoread-fix.php, apply-foto-gate.php, dump-fotoread.php, dump-photoflow*.php, dump-photo-all.php. 
Özellikle: analyze_photo action'ının backend sözleşmesi (api.php'ye ne eklendi, hangi parametreler: b64/mime/lang), frontend akışı (dosya seçimi → fetch → cevabın sohbete/dilekçeye aktarımı), bilinen geçmiş kırılmalar (görev #84 bağlam kaybı, #90 çeviri gelmiyor — bunların yamaları hangileri, sonraki yamalar bunları ezmiş olabilir mi). Foto akışını KIRABİLECEK bugünkü yeni yamaları da düşün: bugün fetch'i saran 3 katman eklendi (CH_VIZE_PRUF profil ekleyici, CH_VIZE_FORMS yer tutucu değiştirici, CH_VIZE_RESULT yanıt klonlayıcı) — bunlar api.php?action=aichat'i sarıyor ama analyze_photo'ya dokunuyor mu kontrol et (apply-vize-pruef.php, apply-vize-forms.php, apply-vize-result.php dosyalarındaki fetch wrapper kodunu oku).` + EK, { label: 'analiz:foto', schema: SCHEMA, effort: 'high' }),
  () => agent(COMMON + `
ALAN: MİKROFON (kullanıcı "mikrofonda sorun var" dedi — hem sitede hem App'te geçmişte sorunlar oldu; bugün CH_MIC_UNI (doğrudan SpeechRecognition→input), CH_MIC_APPKB (App'te sesli yazım paneli) deploy edildi ama kullanıcı hâlâ sorun bildiriyor).
İncele: chat/apply-mic-unified.php, apply-mic-app-panel.php, apply-mic-wv-direct.php, apply-mic-app-timeout.php, apply-mic-smart2.php, apply-mic-pro2.php, apply-mickb-fix.php, dump-mic-pipeline.php.
Özellikle: startVoice/chOpenMic sarmalama ZİNCİRİ — bugünkü katmanların yükleme sırası ve guard döngüleri birbiriyle çakışıyor olabilir mi (CH_MIC_UNI'nin 20sn guard'ı unified'ı geri yüklerken CH_MIC_APPKB'nin 60sn guard'ı kendi wrapper'ını geri yüklüyor — ikisi __final bayrağını nasıl kullanıyor, sonsuz yarış var mı, hangisi kazanır?). Sitede SR'nin çalışmama olasılıkları (dil kodu, tarayıcı, izin). Bir 'görünür iz' (on-screen debug trace) tasarla: butona basınca hangi yol çalıştı + SR olayları (onstart/onerror kodu/onend) toast dizisiyle gösterilsin — bunun için hangi fonksiyonlar sarılmalı, öneri ver.` + EK, { label: 'analiz:mikrofon', schema: SCHEMA, effort: 'high' }),
  () => agent(COMMON + `
ALAN: ABONELİK TANIMA (kullanıcı "abonelik tanımalarında sorun var" dedi — muhtemel belirti: ödeme yapmış/plan atanmış kullanıcı sohbette/kotada free gibi davranılıyor veya plan rozetleri yanlış).
İncele: chat/apply-plan-quota.php, apply-plans-fix.php, apply-konto-plans.php, apply-freedoc-be.php, apply-plan-page2.php, apply-devplan-fix.php, dump-plan-values.php, dump-plans3.php, dump-freegate.php, plan-check.php, apply-admin-users.php (set_plan INSERT'i), apply-admin-block-ip.php.
Özellikle: (1) canlı api.php/auth.php'de plan çözümleme sorgusu nasıl (hangi yamayla eklendi: user_plans'tan hangi ORDER/status/expires filtresiyle seçiliyor); (2) bilinen DB durumu: user_plans'ta AYNI kullanıcıya BİRDEN FAZLA satır var (#1'de 2x elite, #11'de basic+pro — bugünkü dump kanıtı) ve tabloda UNIQUE(user_id) yok — çözümleme 'ORDER BY p.id DESC LIMIT 1' ise #11 pro alır ama farklı bir sorgu status/expires_at NULL yüzünden hiç satır bulamayabilir; (3) free kota kapısı (doFreeStatus/doFreeUse, email+IP) premium kullanıcıya yanlış uygulanıyor olabilir mi — gating kodu hangi sırayla plan kontrol ediyor. Canlı api.php/auth.php'de aranacak kesin needle'lar ver (ör. "FROM user_plans", "doFreeUse", "expires_at").` + EK, { label: 'analiz:abonelik', schema: SCHEMA, effort: 'high' }),
  () => agent(COMMON + `
ALAN: TÜRKİYE VİZE BELGE YAPIMI (kullanıcı "türkiye vize bölümü belge yapımlarında sorun var" dedi. Bilinen: TR Vize Modülü'nün mektup belgeleri sabit İngilizce kalıba HAM Türkçe yanıt yapıştırıyor; bugün CH_TRVIZE_AI adlı DOM son-işlemcisi deploy edildi — .vf-letter bloklarını AI ile tam İngilizceye çeviriyor. Kullanıcı hâlâ sorun bildiriyor).
İncele: chat/apply-trvize-ai.php (bugünkü düzeltme), apply-visa-requirements*.php varsa, ve şu dump çıktısı bilgisini kullan: TR modülünün builder'ında if(key==="cover"){...pageWrap('<div class=vfh>...'+esc(body)+'</div>')} deseni var, sponsor için de benzer; ayrıca modülde form-tipi belgeler var (F("f36a","36. Yer / Place") gibi iki dilli resmi form alanları). Grep ile chat/ altında 'vf-letter', 'pageWrap', 'vfA4', 'buildPages', 'genDoc', 'renderDoc' geçen apply dosyalarını bul ve viewer mimarisini çıkar: belge ayrı pencerede mi (window.open) yoksa ana DOM'da mı render ediliyor — CH_TRVIZE_AI'nin document.querySelectorAll('.vf-letter') çağrısı belgeye ULAŞABİLİYOR MU? Ulaşamıyorsa (ayrı pencere/iframe) düzeltme hiç tetiklenmez — bu en kritik hipotez. Ayrıca hangi belge anahtarları mektup tipi (cover/sponsor başka?), hangileri form tipi — hepsinin listesi için canlı kodda aranacak needle'lar ver.` + EK, { label: 'analiz:trvize', schema: SCHEMA, effort: 'high' }),
])
return { foto: results[0], mikrofon: results[1], abonelik: results[2], trvize: results[3] }