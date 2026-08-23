import React, { FormEvent, useEffect, useMemo, useRef, useState } from 'react';
import { supabase } from '../supabase';

type Msg = { role: 'assistant' | 'user'; text: string };
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

function money(value: number) {
  return Number(value).toLocaleString('ar-KW', { maximumFractionDigits: 2 });
}

function rangeLabel(offer: Offer) {
  return offer.max_width ? `من ${offer.min_width} إلى ${offer.max_width} متر` : `من ${offer.min_width} متر فأكثر`;
}

function offerForWidth(width: number, offers: Offer[]) {
  return offers.find(o => width >= Number(o.min_width) && (o.max_width == null || width <= Number(o.max_width)));
}

function answerFor(text: string, offers: Offer[]) {
  const q = text.replace(/[؟?]/g, '').trim();
  if (!offers.length) return 'بيانات التصميمات غير متاحة مؤقتاً. حاول مرة أخرى بعد قليل.';

  const width = q.match(/(\d+(?:[.,]\d+)?)\s*(?:متر|م\b)/);
  if (width) {
    const w = Number(width[1].replace(',', '.'));
    const match = offerForWidth(w, offers);
    if (match) {
      return `مقاس ${w} متر مناسب لفئة ${rangeLabel(match)}. المكونات: ${match.components_ar}. السعر ${money(match.price_without_installation)} د.ك بدون تركيب أو ${money(match.price_with_installation)} د.ك مع التركيب.`;
    }
    if (w > Math.max(...offers.map(o => Number(o.max_width || o.min_width)))) {
      return 'المقاس أكبر من الفئات الحالية ويحتاج طلب خاص. أقدر أسجل بياناتك ويتواصل معك فريق ماركوز هوم.';
    }
  }

  if (/مكون|يتكون|فيه ايه|فيه إيه|تفاصيل/.test(q)) {
    const first = offers[0];
    const details = offers.map(o => `${rangeLabel(o)}: ${o.components_ar}`).join('. ');
    return `${first.name_ar} ارتفاعه ${first.height_m} متر. ${details}. قول لي عرض الحائط وأنا أحدد لك الفئة المناسبة.`;
  }

  if (/سعر|كام|بكام|تكلف/.test(q)) {
    const prices = offers.map(o => `${rangeLabel(o)}: ${money(o.price_without_installation)} د.ك بدون تركيب أو ${money(o.price_with_installation)} د.ك مع التركيب`).join('. ');
    return `الأسعار الحالية حسب عرض الحائط: ${prices}.`;
  }

  return 'أنا مساعد ماركوز هوم. أقدر أشرح المكونات والأسعار وأسجل طلبك. جرّب تقول: الحائط 4 متر، أو: عايز أحجز.';
}

