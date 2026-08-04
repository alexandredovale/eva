# Visão geral do EVA — Evidence Algorithm

## Objetivo

O EVA, sigla de Evidence Algorithm, é o sistema e a arquitetura principal do projeto para construir, organizar e explorar memória cognitiva documental verificável.

O EVA recebe documentos estruturados, preserva sua hierarquia e cria evidências rastreáveis. Durante uma consulta, o sistema pode compreender interações semânticas explícitas entre as evidências recuperadas. A resposta sempre deve poder voltar ao conteúdo original que a sustenta.

## Evidence Algorithm

O núcleo persistente é composto por:

- documentos e sua árvore normalizada;
- evidências primárias literais;
- evidências derivadas hierárquicas;
- derivações que registram a linhagem;
- embeddings de unidades previamente organizadas.

`evidence_class` distingue a natureza da unidade persistida. `evidence_type` identifica sua função semântica e estrutural. Essa combinação permite recuperar a região adequada do documento sem duplicar a memória em tabelas relacionais.

## Cnode como derivação conceitual

Cnode é uma derivação conceitual interna do EVA: a compreensão contextual de uma interação explícita entre evidências. Ele existe somente no processamento transitório da consulta. Não é um sistema separado, uma camada hierárquica superior, um nó da árvore documental, um registro, identificador, vetor ou cache cognitivo próprio.

`simetry` descreve uma interação recíproca. `assimetry` descreve uma interação orientada com origem e destino explícitos. Nenhuma delas representa peso, julgamento, superioridade, causalidade inferida ou importância.

## Princípio fundamental

```text
Construção: fonte → árvore → evidência primária → síntese derivada → embedding
Consulta semântica: pergunta → Retriever → Top-k → CIE → fontes primárias
Interação: fontes recuperadas → simetry/assimetry transitória → validação literal
Resposta: evidências citadas → resposta e limitações
```

## Neutralidade da IA

A IA apenas compreende e descreve relações semânticas explícitas. Ela não julga conteúdos, não atribui pesos, não classifica importância e não transforma proximidade semântica em conclusão documental.

O Context Intelligence Engine (CIE) reforça essa neutralidade entre o Retriever e as camadas cognitivas. Média, desvio padrão e coeficiente de variação identificam o núcleo principal e a faixa de convergência complementar da distribuição vetorial; quando não há núcleo, a convergência assume o papel principal. A recuperação é local, determinística, transitória e independente de julgamento por modelo. Depois da resolução até fontes primárias, somente evidências efetivamente citadas são mantidas na resposta.

## White label

O núcleo não depende de ramo de conhecimento, marca ou fornecedor de IA. Identidade visual, modelos, endpoints e serviços externos são configuráveis sem alterar as regras cognitivas do EVA.

## Sustentabilidade energética

O EVA contém mecanismos de contenção computacional: reutiliza a construção cognitiva, dispensa embedding transitório em rotas não semânticas, não chama o provedor de resposta quando nenhuma evidência é recuperada e não materializa relações documentais antecipadamente.

Essas propriedades possuem potencial de reduzir demanda energética quando o sistema é adotado em escala, mas a economia líquida ainda depende de validação experimental. A análise, seus limites científicos e o protocolo de medição estão documentados em [Sustentabilidade energética](13_SUSTENTABILIDADE_ENERGETICA.md).
