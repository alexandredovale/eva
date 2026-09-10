# EVA — Fluxo de APIs e processamento documental

Este documento apresenta, em diagramas de texto, o fluxo vigente do EVA desde o anexo de um documento até a resposta ao input do usuário.

O diagrama visual canônico está em [`EVA_API_FLOW_CIE.svg`](EVA_API_FLOW_CIE.svg).

**English:** [Complete API and documentary-processing flow](en/03_EVA_API_FLOW.md)

## 1. Anexo e construção da memória documental

```text
===============================================================================
FASE 1 — ANEXO E CONSTRUÇÃO DA MEMÓRIA DOCUMENTAL
===============================================================================

[USUÁRIO ANEXA DOCUMENTO]
           |
           v
POST /api/documents                         <- API interna do EVA
           |
           v
[VALIDAÇÃO LOCAL DO ARQUIVO]
- tamanho
- integridade do upload
- formato permitido
           |
           v
[PARSER LOCAL]
Markdown / JSON / XML
           |
           v
[ÁRVORE DOCUMENTAL NORMALIZADA]
           |
           +--> nós documentais
           +--> evidências primárias literais
           +--> referências à fonte
           +--> hashes
           |
           v
[BANCO DE DADOS + ARQUIVO ORIGINAL]

       ATÉ AQUI: NENHUMA API EXTERNA DE IA
```

O processamento cognitivo é iniciado separadamente:

```text
[USUÁRIO CLICA EM "PROCESSAR"]
           |
           v
POST /api/documents/{id}/process            <- API interna do EVA
           |
           v
[FILA DE PROCESSAMENTO]
           |
           +--> trabalho: embeddings

       ATÉ AQUI: AINDA NÃO HOUVE CHAMADA EXTERNA
```

## 2. Embeddings do acervo

```text
┌─────────────────────────────────────────────────────────────┐
│ ETAPA ÚNICA — EMBEDDINGS DO ACERVO                          │
└─────────────────────────────────────────────────────────────┘
        |
        v
[CARREGA EVIDÊNCIAS PRIMÁRIAS]
        |
        v
[MONTA UNIDADES SEMÂNTICAS ESTRUTURADAS]
- documento
- caminho estrutural
- tipo e título do nó
- referência à fonte
- conteúdo literal
        |
        v
╔═════════════════════════════════════════════════════════════╗
║ API EXTERNA 1 — PROVEDOR DE EMBEDDINGS                      ║
║                                                             ║
║ Envio em lotes. Padrão atual: até 64 unidades por chamada.  ║
║ Documentos maiores podem exigir várias chamadas.            ║
╚═════════════════════════════════════════════════════════════╝
        |
        v
[VETORES PERSISTIDOS NO BANCO]
        |
        v
[DOCUMENTO PRONTO PARA CONSULTA SEMÂNTICA]
```

## 3. Input e resposta ao usuário

