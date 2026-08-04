# Consulta

## Objetivo

Responder ao usuário a partir de evidências recuperadas, preservando rastreabilidade e compreendendo interações somente quando o input e as fontes justificarem essa análise.

## Tipos de input

- **direto:** contém identificador de evidência, citação ou referência exata;
- **estrutural:** menciona obra, parte, seção, capítulo, título ou caminho;
- **conceitual:** procura um tema sem indicar sua localização;
- **relacional:** pergunta como conceitos interagem;
- **amplo:** solicita visão geral pelas camadas superiores.

Um input pode combinar tipos. Essa identificação local não avalia intenção, qualidade ou importância.

A intenção relacional é identificada localmente e sem chamada adicional de IA. O detector normaliza caixa e diacríticos, reconhece famílias morfológicas completas, como `relação`, `relacionam`, `relacionado`, `interage`, `interação` e `interaction`, e aceita operadores formais como `↔` e `→`. Essa estratégia é determinística e extensível, mas não pretende inferir universalmente a semântica de qualquer idioma: novos radicais linguísticos precisam ser adicionados explicitamente e protegidos por testes de regressão.

## Continuidade conversacional

O chat conserva visualmente todas as rodadas concluídas enquanto a interface atual permanece aberta. Cada rodada apresenta separadamente o input do usuário, a resposta documental, as evidências utilizadas e, para o superadmin, os detalhes técnicos permitidos. Uma nova consulta é acrescentada ao transcript sem substituir as anteriores, e a rolagem acompanha a rodada mais recente.

O transcript visual completo não é enviado integralmente ao backend. A partir da segunda consulta, o navegador compõe o campo `input` com a solicitação atual no início e, em seguida, no máximo as três rodadas concluídas mais recentes no formato:

```text
<input atual>

# Interação Anterior:
## Usuário
<input anterior>
## Resposta
<resposta anterior>
```

Quando mais de uma rodada é anexada, os blocos permanecem em ordem cronológica. O limite da API continua sendo 20.000 bytes; se os três blocos não couberem integralmente, o bloco mais antigo é descartado inteiro até que o payload respeite o teto.

O `SYSTEM_PROMPT` do `QueryAnswerProvider` orienta a IA a decidir se o input atual realmente continua alguma rodada anterior. Quando houver continuidade, o histórico pode resolver referências conversacionais e esclarecer o pedido atual; quando não houver, deve ser ignorado. Não existe tratamento linguístico ou semântico adicional no frontend.

Perguntas e respostas anteriores nunca são evidências documentais, mesmo quando contêm citações. Toda afirmação da nova resposta continua limitada às `primary_evidences` recuperadas para a consulta atual e submetida às mesmas validações locais de IDs, citações e fragmentos.

O botão **Reiniciar chat** limpa o transcript, as rodadas disponíveis para contexto e o input atual, mas preserva a seleção de projetos e obras. O estado conversacional reside apenas na memória JavaScript da página: não é persistido no banco, em auditoria ou em `sessionStorage`, e é reiniciado em logout, novo login ou recarregamento da aplicação.

## Cobertura parcial do input

O usuário pode combinar livremente conceitos e relações. A suficiência documental é avaliada por aspecto: se uma relação solicitada envolve X, Y e Z, mas somente X e Y possuem evidências recuperadas, o sistema responde a relação entre X e Y com citações e informa que não encontrou evidência suficiente para Z.

A ausência de um aspecto nunca autoriza conhecimento externo e não apaga os demais aspectos sustentados. O bloqueio integral antes da geração ocorre somente quando nenhuma evidência primária é recuperada.

## Recuperação

Consultas diretas, estruturais e amplas percorrem a árvore e suas evidências primárias. Consultas conceituais e relacionais geram um embedding transitório do input e pesquisam evidências `primary` e `derived`.

Em consultas conceituais ou relacionais, uma correspondência textual exata não encerra a recuperação. A evidência literal entra primeiro como `core`, preservando a resposta direta como âncora, e o mesmo input segue para o Top-k vetorial e para o CIE. As fontes primárias semanticamente eleitas completam o contexto dentro de `QUERY_MAX_EVIDENCE`, sem sair das obras selecionadas. Consultas exclusivamente diretas, estruturais ou amplas continuam sem consumir embedding de consulta.

Resultados literais, lexicais e estruturais são candidatos, não conclusões. Nessas rotas não vetoriais, a aplicação forma o contexto final dentro do limite e o entrega como eleição integral. O provedor deve incorporar todas as evidências recebidas sem transformá-las em conclusões além de seu conteúdo literal.

