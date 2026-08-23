import React, { useEffect, useMemo, useRef, useState } from 'react';
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

function answerFor(text: string, offers: Offer[]) {
  const q = text.replace(/[؟?]/g, '').trim();
  if (!offers.length) return 'بيانات التصميمات غير متاحة مؤقتاً. حاول مرة أخرى بعد قليل.';

  const width = q.match(/(\d+(?:[.,]\d+)?)\s*(?:متر|م)?/);
  if (width) {
    const w = Number(width[1].replace(',', '.'));
    const match = offers.find(o => w >= Number(o.min_width) && (o.max_width == null || w <= Number(o.max_width)));
    if (match) {
      return `مقاس ${w} متر مناسب لفئة ${rangeLabel(match)}. المكونات: ${match.components_ar}. السعر ${money(match.price_without_installation)} د.ك بدون تركيب أو ${money(match.price_with_installation)} د.ك مع التركيب.`;
    }
    if (w > Math.max(...offers.map(o => Number(o.max_width || o.min_width)))) {
      return 'المقاس أكبر من الفئات الحالية ويحتاج طلب خاص. في المرحلة التالية أقدر أجمع بياناتك للحجز مباشرة.';
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

  return 'أنا مساعد ماركوز هوم. أقدر حالياً أشرح مكونات التصميم وأسعاره حسب عرض الحائط. جرّب تقول: مكونات التصميم إيه؟ أو: الحائط 4 متر.';
}

export default function MarcosAssistant() {
  const [open, setOpen] = useState(false);
  const [listening, setListening] = useState(false);
  const [input, setInput] = useState('');
  const [offers, setOffers] = useState<Offer[]>([]);
  const [messages, setMessages] = useState<Msg[]>([
    { role: 'assistant', text: 'أهلاً بك في ماركوز هوم. اسألني بصوتك عن مكونات التصميم أو المقاس والسعر.' },
  ]);
  const recognitionRef = useRef<any>(null);
  const speechSupported = useMemo(() => typeof window !== 'undefined' && !!((window as any).SpeechRecognition || (window as any).webkitSpeechRecognition), []);

  useEffect(() => {
    const load = async () => {
      const { data } = await supabase
        .from('assistant_offers')
        .select('*')
        .eq('active', true)
        .order('sort_order');
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
    const reply = answerFor(text, offers);
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

  return (
    <div className="mh-assistant" dir="rtl">
      {open && <section className="mh-assistant-panel">
        <header><div><strong>مساعد ماركوز هوم</strong><small>المعلومات متصلة بلوحة التحكم</small></div><button onClick={() => setOpen(false)} aria-label="إغلاق">×</button></header>
        <div className="mh-assistant-messages">
          {messages.map((m, i) => <div key={i} className={`mh-msg ${m.role}`}>{m.text}</div>)}
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
