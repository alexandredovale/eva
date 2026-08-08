# EVA Upgrade — κe, CIE Primário e CIE Global de Consolidação

## Objetivo

Este upgrade remove `QUERY_MAX_EVIDENCE` das rotas semânticas e substitui o limite humano por uma composição integralmente query-local.

O fluxo preserva sem alteração a matemática do Context Intelligence Engine:

- descarte abaixo da média;
- convergência entre a média e a média mais um desvio-padrão;
- núcleo acima ou igual à média mais um desvio-padrão.

A mesma geometria é aplicada em três níveis com responsabilidades distintas:

1. CIE hierárquico por obra;
2. CIE primário por região hierárquica herdada;
3. CIE global sobre a união dos núcleos primários locais.

Não existe Top-k semântico, percentual, peso, threshold configurável, treinamento adicional, lote de respostas ou limite dependente do modelo de linguagem.

## Problema anterior

O fluxo anterior era:

```text
população hierárquica completa
→ κq
→ CIE hierárquico
→ resolução de linhagem
→ QUERY_MAX_EVIDENCE
→ LLM
```

κq corrigiu a truncagem estatística provocada pelo antigo Top-k, mas `QUERY_MAX_EVIDENCE` ainda decidia externamente quantas fontes primárias chegariam à LLM.

Esse limite não alterava média, desvio-padrão ou CV do primeiro CIE, mas podia retirar uma fonte primária legitimamente alcançada pela análise. Também fazia o número final ser conhecido antes da geometria da consulta existir.

## Fluxo novo

```text
consulta q
→ população hierárquica completa por obra Hq,d
→ κq,d
→ CIE hierárquico por obra
→ linhagem completa separada por região
→ populações primárias Pcore,d e Pconv,d
→ κe por população primária
→ CIE primário por região e por obra
→ união dos núcleos primários locais Gq
→ CIE global de consolidação
→ núcleo global final Eq
→ LLM
```

Em notação compacta:

\[
q\rightarrow\mathcal H_{q,d}\rightarrow\kappa_{q,d}
\rightarrow CIE_H
\rightarrow\mathcal P_{q,d}^{core}\cup\mathcal P_{q,d}^{conv}
\rightarrow\kappa_e
\rightarrow CIE_P
\rightarrow\mathcal G_q
\rightarrow CIE_G
\rightarrow\mathcal E_q
\]

## 1. População hierárquica e κq

Para cada obra `d`, o Retriever calcula cosine entre o embedding transitório da consulta e todos os resumos hierárquicos elegíveis:

\[
s_h=\cos(q,h)
\]

A população é ordenada de forma decrescente:

\[
s_1\geq s_2\geq\cdots\geq s_N
\]

κq usa rank e similaridade normalizados, distância perpendicular à reta entre os extremos e confirmação do gap adjacente pela própria média e pelo próprio desvio dos gaps.

Se uma ruptura estrutural é identificada:

\[
\mathcal H_{q,d}^{\kappa}
=\{h_1,\ldots,h_{\kappa_{q,d}}\}
\]

Se não existe ruptura identificável, a população completa segue ao CIE. Nenhum corte é fabricado.

## 2. CIE hierárquico preservado

Sobre a população legitimada por κq:

\[
\mu_{H,d}=\frac{1}{N_d}\sum_{i=1}^{N_d}s_i
\]

\[
\sigma_{H,d}
=\sqrt{
\frac{1}{N_d}
\sum_{i=1}^{N_d}(s_i-\mu_{H,d})^2
}
\]

\[
CV_{H,d}=\frac{\sigma_{H,d}}{\mu_{H,d}}
\]

As regiões permanecem exatamente:

\[
Discard_H=\{h:s_h<\mu_{H,d}\}
\]

\[
Conv_H=\{h:\mu_{H,d}\leq s_h<\mu_{H,d}+\sigma_{H,d}\}
\]

\[
Core_H=\{h:s_h\geq\mu_{H,d}+\sigma_{H,d}\}
\]

O primeiro CIE continua selecionando núcleo e convergência complementar. Não houve alteração na classe `ContextIntelligenceEngine`.

## 3. Resolução integral de linhagem

Cada evidência hierárquica selecionada é percorrida por `evidence_derivations` até suas fontes `primary`.

Não existe truncagem durante essa resolução.

As primárias são deduplicadas e herdam a região da melhor linhagem hierárquica que as alcançou:

- uma linhagem `core` prevalece sobre uma linhagem `convergence`;
- na ausência de linhagem `core`, a primária permanece `convergence`.

Isso forma duas populações distintas por obra:

\[
\mathcal P_{q,d}^{core}
\]

