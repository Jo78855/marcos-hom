import React, { FormEvent, useEffect, useMemo, useState } from 'react';
import type { Session } from '@supabase/supabase-js';
import { supabase } from '../supabase';
import './DecorAdmin.css';

type Tab = 'dashboard' | 'orders' | 'customers' | 'installations' | 'catalog';

type Customer = {
  id: string;
  name: string;
  phone: string;
  whatsapp: string | null;
  area: string | null;
  address: string | null;
  notes: string | null;
  created_at: string;
};

type DecorOrder = {
  id: string;
  order_number: string;
  customer_id: string;
  service_type: string;
  width_m: number | null;
  height_m: number | null;
  color: string | null;
  installation: boolean;
  total: number | null;
  paid_amount: number;
  payment_status: string;
  balance: number | null;
  status: string;
  installation_date: string | null;
  internal_notes: string | null;
  customer_notes: string | null;
  created_at: string;
  customer?: Customer | null;
};

type CatalogItem = {
  id: string;
  name: string;
  category: string;
  price_without_installation: number | null;
  price_with_installation: number | null;
  active: boolean;
};

const STATUS_LABELS: Record<string, string> = {
  draft: 'مسودة',
  awaiting_customer: 'بانتظار تأكيد العميل',
  confirmed: 'مؤكد',
  scheduled: 'مجدول',
  technician_assigned: 'تم تعيين الفني',
  en_route: 'في الطريق',
  arrived: 'وصل',
  in_progress: 'قيد التنفيذ',
  blocked: 'توجد مشكلة',
  technician_done: 'منتهي فنيًا',
  awaiting_customer_handover: 'بانتظار استلام العميل',
  completed: 'مستلم ومغلق',
  cancelled: 'ملغي',
};

const SERVICES = [
  'تصميم خلفية شاشة',
  'ركن قهوة',
  'بانوهات',
  'خلفية سرير',
  'طاولة شاشة',
  'أعمدة WPC',
  'باركيه',
  'طلب خاص',
];

export default function DecorAdmin() {
  const [session, setSession] = useState<Session | null>(null);
  const [authLoading, setAuthLoading] = useState(true);

  useEffect(() => {
    supabase.auth.getSession().then(({ data }) => {
      setSession(data.session);
      setAuthLoading(false);
    });
    const { data } = supabase.auth.onAuthStateChange((_event, nextSession) => setSession(nextSession));
    return () => data.subscription.unsubscribe();
  }, []);

  if (authLoading) return <div className="mh-center" dir="rtl">جاري التحميل...</div>;
  return session ? <DecorWorkspace onLogout={() => supabase.auth.signOut()} /> : <DecorLogin />;
}

function DecorLogin() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  const submit = async (event: FormEvent) => {
    event.preventDefault();
    setBusy(true);
    setError('');
    const { error: signInError } = await supabase.auth.signInWithPassword({ email, password });
    if (signInError) setError('بيانات الدخول غير صحيحة');
    setBusy(false);
  };

  return <main className="mh-login" dir="rtl">
    <form onSubmit={submit}>
      <div className="mh-logo"><img src="/marcos-home-logo.jpg" alt="Marco’s Home" /></div>
      <h1>Marco’s Home</h1>
      <p>إدارة الديكورات والطلبات والتركيبات</p>
      <label>البريد الإلكتروني<input type="email" value={email} onChange={e => setEmail(e.target.value)} required /></label>
      <label>كلمة المرور<input type="password" value={password} onChange={e => setPassword(e.target.value)} required /></label>
      {error && <div className="mh-error">{error}</div>}
      <button disabled={busy}>{busy ? 'جاري الدخول...' : 'دخول'}</button>
    </form>
  </main>;
}