`simetry` e `assimetry` são operadores cognitivos internos e permanecem no contexto integral da consulta relacional. Eles orientam a compreensão da IA, mas não são tratados como expressões que a fonte documental precise conter.

Na recuperação semântica, o Retriever ordena até `QUERY_CANDIDATE_LIMIT` candidatos e o Context Intelligence Engine calcula média, desvio padrão populacional e coeficiente de variação. Candidatos abaixo da média são descartados. O núcleo acima ou igual a `μ + σ` lidera o contexto final; a faixa entre `μ` e `μ + σ` entra como análise complementar obrigatória. Se o núcleo estiver vazio, a convergência assume o papel principal. O processo é determinístico e não executa reranking por IA.

Quando uma evidência derivada é selecionada pelo CIE, `evidence_derivations` é percorrida até suas fontes primárias. A resolução distribui o limite entre os candidatos eleitos e ordena as fontes de cada linhagem pela similaridade da consulta, evitando esgotar o contexto na primeira linhagem ampla. A resposta recebe conteúdo literal completo e o papel `core` ou `convergence`; similaridades e estatísticas não são enviadas como autoridade documental nem persistidas.

## Governança de respostas por projeto

O superadmin pode preencher o campo **Perfil de respostas** de um projeto com orientações complementares de público, papel de auxílio, vocabulário, tom, foco ou apresentação. O perfil não altera o acervo nem substitui o `SYSTEM_PROMPT` padrão: regras de evidência primária, citações, limitações, validação de interações e saída JSON continuam prioritárias.

A ativação é determinada pelo escopo explicitamente enviado pelo chat:

- marcar o projeto na raiz ativa seu perfil e inclui todas as obras prontas vinculadas;
- marcar somente uma obra, ainda que ela pertença a um projeto, não ativa o perfil desse projeto;
- marcar vários projetos ativa separadamente os perfis configurados de cada um;
- marcar um projeto e uma obra avulsa aplica o perfil somente à parcela documental do projeto.

O backend resolve as obras de todos os escopos autorizados, reúne seus IDs e aplica deduplicação antes da recuperação. Assim, se os projetos A e B possuírem a mesma obra e ambos forem marcados, essa obra participa da consulta uma única vez. A deduplicação documental não desativa governança: os perfis de A e B permanecem presentes no prompt, identificados pelos respectivos projetos e conjuntos de obras.

Perfis compatíveis podem ser combinados. Se dois perfis incidirem sobre o mesmo aspecto de uma obra compartilhada com orientações incompatíveis, o provedor deve preservar as regras-base e usar formulação neutra, sem escolher arbitrariamente um perfil ou ampliar o conteúdo das evidências.

Uma obra selecionada individualmente pode aparecer em diferentes projetos concedidos ao usuário sem revelar ou ativar implicitamente nenhum perfil. Essa separação impede que relações administrativas invisíveis mudem o comportamento da resposta sem uma seleção explícita do projeto.

## Parâmetros do CORE da consulta

Os limites da consulta são carregados por `config/ai.php`, consumidos pela API e aplicados por `DocumentContextRetriever`, `DocumentQueryService` e `QueryAnswerProvider`. Eles delimitam três responsabilidades diferentes e não são intercambiáveis.

### `QUERY_CANDIDATE_LIMIT`

Define o Top-k vetorial analisado pelo CIE em cada documento para consultas conceituais ou relacionais.

- **Fallback do código:** `20`.
- **Intervalo efetivo:** de `1` a `200`.
- **Escopo:** por documento e somente em recuperação semântica.
- **Função:** definir a população usada nos cálculos de `μ`, `σ` e `CV`; não define quantos textos chegam ao provedor.
- **Persistência:** candidatos, similaridades e análise permanecem transitórios.

```env
QUERY_CANDIDATE_LIMIT=20
```

### `QUERY_MAX_EVIDENCE`

Define a quantidade máxima de evidências primárias candidatas que compõem o contexto documental entregue ao provedor de resposta.

- **Função:** limitar quantos textos documentais completos a IA poderá analisar para responder ao input.
- **Fallback do código:** `8` quando a variável não estiver definida.
- **Intervalo efetivo:** de `1` a `50`; a configuração carregada é normalizada para esse intervalo.
- **Escopo:** é um limite global por consulta, não um limite por projeto ou por obra na chamada final à IA.
- **Seleção:** nas rotas semânticas, o limite é aplicado às fontes primárias resolvidas depois do CIE; nas demais rotas, é aplicado diretamente aos candidatos hierárquicos ou literais.
- **Múltiplas obras:** cada obra pode produzir seu contexto de recuperação, mas `DocumentQueryService` intercala os resultados entre as obras e encerra a composição quando atinge o limite global.
- **Rastreabilidade:** as evidências finais já foram eleitas deterministicamente. A IA deve reproduzir o conjunto integral em `used_evidence_ids`, usar o núcleo como referência principal e incorporar cada convergência à análise complementar; qualquer redução, omissão textual ou inventário isolado de citações é rejeitado.
- **Impacto operacional:** valores maiores ampliam cobertura e consumo de tokens. Valores menores reduzem contexto e custo, mas podem retirar evidências necessárias para cobrir todos os aspectos do input.

