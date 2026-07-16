import React, { FormEvent, useEffect, useMemo, useState } from 'react';
import type { Session } from '@supabase/supabase-js';
import { supabase } from '../supabase';

type Product = 'coffee' | 'fire';
type UnifiedOrder = {
  id: string;
  product: Product;
  customerName: string;
  customerPhone: string;
  selection: string;
  total: number | null;
  status: string;
  createdAt: string;
};

const productLabel: Record<Product, string> = { coffee: 'ركن القهوة', fire: 'الفير المعطر' };
const statusLabel: Record<string, string> = {
  new: 'جديد',
  contacted: 'تم التواصل',
  confirmed: 'مؤكد',
  completed: 'مكتمل',
  cancelled: 'ملغي',
};

export default function UnifiedAdmin() {
  const [session, setSession] = useState<Session | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    supabase.auth.getSession().then(({ data }) => { setSession(data.session); setLoading(false); });
    const { data } = supabase.auth.onAuthStateChange((_event, nextSession) => setSession(nextSession));
    return () => data.subscription.unsubscribe();
  }, []);

  if (loading) return <div className="admin-center" dir="rtl">جاري التحميل...</div>;
  return session
    ? <UnifiedDashboard onLogout={() => supabase.auth.signOut()} />
    : <UnifiedLogin />;
}

function UnifiedLogin() {
  const [email, setEmail] = useState('joseph.sobhy2022@gmail.com');
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

  return <main className="admin-login" dir="rtl"><form onSubmit={submit}>
    <div className="brand-mark unified-mark">MH</div>
    <h1>لوحة ماركوز هوم الموحدة</h1>
    <p>طلبات ركن القهوة والفير في مكان واحد</p>
    <label>البريد الإلكتروني<input type="email" value={email} onChange={event => setEmail(event.target.value)} required /></label>
    <label>كلمة المرور<input type="password" value={password} onChange={event => setPassword(event.target.value)} required /></label>
    {error && <div className="admin-error">{error}</div>}
    <button disabled={busy}>{busy ? 'جاري الدخول...' : 'دخول'}</button>
    <a href="/admin">العودة للوحة المنتج</a>
  </form></main>;
}

