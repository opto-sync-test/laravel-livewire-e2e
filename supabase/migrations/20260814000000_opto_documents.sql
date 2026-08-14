create table if not exists public.opto_documents (
  id text primary key,
  payload jsonb not null,
  updated_at bigint not null,
  client_id text not null
);

alter table public.opto_documents enable row level security;

grant select, insert, update, delete on public.opto_documents to anon, authenticated;

create policy "fixture clients may read documents"
on public.opto_documents for select
to anon, authenticated
using (true);

create policy "fixture clients may insert documents"
on public.opto_documents for insert
to anon, authenticated
with check (client_id <> '');

create policy "fixture clients may update documents"
on public.opto_documents for update
to anon, authenticated
using (true)
with check (client_id <> '');

create policy "fixture clients may delete documents"
on public.opto_documents for delete
to anon, authenticated
using (true);

do $$
begin
  if not exists (
    select 1
    from pg_publication_tables
    where pubname = 'supabase_realtime'
      and schemaname = 'public'
      and tablename = 'opto_documents'
  ) then
    alter publication supabase_realtime add table public.opto_documents;
  end if;
end
$$;

