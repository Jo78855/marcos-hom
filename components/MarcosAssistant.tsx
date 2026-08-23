import React, { useMemo, useRef, useState } from 'react';

type Msg = { role: 'assistant' | 'user'; text: string };

const DESIGN_198 = {
  name: 'تصميم 198',
  height: '2.90 متر',
  tiers: [
    { width: 'من 3 إلى 3.5 متر', without: 130, with: 170, parts: 'طاولة 2.5 متر + كبت + 3 ألواح فوم بورد' },
    { width: 'من 3.5 إلى 4.5 متر', without: 150, with: 198, parts: 'طاولة 3 متر + كبت + 4 ألواح فوم بورد' },
    { width: 'من 4.6 إلى 5.5 متر', without: 160, with: 210, parts: 'تنفيذ حسب المقاس ضمن مكونات التصميم المعتمدة' },
  ],
};

function answerFor(text: string) {
  const q = text.replace(/[؟?]/g, '').trim();
  if (/مكون|يتكون|فيه ايه|فيه إيه|تفاصيل/.test(q)) {
    return `تصميم 198 ارتفاعه ${DESIGN_198.height}. للمقاس من 3 إلى 3.5 متر: ${DESIGN_198.tiers[0].parts}. وللمقاس من 3.5 إلى 4.5 متر: ${DESIGN_198.tiers[1].parts}. قول لي عرض الحائط وأنا أحدد لك الفئة المناسبة.`;
  }
  if (/سعر|كام|بكام|تكلف/.test(q)) {
    return 'الأسعار حسب عرض الحائط: من 3 إلى 3.5 متر: 130 د.ك بدون تركيب أو 170 د.ك مع التركيب. من 3.5 إلى 4.5 متر: 150 د.ك بدون تركيب أو 198 د.ك مع التركيب. من 4.6 إلى 5.5 متر: 160 د.ك بدون تركيب أو 210 د.ك مع التركيب.';
  }
  const width = q.match(/(3(?:[.,]\d+)?|4(?:[.,]\d+)?|5(?:[.,]\d+)?)\s*(?:متر|م)?/);
  if (width) {
    const w = Number(width[1].replace(',', '.'));
    if (w >= 3 && w <= 3.5) return `مقاس ${w} متر يدخل في الفئة الأولى: ${DESIGN_198.tiers[0].parts}. السعر 130 د.ك بدون تركيب أو 170 د.ك مع التركيب.`;
    if (w > 3.5 && w <= 4.5) return `مقاس ${w} متر يدخل في الفئة الثانية: ${DESIGN_198.tiers[1].parts}. السعر 150 د.ك بدون تركيب أو 198 د.ك مع التركيب.`;
    if (w > 4.5 && w <= 5.5) return `مقاس ${w} متر يدخل في الفئة الثالثة. السعر 160 د.ك بدون تركيب أو 210 د.ك مع التركيب.`;
    if (w > 5.5) return 'المقاس أكبر من 5.5 متر ويحتاج طلب خاص. أقدر أجمع بياناتك للحجز في المرحلة التالية.';
  }
  return 'أنا مساعد ماركوز هوم. أقدر حالياً أشرح لك مكونات تصميم 198 وأسعاره حسب عرض الحائط. جرّب تقول: مكونات التصميم إيه؟ أو: الحائط 4 متر.';
}

export default function MarcosAssistant() {
  const [open, setOpen] = useState(false);
  const [listening, setListening] = useState(false);
  const [input, setInput] = useState('');
  const [messages, setMessages] = useState<Msg[]>([
    { role: 'assistant', text: 'أهلاً بك في ماركوز هوم. اسألني بصوتك عن مكونات تصميم 198 أو المقاس والسعر.' },
  ]);
  const recognitionRef = useRef<any>(null);
  const speechSupported = useMemo(() => typeof window !== 'undefined' && !!((window as any).SpeechRecognition || (window as any).webkitSpeechRecognition), []);

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
    const reply = answerFor(text);
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
        <header><div><strong>مساعد ماركوز هوم</strong><small>نسخة تجريبية — تصميم 198</small></div><button onClick={() => setOpen(false)} aria-label="إغلاق">×</button></header>
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