\[
\mathcal P_{q,d}^{conv}
\]

A separação impede que uma população hierárquica nuclear numerosa elimine a convergência antes de sua própria análise primária.

## 4. Cosine primário

O embedding transitório da consulta é reutilizado. Não ocorre nova chamada ao provedor de embeddings.

Para cada fonte primária:

\[
c_p=\cos(q,p)
\]

Os embeddings primários já pertencem ao índice documental. O cosine próprio não substitui nem reclassifica o primeiro CIE; ele descreve a nova população que só passou a existir depois da linhagem.

## 5. Fronteira primária κe

κe aplica o mesmo detector query-local separadamente em:

\[
\mathcal P_{q,d}^{core}
\]

e

\[
\mathcal P_{q,d}^{conv}
\]

Logo:

\[
\kappa_{e,d}^{core}
=Kappa(\mathcal P_{q,d}^{core})
\]

\[
\kappa_{e,d}^{conv}
=Kappa(\mathcal P_{q,d}^{conv})
\]

Quando κe não identifica ruptura, a respectiva população primária permanece completa. Isso mantém a mesma neutralidade adotada em κq.

## 6. CIE primário estratificado

Cada população selecionada por κe recebe uma aplicação independente do CIE.

Para uma região herdada `r`, com `r ∈ {core, convergence}`:

\[
\mu_{P,d,r}
=\frac{1}{M_{d,r}}
\sum_{p\in\mathcal P_{q,d}^{r}}c_p
\]

\[
\sigma_{P,d,r}
=\sqrt{
\frac{1}{M_{d,r}}
\sum_{p\in\mathcal P_{q,d}^{r}}
(c_p-\mu_{P,d,r})^2
}
\]

\[
CV_{P,d,r}=\frac{\sigma_{P,d,r}}{\mu_{P,d,r}}
\]

As regiões primárias são:

\[
Discard_{P,d,r}=\{p:c_p<\mu_{P,d,r}\}
\]

\[
Conv_{P,d,r}
=\{p:\mu_{P,d,r}\leq c_p<\mu_{P,d,r}+\sigma_{P,d,r}\}
\]

\[
Core_{P,d,r}
=\{p:c_p\geq\mu_{P,d,r}+\sigma_{P,d,r}\}
\]

O núcleo primário local é:

\[
L_{d,r}=
\begin{cases}
Core_{P,d,r},&Core_{P,d,r}\neq\varnothing\\
Conv_{P,d,r},&Core_{P,d,r}=\varnothing
\end{cases}
\]

O fallback evita produzir uma população vazia quando a distribuição não possui elemento acima de `μ + σ`.

## 7. População global consolidada

Os núcleos primários locais de todas as obras e das duas regiões herdadas são unidos e deduplicados:

\[
\mathcal G_q=
\bigcup_d
\left(
L_{d,core}\cup L_{d,convergence}
\right)
\]

Essa população contém somente fontes que já passaram por:

1. κq;
2. CIE hierárquico;
3. resolução de linhagem;
4. κe;
5. CIE primário local.

Ela é a população de consolidação, não a população bruta do corpus.

## 8. CIE global

Como todos os cosines primários foram calculados contra a mesma consulta e com o mesmo modelo de embedding, a população `Gq` pode ser analisada globalmente.

\[
\mu_G=\frac{1}{|\mathcal G_q|}
\sum_{p\in\mathcal G_q}c_p
\]

\[
\sigma_G=
\sqrt{
\frac{1}{|\mathcal G_q|}
\sum_{p\in\mathcal G_q}(c_p-\mu_G)^2
}
\]

\[
CV_G=\frac{\sigma_G}{\mu_G}
\]

O núcleo global é:

\[
Core_G=
\{p\in\mathcal G_q:c_p\geq\mu_G+\sigma_G\}
\]

A população final entregue à LLM é:

\[
\boxed{
\mathcal E_q=
\begin{cases}
Core_G,&Core_G\neq\varnothing\\
Conv_G,&Core_G=\varnothing
\end{cases}
}
\]

Consequentemente:

\[
\boxed{K(q)=|\mathcal E_q|}
\]

`K(q)` não é configurado e não é conhecido antes da consulta.

## 9. Relação com a desigualdade de Cantelli

Para uma distribuição com variância não nula, Cantelli estabelece:

\[
P(X-\mu\geq\sigma)\leq\frac{1}{2}
\]

Portanto, quando o núcleo global existe:

\[
|Core_G|\leq\frac{|\mathcal G_q|}{2}
\]

Esse é um teto matemático, não uma previsão de que metade será selecionada. A cardinalidade real depende da geometria da consulta.

## 10. Preservação de papéis

