<?php
/**
 * ChatHelp — PORTEKİZ MODÜLÜ (~36 belge, 6 kategori, Portekizce) — CH_TR_MODULE ile aynı kanıtlanmış desen
 * -------------------------------------------------------------------------------------------------------
 *  • Kategoriler country:'PT' etiketli -> genelleştirilmiş ülke filtresi (CH_TR_MODULE) otomatik çalışır
 *  • Welcome grid kartları data-cccountry='PT' -> kart görünürlüğü otomatik
 *  • Dil: setCountry('PT') zaten UL='pt' yapıyor (CC_DATA.PT.l) — ek dil kodu gerekmez
 *  • Belge dili/hukuku: api.php'de lawSystems['PT'] + doc-lang PT->pt (apply-lawsys-all.php ile)
 *
 * KULLANIM: pull-updates.php?files=apply-portugal.php&run=1. SİL.
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-portugal BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/index.php";
if(!file_exists($file)) exit("index.php yok\n");
$src=file_get_contents($file); $start=$src;
$anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if(strpos($src,"CH_PT_MODULE")!==false) exit("Zaten ekli (CH_PT_MODULE).\n");
if(strpos($src,$anchor)===false) exit("anchor yok\n");
file_put_contents($file.".bak-pt-".date("Ymd-His"),$src);

$js=<<<'CHJS'

/* CH_PT_MODULE — Portekiz hukuku modülü (deferred; DOCS/CATS hazır olunca yüklenir) */
try{(function(){
  function Pq(k,l,r,o){var x={k:k,l:l,r:!!r};if(o)x.opts=o;return x;}
  var XC={
    pt_rescisoes:{n:"Rescisões e Cancelamentos",ic:"📄"},
    pt_habitacao:{n:"Habitação e Arrendamento",ic:"🏠"},
    pt_trabalho:{n:"Trabalho e Emprego",ic:"💼"},
    pt_consumidor:{n:"Consumidor e Litígios",ic:"🛒"},
    pt_contratos:{n:"Contratos",ic:"📝"},
    pt_servicos:{n:"Serviços Públicos",ic:"🏛️"}
  };
  var X_ORDER=["pt_rescisoes","pt_habitacao","pt_trabalho","pt_consumidor","pt_contratos","pt_servicos"];
  var CCODE="PT", DOCWORD="documentos";

  var D={
    /* Rescisões e Cancelamentos */
    pt_rescisao_telecom:{ic:"📱",n:"Rescisão de telecomunicações (TV/internet/telemóvel)",cat:"pt_rescisoes",tier:"einfach",q:[Pq("operador","Operador: nome e morada",true),Pq("num_cliente","Nº de cliente / contrato",true),Pq("fidelizacao","Tem período de fidelização?",false,["Não","Sim","Não sei"]),Pq("data","Data de rescisão pretendida",false),Pq("dados","O seu nome completo e NIF",true)]},
    pt_cancel_ginasio:{ic:"🏋️",n:"Cancelamento de ginásio / clube",cat:"pt_rescisoes",tier:"einfach",q:[Pq("centro","Ginásio: nome e morada",true),Pq("num_socio","Nº de sócio",false),Pq("data","Data de cancelamento",true),Pq("motivo","Motivo (opcional)",false),Pq("dados","O seu nome completo",true)]},
    pt_rescisao_seguro:{ic:"🛡️",n:"Rescisão de apólice de seguro",cat:"pt_rescisoes",tier:"einfach",q:[Pq("seguradora","Seguradora: nome e morada",true),Pq("num_apolice","Nº da apólice",true),Pq("tipo","Tipo de seguro",true,["Automóvel","Habitação","Saúde","Vida","Outro"]),Pq("vencimento","Data de vencimento (pré-aviso 30 dias)",true),Pq("dados","O seu nome completo e NIF",true)]},
    pt_encerramento_conta:{ic:"🏦",n:"Encerramento de conta bancária",cat:"pt_rescisoes",tier:"einfach",q:[Pq("banco","Banco: nome e balcão",true),Pq("iban","IBAN da conta",true),Pq("transferencia","IBAN para transferir o saldo (opcional)",false),Pq("dados","O seu nome completo e NIF",true)]},
    pt_cancel_subscricao:{ic:"📺",n:"Cancelamento de subscrição digital",cat:"pt_rescisoes",tier:"einfach",q:[Pq("plataforma","Plataforma (Netflix, Spotify…)",true),Pq("conta","E-mail da conta",true),Pq("data","Data de cancelamento (opcional)",false),Pq("dados","O seu nome completo",true)]},
    pt_rescisao_energia:{ic:"⚡",n:"Rescisão de fornecimento (luz/gás)",cat:"pt_rescisoes",tier:"einfach",q:[Pq("comercializadora","Comercializadora: nome e morada",true),Pq("num_contrato","Nº de contrato / CPE ou CUI",true),Pq("morada","Morada do fornecimento",true),Pq("data","Data de rescisão pretendida",true),Pq("dados","O seu nome completo e NIF",true)]},

    /* Habitação e Arrendamento */
    pt_denuncia_arrendamento:{ic:"🏠",n:"Denúncia do contrato de arrendamento (inquilino)",cat:"pt_habitacao",tier:"einfach",q:[Pq("senhorio","Senhorio/agência: nome e morada",true),Pq("morada","Morada do imóvel arrendado",true),Pq("data_saida","Data de saída (respeitar pré-aviso legal, NRAU)",true),Pq("dados","O seu nome completo e NIF",true)]},
    pt_caucao:{ic:"💶",n:"Pedido de devolução da caução",cat:"pt_habitacao",tier:"standard",q:[Pq("senhorio","Senhorio: nome e morada",true),Pq("morada","Morada do imóvel",true),Pq("data_entrega","Data de entrega das chaves",true),Pq("valor","Valor da caução (€)",true),Pq("iban","IBAN para a devolução",false),Pq("dados","O seu nome completo",true)]},
    pt_defeitos:{ic:"🔧",n:"Comunicação de defeitos ao senhorio",cat:"pt_habitacao",tier:"standard",q:[Pq("senhorio","Senhorio: nome e morada",true),Pq("morada","Morada do imóvel",true),Pq("problema","Descrição do defeito/avaria",true),Pq("urgencia","É urgente?",false,["Sim","Não"]),Pq("dados","O seu nome completo",true)]},
    pt_aumento_renda:{ic:"📈",n:"Oposição a aumento de renda",cat:"pt_habitacao",tier:"standard",q:[Pq("senhorio","Senhorio: nome e morada",true),Pq("morada","Morada do imóvel",true),Pq("renda_atual","Renda atual (€)",true),Pq("renda_nova","Renda exigida (€)",true),Pq("fundamentos","Fundamentos da sua oposição",true)]},
    pt_contrato_arrendamento:{ic:"📄",n:"Contrato de arrendamento habitacional",cat:"pt_habitacao",tier:"komplex",q:[Pq("papel","O senhor/a senhora é",true,["Senhorio","Inquilino"]),Pq("outra_parte","Outra parte: nome e NIF",true),Pq("morada","Morada do imóvel",true),Pq("tipologia","Área (m²) e tipologia (T1, T2…)",false),Pq("inicio","Data de início",true),Pq("renda","Renda mensal (€)",true),Pq("caucao","Caução (€, opcional)",false),Pq("despesas","Despesas incluídas (condomínio, IMI…)",false)]},
    pt_ruido:{ic:"🔊",n:"Queixa de ruído (vizinho/condomínio)",cat:"pt_habitacao",tier:"standard",q:[Pq("destinatario","Destinatário (vizinho/administrador do condomínio)",true),Pq("problema","Descrição do incómodo",true),Pq("horarios","Frequência e horários",false),Pq("pedido","O seu pedido",true),Pq("dados","O seu nome e fração/andar",true)]},

    /* Trabalho e Emprego */
    pt_demissao:{ic:"👋",n:"Carta de demissão (denúncia pelo trabalhador)",cat:"pt_trabalho",tier:"einfach",q:[Pq("empresa","Empresa: nome e morada",true),Pq("cargo","O seu cargo e antiguidade",true),Pq("ultimo_dia","Último dia de trabalho (aviso prévio 30/60 dias)",true),Pq("dados","O seu nome completo e NIF",true)]},
    pt_salarios:{ic:"🧾",n:"Reclamação de salários em atraso",cat:"pt_trabalho",tier:"standard",q:[Pq("empresa","Empresa: nome e morada",true),Pq("cargo","O seu cargo e datas do contrato",true),Pq("verbas","Verbas em falta (meses, subsídios de férias/Natal)",true),Pq("valor","Valor estimado (€)",false),Pq("dados","O seu nome completo",true)]},
    pt_ferias:{ic:"🏖️",n:"Pedido de férias",cat:"pt_trabalho",tier:"einfach",q:[Pq("empresa","Empresa/responsável",true),Pq("desde","De (data)",true),Pq("ate","Até (data)",true),Pq("dados","O seu nome e cargo",true)]},
    pt_teletrabalho:{ic:"🏡",n:"Pedido de teletrabalho",cat:"pt_trabalho",tier:"einfach",q:[Pq("empresa","Empresa/responsável",true),Pq("cargo","O seu cargo",true),Pq("regime","Regime pretendido (dias/semana)",true),Pq("motivo","Motivo (opcional)",false)]},
    pt_certificado_trabalho:{ic:"📜",n:"Pedido de certificado de trabalho",cat:"pt_trabalho",tier:"einfach",q:[Pq("empresa","Empresa/RH: nome",true),Pq("cargo","O seu cargo e período do contrato",true),Pq("finalidade","Finalidade (opcional)",false),Pq("dados","O seu nome completo",true)]},
    pt_impugnacao_despedimento:{ic:"⚖️",n:"Impugnação de despedimento",cat:"pt_trabalho",tier:"komplex",q:[Pq("empresa","Empresa: nome, NIF e morada",true),Pq("relacao","O seu cargo e datas da relação laboral",true),Pq("despedimento","Tipo de despedimento e data da comunicação",true),Pq("fundamentos","Fundamentos da impugnação",true),Pq("pedido","Pedido (reintegração, indemnização €…)",true),Pq("dados","O seu nome completo, NIF e morada",true)]},

    /* Consumidor e Litígios */
    pt_livre_resolucao:{ic:"🔄",n:"Livre resolução de compra (14 dias)",cat:"pt_consumidor",tier:"einfach",q:[Pq("vendedor","Vendedor: nome e morada",true),Pq("encomenda","Nº da encomenda",true),Pq("data","Data de compra/receção",true),Pq("produtos","Produtos abrangidos",true),Pq("iban","IBAN para o reembolso (opcional)",false)]},
    pt_garantia:{ic:"🔧",n:"Reclamação de produto com defeito (garantia 3 anos)",cat:"pt_consumidor",tier:"standard",q:[Pq("vendedor","Vendedor: nome e morada",true),Pq("produto","Produto (marca/modelo)",true),Pq("data_compra","Data de compra",true),Pq("defeito","Defeito detetado",true),Pq("pedido","O seu pedido",true,["Reparação","Substituição","Redução do preço","Resolução do contrato"])]},
    pt_aerea:{ic:"✈️",n:"Reclamação a companhia aérea (atraso/cancelamento)",cat:"pt_consumidor",tier:"standard",q:[Pq("companhia","Companhia aérea",true),Pq("voo","Nº do voo e data",true),Pq("problema","Problema",true,["Atraso","Cancelamento","Overbooking","Bagagem"]),Pq("pedido","O seu pedido (indemnização UE 261/2004…)",true),Pq("dados","O seu nome completo",true)]},
    pt_interpelacao:{ic:"📨",n:"Interpelação para pagamento",cat:"pt_consumidor",tier:"standard",q:[Pq("devedor","Devedor: nome e morada",true),Pq("origem","Origem da dívida (fatura, contrato, empréstimo…)",true),Pq("valor","Valor em dívida (€)",true),Pq("prazo","Prazo de pagamento concedido",true),Pq("iban","IBAN para pagamento (opcional)",false),Pq("dados","O seu (credor) nome e morada",true)]},
    pt_contestacao_fatura:{ic:"🧾",n:"Contestação de fatura",cat:"pt_consumidor",tier:"standard",q:[Pq("empresa","Empresa emissora: nome e morada",true),Pq("fatura","Nº da fatura e data",true),Pq("valor","Valor contestado (€)",true),Pq("motivos","Motivos da contestação",true),Pq("dados","O seu nome e nº de cliente",true)]},
    pt_livro_reclamacoes:{ic:"📕",n:"Reclamação de consumo (Livro de Reclamações/DECO)",cat:"pt_consumidor",tier:"standard",q:[Pq("empresa","Empresa reclamada: nome e morada",true),Pq("factos","Factos (o que aconteceu, quando)",true),Pq("pedido","O seu pedido",true,["Devolução","Reparação","Substituição","Compensação"]),Pq("valor","Valor afetado (€, opcional)",false),Pq("dados","O seu nome completo e NIF",true)]},

    /* Contratos */
    pt_confissao_divida:{ic:"💶",n:"Confissão de dívida",cat:"pt_contratos",tier:"standard",q:[Pq("credor","Credor: nome e morada",true),Pq("devedor","Devedor: nome e morada",true),Pq("valor","Valor (€, em algarismos e por extenso)",true),Pq("prazo","Prazo/forma de pagamento",true),Pq("juros","Com juros?",false,["Sem juros","Com juros"])]},
    pt_compra_venda:{ic:"🛒",n:"Contrato de compra e venda entre particulares",cat:"pt_contratos",tier:"einfach",q:[Pq("papel","O senhor/a senhora é",true,["Vendedor","Comprador"]),Pq("outra_parte","Outra parte: nome e NIF",true),Pq("objeto","Objeto da venda",true),Pq("preco","Preço (€)",true),Pq("entrega","Data de entrega",false)]},
    pt_mutuo:{ic:"🤝",n:"Contrato de mútuo (empréstimo entre particulares)",cat:"pt_contratos",tier:"standard",q:[Pq("mutuante","Mutuante: nome e NIF",true),Pq("mutuario","Mutuário: nome e NIF",true),Pq("valor","Valor emprestado (€)",true),Pq("reembolso","Plano de reembolso",true),Pq("juros","Juros (%, opcional)",false)]},
    pt_procuracao:{ic:"📝",n:"Procuração simples",cat:"pt_contratos",tier:"einfach",q:[Pq("procurador","Pessoa autorizada: nome e NIF/CC",true),Pq("objeto","Para que fins autoriza",true),Pq("validade","Validade (opcional)",false),Pq("dados","Os seus (mandante) nome e NIF/CC",true)]},
    pt_confidencialidade:{ic:"🔒",n:"Acordo de confidencialidade (NDA)",cat:"pt_contratos",tier:"standard",q:[Pq("outra_parte","Outra parte: nome e morada",true),Pq("objeto","Objeto da colaboração",true),Pq("ambito","Âmbito da informação confidencial",true),Pq("duracao","Duração (opcional)",false)]},
    pt_prestacao_servicos:{ic:"🧰",n:"Contrato de prestação de serviços",cat:"pt_contratos",tier:"komplex",q:[Pq("papel","O senhor/a senhora é",true,["Prestador","Cliente"]),Pq("outra_parte","Outra parte: nome e NIF",true),Pq("servico","Descrição do serviço",true),Pq("preco","Preço e forma de pagamento (€)",true),Pq("prazo","Prazo/duração",false),Pq("clausulas","Cláusulas especiais (opcional)",false)]},

    /* Serviços Públicos */
    pt_multa:{ic:"🚗",n:"Contestação de multa de trânsito (ANSR)",cat:"pt_servicos",tier:"standard",q:[Pq("auto","Nº do auto de contraordenação/processo",true),Pq("data","Data da infração",true),Pq("matricula","Matrícula",false),Pq("fundamentos","Fundamentos da defesa",true),Pq("dados","O seu nome completo e NIF",true)]},
    pt_financas:{ic:"🧾",n:"Requerimento às Finanças (pagamento em prestações)",cat:"pt_servicos",tier:"standard",q:[Pq("servico","Serviço de Finanças competente",true),Pq("divida","Dívida (imposto e valor €)",true),Pq("situacao","Situação económica",true),Pq("proposta","Plano de pagamento proposto",false),Pq("dados","O seu nome completo e NIF",true)]},
    pt_seguranca_social:{ic:"🏥",n:"Requerimento à Segurança Social",cat:"pt_servicos",tier:"standard",q:[Pq("servico","Serviço (centro distrital) da Segurança Social",true),Pq("objeto","Objeto (subsídio, pensão, contribuições…)",true),Pq("exposicao","Exposição dos factos",true),Pq("pedido","O seu pedido",true),Pq("dados","O seu nome, NIF e NISS",true)]},
    pt_alteracao_morada:{ic:"🏠",n:"Comunicação de alteração de morada",cat:"pt_servicos",tier:"einfach",q:[Pq("destinatario","Destinatário (entidade/empresa)",true),Pq("morada_antiga","Morada antiga",true),Pq("morada_nova","Morada nova",true),Pq("data","Data de efeito",false),Pq("dados","O seu nome completo e NIF",true)]},
    pt_registo_criminal:{ic:"📋",n:"Pedido de certificado de registo criminal",cat:"pt_servicos",tier:"einfach",q:[Pq("finalidade","Finalidade (emprego, visto, concurso…)",true),Pq("dados","O seu nome completo, filiação e data de nascimento",true),Pq("documento","NIF e nº do Cartão de Cidadão",true),Pq("morada","A sua morada",true)]},
    pt_camara:{ic:"🏛️",n:"Exposição à Câmara Municipal",cat:"pt_servicos",tier:"standard",q:[Pq("camara","Câmara Municipal (município)",true),Pq("assunto","Assunto (licenciamento, via pública, ruído…)",true),Pq("exposicao","Exposição dos factos",true),Pq("pedido","O seu pedido",true),Pq("dados","O seu nome, NIF e morada",true)]}
  };

  function inject(){
    try{
      if(typeof DOCS==="undefined"||!DOCS) return false;
      for(var k in D){ if(!DOCS[k]) DOCS[k]=D[k]; if(typeof DOC_TIER!=="undefined") DOC_TIER[k]=D[k].tier||"standard"; }
      if(typeof CATS!=="undefined"&&CATS){
        for(var ck in XC){ if(!CATS[ck]) CATS[ck]={n:XC[ck].n,ic:XC[ck].ic,docs:[],country:CCODE}; if(!CATS[ck].docs) CATS[ck].docs=[]; }
        for(var k2 in D){ var c2=D[k2].cat; if(CATS[c2]&&CATS[c2].docs.indexOf(k2)<0) CATS[c2].docs.push(k2); }
      }
      if(typeof CAT_LABELS!=="undefined"){ for(var cl in XC) CAT_LABELS[cl]=XC[cl].n; }
      if(typeof CAT_DOCS!=="undefined"){
        for(var cd in XC){ if(!CAT_DOCS[cd]) CAT_DOCS[cd]=[]; }
        for(var k3 in D){ var c3=D[k3].cat; if(CAT_DOCS[c3]){ var ex=false; for(var i=0;i<CAT_DOCS[c3].length;i++){ if(CAT_DOCS[c3][i].k===k3){ex=true;break;} } if(!ex) CAT_DOCS[c3].push({k:k3,ic:D[k3].ic,t:D[k3].n,tier:D[k3].tier||"standard"}); } }
      }
      return (typeof DOCS!=="undefined" && DOCS.pt_interpelacao)?true:false;
    }catch(e){ return false; }
  }

  /* Ülke filtresi (CH_TR_MODULE kuruyor; yoksa aynı korumalı sarmalayıcı burada da kurulur) */
  function installMultiCountryFilter(){
    try{
      if(typeof showAllCats!=="function" || showAllCats.__multiCountry) return;
      var _sac=showAllCats;
      var w=function(){
        try{
          if(typeof CATS==="undefined") return _sac.apply(this,arguments);
          var cur=(typeof CC!=="undefined"?CC:"DE");
          var hid={};
          for(var k in CATS){
            var cc=(CATS[k]&&CATS[k].country)||"DE";
            if(cc!==cur){ hid[k]=CATS[k]; delete CATS[k]; }
          }
          try{ return _sac.apply(this,arguments); } finally { for(var h in hid) CATS[h]=hid[h]; }
        }catch(e){ return _sac.apply(this,arguments); }
      };
      w.__multiCountry=1; showAllCats=w; if(typeof window!=="undefined") window.showAllCats=w;
    }catch(e){}
  }

  /* Welcome grid: bu ülkenin kartları (data-cccountry) + görünürlük */
  function xGrid(){
    try{
      var grid=document.querySelector(".wcat-grid"); if(!grid) return;
      var cur=(typeof CC!=="undefined"?CC:"DE");
      if(cur===CCODE){
        X_ORDER.forEach(function(ck){
          if(grid.querySelector('[data-cccountry="'+CCODE+'"][data-ccat="'+ck+'"]')) return;
          var c=XC[ck]; var el=document.createElement("div"); el.className="wcat";
          el.setAttribute("data-cccountry",CCODE); el.setAttribute("data-ccat",ck);
          el.onclick=function(){ try{ if(typeof showCat==="function") showCat(ck); }catch(e){} };
          var n=(typeof CATS!=="undefined"&&CATS[ck]&&CATS[ck].docs)?CATS[ck].docs.length:0;
          el.innerHTML='<div class="wcat-ic">'+c.ic+'</div><div class="wcat-t">'+c.n+'</div><div class="wcat-s">'+n+' '+DOCWORD+'</div>';
          grid.appendChild(el);
        });
      }
      grid.querySelectorAll(".wcat").forEach(function(el){
        var cc=el.getAttribute("data-cccountry")||(el.getAttribute("data-frcat")?"FR":(el.getAttribute("data-trcat")?"TR":"DE"));
        el.style.display=(cc===cur)?"":"none";
      });
    }catch(e){}
  }

  function installCountryHook(){
    try{
      if(typeof setCountry!=="function" || setCountry["__m"+CCODE]) return;
      var _sc=setCountry;
      var s=function(cc){ var r; try{ r=_sc.apply(this,arguments); }catch(e){} try{ xGrid(); }catch(e){} return r; };
      s["__m"+CCODE]=1; setCountry=s; if(typeof window!=="undefined") window.setCountry=s;
    }catch(e){}
  }

  var done=false, ticks=0;
  function run(){ if(!done){ if(inject()) done=true; } installMultiCountryFilter(); installCountryHook(); try{ xGrid(); }catch(e){} if(ticks++<40) setTimeout(run,500); }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",run); else run();
})();}catch(e){}
CHJS;
$src=str_replace($anchor,$anchor.$js,$src);
if($src!==$start){ file_put_contents($file,$src); echo "Portekiz modulu eklendi (CH_PT_MODULE) - ~36 belge, 6 kategori, Portekizce.\n"; }
else echo "degisiklik yok\n";
echo "\nNOT: Belge dili/hukuku icin apply-lawsys-all.php de calistirilmali (lawSystems['PT'] + doc-lang).\n";
echo "SONRA: opcache-reset (pull-updates otomatik yapar). SIL: rm apply-portugal.php\n";
echo "Test: Ayarlar -> Portugal sec -> kategoriler Portekizce -> 'Interpelacao para pagamento' uret.\n";
