import React, { FormEvent, useEffect, useMemo, useRef, useState } from 'react';
import { supabase } from '../supabase';

type Msg = { role: 'assistant' | 'user'; text: string };
type Offer = { id:number; code:string; name_ar:string; min_width:number; max_width:number|null; height_m:number; price_without_installation:number; price_with_installation:number; components_ar:string; active:boolean; sort_order:number };
type Product = { id:number; code:string; category_ar:string; name_ar:string; aliases_ar:string[]; description_ar:string; price_text_ar:string; details_ar:string; active:boolean; sort_order:number };

const money=(v:number)=>Number(v).toLocaleString('ar-KW',{maximumFractionDigits:2});
const rangeLabel=(o:Offer)=>o.max_width?`من ${o.min_width} إلى ${o.max_width} متر`:`من ${o.min_width} متر فأكثر`;
const offerForWidth=(w:number,offers:Offer[])=>offers.find(o=>w>=Number(o.min_width)&&(o.max_width==null||w<=Number(o.max_width)));
const norm=(s:string)=>s.toLowerCase().replace(/[أإآ]/g,'ا').replace(/ة/g,'ه').replace(/[؟?،,.]/g,' ').replace(/\s+/g,' ').trim();

function productMatch(text:string, products:Product[]){
  const q=norm(text);
  return products.find(p=>[p.name_ar,...(p.aliases_ar||[])].some(a=>q.includes(norm(a))));
}

function naturalLead(kind:'price'|'details'|'general'|'width'='general'){
  const options={
    price:['أكيد.','تمام، بالنسبة للسعر،','حاضر.'],
    details:['أكيد، خليني أوضح لك.','تمام، التفاصيل كالتالي.','حاضر، باختصار.'],
    general:['أكيد.','تمام.','حاضر.'],
    width:['تمام، على المقاس ده،','أكيد، بالنسبة للمقاس ده،','حاضر، المقاس ده يدخل في']
  }[kind];
  return options[Math.floor(Math.random()*options.length)];
}

function answerFor(text:string, offers:Offer[], products:Product[]){
  const q=norm(text);
  const product=productMatch(text,products);

  if(product){
    const wantsPrice=/سعر|كام|بكام|تكلف/.test(q);
    const wantsDetails=/تفاصيل|مكون|يتكون|مقاس|الوان|خامة|خامات/.test(q);
    if(wantsPrice) return `${naturalLead('price')} ${product.name_ar} ${product.price_text_ar}. ${product.details_ar||'ولو تحب أشرح لك المكونات أو أسجل لك طلب.'}`;
    if(wantsDetails) return `${naturalLead('details')} ${product.description_ar}. ${product.price_text_ar?`والسعر الحالي ${product.price_text_ar}.`:''} ${product.details_ar||''}`.trim();
    return `${naturalLead()} ${product.name_ar}: ${product.description_ar}. ${product.price_text_ar?`وسعره الحالي ${product.price_text_ar}.`:''} لو تحب، أقول لك المقاسات أو أسجل لك طلب.`;
  }

  const width=text.match(/(\d+(?:[.,]\d+)?)\s*(?:متر|م\b)/);
  if(width&&offers.length){
    const w=Number(width[1].replace(',','.'));
    const match=offerForWidth(w,offers);
    if(match) return `${naturalLead('width')} لو عرض الحائط ${w} متر، الفئة المناسبة ${rangeLabel(match)}. التصميم يشمل ${match.components_ar}. السعر ${money(match.price_without_installation)} دينار بدون تركيب، أو ${money(match.price_with_installation)} دينار مع التركيب. ولو تحب أكمل معاك للحجز.`;
    if(w>Math.max(...offers.map(o=>Number(o.max_width||o.min_width)))) return 'المقاس أكبر من الفئات الجاهزة عندنا، لكن مفيش مشكلة. أسجل لك الطلب كطلب خاص، والفريق يتواصل معاك بالتسعير المناسب.';
  }

  if(/عندكم|ايه المنتجات|ايه الخدمات|الخدمات|المنتجات|بتبيعوا ايه|بتعملوا ايه/.test(q)){
    const names=products.slice(0,8).map(p=>p.name_ar).join('، ');
    return `أكيد. عندنا ${names}. قول لي بس إيه اللي مهتم بيه، وأنا أقول لك السعر والتفاصيل مباشرة.`;
  }

  if(/سعر|كام|بكام|تكلف/.test(q)&&offers.length){
    return `أكيد. لو تقصد تصميم 198 فالسعر بيتحدد حسب عرض الحائط. قول لي العرض بالمتر، وأنا أطلع لك السعر مباشرة. ولو تقصد منتج تاني، اذكر اسمه بس.`;
  }

  return 'تمام. قول لي إنت محتاج إيه بالضبط: خلفية شاشة، ركن قهوة، طاولة، أعمدة، فوم بورد أو جهاز الفير؟ وأنا أساعدك بالسعر والتفاصيل خطوة بخطوة.';
}

