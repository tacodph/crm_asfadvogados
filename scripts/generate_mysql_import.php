<?php

/**
 * Gera dump completo do banco PostgreSQL para MySQL: database/u802141539_crmasf.sql
 *
 * Contém SCHEMA (DDL) + DADOS (DML) de todas as tabelas do CRM e catálogo IBGE,
 * 100% autocontido e pronto para importação no banco MySQL de destino.
 *
 * Uso: php scripts/generate_mysql_import.php
 */

require __DIR__.'/../vendor/autoload.php';

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$targetDb = 'u802141539_crmasf';
$outputPath = database_path("{$targetDb}.sql");
$sourceSchema = 'crm_asfadvogados';
$ibgeSchema = 'base_dados_ibge';

/**
 * Tabelas do CRM ASF Advogados em ordem de dependência referencial.
 *
 * @var list<string>
 */
$crmTables = [
    // 1. Tenancy e Catálogos Globais
    'tenants',
    'roles',
    'ufs',
    'tipos_pessoa',
    'users',
    'setores',
    'canais_contato',
    'status_consentimentos',
    'finalidades_consentimento',
    'status_conflitos',
    'status_comerciais',
    'status_atendimentos',
    'status_qualificacoes',

    // 2. Entidades Principais de Contato
    'empresas',
    'contatos',
    'consentimentos_contato',

    // 3. Funil de Vendas e Negociações
    'funis',
    'etapas_funil',
    'negociacoes',
    'historicos_negociacao',
    'tarefas_negociacao',
    'propostas',

    // 4. Módulos Meta (CAPI Conversões + Marketing Ads)
    'meta_conversao_configs',
    'meta_conversao_eventos',
    'meta_conversao_estatisticas',
    'meta_ads_contas',
    'meta_ads_campanhas',
    'meta_ads_conjuntos',
    'meta_ads_anuncios',
    'meta_ads_insights_diarios',
    'meta_ads_sync_execucoes',

    // 5. Tabelas do Framework Laravel
    'migrations',
    'passkeys',
    'password_reset_tokens',
    'cache',
    'cache_locks',
    'jobs',
    'job_batches',
    'failed_jobs',
    'sessions',
];

/**
 * Tabelas do Catálogo IBGE.
 *
 * @var list<string>
 */
$ibgeTables = ['tab_estados', 'tab_municipios'];

function mapPgType(object $col): string
{
    $name = $col->column_name;
    $type = $col->udt_name ?? $col->data_type;

    return match ($type) {
        'int8', 'bigint' => 'BIGINT UNSIGNED',
        'int4', 'integer' => 'INT UNSIGNED',
        'int2', 'smallint' => 'SMALLINT UNSIGNED',
        'bool', 'boolean' => 'TINYINT(1)',
        'text' => 'TEXT',
        'json', 'jsonb' => 'JSON',
        'date' => 'DATE',
        'time', 'time without time zone' => 'TIME',
        'timestamp', 'timestamptz', 'timestamp without time zone' => 'TIMESTAMP',
        'numeric', 'decimal' => isset($col->numeric_precision, $col->numeric_scale)
            ? "DECIMAL({$col->numeric_precision}, {$col->numeric_scale})"
            : 'DECIMAL(12, 2)',
        'varchar', 'character varying' => isset($col->character_maximum_length)
            ? "VARCHAR({$col->character_maximum_length})"
            : 'VARCHAR(255)',
        default => 'VARCHAR(255)',
    };
}

function mysqlDefault(?string $default, string $mysqlType): ?string
{
    if ($default === null || $default === '') {
        return null;
    }

    if (str_contains($default, 'nextval(')) {
        return null;
    }

    if ($default === 'false') {
        return '0';
    }

    if ($default === 'true') {
        return '1';
    }

    if (is_numeric($default)) {
        return (string) $default;
    }

    if (preg_match("/^'([^']*)'::/", $default, $m)) {
        return "'".str_replace("'", "''", $m[1])."'";
    }

    if (preg_match('/^(\d+)::/', $default, $m)) {
        return $m[1];
    }

    if ($default === 'CURRENT_TIMESTAMP') {
        return 'CURRENT_TIMESTAMP';
    }

    return null;
}

