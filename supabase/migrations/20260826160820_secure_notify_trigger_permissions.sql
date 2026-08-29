revoke execute on function public.notify_new_order() from public, anon, authenticated;
grant execute on function public.notify_new_order() to service_role;

