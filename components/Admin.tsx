import React, { FormEvent, useEffect, useState } from 'react';
import type { Session } from '@supabase/supabase-js';
import { supabase } from '../supabase';

type Settings = { price_without_installation: number; price_with_installation: number; whatsapp_number: string };
type Design = { id: string; name_ar: string; image_url: string; active: boolean; sort_order: number };
type Order = { id: string; total: number; installation: boolean; status: string; created_at: string; designs: { name_ar: string } | null };

export default function Admin() {
  const [session, setSession] = useState<Session | null>(null);
  const [loading, setLoading] = useState(true);
  useEffect(() => {
    supabase.auth.getSession().then(({ data }) => { setSession(data.session); setLoading(false); });
    const { data } = supabase.auth.onAuthStateChange((_event, next) => setSession(next));
    return () => data.subscription.unsubscribe();
  }, []);
  if (loading) return <div className="admin-center" dir="rtl">جاري التحميل...</div>;
  return session ? <Dashboard onLogout={() => supabase.auth.signOut()} /> : <Login />;
}

function Login() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const submit = async (event: FormEvent) => {
    event.preventDefault(); setBusy(true); setError('');
    const { error } = await supabase.auth.signInWithPassword({ email, password });
    if (error) setError('بيانات الدخول غير صحيحة');
    setBusy(false);
  };
  return <main className="admin-login" dir="rtl"><form onSubmit={submit}><div className="brand-mark">MH</div><h1>لوحة تحكم ماركوز هوم</h1><p>أدخل بيانات حساب المدير</p><label>البريد الإلكتروني<input type="email" value={email} onChange={e => setEmail(e.target.value)} required/></label><label>كلمة المرور<input type="password" value={password} onChange={e => setPassword(e.target.value)} required/></label>{error && <div className="admin-error">{error}</div>}<button disabled={busy}>{busy ? 'جاري الدخول...' : 'دخول'}</button><a href="/">العودة للموقع</a></form></main>;
}

function Dashboard({ onLogout }: { onLogout: () => void }) {
  const [settings, setSettings] = useState<Settings | null>(null);
  const [designs, setDesigns] = useState<Design[]>([]);
  const [orders, setOrders] = useState<Order[]>([]);
  const [notice, setNotice] = useState('');
  const load = async () => {
    const [s, d, o] = await Promise.all([
      supabase.from('store_settings').select('*').eq('id', 1).single(),
      supabase.from('designs').select('*').order('sort_order'),
      supabase.from('orders').select('*,designs(name_ar)').order('created_at', { ascending: false }),
    ]);
    if (s.data) setSettings(s.data as Settings);
    if (d.data) setDesigns(d.data as Design[]);
    if (o.data) setOrders(o.data as unknown as Order[]);
  };
  useEffect(() => { load(); }, []);
  const saveSettings = async () => {
    if (!settings) return;
    const { error } = await supabase.from('store_settings').update({ ...settings, updated_at: new Date().toISOString() }).eq('id', 1);
    setNotice(error ? 'تعذر الحفظ' : 'تم حفظ الأسعار بنجاح');
  };
  const updateDesign = async (item: Design, patch: Partial<Design>) => {
    const next = { ...item, ...patch };
    setDesigns(current => current.map(d => d.id === item.id ? next : d));
    await supabase.from('designs').update(patch).eq('id', item.id);
  };
  const updateOrder = async (id: string, status: string) => {
    await supabase.from('orders').update({ status }).eq('id', id);
    setOrders(current => current.map(order => order.id === id ? { ...order, status } : order));
  };
  return <main className="admin-shell" dir="rtl">
    <header className="admin-header"><div><strong>لوحة تحكم ماركوز هوم</strong><small>إدارة ركن القهوة</small></div><div><a href="/" target="_blank">عرض الموقع</a><button onClick={onLogout}>تسجيل الخروج</button></div></header>
    <div className="admin-content">
      <section className="admin-card"><h2>الأسعار وواتساب</h2>{settings && <div className="admin-fields"><label>بدون تركيب<input type="number" value={settings.price_without_installation} onChange={e => setSettings({...settings, price_without_installation: Number(e.target.value)})}/></label><label>شامل التركيب<input type="number" value={settings.price_with_installation} onChange={e => setSettings({...settings, price_with_installation: Number(e.target.value)})}/></label><label>رقم واتساب<input value={settings.whatsapp_number} onChange={e => setSettings({...settings, whatsapp_number: e.target.value})}/></label><button onClick={saveSettings}>حفظ التعديلات</button></div>}{notice && <p className="admin-success">{notice}</p>}</section>
      <section className="admin-card"><h2>التصميمات</h2><div className="admin-designs">{designs.map(item => <div key={item.id}><img src={item.image_url}/><input value={item.name_ar} onChange={e => updateDesign(item, { name_ar: e.target.value })}/><label className="switch"><input type="checkbox" checked={item.active} onChange={e => updateDesign(item, { active: e.target.checked })}/><span>{item.active ? 'ظاهر' : 'مخفي'}</span></label></div>)}</div></section>
      <section className="admin-card"><div className="card-title"><h2>الطلبات ({orders.length})</h2><button onClick={load}>تحديث</button></div>{orders.length === 0 ? <p className="empty">لا توجد طلبات حتى الآن.</p> : <div className="orders-table">{orders.map(order => <div key={order.id}><span>{new Date(order.created_at).toLocaleString('ar-KW')}</span><strong>{order.designs?.name_ar || 'تصميم'}</strong><span>{order.installation ? 'شامل التركيب' : 'بدون تركيب'}</span><b>{order.total} د.ك</b><select value={order.status} onChange={e => updateOrder(order.id, e.target.value)}><option value="new">جديد</option><option value="contacted">تم التواصل</option><option value="confirmed">مؤكد</option><option value="completed">مكتمل</option><option value="cancelled">ملغي</option></select></div>)}</div>}</section>
    </div>
  </main>;
}
