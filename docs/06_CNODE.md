# Cnode e forças cognitivas

## Definição

Cnode ou Cognitive Node é a compreensão contextual de uma interação semântica explícita entre evidências recuperadas. Ele não é uma entidade isolada: existe durante o processamento e na forma como a consulta organiza as interações documentais.

O Evidence Algorithm persiste as evidências e sua linhagem. O Cnode é produzido transitoriamente a partir desse núcleo e validado contra as fontes primárias.

`simetry` e `assimetry` pertencem ao vocabulário interno do EVA. Elas não são expressões que o documento precise conter e sua ausência textual não impede que o sistema responda à questão substantiva com evidências válidas.

## Tipos únicos

```text
simetry
assimetry
```

A taxonomia anterior — `supports`, `complements`, `expands`, `contradicts`, `questions`, `defines`, `depends_on`, `causes`, `precedes`, `exemplifies`, `specializes`, `generalizes` e `analogous_to` — não integra o modelo.

## Importância no sistema

A identificação `simetry`/`assimetry` preserva a forma da interação documental compreendida durante uma consulta. `simetry` registra reciprocidade explícita; `assimetry` conserva uma orientação explícita entre origem e destino. Quando nenhuma dessas formas puder ser demonstrada pelas evidências, o sistema mantém a resposta documental válida e apresenta a limitação relacional.

Essa identificação permite:

- distinguir reciprocidade de direção sem alterar o conteúdo das fontes;
- apresentar separadamente interações recíprocas e orientadas na saída da consulta;
- rastrear cada interação até duas evidências citadas e seus fragmentos literais;
- impedir que similaridade temática seja convertida em relação comprovada;
- explicitar quando as evidências sustentam a resposta, mas não sustentam uma classificação de interação.

Essa camada é explicativa e transitória. Ela não atribui pontuação, confiança, peso, intensidade, importância ou verdade; não cria ranking; não altera embeddings; e não produz memória ou relação persistente no banco de dados.

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

## Quantidade

A quantidade de interações em uma resposta descreve apenas aquele contexto consultado. Não existe contagem global persistida de Cnodes e nenhuma contagem pode ser convertida em ranking ou importância.