function pickArabicVoice(){
  if(!('speechSynthesis' in window)) return undefined;
  const voices=window.speechSynthesis.getVoices();
  const arabic=voices.filter(v=>/^ar([-_]|$)/i.test(v.lang));
  if(!arabic.length) return undefined;
  const score=(v:SpeechSynthesisVoice)=>{
    const n=(v.name+' '+v.lang).toLowerCase();
    let s=0;
    if(/ar-kw|kuwait/.test(n)) s+=8;
    if(/ar-sa|saudi/.test(n)) s+=6;
    if(/ar-eg|egypt/.test(n)) s+=4;
    if(/hamed|maged|tarik|naayf|male/.test(n)) s+=3;
    if(/natural|neural|online/.test(n)) s+=2;
    return s;
  };
  return [...arabic].sort((a,b)=>score(b)-score(a))[0];
}

export default function MarcosAssistant({embedded=false}:{embedded?:boolean}){
  const [open,setOpen]=useState(embedded),[listening,setListening]=useState(false),[input,setInput]=useState('');
  const [offers,setOffers]=useState<Offer[]>([]),[products,setProducts]=useState<Product[]>([]);
  const [bookingOpen,setBookingOpen]=useState(false),[bookingBusy,setBookingBusy]=useState(false),[bookingNotice,setBookingNotice]=useState('');
  const [customerName,setCustomerName]=useState(''),[customerPhone,setCustomerPhone]=useState(''),[area,setArea]=useState(''),[wallWidth,setWallWidth]=useState('');
  const [installation,setInstallation]=useState(true);
  const [messages,setMessages]=useState<Msg[]>([{role:'assistant',text:'أهلاً وسهلاً في ماركوز هوم. قول لي إنت محتاج إيه، وأنا أساعدك في السعر والمقاس والتفاصيل.'}]);
  const recognitionRef=useRef<any>(null);
  const speechSupported=useMemo(()=>typeof window!=='undefined'&&!!((window as any).SpeechRecognition||(window as any).webkitSpeechRecognition),[]);

  useEffect(()=>{(async()=>{
    const [o,p]=await Promise.all([
      supabase.from('assistant_offers').select('*').eq('active',true).order('sort_order'),
      supabase.from('assistant_products').select('*').eq('active',true).order('sort_order')
    ]);
    if(o.data)setOffers(o.data as Offer[]);
    if(p.data)setProducts(p.data as Product[]);
  })();},[]);

  const speak=(text:string)=>{
    if(!('speechSynthesis' in window))return;
    window.speechSynthesis.cancel();
    const voice=pickArabicVoice();
    const parts=text.split(/(?<=[.!؟])\s+/).filter(Boolean);
    parts.forEach((part,index)=>{
      const u=new SpeechSynthesisUtterance(part);
      u.lang=voice?.lang||'ar-KW';
      if(voice)u.voice=voice;
      u.rate=.88;
      u.pitch=.94;
      u.volume=1;
      if(index>0) u.rate=.86;
      window.speechSynthesis.speak(u);
    });
  };

  const submit=(raw=input)=>{
    const text=raw.trim();
    if(!text)return;
    let reply:string;
    if(/احجز|حجز|معاينه|معاينة|اطلب|طلب/.test(norm(text))){
      setBookingOpen(true);
      reply='أكيد. فتحت لك تسجيل الطلب. اكتب الاسم ورقم الهاتف والمنطقة وعرض الحائط، وأنا أكمل معاك.';
    }else reply=answerFor(text,offers,products);
    setMessages(v=>[...v,{role:'user',text},{role:'assistant',text:reply}]);
    setInput('');
    speak(reply);
  };

  const startVoice=()=>{
    const SR=(window as any).SpeechRecognition||(window as any).webkitSpeechRecognition;
    if(!SR)return;
    const r=new SR();
    r.lang='ar-KW';
    r.interimResults=false;
    r.continuous=false;
    r.onstart=()=>setListening(true);
    r.onend=()=>setListening(false);
    r.onerror=()=>setListening(false);
    r.onresult=(e:any)=>{const t=e.results?.[0]?.[0]?.transcript||'';setInput(t);submit(t)};
    recognitionRef.current=r;
    r.start();
  };

  const submitBooking=async(e:FormEvent)=>{
    e.preventDefault();
    setBookingNotice('');
    const width=Number(wallWidth);
    if(!customerName.trim()||!customerPhone.trim()||!area.trim()||!Number.isFinite(width)||width<=0){setBookingNotice('كمل الاسم ورقم الهاتف والمنطقة وعرض الحائط، وأنا أسجل الطلب.');return}
    const offer=offerForWidth(width,offers);
    const total=offer?Number(installation?offer.price_with_installation:offer.price_without_installation):0;
    setBookingBusy(true);
    const{error}=await supabase.from('orders').insert({customer_name:customerName.trim(),customer_phone:customerPhone.trim(),design_id:null,installation,total,status:'new',area:area.trim(),wall_width:width,source:'voice_assistant'});
    setBookingBusy(false);
    if(error){setBookingNotice(`تعذر تسجيل الطلب: ${error.message}`);return}
    const reply=total>0?`تمام، تم تسجيل طلبك. السعر المبدئي ${money(total)} دينار ${installation?'مع التركيب':'بدون تركيب'}. فريق ماركوز هوم هيتواصل معاك لتأكيد التفاصيل.`:'تمام، تم تسجيل طلبك كطلب خاص. فريق ماركوز هوم هيتواصل معاك علشان التسعير والتفاصيل.';
    setBookingNotice(reply);
    setMessages(v=>[...v,{role:'assistant',text:reply}]);
    speak(reply);
    setCustomerName('');setCustomerPhone('');setArea('');setWallWidth('');
  };

  return <div className={embedded?'mh-assistant mh-assistant-embedded':'mh-assistant'} dir="rtl">
    {open&&<section className="mh-assistant-panel"><header><div><strong>مساعد ماركوز هوم</strong><small>اسأل بصوتك أو اكتب سؤالك</small></div>{!embedded&&<button onClick={()=>setOpen(false)} aria-label="إغلاق">×</button>}</header>
      <div className="mh-assistant-messages">{messages.map((m,i)=><div key={i} className={`mh-msg ${m.role}`}>{m.text}</div>)}
      {bookingOpen&&<form className="mh-booking" onSubmit={submitBooking}><strong>تسجيل طلب / معاينة</strong><input value={customerName} onChange={e=>setCustomerName(e.target.value)} placeholder="الاسم"/><input value={customerPhone} onChange={e=>setCustomerPhone(e.target.value)} inputMode="tel" placeholder="رقم الهاتف"/><input value={area} onChange={e=>setArea(e.target.value)} placeholder="المنطقة"/><input value={wallWidth} onChange={e=>setWallWidth(e.target.value)} inputMode="decimal" placeholder="عرض الحائط بالمتر"/><div className="mh-booking-options"><button type="button" className={!installation?'active':''} onClick={()=>setInstallation(false)}>بدون تركيب</button><button type="button" className={installation?'active':''} onClick={()=>setInstallation(true)}>مع التركيب</button></div><button className="mh-booking-submit" disabled={bookingBusy}>{bookingBusy?'جاري التسجيل...':'تسجيل الطلب'}</button>{bookingNotice&&<small>{bookingNotice}</small>}</form>}
      </div>
      <div className="mh-assistant-input"><button className={listening?'mic listening':'mic'} onClick={startVoice} disabled={!speechSupported}>{listening?'●':'🎙'}</button><input value={input} onChange={e=>setInput(e.target.value)} onKeyDown={e=>e.key==='Enter'&&submit()} placeholder="اتكلم أو اكتب سؤالك"/><button onClick={()=>submit()}>إرسال</button></div>
      {!speechSupported&&<p className="mh-assistant-note">يمكنك تجربة المحادثة بالكتابة حالياً على هذا المتصفح.</p>}
    </section>}
    {!embedded&&<button className="mh-assistant-launcher" onClick={()=>setOpen(v=>!v)} aria-label="مساعد ماركوز هوم">🎙<span>اسأل ماركوز هوم</span></button>}
  </div>
}