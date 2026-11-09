<?php

/*
 * Local development rehearsal only. This writes a temporary SQL dump, restores
 * it into a newly generated scratch schema, compares row counts, and drops only
 * that scratch schema. It never writes to the source schema.
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (config('database.default') !== 'mysql') {
    fwrite(STDERR, "The backup rehearsal requires the configured MySQL-compatible connection.\n");
    exit(2);
}

$connection = DB::connection()->getConfig();
$sourceDatabase = (string) $connection['database'];
$scratchDatabase = 'umis_restore_test_'.bin2hex(random_bytes(6));
$backupPath = tempnam(sys_get_temp_dir(), 'umis-helpdesk-backup-');
$scratchCreated = false;
$mysqlDirectory = PHP_OS_FAMILY === 'Windows' ? 'C:/xampp/mysql/bin/' : '';
$mysql = $mysqlDirectory.'mysql'.(PHP_OS_FAMILY === 'Windows' ? '.exe' : '');
$dump = $mysqlDirectory.'mysqldump'.(PHP_OS_FAMILY === 'Windows' ? '.exe' : '');
$environment = ['MYSQL_PWD' => (string) ($connection['password'] ?? '')];
$clientArguments = [
    '--host='.(string) $connection['host'],
    '--port='.(string) $connection['port'],
    '--user='.(string) $connection['username'],
];
$countSql = "SELECT CONCAT('migrations=',COUNT(*)) FROM migrations; SELECT CONCAT('users=',COUNT(*)) FROM users; SELECT CONCAT('tickets=',COUNT(*)) FROM tickets; SELECT CONCAT('assets=',COUNT(*)) FROM assets; SELECT CONCAT('maintenance_records=',COUNT(*)) FROM maintenance_records;";

try {
    if (! is_file($mysql) || ! is_file($dump) || $backupPath === false) {
        throw new RuntimeException('The local MySQL client tools or temporary backup file are unavailable.');
    }

    $dumpProcess = new Process(array_merge([$dump], $clientArguments, [
        '--single-transaction', '--routines', '--triggers', '--default-character-set=utf8mb4', $sourceDatabase,
    ]), dirname(__DIR__), $environment);
    $dumpProcess->mustRun();
    if (file_put_contents($backupPath, $dumpProcess->getOutput(), LOCK_EX) === false || filesize($backupPath) === 0) {
        throw new RuntimeException('The temporary database dump could not be written.');
    }

    $createProcess = new Process(array_merge([$mysql], $clientArguments, [
        '--execute=CREATE DATABASE '.$scratchDatabase.' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
    ]), dirname(__DIR__), $environment);
    $createProcess->mustRun();
    $scratchCreated = true;

    $restoreProcess = new Process(array_merge([$mysql], $clientArguments, ['--database='.$scratchDatabase]), dirname(__DIR__), $environment);
    $restoreProcess->setInput(file_get_contents($backupPath));
    $restoreProcess->mustRun();

    $sourceCounts = new Process(array_merge([$mysql], $clientArguments, ['--database='.$sourceDatabase, '--batch', '--skip-column-names', '--execute='.$countSql]), dirname(__DIR__), $environment);
    $sourceCounts->mustRun();
    $restoredCounts = new Process(array_merge([$mysql], $clientArguments, ['--database='.$scratchDatabase, '--batch', '--skip-column-names', '--execute='.$countSql]), dirname(__DIR__), $environment);
    $restoredCounts->mustRun();

    if (trim($sourceCounts->getOutput()) !== trim($restoredCounts->getOutput())) {
        throw new RuntimeException('Restored migration and operational record counts do not match the source.');
    }

    fwrite(STDOUT, "Backup and isolated restore rehearsal passed. Migration, user, ticket, asset, and maintenance record counts match.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'Backup/restore rehearsal did not pass: '.get_class($exception)."\n");
    $exitCode = 1;
} finally {
    if ($scratchCreated && preg_match('/^umis_restore_test_[a-f0-9]{12}$/', $scratchDatabase)) {
        $dropProcess = new Process(array_merge([$mysql], $clientArguments, ['--execute=DROP DATABASE '.$scratchDatabase]), dirname(__DIR__), $environment);
        $dropProcess->run();
    }

    if ($backupPath !== false && is_file($backupPath)) {
        unlink($backupPath);
    }
}

if (isset($exitCode)) {
    exit($exitCode);
}
