# Baseline Pré-Implementação Modular EVA

Data: 2026-08-03
Aplicação: EVA 1.1.1
PHP CLI: 8.2.12

## Ambiente

- `PDO`: habilitado;
- `pdo_mysql`: habilitado;
- `pdo_sqlite`: habilitado;
- SQLite: 3.39.2;
- execução real de IA: não utilizada.

## Regressão anterior ao update

Foram executados os 19 arquivos `tests/*Test.php` sem habilitar chamadas reais de IA.

Resultado:

- 17 arquivos concluíram com sucesso;
- 799 asserções explícitas foram aprovadas, além do teste real de backup/restauração;
- `EnvironmentConfigurationTest.php` já falhava porque o `.env` local contém três variáveis de credencial adicionais fora do inventário inferido pelos arquivos de configuração;
- `GoLiveReadinessTest.php` não foi executado porque exige `AI_LIVE_ENABLED=true` e `--live`.

As duas ocorrências foram classificadas como condições preexistentes do ambiente, não como regressões do update modular.

## Backup

- dump MySQL persistente: `database/backups/pre_modular_20260803_145954.sql`;
- tamanho do dump: 256232676 bytes;
- o teste `InfrastructureBackupRestoreTest.php` validou restauração integral em banco temporário;
- arquivos previstos para integração foram copiados para `updates/backups/pre_modular_20260803/`.

## Segurança

- nenhuma chave ou credencial foi exibida durante o baseline;
- a busca por padrões de segredo fora de `.env` e dos backups encontrou somente falsos positivos em URLs públicas da documentação;
- `.env` permaneceu inalterado e não foi copiado para a área de backup do update.

## Estado inicial

O sistema foi considerado apto a iniciar a implementação modular. Qualquer falha adicional observada após este baseline deverá ser tratada como possível regressão até investigação.