Exemplo:

```env
QUERY_MAX_EVIDENCE=10
```

Nesse caso, no máximo dez evidências candidatas distintas são entregues ao `QueryAnswerProvider`, mesmo quando o usuário seleciona várias obras.

### `QUERY_MAX_INTERACTIONS`

Define a quantidade máxima de interações transitórias `simetry` e `assimetry` que podem ser aceitas na resposta de uma consulta relacional.

- **Função:** limitar a saída relacional produzida sobre as evidências recuperadas e citadas.
- **Fallback do código:** `20` quando a variável não estiver definida.
- **Intervalo efetivo:** de `0` a `100`; a configuração carregada é normalizada para esse intervalo.
- **Ativação:** interações são analisadas sempre que há pelo menos duas evidências eleitas e o limite é maior que zero, independentemente do tipo inicial do input.
- **Desativação:** o valor `0` desativa a geração de interações; a resposta documental e suas citações continuam funcionando.
- **Contrato com a IA:** o valor é enviado ao `QueryAnswerProvider` como `interaction_limit`. Uma resposta acima do limite é rejeitada.
- **Validação:** cada interação aceita deve usar exatamente duas evidências pertencentes ao contexto, declaradas como utilizadas e citadas, além de conter fragmentos literais verificáveis de ambas.
- **Persistência:** o limite não cria pares antecipadamente, não executa combinação massiva e não altera o banco. As interações existem somente durante aquela consulta.
- **Independência:** aumentar esse valor não aumenta o Top-k analisado pelo CIE nem o contexto documental; essas responsabilidades pertencem a `QUERY_CANDIDATE_LIMIT` e `QUERY_MAX_EVIDENCE`.

Exemplo:

```env
QUERY_MAX_INTERACTIONS=20
```

Nesse caso, qualquer consulta com ao menos duas evidências pode retornar no máximo vinte interações validadas, desde que a relação seja demonstrada literalmente.

### `AI_QUERY_MAX_OUTPUT_TOKENS`

Define o teto de tokens de saída em cada tentativa do `QueryAnswerProvider`.

- **Função:** reservar espaço suficiente para fechar o contrato JSON de resposta, inclusive em consultas relacionais.
- **Fallback do código:** `1800` quando a variável não estiver definida.
- **Intervalo efetivo:** de `100` a `3000`; a configuração carregada é normalizada para esse intervalo.
- **Consumo:** o valor é um teto, não uma quantidade obrigatoriamente gerada ou cobrada. A resposta pode terminar antes.
- **Comando de saída:** o provedor recebe instruções para preservar todos os aspectos sustentados, limitar repetição, usar fragmentos literais mínimos suficientes e não tratar `interaction_limit` como meta de preenchimento.
- **Truncamento:** uma resposta com `finish_reason=length` nunca é decodificada, reparada ou aceita parcialmente.
- **Recuperação:** ocorre no máximo uma regeneração integral, no mesmo teto configurado, com comando adicional de compacidade. Se a segunda tentativa também for truncada, a consulta é encerrada com erro explícito.
- **Segurança operacional:** não existe repetição ilimitada nem aumento automático acima do teto definido no `.env`.

Exemplo:

```env
AI_QUERY_MAX_OUTPUT_TOKENS=1800
```

O valor foi calibrado a partir da matriz relacional real. Reduzi-lo exige nova validação ao vivo porque o JSON inclui resposta, citações, interações, fragmentos literais e limitações.

### Regeneração silenciosa por falha de validação

Quando uma geração chega completa ao backend, mas viola o contrato local de evidências, citações, incorporação analítica ou interações, `DocumentQueryService` descarta integralmente essa saída e solicita nova geração com o mesmo contexto eleito. A partir da segunda tentativa, o provedor recebe somente um código técnico conhecido da falha e, quando uma evidência eleita não foi incorporada, o respectivo identificador público. São permitidas no máximo três tentativas totais de resposta validada dentro da mesma requisição da API.

As tentativas rejeitadas não aparecem no transcript, não alteram a eleição do CIE e não reutilizam texto parcial. O feedback corretivo não contém input, conteúdo documental, resposta rejeitada, similaridade, peso ou valor subjetivo. Enquanto houver tentativa disponível, a interface permanece em **Consultando evidências…** e não exibe o identificador nem a regra técnica que causou a rejeição.

