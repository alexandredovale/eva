# Regras obrigatórias

1. Aceitar somente Markdown, JSON e XML como fontes documentais.
2. Preservar conteúdo, ordem, hierarquia e referência da origem.
3. Não confundir texto original com conteúdo gerado por IA.
4. Persistir evidências com `evidence_class` e `evidence_type` explícitos.
5. Construir sínteses superiores somente a partir de evidências inferiores identificadas.
6. Gerar embeddings somente de unidades organizadas, nunca por cortes arbitrários de tamanho.
7. Tratar EVA — Evidence Algorithm — como nome e arquitetura principal do sistema, mantendo evidências, derivações e embeddings como seu núcleo persistente.
8. Tratar Cnode somente como derivação conceitual interna e transitória do EVA, nunca como sistema, camada hierárquica superior, nó documental ou entidade persistente; não persistir pares candidatos, análises de interação ou métricas relacionais como memória/ranking. `audit_events` pode manter apenas contagens sanitizadas por consulta, sem participantes, fragmentos ou reconstrução do par.
9. Avaliar interações quando houver ao menos duas evidências recuperadas e produzir somente relações entre evidências efetivamente citadas, dentro do limite solicitado.
10. Usar exclusivamente `simetry` e `assimetry` para descrever interações.
11. Não classificar relações por taxonomias julgamentais.
12. Não transformar similaridade vetorial em conclusão.
13. Usar `simetry` somente para interação recíproca explícita.
14. Usar `assimetry` somente com origem e destino explícitos.
15. Não interpretar assimetria como hierarquia, superioridade, causalidade ou importância.
16. Exigir duas evidências recuperadas e citadas para cada interação.
17. Exigir fragmento literal de cada participante.
18. Não atribuir confiança, intensidade, prioridade, relevância, importância ou peso.
19. Não responder como fato documental sem evidência primária suficiente.
20. Informar quando o documento não sustenta uma conclusão.
21. Exibir as evidências usadas e separar `simetry`, `assimetry` e limitações.
22. Preservar histórico quando sínteses, modelos ou vetores mudarem.
23. Manter provedores substituíveis e configuráveis por capacidade.
24. Nunca gravar ou exibir chaves, senhas ou segredos.
25. Manter arquivos enviados fora da pasta pública.
26. Evitar duplicação de conceitos, tabelas e responsabilidades.
27. Exigir configuração habilitada e opção explícita da CLI para chamadas reais.
28. Usar embedding de consulta somente em inputs conceituais ou relacionais.
29. Não persistir embedding de consulta nem similaridades calculadas.
30. Não chamar o provedor de resposta quando nenhuma evidência primária for recuperada.
31. Manter `simetry` e `assimetry` como operadores essenciais da compreensão cognitiva relacional.
32. Não exigir que os nomes `simetry` e `assimetry` apareçam literalmente na fonte documental.
33. Descartar interações não verificáveis sem apagar uma resposta documental válida.
34. Informar limitação relacional quando nenhuma interação `simetry` ou `assimetry` puder ser validada.
35. Permitir que o usuário combine livremente aspectos sustentados e não sustentados no mesmo input.
36. Responder cada aspecto sustentado com evidências citadas.
37. Nomear separadamente cada aspecto sem evidência suficiente no contexto recuperado.
38. Nunca apagar uma relação parcial válida apenas porque outro aspecto do input não possui evidência.
39. Tratar resultados recuperados como candidatos até a composição determinística do contexto disponível concluída pela aplicação.
40. Entregar ao provedor somente o contexto final autorizado, com fontes primárias e papéis explícitos de núcleo ou convergência nas rotas semânticas.
41. Exigir que toda evidência mantida no resultado seja citada no trecho analítico em que contribui para a resposta.
42. Descartar evidência recuperada omitida no texto e rejeitar marcador isolado ou inventário de citações que não demonstre incorporação analítica.
43. Nunca acrescentar automaticamente uma citação omitida pelo provedor para fazer a resposta aparentar conformidade.
44. Tratar `QUERY_MAX_EVIDENCE` como limite global de evidências primárias entregues ao provedor em cada consulta, aplicado depois do CIE nas rotas semânticas.
45. Não confundir contexto recuperado com evidência utilizada; a aplicação autoriza o conjunto disponível e conserva no resultado somente as fontes efetivamente citadas.
46. Tratar `QUERY_MAX_INTERACTIONS` como limite de saída relacional transitória, nunca como quantidade de evidências, pares persistidos ou combinações antecipadas.
47. Desativar interações quando `QUERY_MAX_INTERACTIONS` for zero sem desativar a resposta documental baseada em evidências.
48. Detectar a intenção relacional localmente, por regras determinísticas normalizadas, sem criar uma chamada de IA anterior à recuperação.
49. Nunca aceitar, reparar ou completar uma resposta cujo `finish_reason` seja `length`.
50. Permitir no máximo uma regeneração integral e compacta após truncamento, sem ultrapassar `AI_QUERY_MAX_OUTPUT_TOKENS`.
51. Tratar `AI_QUERY_MAX_OUTPUT_TOKENS` como teto por tentativa e `QUERY_MAX_INTERACTIONS` como teto de interações, nunca como metas de preenchimento.
52. Validar a compatibilidade de todas as unidades pendentes com o limite de entrada do provedor antes de enviar qualquer lote de embeddings.
53. Nunca truncar, cortar ou fragmentar arbitrariamente uma evidência para produzir seu embedding.
54. Representar uma primária excedente pelo embedding de uma síntese derivada válida somente quando a linhagem até a evidência primária integral estiver persistida.
55. Aplicar o CIE somente às distribuições vetoriais das rotas conceitual e relacional.
56. Limitar o conjunto estatístico por `QUERY_CANDIDATE_LIMIT`, com padrão 20 e intervalo efetivo de 1 a 200 candidatos por documento.
57. Calcular média e desvio padrão populacionais sobre o Top-k e calcular `CV = σ / μ`, usando `null` quando `μ = 0`.
58. Classificar como descarte `s < μ`, convergência `μ ≤ s < μ + σ` e núcleo `s ≥ μ + σ`.
59. Usar o núcleo como referência principal e a faixa de convergência como contexto complementar disponível; quando o núcleo estiver vazio, promover a convergência ao papel principal.
60. Preservar a ordem do Retriever dentro das regiões; não criar nota, peso, heurística subjetiva ou reranking por IA.
61. Resolver candidatos derivados selecionados pelo CIE até suas fontes primárias antes de aplicar o limite global e chamar o provedor de resposta.
62. Não persistir candidatos, similaridades, estatísticas, regiões ou seleção do CIE como memória documental.
63. Interromper a vetorização com o identificador da evidência quando uma primária excedente não possuir síntese derivada compatível, exigindo subdivisão estrutural real.
64. Exigir que `used_evidence_ids` contenha somente evidências efetivamente citadas na resposta.
65. Preservar `core` como precedência argumentativa e usar `convergence` somente quando contribuir como reforço, contexto, limite ou contraponto sustentado literalmente.
66. Não inventar relações para acomodar uma evidência recuperada; candidatos sem contribuição citada devem ser descartados sem invalidar a resposta.
67. Manter a calibração semântica estrita de `simetry` e `assimetry` separada da validade documental da resposta.
68. Descartar silenciosamente uma resposta rejeitada pela validação local e permitir no máximo três tentativas totais com o mesmo contexto disponível; da segunda tentativa em diante, transmitir somente um código seguro de correção. Evidência recuperada mas não citada deve ser descartada da base final, nunca usada como motivo isolado para nova tentativa ou bloqueio integral.
69. Exibir erro ao usuário somente depois da terceira falha consecutiva de validação, usando mensagem genérica sem identificador de evidência ou regra técnica interna.
70. Manter módulos independentes de projetos, usuários e documentos; associações observadas pertencem ao módulo, não ao modelo persistente do Core.
71. Permitir zero, um ou vários módulos ativos e entregar cada evento somente aos assinantes declarados.
72. Conhecer no Core apenas contratos e capacidades genéricas, sem nome, menu, regra, HTML, CSS ou função específica de módulo.
73. Usar `module_events` como única tabela adicional do banco principal para a caixa postal neutra, sem alterar tabelas preexistentes.
74. Manter esquema, histórico e cursor de cada módulo em seu próprio SQLite privado e excluído do versionamento.
75. Persistir idempotentemente o evento sanitizado depois da validação e antes da resposta HTTP, então tentar seu processamento imediato; manter processamento, registro idempotente e avanço de cursor de cada módulo na mesma transação SQLite privada.
76. Isolar a falha de um módulo da resposta documental e dos demais módulos assinantes.
77. Rejeitar eventos com campos sensíveis e não permitir que módulos escrevam na memória documental do Core.
78. Exigir confirmação digitada para exclusão definitiva e remover o pacote e todo o diretório privado de dados correspondente.
79. Não atribuir notas, pesos, confiança ou qualquer valor subjetivo às observações pedagógicas produzidas por módulos.
