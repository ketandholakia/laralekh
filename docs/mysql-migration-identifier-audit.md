# MySQL migration identifier audit

Scanned every checked-in migration on 2026-11-06 for Laravel-generated foreign-key and index names longer than MySQL's 64-character identifier limit. The names below were over the limit; each now has an explicit short name.

| Migration | Former generated identifier | Length | Explicit identifier |
|---|---|---:|---|
| `2026_11_05_000001_create_accounting_schema.php` | `vouchers_company_id_financial_year_id_reverses_voucher_id_foreign` | 65 | `vouchers_reverses_voucher_fk` |
| `2026_11_06_220000_create_purchase_bills.php` | `purchase_bill_items_company_id_financial_year_id_purchase_bill_id_foreign` | 73 | `purchase_bill_items_parent_fk` |
| `2026_11_06_230000_create_supplier_payments.php` | `supplier_payments_company_id_financial_year_id_series_number_unique` | 67 | `supplier_payments_number_unique` |
| `2026_11_06_230000_create_supplier_payments.php` | `supplier_payment_allocations_company_id_financial_year_id_foreign` | 65 | `supplier_allocations_tenant_year_fk` |
| `2026_11_06_230000_create_supplier_payments.php` | `supplier_payment_allocations_company_id_financial_year_id_payment_id_foreign` | 76 | `supplier_allocations_payment_fk` |
| `2026_11_06_230000_create_supplier_payments.php` | `supplier_payment_allocations_company_id_bill_financial_year_id_purchase_bill_id_foreign` | 87 | `supplier_allocations_bill_fk` |
| `2026_11_06_230000_create_supplier_payments.php` | `supplier_payment_allocation_reversals_company_id_financial_year_id_foreign` | 74 | `supplier_alloc_reversals_year_fk` |
| `2026_11_06_230000_create_supplier_payments.php` | `supplier_payment_allocation_reversals_company_id_allocation_id_foreign` | 70 | `supplier_alloc_reversals_source_fk` |
| `2026_11_06_240000_purchase_guards_and_debit_note_design.php` | `purchase_debit_notes_company_id_financial_year_id_purchase_bill_id_foreign` | 74 | `purchase_debit_notes_bill_fk` |
| `2026_11_06_240000_purchase_guards_and_debit_note_design.php` | `purchase_debit_notes_company_id_financial_year_id_voucher_id_foreign` | 68 | `purchase_debit_notes_voucher_fk` |
| `2026_11_06_240000_purchase_guards_and_debit_note_design.php` | `purchase_debit_notes_company_id_financial_year_id_series_number_unique` | 70 | `purchase_debit_notes_number_unique` |
| `2026_11_06_240000_purchase_guards_and_debit_note_design.php` | `purchase_debit_note_lines_company_id_financial_year_id_purchase_debit_note_id_foreign` | 85 | `purchase_debit_note_lines_parent_fk` |

The 64-character generated unique identifier `purchase_debit_note_lines_purchase_debit_note_id_position_unique` was also shortened to `purchase_debit_lines_position_unique` to avoid a brittle boundary case. A clean MySQL migration run also exposed missing referenced composite unique keys on supplier payments and purchase debit notes; explicit `(company_id, financial_year_id, id)` unique keys were added to satisfy the intended tenant/year foreign keys.