export default function MarcosAssistant() {
  const [open, setOpen] = useState(false);
  const [listening, setListening] = useState(false);
  const [input, setInput] = useState('');
  const [offers, setOffers] = useState<Offer[]>([]);
  const [bookingOpen, setBookingOpen] = useState(false);
  const [bookingBusy, setBookingBusy] = useState(false);
  const [bookingNotice, setBookingNotice] = useState('');
  const [customerName, setCustomerName] = useState('');
  const [customerPhone, setCustomerPhone] = useState('');
  const [area, setArea] = useState('');
  const [wallWidth, setWallWidth] = useState('');
  const [installation, setInstallation] = useState(true);
  const [messages, setMessages] = useState<Msg[]>([
    { role: 'assistant', text: 'أهلاً بك في ماركوز هوم. اسألني بصوتك عن مكونات التصميم أو المقاس والسعر، أو قل: عايز أحجز.' },
  ]);
  const recognitionRef = useRef<any>(null);
  const speechSupported = useMemo(() => typeof window !== 'undefined' && !!((window as any).SpeechRecognition || (window as any).webkitSpeechRecognition), []);

  useEffect(() => {
    const load = async () => {
      const { data } = await supabase.from('assistant_offers').select('*').eq('active', true).order('sort_order');
      if (data) setOffers(data as Offer[]);
    };
    load();
  }, []);

  const speak = (text: string) => {
    if (!('speechSynthesis' in window)) return;
    window.speechSynthesis.cancel();
    const u = new SpeechSynthesisUtterance(text);
    u.lang = 'ar-KW';
    u.rate = 0.95;
    window.speechSynthesis.speak(u);
  };

  const submit = (raw = input) => {
    const text = raw.trim();
    if (!text) return;
    let reply: string;
    if (/احجز|حجز|معاينة|اطلب|طلب/.test(text)) {
      setBookingOpen(true);
      reply = 'تمام. فتحت لك تسجيل الطلب. اكتب الاسم ورقم الهاتف والمنطقة وعرض الحائط، وأنا أسجل الطلب مباشرة.';
    } else {
      reply = answerFor(text, offers);
    }
    setMessages(prev => [...prev, { role: 'user', text }, { role: 'assistant', text: reply }]);
    setInput('');
    speak(reply);
  };

  const startVoice = () => {
    const SR = (window as any).SpeechRecognition || (window as any).webkitSpeechRecognition;
    if (!SR) return;
    const recognition = new SR();
    recognition.lang = 'ar-KW';
    recognition.interimResults = false;
    recognition.continuous = false;
    recognition.onstart = () => setListening(true);
    recognition.onend = () => setListening(false);
    recognition.onerror = () => setListening(false);
    recognition.onresult = (event: any) => {
      const text = event.results?.[0]?.[0]?.transcript || '';
      setInput(text);
      submit(text);
    };
    recognitionRef.current = recognition;
    recognition.start();
  };

  const submitBooking = async (event: FormEvent) => {
    event.preventDefault();
    setBookingNotice('');
    const width = Number(wallWidth);
    if (!customerName.trim() || !customerPhone.trim() || !area.trim() || !Number.isFinite(width) || width <= 0) {
      setBookingNotice('أكمل الاسم ورقم الهاتف والمنطقة وعرض الحائط.');
      return;
    }
    const offer = offerForWidth(width, offers);
    if (!offer) {
      setBookingNotice('المقاس يحتاج تسعير خاص. سيتم تسجيله كطلب خاص بدون سعر نهائي.');
    }
    const total = offer ? Number(installation ? offer.price_with_installation : offer.price_without_installation) : 0;
    setBookingBusy(true);
    const { error } = await supabase.from('orders').insert({
      customer_name: customerName.trim(),
      customer_phone: customerPhone.trim(),
      design_id: null,
      installation,
      total,
      status: 'new',
      area: area.trim(),
      wall_width: width,
      source: 'voice_assistant',
    });
    setBookingBusy(false);
    if (error) {
      setBookingNotice(`تعذر تسجيل الطلب: ${error.message}`);
      return;
    }
    const reply = total > 0 ? `تم تسجيل طلبك بنجاح. السعر المبدئي ${money(total)} د.ك ${installation ? 'مع التركيب' : 'بدون تركيب'}. سيتواصل معك فريق ماركوز هوم.` : 'تم تسجيل طلبك الخاص بنجاح، وسيتواصل معك فريق ماركوز هوم للتسعير.';
    setBookingNotice(reply);
    setMessages(prev => [...prev, { role: 'assistant', text: reply }]);
    speak(reply);
    setCustomerName(''); setCustomerPhone(''); setArea(''); setWallWidth('');
  };

  return (
    <div className="mh-assistant" dir="rtl">
      {open && <section className="mh-assistant-panel">
        <header><div><strong>مساعد ماركوز هوم</strong><small>المعلومات متصلة بلوحة التحكم</small></div><button onClick={() => setOpen(false)} aria-label="إغلاق">×</button></header>
        <div className="mh-assistant-messages">
          {messages.map((m, i) => <div key={i} className={`mh-msg ${m.role}`}>{m.text}</div>)}
          {bookingOpen && <form className="mh-booking" onSubmit={submitBooking}>
            <strong>تسجيل طلب / معاينة</strong>
            <input value={customerName} onChange={e => setCustomerName(e.target.value)} placeholder="الاسم" />
            <input value={customerPhone} onChange={e => setCustomerPhone(e.target.value)} inputMode="tel" placeholder="رقم الهاتف" />
            <input value={area} onChange={e => setArea(e.target.value)} placeholder="المنطقة" />
            <input value={wallWidth} onChange={e => setWallWidth(e.target.value)} inputMode="decimal" placeholder="عرض الحائط بالمتر" />
            <div className="mh-booking-options"><button type="button" className={!installation ? 'active' : ''} onClick={() => setInstallation(false)}>بدون تركيب</button><button type="button" className={installation ? 'active' : ''} onClick={() => setInstallation(true)}>مع التركيب</button></div>
            <button className="mh-booking-submit" disabled={bookingBusy}>{bookingBusy ? 'جاري التسجيل...' : 'تسجيل الطلب'}</button>
            {bookingNotice && <small>{bookingNotice}</small>}
          </form>}
        </div>
        <div className="mh-assistant-input">
          <button className={listening ? 'mic listening' : 'mic'} onClick={startVoice} disabled={!speechSupported} title={speechSupported ? 'تحدث الآن' : 'الصوت غير مدعوم في هذا المتصفح'}>{listening ? '●' : '🎙'}</button>
          <input value={input} onChange={e => setInput(e.target.value)} onKeyDown={e => e.key === 'Enter' && submit()} placeholder="اتكلم أو اكتب سؤالك" />
          <button onClick={() => submit()}>إرسال</button>
        </div>
        {!speechSupported && <p className="mh-assistant-note">يمكنك تجربة المحادثة بالكتابة حالياً على هذا المتصفح.</p>}
      </section>}
      <button className="mh-assistant-launcher" onClick={() => setOpen(v => !v)} aria-label="مساعد ماركوز هوم">🎙<span>اسأل ماركوز هوم</span></button>
    </div>
  );
}
