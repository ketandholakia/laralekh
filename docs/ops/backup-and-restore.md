# Backup & Restore Strategy and Runbook (LL-P5-003)

LaraLekh is an immutable multi-tenant Indian GST accounting system. Accounting and tax records cannot be reconstructed from scratch if lost; furthermore, **CGST Act Section 36** mandates retaining books of account and records for **72 months (6 years)** from the due date of furnishing the annual return for the relevant financial year (effectively ~7–8 years).

---

## 1. Recovery Objectives

| Metric | Target | Mechanism |
|---|---|---|
| **RPO (Recovery Point Objective)** | $\le 24\text{ hours}$ (daily dump); $\le 5\text{ minutes}$ with MySQL binary logging (PITR) | Daily automated `mysqldump` + optional MySQL 8.4 `binlog` archiving |
| **RTO (Recovery Time Objective)** | $\le 30\text{ minutes}$ | Compressed SQL restore into fresh MySQL 8.4 schema + trigger verification |

---

## 2. What Must Be Backed Up

1. **MySQL 8.4 Primary Database** (via `tools/backup-mysql.sh`):
   - Must include `--single-transaction --routines --triggers --events --hex-blob --set-gtid-purged=OFF`.
   - `--triggers` is **mandatory**: LaraLekh relies on 46+ MySQL immutability triggers (`vouchers`, `invoices`, `purchase_bills`, `receipts`, `gst_return_snapshots`, etc.). A dump without `--triggers` silently strips database-level immutability.
2. **Application Secrets & Keys (stored separately from DB dumps)**:
   - `.env` (`APP_KEY`, `DB_PASSWORD`, mail/object-storage credentials). Never store `APP_KEY` in the same archive or unencrypted bucket as database dumps.
3. **File Storage (`storage/app/`)**:
   - Uploaded GSTR-2B files, company logos, and exported archives.

---

## 3. Retention Schedule (GFS + Statutory Archive)

| Tier | Frequency | Retention | Storage |
|---|---|---|---|
| **Daily** | 02:00 IST | 14 days | Encrypted local + off-site object storage |
| **Weekly** | Sunday 03:00 IST | 8 weeks | Off-site object storage (immutable/WORM bucket lock recommended) |
| **Monthly / FY Close** | 1st of month / post-audit FY close | **8 years (96 months)** | Cold/Glacier WORM archive per CGST Act s. 36 |

All backup artifacts must be accompanied by a `.sha256` manifest (`sha256sum`) and encrypted at rest (SSE-KMS or GPG/age).

---

## 4. Automated Backup Execution

Run `tools/backup-mysql.sh` via cron or systemd timer:

```bash
DB_HOST=127.0.0.1 \
DB_PORT=3306 \
DB_DATABASE=laravix \
DB_USERNAME=laravix_backup \
DB_PASSWORD="<secret>" \
BACKUP_DIR=/var/backups/laravix \
RETENTION_DAYS=14 \
  bash tools/backup-mysql.sh
```

Required MySQL privileges for the `laravix_backup` user:
`SELECT, LOCK TABLES, SHOW VIEW, EVENT, TRIGGER, PROCESS` (plus `RELOAD` / `BACKUP_ADMIN` if using `FLUSH TABLES` / lockless backup).

---

## 5. Restore Runbook (Step-by-Step)

> **Safety rule:** Never overwrite a live production database in-place before verifying the restore in an isolated staging/test database.

### Step 1: Verify Backup Integrity
```bash
sha256sum -c /var/backups/laravix/daily/laravix_20261125T020000Z.sql.gz.sha256
```

### Step 2: Restore into an Isolated Staging/Verification Database
```bash
mysql -h 127.0.0.1 -u root -p -e \
  "CREATE DATABASE laravix_restore CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

gzip -dc /var/backups/laravix/daily/laravix_20261125T020000Z.sql.gz \
  | mysql -h 127.0.0.1 -u root -p laravix_restore
```

### Step 3: Post-Restore Integrity Checklist
Run the following verification checks before promoting or switching `DB_DATABASE`:

1. **Migration state:**
   ```bash
   DB_DATABASE=laravix_restore php artisan migrate:status
   ```
2. **Trigger count parity (must be $\ge 46$):**
   ```sql
   SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = 'laravix_restore';
   ```
3. **Accounting Trial Balance parity (`SUM(debit) == SUM(credit)` per company):**
   ```sql
   SELECT company_id, SUM(debit) AS total_dr, SUM(credit) AS total_cr
   FROM laravix_restore.voucher_lines
   GROUP BY company_id
   HAVING total_dr <> total_cr;
   ```
   *(Must return **0 rows**.)*
4. **Immutability trigger tamper probe** (on a non-production copy):
   ```sql
   UPDATE laravix_restore.vouchers SET narration = 'restore-probe' WHERE id = (SELECT id FROM (SELECT id FROM laravix_restore.vouchers LIMIT 1) t);
   -- Expected: ERROR 1644 (45000): Immutable or invalid accounting document
   ```

### Step 4: Cut Over Application
1. Put application into maintenance mode: `php artisan down --retry=60`
2. Update `.env` `DB_DATABASE` (or swap database), clear config cache: `php artisan config:cache`
3. Restart queue workers: `php artisan queue:restart`
4. Bring application up: `php artisan up`

---

## 6. Automated Restore Rehearsal (`laravix_test_*`)

End-to-end backup and restore verification is automated by `tools/verify-backup-restore.php` and executed in CI against MySQL 8.4 (`laravix_test_suite` $\rightarrow$ `laravix_test_restore_*`).

To run locally against Docker MySQL 8.4:
```bash
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=33061 \
DB_DATABASE=laravix_test_suite DB_USERNAME=root DB_PASSWORD=laravix84 \
CACHE_STORE=array SESSION_DRIVER=array QUEUE_CONNECTION=sync \
php tools/verify-backup-restore.php
```
