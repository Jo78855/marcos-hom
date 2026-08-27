import React, { FormEvent, useEffect, useState } from 'react';
import { supabase } from '../supabase';
import './OrderAccessPortal.css';

const logoPath = window.location.hostname.endsWith('github.io') ? '/marcos-hom/marcos-home-logo.jpg' : '/marcos-home-logo.jpg';

type PortalData = {
  audience: 'customer' | 'technician';
  order: {
    order_number: string;
    service_type: string;
    width_m: number | null;
    height_m: number | null;
    color: string | null;
    installation: boolean;
    installation_date: string | null;
    status: string;
    customer_notes: string | null;
    total?: number | null;
    paid_amount?: number;
    balance?: number | null;
  };
  customer: { name: string; phone?: string; area?: string | null; address?: string | null };
  technician?: { name: string } | null;
  items: Array<{ name: string; description: string | null; quantity: number }>;
  events: Array<{ status: string; note: string | null; created_at: string }>;
  confirmations: Array<{
    confirmation_type: 'details' | 'handover';
    accepted: boolean;
    rating: number | null;
    comment: string | null;
    created_at: string;
  }>;
};

const STATUS: Record<string, string> = {
  draft: 'قيد التجهيز', awaiting_customer: 'بانتظار تأكيدك', confirmed: 'تم التأكيد', scheduled: 'تم تحديد الموعد',
  technician_assigned: 'تم تعيين الفني', en_route: 'الفني في الطريق', arrived: 'وصل الفني', in_progress: 'بدأ التنفيذ',
  blocked: 'توجد ملاحظة', technician_done: 'انتهى التنفيذ', awaiting_customer_handover: 'بانتظار الاستلام',
  completed: 'تم الاستلام', cancelled: 'ملغي',
};

