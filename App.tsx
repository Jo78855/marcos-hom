import React, { FormEvent, useEffect, useMemo, useState } from 'react';
import type { Session } from '@supabase/supabase-js';
import { ArrowRight, CalendarDays, ChevronLeft, ClipboardList, Copy, LayoutDashboard, LogOut, MessageCircleMore, PackageSearch, Plus, Search, Users } from 'lucide-react';
import { supabase } from './supabase';

type View = 'home' | 'new-order' | 'orders' | 'customers' | 'schedule' | 'catalog' | 'assistant';
type Customer = { id: string; name: string; phone: string; area: string | null };
type Order = { id: string; order_number: string; source: string; service_type: string; width_m: number | null; height_m: number | null; color: string | null; installation: boolean; total: number | null; paid_amount: number; payment_status: 'unpaid' | 'paid' | 'partial'; status: string; installation_date: string | null; customer_notes: string | null; created_at: string; customer: Customer };
type CatalogItem = { id: string; name: string; category: string; price_without_installation: number | null; price_with_installation: number | null; active: boolean };

const navItems: { id: View; label: string; icon: React.ComponentType<{ size?: number }> }[] = [
  { id: 'home', label: 'الرئيسية', icon: LayoutDashboard }, { id: 'orders', label: 'الطلبات', icon: ClipboardList },
  { id: 'customers', label: 'العملاء', icon: Users }, { id: 'schedule', label: 'المواعيد', icon: CalendarDays },
  { id: 'catalog', label: 'المنتجات والأسعار', icon: PackageSearch }, { id: 'assistant', label: 'مساعد ماركو', icon: MessageCircleMore },
];

export default function App() {
  const [session, setSession] = useState<Session | null>(null);
  const [authLoading, setAuthLoading] = useState(true);
  useEffect(() => {
    supabase.auth.getSession().then(({ data }) => { setSession(data.session); setAuthLoading(false); });
    const { data } = supabase.auth.onAuthStateChange((_event, nextSession) => setSession(nextSession));
    return () => data.subscription.unsubscribe();
  }, []);
  if (authLoading) return <div className="boot" dir="rtl">جاري تشغيل لوحة Marco’s Home…</div>;
  if (!session) return <Login />;
  return <Dashboard onLogout={() => supabase.auth.signOut()} />;
}

function Login() {
  const [email, setEmail] = useState(''), [password, setPassword] = useState(''), [busy, setBusy] = useState(false), [error, setError] = useState('');
  const submit = async (event: FormEvent) => { event.preventDefault(); setBusy(true); setError(''); const { error: signInError } = await supabase.auth.signInWithPassword({ email, password }); if (signInError) setError('بيانات الدخول غير صحيحة'); setBusy(false); };
  return <main className="login-page" dir="rtl"><form onSubmit={submit}><div className="brand-mark login-mark">MH</div><h1>Marco’s Home</h1><p>لوحة الإدارة</p><label>البريد الإلكتروني<input type="email" required value={email} onChange={e => setEmail(e.target.value)} /></label><label>كلمة المرور<input type="password" required value={password} onChange={e => setPassword(e.target.value)} /></label>{error && <div className="auth-error">{error}</div>}<button className="primary" disabled={busy}>{busy ? 'جاري الدخول…' : 'دخول'}</button></form></main>;
}

