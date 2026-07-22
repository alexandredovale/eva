# Filosofia do EVA — Evidence Algorithm

Versão: 3.1  
Estado conceitual: arquitetura vigente

## 1. Proposição central

O EVA é um sistema de memória documental verificável. Sua unidade de confiança não é a resposta de um modelo, uma associação probabilística isolada nem um grafo autônomo: é a evidência preservada com origem, contexto estrutural e linhagem de derivação.

A memória pertence ao conjunto documental persistido e às regras determinísticas que governam sua recuperação. Modelos podem auxiliar a sintetizar, representar semanticamente e formular respostas, mas não recebem autoridade para redefinir a fonte, criar evidências ou alterar a memória durante uma consulta.

O banco de dados é o meio de persistência dessa memória; não é, por si só, sua autoridade epistemológica. A autoridade vem da correspondência verificável entre cada evidência e o documento que a originou.

## 2. Evidência antes de interpretação

O EVA distingue somente duas classes persistentes de evidência:

- **evidência primária**: conteúdo literal de um nó documental, preservado como unidade semântica completa;
- **evidência derivada**: síntese hierárquica produzida a partir de evidências já conhecidas, sempre ligada às suas fontes por uma linhagem explícita.

Evidências derivadas ampliam a capacidade de localização e compreensão do documento, mas não substituem evidências primárias como fundamento final de uma resposta. Quando uma síntese conduz a uma região relevante, o sistema retorna às fontes primárias que a sustentam.

Essa distinção contém uma regra de prudência: uma interpretação pode orientar a busca, mas a afirmação apresentada ao usuário deve permanecer ancorada no texto documental recuperado.

## 3. Organização semântica, não fragmentação arbitrária

Documentos possuem organização própria: títulos, seções, parágrafos, listas, propriedades, elementos e relações hierárquicas. O EVA respeita essa organização.

Embeddings não são gerados por cortes arbitrários de caracteres ou por janelas cegas de tokens. São gerados para conteúdos que já constituem unidades semânticas completas na árvore documental, incluindo evidências primárias e sínteses hierárquicas derivadas.

Preservar a unidade organizada pelo autor é preservar parte essencial do significado. A estrutura não é decoração do texto; ela participa do contexto necessário à sua compreensão.

## 4. Compreensão da consulta

Toda consulta é primeiro compreendida quanto à sua forma operacional. Ela pode exigir localização direta, navegação estrutural, abrangência documental ou recuperação semântica conceitual e relacional.

O EVA não reduz toda pergunta a uma única estratégia. Consultas diretas, estruturais e amplas podem ser resolvidas pela hierarquia documental. Consultas conceituais e relacionais podem usar uma representação vetorial transitória do input atual para localizar evidências primárias e derivadas semanticamente próximas.

Similaridade é um mecanismo de ordenação, não um juízo de verdade, importância ou força cognitiva. Seu valor é descartado após a consulta e nunca é convertido em memória, peso ou relação permanente.

## 5. Interações cognitivas transitórias

No EVA, relações cognitivas não são entidades persistentes. Elas existem somente durante a compreensão de uma interação concreta, no contexto formado pelo input atual e entre as evidências efetivamente recuperadas.

Há duas formas fundamentais de interação:

- **simetry**: interação recíproca entre dois participantes, sem origem ou destino privilegiados;
- **assimetry**: interação direcional, com origem e destino explicitamente identificados.

Esses apontamentos descrevem a forma da interação sem atribuir pesos, intensidades, valores morais ou rótulos julgamentais. O EVA não presume que uma evidência “vence”, “vale mais” ou deve ser favorecida. A força cognitiva observável decorre da quantidade e da clareza das interações recuperáveis em um contexto, não de pesos inferidos pelo sistema.

Os nomes `simetry` e `assimetry` pertencem ao vocabulário interno do EVA e não precisam existir na fonte. Eles são operadores essenciais da compreensão cognitiva relacional: orientam como a IA compreende as interações entre evidências, sem serem confundidos com conceitos documentais cuja presença literal deva ser procurada.

O conceito historicamente chamado de **Cnode** permanece apenas como uma maneira de compreender essa interação cognitiva em tempo de consulta. Ele não designa tabela, objeto persistente, identidade global, cache, embedding próprio ou grafo de memória. Encerrada a consulta, a interação é descartada.

