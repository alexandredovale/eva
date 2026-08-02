# Context Intelligence Engine (CIE)

## Objetivo

O Context Intelligence Engine é a camada matemática entre a recuperação vetorial e as camadas cognitivas do EVA. O Retriever continua responsável por localizar e ordenar candidatos por similaridade de cosseno. O CIE observa a distribuição desse Top-k e determina o contexto semântico final sem recorrer a outro modelo, reranker, peso subjetivo ou regra de relevância criada pela IA.

O CIE é aplicado somente às rotas conceitual e relacional, porque apenas elas produzem uma distribuição de similaridades vetoriais. As rotas direta, estrutural e ampla continuam usando identificadores, conteúdo literal e hierarquia documental.

## Fluxo

```text
input conceitual ou relacional
        → embedding transitório do input
        → Retriever
        → Top-k vetorial (30 por padrão)
        → Context Intelligence Engine
        → resolução de evidências derivadas até fontes primárias
        → limite global do contexto final
        → camadas cognitivas
        → LLM
```

## Cálculo

Para `N` candidatos com similaridades `sᵢ`, o CIE calcula a média populacional, o desvio padrão populacional e o coeficiente de variação:

```text
μ = (Σ sᵢ) / N
σ = √[(Σ(sᵢ − μ)²) / N]
CV = σ / μ
```

Quando `μ = 0`, o CV é matematicamente indefinido e a saída auditável usa `null`. Média, desvio padrão, limites e similaridades permanecem transitórios e não são gravados na memória documental.

## Regiões da distribuição

- **Descarte:** `s < μ`.
- **Faixa de convergência:** `μ ≤ s < μ + σ`.
- **Núcleo de convergência:** `s ≥ μ + σ`.

Se o núcleo existir, ele lidera o contexto semântico selecionado e a faixa de convergência o acompanha como análise complementar obrigatória. Se o núcleo estiver vazio, a faixa de convergência assume o papel principal. Uma distribuição homogênea possui `σ = 0`; nesse caso, todos os candidatos iguais à média pertencem ao núcleo.

As comparações de fronteira usam tolerância numérica de `1e-12` na escala da distribuição para impedir que a representação binária de ponto flutuante desloque um valor matematicamente igual à média. Essa tolerância não altera as fórmulas nem os valores expostos.

## Determinismo e neutralidade

Dado o mesmo conjunto ordenado de candidatos e similaridades, o CIE sempre produz o mesmo resultado. Ele preserva a ordem original do Retriever dentro de cada região e não:

- julga verdade, correção, qualidade ou importância;
- cria notas, pesos ou rankings artificiais;
- executa chamadas externas;
- altera embeddings ou evidências;
- persiste análise, contexto ou similaridades;
- substitui a validação de citações e fragmentos.

O provedor de resposta recebe somente as evidências primárias resolvidas após a seleção, identificadas como `core` ou `convergence`. Não recebe as similaridades nem as usa como autoridade documental. A eleição é integral: o provedor não pode rejeitar ou reduzir as fontes recebidas.

## Evidências derivadas e múltiplos documentos

O CIE classifica os candidatos vetoriais antes da resolução de linhagem. Um candidato `derived` selecionado continua sendo resolvido por `evidence_derivations` até suas fontes `primary`, e somente o conteúdo primário literal pode chegar ao provedor de resposta.

Cada fonte primária final deve ser incorporada à resposta no trecho analítico em que contribui. A reprodução de IDs em `used_evidence_ids` sem uso textual não é suficiente, e listas isoladas de citações são rejeitadas. O núcleo preserva precedência; a convergência reforça, contextualiza, delimita ou contrapõe o núcleo sem autorizar relações inventadas.

Em consultas com múltiplas obras, cada documento produz sua própria distribuição e sua própria análise do CIE. `DocumentQueryService` intercala depois as fontes primárias selecionadas, remove duplicatas e respeita `QUERY_MAX_EVIDENCE` como limite global. Isso evita misturar escalas de similaridade de índices documentais distintos antes da estabilização local.

## Configuração

```env
QUERY_CANDIDATE_LIMIT=30
QUERY_MAX_EVIDENCE=8
```

- `QUERY_CANDIDATE_LIMIT`: tamanho do Top-k semântico analisado por documento; padrão `30`, intervalo efetivo `1..200`.
- `QUERY_MAX_EVIDENCE`: máximo de evidências primárias no contexto entregue ao provedor; padrão `8`, intervalo efetivo `1..50`.

Os limites não são equivalentes. O primeiro define a população estatística do CIE. O segundo contém o contexto final e o consumo de tokens depois da seleção e da resolução de linhagem.

## Saída auditável

Respostas de consulta incluem `context_intelligence`, uma lista com uma análise para cada recuperação semântica executada. Cada item informa:

- quantidade de candidatos;
- média, desvio padrão e CV;
- limites da faixa de convergência;
- região usada no contexto final;
- papéis finais de núcleo e convergência;
- candidatos do núcleo, convergência e descarte com a similaridade original.

O campo fica vazio em consultas exclusivamente diretas, estruturais ou amplas. Ele descreve uma execução transitória e não implica persistência no banco ou no log de auditoria.

## Verificação

O teste matemático independente de banco cobre núcleo, fallback para convergência, média zero, distribuição homogênea, distribuição vazia e serialização auditável:

```powershell
php tests\ContextIntelligenceEngineTest.php
php tests\ContextIntelligenceIntegrationTest.php
```

O segundo teste monta um documento simples com cinco vetores conhecidos dentro de uma transação e confirma o contexto primário e o payload da API. `tests/QueryTest.php` verifica ainda a integração com a recuperação semântica geral e a resolução para fontes primárias. Todos usam provedores simulados e não fazem chamadas pagas.