function Dashboard({ onLogout }: { onLogout: () => void }) {
  const [view, setView] = useState<View>('home'), [orders, setOrders] = useState<Order[]>([]), [customers, setCustomers] = useState<Customer[]>([]), [catalog, setCatalog] = useState<CatalogItem[]>([]), [loading, setLoading] = useState(true), [error, setError] = useState('');
  const load = async () => {
    setLoading(true); setError('');
    const [ordersResult, customersResult, catalogResult] = await Promise.all([
      supabase.from('mh_orders').select('*, customer:mh_customers!mh_orders_customer_id_fkey(id,name,phone,area)').order('created_at', { ascending: false }),
      supabase.from('mh_customers').select('id,name,phone,area').order('updated_at', { ascending: false }),
      supabase.from('mh_catalog').select('id,name,category,price_without_installation,price_with_installation,active').eq('active', true).order('sort_order'),
    ]);
    if (ordersResult.error || customersResult.error || catalogResult.error) setError('تعذر تحميل بيانات لوحة الإدارة. تأكد من صلاحية حساب الإدارة.');
    setOrders((ordersResult.data || []) as unknown as Order[]); setCustomers((customersResult.data || []) as Customer[]); setCatalog((catalogResult.data || []) as CatalogItem[]); setLoading(false);
  };
  useEffect(() => { void load(); const channel = supabase.channel('marcos-home-admin-orders').on('postgres_changes', { event: '*', schema: 'public', table: 'mh_orders' }, () => void load()).subscribe(); return () => { void supabase.removeChannel(channel); }; }, []);
  const title = useMemo(() => navItems.find(item => item.id === view)?.label ?? 'طلب جديد', [view]);
  return <div className="app" dir="rtl"><aside className="sidebar"><button className="brand" onClick={() => setView('home')}><span className="brand-mark">MH</span><span><strong>Marco’s Home</strong><small>إدارة الديكورات</small></span></button><nav>{navItems.map(item => { const Icon = item.icon; return <button key={item.id} className={view === item.id ? 'active' : ''} onClick={() => setView(item.id)}><Icon size={20} /><span>{item.label}</span></button>; })}</nav><button className="new-order-side" onClick={() => setView('new-order')}><Plus size={19} /> طلب جديد</button></aside><main className="main"><header className="topbar"><div><small>Marco’s Home</small><h1>{view === 'new-order' ? 'طلب جديد' : title}</h1></div><div className="top-actions"><button className="primary" onClick={() => setView('new-order')}><Plus size={18} /> طلب جديد</button><button className="logout" onClick={onLogout}><LogOut size={18} /></button></div></header>{error && <div className="auth-error">{error}</div>}{view === 'home' && <Home onOpen={setView} orders={orders} />}{view === 'new-order' && <NewOrder onCancel={() => setView('home')} onCreated={async () => { await load(); setView('orders'); }} />}{view === 'orders' && <Orders orders={orders} loading={loading} onChanged={load} />}{view === 'customers' && <Customers customers={customers} orders={orders} />}{view === 'schedule' && <Schedule orders={orders} />}{view === 'catalog' && <Catalog items={catalog} />}{view === 'assistant' && <AssistantSummary orders={orders} />}</main></div>;
}

function Home({ onOpen, orders }: { onOpen: (view: View) => void; orders: Order[] }) {
  const today = new Date().toISOString().slice(0, 10), todayInstallations = orders.filter(order => order.installation_date?.slice(0, 10) === today).length, followUp = orders.filter(order => !['completed', 'cancelled'].includes(order.status)).length;
  const actions = [['new-order', 'تسجيل طلب', 'إضافة طلب داخل القاعدة الأساسية', Plus], ['orders', 'الطلبات', `${orders.length} طلب مسجل`, ClipboardList], ['customers', 'العملاء', 'بيانات العميل وتاريخه', Users], ['schedule', 'المواعيد', 'المعاينات والتركيبات', CalendarDays], ['catalog', 'الأسعار والمنتجات', 'المصدر الموحد للبيانات', PackageSearch], ['assistant', 'مساعد ماركو', 'طلبات الموقع والمساعد', MessageCircleMore]] as const;
  return <section className="page"><div className="welcome"><div><span>متصل بالموقع وقاعدة البيانات</span><h2>كل شغلك من شاشة واحدة</h2><p>طلبات الموقع والمساعد والطلبات اليدوية تظهر في نفس المكان.</p></div><button onClick={() => onOpen('new-order')}>ابدأ طلب جديد <ArrowRight size={18} /></button></div><div className="quick-grid">{actions.map(([id, title, text, Icon]) => <button key={id} onClick={() => onOpen(id)}><span className="icon"><Icon size={24} /></span><div><strong>{title}</strong><small>{text}</small></div><ChevronLeft size={18} /></button>)}</div><div className="status-strip"><div><small>كل الطلبات</small><strong>{orders.length}</strong></div><div><small>تركيبات اليوم</small><strong>{todayInstallations}</strong></div><div><small>طلبات الموقع والمساعد</small><strong>{orders.filter(order => order.source === 'voice_assistant' || order.source === 'website').length}</strong></div><div><small>تحتاج متابعة</small><strong>{followUp}</strong></div></div></section>;
}

