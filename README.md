# Laravel Livewire + local Supabase OptoSync E2E

This fixture proves the PHP path end to end with Laravel 13.25, Livewire 4.4,
the official pinned OptoSync PHP client, browser background execution, Laravel
queue workers, and a real local Supabase stack.

The test boundaries are intentionally concrete:

- the Livewire component validates an edit, renders it optimistically, and
  dispatches the exact mutation to a module-aware service worker;
- the service worker commits the mutation to IndexedDB before acknowledging it,
  freezes one ordered body, hashes it, and sends it concurrently to upload and
  realtime endpoints;
- Background Sync, Periodic Background Sync, explicit application wake, and
  online wake all retry without deleting a partially delivered batch;
- Laravel queue jobs serialize the same `ImmutableSyncBatch` onto independent
  `opto-sync-upload` and `opto-sync-realtime` queues, with bounded backoff and
  lane-specific uniqueness;
- the Laravel endpoint verifies the SHA-256 header against the raw body before
  accepting mutation identities;
- Supabase starts locally in CI, applies the migration, performs an anonymous
  PostgREST insert/read/delete through real row-level-security policies, and
  verifies that the table belongs to the `supabase_realtime` publication.

## Run the PHP and browser contracts

```sh
git submodule update --init --recursive
composer install
composer test
node --check public/service-worker.js
```

## Run the Supabase contract locally

Install Docker and the Supabase CLI, then:

```sh
supabase start
supabase status -o env
```

Export the reported `API_URL`, `ANON_KEY`, and `DB_URL`, then run:

```sh
php scripts/supabase_contract.php
```

The integration uses only the deterministic credentials emitted by the local
Supabase CLI. It does not require or accept a hosted project key. The CI job
stops the local stack even when a contract fails.

