import React, { FormEvent, useEffect, useState } from 'react';
import type { Session } from '@supabase/supabase-js';
import { supabase } from '../supabase';

type FireSettings = { id: number; whatsapp_number: string };
type FireSize = { id: string; name_ar: string; length_cm: number; price: number | null; active: boolean; sort_order: number };
type FireOrder = { id: string; customer_name: string | null; customer_phone: string | null; size_name: string; total: number | null; status: string; created_at: string };

export default function FireAdmin() {
  const [session, setSession] = useState<Session | null>(null);
  const [loading, setLoading] = useState(true);
  const [recoveringPassword, setRecoveringPassword] = useState(() => window.location.hash.includes('type=recovery'));
  const [authMessage, setAuthMessage] = useState('');
  useEffect(() => {
    supabase.auth.getSession().then(({ data }) => { setSession(data.session); setLoading(false); });
    const { data } = supabase.auth.onAuthStateChange((event, next) => {
      setSession(next);
      if (event === 'PASSWORD_RECOVERY') setRecoveringPassword(true);
    });
    return () => data.subscription.unsubscribe();
  }, []);
  if (loading) return <div className="admin-center" dir="rtl">جاري التحميل...</div>;
  if (recoveringPassword) return <ResetPassword onComplete={() => {
    window.history.replaceState({}, document.title, '/admin');
    setRecoveringPassword(false);
    setAuthMessage('تم تعيين كلمة المرور الجديدة. يمكنك تسجيل الدخول الآن.');
  }} />;
  return session ? <FireDashboard onLogout={() => supabase.auth.signOut()} /> : <Login initialMessage={authMessage} />;
}

function Login({ initialMessage = '' }: { initialMessage?: string }) {
  const [email, setEmail] = useState('joseph.sobhy2022@gmail.com');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [message, setMessage] = useState(initialMessage);
  const [busy, setBusy] = useState(false);
  const submit = async (event: FormEvent) => {
    event.preventDefault(); setBusy(true); setError(''); setMessage('');
    const { error } = await supabase.auth.signInWithPassword({ email, password });
    if (error) setError('بيانات الدخول غير صحيحة');
    setBusy(false);
  };
  const sendRecoveryEmail = async () => {
    setBusy(true); setError(''); setMessage('');
    const { error } = await supabase.auth.resetPasswordForEmail(email.trim(), { redirectTo: `${window.location.origin}/admin` });
    if (error) setError('تعذر إرسال رابط الاستعادة. حاول مرة أخرى بعد قليل.');
    else setMessage('تم إرسال رابط استعادة كلمة المرور إلى بريدك الإلكتروني.');
    setBusy(false);
  };
  return <main className="admin-login" dir="rtl"><form onSubmit={submit}><div className="brand-mark fire-mark">MH</div><h1>لوحة تحكم الفير المعطر</h1><p>أدخل بيانات حساب المدير</p><label>البريد الإلكتروني<input type="email" value={email} onChange={e => setEmail(e.target.value)} required/></label><label>كلمة المرور<input type="password" value={password} onChange={e => setPassword(e.target.value)} required/></label>{error && <div className="admin-error">{error}</div>}{message && <div className="admin-success-box">{message}</div>}<button disabled={busy}>{busy ? 'جاري التنفيذ...' : 'دخول'}</button><button className="forgot-password" type="button" onClick={sendRecoveryEmail} disabled={busy}>نسيت كلمة المرور؟</button><a href="/">العودة للتطبيق</a></form></main>;
}

function ResetPassword({ onComplete }: { onComplete: () => void }) {
  const [password, setPassword] = useState('');
  const [confirmation, setConfirmation] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const submit = async (event: FormEvent) => {
    event.preventDefault(); setError('');
    if (password.length < 8) return setError('كلمة المرور يجب أن تكون 8 أحرف على الأقل');
    if (password !== confirmation) return setError('كلمتا المرور غير متطابقتين');
    setBusy(true);
    const { error } = await supabase.auth.updateUser({ password });
    if (error) { setError('تعذر تعيين كلمة المرور. اطلب رابطًا جديدًا.'); setBusy(false); return; }
    await supabase.auth.signOut();
    onComplete();
  };
  return <main className="admin-login" dir="rtl"><form onSubmit={submit}><div className="brand-mark fire-mark">MH</div><h1>تعيين كلمة مرور جديدة</h1><p>اكتب كلمة مرور جديدة لحساب المدير.</p><label>كلمة المرور الجديدة<input type="password" value={password} onChange={e => setPassword(e.target.value)} minLength={8} required/></label><label>تأكيد كلمة المرور<input type="password" value={confirmation} onChange={e => setConfirmation(e.target.value)} minLength={8} required/></label>{error && <div className="admin-error">{error}</div>}<button disabled={busy}>{busy ? 'جاري الحفظ...' : 'حفظ كلمة المرور'}</button></form></main>;
}