function parseMeasurements(value: string) { const values = value.replace(/,/g, '.').match(/\d+(?:\.\d+)?/g)?.map(Number) || []; return { width_m: values[0] || null, height_m: values[1] || null }; }
function makeOrderNumber() { const date = new Date().toISOString().slice(0, 10).replace(/-/g, ''); return `MH-${date}-${crypto.randomUUID().replace(/-/g, '').slice(0, 6).toUpperCase()}`; }
function normalizePhone(value: string) { return value.replace(/\D/g, ''); }

function NewOrder({ onCancel, onCreated }: { onCancel: () => void; onCreated: () => void }) {
  const [form, setForm] = useState({ customer_name: '', customer_phone: '', area: '', service_type: 'خلفية شاشة', measurement: '', color: 'العسلي', installation: true, total: '', notes: '' }), [busy, setBusy] = useState(false), [error, setError] = useState('');
  const set = (key: string, value: string | boolean) => setForm(current => ({ ...current, [key]: value }));
  const submit = async (event: FormEvent) => {
    event.preventDefault(); setBusy(true); setError(''); const phone = normalizePhone(form.customer_phone);
    if (phone.length < 8) { setError('راجع رقم الهاتف.'); setBusy(false); return; }
    const { data: existing, error: customerReadError } = await supabase.from('mh_customers').select('id').eq('phone', phone).limit(1).maybeSingle();
    if (customerReadError) { setError('تعذر البحث عن العميل.'); setBusy(false); return; }
    let customerId = existing?.id as string | undefined;
    if (customerId) { const { error: updateError } = await supabase.from('mh_customers').update({ name: form.customer_name.trim(), area: form.area.trim() || null, whatsapp: phone }).eq('id', customerId); if (updateError) { setError('تعذر تحديث بيانات العميل.'); setBusy(false); return; } }
    else { const { data: created, error: createError } = await supabase.from('mh_customers').insert({ name: form.customer_name.trim(), phone, whatsapp: phone, area: form.area.trim() || null }).select('id').single(); if (createError || !created) { setError('تعذر إنشاء ملف العميل.'); setBusy(false); return; } customerId = created.id; }
    const measurements = parseMeasurements(form.measurement), total = form.total.trim() === '' ? null : Math.max(0, Number(form.total));
    const { error: orderError } = await supabase.from('mh_orders').insert({ order_number: makeOrderNumber(), customer_id: customerId, source: 'admin', service_type: form.service_type, ...measurements, color: form.color.trim() || null, installation: form.installation, total, paid_amount: 0, payment_status: 'unpaid', status: 'draft', customer_notes: form.notes.trim() || null });
    if (orderError) { setError('تعذر تسجيل الطلب.'); setBusy(false); return; } setBusy(false); onCreated();
  };
  return <section className="page"><div className="section-head"><h2>تسجيل طلب جديد</h2><p>يُحفظ في نفس قاعدة بيانات طلبات الموقع والمساعد.</p></div><form className="order-form" onSubmit={submit}><div className="form-grid"><label>اسم العميل<input required value={form.customer_name} onChange={e => set('customer_name', e.target.value)} /></label><label>رقم الهاتف<input required inputMode="tel" value={form.customer_phone} onChange={e => set('customer_phone', e.target.value)} /></label><label>المنطقة<input value={form.area} onChange={e => set('area', e.target.value)} /></label><label>نوع الخدمة<select value={form.service_type} onChange={e => set('service_type', e.target.value)}><option>خلفية شاشة</option><option>ركن قهوة</option><option>فاير معطر</option><option>طاولة شاشة</option><option>أعمدة WPC</option><option>بانوهات</option><option>طلب خاص</option></select></label><label>المقاس<input value={form.measurement} onChange={e => set('measurement', e.target.value)} placeholder="مثال: 4 × 2.9 متر" /></label><label>اللون<input value={form.color} onChange={e => set('color', e.target.value)} /></label><label>السعر الكامل<input inputMode="decimal" value={form.total} onChange={e => set('total', e.target.value)} placeholder="د.ك" /></label></div><div className="install-choice"><span>طريقة التنفيذ</span><button type="button" className={form.installation ? 'selected' : ''} onClick={() => set('installation', true)}>مع التركيب</button><button type="button" className={!form.installation ? 'selected' : ''} onClick={() => set('installation', false)}>بدون تركيب</button></div><label className="notes-label">ملاحظات<textarea value={form.notes} onChange={e => set('notes', e.target.value)} /></label>{error && <div className="auth-error">{error}</div>}<div className="form-actions"><button type="button" className="ghost" onClick={onCancel}>إلغاء</button><button className="primary" disabled={busy}>{busy ? 'جاري التسجيل…' : 'تأكيد وتسجيل الطلب'}</button></div></form></section>;
}