## 6. Aplicação multidisciplinar e confiabilidade por restrição

Um projeto do EVA pode reunir documentos especializados em disciplinas distintas sem fundir suas identidades, reescrever suas fontes ou construir antecipadamente uma ontologia entre elas. Cada evidência conserva o documento, a posição estrutural e a linhagem que a originaram. A reunião administrativa de obras amplia o espaço autorizado de consulta; não transforma proximidade temática em relação factual permanente.

Quando um input conceitual ou relacional atravessa mais de uma disciplina, o sistema recupera candidatos em cada documento e monta uma seleção transitória limitada. Evidências de campos diferentes podem então participar da mesma resposta e de interações `simetry` ou `assimetry`, desde que pertençam ao contexto recuperado, sejam citadas e mantenham fragmentos verificáveis. A relação nasce no evento de consulta e termina com ele.

Esse comportamento permite formular uma **síntese conceitual emergente**: uma articulação que pode não estar expressa integralmente em nenhum documento isolado, mas cuja composição é sustentada pelas evidências apresentadas. A síntese emergente não adquire por isso o estatuto de evidência, conceito intrínseco comprovado ou nova memória documental. Ela permanece interpretação situada, auditável e sujeita às limitações do recorte recuperado.

Nesse contexto, confiabilidade não significa infalibilidade, probabilidade de verdade ou ausência de erro semântico. Significa que o sistema introduz condições verificáveis para confiar no processo:

1. preservação da identidade e da integridade de cada fonte;
2. separação entre evidência literal, síntese derivada e interpretação de consulta;
3. resolução das sínteses até evidências primárias;
4. validação local de citações, participantes e fragmentos;
5. declaração explícita do que não possui fundamento suficiente;
6. descarte das relações transitórias após a resposta.

Adicionar documentos amplia o universo de candidatos, mas não altera evidências existentes nem cria conexões permanentes entre todas as obras. Essa propriedade preserva a saúde da memória e evita contaminação cumulativa: novas consultas podem revelar outros encontros multidisciplinares sem transformar interpretações anteriores em premissas silenciosas das consultas futuras.

## 7. Neutralidade e restrição epistêmica

O EVA não deve preencher lacunas com plausibilidade. Se não houver evidência primária suficiente, a operação correta é declarar a limitação e interromper a geração de uma resposta documental.

Suficiência documental pode ser parcial. Se uma pergunta combina X, Y e Z, mas apenas X e Y encontram fundamento, o EVA descreve a relação sustentada entre X e Y, cita suas fontes e identifica Z como aspecto sem evidência suficiente. A lacuna de uma parte restringe essa parte; não apaga aquilo que o documento permite responder sobre as demais.

Recuperar um texto não equivale a aceitá-lo como fundamento. Resultados lexicais e estruturais formam um ambiente de candidatos que deve ser compreendido pela IA em conjunto. Textos pertinentes são citados; intrusos permanecem observáveis durante a análise, mas não são promovidos a evidência utilizada nem provocam o descarte das fontes válidas encontradas ao seu lado.

Quando o input contradiz, desloca ou questiona o conteúdo recuperado, o sistema descreve a divergência por meio das evidências disponíveis. Ele não julga o usuário, não adere automaticamente à premissa da pergunta e não transforma o documento em autoridade universal. Sua função é apresentar o que a fonte permite sustentar e o que ela não permite concluir.

Essa disciplina constitui o princípio de **antievasão**:

1. não responder além das evidências recuperadas;
2. não ocultar a ausência de fundamento documental;
3. não inventar citações, relações ou participantes;
4. não converter semelhança estatística em certeza;
5. não persistir interpretações produzidas durante a conversa.

## 8. Validação e rastreabilidade

Uma resposta verificável exige mais do que uma citação decorativa. O EVA valida localmente se cada identificador citado pertence ao contexto recuperado e se cada interação declarada envolve evidências citadas e trechos reconhecidos naquele contexto.

Quando o provedor utiliza uma evidência válida, mas omite sua marca visível, a aplicação pode apresentar deterministicamente o identificador já validado. O sistema nunca cria um identificador ausente nem aceita referências desconhecidas.