function fetchColumns(string $schema, string $table): array
{
    return DB::select('
        SELECT column_name, data_type, udt_name, character_maximum_length,
               numeric_precision, numeric_scale, is_nullable, column_default
        FROM information_schema.columns
        WHERE table_schema = ? AND table_name = ?
        ORDER BY ordinal_position
    ', [$schema, $table]);
}

function fetchForeignKeys(string $schema, string $table): array
{
    return DB::select("
        SELECT DISTINCT
            tc.constraint_name,
            kcu.column_name,
            ccu.table_schema AS foreign_table_schema,
            ccu.table_name AS foreign_table_name,
            ccu.column_name AS foreign_column_name,
            rc.delete_rule
        FROM information_schema.table_constraints AS tc
        JOIN information_schema.key_column_usage AS kcu
          ON tc.constraint_name = kcu.constraint_name
         AND tc.table_schema = kcu.table_schema
        JOIN information_schema.constraint_column_usage AS ccu
          ON ccu.constraint_name = tc.constraint_name
         AND ccu.constraint_schema = tc.table_schema
        JOIN information_schema.referential_constraints AS rc
          ON rc.constraint_name = tc.constraint_name
         AND rc.constraint_schema = tc.table_schema
        WHERE tc.constraint_type = 'FOREIGN KEY'
          AND tc.table_schema = ?
          AND tc.table_name = ?
        ORDER BY tc.constraint_name
    ", [$schema, $table]);
}

function fetchIndexes(string $schema, string $table): array
{
    return DB::select('
        SELECT indexname, indexdef
        FROM pg_indexes
        WHERE schemaname = ? AND tablename = ?
        ORDER BY indexname
    ', [$schema, $table]);
}

function onDeleteClause(string $rule, string $column = ''): string
{
    if ($column === 'municipio_id') {
        return 'ON DELETE SET NULL';
    }

    return match ($rule) {
        'CASCADE' => 'ON DELETE CASCADE',
        'SET NULL' => 'ON DELETE SET NULL',
        'RESTRICT', 'NO ACTION' => 'ON DELETE RESTRICT',
        default => '',
    };
}

function escapeMySqlString(string $value): string
{
    return str_replace(
        ['\\', "\0", "\n", "\r", "'", "\x1a"],
        ['\\\\', '\\0', '\\n', '\\r', "\\'", '\\Z'],
        $value
    );
}

function sqlValue(mixed $value, object $col): string
{
    if ($value === null) {
        return 'NULL';
    }

    $name = $col->column_name;
    $type = $col->udt_name ?? $col->data_type;

    if ($type === 'bool' || $type === 'boolean' || is_bool($value)) {
        if ($value === true || $value === 't' || $value === 'true' || $value === 1 || $value === '1') {
            return '1';
        }

        return '0';
    }

    if (in_array($type, ['int8', 'int4', 'int2', 'bigint', 'integer', 'smallint'], true)) {
        return (string) (int) $value;
    }

    if (in_array($type, ['numeric', 'decimal', 'float4', 'float8'], true)) {
        return (string) $value;
    }

    if (in_array($type, ['json', 'jsonb'], true)) {
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return "'".escapeMySqlString((string) $value)."'";
    }

    if ($type === 'date') {
        $str = (string) $value;
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $str, $m)) {
            return "'{$m[0]}'";
        }

        return 'NULL';
    }

    if ($type === 'time' || $type === 'time without time zone') {
        $str = (string) $value;
        if (preg_match('/^\d{2}:\d{2}(:\d{2})?/', $str, $m)) {
            return "'{$m[0]}'";
        }

        return 'NULL';
    }

    if (str_contains($type, 'timestamp') || str_ends_with($name, '_at') || str_ends_with($name, '_em')) {
        $str = (string) $value;
        if (preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}/', $str)) {
            return "'".substr(str_replace('T', ' ', $str), 0, 19)."'";
        }
        if ($str === '') {
            return 'NULL';
        }
    }

    return "'".escapeMySqlString((string) $value)."'";
}

