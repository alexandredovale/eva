# Arquitetura

## Objetivo

Separar responsabilidades sem duplicar conceitos e sem permitir que a IA atribua julgamento ou peso às interações.

## Módulos

1. **Entrada:** valida formato, tamanho, integridade e codificação.
2. **Parser:** lê Markdown, JSON ou XML sem inferências.
3. **Normalizador:** converte os formatos para uma árvore documental comum.
4. **Evidências:** persiste conteúdo primário, sínteses derivadas e sua proveniência.
5. **Embeddings:** vetoriza unidades completas previamente organizadas.
6. **Retriever:** roteia o input e recupera candidatos primários ou derivados.
7. **Fronteiras query-local + CIE:** κq legitima a população hierárquica completa; κe legitima separadamente as fontes primárias herdadas de núcleo e convergência; o CIE classifica cada estágio e consolida globalmente os núcleos primários locais.
8. **Interação transitória:** deriva conceitualmente um Cnode por `simetry`/`assimetry` entre fontes citadas.
9. **Validação:** mantém somente fontes analiticamente citadas e exige participantes conhecidos, citações visíveis e fragmentos literais.
10. **Produto:** fornece interface, API, fila, auditoria, métricas e identidade visual.
11. **Infraestrutura:** banco, arquivos, logs e integrações configuráveis.

## Fluxo macro

```text
Arquivo → parser → árvore → evidências primárias → sínteses → derivações → embeddings

Pergunta → roteamento → população `derived:node_summary` completa
         → cosine global → κq → CIE hierárquico (μ, σ, CV)
         → resolução integral da linhagem por região herdada
         → cosine primário → κe → CIE primário por região e obra
         → união dos núcleos locais → CIE global
         → núcleo global (ou fallback de convergência) + âncoras literais
         → contrato determinístico → resposta + interações transitórias → validação
```

## Separação de responsabilidades

Embeddings localizam unidades hierárquicas semanticamente compatíveis. Nas rotas semânticas, toda a população elegível é ordenada e κq emerge da geometria query-local antes do CIE hierárquico. A linhagem selecionada é resolvida sem truncagem; as fontes primárias, separadas pelo papel hierárquico herdado, passam por κe e CIE primário. A união deduplicada dos núcleos primários locais recebe o CIE global. Seu núcleo (`s ≥ μ + σ`) forma o contexto final, com fallback para a convergência (`μ ≤ s < μ + σ`) somente quando o núcleo estiver vazio. Correspondências literais exatas externas à população vetorial permanecem como âncoras protegidas.

Depois da resolução para fontes primárias, o contexto disponível está concluído. A IA não pode introduzir fontes externas ou IDs fora desse conjunto. A base final da resposta contém somente as fontes efetivamente incorporadas à prosa com citações visíveis; uma fonte recuperada mas não citada é descartada, sem invalidar toda a resposta. Citação inexistente, fora do contexto ou apresentada apenas como inventário continua inválida, e a aplicação não completa marcadores omitidos.

As interações são produzidas pela mesma capacidade linguística que responde à consulta. O Cnode resultante é uma derivação conceitual interna do EVA, não um sistema, módulo hierárquico superior, nó documental ou entidade persistente. Essas interações não recebem identidade permanente e não são analisadas antecipadamente por combinação massiva de pares. A camada local valida tipo, orientação, participantes citados e literalidade dos fragmentos.

## Neutralidade de fornecedores

O domínio conhece capacidades, nunca marcas. As implementações são `EmbeddingProvider`, `SummaryProvider` e `QueryAnswerProvider`. `CognitiveProviderFactory` resolve essas capacidades pela configuração neutra do `.env`.

Fornecedor, endpoint, modelo e nome da variável de credencial ficam exclusivamente no `.env`. Trocar esses vínculos não exige renomear classes, comandos, serviços, rotas, testes ou contratos.

## Limites

- O parser não gera inferências.
- Similaridade não confirma interação.
- O CIE não julga documentos nem cria notas, pesos ou reranking por IA.
- A IA não atribui confiança, intensidade, qualidade, prioridade ou relevância.
- A IA não classifica relações por taxonomias julgamentais.
- Assimetria não significa hierarquia ou superioridade.
- Objetos de interação transitória — pares, papéis, descrições e fragmentos — não são persistidos nem usados como ranking; somente contagens sanitizadas por consulta podem permanecer na auditoria operacional.
- A interface não acessa o banco diretamente.
- A pasta pública não contém documentos, configurações ou segredos.
