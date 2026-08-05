# Composição determinística do contexto e contrato de citação visível

**Estado:** implementado e validado
**Atualização:** 5 de agosto de 2026

## Princípio

No EVA, o modelo de resposta não define o universo documental autorizado. O Retriever localiza candidatos; o Context Intelligence Engine separa descarte, convergência e núcleo; a linhagem transforma rotas derivadas em fontes primárias; e a aplicação compõe um contexto disponível dentro do limite global.

```text
Retriever
   → Top-k por documento
   → CIE
   → núcleo + convergência
   → resolução para fontes primárias
   → contexto disponível limitado
   → resposta e interações propostas pela LLM
   → validação local
   → base final formada somente por evidências citadas
```

A composição do contexto é determinística para o mesmo banco, input, configuração e vetores. O modelo não pode introduzir fonte externa, identificador desconhecido ou evidência fora desse conjunto. Ele pode, porém, deixar de usar uma candidata que não contribua para a resposta. Essa candidata é descartada da base final sem invalidar toda a geração.

## Contexto disponível não é base final

O EVA distingue duas etapas:

```text
contexto disponível → evidências primárias que a aplicação permite analisar
base final          → subconjunto efetivamente citado na resposta validada
```

O núcleo (`core`) possui precedência no contexto disponível. A convergência (`convergence`) pode reforçar, contextualizar, delimitar ou contrapor o núcleo quando sua literalidade contribuir. Esses papéis não obrigam a inventar relações nem convertem uma candidata irrelevante em fundamento.

## Citação não é inventário

O campo `used_evidence_ids` é derivado das citações visíveis encontradas em `answer`; a lista declarada pelo provedor não prevalece sobre o texto. Cada evidência mantida deve aparecer na frase ou no parágrafo que explica sua contribuição.

```text
ID conhecido + citação visível + uso analítico → evidência mantida
candidata recuperada sem citação              → evidência descartada
ID fora do contexto                           → resposta inválida
lista isolada de IDs                          → resposta inválida
```

A aplicação não acrescenta marcadores omitidos e não aceita um apêndice como `Evidências: [EVA-E000000]` no lugar da incorporação analítica.

## Resposta e CNode transitório

Quando o contexto contém ao menos duas evidências e `QUERY_MAX_INTERACTIONS` é maior que zero, a mesma chamada que formula a resposta também avalia `simetry` e `assimetry`, independentemente do tipo inicial do input. No código, cada resultado aceito é um `RetrievedInteraction`.

Uma interação somente permanece quando conecta duas evidências citadas, usa papéis coerentes e contém um fragmento literal verificável de cada participante. Pares inválidos ou associados a evidências não citadas são descartados; a resposta documental válida pode permanecer acompanhada de uma limitação relacional. Nenhum CNode recebe ID, embedding ou registro persistente.

## Falhas e tentativas limitadas

Se uma geração completa viola o contrato local, `DocumentQueryService` descarta toda a saída e pode solicitar nova geração com o mesmo contexto disponível. A requisição admite no máximo três tentativas totais de resposta validada. Dentro de cada tentativa, `QueryAnswerProvider` admite no máximo uma regeneração compacta quando a primeira saída termina por limite de tokens. Texto parcial nunca integra o resultado.

Uma candidata simplesmente não citada não provoca nova tentativa. Ela é removida da base final.

## Observabilidade sem memória cognitiva

O Core não persiste contexto, resposta ou objetos `simetry`/`assimetry` como memória documental. Depois de uma consulta concluída, `audit_events` registra metadados sanitizados, inclusive `simetry_count` e `assimetry_count`. Se houver módulo ativo assinante, o Runtime pode persistir o envelope permitido de `interaction.completed` em `module_events`, agora incluída no schema consolidado, e entregá-lo ao armazenamento privado do módulo. A migration `20260803_010_module_events.sql` atende somente bancos legados. Ausência da tabela em instalação incompleta ou falha modular é isolada da resposta. Esses registros operacionais não alteram documentos, evidências, derivações ou embeddings.

## Registro histórico da mudança

Em 2 de agosto de 2026, uma chamada real incorporou dez de dez fontes recebidas e demonstrou a diferença entre devolver IDs e usá-los analiticamente. Esse caso permanece válido como observação histórica do antigo contrato de incorporação integral.

Em 4 de agosto de 2026, o contrato foi corrigido para refletir a fronteira vigente: dez candidatas foram recuperadas, quatro contribuíram com citações visíveis e seis foram descartadas sem derrubar a resposta. A autoridade local continua definindo o universo permitido; a pertinência do subconjunto final é observada pelas citações validadas.

## Fronteira atual

A aplicação comprova identidade, pertencimento ao contexto, citação visível, uso analítico observável, papéis e literalidade dos fragmentos. Ela ainda não comprova deterministicamente que os fragmentos expressam reciprocidade ou direção em sentido semântico estrito. Essa calibração permanece como evolução futura e não autoriza persistência de relações, pesos ou rankings.
