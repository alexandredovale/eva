# Figure contract templates

This directory contains one independent example file for every supported document format and figure-contract language.

| Language | Markdown | JSON | XML |
|---|---|---|---|
| Portuguese | [`pt-BR/figure.md`](pt-BR/figure.md) | [`pt-BR/figure.json`](pt-BR/figure.json) | [`pt-BR/figure.xml`](pt-BR/figure.xml) |
| English | [`en/figure.md`](en/figure.md) | [`en/figure.json`](en/figure.json) | [`en/figure.xml`](en/figure.xml) |

Each file is a minimal ingestible document and may be copied as a starting point. Replace the thematic title, figure title, path, and descriptive metadata without removing the hierarchical relationship between topic and figure.

`Arquivo`/`File` is required to resolve the image. The other four fields are optional for the parser but recommended to provide useful documentary context and alternative text. The logical prefix may be `figuras/` or `figures/`; neither is repeated inside the physical `storage/figures/{EVA-D...}/` directory.

Portuguese version: [`README.pt-BR.md`](README.pt-BR.md).
