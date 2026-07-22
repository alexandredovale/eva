# Regras obrigatórias

1. Aceitar somente Markdown, JSON e XML como fontes documentais.
2. Preservar conteúdo, ordem, hierarquia e referência da origem.
3. Não confundir texto original com conteúdo gerado por IA.
4. Persistir evidências com `evidence_class` e `evidence_type` explícitos.
5. Construir sínteses superiores somente a partir de evidências inferiores identificadas.
6. Gerar embeddings somente de unidades organizadas, nunca por cortes arbitrários de tamanho.
7. Manter evidências, derivações e embeddings como núcleo persistente do Evidence Algorithm.
8. Não persistir Cnodes, pares candidatos, análises de interação ou métricas relacionais.
9. Produzir interações somente em consultas relacionais e dentro do limite solicitado.
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
39. Tratar resultados literais, lexicais e estruturais como candidatos sujeitos à análise da IA.
40. Entregar ao provedor todos os candidatos recuperados dentro do limite, inclusive possíveis intrusos.
41. Citar somente candidatos que sustentem efetivamente a resposta.
42. Não descartar uma resposta válida pela presença de candidatos intrusos.
43. Aceitar ausência de evidências utilizadas após análise somente com limitação explícita e sem citações ou interações.
44. Tratar `QUERY_MAX_EVIDENCE` como limite global de evidências candidatas entregues ao provedor em cada consulta.
45. Não confundir evidência candidata com evidência utilizada; somente a IA pode selecionar, dentro do contexto permitido, quais candidatas sustentam a resposta.
46. Tratar `QUERY_MAX_INTERACTIONS` como limite de saída relacional transitória, nunca como quantidade de evidências, pares persistidos ou combinações antecipadas.
47. Desativar interações quando `QUERY_MAX_INTERACTIONS` for zero sem desativar a resposta documental baseada em evidências.
48. Detectar a intenção relacional localmente, por regras determinísticas normalizadas, sem criar uma chamada de IA anterior à recuperação.
49. Nunca aceitar, reparar ou completar uma resposta cujo `finish_reason` seja `length`.
50. Permitir no máximo uma regeneração integral e compacta após truncamento, sem ultrapassar `AI_QUERY_MAX_OUTPUT_TOKENS`.
51. Tratar `AI_QUERY_MAX_OUTPUT_TOKENS` como teto por tentativa e `QUERY_MAX_INTERACTIONS` como teto de interações, nunca como metas de preenchimento.
52. Validar a compatibilidade de todas as unidades pendentes com o limite de entrada do provedor antes de enviar qualquer lote de embeddings.
53. Nunca truncar, cortar ou fragmentar arbitrariamente uma evidência para produzir seu embedding.
54. Representar uma primária excedente pelo embedding de uma síntese derivada válida somente quando a linhagem até a evidência primária integral estiver persistida.
55. Interromper a vetorização com o identificador da evidência quando uma primária excedente não possuir síntese derivada compatível, exigindo subdivisão estrutural real.
