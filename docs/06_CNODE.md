# Cnode — derivação conceitual do EVA

## Definição

Cnode ou Cognitive Node é uma derivação conceitual interna do EVA — Evidence Algorithm: a compreensão contextual de uma interação semântica explícita entre evidências recuperadas. EVA é o nome e a arquitetura principal. Cnode não é um sistema, subsistema, camada hierárquica superior, nó da árvore documental ou entidade persistente; existe apenas durante o processamento da consulta.

O EVA persiste as evidências e sua linhagem. A derivação conceitual Cnode é produzida transitoriamente a partir desse núcleo e validada contra as fontes primárias.

O Context Intelligence Engine atua antes dessa compreensão relacional. Ele reduz o Top-k vetorial ao núcleo estatístico principal e à faixa de convergência complementar — promovendo a convergência quando o núcleo não existe — sem produzir `simetry`, `assimetry` ou qualquer interpretação semântica. CIE e Cnode são operações transitórias distintas dentro do EVA, sem hierarquia de sistemas: o primeiro seleciona contexto por distribuição; a segunda deriva conceitualmente interações explícitas entre fontes já selecionadas.

`simetry` e `assimetry` pertencem ao vocabulário interno do EVA. Elas não são expressões que o documento precise conter e sua ausência textual não impede que o sistema responda à questão substantiva com evidências válidas.

## Tipos únicos

```text
simetry
assimetry
```

A taxonomia anterior — `supports`, `complements`, `expands`, `contradicts`, `questions`, `defines`, `depends_on`, `causes`, `precedes`, `exemplifies`, `specializes`, `generalizes` e `analogous_to` — não integra o modelo.

## Função na consulta do EVA

A identificação `simetry`/`assimetry` preserva a forma da interação documental compreendida durante uma consulta. `simetry` registra reciprocidade explícita; `assimetry` conserva uma orientação explícita entre origem e destino. Quando nenhuma dessas formas puder ser demonstrada pelas evidências, o sistema mantém a resposta documental válida e apresenta a limitação relacional.

Essa identificação permite:

- distinguir reciprocidade de direção sem alterar o conteúdo das fontes;
- apresentar separadamente interações recíprocas e orientadas na saída da consulta;
- rastrear cada interação até duas evidências citadas e seus fragmentos literais;
- impedir que similaridade temática seja convertida em relação comprovada;
- explicitar quando as evidências sustentam a resposta, mas não sustentam uma classificação de interação.

Essa derivação é explicativa e transitória. Ela não atribui pontuação, confiança, peso, intensidade, importância ou verdade; não cria ranking; não altera embeddings; e não produz memória ou relação persistente no banco de dados.

## Simetry

`simetry` representa uma interação recíproca explicitamente demonstrada. As duas evidências usam o papel `participant`. Isso não afirma igualdade entre os conteúdos.

```text
participant ↔ participant
```

## Assimetry

`assimetry` representa uma interação cuja orientação está semanticamente explícita.

```text
origin → destination
```

A orientação não significa superioridade, importância, causa inferida, apoio, oposição, verdade ou intensidade.

## Contrato transitório

Uma interação válida contém:

- tipo `simetry` ou `assimetry`;
- descrição semântica neutra;
- duas evidências primárias participantes;
- papéis coerentes com o tipo;
- um fragmento literal de cada evidência;
- referência de origem de cada fragmento.

Ela não contém identificador permanente, confiança, similaridade, peso, intensidade, prioridade, estado de banco ou embedding próprio.

## Validação

A interação só integra o resultado quando pode ser reconstruída pelas evidências citadas. Similaridade temática não basta. Uma interação candidata inválida é descartada e não deixa registro cognitivo residual; se a resposta documental e suas citações forem válidas, elas permanecem no resultado acompanhadas da limitação relacional. Citações documentais inválidas continuam rejeitando a resposta.

### Limite vigente e evolução futura

A aplicação valida deterministicamente identidade dos participantes, papéis, orientação declarada e literalidade dos fragmentos. A comprovação de que esses fragmentos expressam reciprocidade ou direção em sentido semântico estrito ainda depende da interpretação do provedor. Assim, uma convergência temática forte pode ocasionalmente ser classificada como `simetry` mesmo sem reciprocidade documental inequívoca.

Uma calibração futura deverá exigir demonstração separada das duas direções de uma `simetry` e da origem/destino de uma `assimetry`. Essa evolução não bloqueia a resposta documental atual, não altera as evidências citadas e não autoriza persistência de relações.

## Quantidade

A quantidade de interações em uma resposta descreve apenas aquele contexto consultado. Não existe tabela, identidade ou contador cognitivo global de Cnodes. Entretanto, `ProductApi` registra em `audit_events`, para cada consulta concluída, as contagens sanitizadas `simetry_count` e `assimetry_count`; essas métricas operacionais podem ser agregadas sem reconstruir pares, participantes, descrições ou fragmentos. Elas não transformam Cnode em entidade e não podem ser usadas como ranking, peso ou importância.