export default function OrderAccessPortal() {
  const token = new URLSearchParams(window.location.search).get('token') || '';
  const [data, setData] = useState<PortalData | null>(null);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const [note, setNote] = useState('');
  const [rating, setRating] = useState(5);
  const [choice, setChoice] = useState<'ok' | 'note' | ''>('');

  const load = async () => {
    if (!token) return setError('الرابط غير مكتمل. اطلب رابطًا جديدًا من ماركوز هوم.');
    const { data: result, error: rpcError } = await supabase.rpc('mh_open_order_link', { raw_token: token });
    if (rpcError || !result) return setError('الرابط غير صالح أو انتهت صلاحيته.');
    setData(result as PortalData);
  };

  useEffect(() => { load(); }, []);

  const technicianStatus = async (status: string) => {
    setBusy(true);
    const { error: rpcError } = await supabase.rpc('mh_technician_update_status', { raw_token: token, next_status: status, status_note: note || null });
    if (rpcError) setError('تعذر تحديث الحالة. حاول مرة أخرى.'); else { setNote(''); await load(); }
    setBusy(false);
  };

  const customerConfirm = async (event: FormEvent, type: 'details' | 'handover') => {
    event.preventDefault();
    setBusy(true);
    const savedComment = note.trim() || (type === 'handover'
      ? 'تم استلام الأعمال المتفق عليها — لا توجد ملاحظات'
      : 'التفاصيل تمام — لا توجد ملاحظات');
    const { error: rpcError } = await supabase.rpc('mh_customer_confirm_order', {
      raw_token: token, confirmation_kind: type, customer_rating: type === 'handover' ? rating : null, customer_comment: savedComment,
    });
    if (rpcError) setError('تعذر تسجيل التأكيد. حاول مرة أخرى.'); else { setNote(''); setChoice(''); await load(); }
    setBusy(false);
  };

  if (error && !data) return <main className="portal-shell" dir="rtl"><PortalBrand /><section className="portal-card portal-error"><h1>تعذر فتح الطلب</h1><p>{error}</p></section></main>;
  if (!data) return <main className="portal-shell" dir="rtl"><PortalBrand /><section className="portal-card">جاري فتح الطلب...</section></main>;

  const order = data.order;
  const isCustomer = data.audience === 'customer';
  const isHandover = order.status === 'awaiting_customer_handover' || order.status === 'technician_done';
  const detailsConfirmation = data.confirmations?.find(item => item.confirmation_type === 'details' && item.accepted);
  const handoverConfirmation = data.confirmations?.find(item => item.confirmation_type === 'handover' && item.accepted);
  return <main className="portal-shell" dir="rtl">
    <PortalBrand />
    <section className="portal-hero"><span>{isCustomer ? 'رابط العميل الآمن' : `مهمة الفني: ${data.technician?.name || ''}`}</span><h1>{order.order_number}</h1><b>{STATUS[order.status] || order.status}</b></section>
    {error && <div className="portal-banner">{error}</div>}
    {isCustomer ? <section className="portal-card portal-summary"><h2>ملخص الطلب</h2><dl>
      <div><dt>الخدمة</dt><dd>{order.service_type}</dd></div>
      <div><dt>الحالة</dt><dd>{STATUS[order.status] || order.status}</dd></div>
      <div><dt>موعد التركيب</dt><dd>{order.installation_date ? new Date(order.installation_date).toLocaleString('ar-KW') : 'يحدد لاحقًا'}</dd></div>
    </dl></section> : <section className="portal-grid">
      <article className="portal-card"><h2>بيانات الطلب</h2><dl><div><dt>الخدمة</dt><dd>{order.service_type}</dd></div><div><dt>المقاس</dt><dd>{order.width_m || '-'} × {order.height_m || '-'} متر</dd></div><div><dt>اللون</dt><dd>{order.color || 'حسب الاتفاق'}</dd></div><div><dt>التركيب</dt><dd>{order.installation ? 'شامل التركيب' : 'بدون تركيب'}</dd></div><div><dt>الموعد</dt><dd>{order.installation_date ? new Date(order.installation_date).toLocaleString('ar-KW') : 'يحدد لاحقًا'}</dd></div></dl></article>
      <article className="portal-card"><h2>موقع التنفيذ</h2><dl><div><dt>الاسم</dt><dd>{data.customer.name}</dd></div><div><dt>الهاتف</dt><dd>{data.customer.phone || '-'}</dd></div><div><dt>العنوان</dt><dd>{data.customer.address || data.customer.area || '-'}</dd></div></dl></article>
    </section>}
    {!!data.items.length && <section className="portal-card"><h2>مكونات الطلب</h2>{data.items.map((item, index) => <div className="portal-item" key={index}><strong>{item.name}</strong><span>{item.quantity} × {item.description || ''}</span></div>)}</section>}
    {isCustomer && order.customer_notes && <section className="portal-card portal-agreement"><h2>الملاحظات المتفق عليها</h2><p>{order.customer_notes}</p></section>}
    {isCustomer && (detailsConfirmation || handoverConfirmation) && <section className="portal-card portal-documentation"><h2>تم تسجيل طلبك</h2><div className="portal-proof-grid">
      <div className={detailsConfirmation ? 'portal-proof done' : 'portal-proof'}><strong>تأكيد تفاصيل الطلب</strong><span>{detailsConfirmation ? `تم التأكيد: ${new Date(detailsConfirmation.created_at).toLocaleString('ar-KW')}` : 'لم يؤكد العميل بعد'}</span>{detailsConfirmation?.comment && <small>ملاحظة العميل: {detailsConfirmation.comment}</small>}</div>
      <div className={handoverConfirmation ? 'portal-proof done' : 'portal-proof'}><strong>استلام الأعمال</strong><span>{handoverConfirmation ? `تم الاستلام: ${new Date(handoverConfirmation.created_at).toLocaleString('ar-KW')}` : 'بانتظار انتهاء التنفيذ'}</span>{handoverConfirmation?.rating && <small>التقييم: {handoverConfirmation.rating} من 5</small>}{handoverConfirmation?.comment && <small>رأي العميل: {handoverConfirmation.comment}</small>}</div>
    </div></section>}
    <section className="portal-card portal-actions"><h2>{isCustomer ? (isHandover ? 'استلام العمل' : 'تأكيد التفاصيل') : 'تحديث التنفيذ'}</h2>
      {isCustomer ? <form onSubmit={e => customerConfirm(e, isHandover ? 'handover' : 'details')}>
        {isHandover && <label>تقييم الخدمة<select value={rating} onChange={e => setRating(Number(e.target.value))}>{[5,4,3,2,1].map(value => <option key={value} value={value}>{value} نجوم</option>)}</select></label>}
        <div className="portal-choice-grid"><label className={choice === 'ok' ? 'portal-choice selected' : 'portal-choice'}><input type="radio" name="customer-choice" value="ok" checked={choice === 'ok'} onChange={() => setChoice('ok')} /><span>{isHandover ? 'تم استلام الأعمال المتفق عليها' : 'التفاصيل تمام'}</span></label><label className={choice === 'note' ? 'portal-choice selected' : 'portal-choice'}><input type="radio" name="customer-choice" value="note" checked={choice === 'note'} onChange={() => setChoice('note')} /><span>{isHandover ? 'تم الاستلام مع ملاحظة' : 'لدي ملاحظة'}</span></label></div>
        {choice === 'note' && <textarea value={note} onChange={e => setNote(e.target.value)} placeholder="اكتب ملاحظتك — اختياري" />}
        <button disabled={busy || !choice}>{isHandover ? 'تأكيد الاستلام' : 'تأكيد التفاصيل'}</button>
      </form> : <><textarea value={note} onChange={e => setNote(e.target.value)} placeholder="أضف ملاحظة اختيارية" /><div className="portal-buttons"><button disabled={busy} onClick={() => technicianStatus('en_route')}>في الطريق</button><button disabled={busy} onClick={() => technicianStatus('arrived')}>وصلت</button><button disabled={busy} onClick={() => technicianStatus('in_progress')}>بدأ التنفيذ</button><button disabled={busy} onClick={() => technicianStatus('blocked')}>توجد مشكلة</button><button className="done" disabled={busy} onClick={() => technicianStatus('technician_done')}>انتهى العمل</button></div></>}
    </section>
    {isCustomer && <details className="portal-card portal-more"><summary>عرض التفاصيل الكاملة</summary><dl><div><dt>المقاس</dt><dd>{order.width_m || '-'} × {order.height_m || '-'} متر</dd></div><div><dt>اللون</dt><dd>{order.color || 'حسب الاتفاق'}</dd></div><div><dt>التركيب</dt><dd>{order.installation ? 'شامل التركيب' : 'بدون تركيب'}</dd></div><div><dt>الإجمالي</dt><dd>{order.total ?? 'حسب الاتفاق'} د.ك</dd></div><div><dt>المدفوع</dt><dd>{order.paid_amount ?? 0} د.ك</dd></div><div><dt>المتبقي</dt><dd>{order.balance ?? 0} د.ك</dd></div></dl>{!!data.events.length && <div className="portal-history"><h3>سجل التنفيذ</h3>{data.events.map((event, index) => <div className="portal-event" key={index}><b>{STATUS[event.status] || event.status}</b><span>{new Date(event.created_at).toLocaleString('ar-KW')}{event.note ? ` — ${event.note}` : ''}</span></div>)}</div>}</details>}
    {!isCustomer && !!data.events.length && <section className="portal-card"><h2>سجل الحالة</h2>{data.events.map((event, index) => <div className="portal-event" key={index}><b>{STATUS[event.status] || event.status}</b><span>{new Date(event.created_at).toLocaleString('ar-KW')}{event.note ? ` — ${event.note}` : ''}</span></div>)}</section>}
  </main>;
}

function PortalBrand() { return <header className="portal-brand"><img src={logoPath} alt="Marco’s Home" /><div><strong>Marco’s Home</strong><span>تنفيذ موثّق وواضح</span></div></header>; }