function ibgeTableDefinitions(): array
{
    return [
        'tab_estados' => <<<'SQL'
CREATE TABLE `tab_estados` (
  `id` INT UNSIGNED NOT NULL,
  `txt_uf` VARCHAR(255) NOT NULL,
  `txt_sigla_uf` VARCHAR(255) NULL,
  `txt_gentilico` VARCHAR(255) NULL,
  `txt_nome_governador` VARCHAR(255) NULL,
  `txt_nome_capital` VARCHAR(255) NULL,
  `vlr_area_territorial_km2` DECIMAL(14, 4) NULL,
  `num_populacao_ultimo_censo` INT UNSIGNED NULL,
  `vlr_densidade_demografica_hab_km2` DECIMAL(14, 4) NULL,
  `num_populacao_estimada` INT UNSIGNED NULL,
  `vlr_idh` DECIMAL(8, 4) NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,
        'tab_municipios' => <<<'SQL'
CREATE TABLE `tab_municipios` (
  `id` INT UNSIGNED NOT NULL,
  `txt_nome_municipios` VARCHAR(255) NULL,
  `cod_municipio_6dig` INT UNSIGNED NULL,
  `cod_regiao_geografica_imediata` INT UNSIGNED NULL,
  `cod_regiao_geografica_intermediaria` INT UNSIGNED NULL,
  `estado_id` INT UNSIGNED NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tab_municipios_cod_municipio_6dig_index` (`cod_municipio_6dig`),
  KEY `tab_municipios_estado_id_index` (`estado_id`),
  CONSTRAINT `tab_municipios_estado_id_foreign` FOREIGN KEY (`estado_id`) REFERENCES `tab_estados` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,
    ];
}

function buildCreateTable(string $schema, string $table): string
{
    $columns = fetchColumns($schema, $table);
    $fks = fetchForeignKeys($schema, $table);
    $indexes = fetchIndexes($schema, $table);

    $pkColumns = [];
    foreach ($indexes as $idx) {
        if (! str_ends_with($idx->indexname, '_pkey')) {
            continue;
        }

        if (preg_match('/\(([^)]+)\)/', $idx->indexdef, $m)) {
            $pkColumns = array_map(static fn (string $c) => trim($c), explode(',', $m[1]));
        }
    }

    $lines = [];

    foreach ($columns as $col) {
        $mysqlType = mapPgType($col);
        $nullable = $col->is_nullable === 'YES' ? 'NULL' : 'NOT NULL';
        $default = mysqlDefault($col->column_default, $mysqlType);
        $isAutoIncrement = $col->column_default !== null && str_contains((string) $col->column_default, 'nextval(');

        if ($isAutoIncrement && $col->column_name === 'id') {
            $line = "  `{$col->column_name}` {$mysqlType} NOT NULL AUTO_INCREMENT";
        } else {
            $line = "  `{$col->column_name}` {$mysqlType} {$nullable}";
            if ($default !== null) {
                $line .= " DEFAULT {$default}";
            } elseif ($mysqlType === 'TIMESTAMP' && $nullable === 'NULL') {
                $line .= ' DEFAULT NULL';
            }
        }

        $lines[] = $line;
    }

    $handledFks = [];
    foreach ($fks as $fk) {
        $constraintName = $fk->constraint_name;
        if (isset($handledFks[$constraintName])) {
            continue;
        }
        $handledFks[$constraintName] = true;

        $lines[] = sprintf(
            '  CONSTRAINT `%s` FOREIGN KEY (`%s`) REFERENCES `%s` (`%s`) %s',
            $constraintName,
            $fk->column_name,
            $fk->foreign_table_name,
            $fk->foreign_column_name,
            onDeleteClause($fk->delete_rule, $fk->column_name),
        );
    }

    if ($pkColumns !== []) {
        $lines[] = '  PRIMARY KEY (`'.implode('`, `', $pkColumns).'`)';
    }

    $sql = "CREATE TABLE `{$table}` (\n".implode(",\n", $lines)."\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n";

    $extraIndexes = [];
    $handledIndexes = [];
    foreach ($indexes as $idx) {
        if (str_ends_with($idx->indexname, '_pkey')) {
            continue;
        }

        if (isset($handledIndexes[$idx->indexname])) {
            continue;
        }
        $handledIndexes[$idx->indexname] = true;

        if (! preg_match('/CREATE (UNIQUE )?INDEX (\w+) ON [^.]+\.(\w+) USING btree \((.+)\)/', $idx->indexdef, $m)) {
            continue;
        }

        $unique = $m[1] !== '';
        $indexName = $m[2];
        $indexTable = $m[3];
        $colsRaw = $m[4];
        $cols = array_map(static fn (string $c) => '`'.trim($c).'`', explode(',', $colsRaw));
        $extraIndexes[] = sprintf(
            'CREATE %sINDEX `%s` ON `%s` (%s);',
            $unique ? 'UNIQUE ' : '',
            $indexName,
            $indexTable,
            implode(', ', $cols),
        );
    }

    if ($extraIndexes !== []) {
        $sql .= "\n".implode("\n", $extraIndexes)."\n";
    }

    return $sql;
}

/**
 * Despeja os dados de uma tabela em lotes de INSERT INTO.
 *
 * @param  resource  $fp
 * @param  list<object>  $columnsMeta
 */
function dumpTableData($fp, string $schema, string $table, array $columnsMeta, int $batchSize = 100): int
{
    $colNames = array_map(static fn ($c) => $c->column_name, $columnsMeta);
    $columnList = '`'.implode('`, `', $colNames).'`';

    $hasId = in_array('id', $colNames, true);
    $orderClause = $hasId ? 'ORDER BY id ASC' : '';

    $rows = DB::select("SELECT * FROM {$schema}.{$table} {$orderClause}");
    $total = count($rows);

    if ($total === 0) {
        fwrite($fp, "-- Dados da tabela `{$table}`: 0 registros\n\n");

        return 0;
    }

    fwrite($fp, "-- Dados da tabela `{$table}`: {$total} registro(s)\n");
    $chunks = array_chunk($rows, $batchSize);

    foreach ($chunks as $chunk) {
        $values = [];
        foreach ($chunk as $row) {
            $rowValues = [];
            foreach ($columnsMeta as $col) {
                $rowValues[] = sqlValue($row->{$col->column_name} ?? null, $col);
            }
            $values[] = '('.implode(', ', $rowValues).')';
        }

        fwrite($fp, "INSERT INTO `{$table}` ({$columnList}) VALUES\n".implode(",\n", $values).";\n\n");
    }

    return $total;
}

// ============================================================
// Execução Principal do Dump
// ============================================================

echo "Iniciando geração do dump para MySQL ({$targetDb})...\n";

$fp = fopen($outputPath, 'w');
if (! $fp) {
    throw new RuntimeException("Não foi possível abrir o arquivo para escrita: {$outputPath}");
}

// 1. Cabeçalho compatível com MySQL e UTF-8
fwrite($fp, "-- ============================================================\n");
fwrite($fp, "-- CRM ASF Advogados — Dump PostgreSQL -> MySQL\n");
fwrite($fp, "-- Banco de Destino: {$targetDb}\n");
fwrite($fp, '-- Data de Geração: '.now()->toDateTimeString()."\n");
fwrite($fp, "-- ============================================================\n\n");

fwrite($fp, "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n");
fwrite($fp, "/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\n");
fwrite($fp, "/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\n");
fwrite($fp, "/*!50503 SET NAMES utf8mb4 */;\n");
fwrite($fp, "/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;\n");
fwrite($fp, "/*!40103 SET TIME_ZONE='+00:00' */;\n");
fwrite($fp, "/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;\n");
fwrite($fp, "/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;\n");
fwrite($fp, "/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;\n");
fwrite($fp, "/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;\n\n");

fwrite($fp, "-- Descomente a linha abaixo se tiver privilégios de criar o banco no servidor:\n");
fwrite($fp, "-- CREATE DATABASE IF NOT EXISTS `{$targetDb}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n");
fwrite($fp, "USE `{$targetDb}`;\n\n");

// 2. Drop de tabelas em ordem inversa de dependência
fwrite($fp, "-- ------------------------------------------------------------\n");
fwrite($fp, "-- Limpeza de tabelas existentes (se houver)\n");
fwrite($fp, "-- ------------------------------------------------------------\n");

$allTables = array_merge($ibgeTables, $crmTables);
$dropOrder = array_reverse($allTables);
foreach ($dropOrder as $table) {
    fwrite($fp, "DROP TABLE IF EXISTS `{$table}`;\n");
}
fwrite($fp, "\n");

// 3. Criação das tabelas do IBGE (armazenadas diretamente em u802141539_crmasf para ambiente autocontido)
fwrite($fp, "-- ============================================================\n");
fwrite($fp, "-- Catálogo IBGE (tab_estados e tab_municipios)\n");
fwrite($fp, "-- ============================================================\n\n");

$ibgeDefs = ibgeTableDefinitions();
foreach ($ibgeTables as $ibgeTable) {
    fwrite($fp, $ibgeDefs[$ibgeTable]."\n\n");
}

// 4. Criação das tabelas do CRM
fwrite($fp, "-- ============================================================\n");
fwrite($fp, "-- Tabelas de Negócio do CRM ASF Advogados\n");
fwrite($fp, "-- ============================================================\n\n");

foreach ($crmTables as $table) {
    fwrite($fp, buildCreateTable($sourceSchema, $table)."\n");
}

// 5. Carga de Dados do Catálogo IBGE
fwrite($fp, "-- ============================================================\n");
fwrite($fp, "-- Inserção de Dados: Catálogo IBGE\n");
fwrite($fp, "-- ============================================================\n\n");

$estadosCols = fetchColumns($ibgeSchema, 'tab_estados');
$estadosCount = dumpTableData($fp, $ibgeSchema, 'tab_estados', $estadosCols, 100);

$municipiosCols = fetchColumns($ibgeSchema, 'tab_municipios');
$municipiosCount = dumpTableData($fp, $ibgeSchema, 'tab_municipios', $municipiosCols, 200);

// 7. Carga de Dados das Tabelas CRM
fwrite($fp, "-- ============================================================\n");
fwrite($fp, "-- Inserção de Dados: Tabelas do CRM ASF Advogados\n");
fwrite($fp, "-- ============================================================\n\n");

$crmCounts = [];
$totalCrmRows = 0;

foreach ($crmTables as $table) {
    $cols = fetchColumns($sourceSchema, $table);
    $count = dumpTableData($fp, $sourceSchema, $table, $cols, 100);
    $crmCounts[$table] = $count;
    $totalCrmRows += $count;
}

// 8. Rodapé com restauração das checagens e flags
fwrite($fp, "-- ============================================================\n");
fwrite($fp, "-- Finalização do Dump\n");
fwrite($fp, "-- ============================================================\n\n");
fwrite($fp, "/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;\n");
fwrite($fp, "/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;\n");
fwrite($fp, "/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;\n");
fwrite($fp, "/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;\n");
fwrite($fp, "/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;\n");
fwrite($fp, "/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;\n");
fwrite($fp, "/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;\n");
fwrite($fp, "/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;\n");

fclose($fp);

$fileSizeBytes = filesize($outputPath);
$fileSizeMb = round($fileSizeBytes / (1024 * 1024), 2);

echo "Dump gerado com sucesso em: {$outputPath}\n";
echo "Tamanho do arquivo: {$fileSizeMb} MB (".number_format($fileSizeBytes, 0, ',', '.')." bytes)\n";
echo 'Tabelas criadas: '.(count($crmTables) + count($ibgeTables))." (CRM: 40, IBGE: 2)\n";
echo "Registros IBGE: {$estadosCount} estados, {$municipiosCount} municípios\n";
echo "Registros CRM: {$totalCrmRows} registros nas tabelas de negócio\n\n";
echo "Destaques dos dados exportados:\n";
foreach ($crmCounts as $tbl => $cnt) {
    if ($cnt > 0) {
        echo sprintf("  - %-30s: %d registros\n", $tbl, $cnt);
    }
}
