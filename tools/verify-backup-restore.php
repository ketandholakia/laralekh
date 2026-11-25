<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (config('database.default') !== 'mysql') {
    throw new RuntimeException('Backup/restore verification requires DB_CONNECTION=mysql.');
}

$sourceDb = (string) config('database.connections.mysql.database');
if (! str_starts_with($sourceDb, 'laravix_test_')) {
    throw new RuntimeException("Safety guard: source database '{$sourceDb}' must start with 'laravix_test_'.");
}

$host = (string) config('database.connections.mysql.host', '127.0.0.1');
$port = (string) config('database.connections.mysql.port', '3306');
$user = (string) config('database.connections.mysql.username', 'root');
$pass = (string) config('database.connections.mysql.password', '');

$restoreDb = 'laravix_test_restore_'.date('YmdHis');
$dumpPath = sys_get_temp_dir().DIRECTORY_SEPARATOR."{$sourceDb}_verify.sql";

// Ensure migrations are up to date on source database
$migrator = $app->make('migrator');
if (! $migrator->repositoryExists()) {
    $migrator->getRepository()->createRepository();
}
$migrator->run(database_path('migrations'));

$env = ['MYSQL_PWD' => $pass] + $_ENV + $_SERVER;

$runCmd = static function (string $cmd) use ($env): string {
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $proc = proc_open($cmd, $descriptors, $pipes, base_path(), $env);
    if (! is_resource($proc)) {
        throw new RuntimeException("Failed to execute: {$cmd}");
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($proc);
    if ($exit !== 0) {
        throw new RuntimeException("Command failed ({$exit}): {$cmd}\n{$stderr}");
    }

    return (string) $stdout;
};

try {
    // 1. Dump source database with triggers, routines, and consistent transaction snapshot
    $dumpCmd = sprintf(
        'mysqldump -h %s -P %s -u %s --single-transaction --routines --triggers --events --set-gtid-purged=OFF %s > %s',
        escapeshellarg($host),
        escapeshellarg($port),
        escapeshellarg($user),
        escapeshellarg($sourceDb),
        escapeshellarg($dumpPath)
    );
    $runCmd($dumpCmd);

    if (! file_exists($dumpPath) || filesize($dumpPath) === 0) {
        throw new RuntimeException('Dump file is missing or empty.');
    }
    $checksum = hash_file('sha256', $dumpPath);

    // 2. Create isolated restore target database
    DB::statement("DROP DATABASE IF EXISTS `{$restoreDb}`");
    DB::statement("CREATE DATABASE `{$restoreDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    // 3. Restore dump into target database
    $restoreCmd = sprintf(
        'mysql -h %s -P %s -u %s %s < %s',
        escapeshellarg($host),
        escapeshellarg($port),
        escapeshellarg($user),
        escapeshellarg($restoreDb),
        escapeshellarg($dumpPath)
    );
    $runCmd($restoreCmd);

    // 4. Verify table count and immutability trigger parity between source and restored DB
    $sourceTables = DB::select('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME', [$sourceDb]);
    $restoreTables = DB::select('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME', [$restoreDb]);
    if (count($sourceTables) !== count($restoreTables)) {
        throw new RuntimeException('Table count mismatch after restore.');
    }

    $sourceTriggers = DB::select('SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = ? ORDER BY TRIGGER_NAME', [$sourceDb]);
    $restoreTriggers = DB::select('SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = ? ORDER BY TRIGGER_NAME', [$restoreDb]);
    if (count($sourceTriggers) !== count($restoreTriggers) || count($restoreTriggers) < 46) {
        throw new RuntimeException('Trigger count parity failed after restore.');
    }

    $sourceMigrations = DB::select("SELECT COUNT(*) AS cnt FROM `{$sourceDb}`.`migrations`")[0]->cnt;
    $restoreMigrations = DB::select("SELECT COUNT(*) AS cnt FROM `{$restoreDb}`.`migrations`")[0]->cnt;
    if ((int) $sourceMigrations !== (int) $restoreMigrations) {
        throw new RuntimeException('Migrations table count mismatch after restore.');
    }

    echo sprintf(
        "PASS: Backup & restore verified (%s -> %s). Tables: %d, Triggers: %d, Migrations: %d, SHA-256: %s\n",
        $sourceDb,
        $restoreDb,
        count($restoreTables),
        count($restoreTriggers),
        (int) $restoreMigrations,
        $checksum
    );
} finally {
    DB::statement("DROP DATABASE IF EXISTS `{$restoreDb}`");
    if (file_exists($dumpPath)) {
        @unlink($dumpPath);
    }
}
