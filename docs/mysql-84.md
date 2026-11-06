# Running the suite against MySQL 8.4

The production target is MySQL 8.4. Windows hosts without a local MySQL 8.4
install can run the suite against a containerised 8.4 server via Docker
Compose. (This host's `C:/tools/mysql` carries only 9.7.2; Docker Desktop is
installed but its daemon must be running.)

## Steps

```sh
# 1. Start Docker Desktop (Windows) and wait for the engine:
powershell -Command "Start-Process 'C:\Program Files\Docker\Docker\Docker Desktop.exe'"
docker info --format '{{.ServerVersion}}'   # repeats until the engine is up

# 2. Start the MySQL 8.4 container (creates laravix_test_suite automatically):
docker compose up -d mysql84
docker compose ps                           # wait until healthy

# 3. Run the suite against it:
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=33061 \
DB_DATABASE=laravix_test_suite DB_USERNAME=root DB_PASSWORD=laravix84 \
php vendor/pestphp/pest/bin/pest

# 4. Migration up/rollback/up against the same server uses a dedicated DB:
docker compose exec mysql84 mysql -uroot -plaravix84 \
  -e "DROP DATABASE IF EXISTS laravix_test_migrations; CREATE DATABASE laravix_test_migrations;"
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=33061 \
DB_DATABASE=laravix_test_migrations DB_USERNAME=root DB_PASSWORD=laravix84 \
php tools/verify-migrations.php

# 5. Multi-process race tools (same environment variables as step 3):
php tools/test-mysql-concurrency.php
php tools/test-correction-concurrency.php
php tools/test-receipt-concurrency.php

# 6. Teardown (drops the volume):
docker compose down -v
```

## Verified

2026-11-07 (after the Laravel 13 upgrade): Docker Desktop 29.2.0 with
`mysql:8.4` (8.4.13) — full Pest suite `1 todo, 344 passed (954 assertions)`
with **no deprecation bucket**, migration up/rollback/up PASS, and all three
race tools PASS. (Pre-upgrade run: 218 deprecated / 127 passed.)

## Notes

- The test database name must start with `laravix_test_`; `docker-compose.yml`
  pre-creates `laravix_test_suite` and the race tools create their own
  `laravix_test_*` companies inside it. Never point these tools at application
  data.
- `mysql:8.4` is the LTS innovation series used by the Step 1/3 validation
  runs (8.4.x); keep the major pinned at 8.
- On hosts without the phpredis extension (this one), prefix the verify and
  race commands with `CACHE_STORE=array SESSION_DRIVER=array QUEUE_CONNECTION=sync`;
  their default Redis cache store would abort with `Class "Redis" not found`.
- If port 33061 is taken, change the host-side port mapping only.
- Alternative without Docker: install MySQL 8.4 LTS from
  <https://dev.mysql.com/downloads/mysql/8.4.html> and set the same
  `DB_*` variables against your local server on any port.
