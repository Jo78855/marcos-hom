create extension if not exists pgcrypto;

create table if not exists public.mh_customers (
  id uuid primary key default gen_random_uuid(),
  name text not null,
  phone text not null,
  whatsapp text,
  area text,
  address text,
  notes text,
  created_at timestamptz not null default now()
);
create unique index if not exists mh_customers_phone_unique on public.mh_customers (phone);

create table if not exists public.mh_catalog (
  id uuid primary key default gen_random_uuid(),
  name text not null,
  category text not null,
  price_without_installation numeric(10,3),
  price_with_installation numeric(10,3),
  active boolean not null default true,
  sort_order integer not null default 0,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table if not exists public.mh_orders (
  id uuid primary key default gen_random_uuid(),
  order_number text not null unique,
  customer_id uuid not null references public.mh_customers(id) on delete restrict,
  service_type text not null,
  catalog_id uuid references public.mh_catalog(id) on delete set null,
  width_m numeric(8,2),
  height_m numeric(8,2),
  color text,
  installation boolean not null default true,
  total numeric(10,3),
  deposit numeric(10,3) not null default 0,
  balance numeric(10,3),
  status text not null default 'new' check (status in ('new','contacted','measurement','quoted','confirmed','preparing','scheduled','completed','collected','cancelled')),
  installation_date timestamptz,
  notes text,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);
create index if not exists mh_orders_status_idx on public.mh_orders(status);
create index if not exists mh_orders_installation_date_idx on public.mh_orders(installation_date);
create index if not exists mh_orders_customer_idx on public.mh_orders(customer_id);

insert into public.mh_catalog (name, category, price_without_installation, price_with_installation, sort_order)
select * from (values
  ('تصميم شاشة 3 إلى 3.5 متر', 'خلفيات شاشة', 130.000, 170.000, 10),
  ('تصميم شاشة 3.5 إلى 4.5 متر', 'خلفيات شاشة', 150.000, 198.000, 20),
  ('تصميم شاشة 4.6 إلى 5.5 متر', 'خلفيات شاشة', 160.000, 210.000, 30),
  ('ركن القهوة', 'ركن القهوة', 35.000, 50.000, 40),
  ('طاولة شاشة 1.5 متر', 'طاولات', 40.000, 50.000, 50),
  ('طاولة شاشة 2 متر', 'طاولات', 50.000, 60.000, 60),
  ('عمود WPC', 'WPC', 5.000, 7.000, 70)
) as seed(name, category, price_without_installation, price_with_installation, sort_order)
where not exists (select 1 from public.mh_catalog);

alter table public.mh_customers enable row level security;
alter table public.mh_orders enable row level security;
alter table public.mh_catalog enable row level security;

create policy "mh_authenticated_customers" on public.mh_customers for all to authenticated using (true) with check (true);
create policy "mh_authenticated_orders" on public.mh_orders for all to authenticated using (true) with check (true);
create policy "mh_authenticated_catalog" on public.mh_catalog for all to authenticated using (true) with check (true);
