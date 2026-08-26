create extension if not exists pgcrypto;

create schema if not exists private;
revoke all on schema private from public, anon, authenticated;

create table if not exists public.mh_staff_profiles (
  user_id uuid primary key references auth.users(id) on delete cascade,
  full_name text not null,
  role text not null check (role in ('admin', 'employee')),
  active boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

insert into public.mh_staff_profiles (user_id, full_name, role)
select id, 'Joseph Sobhy', 'admin'
from auth.users
where lower(email) = 'joseph.sobhy2022@gmail.com'
on conflict (user_id) do update set
  full_name = excluded.full_name,
  role = 'admin',
  active = true,
  updated_at = now();

create or replace function private.mh_has_role(allowed_roles text[])
returns boolean
language sql
stable
security definer
set search_path = public, pg_temp
as $$
  select exists (
    select 1
    from public.mh_staff_profiles profile
    where profile.user_id = (select auth.uid())
      and profile.active = true
      and profile.role = any(allowed_roles)
  );
$$;
revoke all on function private.mh_has_role(text[]) from public;
grant usage on schema private to authenticated;
grant execute on function private.mh_has_role(text[]) to authenticated;

create table if not exists public.mh_customers (
  id uuid primary key default gen_random_uuid(),
  name text not null,
  phone text not null,
  whatsapp text,
  area text,
  address text,
  notes text,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);
create unique index if not exists mh_customers_phone_unique
  on public.mh_customers (regexp_replace(phone, '[^0-9]', '', 'g'));

create table if not exists public.mh_catalog (
  id uuid primary key default gen_random_uuid(),
  code text not null unique,
  name text not null,
  category text not null,
  description text,
  image_url text,
  price_without_installation numeric(10,3),
  price_with_installation numeric(10,3),
  active boolean not null default true,
  sort_order integer not null default 0,
  source text not null default 'marcos_home',
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table if not exists public.mh_orders (
  id uuid primary key default gen_random_uuid(),
  order_number text not null unique,
  customer_id uuid not null references public.mh_customers(id) on delete restrict,
  source text not null default 'admin',
  service_type text not null,
  width_m numeric(8,2),
  height_m numeric(8,2),
  color text,
  installation boolean not null default true,
  total numeric(10,3),
  paid_amount numeric(10,3) not null default 0 check (paid_amount >= 0),
  balance numeric(10,3) generated always as (
    case when total is null then null else greatest(total - paid_amount, 0) end
  ) stored,
  payment_status text not null default 'unpaid'
    check (payment_status in ('unpaid', 'partial', 'paid')),
  status text not null default 'draft' check (status in (
    'draft', 'awaiting_customer', 'confirmed', 'scheduled',
    'technician_assigned', 'en_route', 'arrived', 'in_progress',
    'blocked', 'technician_done', 'awaiting_customer_handover',
    'completed', 'cancelled'
  )),
  installation_date timestamptz,
  internal_notes text,
  customer_notes text,
  created_by uuid references auth.users(id) on delete set null default auth.uid(),
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);
create index if not exists mh_orders_status_idx on public.mh_orders(status);
create index if not exists mh_orders_installation_date_idx on public.mh_orders(installation_date);
create index if not exists mh_orders_customer_idx on public.mh_orders(customer_id);

create table if not exists public.mh_order_items (
  id uuid primary key default gen_random_uuid(),
  order_id uuid not null references public.mh_orders(id) on delete cascade,
  catalog_id uuid references public.mh_catalog(id) on delete set null,
  name text not null,
  description text,
  quantity numeric(10,2) not null default 1 check (quantity > 0),
  unit_price numeric(10,3),
  line_total numeric(10,3),
  sort_order integer not null default 0,
  created_at timestamptz not null default now()
);
create index if not exists mh_order_items_order_idx on public.mh_order_items(order_id);

create table if not exists public.mh_technicians (
  id uuid primary key default gen_random_uuid(),
  name text not null,
  phone text not null,
  whatsapp text,
  active boolean not null default true,
  notes text,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table if not exists public.mh_assignments (
  id uuid primary key default gen_random_uuid(),
  order_id uuid not null references public.mh_orders(id) on delete cascade,
  technician_id uuid not null references public.mh_technicians(id) on delete restrict,
  scheduled_at timestamptz,
  assigned_at timestamptz not null default now(),
  completed_at timestamptz,
  notes text,
  unique (order_id, technician_id)
);
create index if not exists mh_assignments_technician_idx on public.mh_assignments(technician_id);

create table if not exists public.mh_order_access_links (
  id uuid primary key default gen_random_uuid(),
  order_id uuid not null references public.mh_orders(id) on delete cascade,
  audience text not null check (audience in ('customer', 'technician')),
  technician_id uuid references public.mh_technicians(id) on delete cascade,
  token_hash bytea not null unique,
  expires_at timestamptz,
  revoked_at timestamptz,
  created_by uuid references auth.users(id) on delete set null default auth.uid(),
  created_at timestamptz not null default now(),
  last_opened_at timestamptz,
  check ((audience = 'customer' and technician_id is null) or
         (audience = 'technician' and technician_id is not null))
);
create index if not exists mh_order_access_links_order_idx on public.mh_order_access_links(order_id);

create table if not exists public.mh_status_events (
  id bigint generated by default as identity primary key,
  order_id uuid not null references public.mh_orders(id) on delete cascade,
  status text not null,
  actor_type text not null check (actor_type in ('admin', 'employee', 'technician', 'customer', 'system')),
  actor_id text,
  note text,
  metadata jsonb not null default '{}'::jsonb,
  created_at timestamptz not null default now()
);
create index if not exists mh_status_events_order_created_idx
  on public.mh_status_events(order_id, created_at desc);

create table if not exists public.mh_job_photos (
  id uuid primary key default gen_random_uuid(),
  order_id uuid not null references public.mh_orders(id) on delete cascade,
  assignment_id uuid references public.mh_assignments(id) on delete set null,
  stage text not null check (stage in ('before', 'during', 'after', 'issue')),
  storage_path text not null,
  caption text,
  uploaded_by_type text not null check (uploaded_by_type in ('admin', 'employee', 'technician')),
  created_at timestamptz not null default now()
);
create index if not exists mh_job_photos_order_idx on public.mh_job_photos(order_id);

create table if not exists public.mh_customer_confirmations (
  id uuid primary key default gen_random_uuid(),
  order_id uuid not null references public.mh_orders(id) on delete cascade,
  confirmation_type text not null check (confirmation_type in ('details', 'handover')),
  accepted boolean not null,
  rating smallint check (rating between 1 and 5),
  comment text,
  created_at timestamptz not null default now(),
  unique (order_id, confirmation_type)
);

create or replace function private.mh_set_updated_at()
returns trigger
language plpgsql
security definer
set search_path = public, pg_temp
as $$
begin
  new.updated_at = now();
  return new;
end;
$$;
revoke all on function private.mh_set_updated_at() from public;

drop trigger if exists mh_staff_profiles_updated_at on public.mh_staff_profiles;
create trigger mh_staff_profiles_updated_at before update on public.mh_staff_profiles
for each row execute function private.mh_set_updated_at();
drop trigger if exists mh_customers_updated_at on public.mh_customers;
create trigger mh_customers_updated_at before update on public.mh_customers
for each row execute function private.mh_set_updated_at();
drop trigger if exists mh_catalog_updated_at on public.mh_catalog;
create trigger mh_catalog_updated_at before update on public.mh_catalog
for each row execute function private.mh_set_updated_at();
drop trigger if exists mh_orders_updated_at on public.mh_orders;
create trigger mh_orders_updated_at before update on public.mh_orders
for each row execute function private.mh_set_updated_at();
drop trigger if exists mh_technicians_updated_at on public.mh_technicians;
create trigger mh_technicians_updated_at before update on public.mh_technicians
for each row execute function private.mh_set_updated_at();

alter table public.mh_staff_profiles enable row level security;
alter table public.mh_customers enable row level security;
alter table public.mh_catalog enable row level security;
alter table public.mh_orders enable row level security;
alter table public.mh_order_items enable row level security;
alter table public.mh_technicians enable row level security;
alter table public.mh_assignments enable row level security;
alter table public.mh_order_access_links enable row level security;
alter table public.mh_status_events enable row level security;
alter table public.mh_job_photos enable row level security;
alter table public.mh_customer_confirmations enable row level security;

create policy "mh staff read own profile" on public.mh_staff_profiles
for select to authenticated using (user_id = (select auth.uid()) or private.mh_has_role(array['admin']));
create policy "mh admin manage staff" on public.mh_staff_profiles
for all to authenticated using (private.mh_has_role(array['admin']))
with check (private.mh_has_role(array['admin']));

create policy "mh staff read customers" on public.mh_customers
for select to authenticated using (private.mh_has_role(array['admin','employee']));
create policy "mh staff insert customers" on public.mh_customers
for insert to authenticated with check (private.mh_has_role(array['admin','employee']));
create policy "mh staff update customers" on public.mh_customers
for update to authenticated using (private.mh_has_role(array['admin','employee']))
with check (private.mh_has_role(array['admin','employee']));
create policy "mh admin delete customers" on public.mh_customers
for delete to authenticated using (private.mh_has_role(array['admin']));

create policy "mh staff read catalog" on public.mh_catalog
for select to authenticated using (private.mh_has_role(array['admin','employee']));
create policy "mh admin manage catalog" on public.mh_catalog
for all to authenticated using (private.mh_has_role(array['admin']))
with check (private.mh_has_role(array['admin']));

create policy "mh staff read orders" on public.mh_orders
for select to authenticated using (private.mh_has_role(array['admin','employee']));
create policy "mh staff insert orders" on public.mh_orders
for insert to authenticated with check (private.mh_has_role(array['admin','employee']));
create policy "mh staff update orders" on public.mh_orders
for update to authenticated using (private.mh_has_role(array['admin','employee']))
with check (private.mh_has_role(array['admin','employee']));
create policy "mh admin delete orders" on public.mh_orders
for delete to authenticated using (private.mh_has_role(array['admin']));

create policy "mh staff manage order items" on public.mh_order_items
for all to authenticated using (private.mh_has_role(array['admin','employee']))
with check (private.mh_has_role(array['admin','employee']));
create policy "mh staff read technicians" on public.mh_technicians
for select to authenticated using (private.mh_has_role(array['admin','employee']));
create policy "mh admin manage technicians" on public.mh_technicians
for all to authenticated using (private.mh_has_role(array['admin']))
with check (private.mh_has_role(array['admin']));
create policy "mh staff manage assignments" on public.mh_assignments
for all to authenticated using (private.mh_has_role(array['admin','employee']))
with check (private.mh_has_role(array['admin','employee']));
create policy "mh staff manage access links" on public.mh_order_access_links
for all to authenticated using (private.mh_has_role(array['admin','employee']))
with check (private.mh_has_role(array['admin','employee']));
create policy "mh staff manage status events" on public.mh_status_events
for all to authenticated using (private.mh_has_role(array['admin','employee']))
with check (private.mh_has_role(array['admin','employee']));
create policy "mh staff manage job photos" on public.mh_job_photos
for all to authenticated using (private.mh_has_role(array['admin','employee']))
with check (private.mh_has_role(array['admin','employee']));
create policy "mh staff read customer confirmations" on public.mh_customer_confirmations
for select to authenticated using (private.mh_has_role(array['admin','employee']));

insert into public.mh_catalog
  (code, name, category, description, price_without_installation, price_with_installation, sort_order, source)
values
  ('design-198-300-350', 'تصميم شاشة 3 إلى 3.5 متر', 'خلفيات شاشة', 'طاولة 2.5 متر وكبد و3 ألواح فوم بورد', 130, 170, 10, 'design_198'),
  ('design-198-350-450', 'تصميم شاشة 3.5 إلى 4.5 متر', 'خلفيات شاشة', 'طاولة 3 متر وكبد و4 ألواح فوم بورد', 150, 198, 20, 'design_198'),
  ('design-198-460-550', 'تصميم شاشة 4.6 إلى 5.5 متر', 'خلفيات شاشة', 'التسعير حسب العرض المعتمد', 160, 210, 30, 'design_198'),
  ('coffee-corner', 'ركن القهوة', 'ركن القهوة', 'لوح فوم بورد V وكبت بعرض متر', 35, 50, 40, 'coffee_corner'),
  ('tv-console-150', 'طاولة شاشة 1.5 متر', 'طاولات', '4 أبواب، ارتفاع 25 سم وعمق 32 سم', 40, 50, 50, 'marcos_home'),
  ('tv-console-200', 'طاولة شاشة 2 متر', 'طاولات', '4 أبواب، ارتفاع 25 سم وعمق 32 سم', 50, 60, 60, 'marcos_home'),
  ('wpc-column', 'عمود WPC', 'أعمدة WPC', 'ارتفاع 2.90 متر ومقطع 5×10 سم', 5, 7, 70, 'marcos_home'),
  ('fire-040', 'جهاز الفاير 40 سم', 'جهاز الفاير', null, 85, 85, 80, 'fire'),
  ('fire-070', 'جهاز الفاير 70 سم', 'جهاز الفاير', null, 135, 135, 90, 'fire'),
  ('fire-100', 'جهاز الفاير 1 متر', 'جهاز الفاير', null, 180, 180, 100, 'fire'),
  ('fire-120', 'جهاز الفاير 1.20 متر', 'جهاز الفاير', null, 220, 220, 110, 'fire'),
  ('fire-150', 'جهاز الفاير 1.50 متر', 'جهاز الفاير', null, 270, 270, 120, 'fire')
on conflict (code) do update set
  name = excluded.name,
  category = excluded.category,
  description = excluded.description,
  price_without_installation = excluded.price_without_installation,
  price_with_installation = excluded.price_with_installation,
  sort_order = excluded.sort_order,
  source = excluded.source,
  updated_at = now();

comment on table public.mh_order_access_links is
  'Stores only SHA-256 token hashes. Raw customer and technician tokens are never persisted.';
