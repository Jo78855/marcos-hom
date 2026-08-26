create or replace function public.mh_open_order_link(raw_token text)
returns jsonb
language plpgsql
security definer
set search_path = ''
as $$
declare
  access_record public.mh_order_access_links%rowtype;
  result jsonb;
begin
  if raw_token is null or raw_token !~ '^[0-9a-f]{64}$' then return null; end if;
  select * into access_record from public.mh_order_access_links
  where token_hash = extensions.digest(raw_token, 'sha256') and revoked_at is null
    and (expires_at is null or expires_at > now()) limit 1;
  if access_record.id is null then return null; end if;
  update public.mh_order_access_links set last_opened_at = now() where id = access_record.id;

  select jsonb_build_object(
    'audience', access_record.audience,
    'order', case when access_record.audience = 'customer' then
      jsonb_build_object('order_number',o.order_number,'service_type',o.service_type,'width_m',o.width_m,'height_m',o.height_m,'color',o.color,'installation',o.installation,'installation_date',o.installation_date,'status',o.status,'customer_notes',o.customer_notes,'total',o.total,'paid_amount',o.paid_amount,'balance',o.balance)
    else jsonb_build_object('order_number',o.order_number,'service_type',o.service_type,'width_m',o.width_m,'height_m',o.height_m,'color',o.color,'installation',o.installation,'installation_date',o.installation_date,'status',o.status,'customer_notes',o.customer_notes) end,
    'customer', case when access_record.audience = 'customer' then jsonb_build_object('name',c.name)
      else jsonb_build_object('name',c.name,'phone',c.phone,'area',c.area,'address',c.address) end,
    'technician', case when t.id is null then null else jsonb_build_object('name',t.name) end,
    'items', coalesce((select jsonb_agg(jsonb_build_object('name',i.name,'description',i.description,'quantity',i.quantity) order by i.sort_order) from public.mh_order_items i where i.order_id=o.id),'[]'::jsonb),
    'events', coalesce((select jsonb_agg(jsonb_build_object('status',e.status,'note',e.note,'created_at',e.created_at) order by e.created_at desc) from (select * from public.mh_status_events where order_id=o.id order by created_at desc limit 20) e),'[]'::jsonb)
  ) into result
  from public.mh_orders o join public.mh_customers c on c.id=o.customer_id
  left join public.mh_technicians t on t.id=access_record.technician_id where o.id=access_record.order_id;
  return result;
end;
$$;

create or replace function public.mh_technician_update_status(raw_token text, next_status text, status_note text default null)
returns boolean language plpgsql security definer set search_path='' as $$
declare access_record public.mh_order_access_links%rowtype;
begin
  if raw_token is null or raw_token !~ '^[0-9a-f]{64}$' then raise exception 'invalid link'; end if;
  if status_note is not null and length(status_note) > 1000 then raise exception 'note too long'; end if;
  if next_status not in ('en_route','arrived','in_progress','blocked','technician_done') then raise exception 'invalid status'; end if;
  select * into access_record from public.mh_order_access_links where token_hash=extensions.digest(raw_token,'sha256') and audience='technician' and revoked_at is null and (expires_at is null or expires_at>now()) limit 1;
  if access_record.id is null then raise exception 'invalid link'; end if;
  if not exists(select 1 from public.mh_assignments where order_id=access_record.order_id and technician_id=access_record.technician_id) then raise exception 'assignment not found'; end if;
  update public.mh_orders set status=next_status where id=access_record.order_id;
  insert into public.mh_status_events(order_id,status,actor_type,actor_id,note) values(access_record.order_id,next_status,'technician',access_record.technician_id::text,status_note);
  if next_status='technician_done' then update public.mh_assignments set completed_at=now() where order_id=access_record.order_id and technician_id=access_record.technician_id; end if;
  return true;
end; $$;

create or replace function public.mh_customer_confirm_order(raw_token text, confirmation_kind text, customer_rating integer default null, customer_comment text default null)
returns boolean language plpgsql security definer set search_path='' as $$
declare access_record public.mh_order_access_links%rowtype; new_status text;
begin
  if raw_token is null or raw_token !~ '^[0-9a-f]{64}$' then raise exception 'invalid link'; end if;
  if customer_comment is not null and length(customer_comment) > 1000 then raise exception 'comment too long'; end if;
  if confirmation_kind not in ('details','handover') then raise exception 'invalid confirmation'; end if;
  if customer_rating is not null and (customer_rating<1 or customer_rating>5) then raise exception 'invalid rating'; end if;
  select * into access_record from public.mh_order_access_links where token_hash=extensions.digest(raw_token,'sha256') and audience='customer' and revoked_at is null and (expires_at is null or expires_at>now()) limit 1;
  if access_record.id is null then raise exception 'invalid link'; end if;
  insert into public.mh_customer_confirmations(order_id,confirmation_type,accepted,rating,comment) values(access_record.order_id,confirmation_kind,true,customer_rating,customer_comment)
  on conflict(order_id,confirmation_type) do update set accepted=true,rating=excluded.rating,comment=excluded.comment,created_at=now();
  new_status := case when confirmation_kind='handover' then 'completed' else 'confirmed' end;
  update public.mh_orders set status=new_status, customer_notes=coalesce(customer_comment,customer_notes) where id=access_record.order_id;
  insert into public.mh_status_events(order_id,status,actor_type,note) values(access_record.order_id,new_status,'customer',customer_comment);
  return true;
end; $$;

revoke all on function public.mh_open_order_link(text) from public;
revoke all on function public.mh_technician_update_status(text,text,text) from public;
revoke all on function public.mh_customer_confirm_order(text,text,integer,text) from public;
grant execute on function public.mh_open_order_link(text) to anon, authenticated;
grant execute on function public.mh_technician_update_status(text,text,text) to anon, authenticated;
grant execute on function public.mh_customer_confirm_order(text,text,integer,text) to anon, authenticated;
