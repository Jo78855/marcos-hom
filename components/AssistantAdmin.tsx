import React, { useEffect, useState } from 'react';
import type { Session } from '@supabase/supabase-js';
import { supabase } from '../supabase';

type Offer = {
  id: number;
  code: string;
  name_ar: string;
  min_width: number;
  max_width: number | null;
  height_m: number;
  price_without_installation: number;
  price_with_installation: number;
  components_ar: string;
  active: boolean;
  sort_order: number;
};

export default function AssistantAdmin() {
  const [session, setSession] = useState<Session | null>(null);
  const [loading, setLoading] = useState(true);
  useEffect(() => {
    supabase.auth.getSession().then(({ data }) => { setSession(data.session); setLoading(false); });
    const { data } = supabase.auth.onAuthStateChange((_event, next) => setSession(next));
    return () => data.subscription.unsubscribe();
  }, []);
  if (loading) return <main className="admin-center" dir="rtl">جاري التحميل...</main>;
  if (!session) return <main className="admin-login" dir="rtl"><div><div className="brand-mark">MH</div><h1>إدارة مساعد ماركوز هوم</h1><p>سجل الدخول من لوحة التحكم أولاً.</p><a href="/admin">الذهاب لتسجيل الدخول</a></div></main>;
  return <KnowledgeEditor />;
}

function KnowledgeEditor() {
  const [offers, setOffers] = useState<Offer[]>([]);
  const [busy, setBusy] = useState(false);
  const [notice, setNotice] = useState('');

  const load = async () => {
    const { data, error } = await supabase.from('assistant_offers').select('*').order('sort_order');
    if (error) setNotice(`تعذر تحميل بيانات المساعد: ${error.message}`);
    if (data) setOffers(data as Offer[]);
  };

  useEffect(() => { load(); }, []);

  const patchLocal = (id: number, patch: Partial<Offer>) => {
    setOffers(current => current.map(item => item.id === id ? { ...item, ...patch } : item));
  };

  const save = async (offer: Offer) => {
    setBusy(true); setNotice('');
    const { error } = await supabase.from('assistant_offers').update({
      name_ar: offer.name_ar,
      min_width: Number(offer.min_width),
      max_width: offer.max_width === null ? null : Number(offer.max_width),
      height_m: Number(offer.height_m),
      price_without_installation: Number(offer.price_without_installation),
      price_with_installation: Number(offer.price_with_installation),
      components_ar: offer.components_ar,
      active: offer.active,
      sort_order: Number(offer.sort_order),
      updated_at: new Date().toISOString(),
    }).eq('id', offer.id);
    setNotice(error ? `تعذر الحفظ: ${error.message}` : 'تم تحديث عقل المساعد. التغيير سيظهر للعميل عند فتح الموقع أو تحديث الصفحة.');
    setBusy(false);
  };

  const addTier = async () => {
    setBusy(true); setNotice('');
    const sort = offers.length ? Math.max(...offers.map(o => o.sort_order)) + 10 : 10;
    const { error } = await supabase.from('assistant_offers').insert({
      code: `custom-${Date.now()}`,
      name_ar: 'تصميم جديد',
      min_width: 0,
      max_width: null,
      height_m: 2.9,
      price_without_installation: 0,
      price_with_installation: 0,
      components_ar: 'اكتب مكونات التصميم هنا',
      active: false,
      sort_order: sort,
    });
    if (error) setNotice(`تعذر إضافة فئة: ${error.message}`);
    else { setNotice('تمت إضافة فئة جديدة. عدّل بياناتها ثم فعّلها.'); await load(); }
    setBusy(false);
  };

  return <main className="admin-shell" dir="rtl">
    <header className="admin-header"><div><strong>عقل مساعد ماركوز هوم</strong><small>المكونات والأسعار والمقاسات</small></div><div><a href="/admin/overview">اللوحة الموحدة</a><a href="/" target="_blank">اختبار المساعد</a><button onClick={() => supabase.auth.signOut().then(() => location.href='/admin')}>تسجيل الخروج</button></div></header>
    <div className="admin-content">
      <section className="admin-card"><div className="card-title"><div><h2>فئات التصميم</h2><small>أي تعديل هنا يصبح مصدر معلومات المساعد الصوتي.</small></div><button onClick={addTier} disabled={busy}>+ إضافة فئة</button></div>{notice && <p className={notice.startsWith('تم') ? 'admin-success' : 'admin-error'}>{notice}</p>}</section>
      {offers.map(offer => <section className="admin-card assistant-admin-card" key={offer.id}>
        <div className="assistant-admin-grid">
          <label>اسم التصميم<input value={offer.name_ar} onChange={e => patchLocal(offer.id,{name_ar:e.target.value})}/></label>
          <label>من عرض (متر)<input type="number" step="0.1" value={offer.min_width} onChange={e => patchLocal(offer.id,{min_width:Number(e.target.value)})}/></label>
          <label>إلى عرض (متر)<input type="number" step="0.1" value={offer.max_width ?? ''} onChange={e => patchLocal(offer.id,{max_width:e.target.value===''?null:Number(e.target.value)})}/></label>
          <label>الارتفاع<input type="number" step="0.01" value={offer.height_m} onChange={e => patchLocal(offer.id,{height_m:Number(e.target.value)})}/></label>
          <label>بدون تركيب<input type="number" value={offer.price_without_installation} onChange={e => patchLocal(offer.id,{price_without_installation:Number(e.target.value)})}/></label>
          <label>مع التركيب<input type="number" value={offer.price_with_installation} onChange={e => patchLocal(offer.id,{price_with_installation:Number(e.target.value)})}/></label>
          <label className="assistant-components">مكونات التصميم<textarea rows={3} value={offer.components_ar} onChange={e => patchLocal(offer.id,{components_ar:e.target.value})}/></label>
          <label>الترتيب<input type="number" value={offer.sort_order} onChange={e => patchLocal(offer.id,{sort_order:Number(e.target.value)})}/></label>
          <label className="switch assistant-active"><input type="checkbox" checked={offer.active} onChange={e => patchLocal(offer.id,{active:e.target.checked})}/><span>{offer.active?'مفعّل للمساعد':'غير مفعّل'}</span></label>
        </div>
        <button className="assistant-save" onClick={() => save(offer)} disabled={busy}>حفظ هذه الفئة</button>
      </section>)}
    </div>
  </main>;
}
