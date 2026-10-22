<?php
/** ChatHelp — apply-tr-expand-more (CH_TR_EXPAND_MORE) — TR katalog genisletme
 *  Workflow ile uretildi: +18 belge, 4 kategori. Deferred injection.
 *  KULLANIM: pull-updates.php?files=apply-tr-expand-more.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-tr-expand-more BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_TR_EXPAND_MORE')!==false) exit("Zaten ekli (CH_TR_EXPAND_MORE).\n");
$anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if (strpos($src,$anchor)===false) exit("HATA: anchor (getDocPrice) yok.\n");
$js = <<<'CHJS'

/* CH_TR_EXPAND_MORE — TR katalog genisletme (workflow, deferred) */
try{(function(){
  function Pe(k,l,r,o){var x={k:k,l:l,r:!!r};if(o)x.opts=o;return x;}
  var NEWCATS={
    "trx_miras":{n:"Miras ve Veraset Hukuku",ic:"⚖️"},
    "trx_kira":{n:"Kira Hukuku",ic:"🔑"},
    "trx_kat_mulkiyeti":{n:"Apartman ve Kat Mülkiyeti Yönetimi",ic:"🏢"},
    "trx_is_tuketici":{n:"İş İlişkileri, Tüketici ve Esnaflık",ic:"💼"}
  };
  var E={
    "trx_miras_reddi_dilekce":{ic:"📜",n:"Mirasın Reddi Dilekçesi",cat:"trx_miras",tier:"komplex",q:[Pe("mirasci_ad","Mirasçının adı soyadı",true),Pe("mirasci_tckn","Mirasçının TC kimlik numarası",true),Pe("muris_ad","Murisin (miras bırakanın) adı soyadı",true),Pe("muris_vefat_tarihi","Murisin vefat tarihi",true),Pe("red_nedeni","Mirası ret nedeni",false,["Miras borca batık","Kişisel tercih","Diğer"]),Pe("sure_kontrolu","Mirasın reddi hakkının doğduğunu öğrenme tarihinden bu yana yasal 3 aylık süre geçti mi?",true,["Hayır, süre içindeyim","Evet, süre geçti (mahkemeden ek süre talep edilmesi gerekebilir)"]),Pe("mahkeme","Başvurulacak Sulh Hukuk Mahkemesi (yerleşim yeri)",true)]},
    "trx_mirastan_feragat_sozlesmesi":{ic:"🤝",n:"Mirastan Feragat Sözleşmesi",cat:"trx_miras",tier:"standard",q:[Pe("muris_ad","Miras bırakanın adı soyadı",true),Pe("feragat_eden_ad","Feragat eden mirasçının adı soyadı",true),Pe("karsilik_var_mi","Feragat karşılığında bir bedel/mal verilecek mi?",true,["Evet, karşılıklı feragat","Hayır, karşılıksız feragat"]),Pe("karsilik_detay","Karşılık olarak verilecek bedel/mal",false),Pe("noter_bilgisi","Sözleşmenin düzenleneceği noterlik ve tarih (bu sözleşme yasal olarak resmi vasiyetname şeklinde noterde düzenlenmelidir)",true),Pe("feragat_kapsami","Feragatin kapsamı",true,["Tüm miras hakkından feragat","Sadece saklı paya ilişkin kısmi feragat"])]},
    "trx_veraset_ilami_talep":{ic:"📋",n:"Veraset İlamı (Mirasçılık Belgesi) Talep Dilekçesi",cat:"trx_miras",tier:"standard",q:[Pe("talep_eden_ad","Talepte bulunan mirasçının adı soyadı",true),Pe("muris_bilgi","Murisin (miras bırakanın) adı soyadı ve TC kimlik numarası",true),Pe("vefat_bilgi","Murisin vefat tarihi ve yeri",true),Pe("diger_mirasci","Bilinen diğer mirasçılar (varsa)",false),Pe("basvuru_mercii","Başvuru mercii",true,["Sulh Hukuk Mahkemesi","Noter"])]},
    "trx_miras_taksim_sozlesmesi":{ic:"📑",n:"Miras Paylaşma (Taksim) Sözleşmesi",cat:"trx_miras",tier:"standard",q:[Pe("muris_bilgi","Miras bırakanın adı soyadı ve vefat tarihi",true),Pe("mirasci_listesi","Mirasçıların adı soyadı listesi",true),Pe("paylasilacak_mal","Paylaşılacak mal varlığı (taşınmaz, araç, banka hesabı vb.)",true),Pe("paylasim_sekli","Paylaşımın nasıl yapılacağı (kime ne düşecek)",true),Pe("denklestirme","Mirasçılar arasında denkleştirme (ivaz) ödemesi yapılacak mı?",false,["Evet","Hayır"])]},
    "trx_vasiyetname_el_yazili":{ic:"✍️",n:"El Yazılı Vasiyetname",cat:"trx_miras",tier:"standard",q:[Pe("vasiyetci_bilgi","Vasiyetçinin adı soyadı, doğum tarihi ve TC kimlik numarası",true),Pe("vasiyetci_adres","Vasiyetçinin yerleşim yeri adresi",true),Pe("lehtar_bilgi","Mirasçı/lehtar olarak belirlenecek kişiler ve payları",true),Pe("ozel_mal_atama","Belirli bir mal varlığının kime bırakılacağı (varsa)",false),Pe("duzenleme_bilgi","Vasiyetnamenin düzenlendiği tarih ve yer (tamamı vasiyetçinin el yazısıyla yazılmalıdır)",true),Pe("ozel_husus","Vasiyet edilecek özel bir husus (mirastan çıkarma vb.)",false)]},
    "trx_kira_fesih_ihtar":{ic:"⚠️",n:"Kiracıya Tahliye İhtarnamesi (Kira Borcu Nedeniyle)",cat:"trx_kira",tier:"standard",q:[Pe("kiraya_veren","Kiraya verenin adı soyadı/unvanı",true),Pe("kiraci","Kiracının adı soyadı/unvanı",true),Pe("tasinmaz_adres","Kiralanan taşınmazın adresi",true),Pe("borc_tutar","Ödenmeyen kira bedeli tutarı ve ait olduğu dönem",true),Pe("odeme_suresi","İhtarname ile tanınacak ödeme süresi",true,["30 gün","Diğer süre"]),Pe("gonderim_yontemi","İhtarnamenin gönderim yöntemi",false,["Noter","İadeli taahhütlü posta"])]},
    "trx_kira_artis_bildirimi":{ic:"📈",n:"Kira Bedeli Artış Bildirimi",cat:"trx_kira",tier:"einfach",q:[Pe("kiraya_veren","Kiraya verenin adı soyadı",true),Pe("kiraci","Kiracının adı soyadı",true),Pe("tasinmaz_adres","Kiralanan taşınmazın adresi",true),Pe("mevcut_bedel","Mevcut kira bedeli",true),Pe("yeni_bedel","Yeni kira bedeli ve artış oranı",true),Pe("gecerlilik_tarihi","Artışın geçerli olacağı tarih",true)]},
    "trx_kira_tahliye_taahhutnamesi":{ic:"🔑",n:"Tahliye Taahhütnamesi",cat:"trx_kira",tier:"standard",q:[Pe("kiraci","Kiracının adı soyadı",true),Pe("kiraya_veren","Kiraya verenin adı soyadı",true),Pe("tasinmaz_adres","Kiralanan taşınmazın adresi",true),Pe("tahliye_tarihi","Taahhüt edilen tahliye tarihi",true),Pe("duzenleme_tarihi","Taahhütnamenin düzenlendiği tarih",true),Pe("imza_zamani","Taahhütname, kira sözleşmesiyle aynı tarihte mi imzalandı? (Aynı tarihte imzalanan taahhütnameler Yargıtay içtihadına göre geçersiz sayılabilir)",true,["Evet","Hayır, sonradan imzalandı"])]},
    "trx_depozito_iade_talebi":{ic:"💰",n:"Kira Depozito (Güvence Bedeli) İade Talep Yazısı",cat:"trx_kira",tier:"einfach",q:[Pe("kiraci","Kiracının adı soyadı",true),Pe("kiraya_veren","Kiraya verenin adı soyadı",true),Pe("tasinmaz_adres","Kiralanan taşınmazın adresi",true),Pe("depozito_tutar","Ödenen depozito (güvence bedeli) tutarı",true),Pe("tahliye_tarihi","Taşınmazın tahliye tarihi",true),Pe("hasar_durumu","Taşınmazda hasar/eksik bulunuyor mu?",false,["Hayır, hasarsız teslim edildi","Evet, tutanağa bağlandı"])]},
    "trx_kat_malikleri_toplanti_cagri":{ic:"📢",n:"Kat Malikleri Kurulu Toplantı Çağrısı",cat:"trx_kat_mulkiyeti",tier:"standard",q:[Pe("bina_adres","Apartman/site adı ve adresi",true),Pe("yonetici_ad","Yönetici/yönetim kurulunun adı",true),Pe("toplanti_bilgi","Toplantı tarihi, saati ve yeri",true),Pe("gundem","Toplantı gündem maddeleri",true),Pe("toplanti_turu","Toplantı türü",true,["Olağan","Olağanüstü"]),Pe("cagri_yontemi","Çağrının yapılma yöntemi",false,["Taahhütlü mektup","İmza karşılığı tebliğ","İlan panosu"])]},
    "trx_kat_malikleri_karar_tutanagi":{ic:"📝",n:"Kat Malikleri Kurulu Karar Tutanağı",cat:"trx_kat_mulkiyeti",tier:"standard",q:[Pe("bina_adres","Apartman/site adı ve adresi",true),Pe("toplanti_bilgi","Toplantı tarihi ve katılımcı sayısı",true),Pe("kararlar","Alınan kararlar",true),Pe("oylama_sekli","Karar oy birliği ile mi yoksa oy çokluğu ile mi alındı?",true,["Oy birliği","Oy çokluğu"]),Pe("muhalif_uyeler","Muhalif kalan kat malikleri (varsa)",false),Pe("toplanti_baskani","Toplantıyı yöneten kişi",true)]},
    "trx_aidat_odeme_ihtari":{ic:"🧾",n:"Ortak Gider (Aidat) Ödeme İhtarnamesi",cat:"trx_kat_mulkiyeti",tier:"standard",q:[Pe("yonetici_ad","Yönetici/yönetim kurulunun adı",true),Pe("borclu_bilgi","Borçlu kat malikinin adı soyadı ve bağımsız bölüm numarası",true),Pe("borc_tutar","Ödenmeyen aidat tutarı ve ait olduğu dönem",true),Pe("gecikme_faizi","Gecikme faizi uygulanacak mı?",false,["Evet","Hayır"]),Pe("odeme_suresi","Ödeme için tanınan süre",true),Pe("gonderim_yontemi","İhtarın gönderim yöntemi",false,["Noter","İadeli taahhütlü posta"])]},
    "trx_komsu_rahatsizlik_sikayet":{ic:"🔇",n:"Komşuluk Hukuku İhlali Şikayet/İhtar Yazısı",cat:"trx_kat_mulkiyeti",tier:"einfach",q:[Pe("sikayetci_bilgi","Şikayetçinin adı soyadı ve dairesi",true),Pe("sikayet_edilen_bilgi","Şikayet edilen komşunun adı soyadı ve dairesi",true),Pe("rahatsizlik_turu","Rahatsızlığın türü",true,["Gürültü","Koku","Ortak alan işgali","Diğer"]),Pe("sure","Rahatsızlığın ne zamandır devam ettiği",true),Pe("onceki_uyari","Daha önce sözlü/yazılı uyarı yapıldı mı?",false,["Evet","Hayır"])]},
    "trx_is_sozlesmesi_fesih_bildirimi":{ic:"📄",n:"İşveren Tarafından İş Sözleşmesi Fesih Bildirimi",cat:"trx_is_tuketici",tier:"komplex",q:[Pe("isveren_unvan","İşverenin unvanı",true),Pe("isci_ad","İşçinin adı soyadı",true),Pe("ise_baslama_tarihi","İşçinin işe başlama tarihi",true),Pe("fesih_nedeni","Fesih nedeni",true,["Geçerli neden (ekonomik/verimlilik)","Haklı neden (İş Kanunu m.25)","İşçinin davranışlarından kaynaklanan neden"]),Pe("fesih_tarihi_tazminat","Fesih tarihi ve ihbar/kıdem tazminatı ödenip ödenmeyeceği",true),Pe("teblig_yontemi","Fesih bildiriminin tebliğ yöntemi",false,["Elden tebliğ","Noter","İadeli taahhütlü posta"])]},
    "trx_tazminat_talep_ihtarnamesi":{ic:"💸",n:"Kıdem ve İhbar Tazminatı Talep İhtarnamesi",cat:"trx_is_tuketici",tier:"standard",q:[Pe("isci_ad","İşçinin adı soyadı",true),Pe("isveren_unvan","İşverenin unvanı/adı",true),Pe("calisma_donemi","İşe başlama ve iş ilişkisinin sona erdiği tarihler",true),Pe("fesih_taraf_sekli","İş sözleşmesi kim tarafından ve nasıl feshedildi",true,["İşveren tarafından feshedildi","İşçi haklı nedenle (İş Kanunu m.24) feshetti","Emeklilik nedeniyle sona erdi"]),Pe("son_brut_maas","Son brüt aylık ücret tutarı",true),Pe("talep_edilen_tazminat","Talep edilen tazminat/alacak kalemleri",true,["Kıdem tazminatı","İhbar tazminatı","Kullanılmayan yıllık izin ücreti","Fazla mesai ücreti","Birden fazlası"]),Pe("odeme_suresi","Ödeme için tanınan süre",false),Pe("gonderim_yontemi","İhtarnamenin gönderim yöntemi",false,["Noter","İadeli taahhütlü posta"])]},
    "trx_tuketici_hakem_heyeti_basvuru":{ic:"⚖️",n:"Tüketici Hakem Heyetine Başvuru Dilekçesi",cat:"trx_is_tuketici",tier:"standard",q:[Pe("basvuran_bilgi","Başvuran (tüketici) adı soyadı ve adresi",true),Pe("saticiunvan","Şikayet edilen satıcı/sağlayıcı unvanı",true),Pe("uyusmazlik_konusu","Uyuşmazlık konusu ürün/hizmet",true),Pe("uyusmazlik_tutari","Uyuşmazlık tutarı",true),Pe("talep_turu","Talep türü",true,["Bedel iadesi","Ayıplı malın değişimi","Onarım","Bedel indirimi"]),Pe("islem_tarihi","Satın alma/işlem tarihi",true)]},
    "trx_kvkk_veri_sahibi_basvuru":{ic:"🔒",n:"KVKK Veri Sorumlusuna Başvuru Formu",cat:"trx_is_tuketici",tier:"standard",q:[Pe("basvuran_bilgi","Başvuran kişinin adı soyadı ve TC kimlik numarası",true),Pe("veri_sorumlusu_unvan","Veri sorumlusu şirket/kurum unvanı",true),Pe("talep_konusu","Talep konusu",true,["Kişisel verilerin işlenip işlenmediğinin öğrenilmesi","Verilerin silinmesi/yok edilmesi","Verilerin düzeltilmesi","Verilerin aktarıldığı üçüncü kişilerin öğrenilmesi","Diğer"]),Pe("talep_gerekcesi","Talebin gerekçesi/açıklaması",true),Pe("yanit_yontemi","Başvuru yanıtının iletilmesini istediğiniz yöntem",false,["E-posta","Posta","KEP"]),Pe("islem_baglami","İlgili veri işleme faaliyetinin tarihi/bağlamı",false)]},
    "trx_ticari_isletme_devir_sozlesmesi":{ic:"🏬",n:"Ticari İşletme (Esnaf İşyeri) Devir Sözleşmesi",cat:"trx_is_tuketici",tier:"komplex",q:[Pe("devreden_bilgi","Devreden (işletme sahibi) adı soyadı/unvanı",true),Pe("devralan_bilgi","Devralan kişinin adı soyadı/unvanı",true),Pe("isletme_bilgi","İşletmenin ticaret unvanı, faaliyet konusu ve adresi",true),Pe("devir_bedeli","Devir bedeli ve ödeme şekli",true),Pe("devir_tarihi","Devrin gerçekleşeceği tarih",true),Pe("devre_dahil_unsurlar","Devre dahil unsurlar (demirbaş, stok, marka/işyeri adı, kira sözleşmesi vb.)",true),Pe("borc_alacak_sorumlulugu","Devir tarihinden önceki borç ve alacaklardan kimin sorumlu olacağı (Türk Ticaret Kanunu uyarınca devralan da belirli süre sorumlu olabilir)",true),Pe("vergi_dairesi_bildirim","Vergi dairesine ve ilgili odaya devir bildirimi yapılacak mı?",false,["Evet","Hayır"])]}
  };
  function inject(){
    try{
      if(typeof DOCS==="undefined"||!DOCS) return false;
      if(typeof CATS==="undefined"||!CATS) return false;
      for(var k in E){ if(!DOCS[k]) DOCS[k]=E[k]; if(typeof DOC_TIER!=="undefined") DOC_TIER[k]=E[k].tier||"standard"; }
      for(var nc in NEWCATS){ if(!CATS[nc]) CATS[nc]={n:NEWCATS[nc].n,ic:NEWCATS[nc].ic,docs:[],country:"TR"}; if(!CATS[nc].docs) CATS[nc].docs=[]; }
      for(var k2 in E){ var c2=E[k2].cat; if(CATS[c2]&&CATS[c2].docs&&CATS[c2].docs.indexOf(k2)<0) CATS[c2].docs.push(k2); }
      if(typeof CAT_LABELS!=="undefined"){ for(var cl in NEWCATS) CAT_LABELS[cl]=NEWCATS[cl].n; }
      if(typeof CAT_DOCS!=="undefined"){
        for(var cd in NEWCATS){ if(!CAT_DOCS[cd]) CAT_DOCS[cd]=[]; }
        for(var k3 in E){ var c3=E[k3].cat; if(CAT_DOCS[c3]){ var ex=false; for(var i=0;i<CAT_DOCS[c3].length;i++){ if(CAT_DOCS[c3][i].k===k3){ex=true;break;} } if(!ex) CAT_DOCS[c3].push({k:k3,ic:E[k3].ic,t:E[k3].n,tier:E[k3].tier||"standard"}); } }
      }
      return !!(DOCS.trx_miras_reddi_dilekce);
    }catch(e){ return false; }
  }
  var done=false, ticks=0;
  function run(){ if(!done){ if(inject()) done=true; } if(ticks++<40) setTimeout(run,500); }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",run); else run();
})();}catch(e){}

CHJS;
$src=str_replace($anchor,$anchor.$js,$src);
$tmp = tempnam(sys_get_temp_dir(),'tr').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-trmore-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "  ✓ TR: +18 belge, 4 kategori eklendi\n\n✓ CH_TR_EXPAND_MORE uygulandi.\n";
