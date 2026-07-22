# Arquitetura

## Objetivo

Separar responsabilidades sem duplicar conceitos e sem permitir que a IA atribua julgamento ou peso às interações.

## Módulos

1. **Entrada:** valida formato, tamanho, integridade e codificação.
2. **Parser:** lê Markdown, JSON ou XML sem inferências.
3. **Normalizador:** converte os formatos para uma árvore documental comum.
4. **Evidências:** persiste conteúdo primário, sínteses derivadas e sua proveniência.
5. **Embeddings:** vetoriza unidades completas previamente organizadas.
6. **Consulta:** roteia o input e recupera evidências primárias ou derivadas.
7. **Interação transitória:** compreende `simetry`/`assimetry` entre fontes recuperadas.
8. **Validação:** exige participantes conhecidos, citações e fragmentos literais.
9. **Produto:** fornece interface, API, fila, auditoria, métricas e identidade visual.
10. **Infraestrutura:** banco, arquivos, logs e integrações configuráveis.

## Fluxo macro

```text
Arquivo → parser → árvore → evidências primárias → sínteses → derivações → embeddings

Pergunta → roteamento → busca em evidências primárias/derivadas
         → resolução da linhagem → fontes primárias
         → resposta + interações transitórias → validação
```

## Separação de responsabilidades

Embeddings localizam evidências semanticamente compatíveis. Similaridade existe somente durante a ordenação e não é persistida como força cognitiva.

As interações são produzidas pela mesma capacidade linguística que responde à consulta. Elas não recebem identidade permanente e não são analisadas antecipadamente por combinação massiva de pares. A camada local valida tipo, orientação, participantes e literalidade dos fragmentos.

## Neutralidade de fornecedores

O domínio conhece capacidades, nunca marcas. As implementações são `EmbeddingProvider`, `SummaryProvider` e `QueryAnswerProvider`. `CognitiveProviderFactory` resolve essas capacidades pela configuração neutra do `.env`.

Fornecedor, endpoint, modelo e nome da variável de credencial ficam exclusivamente no `.env`. Trocar esses vínculos não exige renomear classes, comandos, serviços, rotas, testes ou contratos.

## Limites

- O parser não gera inferências.
- Similaridade não confirma interação.
- A IA não atribui confiança, intensidade, qualidade, prioridade ou relevância.
- A IA não classifica relações por taxonomias julgamentais.
- Assimetria não significa hierarquia ou superioridade.
- Interações transitórias não são persistidas nem usadas como ranking.
- A interface não acessa o banco diretamente.
- A pasta pública não contém documentos, configurações ou segredos.