function DecorWorkspace({ onLogout }: { onLogout: () => void }) {
  const [tab, setTab] = useState<Tab>('dashboard');
  const [orders, setOrders] = useState<DecorOrder[]>([]);
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [catalog, setCatalog] = useState<CatalogItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [showNewOrder, setShowNewOrder] = useState(false);

  const load = async () => {
    setLoading(true);
    setError('');
    const [ordersResult, customersResult, catalogResult] = await Promise.all([
      supabase.from('mh_orders').select('*, customer:mh_customers(*)').order('created_at', { ascending: false }),
      supabase.from('mh_customers').select('*').order('created_at', { ascending: false }),
      supabase.from('mh_catalog').select('*').order('category').order('name'),
    ]);

    if (ordersResult.error || customersResult.error || catalogResult.error) {
      setError('قاعدة بيانات إدارة الديكورات لم تُجهّز بالكامل بعد.');
    }
    setOrders((ordersResult.data || []) as DecorOrder[]);
    setCustomers((customersResult.data || []) as Customer[]);
    setCatalog((catalogResult.data || []) as CatalogItem[]);
    setLoading(false);
  };

  useEffect(() => { load(); }, []);

  const stats = useMemo(() => ({
    newOrders: orders.filter(o => ['draft', 'awaiting_customer'].includes(o.status)).length,
    scheduled: orders.filter(o => o.status === 'scheduled').length,
    active: orders.filter(o => !['completed', 'cancelled'].includes(o.status)).length,
    receivables: orders.reduce((sum, order) => sum + Number(order.balance || 0), 0),
  }), [orders]);

  const installations = useMemo(() => orders
    .filter(order => order.installation_date)
    .sort((a, b) => Date.parse(a.installation_date || '') - Date.parse(b.installation_date || '')), [orders]);

  const updateStatus = async (orderId: string, status: string) => {
    const { error: updateError } = await supabase.from('mh_orders').update({ status }).eq('id', orderId);
    if (updateError) return setError('تعذر تحديث حالة الطلب.');
    setOrders(current => current.map(order => order.id === orderId ? { ...order, status } : order));
  };

  return <main className="mh-app" dir="rtl">
    <aside className="mh-sidebar">
      <div className="mh-brand"><div className="mh-logo"><img src="/marcos-home-logo.jpg" alt="Marco’s Home" /></div><div><strong>Marco’s Home</strong><small>Decor Operations</small></div></div>
      <nav>
        <button className={tab === 'dashboard' ? 'active' : ''} onClick={() => setTab('dashboard')}>الرئيسية</button>
        <button className={tab === 'orders' ? 'active' : ''} onClick={() => setTab('orders')}>الطلبات</button>
        <button className={tab === 'customers' ? 'active' : ''} onClick={() => setTab('customers')}>العملاء</button>
        <button className={tab === 'installations' ? 'active' : ''} onClick={() => setTab('installations')}>التركيبات</button>
        <button className={tab === 'catalog' ? 'active' : ''} onClick={() => setTab('catalog')}>التصميمات والأسعار</button>
      </nav>
      <button className="mh-logout" onClick={onLogout}>تسجيل الخروج</button>
    </aside>

    <section className="mh-main">
      <header className="mh-header">
        <div><h1>{tabTitle(tab)}</h1><p>مركز تشغيل ماركوز هوم للديكورات</p></div>
        <div className="mh-header-actions"><button onClick={load}>تحديث</button><button className="primary" onClick={() => setShowNewOrder(true)}>+ طلب جديد</button></div>
      </header>

      {error && <div className="mh-banner">{error}</div>}
      {loading ? <div className="mh-loading">جاري تحميل البيانات...</div> : <>
        {tab === 'dashboard' && <Dashboard orders={orders} stats={stats} installations={installations} />}
        {tab === 'orders' && <OrdersView orders={orders} onStatus={updateStatus} />}
        {tab === 'customers' && <CustomersView customers={customers} orders={orders} />}
        {tab === 'installations' && <InstallationsView installations={installations} />}
        {tab === 'catalog' && <CatalogView catalog={catalog} />}
      </>}
    </section>

    {showNewOrder && <NewOrderModal onClose={() => setShowNewOrder(false)} onCreated={() => { setShowNewOrder(false); load(); }} />}
  </main>;
}

function Dashboard({ orders, stats, installations }: { orders: DecorOrder[]; stats: { newOrders: number; scheduled: number; active: number; receivables: number }; installations: DecorOrder[] }) {
  return <div className="mh-grid">
    <section className="mh-stats">
      <article><span>طلبات تحتاج متابعة</span><strong>{stats.newOrders}</strong></article>
      <article><span>طلبات نشطة</span><strong>{stats.active}</strong></article>
      <article><span>تركيبات محددة</span><strong>{stats.scheduled}</strong></article>
      <article><span>مبالغ متبقية</span><strong>{stats.receivables.toFixed(3)} د.ك</strong></article>
    </section>
    <section className="mh-card"><div className="mh-card-head"><h2>أحدث الطلبات</h2></div><OrderRows orders={orders.slice(0, 6)} /></section>
    <section className="mh-card"><div className="mh-card-head"><h2>التركيبات القادمة</h2></div>{installations.length ? installations.slice(0, 5).map(order => <div className="mh-install" key={order.id}><div><strong>{order.customer?.name || 'عميل'}</strong><span>{order.service_type}</span></div><div><b>{order.installation_date ? new Date(order.installation_date).toLocaleDateString('ar-KW') : '-'}</b><span>{order.customer?.area || ''}</span></div></div>) : <p className="mh-empty">لا توجد تركيبات مجدولة.</p>}</section>
  </div>;
}

