# Templates de contrato de figura

Este diretório contém um arquivo de exemplo exclusivo para cada combinação aceita de formato documental e idioma do contrato de figura.

| Idioma | Markdown | JSON | XML |
|---|---|---|---|
| Português | [`pt-BR/figure.md`](pt-BR/figure.md) | [`pt-BR/figure.json`](pt-BR/figure.json) | [`pt-BR/figure.xml`](pt-BR/figure.xml) |
| Inglês | [`en/figure.md`](en/figure.md) | [`en/figure.json`](en/figure.json) | [`en/figure.xml`](en/figure.xml) |

Cada arquivo é um documento mínimo válido para ingestão e pode ser copiado como ponto de partida. Troque o título temático, o título da figura, o caminho e os metadados descritivos sem remover a relação hierárquica entre tópico e figura.

`Arquivo`/`File` é obrigatório para resolver a imagem. Os demais quatro campos são opcionais para o parser, mas recomendados para produzir contexto documental e texto alternativo úteis. O prefixo lógico pode ser `figuras/` ou `figures/`; nenhum deles é repetido no diretório físico `storage/figures/{EVA-D...}/`.

English version: [`README.md`](README.md).
