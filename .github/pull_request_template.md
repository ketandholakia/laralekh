## Summary

<!-- What does this pull request change? -->

## Reason for change

<!-- Why is this change needed? Link the issue or describe the problem. -->

## Testing performed

<!-- How was this verified? e.g. `vendor/bin/pest`, `npm run build`, manual steps. -->

- [ ] Full Pest suite passes (`vendor/bin/pest`)
- [ ] Frontend build passes (`npm run build`) — required for Blade/Tailwind changes

## Database / schema impact

<!-- State "none" if there is no schema, migration or data change. -->

- [ ] No new or modified migrations
- [ ] Tests still run on isolated SQLite in-memory (`laravix_test_` guard untouched)

## Security impact

<!-- State "none" if not applicable. Otherwise describe authorization, tenancy or data-handling effects. -->

- [ ] No weakening of tenant isolation or company-scoped permissions
- [ ] No new dependency on untrusted input

## Screenshots

<!-- Required for UI changes. Attach before/after images. -->

## Checklist

- [ ] No secrets, credentials, `.env` files or local artifacts are added
- [ ] No dependency upgrades
- [ ] Conversation threads resolved before requesting merge
