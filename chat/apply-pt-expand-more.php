<?php
/** ChatHelp — apply-pt-expand-more (CH_PT_EXPAND_MORE) — PT katalog genisletme
 *  Workflow ile uretildi: +16 belge, 4 kategori. Deferred injection.
 *  KULLANIM: pull-updates.php?files=apply-pt-expand-more.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-pt-expand-more BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_PT_EXPAND_MORE')!==false) exit("Zaten ekli (CH_PT_EXPAND_MORE).\n");
$anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if (strpos($src,$anchor)===false) exit("HATA: anchor (getDocPrice) yok.\n");
$js = <<<'CHJS'

/* CH_PT_EXPAND_MORE — PT katalog genisletme (workflow, deferred) */
try{(function(){
  function Pe(k,l,r,o){var x={k:k,l:l,r:!!r};if(o)x.opts=o;return x;}
  var NEWCATS={
    "pt_dividas":{n:"Dívidas e Cobranças",ic:"💳"},
    "pt_dados":{n:"Dados e Privacidade (RGPD)",ic:"🔐"},
    "pt_condominio":{n:"Condomínio e Vizinhança",ic:"🏢"},
    "pt_sucessoes":{n:"Heranças e Sucessões",ic:"⚱️"}
  };
  var E={
    "ptx_plano_pagamento_credor":{ic:"🗓️",n:"Proposta de plano de pagamento a credor",cat:"pt_dividas",tier:"standard",q:[Pe("credor","Credor: nome e morada",true),Pe("divida","Dívida: referência/contrato e montante em dívida (€)",true),Pe("proposta","Plano proposto: número de prestações e valor mensal (€)",true),Pe("inicio","Data de início dos pagamentos",true),Pe("motivo","Motivo da dificuldade financeira (breve)",false),Pe("dados","Os seus dados: nome, morada e NIF",true)]},
    "ptx_prescricao_divida":{ic:"⏳",n:"Invocação de prescrição de dívida (art. 309.º e ss. CC)",cat:"pt_dividas",tier:"standard",q:[Pe("credor","Credor ou empresa de cobrança: nome e morada",true),Pe("divida","Dívida invocada: referência e montante (€)",true),Pe("origem","Data da origem da dívida ou do último pagamento",true),Pe("prazo","Prazo de prescrição aplicável",false,["Geral — 20 anos (art. 309.º CC)","Prestações periódicas: rendas, juros — 5 anos (art. 310.º CC)","Serviços essenciais: água, luz, gás, telecom — 6 meses (Lei 23/96)","Não sei"]),Pe("dados","Os seus dados: nome e morada",true)]},
    "ptx_oposicao_injuncao":{ic:"⚖️",n:"Oposição a requerimento de injunção (DL 269/98)",cat:"pt_dividas",tier:"komplex",q:[Pe("credor","Requerente (credor): nome e morada",true),Pe("processo","Nº do procedimento de injunção / referência (Balcão Nacional de Injunções)",true),Pe("notificado","Data em que foi notificado (prazo de oposição: 15 dias)",true),Pe("fundamento","Fundamento da oposição",true,["Dívida inexistente","Dívida já paga","Montante ou juros errados","Dívida prescrita","Não reconheço o contrato"]),Pe("explicacao","Explique a sua oposição (nas suas palavras)",true),Pe("dados","Os seus dados: nome, morada e NIF",true)]},
    "ptx_quitacao_divida":{ic:"🧾",n:"Pedido de recibo de quitação (art. 787.º CC)",cat:"pt_dividas",tier:"einfach",q:[Pe("credor","Credor: nome e morada",true),Pe("divida","Dívida paga: referência ou contrato",true),Pe("pagamento","Pagamento efetuado: data, montante (€) e meio",true),Pe("total","O pagamento liquida",false,["A totalidade da dívida","Uma prestação / parte"]),Pe("dados","Os seus dados: nome e morada",true)]},
    "ptx_rgpd_acesso":{ic:"🔎",n:"Pedido de acesso a dados pessoais (art. 15.º RGPD)",cat:"pt_dados",tier:"einfach",q:[Pe("entidade","Responsável pelo tratamento (empresa/entidade): nome e morada ou e-mail",true),Pe("relacao","Que relação tem com a entidade (cliente, ex-trabalhador, utente…)",false),Pe("ambito","Dados que pretende consultar",false,["Todos os meus dados","Uma categoria específica"]),Pe("especificar","Se específico, indique quais",false),Pe("forma","Forma de resposta preferida",false,["E-mail","Cópia em papel"]),Pe("requerente","Os seus dados: nome, morada e contacto",true)]},
    "ptx_rgpd_apagamento":{ic:"🗑️",n:"Pedido de apagamento / direito ao esquecimento (art. 17.º RGPD)",cat:"pt_dados",tier:"standard",q:[Pe("entidade","Responsável pelo tratamento: nome e morada ou e-mail",true),Pe("dados","Dados que pretende apagar (o mais preciso possível)",true),Pe("motivo","Motivo do pedido",true,["Já não são necessários para a finalidade","Retiro o consentimento","Oponho-me ao tratamento","Tratamento ilícito"]),Pe("terceiros","Foram comunicados a terceiros que devam ser informados?",false,["Sim","Não sei","Não"]),Pe("requerente","Os seus dados: nome, morada e contacto",true)]},
    "ptx_rgpd_marketing":{ic:"📵",n:"Oposição a marketing direto (art. 21.º RGPD)",cat:"pt_dados",tier:"einfach",q:[Pe("entidade","Empresa: nome e morada ou e-mail",true),Pe("canais","Canais que pretende cessar",true,["E-mail","SMS","Telefone","Correio postal","Todos"]),Pe("contacto","Contacto(s) usados pela empresa (e-mail/telefone) a remover",true),Pe("requerente","Os seus dados: nome e morada",true)]},
    "ptx_reclamacao_cnpd":{ic:"🛡️",n:"Reclamação à CNPD (art. 77.º RGPD)",cat:"pt_dados",tier:"komplex",q:[Pe("entidade","Entidade visada (quem tratou os dados): nome e morada",true),Pe("ocorrido","O que aconteceu (violação de dados, recusa de acesso, uso indevido…)",true),Pe("data","Quando ocorreu ou de que tomou conhecimento",true),Pe("diligencias","Já contactou a entidade sobre isto?",true,["Sim, sem resposta","Sim, resposta insatisfatória","Não"]),Pe("provas","Provas que pode juntar (e-mails, capturas de ecrã, cartas)",false),Pe("requerente","Os seus dados: nome, morada e contacto",true)]},
    "ptx_convocatoria_assembleia":{ic:"📢",n:"Convocatória de assembleia de condóminos (art. 1431.º CC)",cat:"pt_condominio",tier:"standard",q:[Pe("condominio","Condomínio: morada do prédio",true),Pe("convocante","Quem convoca",true,["Administrador","Condóminos que representam ≥ 25% do capital"]),Pe("tipo","Tipo de assembleia",false,["Ordinária","Extraordinária"]),Pe("datahora","Data, hora e local (1.ª e 2.ª convocatória)",true),Pe("ordem","Ordem de trabalhos (pontos a discutir)",true),Pe("dados","Os seus dados e fração",true)]},
    "ptx_impugnacao_deliberacao":{ic:"🗳️",n:"Impugnação de deliberação da assembleia de condóminos (art. 1433.º CC)",cat:"pt_condominio",tier:"komplex",q:[Pe("condominio","Condomínio: morada do prédio e a sua fração",true),Pe("assembleia","Data da assembleia cuja deliberação impugna",true),Pe("deliberacao","Deliberação(ões) que impugna",true),Pe("fundamento","Fundamento da impugnação",true,["Contrária à lei ou ao regulamento","Vício de convocação ou de quórum","Prejudica direitos do condómino","Abuso de maioria"]),Pe("posicao","Na assembleia esteve",false,["Ausente","Presente e votou contra","Absteve-se"]),Pe("dados","Os seus dados: nome e contacto",true)]},
    "ptx_quotas_condominio":{ic:"💰",n:"Interpelação para pagamento de quotas de condomínio em atraso",cat:"pt_condominio",tier:"standard",q:[Pe("condominio","Condomínio: morada do prédio",true),Pe("condomino","Condómino devedor: nome e fração",true),Pe("valores","Quotas em atraso (períodos e montantes, €)",true),Pe("prazo","Prazo de pagamento (p. ex. 15 dias)",true),Pe("iban","IBAN do condomínio para pagamento",true),Pe("consequencia","Avisar que a dívida seguirá para injunção/tribunal?",false,["Sim","Não"])]},
    "ptx_arvores_estrema":{ic:"🌳",n:"Carta ao vizinho: árvores e plantações junto à estrema (art. 1366.º-1368.º CC)",cat:"pt_condominio",tier:"einfach",q:[Pe("vizinho","Vizinho: nome e morada",true),Pe("problema","Situação (ramos ou raízes que invadem, sombra, queda de folhas/frutos)",true),Pe("pedido","O que pede",true,["Corte de ramos salientes","Corte de raízes","Recolha de folhas/frutos","Manutenção da árvore"]),Pe("prazo","Prazo para resolução",false),Pe("dados","Os seus dados: nome e morada",true)]},
    "ptx_repudio_heranca":{ic:"🚫",n:"Declaração de repúdio de herança (art. 2062.º CC)",cat:"pt_sucessoes",tier:"komplex",q:[Pe("falecido","Falecido (autor da herança): nome, data do óbito e última morada",true),Pe("relacao","A sua qualidade de herdeiro / grau de parentesco",true),Pe("motivo","Motivo do repúdio (opcional)",false),Pe("efeito","Está ciente de que o repúdio é irrevogável e retroativo?",false,["Sim","Quero mais informação"]),Pe("dados","Os seus dados: nome, data de nascimento, morada e NIF",true),Pe("nota","Nota: o repúdio só é válido por escritura pública ou por termo no processo de inventário",false)]},
    "ptx_interpelacao_cabeca_casal":{ic:"📋",n:"Interpelação ao cabeça-de-casal (relação de bens, art. 2079.º/2087.º CC)",cat:"pt_sucessoes",tier:"standard",q:[Pe("cabeca","Cabeça-de-casal: nome e morada",true),Pe("falecido","Falecido: nome e data do óbito",true),Pe("qualidade","A sua qualidade (herdeiro, legatário)",true),Pe("pedido","O que pretende",true,["Relação de bens da herança","Prestação de contas","Informação sobre a administração","Entrega do quinhão"]),Pe("prazo","Prazo de resposta (p. ex. 15 dias)",false),Pe("dados","Os seus dados: nome e morada",true)]},
    "ptx_pedido_partilha":{ic:"🤝",n:"Pedido de partilha da herança (art. 2101.º CC)",cat:"pt_sucessoes",tier:"standard",q:[Pe("herdeiros","Restantes herdeiros: nomes e moradas",true),Pe("falecido","Falecido: nome e data do óbito",true),Pe("bens","Bens a partilhar (breve descrição)",true),Pe("forma","Forma de partilha pretendida",false,["Extrajudicial (acordo em cartório)","Inventário (se não houver acordo)"]),Pe("prazo","Prazo para acordo (p. ex. 30 dias)",false),Pe("dados","Os seus dados: nome e morada",true)]},
    "ptx_doacao_moveis":{ic:"🎁",n:"Contrato de doação de bens móveis (art. 940.º/947.º CC)",cat:"pt_sucessoes",tier:"standard",q:[Pe("papel","É",true,["Doador","Donatário"]),Pe("outra","Outra parte: nome, morada e NIF",true),Pe("bem","Bem doado (descrição precisa e valor aproximado)",true),Pe("entrega","Entrega do bem",false,["Já entregue (tradição)","A entregar na data do contrato"]),Pe("encargo","A doação tem algum encargo ou condição?",false,["Não","Sim (indicar)"]),Pe("usufruto","O doador reserva usufruto do bem?",false,["Não","Sim"])]}
  };
  function inject(){
    try{
      if(typeof DOCS==="undefined"||!DOCS) return false;
      if(typeof CATS==="undefined"||!CATS) return false;
      for(var k in E){ if(!DOCS[k]) DOCS[k]=E[k]; if(typeof DOC_TIER!=="undefined") DOC_TIER[k]=E[k].tier||"standard"; }
      for(var nc in NEWCATS){ if(!CATS[nc]) CATS[nc]={n:NEWCATS[nc].n,ic:NEWCATS[nc].ic,docs:[],country:"PT"}; if(!CATS[nc].docs) CATS[nc].docs=[]; }
      for(var k2 in E){ var c2=E[k2].cat; if(CATS[c2]&&CATS[c2].docs&&CATS[c2].docs.indexOf(k2)<0) CATS[c2].docs.push(k2); }
      if(typeof CAT_LABELS!=="undefined"){ for(var cl in NEWCATS) CAT_LABELS[cl]=NEWCATS[cl].n; }
      if(typeof CAT_DOCS!=="undefined"){
        for(var cd in NEWCATS){ if(!CAT_DOCS[cd]) CAT_DOCS[cd]=[]; }
        for(var k3 in E){ var c3=E[k3].cat; if(CAT_DOCS[c3]){ var ex=false; for(var i=0;i<CAT_DOCS[c3].length;i++){ if(CAT_DOCS[c3][i].k===k3){ex=true;break;} } if(!ex) CAT_DOCS[c3].push({k:k3,ic:E[k3].ic,t:E[k3].n,tier:E[k3].tier||"standard"}); } }
      }
      return !!(DOCS.ptx_plano_pagamento_credor);
    }catch(e){ return false; }
  }
  var done=false, ticks=0;
  function run(){ if(!done){ if(inject()) done=true; } if(ticks++<40) setTimeout(run,500); }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",run); else run();
})();}catch(e){}

CHJS;
$src=str_replace($anchor,$anchor.$js,$src);
$tmp = tempnam(sys_get_temp_dir(),'pt').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-ptmore-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "  ✓ PT: +16 belge, 4 kategori eklendi\n\n✓ CH_PT_EXPAND_MORE uygulandi.\n";
