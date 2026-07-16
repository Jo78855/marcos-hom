import React, { FormEvent, useEffect, useState } from 'react';
import type { Session } from '@supabase/supabase-js';
import { supabase } from '../supabase';

type Settings = { price_without_installation: number; price_with_installation: number; whatsapp_number: string };
type Design = { id: string; name_ar: string; image_url: string; active: boolean; sort_order: number };
type Order = { id: string; customer_name: string | null; customer_phone: string | null; design_id: string | null; total: number; installation: boolean; status: string; created_at: string; design_name?: string };

export default function Admin() {
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
  if (recoveringPassword) {
    return <ResetPassword onComplete={() => {
      window.history.replaceState({}, document.title, '/admin');
      setRecoveringPassword(false);
      setAuthMessage('تم تعيين كلمة المرور الجديدة. يمكنك تسجيل الدخول الآن.');
    }} />;
  }
  return session ? <Dashboard onLogout={() => supabase.auth.signOut()} /> : <Login initialMessage={authMessage} />;
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
    if (!email.trim()) {
      setError('اكتب البريد الإلكتروني أولًا');
      return;
    }
    setBusy(true); setError(''); setMessage('');
    const { error } = await supabase.auth.resetPasswordForEmail(email.trim(), {
      redirectTo: `${window.location.origin}/admin`,
    });
    if (error) setError('تعذر إرسال رابط الاستعادة. حاول مرة أخرى بعد قليل.');
    else setMessage('تم إرسال رابط استعادة كلمة المرور إلى بريدك الإلكتروني. افتح الرسالة واضغط على الرابط.');
    setBusy(false);
  };
  return <main className="admin-login" dir="rtl"><form onSubmit={submit}><div className="brand-mark">MH</div><h1>لوحة تحكم ماركوز هوم</h1><p>أدخل بيانات حساب المدير</p><label>البريد الإلكتروني<input type="email" value={email} onChange={e => setEmail(e.target.value)} required/></label><label>كلمة المرور<input type="password" value={password} onChange={e => setPassword(e.target.value)} required/></label>{error && <div className="admin-error">{error}</div>}{message && <div className="admin-success-box">{message}</div>}<button disabled={busy}>{busy ? 'جاري التنفيذ...' : 'دخول'}</button><button className="forgot-password" type="button" onClick={sendRecoveryEmail} disabled={busy}>نسيت كلمة المرور؟</button><a href="/">العودة للموقع</a></form></main>;
}

function ResetPassword({ onComplete }: { onComplete: () => void }) {
  const [password, setPassword] = useState('');
  const [confirmation, setConfirmation] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const submit = async (event: FormEvent) => {
    event.preventDefault(); setError('');
    if (password.length < 8) {
      setError('كلمة المرور يجب أن تكون 8 أحرف على الأقل');
      return;
    }
    if (password !== confirmation) {
      setError('كلمتا المرور غير متطابقتين');
      return;
    }
    setBusy(true);
    const { error } = await supabase.auth.updateUser({ password });
    if (error) {
      setError('تعذر تعيين كلمة المرور. اطلب رابط استعادة جديدًا وحاول مرة أخرى.');
      setBusy(false);
      return;
    }
    await supabase.auth.signOut();
    onComplete();
  };
  return <main className="admin-login" dir="rtl"><form onSubmit={submit}><div className="brand-mark">MH</div><h1>تعيين كلمة مرور جديدة</h1><p>اكتب كلمة مرور جديدة لحساب مدير لوحة التحكم.</p><label>كلمة المرور الجديدة<input type="password" value={password} onChange={e => setPassword(e.target.value)} minLength={8} autoComplete="new-password" required/></label><label>تأكيد كلمة المرور<input type="password" value={confirmation} onChange={e => setConfirmation(e.target.value)} minLength={8} autoComplete="new-password" required/></label>{error && <div className="admin-error">{error}</div>}<button disabled={busy}>{busy ? 'جاري الحفظ...' : 'حفظ كلمة المرور الجديدة'}</button></form></main>;
}

