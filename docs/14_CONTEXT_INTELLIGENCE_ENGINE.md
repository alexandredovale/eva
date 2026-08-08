# Context Intelligence Engine (CIE)

## Objetivo

O Context Intelligence Engine é a camada matemática entre a recuperação vetorial e as camadas cognitivas do EVA. O Retriever calcula cosine contra toda a população hierárquica elegível, ordena globalmente e aplica a fronteira query-local κq. O CIE recebe somente depois a população estatisticamente legitimada e preserva suas regras de média, desvio padrão e CV.

O CIE é aplicado somente às rotas conceitual e relacional, porque apenas elas produzem uma distribuição de similaridades vetoriais. As rotas direta, estrutural e ampla continuam usando identificadores, conteúdo literal e hierarquia documental.

## Fluxo

```text
input conceitual ou relacional
        → embedding transitório do input
        → Retriever
        → população hierárquica elegível completa
        → cosine global e fronteira query-local κq
        → CIE hierárquico
        → resolução integral por região herdada
        → cosine primário → κe → CIE primário por região e obra
        → união dos núcleos locais → CIE global
        → núcleo global (ou fallback de convergência) + âncoras literais
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

Em qualquer estágio, se o núcleo existir, ele é a população eleita; se estiver vazio, a faixa de convergência assume esse papel. No estágio hierárquico, núcleo e convergência são ambos preservados para resolução de linhagem e passam separadamente pelo estágio primário. Uma distribuição homogênea possui `σ = 0`; nesse caso, todos os candidatos iguais à média pertencem ao núcleo.

As comparações de fronteira usam tolerância numérica de `1e-12` na escala da distribuição para impedir que a representação binária de ponto flutuante desloque um valor matematicamente igual à média. Essa tolerância não altera as fórmulas nem os valores expostos.

## Determinismo e neutralidade

Dado o mesmo conjunto ordenado de candidatos e similaridades, o CIE sempre produz o mesmo resultado. Ele preserva a ordem original do Retriever dentro de cada região e não:

- julga verdade, correção, qualidade ou importância;
- cria notas, pesos ou rankings artificiais;
- executa chamadas externas;
- altera embeddings ou evidências;
- persiste análise, contexto ou similaridades;
- substitui a validação de citações e fragmentos.

O provedor de resposta recebe somente o núcleo do CIE global — ou sua convergência quando o núcleo estiver vazio — e eventuais âncoras literais protegidas. Cada evidência conserva o papel hierárquico herdado `core` ou `convergence`; esse papel não significa que a convergência global também foi enviada. O provedor não recebe as similaridades nem as usa como autoridade documental. Somente as fontes efetivamente citadas integram o resultado.

## Evidências derivadas e múltiplos documentos

O primeiro CIE classifica candidatos hierárquicos. A linhagem é resolvida integralmente e separada pelo papel herdado; κe e um CIE primário elegem núcleos locais por obra e região. A união desses núcleos recebe um CIE global de consolidação. Somente seu núcleo, ou sua convergência quando o núcleo estiver vazio, chega ao provedor.

Cada fonte primária mantida deve ser incorporada à resposta no trecho analítico em que contribui. A reprodução de IDs em `used_evidence_ids` sem uso textual não é suficiente, e listas isoladas de citações são rejeitadas. Fontes recuperadas sem citação são descartadas sem derrubar a resposta. O núcleo preserva precedência; a convergência pode reforçar, contextualizar, delimitar ou contrapor o núcleo sem autorizar relações inventadas.

Em consultas com múltiplas obras, cada documento estabiliza κq, CIE hierárquico, κe e CIE primário localmente. Somente depois os cosines primários dos núcleos locais são reunidos no CIE global, sem Top-k global.

## Fronteira query-local κq

κq é calculado sobre todos os resumos hierárquicos elegíveis da obra. Rank e similaridade são normalizados em `[0,1]`; o candidato geométrico nasce da maior distância perpendicular à reta entre os extremos e o gap adjacente precisa confirmar uma separação excepcional segundo a própria média e o próprio desvio dos gaps. Não há peso, histórico, treinamento ou threshold semântico configurável.

Estados degenerados são explícitos: população vazia, população insuficiente (`N < 3`), ausência de dispersão, ausência de ruptura e ruptura ambígua. Em qualquer estado sem κq identificado, a população completa segue ao CIE; nenhum corte é fabricado.

## Configuração

Não existe configuração de quantidade para evidências semânticas. `QUERY_NON_SEMANTIC_MAX_EVIDENCE` permanece restrita às rotas que não executam CIE. A base final será o subconjunto efetivamente citado no núcleo global.

## Saída auditável

Respostas incluem uma análise `hierarchical`, até duas análises `primary` por obra e uma análise `global`, identificadas por `stage` e `source_region`. Cada item informa:

- quantidade de candidatos;
- diagnóstico `retrieval_boundary` com população hierárquica total, scores ordenados, normalização, gaps, estatísticas dos gaps, estado, candidato e κq efetivo;
- média, desvio padrão e CV;
- limites da faixa de convergência;
- região usada no contexto disponível;
- papéis finais de núcleo e convergência;
- candidatos do núcleo, convergência e descarte com a similaridade original.

O campo fica vazio em consultas exclusivamente diretas, estruturais ou amplas. Ele descreve uma execução transitória e não implica persistência no banco ou no log de auditoria.

## Verificação

O teste matemático independente de banco cobre núcleo, fallback para convergência, média zero, distribuição homogênea, distribuição vazia e serialização auditável:

```powershell
php tests\ContextIntelligenceEngineTest.php
php tests\QueryLocalKappaDetectorTest.php
php tests\ContextIntelligenceIntegrationTest.php
```

O segundo teste monta um documento simples com cinco vetores conhecidos dentro de uma transação e confirma o contexto primário e o payload da API. `tests/QueryTest.php` verifica ainda a integração com a recuperação semântica geral e a resolução para fontes primárias. Todos usam provedores simulados e não fazem chamadas pagas.
