import React, { useEffect, useMemo, useState } from 'react';
import { supabase } from '../supabase';

type FireSize = { id: string; name_ar: string; length_cm: number; price: number | null };
type FireSettings = { whatsapp_number: string };

interface InstallPromptEvent extends Event {
  prompt: () => Promise<void>;
  userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
}

const fallbackSizes: FireSize[] = [
  { id: '40', name_ar: '40 سم', length_cm: 40, price: 85 },
  { id: '70', name_ar: '70 سم', length_cm: 70, price: 135 },
  { id: '100', name_ar: '1 متر', length_cm: 100, price: 180 },
  { id: '120', name_ar: '1.20 متر', length_cm: 120, price: 220 },
  { id: '150', name_ar: '1.50 متر', length_cm: 150, price: 270 },
];

export default function FireStorefront() {
  const [sizes, setSizes] = useState<FireSize[]>(fallbackSizes);
  const [settings, setSettings] = useState<FireSettings>({ whatsapp_number: '96550204320' });
  const [size, setSize] = useState<FireSize>(fallbackSizes[0]);
  const [approved, setApproved] = useState(false);
  const [sending, setSending] = useState(false);
  const [customerName, setCustomerName] = useState('');
  const [customerPhone, setCustomerPhone] = useState('');
  const [orderError, setOrderError] = useState('');
  const [installPrompt, setInstallPrompt] = useState<InstallPromptEvent | null>(null);
  const [installed, setInstalled] = useState(window.matchMedia('(display-mode: standalone)').matches);

  useEffect(() => {
    Promise.all([
      supabase.from('fire_settings').select('whatsapp_number').eq('id', 1).single(),
      supabase.from('fire_sizes').select('id,name_ar,length_cm,price').eq('active', true).order('sort_order'),
    ]).then(([settingsResult, sizesResult]) => {
      if (settingsResult.data) setSettings(settingsResult.data as FireSettings);
      if (sizesResult.data?.length) {
        setSizes(sizesResult.data as FireSize[]);
        setSize(sizesResult.data[0] as FireSize);
      }
    });
  }, []);

  useEffect(() => {
    const capturePrompt = (event: Event) => { event.preventDefault(); setInstallPrompt(event as InstallPromptEvent); };
    const markInstalled = () => { setInstalled(true); setInstallPrompt(null); };
    window.addEventListener('beforeinstallprompt', capturePrompt);
    window.addEventListener('appinstalled', markInstalled);
    return () => {
      window.removeEventListener('beforeinstallprompt', capturePrompt);
      window.removeEventListener('appinstalled', markInstalled);
    };
  }, []);

  const installApp = async () => {
    if (installPrompt) {
      await installPrompt.prompt();
      const choice = await installPrompt.userChoice;
      if (choice.outcome === 'accepted') setInstallPrompt(null);
      return;
    }
    alert('على آيفون: افتح الموقع في Safari، اضغط زر المشاركة، ثم اختر «إضافة إلى الشاشة الرئيسية».');
  };

  const priceLabel = size.price === null ? 'السعر حسب المقاس' : `${size.price} د.ك`;
  const totalLabel = approved ? priceLabel : '—';
  const message = useMemo(() => [
    'مرحباً ماركوز هوم، أرغب بطلب جهاز الفير المعطر:',
    `الاسم: ${customerName}`,
    `رقم الهاتف: ${customerPhone}`,
    `المقاس: ${size.name_ar}`,
    `السعر: ${priceLabel}`,
  ].join('\n'), [customerName, customerPhone, size, priceLabel]);

  const chooseSize = (next: FireSize) => { setSize(next); setApproved(false); setOrderError(''); };
  const sendOrder = async () => {
    setOrderError('');
    if (!customerName.trim() || !customerPhone.trim()) {
      setOrderError('اكتب الاسم ورقم الهاتف أولًا');
      return;
    }
    setSending(true);
    const { error } = await supabase.from('fire_orders').insert({
      customer_name: customerName.trim(),
      customer_phone: customerPhone.trim(),
      size_id: size.id.length === 36 ? size.id : null,
      size_name: size.name_ar,
      total: size.price,
      status: 'new',
    });
    if (error) {
      setOrderError(`تعذر تسجيل الطلب: ${error.message}`);
      setSending(false);
      return;
    }
    window.open(`https://wa.me/${settings.whatsapp_number}?text=${encodeURIComponent(message)}`, '_blank');
    setSending(false);
  };

  return <main dir="rtl" className="site-shell fire-site">
    <header className="topbar">
      <div className="brand"><span className="brand-mark fire-mark">MH</span><span><strong>ماركوز هوم</strong><small>جهاز الفير المعطر</small></span></div>
      <div className="top-actions"><span className="status fire-status">متاح للطلب الآن</span>{!installed && <button className="install-app fire-install" onClick={installApp}>تثبيت التطبيق</button>}</div>
    </header>
    <section className="hero fire-hero">
      <div><span className="eyebrow fire-eyebrow">الفير المعطر من ماركوز هوم</span><h1>اختار المقاس المناسب وخلي الديكور ينبض بالحياة</h1><p>لهب مائي ثلاثي الأبعاد، مع مركز صيانة وقطع غيار. الأسعار تبدأ من 85 د.ك.</p></div>
      <div className="total-card fire-total"><small>إجمالي طلبك</small><strong>{totalLabel}</strong></div>
    </section>
    <section className="workspace">
      <aside className="controls">
        <div className="step"><span>1</span><div><h2>اختر المقاس: {size.name_ar}</h2><div className="fire-size-grid">
          {sizes.map(item => <button key={item.id} className={size.id === item.id ? 'option active fire-size' : 'option fire-size'} onClick={() => chooseSize(item)}><strong>{item.name_ar}</strong><small>{item.price === null ? 'السعر يُحدّث قريبًا' : `${item.price} د.ك`}</small></button>)}
        </div></div></div>
        <div className="step"><span>2</span><div><h2>بيانات التواصل</h2><div className="customer-fields"><input value={customerName} onChange={e => setCustomerName(e.target.value)} placeholder="الاسم"/><input value={customerPhone} onChange={e => setCustomerPhone(e.target.value)} inputMode="tel" placeholder="رقم الهاتف"/></div>{orderError && <p className="order-error">{orderError}</p>}</div></div>
        <button className="approve fire-approve" onClick={() => setApproved(true)}>اعتماد المقاس — {priceLabel}</button>
      </aside>
      <section className="preview">
        <div className="preview-title"><div><small>الصورة الرئيسية المعتمدة</small><h2>جهاز الفير المعطر</h2></div><span>{approved ? 'تم الاعتماد' : 'اختر ثم اعتمد'}</span></div>
        <div className="photo-wrap fire-photo"><img src="/fire/main-product.webp" alt="جهاز الفير المعطر من ماركوز هوم"/><div className="photo-label"><strong>{size.name_ar}</strong><span>{priceLabel}</span></div></div>
        <div className="summary"><div><small>السعر</small><strong>{totalLabel}</strong></div><button disabled={!approved || sending} onClick={sendOrder}>{sending ? 'جاري التسجيل...' : 'إرسال الطلب عبر واتساب'}</button></div>
        <p className="hint">اختر المقاس ثم اضغط «اعتماد المقاس» قبل إرسال الطلب.</p>
      </section>
    </section>
  </main>;
}