```text
===============================================================================
FASE 2 — INPUT E RESPOSTA AO USUÁRIO
===============================================================================

[USUÁRIO ENVIA O INPUT ATUAL]
           |
           v
[NAVEGADOR COMPÕE O CONTEXTO CONVERSACIONAL]
- input atual no início
- até 3 rodadas anteriores
- descarte da mais antiga se exceder 20.000 bytes
           |
           v
POST /api/query                              <- API interna do EVA
           |
           v
[DETECÇÃO LOCAL DO TIPO DE INPUT]
           |
           +---------------------------------------------------+
           |                                                   |
           v                                                   v
[DIRETO / ESTRUTURAL / AMPLO]                    [CONCEITUAL / RELACIONAL]
           |                                                   |
           v                                                   v
[RECUPERAÇÃO LOCAL]                              ╔═══════════════════════════╗
- IDs                                            ║ API EXTERNA 3             ║
- frases literais                                ║ EMBEDDING DO INPUT        ║
- títulos                                        ║                           ║
- caminhos estruturais                           ║ Normalmente 1 chamada.    ║
           |                                     ╚═══════════════════════════╝
           |                                                   |
           |                                                   v
           |                                     [SIMILARIDADE CALCULADA
           |                                      LOCALMENTE CONTRA O ACERVO]
           |                                                   |
           |                                                   v
           |                           [POPULAÇÃO COMPLETA + COSINE + κq]
           |                                                   |
           |                                                   v
           |                                     [FRONTEIRA QUERY-LOCAL κq]
           |                                     - curva normalizada e distância geométrica
           |                                     - gap confirmado por μ e σ dos próprios gaps
           |                                     - sem ruptura: população completa
           |                                                   |
           +--------------------------+------------------------+
                                      |
                                      v
                     [PRIMEIRO CIE SOBRE FONTES POR DOCUMENTO]
                     - somente core segue normalmente
                     - convergence é fallback se core estiver vazio
                                      |
                                      v
                     [FONTES PRIMÁRIAS SOBREVIVENTES]
                     - κe + CIE primário por região e documento
                     - sem truncagem ou nova chamada de embedding
                                      |
                                      v
                     [UNIÃO DOS NÚCLEOS LOCAIS → CIE GLOBAL]
                     - núcleo global + convergence auxiliar
                     - âncoras literais exatas permanecem protegidas
                     - a LLM não pode introduzir fontes externas
                                      |
                                      v
                            < HÁ EVIDÊNCIA? >
                               /           \
                            NÃO             SIM
                             |               |
                             v               v
                [RECUSA DETERMINÍSTICA]     ╔═══════════════════════════════╗
                             |              ║ API EXTERNA 4                 ║
                             |              ║ GERAÇÃO DA RESPOSTA          ║
                             |              ║                               ║
                             |              ║ Chamada nominal com input,    ║
                             |              ║ contexto disponível e limites.║
                             |              ╚═══════════════════════════════╝
                             |                              |
                             |                              v
                              |                 [VALIDAÇÃO LOCAL DA SAÍDA]
                              |                 - citações pertencem ao contexto
                              |                 - cada fonte mantida contribui na análise
                              |                 - candidatas não citadas são descartadas
                              |                 - sem inventário isolado de citações
                             |                 - fragmentos literais
                             |                 - simetry/assimetry
                             |                 - campos proibidos
                             |                              |
                             +------------------------------+
                                            |
                                            v
                                    [AUDITORIA E EVENTO OPCIONAL]
                                    - document_queried registra somente contagens
                                    - interaction.completed é emitido se houver assinante
                                            |
                                            v
                                    [RESPOSTA AO USUÁRIO]
                                    - texto documental
                                    - evidências utilizadas
                                    - interações válidas
                                    - análise transitória do CIE
                                    - limitações
                                            |
                                            v
                                    [TRANSCRIPT NO NAVEGADOR]
                                    - mantém todas as rodadas da página
                                    - não persiste memória documental
```

## 4. Consulta multidisciplinar em projeto

```text
[PROJETO COM DOCUMENTOS ESPECIALIZADOS]
           |
           +--> documento A / disciplina A
           +--> documento B / disciplina B
           +--> documento C / disciplina C
           |
           v
[INPUT CONCEITUAL OU RELACIONAL]
           |
           v
[RECUPERAÇÃO INDEPENDENTE POR DOCUMENTO]
           |
           +--> candidatos de A
           +--> candidatos de B
           +--> candidatos de C
           |
           v
[κq + PRIMEIRO CIE SOBRE FONTES + κe + CIE PRIMÁRIO POR DOCUMENTO]
           |
           v
[UNIÃO DEDUPLICADA DOS NÚCLEOS PRIMÁRIOS LOCAIS]
           |
           v
[CIE GLOBAL DE CONSOLIDAÇÃO]
           |
           v
[SELEÇÃO TRANSITÓRIA DE EVIDÊNCIAS PRIMÁRIAS]
           |
           v
╔═════════════════════════════════════════════════════════════╗
║ API EXTERNA — GERAÇÃO DA RESPOSTA                          ║
║                                                             ║
║ Usa o subconjunto documental que efetivamente contribuir,   ║
║ preserva a precedência de core, pode usar convergence,       ║
║ avalia simetry/assimetry e declara lacunas.                  ║
╚═════════════════════════════════════════════════════════════╝
           |
           v
[VALIDAÇÃO LOCAL MULTIDOCUMENTAL]
- cada ID pertence ao contexto autorizado
- cada ID citado pertence ao contexto disponível
- cada evidência mantida possui contribuição analítica citada
- candidatas recuperadas mas não citadas são descartadas
- listas isoladas de citações são rejeitadas
- cada evidência mantém seu documento de origem
- participantes são citados
- fragmentos são verificáveis
- áreas sem fundamento permanecem como limitações
           |
           v
[SÍNTESE CONCEITUAL EMERGENTE E RASTREÁVEL]
           |
           v
[DESCARTE DO CONTEXTO E DAS INTERAÇÕES]

NENHUMA EVIDÊNCIA OU CONEXÃO ENTRE DOCUMENTOS É CRIADA PELA CONSULTA
```

A confiabilidade desse fluxo não é uma estimativa de verdade. Ela decorre da integridade das fontes, da seleção observável, da validação local, da antievasão e da possibilidade de retornar de cada afirmação às evidências documentais participantes. Uma síntese multidisciplinar pode ser nova no contexto da pergunta, mas não é persistida como evidência ou conceito intrínseco ao acervo.