Toda síntese persistente mantém sua derivação. Toda evidência primária conserva sua posição na árvore e sua referência à fonte. Assim, a cadeia de confiança pode ser percorrida da resposta até o conteúdo documental original.

## 9. Fronteira da memória

O EVA persiste somente o que é necessário para reconstruir e auditar o conhecimento documental:

- documentos e sua identidade de conteúdo;
- árvore estrutural normalizada;
- evidências primárias e derivadas;
- linhagens de derivação;
- embeddings das unidades semânticas persistentes;
- estados de processamento e eventos de auditoria sanitizados.

O EVA não persiste consultas brutas, contexto recuperado, similaridades, respostas, interações cognitivas ou históricos conversacionais como memória documental. Consultar é um ato de leitura; não é uma autorização implícita para reescrever o conhecimento.

## 10. Papel dos modelos

Modelos são componentes substituíveis. Na construção da memória, podem produzir sínteses derivadas. Na consulta, podem gerar a representação vetorial transitória necessária à recuperação semântica e formular uma resposta com base nas evidências fornecidas.

As decisões críticas permanecem sob controle da aplicação: quais registros podem ser persistidos, quais evidências entram no contexto, como derivações são resolvidas, quais citações são válidas e quando a ausência de evidência deve encerrar o fluxo.

Fornecedores, modelos, endpoints e credenciais são definidos por configuração externa e neutra. Esse compromisso white label impede que um componente conceitual do EVA dependa do nome de uma empresa ou de um modelo específico.

## 11. Independência e humildade científica

O EVA busca independência de fornecedor, reversibilidade de implementação e possibilidade de auditoria. Sua arquitetura deve continuar compreensível mesmo quando modelos, índices ou estratégias internas forem substituídos.

O sistema não reivindica infalibilidade. Embeddings podem aproximar conceitos inadequados, sínteses podem perder nuances e respostas podem interpretar mal uma evidência. Por isso, a arquitetura privilegia rastreabilidade, validação local, retorno às fontes primárias e recusa explícita quando o fundamento é insuficiente.

A finalidade do EVA não é produzir a aparência de conhecimento. É tornar observável a fronteira entre aquilo que o documento sustenta, aquilo que pode ser derivado com linhagem e aquilo que permanece desconhecido.

## 12. Simplicidade operacional e separação de responsabilidades

A força arquitetural do EVA não depende da acumulação de camadas cognitivas, mas da manutenção de fronteiras claras entre documento, evidência, localização, validação e resposta. Seu fluxo essencial pode ser expresso de forma compacta:

```text
DOCUMENTO
   ↓
EVIDÊNCIAS
   ↓
LOCALIZAÇÃO
   ↓
VALIDAÇÃO
   ↓
RESPOSTA
```

Cada etapa possui uma responsabilidade verificável. O documento fornece a origem; as evidências preservam conteúdo e linhagem; a recuperação localiza candidatos; a aplicação valida o que pode ser utilizado; e o modelo comunica a resposta dentro do contexto autorizado.

Essa divisão limita a autoridade dos modelos sem dispensar sua capacidade:

```text
IA sintetiza    → não cria a fonte
IA representa   → não determina a verdade
IA responde     → não escolhe o que pode persistir
```

O compromisso operacional pode ser resumido em quatro funções:

```text
síntese localiza
fonte fundamenta
aplicação valida
modelo comunica
```

A simplicidade, nesse contexto, não significa ausência de rigor. Significa que memória, interpretação e apresentação não são confundidas. A arquitetura permanece compacta porque evita transformar similaridades, respostas e interações transitórias em novas entidades permanentes; permanece poderosa porque aplica suas restrições na persistência, na recuperação e na validação local, e não somente em instruções fornecidas ao modelo.

O desenvolvimento do EVA deve preservar essa economia arquitetural. Novas capacidades somente devem ampliar o fluxo quando mantiverem a origem verificável, a fronteira da memória e a responsabilidade explícita de cada etapa. Sua robustez deve ser demonstrada por testes reproduzíveis de preservação semântica, estabilidade diante de variações do input e declaração não evasiva de limitações.