async function createCustomerLink(orderId: string) {
  const bytes = crypto.getRandomValues(new Uint8Array(32)), rawToken = Array.from(bytes, byte => byte.toString(16).padStart(2, '0')).join(''), digest = new Uint8Array(await crypto.subtle.digest('SHA-256', new TextEncoder().encode(rawToken))), tokenHash = `\\x${Array.from(digest, byte => byte.toString(16).padStart(2, '0')).join('')}`;
  const { error } = await supabase.from('mh_order_access_links').insert({ order_id: orderId, audience: 'customer', token_hash: tokenHash }); if (error) throw error; return `${window.location.origin}/order/customer/${rawToken}`;
}

function Orders({ orders, loading, onChanged }: { orders: Order[]; loading: boolean; onChanged: () => Promise<void> }) {
  const [query, setQuery] = useState(''), [updating, setUpdating] = useState(''), [notice, setNotice] = useState('');
  const filtered = orders.filter(order => `${order.order_number} ${order.customer?.name || ''} ${order.customer?.phone || ''} ${order.service_type}`.toLowerCase().includes(query.toLowerCase()));
  const updateOrder = async (order: Order, values: Record<string, unknown>) => { setUpdating(order.id); const { error } = await supabase.from('mh_orders').update(values).eq('id', order.id); setUpdating(''); if (error) { setNotice('تعذر تحديث الطلب.'); return; } await onChanged(); };
  const copyCustomerLink = async (order: Order) => { setUpdating(order.id); setNotice(''); try { const link = await createCustomerLink(order.id); await navigator.clipboard.writeText(link); setNotice(`تم نسخ رابط العميل للطلب ${order.order_number}.`); } catch { setNotice('تعذر إنشاء رابط العميل.'); } setUpdating(''); };
  return <section className="page"><div className="toolbar"><div className="search"><Search size={18} /><input value={query} onChange={e => setQuery(e.target.value)} placeholder="ابحث برقم الطلب أو العميل" /></div></div>{notice && <div className="public-note">{notice}</div>}<div className="table-card">{loading ? <div className="empty-inline">جاري التحميل…</div> : filtered.length === 0 ? <div className="empty-inline">لا توجد طلبات مطابقة.</div> : filtered.map(order => <div className="order-row order-with-links" key={order.id}><div><small>{order.order_number}</small><strong>{order.customer?.name || 'عميل'}</strong><span>{order.customer?.phone || '—'}</span></div><div><small>الخدمة</small><strong>{order.service_type}</strong><span>{formatMeasurement(order)}</span></div><div><small>المنطقة</small><strong>{order.customer?.area || '—'}</strong><span>{order.color || '—'}</span></div><div><select value={order.status} disabled={updating === order.id} onChange={e => void updateOrder(order, { status: e.target.value })}>{statusOptions.map(status => <option key={status} value={status}>{statusLabel(status)}</option>)}</select><small>{order.installation ? 'مع التركيب' : 'بدون تركيب'}</small></div><div><strong>{order.total == null ? 'السعر غير محدد' : `${order.total} د.ك`}</strong><button disabled={updating === order.id} onClick={() => void updateOrder(order, order.payment_status === 'paid' ? { payment_status: 'unpaid', paid_amount: 0 } : { payment_status: 'paid', paid_amount: order.total || 0 })}>{order.payment_status === 'paid' ? 'مدفوع بالكامل' : 'غير مدفوع'}</button></div><div className="order-links"><button disabled={updating === order.id} onClick={() => void copyCustomerLink(order)}><Copy size={15} /> رابط العميل</button></div></div>)}</div></section>;
}

