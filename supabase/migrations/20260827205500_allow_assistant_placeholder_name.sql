create or replace function public.mh_create_assistant_order(
  customer_name text,
  customer_phone text,
  customer_area text,
  wall_width numeric,
  wall_height numeric,
  wants_installation boolean,
  quoted_total numeric,
  requested_service text,
  customer_note text,
  photo_path text,
  confirmed_by_customer boolean
)
returns jsonb
language plpgsql
security definer
set search_path = ''
as $$
declare
  normalized_phone text := regexp_replace(coalesce(customer_phone, ''), '[^0-9]', '', 'g');
  clean_name text := btrim(coalesce(customer_name, ''));
  clean_area text := btrim(coalesce(customer_area, ''));
  clean_service text := btrim(coalesce(requested_service, ''));
  clean_note text := nullif(btrim(coalesce(customer_note, '')), '');
  clean_photo text := nullif(btrim(coalesce(photo_path, '')), '');
  customer_record_id uuid;
  order_record_id uuid;
  generated_order_number text;
  existing_order record;
begin
  if confirmed_by_customer is distinct from true then
    raise exception 'customer confirmation is required';
  end if;
  if length(clean_name) < 2 then clean_name := 'عميل المساعد'; end if;
  if length(clean_name) > 120 then raise exception 'invalid customer name'; end if;
  if length(normalized_phone) < 8 or length(normalized_phone) > 15 then raise exception 'invalid customer phone'; end if;
  if length(clean_area) < 2 or length(clean_area) > 120 then raise exception 'invalid customer area'; end if;
  if length(clean_service) < 2 or length(clean_service) > 160 then raise exception 'invalid requested service'; end if;
  if wall_width is null or wall_width < 0.1 or wall_width > 100 then raise exception 'invalid wall width'; end if;
  if wall_height is not null and (wall_height < 0.1 or wall_height > 100) then raise exception 'invalid wall height'; end if;
  if quoted_total is not null and (quoted_total < 0 or quoted_total > 100000) then raise exception 'invalid quoted total'; end if;
  if clean_note is not null and length(clean_note) > 1500 then raise exception 'customer note too long'; end if;
  if clean_photo is not null and (length(clean_photo) > 500 or clean_photo !~ '^[A-Za-z0-9._/-]+$') then raise exception 'invalid photo path'; end if;

  perform pg_catalog.pg_advisory_xact_lock(pg_catalog.hashtext(normalized_phone));

  select o.id, o.order_number into existing_order
  from public.mh_orders o
  join public.mh_customers c on c.id = o.customer_id
  where regexp_replace(c.phone, '[^0-9]', '', 'g') = normalized_phone
    and o.source = 'voice_assistant'
    and o.service_type = clean_service
    and o.created_at > now() - interval '2 minutes'
  order by o.created_at desc
  limit 1;
  if existing_order.id is not null then
    return jsonb_build_object('ok', true, 'duplicate', true, 'order_id', existing_order.id, 'order_number', existing_order.order_number);
  end if;

  if (select count(*) from public.mh_orders o join public.mh_customers c on c.id = o.customer_id
      where regexp_replace(c.phone, '[^0-9]', '', 'g') = normalized_phone
        and o.source = 'voice_assistant' and o.created_at > now() - interval '1 hour') >= 5 then
    raise exception 'too many recent orders';
  end if;

  select id into customer_record_id from public.mh_customers
  where regexp_replace(phone, '[^0-9]', '', 'g') = normalized_phone limit 1;
  if customer_record_id is null then
    insert into public.mh_customers(name, phone, whatsapp, area)
    values(clean_name, normalized_phone, normalized_phone, clean_area)
    returning id into customer_record_id;
  else
    update public.mh_customers
    set name = clean_name, area = clean_area,
        whatsapp = coalesce(nullif(whatsapp, ''), normalized_phone)
    where id = customer_record_id;
  end if;

  generated_order_number := 'MH-' || to_char(now(), 'YYYYMMDD') || '-A' || upper(substr(replace(extensions.gen_random_uuid()::text, '-', ''), 1, 5));
  insert into public.mh_orders(
    order_number, customer_id, source, service_type, width_m, height_m,
    installation, total, paid_amount, payment_status, status,
    customer_notes, internal_notes, created_by
  ) values (
    generated_order_number, customer_record_id, 'voice_assistant', clean_service,
    wall_width, wall_height, coalesce(wants_installation, true), quoted_total,
    0, 'unpaid', 'confirmed', clean_note,
    concat('طلب مؤكد عبر مساعد ماركوز', case when clean_photo is null then '' else ' | صورة المكان: ' || clean_photo end),
    null
  ) returning id into order_record_id;

  insert into public.mh_order_items(order_id, name, description, quantity, unit_price, line_total)
  values(
    order_record_id,
    clean_service,
    concat('المقاس: عرض ', wall_width, 'م', case when wall_height is null then '' else ' × ارتفاع ' || wall_height || 'م' end,
      case when clean_note is null then '' else ' | ' || clean_note end),
    1, quoted_total, quoted_total
  );
  insert into public.mh_status_events(order_id, status, actor_type, note)
  values(order_record_id, 'confirmed', 'system', 'أكد العميل الطلب صراحة عبر المساعد قبل التسجيل');

  return jsonb_build_object('ok', true, 'duplicate', false, 'order_id', order_record_id, 'order_number', generated_order_number);
end;
$$;

revoke all on function public.mh_create_assistant_order(text,text,text,numeric,numeric,boolean,numeric,text,text,text,boolean) from public;
revoke all on function public.mh_create_assistant_order(text,text,text,numeric,numeric,boolean,numeric,text,text,text,boolean) from anon, authenticated;
grant execute on function public.mh_create_assistant_order(text,text,text,numeric,numeric,boolean,numeric,text,text,text,boolean) to anon, authenticated;
