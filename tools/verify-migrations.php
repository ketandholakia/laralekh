<?php
declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'laravix_test_migrations') {
    throw new RuntimeException('This rollback check requires the dedicated laravix_test_migrations database.');
}
$migrator = $app->make('migrator');
if (!$migrator->repositoryExists()) { $migrator->getRepository()->createRepository(); }
$migrator->run(database_path('migrations'));
$migrator->reset([database_path('migrations')]);
$migrator->run(database_path('migrations'));
echo "PASS: MySQL migration up, full rollback, and reapply.\n";