function OrdersView({ orders, onStatus }: { orders: DecorOrder[]; onStatus: (id: string, status: string) => void }) {
  const [query, setQuery] = useState('');
  const filtered = orders.filter(order => `${order.order_number} ${order.customer?.name || ''} ${order.customer?.phone || ''} ${order.service_type}`.toLowerCase().includes(query.toLowerCase()));
  return <section className="mh-card"><div className="mh-toolbar"><input value={query} onChange={e => setQuery(e.target.value)} placeholder="ابحث برقم الطلب أو العميل أو الهاتف" /></div><div className="mh-orders-table">{filtered.map(order => <div className="mh-order-row" key={order.id}><div><strong>{order.order_number}</strong><span>{order.customer?.name || 'عميل'} · {order.customer?.phone || ''}</span></div><div><strong>{order.service_type}</strong><span>{order.width_m ? `${order.width_m} م` : 'مقاس غير محدد'} {order.color ? `· ${order.color}` : ''}</span></div><div><strong>{order.total === null ? 'حسب الطلب' : `${order.total} د.ك`}</strong><span>{order.installation ? 'مع تركيب' : 'بدون تركيب'}</span></div><select value={order.status} onChange={e => onStatus(order.id, e.target.value)}>{Object.entries(STATUS_LABELS).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></div>)}{!filtered.length && <p className="mh-empty">لا توجد طلبات.</p>}</div></section>;
}

function OrderRows({ orders }: { orders: DecorOrder[] }) {
  return <div className="mh-orders-table">{orders.map(order => <div className="mh-order-row compact" key={order.id}><div><strong>{order.order_number}</strong><span>{order.customer?.name || 'عميل'}</span></div><div><strong>{order.service_type}</strong><span>{STATUS_LABELS[order.status] || order.status}</span></div><div><strong>{order.total === null ? 'حسب الطلب' : `${order.total} د.ك`}</strong><span>{order.customer?.area || ''}</span></div></div>)}{!orders.length && <p className="mh-empty">لا توجد طلبات حتى الآن.</p>}</div>;
}

function CustomersView({ customers, orders }: { customers: Customer[]; orders: DecorOrder[] }) {
  return <section className="mh-card"><div className="mh-customer-grid">{customers.map(customer => {
    const customerOrders = orders.filter(order => order.customer_id === customer.id);
    return <article key={customer.id}><strong>{customer.name}</strong><span>{customer.phone}</span><span>{customer.area || 'بدون منطقة'}</span><b>{customerOrders.length} طلب</b>{customer.whatsapp && <a href={`https://wa.me/${customer.whatsapp.replace(/\D/g, '')}`} target="_blank" rel="noreferrer">فتح واتساب</a>}</article>;
  })}{!customers.length && <p className="mh-empty">لا يوجد عملاء مسجلون.</p>}</div></section>;
}

function InstallationsView({ installations }: { installations: DecorOrder[] }) {
  return <section className="mh-card">{installations.map(order => <div className="mh-install full" key={order.id}><div><strong>{order.installation_date ? new Date(order.installation_date).toLocaleString('ar-KW') : '-'}</strong><span>{order.order_number}</span></div><div><strong>{order.customer?.name || 'عميل'}</strong><span>{order.customer?.phone || ''}</span></div><div><strong>{order.service_type}</strong><span>{order.customer?.address || order.customer?.area || ''}</span></div></div>)}{!installations.length && <p className="mh-empty">لا توجد تركيبات مجدولة.</p>}</section>;
}

function CatalogView({ catalog }: { catalog: CatalogItem[] }) {
  return <section className="mh-card"><div className="mh-catalog-grid">{catalog.map(item => <article key={item.id}><span>{item.category}</span><strong>{item.name}</strong><div><b>{item.price_without_installation ?? '-'} د.ك</b><small>بدون تركيب</small></div><div><b>{item.price_with_installation ?? '-'} د.ك</b><small>مع التركيب</small></div><em>{item.active ? 'نشط' : 'متوقف'}</em></article>)}{!catalog.length && <p className="mh-empty">سيظهر هنا كتالوج الأسعار بعد تجهيز قاعدة البيانات.</p>}</div></section>;
}

function NewOrderModal({ onClose, onCreated }: { onClose: () => void; onCreated: () => void }) {
  const [form, setForm] = useState({ name: '', phone: '', area: '', address: '', service: SERVICES[0], width: '', height: '2.90', color: '', installation: true, total: '', installationDate: '', notes: '' });
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  const submit = async (event: FormEvent) => {
    event.preventDefault();
    setBusy(true);
    setError('');
    let customerId = '';
    const normalizedPhone = form.phone.replace(/\s/g, '');
    const existing = await supabase.from('mh_customers').select('id').eq('phone', normalizedPhone).maybeSingle();
    if (existing.data?.id) customerId = existing.data.id;
    else {
      const created = await supabase.from('mh_customers').insert({ name: form.name.trim(), phone: normalizedPhone, whatsapp: normalizedPhone, area: form.area || null, address: form.address || null }).select('id').single();
      if (created.error || !created.data) { setError('تعذر حفظ العميل.'); setBusy(false); return; }
      customerId = created.data.id;
    }
    const total = form.total ? Number(form.total) : null;
    const orderNumber = `MH-${new Date().toISOString().slice(0,10).replace(/-/g,'')}-${Math.random().toString(36).slice(2,6).toUpperCase()}`;
    const createdOrder = await supabase.from('mh_orders').insert({
      order_number: orderNumber,
      customer_id: customerId,
      service_type: form.service,
      width_m: form.width ? Number(form.width) : null,
      height_m: form.height ? Number(form.height) : null,
      color: form.color || null,
      installation: form.installation,
      total,
      paid_amount: 0,
      payment_status: 'unpaid',
      status: form.installationDate ? 'scheduled' : 'draft',
      installation_date: form.installationDate ? new Date(form.installationDate).toISOString() : null,
      internal_notes: form.notes || null,
    });
    if (createdOrder.error) { setError('تعذر حفظ الطلب.'); setBusy(false); return; }
    onCreated();
  };

  return <div className="mh-modal-backdrop" dir="rtl"><form className="mh-modal" onSubmit={submit}><div className="mh-modal-head"><div><h2>طلب ديكور جديد</h2><p>سجل الطلب مرة واحدة وسيظهر في المتابعة والتركيبات.</p></div><button type="button" onClick={onClose}>×</button></div><div className="mh-form-grid"><label>اسم العميل<input required value={form.name} onChange={e => setForm({ ...form, name: e.target.value })} /></label><label>الهاتف / واتساب<input required value={form.phone} onChange={e => setForm({ ...form, phone: e.target.value })} /></label><label>المنطقة<input value={form.area} onChange={e => setForm({ ...form, area: e.target.value })} /></label><label>العنوان<input value={form.address} onChange={e => setForm({ ...form, address: e.target.value })} /></label><label>نوع الخدمة<select value={form.service} onChange={e => setForm({ ...form, service: e.target.value })}>{SERVICES.map(service => <option key={service}>{service}</option>)}</select></label><label>عرض الحائط بالمتر<input type="number" step="0.01" value={form.width} onChange={e => setForm({ ...form, width: e.target.value })} /></label><label>الارتفاع بالمتر<input type="number" step="0.01" value={form.height} onChange={e => setForm({ ...form, height: e.target.value })} /></label><label>اللون<input value={form.color} onChange={e => setForm({ ...form, color: e.target.value })} /></label><label>السعر الإجمالي<input type="number" step="0.001" value={form.total} onChange={e => setForm({ ...form, total: e.target.value })} /></label><label>موعد التركيب<input type="datetime-local" value={form.installationDate} onChange={e => setForm({ ...form, installationDate: e.target.value })} /></label><label className="mh-checkbox"><input type="checkbox" checked={form.installation} onChange={e => setForm({ ...form, installation: e.target.checked })} /> شامل التركيب</label><label className="wide">ملاحظات<textarea value={form.notes} onChange={e => setForm({ ...form, notes: e.target.value })} /></label></div>{error && <div className="mh-error">{error}</div>}<div className="mh-modal-actions"><button type="button" onClick={onClose}>إلغاء</button><button className="primary" disabled={busy}>{busy ? 'جاري الحفظ...' : 'حفظ الطلب'}</button></div></form></div>;
}

function tabTitle(tab: Tab) {
  return ({ dashboard: 'الرئيسية', orders: 'الطلبات', customers: 'العملاء', installations: 'التركيبات', catalog: 'التصميمات والأسعار' } as Record<Tab, string>)[tab];
}