Adicionar documentos ao projeto aumenta o universo de candidatos e de núcleos locais. O CIE global consolida essa população sem limite numérico configurado; ainda assim, uma fronteira estatística não garante cobertura de todas as disciplinas em uma única consulta e não cria conexão persistente entre as obras.

## 5. Quantidade de chamadas por rota

```text
UPLOAD
  └── 0 chamadas externas

PROCESSAMENTO DO DOCUMENTO
  └── M chamadas em lotes para embeddings das evidências primárias

CONSULTA DIRETA / ESTRUTURAL / AMPLA
  ├── 0 chamadas de embedding
  └── 1 chamada de resposta, somente se houver evidência

CONSULTA CONCEITUAL / RELACIONAL
  ├── 1 chamada de embedding do input, reutilizado em todos os estágios
  ├── κq/primeiro CIE sobre fontes e κe/CIE primário por documento, sem chamada externa
  ├── 1 CIE global, sem chamada externa
  └── 1 chamada de resposta, somente se houver evidência

CONSULTA SEM EVIDÊNCIA
  └── 0 chamadas para geração de resposta
```

As quantidades acima descrevem o caminho nominal. Uma tentativa do `QueryAnswerProvider` admite no máximo uma regeneração compacta quando o provedor termina com `finish_reason=length`. Separadamente, `DocumentQueryService` admite no máximo três tentativas totais de resposta validada com o mesmo contexto disponível; saídas rejeitadas são descartadas integralmente e não chegam ao transcript.

## 6. Fluxo resumido

```text
DOCUMENTO
   |
   v
PARSER LOCAL
   |
   v
EVIDÊNCIAS PRIMÁRIAS
   |
   +--> API DE EMBEDDINGS --> VETORES PERSISTIDOS
                                  |
INPUT DO USUÁRIO                  |
   |                              |
   +--> se conceitual/relacional: API DE EMBEDDING
   |                              |
   +----------> RECUPERAÇÃO <-----+
                     |
  POPULAÇÃO PRIMÁRIA → κq → PRIMEIRO CIE SOBRE FONTES
                     |
        NÚCLEO/FALLBACK → κe → CIE PRIMÁRIO → CIE GLOBAL
                     |
        CONTEXTO PRIMÁRIO DISPONÍVEL
                     |
              há evidência?
                /       \
              não       sim
               |         |
            RECUSA    API DE RESPOSTA
                         |
              RESPOSTA + INTERAÇÕES
                         |
                   VALIDAÇÃO LOCAL
                         |
             AUDITORIA / EVENTO OPCIONAL
                         |
                      RESPOSTA
```

## 7. Regra de persistência da consulta

```text
NÃO SÃO PERSISTIDOS COMO MEMÓRIA DOCUMENTAL:

- embedding transitório do input;
- escores de similaridade;
- média, desvio padrão, CV e regiões do CIE;
- contexto recuperado;
- resposta gerada;
- interações cognitivas;
- histórico conversacional.
```

Essa regra não significa ausência absoluta de observabilidade. `audit_events` mantém metadados sanitizados da consulta concluída, inclusive `simetry_count` e `assimetry_count`, sem pares ou fragmentos. Quando existe módulo ativo assinante, `module_events`, incluída no schema consolidado, pode receber o envelope permitido de `interaction.completed`, com input atual, input contextual, resposta validada, referências públicas de evidência e limitações; cada módulo governa seu próprio estado privado. Bancos legados usam a migration `20260803_010_module_events.sql`. Ausência da tabela em uma instalação incompleta ou falha modular gera diagnóstico seguro, mas não derruba a resposta já validada. Nenhum desses registros altera documentos, evidências primárias ou embeddings.

As relações físicas e lógicas que sustentam essa fronteira estão mapeadas em [`docs/17_RELACIONAMENTO_BANCO_DADOS.md`](../docs/17_RELACIONAMENTO_BANCO_DADOS.md).

O transcript completo existe apenas na memória JavaScript da página aberta. Para continuidade, somente as três rodadas concluídas mais recentes são anexadas ao próximo input; elas ajudam o modelo a interpretar referências conversacionais, mas não adquirem autoridade de evidência. **Reiniciar chat**, logout, novo login ou recarregamento descartam esse estado visual sem alterar projetos e documentos persistidos. A API sempre devolve as interações transitórias validadas; na interface vigente, CIE, `simetry`, `assimetry` e limitações técnicas são exibidos somente ao superadmin, enquanto o usuário comum vê a resposta e as evidências utilizadas.
