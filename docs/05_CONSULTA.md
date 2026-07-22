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

Resultados literais, lexicais e estruturais são candidatos, não conclusões. Todos os candidatos recuperados dentro do limite da consulta são entregues ao provedor de resposta, inclusive os que possam ser intrusos. A IA analisa o conjunto, cita somente os textos que sustentam o input e não elimina uma resposta válida pela presença de candidatos irrelevantes.

`simetry` e `assimetry` são operadores cognitivos internos e permanecem no contexto integral da consulta relacional. Eles orientam a compreensão da IA, mas não são tratados como expressões que a fonte documental precise conter.

Quando uma evidência derivada é localizada, `evidence_derivations` é percorrida até suas fontes primárias. A resposta recebe conteúdo literal completo; a similaridade usada para ordenar resultados é descartada.

## Parâmetros do CORE da consulta

Os limites da consulta são carregados por `config/ai.php`, consumidos pela API e aplicados por `DocumentContextRetriever`, `DocumentQueryService` e `QueryAnswerProvider`. Eles delimitam duas responsabilidades diferentes e não são intercambiáveis.

### `QUERY_MAX_EVIDENCE`

Define a quantidade máxima de evidências primárias candidatas que compõem o contexto documental entregue ao provedor de resposta.

- **Função:** limitar quantos textos documentais completos a IA poderá analisar para responder ao input.
- **Fallback do código:** `8` quando a variável não estiver definida.
- **Intervalo efetivo:** de `1` a `50`; a configuração carregada é normalizada para esse intervalo.
- **Escopo:** é um limite global por consulta, não um limite por projeto ou por obra na chamada final à IA.
- **Seleção:** as rotas direta, estrutural, ampla e semântica produzem candidatos; depois da deduplicação, somente os primeiros candidatos dentro do limite permanecem no contexto.
- **Múltiplas obras:** cada obra pode produzir seu contexto de recuperação, mas `DocumentQueryService` intercala os resultados entre as obras e encerra a composição quando atinge o limite global.
- **Rastreabilidade:** uma evidência candidata não é automaticamente uma evidência utilizada. A IA deve declarar em `used_evidence_ids` somente as candidatas que sustentam efetivamente a resposta.
- **Impacto operacional:** valores maiores ampliam cobertura e consumo de tokens; também podem aumentar a presença de candidatos intrusos. Valores menores reduzem contexto e custo, mas podem retirar evidências necessárias para cobrir todos os aspectos do input.

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
- **Ativação:** interações só são solicitadas quando `InputTypeDetector` identifica o input como relacional e o limite é maior que zero.
- **Desativação:** o valor `0` desativa a geração de interações; a resposta documental e suas citações continuam funcionando.
- **Contrato com a IA:** o valor é enviado ao `QueryAnswerProvider` como `interaction_limit`. Uma resposta acima do limite é rejeitada.
- **Validação:** cada interação aceita deve usar exatamente duas evidências pertencentes ao contexto, declaradas como utilizadas e citadas, além de conter fragmentos literais verificáveis de ambas.
- **Persistência:** o limite não cria pares antecipadamente, não executa combinação massiva e não altera o banco. As interações existem somente durante aquela consulta.
- **Independência:** aumentar esse valor não aumenta a quantidade de evidências recuperadas; essa responsabilidade pertence exclusivamente a `QUERY_MAX_EVIDENCE`.

Exemplo:

```env
QUERY_MAX_INTERACTIONS=20
```

Nesse caso, uma consulta relacional pode retornar no máximo vinte interações validadas. Consultas não relacionais continuam retornando zero interações.

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

### Aplicação na API, interface e CLI

A API e a interface web usam os valores carregados do `.env`. O comando `bin/query-document.php` parte dos mesmos valores, mas permite substituição somente para aquela execução:

```powershell
php bin/query-document.php <document-id> --live --evidence-limit=10 --interaction-limit=20 "pergunta"
```

Os argumentos da CLI não alteram o `.env`. Em processos PHP persistentes, alterações de ambiente exigem reinício do processo para garantir o recarregamento da configuração.

## Interações transitórias

Em inputs relacionais, `QueryAnswerProvider` pode declarar interações entre pares das evidências recuperadas:

- `simetry`: dois papéis `participant`;
- `assimetry`: um papel `origin` e um `destination`.

A resposta documental e suas interações transitórias são produzidas na mesma chamada do `QueryAnswerProvider`, configurada por `AI_QUERY_MODEL`. Não existe provedor ou modelo separado para interações; `QUERY_MAX_INTERACTIONS` limita quantas podem ser aceitas na resposta.

Quando a primeira geração termina por limite de saída, a regeneração compacta constitui uma segunda tentativa da mesma capacidade, não um novo estágio cognitivo ou um provedor separado. Nenhum trecho da saída parcial participa do resultado final.

Cada interação contém descrição neutra e um fragmento literal de cada participante. Ela não possui ID público, registro no banco, modelo persistido, confiança, intensidade ou pontuação.

Os nomes `simetry` e `assimetry` não precisam aparecer no documento. Primeiro, o provedor deve responder à questão substantiva com as evidências recuperadas e citadas. A classificação interna é uma camada opcional: quando não puder ser demonstrada por fragmentos literais, a resposta documental válida é preservada, a interação candidata é descartada e uma limitação relacional é apresentada.

`simetry` e `assimetry` participam da compreensão cognitiva do input e permanecem separados da comprovação de cobertura dos conceitos solicitados. Um conceito Z é informado como ausente quando não possui evidência; os operadores cognitivos não são marcados como ausentes apenas por não constarem literalmente na fonte.

## Validação

O adaptador descarta uma interação candidata e acrescenta limitação quando sua estrutura ou seus fragmentos literais não podem ser validados. Campos cognitivos proibidos continuam causando rejeição.

`DocumentQueryService` rejeita a resposta quando:

- uma evidência usada não pertence ao contexto;
- uma citação visível aponta para evidência fora do contexto;
- uma interação excede o limite da consulta;
- uma interação aparece em input não relacional;
- um participante não foi recuperado e citado;
- uma interação admitida pelo adaptador contém fragmento que não existe literalmente na evidência indicada;
- `simetry` recebe orientação;
- `assimetry` não possui origem e destino distintos.

Quando a recuperação não encontra candidato algum, o sistema informa a limitação sem chamar o provedor de resposta. Quando há candidatos, mas a análise da IA conclui que nenhum é utilizável, a saída sem `used_evidence_ids` é aceita somente se não contiver citações ou interações e trouxer limitação documental explícita.

Os identificadores de `used_evidence_ids` são validados contra o contexto. Se o provedor omitir no texto um marcador já validado, a aplicação acrescenta deterministicamente um bloco `Evidências: [EVA-E000000]`; ela não inventa nem substitui identificadores.

## Saída

O resultado separa `answer`, `evidences_used`, `simetry_interactions`, `assimetry_interactions`, pontos de roteamento e limitações. As interações descrevem o contexto daquela consulta e não alteram a memória persistente.
