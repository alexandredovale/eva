# Arquitetura

## Objetivo

Separar responsabilidades sem duplicar conceitos e sem permitir que a IA atribua julgamento ou peso às interações.

## Módulos

1. **Entrada:** valida formato, tamanho, integridade e codificação.
2. **Parser:** lê Markdown, JSON ou XML sem inferências.
3. **Normalizador:** converte os formatos para uma árvore documental comum.
4. **Evidências:** persiste exclusivamente conteúdo primário literal e sua origem estrutural.
5. **Embeddings:** vetoriza unidades completas previamente organizadas.
6. **Retriever:** roteia o input e recupera candidatos primários.
7. **Fronteiras query-local + CIE:** κq legitima a população primária completa; o primeiro CIE encaminha seu núcleo superior ou a convergência como fallback; κe e o CIE primário refinam as fontes, e o CIE global consolida os núcleos locais.
8. **Interação transitória:** deriva conceitualmente um Cnode por `simetry`/`assimetry` entre fontes citadas.
9. **Validação:** mantém somente fontes analiticamente citadas e exige participantes conhecidos, citações visíveis e fragmentos literais.
10. **Produto:** fornece interface, API, fila, auditoria, métricas e identidade visual.
11. **Infraestrutura:** banco, arquivos, logs e integrações configuráveis.

## Fluxo macro

```text
Arquivo → parser → árvore → evidências primárias → embeddings

Pergunta → roteamento → população `primary:node_content` completa
         → cosine global → κq → primeiro CIE (μ, σ, CV)
         → núcleo superior ou fallback de convergência
         → cosine primário → κe → CIE primário por região e obra
         → união dos núcleos locais → CIE global
         → núcleo global + convergência auxiliar + âncoras literais
         → contrato determinístico → resposta + interações transitórias → validação
```

## Fluxo ponta a ponta verificado

1. `POST /api/documents` valida o upload, escolhe o parser pela extensão e lê integralmente a fonte Markdown, JSON ou XML.
2. O parser produz a árvore normalizada. A aplicação registra o documento como `received`, grava a fonte original em armazenamento privado, persiste nós e evidências `primary:node_content` em transação e encerra o documento como `ready`.
3. A ingestão não gera embeddings nem agenda processamento automaticamente. `POST /api/documents/{id}/process` agenda somente a etapa `embeddings`, de forma idempotente por documento e versão da capacidade.
4. O worker organiza cada evidência primária completa com título da obra, caminho, nó, referência e conteúdo literal. Todas as unidades pendentes são verificadas antes da primeira chamada; versões existentes por modelo e hash são reutilizadas, e as demais são vetorizadas em lotes sem fragmentação arbitrária.
5. O navegador envia a `POST /api/query` apenas os escopos selecionados e o input atual. A API autoriza e deduplica as obras, aplica somente os perfis dos projetos explicitamente selecionados e não recebe estado de pergunta anterior.
6. `InputTypeDetector` escolhe localmente as rotas. Inputs diretos, estruturais e amplos usam identificadores, conteúdo literal e árvore; inputs conceituais ou relacionais também criam um único embedding transitório reutilizado entre as obras da mesma requisição.
7. Em cada obra semântica, o Retriever calcula cosine contra toda evidência primária validada com embedding compatível, ordena a população, aplica κq e executa o CIE inicial. Apenas o núcleo, ou a convergência como fallback quando o núcleo estiver vazio, segue para κe e para um CIE primário local.
8. `DocumentQueryService` deduplica a união dos núcleos locais e executa o CIE global. Seu núcleo recebe precedência; sua convergência é anexada como contexto auxiliar. Correspondências literais exatas fora da população primária analisada permanecem protegidas.
9. Se não houver evidência, a resposta documental é encerrada localmente sem chamada generativa. Caso exista contexto, `QueryAnswerProvider` recebe somente o input atual, evidências autorizadas completas, papéis `core`/`convergence`, limitações conhecidas e governanças aplicáveis.
10. A mesma chamada produz um JSON com resposta pedagógica orientada por conclusão, IDs utilizados, interações e limitações. O prompt solicita no máximo três interações essenciais; separadamente, o backend rejeita saídas acima de `QUERY_MAX_INTERACTIONS`. Ele deriva a base efetivamente usada das citações visíveis e valida IDs, incorporação analítica, participantes, orientação e fragmentos literais.
11. Uma saída localmente inválida pode ser regenerada até três tentativas totais com o mesmo contexto. Dentro de cada tentativa, `finish_reason=length` admite uma única regeneração integral compacta; nenhuma saída parcial é aceita.
12. Depois da validação, a API registra apenas metadados sanitizados, emite opcionalmente `interaction.completed` aos módulos assinantes e devolve o resultado. A interface mantém somente essa pergunta e resposta; **Fazer outra pergunta** descarta o resultado transitório, preserva os escopos e inicia outra requisição do zero.

## Separação de responsabilidades

Embeddings localizam evidências primárias semanticamente compatíveis. Nas rotas semânticas, toda a população primária elegível é ordenada e κq emerge da geometria query-local antes do primeiro CIE. Esse estágio encaminha somente seu núcleo (`s ≥ μ + σ`), com fallback para a convergência (`μ ≤ s < μ + σ`) quando o núcleo estiver vazio. As fontes sobreviventes passam pelos cálculos posteriores de κe e CIE primário. A união deduplicada dos núcleos locais recebe o CIE global. Seu núcleo continua sendo o corte principal, mas a convergência global é anexada como contexto complementar para o provedor. Correspondências literais exatas externas à população vetorial permanecem como âncoras protegidas.

Depois da seleção das fontes primárias, o contexto disponível está concluído. A IA não pode introduzir fontes externas ou IDs fora desse conjunto. A base final da resposta contém somente as fontes efetivamente incorporadas à prosa com citações visíveis; uma fonte recuperada mas não citada é descartada, sem invalidar toda a resposta. Citação inexistente, fora do contexto ou apresentada apenas como inventário continua inválida, e a aplicação não completa marcadores omitidos.

As interações são produzidas pela mesma capacidade linguística que responde à consulta. O Cnode resultante é uma derivação conceitual interna do EVA, não um sistema, módulo hierárquico superior, nó documental ou entidade persistente. Essas interações não recebem identidade permanente e não são analisadas antecipadamente por combinação massiva de pares. O comando atual solicita no máximo três interações essenciais, ainda que o teto técnico configurado seja maior; a rejeição local por quantidade usa o teto configurado. A camada local também valida tipo, orientação, participantes citados e literalidade dos fragmentos.

## Neutralidade de fornecedores

O domínio conhece capacidades, nunca marcas. As implementações são `EmbeddingProvider` e `QueryAnswerProvider`. `CognitiveProviderFactory` resolve essas capacidades pela configuração neutra do `.env`.

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
