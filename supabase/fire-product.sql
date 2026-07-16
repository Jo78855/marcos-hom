create table if not exists public.fire_settings (
  id integer primary key check (id = 1),
  whatsapp_number text not null default '96550204320',
  updated_at timestamptz not null default now()
);

insert into public.fire_settings (id, whatsapp_number)
values (1, '96550204320')
on conflict (id) do nothing;

create table if not exists public.fire_sizes (
  id uuid primary key default gen_random_uuid(),
  name_ar text not null,
  length_cm integer not null,
  price numeric(10,3),
  active boolean not null default true,
  sort_order integer not null default 0
);

insert into public.fire_sizes (name_ar, length_cm, price, active, sort_order)
select * from (values
  ('40 سم', 40, 85::numeric, true, 1),
  ('70 سم', 70, 135::numeric, true, 2),
  ('1 متر', 100, 180::numeric, true, 3),
  ('1.20 متر', 120, 220::numeric, true, 4),
  ('1.50 متر', 150, 270::numeric, true, 5)
) as initial(name_ar, length_cm, price, active, sort_order)
where not exists (select 1 from public.fire_sizes);

create table if not exists public.fire_orders (
  id uuid primary key default gen_random_uuid(),
  customer_name text,
  customer_phone text,
  size_id uuid references public.fire_sizes(id) on delete set null,
  size_name text not null,
  total numeric(10,3),
  status text not null default 'new',
  created_at timestamptz not null default now()
);

alter table public.fire_settings enable row level security;
alter table public.fire_sizes enable row level security;
alter table public.fire_orders enable row level security;

grant select on public.fire_settings, public.fire_sizes to anon, authenticated;
grant update on public.fire_settings, public.fire_sizes to authenticated;
grant insert on public.fire_orders to anon, authenticated;
grant select, update on public.fire_orders to authenticated;

drop policy if exists "public read fire settings" on public.fire_settings;
create policy "public read fire settings" on public.fire_settings for select to anon, authenticated using (true);
drop policy if exists "admin update fire settings" on public.fire_settings;
create policy "admin update fire settings" on public.fire_settings for update to authenticated using (true) with check (true);

drop policy if exists "public read fire sizes" on public.fire_sizes;
create policy "public read fire sizes" on public.fire_sizes for select to anon, authenticated using (true);
drop policy if exists "admin update fire sizes" on public.fire_sizes;
create policy "admin update fire sizes" on public.fire_sizes for update to authenticated using (true) with check (true);

drop policy if exists "public create fire orders" on public.fire_orders;
create policy "public create fire orders" on public.fire_orders for insert to anon, authenticated with check (true);
drop policy if exists "admin read fire orders" on public.fire_orders;
create policy "admin read fire orders" on public.fire_orders for select to authenticated using (true);
drop policy if exists "admin update fire orders" on public.fire_orders;
create policy "admin update fire orders" on public.fire_orders for update to authenticated using (true) with check (true);
