<?php
/**
 * ChatHelp — apply-widecat-pt (CH_WIDECAT_PT) — Genis katalog Batch 4 (FINAL)
 *  PT: 36 -> ~74 belge (+38) · 4 yeni kategori (Familia/Auto/Saude/Financas)
 *  Hukuk: Codigo Civil, NRAU (Lei 6/2006), Codigo do Trabalho (art. 341 CT),
 *  DL 84/2021 (garantia 3 anos), DL 24/2014 (livre resolucao 14 dias),
 *  CPA (reclamacao 15 dias / recurso 30 dias), DL 133/2009, Lei 25/2012 (DAV).
 *  Not: el yazisi vasiyet PT'de gecersiz — vasiyet yalnizca noterde (bilgi
 *  notu olarak eklendi, belge uretimi yok).
 * KULLANIM: pull-updates.php?files=apply-widecat-pt.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-widecat-pt BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_WIDECAT_PT')!==false) exit("Zaten ekli (CH_WIDECAT_PT).\n");

$fail=[];

/* ═══ [1] PT: kategoriler + X_ORDER ═══ */
$o1 = "    pt_servicos:{n:\"Serviços Públicos\",ic:\"🏛️\"}\n  };\n  var X_ORDER=[\"pt_rescisoes\",\"pt_habitacao\",\"pt_trabalho\",\"pt_consumidor\",\"pt_contratos\",\"pt_servicos\"];";
$n1 = "    pt_servicos:{n:\"Serviços Públicos\",ic:\"🏛️\"},\n"
    . "    pt_familia:{n:\"Família e Proteção\",ic:\"👪\"},\n"
    . "    pt_auto:{n:\"Auto e Mobilidade\",ic:\"🚗\"},\n"
    . "    pt_saude:{n:\"Saúde e Prestações\",ic:\"🏥\"},\n"
    . "    pt_financas:{n:\"Banca e Finanças\",ic:\"💶\"}\n  };\n"
    . "  var X_ORDER=[\"pt_rescisoes\",\"pt_habitacao\",\"pt_trabalho\",\"pt_consumidor\",\"pt_contratos\",\"pt_servicos\",\"pt_familia\",\"pt_auto\",\"pt_saude\",\"pt_financas\"]; /*CH_WIDECAT_PT*/";
$c=0; $src=str_replace($o1,$n1,$src,$c);
echo ($c===1?"  ✓ [1] PT +4 kategori\n":"  ✗ [1] PT XC anchor ($c)\n"); if($c!==1)$fail[]=1;

