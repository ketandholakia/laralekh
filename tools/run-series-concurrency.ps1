$ErrorActionPreference = 'Stop'
$env:DB_CONNECTION = 'mysql'
$env:DB_HOST = '127.0.0.1'
$env:DB_PORT = '33360'
$env:DB_DATABASE = 'laravix_test_suite'
$env:DB_USERNAME = 'root'
$env:DB_PASSWORD = ''
$env:CACHE_STORE = 'array'
$env:QUEUE_CONNECTION = 'sync'
& php tools/test-series-concurrency.php
exit $LASTEXITCODE