function UnifiedDashboard({ onLogout }: { onLogout: () => void }) {
  const [orders, setOrders] = useState<UnifiedOrder[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [lastUpdated, setLastUpdated] = useState('');
  const [query, setQuery] = useState('');
  const [productFilter, setProductFilter] = useState<'all' | Product>('all');

  const load = async () => {
    setLoading(true);
    setError('');
    const [coffeeResult, designsResult, fireResult] = await Promise.all([
      supabase.from('orders').select('*').order('created_at', { ascending: false }),
      supabase.from('designs').select('id,name_ar'),
      supabase.from('fire_orders').select('*').order('created_at', { ascending: false }),
    ]);

    if (coffeeResult.error || fireResult.error) {
      setError('تعذر تحميل بعض الطلبات. حاول التحديث مرة أخرى.');
    }

    const designNames = new Map((designsResult.data || []).map(item => [item.id, item.name_ar]));
    const coffeeOrders: UnifiedOrder[] = (coffeeResult.data || []).map(order => ({
      id: order.id,
      product: 'coffee',
      customerName: order.customer_name || 'عميل',
      customerPhone: order.customer_phone || 'بدون رقم',
      selection: designNames.get(order.design_id) || (order.installation ? 'شامل التركيب' : 'بدون تركيب'),
      total: order.total,
      status: order.status,
      createdAt: order.created_at,
    }));
    const fireOrders: UnifiedOrder[] = (fireResult.data || []).map(order => ({
      id: order.id,
      product: 'fire',
      customerName: order.customer_name || 'عميل',
      customerPhone: order.customer_phone || 'بدون رقم',
      selection: order.size_name || 'مقاس غير محدد',
      total: order.total,
      status: order.status,
      createdAt: order.created_at,
    }));

    setOrders([...coffeeOrders, ...fireOrders].sort((a, b) => Date.parse(b.createdAt) - Date.parse(a.createdAt)));
    setLastUpdated(new Date().toLocaleTimeString('ar-KW'));
    setLoading(false);
  };

  useEffect(() => {
    load();
    const timer = window.setInterval(load, 15000);
    window.addEventListener('focus', load);
    return () => { window.clearInterval(timer); window.removeEventListener('focus', load); };
  }, []);

  const customers = useMemo(() => {
    const byPhone = new Map<string, { name: string; phone: string; orders: number; total: number; products: Set<Product>; lastOrder: string }>();
    orders.forEach(order => {
      const phone = order.customerPhone.replace(/\D/g, '') || order.customerPhone;
      const current = byPhone.get(phone);
      if (current) {
        current.orders += 1;
        current.total += order.total || 0;
        current.products.add(order.product);
        if (Date.parse(order.createdAt) > Date.parse(current.lastOrder)) current.lastOrder = order.createdAt;
      } else {
        byPhone.set(phone, { name: order.customerName, phone: order.customerPhone, orders: 1, total: order.total || 0, products: new Set([order.product]), lastOrder: order.createdAt });
      }
    });
    return [...byPhone.values()].sort((a, b) => Date.parse(b.lastOrder) - Date.parse(a.lastOrder));
  }, [orders]);

  const filteredOrders = useMemo(() => {
    const normalizedQuery = query.trim().toLowerCase();
    return orders.filter(order => {
      const matchesProduct = productFilter === 'all' || order.product === productFilter;
      const matchesQuery = !normalizedQuery || `${order.customerName} ${order.customerPhone} ${order.selection}`.toLowerCase().includes(normalizedQuery);
      return matchesProduct && matchesQuery;
    });
  }, [orders, productFilter, query]);

  const coffeeCount = orders.filter(order => order.product === 'coffee').length;
  const fireCount = orders.filter(order => order.product === 'fire').length;
  const newCount = orders.filter(order => order.status === 'new').length;

  return <main className="admin-shell unified-admin" dir="rtl">
    <header className="admin-header unified-header">
      <div><strong>لوحة ماركوز هوم الموحدة</strong><small>نظرة واحدة على كل الطلبات والعملاء</small></div>
      <div><a href="https://coffee.marcohom.com/admin">إدارة القهوة</a><a href="https://fire.marcohom.com/admin">إدارة الفير</a><button onClick={onLogout}>تسجيل الخروج</button></div>
    </header>
    <div className="admin-content">
      <section className="unified-stats">
        <article><small>كل الطلبات</small><strong>{orders.length}</strong></article>
        <article><small>طلبات جديدة</small><strong>{newCount}</strong></article>
        <article><small>ركن القهوة</small><strong>{coffeeCount}</strong></article>
        <article><small>الفير المعطر</small><strong>{fireCount}</strong></article>
        <article><small>عملاء مختلفون</small><strong>{customers.length}</strong></article>
      </section>

      <section className="admin-card">
        <div className="card-title unified-title"><div><h2>كل الطلبات</h2>{lastUpdated && <small>آخر تحديث: {lastUpdated} — تلقائي كل 15 ثانية</small>}</div><button onClick={load} disabled={loading}>{loading ? 'جاري التحديث...' : 'تحديث الآن'}</button></div>
        <div className="unified-filters">
          <input value={query} onChange={event => setQuery(event.target.value)} placeholder="ابحث بالاسم أو الهاتف أو المقاس" />
          <select value={productFilter} onChange={event => setProductFilter(event.target.value as 'all' | Product)}><option value="all">كل المنتجات</option><option value="coffee">ركن القهوة</option><option value="fire">الفير المعطر</option></select>
        </div>
        {error && <p className="admin-error">{error}</p>}
        <div className="unified-orders">
          {filteredOrders.map(order => <div key={`${order.product}-${order.id}`}>
            <span className={`product-pill ${order.product}`}>{productLabel[order.product]}</span>
            <strong>{order.customerName}<small>{order.customerPhone}</small></strong>
            <span>{order.selection}</span>
            <b>{order.total === null ? 'حسب الطلب' : `${order.total} د.ك`}</b>
            <span>{statusLabel[order.status] || order.status}</span>
            <time>{new Date(order.createdAt).toLocaleString('ar-KW')}</time>
          </div>)}
          {!loading && filteredOrders.length === 0 && <p className="empty">لا توجد نتائج مطابقة.</p>}
        </div>
      </section>

      <section className="admin-card">
        <h2>قاعدة العملاء الحالية</h2>
        <p className="form-hint">يتم تجميع العميل تلقائيًا حسب رقم الهاتف من طلبات القهوة والفير.</p>
        <div className="unified-customers">
          {customers.map(customer => <div key={customer.phone}>
            <strong>{customer.name}<small>{customer.phone}</small></strong>
            <span>{customer.orders} طلب</span>
            <span>{[...customer.products].map(product => productLabel[product]).join('، ')}</span>
            <b>{customer.total} د.ك</b>
            <time>{new Date(customer.lastOrder).toLocaleDateString('ar-KW')}</time>
          </div>)}
        </div>
      </section>
    </div>
  </main>;
}