/* ═══ [2] PT: +38 belge ═══ */
$ptDocs = <<<'JS'
    /* CH_WIDECAT_PT — catalogo amplo */
    pt_rescisao_energia:{ic:"⚡",n:"Rescisão contrato de eletricidade/gás",cat:"pt_rescisoes",tier:"einfach",q:[Pq("fornecedor","Fornecedor: nome e morada",true),Pq("cliente","Nº de cliente / CPE (luz) ou CUI (gás)",true),Pq("data","Data pretendida",false,["Primeira data possível","Data concreta"]),Pq("dados","Os seus dados: nome e morada",true)]},
    pt_rescisao_assinatura:{ic:"📰",n:"Cancelamento de assinatura (revista/serviço)",cat:"pt_rescisoes",tier:"einfach",q:[Pq("empresa","Empresa: nome e morada",true),Pq("numero","Nº de assinatura/cliente",true),Pq("data","Data de cancelamento",true),Pq("dados","Os seus dados: nome e morada",true)]},
    pt_saida_associacao:{ic:"🤝",n:"Saída de associação/clube",cat:"pt_rescisoes",tier:"einfach",q:[Pq("associacao","Associação: nome e morada",true),Pq("socio","Nº de sócio (se existir)",false),Pq("data","Data de efeito (segundo os estatutos)",true),Pq("dados","Os seus dados: nome e morada",true)]},
    pt_revogacao_debito:{ic:"🏦",n:"Revogação de autorização de débito direto",cat:"pt_rescisoes",tier:"einfach",q:[Pq("credor","Credor: nome e morada",true),Pq("mandato","Referência do mandato / nº de cliente",true),Pq("banco","Informar também o banco?",false,["Sim","Não"]),Pq("dados","Os seus dados: nome e morada",true)]},
    pt_reducao_renda:{ic:"📉",n:"Redução de renda por defeitos do locado",cat:"pt_habitacao",tier:"standard",q:[Pq("senhorio","Senhorio: nome e morada",true),Pq("imovel","Morada do imóvel",true),Pq("defeito","Defeito (desde quando, extensão)",true),Pq("comunicado","Já comunicado?",true,["Sim, por escrito","Sim, oralmente","Não"]),Pq("reducao","Redução pretendida (% ou €)",false),Pq("dados","Os seus dados: nome",true)]},
    pt_comunicacao_defeitos:{ic:"🔧",n:"Comunicação de defeitos ao senhorio",cat:"pt_habitacao",tier:"einfach",q:[Pq("senhorio","Senhorio: nome e morada",true),Pq("imovel","Morada do imóvel",true),Pq("defeito","Defeito e consequências",true),Pq("prazo","Prazo para reparação (p. ex. 15 dias)",true),Pq("dados","Os seus dados: nome",true)]},
    pt_devolucao_caucao:{ic:"💰",n:"Devolução da caução",cat:"pt_habitacao",tier:"einfach",q:[Pq("senhorio","Senhorio: nome e morada",true),Pq("imovel","Morada do (antigo) imóvel",true),Pq("entrega","Data de entrega das chaves",true),Pq("caucao","Valor da caução (€)",true),Pq("iban","O seu IBAN",true),Pq("dados","Os seus dados: nome e nova morada",true)]},
    pt_denuncia_arrendamento:{ic:"🏠",n:"Denúncia do arrendamento (inquilino, NRAU)",cat:"pt_habitacao",tier:"einfach",q:[Pq("senhorio","Senhorio: nome e morada",true),Pq("imovel","Morada do imóvel",true),Pq("data","Data de saída (aviso prévio legal: 120/60 dias conforme a duração)",true),Pq("dados","Os seus dados: nome",true)]},
    pt_contestacao_aumento:{ic:"📈",n:"Contestação de aumento de renda",cat:"pt_habitacao",tier:"standard",q:[Pq("senhorio","Senhorio: nome e morada",true),Pq("imovel","Morada do imóvel",true),Pq("aumento","Aumento comunicado (de € para €, a partir de)",true),Pq("motivos","Porque discorda (nas suas palavras)",true),Pq("dados","Os seus dados: nome",true)]},
    pt_certificado_trabalho:{ic:"📜",n:"Pedido de certificado de trabalho (art. 341 CT)",cat:"pt_trabalho",tier:"einfach",q:[Pq("empregador","Empregador: nome e morada",true),Pq("periodo","Trabalhou de – até",true),Pq("funcao","Função",true),Pq("dados","Os seus dados: nome e morada",true)]},
    pt_denuncia_trabalhador:{ic:"📤",n:"Denúncia do contrato pelo trabalhador",cat:"pt_trabalho",tier:"einfach",q:[Pq("empregador","Empregador: nome e morada",true),Pq("data","Último dia (aviso prévio: 30/60 dias conforme antiguidade)",true),Pq("certificado","Pedir logo o certificado de trabalho?",false,["Sim","Não"]),Pq("dados","Os seus dados: nome e morada",true)]},
    pt_salarios_atraso:{ic:"💶",n:"Reclamação de salários em atraso",cat:"pt_trabalho",tier:"standard",q:[Pq("empregador","Empregador: nome e morada",true),Pq("valores","Valores em dívida (meses, montantes)",true),Pq("prazo","Prazo de pagamento (p. ex. 10 dias; depois ACT/tribunal)",true),Pq("dados","Os seus dados: nome e morada",true)]},
    pt_licenca_parental:{ic:"👶",n:"Comunicação de licença parental",cat:"pt_trabalho",tier:"standard",q:[Pq("empregador","Empregador: nome e morada",true),Pq("crianca","Criança: data de nascimento (ou prevista)",true),Pq("periodo","Período pretendido e modalidade",true),Pq("dados","Os seus dados: nome",true)]},
    pt_garantia_bens:{ic:"📦",n:"Garantia legal: bem com defeito (DL 84/2021, 3 anos)",cat:"pt_consumidor",tier:"standard",q:[Pq("vendedor","Vendedor: nome e morada",true),Pq("compra","Comprado em / nº de fatura",true),Pq("produto","Produto e preço",true),Pq("defeito","Defeito detetado",true),Pq("pretensao","A sua pretensão",true,["Reparação","Substituição","Redução do preço","Resolução (reembolso)"]),Pq("dados","Os seus dados: nome e morada",true)]},
    pt_livre_resolucao:{ic:"🛒",n:"Livre resolução compra online (14 dias, DL 24/2014)",cat:"pt_consumidor",tier:"einfach",q:[Pq("vendedor","Vendedor: nome e morada/e-mail",true),Pq("encomenda","Nº de encomenda / data",true),Pq("recebido","Recebido em",true),Pq("devolucao","Devolução por",false,["Envio","Recolha pretendida"]),Pq("dados","Os seus dados: nome e morada",true)]},
    pt_indemnizacao_voo:{ic:"✈️",n:"Indemnização de voo (Reg. CE 261/2004)",cat:"pt_consumidor",tier:"standard",q:[Pq("companhia","Companhia aérea",true),Pq("voo","Nº do voo e data",true),Pq("rota","Rota (de – para)",true),Pq("problema","O que aconteceu?",true,["Atraso de 3h ou mais","Cancelamento","Recusa de embarque (overbooking)"]),Pq("reserva","Referência da reserva",true),Pq("dados","Os seus dados: nome e morada",true)]},
    pt_contestacao_cobranca:{ic:"📮",n:"Contestação de empresa de cobranças",cat:"pt_consumidor",tier:"standard",q:[Pq("empresa","Empresa: nome e morada",true),Pq("processo","Referência do processo",true),Pq("divida","Montante exigido e alegada origem",true),Pq("excecao","A sua contestação",true,["Dívida inexistente","Já paga","Encargos excessivos","Prescrita"]),Pq("dados","Os seus dados: nome e morada",true)]},
    pt_compra_venda:{ic:"🛍️",n:"Compra e venda entre particulares (bem móvel)",cat:"pt_contratos",tier:"einfach",q:[Pq("papel","É",true,["Vendedor","Comprador"]),Pq("parte","Outra parte: nome e morada",true),Pq("bem","Bem vendido (descrição precisa)",true),Pq("preco","Preço (€) e forma de pagamento",true),Pq("entrega","Data/local de entrega",true),Pq("garantia","Exclusão de garantia (venda entre particulares)?",false,["Sim","Não"])]},
    pt_mutuo_particulares:{ic:"💸",n:"Mútuo entre particulares (declaração de dívida)",cat:"pt_contratos",tier:"standard",q:[Pq("papel","É",true,["Mutuante (quem empresta)","Mutuário (quem recebe)"]),Pq("parte","Outra parte: nome e morada",true),Pq("montante","Montante (€)",true),Pq("reembolso","Reembolso (prestações/vencimento)",true),Pq("juros","Juros",false,["Sem juros","Com juros (% ao ano)"])]},
    pt_comodato:{ic:"🤲",n:"Contrato de comodato (empréstimo gratuito)",cat:"pt_contratos",tier:"einfach",q:[Pq("papel","É",true,["Comodante","Comodatário"]),Pq("parte","Outra parte: nome e morada",true),Pq("bem","Bem emprestado (estado)",true),Pq("prazo","Prazo (de – até)",true),Pq("dano","Indemnização em caso de dano?",false,["Sim, valor atual","Não"])]},
    pt_empreitada_pequena:{ic:"🔨",n:"Contrato de empreitada (pequenas obras)",cat:"pt_contratos",tier:"standard",q:[Pq("papel","É",true,["Dono da obra","Empreiteiro"]),Pq("parte","Outra parte: nome e morada",true),Pq("obra","Descrição dos trabalhos",true),Pq("preco","Preço (€, global ou por medida)",true),Pq("prazo","Prazo de conclusão",true),Pq("materiais","Quem fornece os materiais?",false,["Empreiteiro","Dono da obra"])]},
    pt_reclamacao_cpa:{ic:"⚖️",n:"Reclamação/recurso administrativo (CPA)",cat:"pt_servicos",tier:"komplex",q:[Pq("orgao","Órgão/entidade: nome e morada",true),Pq("ato","Ato/decisão de (referência)",true),Pq("notificado","Notificado em (reclamação: 15 dias / recurso: 30 dias)",true),Pq("via","Via escolhida",true,["Reclamação (ao autor do ato)","Recurso hierárquico"]),Pq("fundamentos","Porque é errada a decisão (nas suas palavras)",true),Pq("dados","Os seus dados: nome e morada",true)]},
    pt_acesso_documentos:{ic:"🗂️",n:"Acesso a documentos administrativos (LADA)",cat:"pt_servicos",tier:"einfach",q:[Pq("entidade","Entidade: nome e morada",true),Pq("documentos","Documentos pedidos (o mais preciso possível)",true),Pq("forma","Forma",false,["E-mail","Cópia em papel","Consulta presencial"]),Pq("dados","Os seus dados: nome e morada",true)]},
    pt_prestacoes_at:{ic:"🧾",n:"Pagamento em prestações — Autoridade Tributária",cat:"pt_servicos",tier:"standard",q:[Pq("referencia","Referência da dívida / nota de cobrança",true),Pq("montante","Montante em dívida (€)",true),Pq("prestacoes","Nº de prestações propostas",true),Pq("motivo","Dificuldades (breve descrição)",true),Pq("dados","Os seus dados: nome, morada, NIF",true)]},
    pt_procuracao:{ic:"📝",n:"Procuração (simples)",cat:"pt_familia",tier:"einfach",q:[Pq("procurador","Procurador: nome, data de nascimento, morada",true),Pq("objeto","Para que vale a procuração?",true,["Um assunto concreto","Serviços públicos","Atos bancários","Geral"]),Pq("detalhes","Descrição mais precisa",true),Pq("prazo","Validade",false,["Sem prazo","Até (data)"]),Pq("dados","Os seus dados: nome, data de nascimento, morada, NIF",true)]},
    pt_dav:{ic:"🏥",n:"Diretivas antecipadas de vontade (DAV, Lei 25/2012)",cat:"pt_familia",tier:"komplex",q:[Pq("vontades","Tratamentos que recusa/aceita",true),Pq("situacoes","Para que situações clínicas",true),Pq("procurador","Procurador de cuidados de saúde (recomendado): nome e contactos",false),Pq("dados","Os seus dados: nome, data de nascimento, morada",true),Pq("nota","Nota: registo no RENTEV (centro de saúde) ou por documento autenticado",false)]},
    pt_pensao_alimentos:{ic:"👨‍👧",n:"Interpelação: pensão de alimentos em atraso",cat:"pt_familia",tier:"standard",q:[Pq("devedor","Devedor: nome e morada",true),Pq("titulo","Título (sentença/acordo de)",true),Pq("atraso","Em atraso (meses, montante)",true),Pq("prazo","Prazo de pagamento (p. ex. 10 dias; depois FGADM/tribunal)",true),Pq("dados","Os seus dados: nome e morada",true)]},
    pt_autorizacao_menor:{ic:"🧳",n:"Autorização de saída de menor para o estrangeiro",cat:"pt_familia",tier:"einfach",q:[Pq("menor","Menor: nome, data de nascimento, nº de documento",true),Pq("acompanhante","Acompanhante: nome, nº de documento",true),Pq("viagem","País e datas da viagem",true),Pq("dados","Progenitor(es) que autoriza(m): nome, nº de documento",true),Pq("nota","Nota: assinatura reconhecida (notário/advogado/junta) recomendada",false)]},
    pt_venda_automovel:{ic:"🚗",n:"Venda de automóvel entre particulares",cat:"pt_auto",tier:"standard",q:[Pq("papel","É",true,["Vendedor","Comprador"]),Pq("parte","Outra parte: nome, morada, NIF",true),Pq("veiculo","Marca, modelo, matrícula, nº de quadro (VIN)",true),Pq("km","1ª matrícula e quilometragem",true),Pq("preco","Preço (€) e pagamento (registo em 60 dias)",true),Pq("inspecao","Inspeção (IPO) válida até",false),Pq("defeitos","Defeitos conhecidos",false)]},
    pt_defesa_contraordenacao:{ic:"🚦",n:"Defesa em contraordenação rodoviária",cat:"pt_auto",tier:"komplex",q:[Pq("auto","Auto de contraordenação nº, de (entidade: ANSR/PSP/GNR)",true),Pq("notificado","Notificado em (respeitar o prazo indicado no auto!)",true),Pq("infracao","Infração imputada",true),Pq("defesa","A sua defesa (nas suas palavras)",true),Pq("dados","Os seus dados: nome e morada",true)]},
    pt_participacao_sinistro:{ic:"💥",n:"Participação de sinistro à seguradora",cat:"pt_auto",tier:"einfach",q:[Pq("seguradora","Seguradora: nome e morada",true),Pq("apolice","Nº da apólice",true),Pq("sinistro","Sinistro: data, local, circunstâncias",true),Pq("danos","Danos no veículo",true),Pq("terceiro","Terceiro envolvido (nome, matrícula, seguradora)",false),Pq("dados","Os seus dados: nome e morada",true)]},
    pt_interpelacao_danos:{ic:"🧾",n:"Interpelação para reparação de danos (art. 483.º CC)",cat:"pt_auto",tier:"standard",q:[Pq("responsavel","Responsável: nome e morada",true),Pq("facto","Facto danoso: data, local, circunstâncias",true),Pq("danos","Danos sofridos (comprovados, montantes)",true),Pq("prazo","Prazo de resposta (p. ex. 30 dias)",true),Pq("dados","Os seus dados: nome e morada",true)]},
    pt_reclamacao_ss:{ic:"🏥",n:"Reclamação — Segurança Social",cat:"pt_saude",tier:"komplex",q:[Pq("decisao","Decisão/ofício de (referência)",true),Pq("materia","Matéria",true,["Subsídio de desemprego","Baixa médica","Abono de família","Pensão","Outra"]),Pq("fundamentos","Porque é errada a decisão",true),Pq("dados","Os seus dados: nome, morada, NISS",true)]},
    pt_reembolso_saude:{ic:"🩺",n:"Pedido de reembolso de despesas de saúde",cat:"pt_saude",tier:"einfach",q:[Pq("entidade","Entidade/seguro: nome e morada",true),Pq("ato","Ato: prestador, data, tipo",true),Pq("montante","Montante pago (€, fatura anexa)",true),Pq("iban","O seu IBAN",true),Pq("dados","Os seus dados: nome, morada, nº de utente/apólice",true)]},
    pt_apoio_dependencia:{ic:"🧓",n:"Requerimento de apoio (complemento por dependência)",cat:"pt_saude",tier:"standard",q:[Pq("pessoa","Pessoa dependente: nome, data de nascimento, NISS",true),Pq("situacao","Situação de saúde/necessidade de apoio (breve)",true),Pq("dados","Requerente: nome e morada",true)]},
    pt_encerramento_conta:{ic:"🏦",n:"Encerramento de conta bancária",cat:"pt_financas",tier:"einfach",q:[Pq("banco","Banco: nome e morada",true),Pq("iban","IBAN da conta a encerrar",true),Pq("saldo","Saldo a transferir para (IBAN)",true),Pq("data","Produção de efeitos",false,["Imediata","Na data"]),Pq("dados","Os seus dados: nome e morada",true)]},
    pt_reembolso_antecipado:{ic:"💳",n:"Reembolso antecipado de crédito (DL 133/2009)",cat:"pt_financas",tier:"standard",q:[Pq("credor","Banco/financeira: nome e morada",true),Pq("contrato","Contrato nº de",true),Pq("tipo","Reembolso",true,["Total","Parcial (montante)"]),Pq("data","Data pretendida",true),Pq("dados","Os seus dados: nome e morada",true)]},
    pt_operacao_nao_autorizada:{ic:"🧾",n:"Contestação de operação não autorizada",cat:"pt_financas",tier:"standard",q:[Pq("banco","Banco/emitente: nome e morada",true),Pq("operacao","Operação contestada (data, montante, beneficiário)",true),Pq("motivo","Motivo",true,["Nunca autorizada","Montante errado","Duplo débito","Fraude/phishing"]),Pq("bloqueio","Cartão já cancelado?",false,["Sim","Não"]),Pq("dados","Os seus dados: nome e morada",true)]},
JS;
$o2 = "var CCODE=\"PT\", DOCWORD=\"documentos\";\n\n  var D={";
$n2 = "var CCODE=\"PT\", DOCWORD=\"documentos\";\n\n  var D={\n".$ptDocs;
$c=0; $src=str_replace($o2,$n2,$src,$c);
echo ($c===1?"  ✓ [2] PT +38 belge\n":"  ✗ [2] PT D anchor ($c)\n"); if($c!==1)$fail[]=2;

if ($fail) { echo "\nHATA: adimlar eslesmedi: ".implode(', ',$fail)." — index.php DEGISTIRILMEDI.\n"; exit; }

$tmp = tempnam(sys_get_temp_dir(),'w4').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-widecat4-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
$nPT = preg_match_all('/\bpt_[a-z0-9_]+:\{ic:/', $src, $m);
echo "\n✓ CH_WIDECAT_PT uygulandi. Yeni toplam: PT=$nPT belge (+4 kategori). GENIS KATALOG TAMAMLANDI.\n";