function Customers({ customers, orders }: { customers: Customer[]; orders: Order[] }) { const counts = new Map<string, number>(); orders.forEach(order => counts.set(order.customer?.id, (counts.get(order.customer?.id) || 0) + 1)); return <section className="page"><div className="table-card">{customers.length === 0 ? <div className="empty-inline">لا يوجد عملاء حتى الآن.</div> : customers.map(customer => <div className="customer-row" key={customer.id}><strong>{customer.name}</strong><span>{customer.phone}</span><span>{customer.area || '—'}</span><b>{counts.get(customer.id) || 0} طلب</b></div>)}</div></section>; }
function Schedule({ orders }: { orders: Order[] }) { const scheduled = orders.filter(order => order.installation_date).sort((a, b) => Date.parse(a.installation_date || '') - Date.parse(b.installation_date || '')); return <section className="page"><div className="table-card">{scheduled.length === 0 ? <div className="empty-inline">لا توجد مواعيد تركيب مسجلة.</div> : scheduled.map(order => <div className="customer-row" key={order.id}><strong>{order.order_number}</strong><span>{order.customer?.name}</span><span>{new Date(order.installation_date || '').toLocaleString('ar-KW')}</span><b>{order.service_type}</b></div>)}</div></section>; }
function Catalog({ items }: { items: CatalogItem[] }) { return <section className="page"><div className="catalog-grid">{items.map(item => <article key={item.id}><strong>{item.name}</strong><small>{item.category}</small><span>{item.price_without_installation ?? '—'} بدون تركيب / {item.price_with_installation ?? '—'} مع التركيب</span></article>)}</div></section>; }
function AssistantSummary({ orders }: { orders: Order[] }) { const assistantOrders = orders.filter(order => order.source === 'voice_assistant'); return <section className="page"><div className="empty-card"><h2>طلبات مساعد ماركو</h2><p>{assistantOrders.length} طلب مؤكد من مساعد الموقع موجود الآن داخل نفس قائمة الطلبات.</p></div></section>; }
const statusOptions = ['draft', 'awaiting_customer', 'confirmed', 'scheduled', 'in_progress', 'awaiting_customer_handover', 'completed', 'cancelled'];
function statusLabel(status: string) { return ({ draft: 'مسودة', awaiting_customer: 'بانتظار تأكيد العميل', confirmed: 'مؤكد', scheduled: 'محدد للتركيب', in_progress: 'قيد التنفيذ', awaiting_customer_handover: 'بانتظار استلام العميل', completed: 'مكتمل', cancelled: 'ملغي' } as Record<string, string>)[status] || status; }
function formatMeasurement(order: Order) { if (order.width_m == null) return 'بدون مقاس'; return order.height_m == null ? `${order.width_m} م` : `${order.width_m} × ${order.height_m} م`; }
