# Eleição determinística e incorporação analítica das evidências

**Estado:** implementado e validado em chamada real
**Data:** 2 de agosto de 2026

## Princípio

No EVA, selecionar o fundamento de uma resposta não é uma prerrogativa do modelo de linguagem. O Retriever localiza candidatos; o Context Intelligence Engine separa descarte, convergência e núcleo; a linhagem transforma rotas derivadas em fontes primárias; e a aplicação entrega ao modelo uma eleição documental concluída.

```text
Retriever
   → Top-k
   → CIE
   → núcleo principal + convergência complementar
   → resolução para fontes primárias
   → eleição determinística
   → incorporação analítica pela LLM
   → validação local fechada
```

A LLM não escolhe, reordena, rejeita ou reduz esse conjunto. Seu papel é formular uma resposta fiel usando o núcleo como referência principal e todas as convergências como elementos complementares da análise.

## Aceitação não é utilização

O campo `used_evidence_ids` constitui um contrato de identidade, mas sua reprodução isolada não demonstra que uma evidência participou do raciocínio. Por isso, o EVA diferencia:

```text
aceitação formal → o ID foi devolvido
utilização real  → a evidência sustenta uma proposição analítica citada
```

Cada evidência eleita deve aparecer na frase ou no parágrafo que explica sua contribuição. Um apêndice como `Evidências: [EVA-E000000]` não satisfaz o contrato. A aplicação não acrescenta citações omitidas e rejeita a resposta quando identifica ausência ou inventário isolado.

Essa decisão impede uma forma sutil de evasão: declarar conformidade estrutural sem permitir que a convergência participe efetivamente da explicação.

## Papéis complementares

O núcleo e a convergência não competem por autoridade:

- `core` sustenta as conclusões principais e aparece com precedência;
- `convergence` reforça, contextualiza, delimita ou contrapõe o núcleo conforme seu conteúdo literal;
- quando não há núcleo, a convergência assume o papel principal;
- nenhuma região recebe peso, confiança ou valor de verdade.

A convergência não deve ser decorativa nem forçada. Sua contribuição precisa permanecer dentro do que a fonte permite afirmar. A obrigação de uso não autoriza inventar relações.

## Validação real de referência

A consulta abaixo foi executada com provedores reais e exclusivamente sobre *O Livro dos Espíritos*:

> a que podemos atribuir a vontade de uma pessoa em construir lápide luxuosa em seu próprio túmulo quando morrer?

O CIE analisou 30 candidatos, com `μ = 0,4256067108`, `σ = 0,0428704555` e `CV = 0,1007278655`. A resolução final produziu três evidências de núcleo e sete de convergência. A resposta incorporou analiticamente as dez fontes, preservou a precedência do núcleo, não criou inventário artificial de citações, retornou JSON válido e terminou sem truncamento em 24,32 segundos.

O teste anterior à validação fechada havia devolvido os mesmos dez IDs, mas sete apareciam apenas em uma lista acrescentada ao final. Esse contraste demonstrou que identidade, citação e utilização são propriedades diferentes e justificou a nova barreira local.

## Fronteira atual

A cobertura analítica das evidências é verificável por regras locais. A adequação semântica estrita de uma classificação `simetry` ou `assimetry` possui uma dificuldade distinta: literalidade e participantes podem ser confirmados deterministicamente, mas reciprocidade ou direção ainda dependem de interpretação semântica.

Essa calibração permanece como evolução futura. Ela não reduz a validade alcançada pela resposta documental e não modifica o princípio central deste marco: todas as evidências eleitas participam da análise, enquanto a escolha do fundamento permanece fora da autoridade da LLM.
