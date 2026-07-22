# EVA — Fluxo de APIs e processamento documental

Este documento apresenta, em diagramas de texto, o fluxo vigente do EVA desde o anexo de um documento até a resposta ao input do usuário.

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
           +--> trabalho: summaries
           |
           +--> trabalho: embeddings

       ATÉ AQUI: AINDA NÃO HOUVE CHAMADA EXTERNA
```

## 2. Sínteses hierárquicas

```text
[WORKER COGNITIVO]
        |
        v
┌─────────────────────────────────────────────────────────────┐
│ ETAPA A — SÍNTESES HIERÁRQUICAS                             │
└─────────────────────────────────────────────────────────────┘
        |
        v
[SELECIONA UMA UNIDADE HIERÁRQUICA COMPLETA]
        |
        v
╔═════════════════════════════════════════════════════════════╗
║ API EXTERNA 1 — PROVEDOR DE SÍNTESES                        ║
║                                                             ║
║ Uma chamada para cada nova unidade hierárquica.             ║
║ Podem ocorrer muitas chamadas para um documento grande.     ║
╚═════════════════════════════════════════════════════════════╝
        |
        v
[EVIDÊNCIA DERIVADA]
        |
        +--> resumo
        +--> modelo utilizado
        +--> hash do input
        +--> linhagem até as evidências de origem
        |
        v
[BANCO DE DADOS]
```

## 3. Embeddings do acervo

```text
┌─────────────────────────────────────────────────────────────┐
│ ETAPA B — EMBEDDINGS DO ACERVO                              │
└─────────────────────────────────────────────────────────────┘
        |
        v
[CARREGA EVIDÊNCIAS PRIMÁRIAS E DERIVADAS]
        |
        v
[MONTA UNIDADES SEMÂNTICAS ESTRUTURADAS]
- documento
- caminho estrutural
- tipo e título do nó
- referência à fonte
- conteúdo ou síntese
        |
        v
╔═════════════════════════════════════════════════════════════╗
║ API EXTERNA 2 — PROVEDOR DE EMBEDDINGS                      ║
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

## 4. Input e resposta ao usuário

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
           +--------------------------+------------------------+
                                      |
                                      v
                     [EVIDÊNCIAS CANDIDATAS]
                                      |
                                      v
                     [RESOLUÇÃO PARA FONTES PRIMÁRIAS]
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
                             |              ║ Uma chamada com input,       ║
                             |              ║ evidências e limitações.      ║
                             |              ╚═══════════════════════════════╝
                             |                              |
                             |                              v
                             |                 [VALIDAÇÃO LOCAL DA SAÍDA]
                             |                 - IDs conhecidos
                             |                 - citações recuperadas
                             |                 - fragmentos literais
                             |                 - simetry/assimetry
                             |                 - campos proibidos
                             |                              |
                             +------------------------------+
                                            |
                                            v
                                    [RESPOSTA AO USUÁRIO]
                                    - texto documental
                                    - evidências utilizadas
                                    - interações válidas
                                    - limitações
                                            |
                                            v
                                    [TRANSCRIPT NO NAVEGADOR]
                                    - mantém todas as rodadas da página
                                    - não persiste memória documental
```

## 5. Consulta multidisciplinar em projeto

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
[INTERCALAÇÃO EM CONTEXTO GLOBAL LIMITADO]
           |
           v
[SELEÇÃO TRANSITÓRIA DE EVIDÊNCIAS PRIMÁRIAS]
           |
           v
╔═════════════════════════════════════════════════════════════╗
║ API EXTERNA — GERAÇÃO DA RESPOSTA                          ║
║                                                             ║
║ Articula somente as evidências recuperadas, identifica      ║
║ simetry/assimetry quando pertinente e declara lacunas.       ║
╚═════════════════════════════════════════════════════════════╝
           |
           v
[VALIDAÇÃO LOCAL MULTIDOCUMENTAL]
- cada ID pertence ao contexto autorizado
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

Adicionar documentos ao projeto aumenta o universo de candidatos. O limite global de evidências continua controlando o contexto entregue à resposta; portanto, não há conexão completa entre todas as obras nem garantia de cobertura de todas as disciplinas em uma única consulta.

## 6. Quantidade de chamadas por rota

```text
UPLOAD
  └── 0 chamadas externas

PROCESSAMENTO DO DOCUMENTO
  ├── N chamadas para sínteses
  └── M chamadas em lotes para embeddings do acervo

CONSULTA DIRETA / ESTRUTURAL / AMPLA
  ├── 0 chamadas de embedding
  └── 1 chamada de resposta, somente se houver evidência

CONSULTA CONCEITUAL / RELACIONAL
  ├── 1 chamada de embedding do input
  └── 1 chamada de resposta, somente se houver evidência

CONSULTA SEM EVIDÊNCIA
  └── 0 chamadas para geração de resposta
```

## 7. Fluxo resumido

```text
DOCUMENTO
   |
   v
PARSER LOCAL
   |
   v
EVIDÊNCIAS PRIMÁRIAS
   |
   +--> API DE SÍNTESES --> EVIDÊNCIAS DERIVADAS
   |
   +--> API DE EMBEDDINGS --> VETORES PERSISTIDOS
                                  |
INPUT DO USUÁRIO                  |
   |                              |
   +--> se conceitual: API DE EMBEDDING
   |                              |
   +----------> RECUPERAÇÃO <-----+
                     |
              há evidência?
                /       \
              não       sim
               |         |
            RECUSA    API DE RESPOSTA
                         |
                   VALIDAÇÃO LOCAL
                         |
                      RESPOSTA
```

## 8. Regra de persistência da consulta

```text
NÃO SÃO PERSISTIDOS COMO MEMÓRIA DOCUMENTAL:

- embedding transitório do input;
- escores de similaridade;
- contexto recuperado;
- resposta gerada;
- interações cognitivas;
- histórico conversacional.
```

O transcript completo existe apenas na memória JavaScript da página aberta. Para continuidade, somente as três rodadas concluídas mais recentes são anexadas ao próximo input; elas ajudam o modelo a interpretar referências conversacionais, mas não adquirem autoridade de evidência. **Reiniciar chat**, logout, novo login ou recarregamento descartam esse estado sem alterar projetos e documentos persistidos.
