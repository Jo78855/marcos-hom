create index if not exists mh_job_photos_assignment_idx
  on public.mh_job_photos(assignment_id);
create index if not exists mh_order_access_links_created_by_idx
  on public.mh_order_access_links(created_by);
create index if not exists mh_order_access_links_technician_idx
  on public.mh_order_access_links(technician_id);
create index if not exists mh_order_items_catalog_idx
  on public.mh_order_items(catalog_id);
create index if not exists mh_orders_created_by_idx
  on public.mh_orders(created_by);

drop policy if exists "mh admin manage staff" on public.mh_staff_profiles;
create policy "mh admin insert staff" on public.mh_staff_profiles
for insert to authenticated with check (private.mh_has_role(array['admin']));
create policy "mh admin update staff" on public.mh_staff_profiles
for update to authenticated using (private.mh_has_role(array['admin']))
with check (private.mh_has_role(array['admin']));
create policy "mh admin delete staff" on public.mh_staff_profiles
for delete to authenticated using (private.mh_has_role(array['admin']));

drop policy if exists "mh admin manage catalog" on public.mh_catalog;
create policy "mh admin insert catalog" on public.mh_catalog
for insert to authenticated with check (private.mh_has_role(array['admin']));
create policy "mh admin update catalog" on public.mh_catalog
for update to authenticated using (private.mh_has_role(array['admin']))
with check (private.mh_has_role(array['admin']));
create policy "mh admin delete catalog" on public.mh_catalog
for delete to authenticated using (private.mh_has_role(array['admin']));

drop policy if exists "mh admin manage technicians" on public.mh_technicians;
create policy "mh admin insert technicians" on public.mh_technicians
for insert to authenticated with check (private.mh_has_role(array['admin']));
create policy "mh admin update technicians" on public.mh_technicians
for update to authenticated using (private.mh_has_role(array['admin']))
with check (private.mh_has_role(array['admin']));
create policy "mh admin delete technicians" on public.mh_technicians
for delete to authenticated using (private.mh_has_role(array['admin']));