O CIE global decide elegibilidade final, mas não apaga a origem hierárquica.

Uma evidência pode possuir:

```text
source_region: convergence
global_region: core
```

Nesse caso ela entra no contexto por pertencer ao núcleo global, mas continua apresentada ao provedor como `convergence`, pois essa é sua função documental herdada.

Evidências literais exatas que não pertencem à população primária analisada permanecem como âncoras protegidas e não são removidas pelo CIE global.

## 11. Estados degenerados

### População vazia

Nenhuma análise global é criada e o fluxo mantém a limitação documental existente.

### População insuficiente

O detector κ preserva todos os candidatos quando `N < 3`. O CIE continua calculando média e desvio normalmente.

### Ausência de dispersão

Quando `σ = 0`, todos os candidatos possuem o mesmo score e satisfazem `s = μ + σ`. Todos pertencem ao núcleo porque a matemática não oferece fundamento para diferenciá-los.

### Núcleo vazio

Quando nenhum candidato alcança `μ + σ`, a convergência assume a seleção final, preservando o comportamento já definido pelo CIE.

### Evidência sem embedding primário elegível

Uma fonte sem embedding compatível não participa do CIE primário. O índice cognitivo deve manter embeddings primários e hierárquicos no mesmo modelo configurado.

## 12. Neutralidade operacional

O upgrade:

- não altera evidências;
- não persiste scores de consulta;
- não envia estatísticas à LLM como autoridade documental;
- não cria chamadas adicionais de embedding;
- não cria chamadas em lotes;
- não depende da janela de contexto do modelo de resposta;
- não usa histórico, treinamento ou feedback da LLM para selecionar evidências;
- não mistura scores de modelos de embedding diferentes.

## 13. Configuração

`QUERY_MAX_EVIDENCE` deixa de existir nas rotas semânticas.

Rotas diretas, estruturais ou amplas não executam CIE e mantêm um limite explicitamente isolado:

```env
QUERY_NON_SEMANTIC_MAX_EVIDENCE=10
```

Esse valor nunca participa de κq, κe, CIE hierárquico, CIE primário ou CIE global.

## 14. Saída auditável

`context_intelligence` identifica cada análise por `stage`:

- `hierarchical`: CIE após κq;
- `primary`: CIE após κe, com `source_region` igual a `core` ou `convergence`;
- `global`: CIE final sobre `Gq`.

Cada análise continua expondo transitoriamente:

- população;
- média;
- desvio-padrão populacional;
- CV;
- faixa de convergência;
- núcleo;
- convergência;
- descarte;
- diagnóstico κ quando aplicável.

## 15. Teste real de referência

Consulta:

> nossa evolução depende exclusivamente daquilo de fazemos enquanto estamos encarnados?

Escopo:

> projeto completo Reforma Íntima e Evolução — 7 obras

Resultado local antes da consolidação:

\[
|\mathcal G_q|=350
\]

Estatísticas globais:

\[
\mu_G=0{,}4288625933904816
\]

\[
\sigma_G=0{,}03870533287997911
\]

\[
\mu_G+\sigma_G=0{,}4675679262704607
\]

Composição:

```text
core global:         63
convergência global: 97
descarte global:    190
população global:   350
```

Portanto:

\[
K(q)=63
\]

A LLM recebeu o núcleo global com 63 evidências e incorporou 4 fontes na resposta validada. A chamada completa levou 27,641 segundos. No experimento sem CIE global, a mesma consulta entregou 350 evidências e levou 34,920 segundos.

## 16. Arquivos centrais

- `app/Application/Query/DocumentContextRetriever.php`
- `app/Application/Query/DocumentQueryService.php`
- `app/Application/Query/ContextIntelligenceEngine.php`
- `app/Application/Query/ContextIntelligenceAnalysis.php`
- `app/Application/Query/QueryLocalKappaDetector.php`
- `tests/ContextIntelligenceIntegrationTest.php`

## 17. Invariantes finais

1. `μ`, `σ` e CV do CIE permanecem intocados.
2. κq nunca recebe Top-k prévio.
3. κe é calculado separadamente sobre primárias herdadas de `core` e `convergence`.
4. A linhagem semântica não recebe limite numérico.
5. O CIE primário usa cosine próprio das fontes primárias.
6. O CIE global recebe somente núcleos primários locais.
7. A LLM recebe somente o núcleo global, ou a convergência global quando o núcleo estiver vazio.
8. O papel hierárquico original acompanha a evidência final.
9. A quantidade semântica final é `K(q)`, propriedade emergente da consulta.
10. `QUERY_NON_SEMANTIC_MAX_EVIDENCE` não participa de nenhuma distribuição semântica.