function Dashboard({ onLogout }: { onLogout: () => void }) {
  const [settings, setSettings] = useState<Settings | null>(null);
  const [designs, setDesigns] = useState<Design[]>([]);
  const [orders, setOrders] = useState<Order[]>([]);
  const [notice, setNotice] = useState('');
  const [newDesignName, setNewDesignName] = useState('');
  const [newDesignFile, setNewDesignFile] = useState<File | null>(null);
  const [uploading, setUploading] = useState(false);
  const [ordersLoading, setOrdersLoading] = useState(false);
  const [ordersError, setOrdersError] = useState('');
  const [lastUpdated, setLastUpdated] = useState('');
  const load = async () => {
    setOrdersLoading(true);
    setOrdersError('');
    const [s, d, o] = await Promise.all([
      supabase.from('store_settings').select('*').eq('id', 1).single(),
      supabase.from('designs').select('*').order('sort_order'),
      supabase.from('orders').select('*').order('created_at', { ascending: false }),
    ]);
    if (s.data) setSettings(s.data as Settings);
    if (d.data) setDesigns(d.data as Design[]);
    if (o.error) setOrdersError(`تعذر تحميل الطلبات: ${o.error.message}`);
    if (o.data) {
      const names = new Map((d.data || []).map(item => [item.id, item.name_ar]));
      setOrders((o.data as Order[]).map(order => ({ ...order, design_name: order.design_id ? names.get(order.design_id) : 'تصميم' })));
    }
    setLastUpdated(new Date().toLocaleTimeString('ar-KW'));
    setOrdersLoading(false);
  };
  useEffect(() => {
    load();
    const timer = window.setInterval(load, 15000);
    window.addEventListener('focus', load);
    return () => { window.clearInterval(timer); window.removeEventListener('focus', load); };
  }, []);
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
  const addDesign = async (event: FormEvent) => {
    event.preventDefault();
    if (!newDesignName.trim() || !newDesignFile) {
      setNotice('اكتب اسم التصميم واختر صورة من جهازك');
      return;
    }
    setUploading(true);
    setNotice('');
    const extension = newDesignFile.name.split('.').pop()?.toLowerCase() || 'webp';
    const filePath = `${Date.now()}-${crypto.randomUUID()}.${extension}`;
    const upload = await supabase.storage.from('designs').upload(filePath, newDesignFile, { cacheControl: '3600', upsert: false });
    if (upload.error) {
      setNotice(`تعذر رفع الصورة: ${upload.error.message}`);
      setUploading(false);
      return;
    }
    const { data: publicImage } = supabase.storage.from('designs').getPublicUrl(filePath);
    const { error } = await supabase.from('designs').insert({ name_ar: newDesignName.trim(), image_url: publicImage.publicUrl, active: true, sort_order: designs.length + 1 });
    if (error) {
      await supabase.storage.from('designs').remove([filePath]);
      setNotice(`تعذر إضافة التصميم: ${error.message}`);
    } else {
      setNewDesignName('');
      setNewDesignFile(null);
      setNotice('تم رفع التصميم وإضافته للموقع بنجاح');
      await load();
    }
    setUploading(false);
  };
  return <main className="admin-shell" dir="rtl">
    <header className="admin-header"><div><strong>لوحة تحكم ماركوز هوم</strong><small>إدارة ركن القهوة</small></div><div><a href="/" target="_blank">عرض الموقع</a><button onClick={onLogout}>تسجيل الخروج</button></div></header>
    <div className="admin-content">
      <section className="admin-card"><h2>أسعار ركن القهوة</h2>{settings && <div className="admin-fields"><label>السعر بدون تركيب (د.ك)<input type="number" value={settings.price_without_installation} onChange={e => setSettings({...settings, price_without_installation: Number(e.target.value)})}/></label><label>السعر شامل التركيب (د.ك)<input type="number" value={settings.price_with_installation} onChange={e => setSettings({...settings, price_with_installation: Number(e.target.value)})}/></label><label>رقم واتساب لاستقبال الطلبات<input value={settings.whatsapp_number} onChange={e => setSettings({...settings, whatsapp_number: e.target.value})}/></label><button onClick={saveSettings}>حفظ الأسعار</button></div>}{notice && <p className="admin-success">{notice}</p>}</section>
      <section className="admin-card"><h2>إضافة تصميم جديد</h2><form className="add-design-form" onSubmit={addDesign}><label>اسم التصميم<input value={newDesignName} onChange={e => setNewDesignName(e.target.value)} placeholder="مثال: أبيض مع خشب طبيعي" required/></label><label>صورة التصميم<input key={newDesignFile ? 'selected' : 'empty'} type="file" accept="image/png,image/jpeg,image/webp" onChange={e => setNewDesignFile(e.target.files?.[0] || null)} required/></label><button disabled={uploading}>{uploading ? 'جاري رفع الصورة...' : 'رفع وإضافة التصميم'}</button></form><p className="form-hint">الصيغ المقبولة: JPG أو PNG أو WEBP.</p></section>
      <section className="admin-card"><h2>التصميمات الحالية</h2><div className="admin-designs">{designs.map(item => <div key={item.id}><img src={item.image_url} alt={item.name_ar}/><input aria-label="اسم التصميم" value={item.name_ar} onChange={e => updateDesign(item, { name_ar: e.target.value })}/><label className="switch"><input type="checkbox" checked={item.active} onChange={e => updateDesign(item, { active: e.target.checked })}/><span>{item.active ? 'ظاهر للعملاء' : 'مخفي عن العملاء'}</span></label></div>)}</div></section>
      <section className="admin-card"><div className="card-title"><div><h2>الطلبات ({orders.length})</h2>{lastUpdated && <small>آخر تحديث: {lastUpdated} — تحديث تلقائي كل 15 ثانية</small>}</div><button onClick={load} disabled={ordersLoading}>{ordersLoading ? 'جاري التحديث...' : 'تحديث الآن'}</button></div>{ordersError && <p className="admin-error">{ordersError}</p>}{orders.length === 0 ? <p className="empty">لا توجد طلبات مسجلة حتى الآن. نفّذ طلبًا جديدًا بعد تحديث الموقع.</p> : <div className="orders-table">{orders.map(order => <div key={order.id}><span>{new Date(order.created_at).toLocaleString('ar-KW')}</span><strong>{order.customer_name || 'عميل'}<small>{order.customer_phone || 'بدون رقم'}</small></strong><span>{order.design_name || 'تصميم'}</span><span>{order.installation ? 'شامل التركيب' : 'بدون تركيب'}</span><b>{order.total} د.ك</b><select value={order.status} onChange={e => updateOrder(order.id, e.target.value)}><option value="new">جديد</option><option value="contacted">تم التواصل</option><option value="confirmed">مؤكد</option><option value="completed">مكتمل</option><option value="cancelled">ملغي</option></select></div>)}</div>}</section>
    </div>
  </main>;
}