function FireDashboard({ onLogout }: { onLogout: () => void }) {
  const [settings, setSettings] = useState<FireSettings | null>(null);
  const [sizes, setSizes] = useState<FireSize[]>([]);
  const [orders, setOrders] = useState<FireOrder[]>([]);
  const [notice, setNotice] = useState('');
  const [ordersError, setOrdersError] = useState('');
  const [ordersLoading, setOrdersLoading] = useState(false);
  const [lastUpdated, setLastUpdated] = useState('');

  const load = async () => {
    setOrdersLoading(true); setOrdersError('');
    const [s, z, o] = await Promise.all([
      supabase.from('fire_settings').select('*').eq('id', 1).single(),
      supabase.from('fire_sizes').select('*').order('sort_order'),
      supabase.from('fire_orders').select('*').order('created_at', { ascending: false }),
    ]);
    if (s.data) setSettings(s.data as FireSettings);
    if (z.data) setSizes(z.data as FireSize[]);
    if (o.data) setOrders(o.data as FireOrder[]);
    if (o.error) setOrdersError(`تعذر تحميل الطلبات: ${o.error.message}`);
    setLastUpdated(new Date().toLocaleTimeString('ar-KW'));
    setOrdersLoading(false);
  };
  useEffect(() => {
    load();
    const timer = window.setInterval(load, 15000);
    window.addEventListener('focus', load);
    return () => { window.clearInterval(timer); window.removeEventListener('focus', load); };
  }, []);

  const save = async () => {
    setNotice('');
    const sizeResults = await Promise.all(sizes.map(item => supabase.from('fire_sizes').update({ price: item.price, active: item.active }).eq('id', item.id)));
    const settingsResult = settings
      ? await supabase.from('fire_settings').update({ whatsapp_number: settings.whatsapp_number, updated_at: new Date().toISOString() }).eq('id', 1)
      : { error: null };
    setNotice(sizeResults.some(result => result.error) || settingsResult.error ? 'تعذر حفظ بعض البيانات' : 'تم حفظ الأسعار بنجاح');
  };
  const updateOrder = async (id: string, status: string) => {
    await supabase.from('fire_orders').update({ status }).eq('id', id);
    setOrders(current => current.map(order => order.id === id ? { ...order, status } : order));
  };

  return <main className="admin-shell" dir="rtl">
    <header className="admin-header"><div><strong>لوحة تحكم ماركوز هوم</strong><small>إدارة جهاز الفير المعطر</small></div><div><a href="/admin/overview">اللوحة الموحدة</a><a href="/" target="_blank">عرض التطبيق</a><button onClick={onLogout}>تسجيل الخروج</button></div></header>
    <div className="admin-content">
      <section className="admin-card"><h2>أسعار المقاسات</h2><div className="fire-admin-sizes">{sizes.map(item => <label key={item.id}>{item.name_ar}<input type="number" min="0" step="1" value={item.price ?? ''} onChange={e => setSizes(current => current.map(size => size.id === item.id ? { ...size, price: e.target.value === '' ? null : Number(e.target.value) } : size))}/><span className="switch"><input type="checkbox" checked={item.active} onChange={e => setSizes(current => current.map(size => size.id === item.id ? { ...size, active: e.target.checked } : size))}/>{item.active ? 'ظاهر' : 'مخفي'}</span></label>)}</div>{settings && <label className="fire-whatsapp">رقم واتساب<input value={settings.whatsapp_number} onChange={e => setSettings({ ...settings, whatsapp_number: e.target.value })}/></label>}<button className="fire-save" onClick={save}>حفظ الأسعار</button>{notice && <p className="admin-success">{notice}</p>}</section>
      <section className="admin-card"><div className="card-title"><div><h2>طلبات الفير ({orders.length})</h2>{lastUpdated && <small>آخر تحديث: {lastUpdated} — تلقائي كل 15 ثانية</small>}</div><button onClick={load} disabled={ordersLoading}>{ordersLoading ? 'جاري التحديث...' : 'تحديث الآن'}</button></div>{ordersError && <p className="admin-error">{ordersError}</p>}{orders.length === 0 ? <p className="empty">لا توجد طلبات مسجلة حتى الآن.</p> : <div className="fire-orders">{orders.map(order => <div key={order.id}><span>{new Date(order.created_at).toLocaleString('ar-KW')}</span><strong>{order.customer_name || 'عميل'}<small>{order.customer_phone || 'بدون رقم'}</small></strong><span>{order.size_name}</span><b>{order.total === null ? 'حسب المقاس' : `${order.total} د.ك`}</b><select value={order.status} onChange={e => updateOrder(order.id, e.target.value)}><option value="new">جديد</option><option value="contacted">تم التواصل</option><option value="confirmed">مؤكد</option><option value="completed">مكتمل</option><option value="cancelled">ملغي</option></select></div>)}</div>}</section>
    </div>
  </main>;
}
