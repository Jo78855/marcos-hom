import React, { useEffect, useMemo, useState } from 'react';
import Admin from './components/Admin';
import { supabase } from './supabase';

type Design = { id: string; name_ar: string; image_url: string };
type Settings = {
  price_without_installation: number;
  price_with_installation: number;
  whatsapp_number: string;
};

interface InstallPromptEvent extends Event {
  prompt: () => Promise<void>;
  userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
}

const fallbackDesigns: Design[] = [
  { id: 'white', name_ar: 'أبيض مع خشب فاتح', image_url: '/coffee/white-lightwood.webp' },
  { id: 'brown', name_ar: 'بني مع ترافرتينو', image_url: '/coffee/brown-travertine.webp' },
  { id: 'black', name_ar: 'أسود مع خشب فاتح', image_url: '/coffee/black-lightwood.webp' },
  { id: 'darkgray', name_ar: 'رمادي غامق مع شيفرون', image_url: '/coffee/darkgray-chevron.webp' },
  { id: 'lightgray', name_ar: 'رمادي فاتح مع شيفرون', image_url: '/coffee/lightgray-chevron.webp' },
  { id: 'lightwood', name_ar: 'خشبي فاتح مع شيفرون', image_url: '/coffee/lightwood-chevron.webp' },
  { id: 'honey', name_ar: 'عسلي ماركوز هوم', image_url: '/coffee/honey-wood.webp' },
];

const fallbackSettings: Settings = {
  price_without_installation: 35,
  price_with_installation: 50,
  whatsapp_number: '96550204320',
};

export default function App() {
  if (window.location.pathname.startsWith('/admin')) return <Admin />;
  return <Storefront />;
}