Uma tentativa posterior válida substitui completamente as anteriores. Somente depois de três falhas consecutivas de validação a API retorna erro ao navegador, usando mensagem genérica sem identificador de evidência. O esgotamento é registrado no log por categoria segura, quantidade de tentativas e `request_id`; o último motivo técnico permanece encadeado internamente sem ser exposto ao usuário.

Esse mecanismo é distinto da recuperação de truncamento do provedor. Uma tentativa individual ainda pode admitir a única regeneração compacta específica para `finish_reason=length`; ela não autoriza repetição ilimitada nem altera o teto configurado.

### Aplicação na API, interface e CLI

A API e a interface web usam os valores carregados do `.env`. O comando `bin/query-document.php` parte dos mesmos valores, mas permite substituição somente para aquela execução:

```powershell
php bin/query-document.php <document-id> --live --evidence-limit=10 --interaction-limit=20 "pergunta"
```

Os argumentos da CLI não alteram o `.env`. Em processos PHP persistentes, alterações de ambiente exigem reinício do processo para garantir o recarregamento da configuração.

## Interações transitórias

Sempre que houver ao menos duas evidências eleitas, `QueryAnswerProvider` deve avaliar interações entre seus pares:

- `simetry`: dois papéis `participant`;
- `assimetry`: um papel `origin` e um `destination`.

A resposta documental e suas interações transitórias são produzidas na mesma chamada do `QueryAnswerProvider`, configurada por `AI_QUERY_MODEL`. Não existe provedor ou modelo separado para interações; `QUERY_MAX_INTERACTIONS` limita quantas podem ser aceitas na resposta.

Quando a primeira geração termina por limite de saída, a regeneração compacta constitui uma segunda tentativa da mesma capacidade, não um novo estágio cognitivo ou um provedor separado. Nenhum trecho da saída parcial participa do resultado final.

Cada interação contém descrição neutra e um fragmento literal de cada participante. Ela não possui ID público, registro no banco, modelo persistido, confiança, intensidade ou pontuação.

Os nomes `simetry` e `assimetry` não precisam aparecer no documento. Primeiro, o provedor deve responder à questão substantiva com as evidências recuperadas e citadas. A avaliação interna é obrigatória, mas a emissão de uma interação depende de demonstração literal. Quando ela não puder ser validada, a resposta documental é preservada, `interactions` permanece vazio e a limitação correspondente é apresentada.

`simetry` e `assimetry` participam da compreensão cognitiva do input e permanecem separados da comprovação de cobertura dos conceitos solicitados. Um conceito Z é informado como ausente quando não possui evidência; os operadores cognitivos não são marcados como ausentes apenas por não constarem literalmente na fonte.

## Validação

O adaptador descarta uma interação candidata e acrescenta limitação quando sua estrutura ou seus fragmentos literais não podem ser validados. Campos cognitivos proibidos continuam causando rejeição.

`DocumentQueryService` rejeita a resposta quando:

- uma evidência usada não pertence ao contexto;
- uma citação visível aponta para evidência fora do contexto;
- uma interação excede o limite da consulta;
- um participante não foi recuperado e citado;
- uma interação admitida pelo adaptador contém fragmento que não existe literalmente na evidência indicada;
- `simetry` recebe orientação;
- `assimetry` não possui origem e destino distintos.

Quando a recuperação não encontra evidência alguma, o sistema informa a limitação sem chamar o provedor de resposta. Quando há contexto eleito, `used_evidence_ids` deve reproduzi-lo integralmente e na mesma ordem; a IA não possui autoridade para refazer a eleição.

Os identificadores de `used_evidence_ids` são validados contra o contexto, mas sua presença formal não basta. Cada evidência eleita deve aparecer citada em uma frase ou parágrafo analítico que exponha sua contribuição. A aplicação não acrescenta citações ausentes e rejeita listas isoladas como `Evidências: [EVA-E000000]`, pois elas não demonstram incorporação analítica.

## Saída

O resultado separa `answer`, `evidences_used`, `evidence_selection`, `simetry_interactions`, `assimetry_interactions`, `routing_points`, `context_intelligence` e `limitations`. Cada evidência utilizada também expõe `selection_region`; `evidence_selection` lista os IDs de núcleo e convergência. `context_intelligence` fica vazio em rotas não semânticas; quando presente, expõe a análise transitória por documento com `μ`, `σ`, `CV`, limites e regiões da distribuição. Nem essa análise nem as interações alteram a memória persistente.