function Storefront() {
  const [designs, setDesigns] = useState<Design[]>(fallbackDesigns);
  const [settings, setSettings] = useState<Settings>(fallbackSettings);
  const [design, setDesign] = useState<Design>(fallbackDesigns[0]);
  const [withInstallation, setWithInstallation] = useState(false);
  const [approved, setApproved] = useState(false);
  const [sending, setSending] = useState(false);
  const [customerName, setCustomerName] = useState('');
  const [customerPhone, setCustomerPhone] = useState('');
  const [orderError, setOrderError] = useState('');
  const [installPrompt, setInstallPrompt] = useState<InstallPromptEvent | null>(null);
  const [installed, setInstalled] = useState(window.matchMedia('(display-mode: standalone)').matches);

  useEffect(() => {
    Promise.all([
      supabase.from('store_settings').select('*').eq('id', 1).single(),
      supabase.from('designs').select('id,name_ar,image_url').eq('active', true).order('sort_order'),
    ]).then(([settingsResult, designsResult]) => {
      if (settingsResult.data) setSettings(settingsResult.data as Settings);
      if (designsResult.data?.length) {
        setDesigns(designsResult.data as Design[]);
        setDesign(designsResult.data[0] as Design);
      }
    });
  }, []);

  useEffect(() => {
    const capturePrompt = (event: Event) => {
      event.preventDefault();
      setInstallPrompt(event as InstallPromptEvent);
    };
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

  const price = withInstallation ? settings.price_with_installation : settings.price_without_installation;
  const optionLabel = withInstallation ? 'شامل التركيب' : 'بدون تركيب';
  const total = approved ? price : 0;
  const message = useMemo(() => [
    'مرحباً ماركوز هوم، أرغب بطلب ركن القهوة التالي:',
    `الاسم: ${customerName}`,
    `رقم الهاتف: ${customerPhone}`,
    `اللون: ${design.name_ar}`,
    `طريقة الطلب: ${optionLabel}`,
    `السعر الإجمالي: ${price} د.ك`,
  ].join('\n'), [customerName, customerPhone, design, optionLabel, price]);

  const chooseDesign = (next: Design) => { setDesign(next); setApproved(false); };
  const chooseOption = (installed: boolean) => { setWithInstallation(installed); setApproved(false); };
  const sendOrder = async () => {
    setOrderError('');
    if (!customerName.trim() || !customerPhone.trim()) {
      setOrderError('اكتب الاسم ورقم الهاتف أولًا');
      return;
    }
    setSending(true);
    const { error } = await supabase.from('orders').insert({
      customer_name: customerName.trim(),
      customer_phone: customerPhone.trim(),
      design_id: design.id.length === 36 ? design.id : null,
      installation: withInstallation,
      total: price,
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

  return (
    <main dir="rtl" className="site-shell">
      <header className="topbar">
        <div className="brand"><span className="brand-mark">MH</span><span><strong>ماركوز هوم</strong><small>ركن القهوة</small></span></div>
        <div className="top-actions"><span className="status">متاح للطلب الآن</span>{!installed && <button className="install-app" onClick={installApp}>تثبيت التطبيق</button>}</div>
      </header>
      <section className="hero">
        <div><span className="eyebrow">ركن القهوة من ماركوز هوم</span><h1>اختر اللون وطريقة الطلب، وأرسله مباشرة</h1><p>سبعة ألوان جاهزة بسعر {settings.price_without_installation} د.ك بدون تركيب أو {settings.price_with_installation} د.ك شامل التركيب.</p></div>
        <div className="total-card"><small>إجمالي طلبك</small><strong>{total} د.ك</strong></div>
      </section>
      <section className="workspace">
        <aside className="controls">
          <div className="step"><span>1</span><div><h2>اختر طريقة الطلب</h2><div className="option-grid">
            <button className={!withInstallation ? 'option active' : 'option'} onClick={() => chooseOption(false)}><strong>بدون تركيب</strong><small>{settings.price_without_installation} د.ك</small></button>
            <button className={withInstallation ? 'option active' : 'option'} onClick={() => chooseOption(true)}><strong>شامل التركيب</strong><small>{settings.price_with_installation} د.ك</small></button>
          </div></div></div>
          <div className="step"><span>2</span><div><h2>اختر اللون: {design.name_ar}</h2><div className="design-grid">
            {designs.map((item) => <button key={item.id} className={design.id === item.id ? 'design active' : 'design'} onClick={() => chooseDesign(item)}><img src={item.image_url} alt={item.name_ar} loading="lazy"/><span>{item.name_ar}</span></button>)}
          </div></div></div>
          <div className="step"><span>3</span><div><h2>بيانات التواصل</h2><div className="customer-fields"><input value={customerName} onChange={e => setCustomerName(e.target.value)} placeholder="الاسم"/><input value={customerPhone} onChange={e => setCustomerPhone(e.target.value)} inputMode="tel" placeholder="رقم الهاتف"/></div>{orderError && <p className="order-error">{orderError}</p>}</div></div>
          <button className="approve" onClick={() => setApproved(true)}>اعتماد الاختيار — {price} د.ك</button>
        </aside>
        <section className="preview">
          <div className="preview-title"><div><small>الصورة الفعلية</small><h2>ركن القهوة المختار</h2></div><span>{approved ? 'تم الاعتماد' : 'اختر ثم اعتمد'}</span></div>
          <div className="photo-wrap"><img src={design.image_url} alt={`ركن القهوة - ${design.name_ar}`}/><div className="photo-label"><strong>{design.name_ar}</strong><span>{price} د.ك — {optionLabel}</span></div></div>
          <div className="summary"><div><small>السعر الإجمالي</small><strong>{total} د.ك</strong></div><button disabled={!approved || sending} onClick={sendOrder}>{sending ? 'جاري التسجيل...' : 'إرسال الطلب عبر واتساب'}</button></div>
          <p className="hint">غيّر اللون أو طريقة الطلب ثم اضغط «اعتماد الاختيار» قبل الإرسال.</p>
        </section>
      </section>
    </main>
  );
}
