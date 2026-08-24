import React,{FormEvent,useEffect,useMemo,useRef,useState}from'react';
import{supabase}from'../supabase';

type Msg={role:'assistant'|'user';text:string};
type Offer={id:number;code:string;name_ar:string;min_width:number;max_width:number|null;height_m:number;price_without_installation:number;price_with_installation:number;components_ar:string;active:boolean;sort_order:number};
type Product={id:number;code:string;category_ar:string;name_ar:string;aliases_ar:string[];description_ar:string;price_text_ar:string;details_ar:string;active:boolean;sort_order:number};

const money=(v:number)=>Number(v).toLocaleString('ar-KW',{maximumFractionDigits:2});
const rangeLabel=(o:Offer)=>o.max_width?`من ${o.min_width} إلى ${o.max_width} متر`:`من ${o.min_width} متر فأكثر`;
const offerForWidth=(w:number,offers:Offer[])=>offers.find(o=>w>=Number(o.min_width)&&(o.max_width==null||w<=Number(o.max_width)));
const norm=(s:string)=>s.toLowerCase().replace(/[أإآ]/g,'ا').replace(/ة/g,'ه').replace(/[؟?،,.]/g,' ').replace(/\s+/g,' ').trim();
const productMatch=(text:string,products:Product[])=>{const q=norm(text);return products.find(p=>[p.name_ar,...(p.aliases_ar||[])].some(a=>q.includes(norm(a))))};
const opener=()=>['أكيد.','تمام.','طبعًا.'][Math.floor(Math.random()*3)];

function answerFor(text:string,offers:Offer[],products:Product[]){
  const q=norm(text),product=productMatch(text,products);
  if(product){
    const price=/سعر|كام|بكام|تكلف/.test(q),details=/تفاصيل|مكون|يتكون|مقاس|الوان|خامة|خامات/.test(q);
    if(price)return`${opener()} ${product.price_text_ar}${product.details_ar?`. ${product.details_ar}`:''}`;
    if(details)return`${opener()} ${product.description_ar}${product.price_text_ar?` وبالنسبة للسعر، ${product.price_text_ar}.`:''}${product.details_ar?` ${product.details_ar}`:''}`;
    return`${opener()} ${product.description_ar}${product.price_text_ar?` وسعره الحالي ${product.price_text_ar}.`:''}`;
  }
  const width=text.match(/(\d+(?:[.,]\d+)?)\s*(?:متر|م\b)/);
  if(width&&offers.length){
    const w=Number(width[1].replace(',','.')),m=offerForWidth(w,offers);
    if(m)return`${opener()} لو عرض الحائط ${w} متر، الفئة المناسبة ${rangeLabel(m)}. السعر ${money(m.price_without_installation)} دينار بدون تركيب، أو ${money(m.price_with_installation)} دينار مع التركيب. ولو تحب أقول لك المكونات كمان.`;
    if(w>Math.max(...offers.map(o=>Number(o.max_width||o.min_width))))return'المقاس ده أكبر من الفئات الجاهزة عندنا، فالأفضل نسجله كطلب خاص ونرجع لك بالتسعير المناسب.';
  }
  if(/عندكم|ايه المنتجات|ايه الخدمات|الخدمات|المنتجات|بتبيعوا ايه|بتعملوا ايه/.test(q))return`أكيد. عندنا ${products.slice(0,8).map(p=>p.name_ar).join('، ')}. قول لي إيه اللي مهتم بيه وأنا أقول لك السعر والتفاصيل.`;
  if(/سعر|كام|بكام|تكلف/.test(q)&&offers.length)return`لو تقصد تصميم 198، السعر بيتحدد حسب عرض الحائط. قول لي العرض كام متر وأنا أحسبه لك مباشرة.`;
  return'أكيد. اسألني عن أي تصميم أو منتج عند ماركوز هوم، أو قول لي مقاس الحائط، وأنا أساعدك بالسعر والتفاصيل. ولو حاب تحجز معاينة أقدر أسجلها لك.';
}

function catalogInstructions(offers:Offer[],products:Product[]){
  const productsText=products.map(p=>`${p.name_ar}: ${p.description_ar}. السعر: ${p.price_text_ar}. ${p.details_ar||''}`).join('\n');
  const offersText=offers.map(o=>`${o.name_ar} ${rangeLabel(o)}: ${o.components_ar}. بدون تركيب ${o.price_without_installation} د.ك، مع التركيب ${o.price_with_installation} د.ك.`).join('\n');
  return `أنت مساعد ماركوز هوم الصوتي. تحدث بالعربية الخليجية بلهجة كويتية خفيفة ومفهومة، بصوت طبيعي ودافئ وهادئ، مثل موظف مبيعات محترف داخل المعرض وليس مذيعًا. اجعل الجمل قصيرة وسلسة ولا تكرر الترحيب. أجب مباشرة عن السعر أو المقاس. لا تخترع أي سعر أو معلومة. إذا لم تجد المعلومة قل إنك تستطيع تسجيل طلب ليتواصل الفريق مع العميل.\n\nهذه هي معلومات ماركوز هوم الحالية التي يجب الالتزام بها:\n${productsText}\n${offersText}`;
}

export default function MarcosAssistant({embedded=false}:{embedded?:boolean}){
  const[open,setOpen]=useState(embedded),[listening,setListening]=useState(false),[input,setInput]=useState('');
  const[offers,setOffers]=useState<Offer[]>([]),[products,setProducts]=useState<Product[]>([]);
  const[bookingOpen,setBookingOpen]=useState(false),[bookingBusy,setBookingBusy]=useState(false),[bookingNotice,setBookingNotice]=useState('');
  const[customerName,setCustomerName]=useState(''),[customerPhone,setCustomerPhone]=useState(''),[area,setArea]=useState(''),[wallWidth,setWallWidth]=useState('');
  const[installation,setInstallation]=useState(true);
  const[messages,setMessages]=useState<Msg[]>([{role:'assistant',text:'أهلاً وسهلاً بك في ماركوز هوم. تقدر تكتب، تستخدم الميكروفون، أو تبدأ محادثة صوتية مباشرة.'}]);
  const[realtimeState,setRealtimeState]=useState<'idle'|'connecting'|'connected'|'error'>('idle');
  const recognitionRef=useRef<any>(null),audioRef=useRef<HTMLAudioElement|null>(null),pcRef=useRef<RTCPeerConnection|null>(null),streamRef=useRef<MediaStream|null>(null),dcRef=useRef<RTCDataChannel|null>(null);
  const speechSupported=useMemo(()=>typeof window!=='undefined'&&!!((window as any).SpeechRecognition||(window as any).webkitSpeechRecognition),[]);

  useEffect(()=>{(async()=>{const[o,p]=await Promise.all([supabase.from('assistant_offers').select('*').eq('active',true).order('sort_order'),supabase.from('assistant_products').select('*').eq('active',true).order('sort_order')]);if(o.data)setOffers(o.data as Offer[]);if(p.data)setProducts(p.data as Product[])})()},[]);
  useEffect(()=>()=>stopRealtime(),[]);

  const browserSpeak=(text:string)=>{if(!('speechSynthesis'in window))return;window.speechSynthesis.cancel();const u=new SpeechSynthesisUtterance(text),voices=window.speechSynthesis.getVoices();u.voice=voices.find(v=>/^ar(-|_)/i.test(v.lang))||null;u.lang='ar-KW';u.rate=.92;window.speechSynthesis.speak(u)};
  const speak=async(text:string)=>{try{if(audioRef.current){audioRef.current.pause();audioRef.current=null}const{data,error}=await supabase.functions.invoke('marcos-tts',{body:{text}});if(error||!(data instanceof Blob))throw error||new Error('No audio');const url=URL.createObjectURL(data),audio=new Audio(url);audioRef.current=audio;audio.onended=()=>{URL.revokeObjectURL(url);audioRef.current=null};await audio.play()}catch{browserSpeak(text)}};

  const stopRealtime=()=>{
    try{dcRef.current?.close()}catch{}
    try{pcRef.current?.close()}catch{}
    streamRef.current?.getTracks().forEach(t=>t.stop());
    dcRef.current=null;pcRef.current=null;streamRef.current=null;
    setRealtimeState('idle');
  };

  const startRealtime=async()=>{
    if(realtimeState==='connected'||realtimeState==='connecting'){stopRealtime();return}
    setRealtimeState('connecting');
    try{
      const{data,error}=await supabase.functions.invoke('marcos-realtime-token',{body:{}});
      if(error)throw error;
      const key=(data as any)?.value||(data as any)?.client_secret?.value||(data as any)?.client_secret;
      if(!key||typeof key!=='string')throw new Error('لم يتم استلام مفتاح الجلسة');

      const pc=new RTCPeerConnection();pcRef.current=pc;
      const remoteAudio=document.createElement('audio');remoteAudio.autoplay=true;remoteAudio.setAttribute('playsinline','true');
      pc.ontrack=e=>{remoteAudio.srcObject=e.streams[0];void remoteAudio.play().catch(()=>{})};

      const stream=await navigator.mediaDevices.getUserMedia({audio:true});streamRef.current=stream;
      stream.getTracks().forEach(track=>pc.addTrack(track,stream));

      const dc=pc.createDataChannel('oai-events');dcRef.current=dc;
      dc.onopen=()=>{
        setRealtimeState('connected');
        dc.send(JSON.stringify({type:'session.update',session:{instructions:catalogInstructions(offers,products),audio:{output:{voice:'marin'}}}}));
      };
      dc.onclose=()=>setRealtimeState('idle');
      dc.onerror=()=>setRealtimeState('error');
      dc.onmessage=e=>{
        try{
          const evt=JSON.parse(e.data);
          if(evt.type==='conversation.item.input_audio_transcription.completed'&&evt.transcript){setMessages(v=>[...v,{role:'user',text:evt.transcript}])}
          if((evt.type==='response.output_audio_transcript.done'||evt.type==='response.output_text.done')&&(evt.transcript||evt.text)){setMessages(v=>[...v,{role:'assistant',text:evt.transcript||evt.text}])}
        }catch{}
      };

      const offer=await pc.createOffer();await pc.setLocalDescription(offer);
      const sdpResponse=await fetch('https://api.openai.com/v1/realtime/calls',{method:'POST',body:offer.sdp||'',headers:{Authorization:`Bearer ${key}`,'Content-Type':'application/sdp'}});
      if(!sdpResponse.ok)throw new Error(`Realtime ${sdpResponse.status}`);
      const answer={type:'answer' as RTCSdpType,sdp:await sdpResponse.text()};await pc.setRemoteDescription(answer);
    }catch(err){console.error('Realtime voice failed',err);stopRealtime();setRealtimeState('error');setTimeout(()=>setRealtimeState('idle'),3000)}
  };

  const submit=(raw=input)=>{const text=raw.trim();if(!text)return;let reply:string;if(/احجز|حجز|معاينه|معاينة|اطلب|طلب/.test(norm(text))){setBookingOpen(true);reply='تمام. خلينا نسجل طلبك. اكتب الاسم ورقم الهاتف والمنطقة وعرض الحائط، وأنا أسجله لك مباشرة.'}else reply=answerFor(text,offers,products);setMessages(v=>[...v,{role:'user',text},{role:'assistant',text:reply}]);setInput('');void speak(reply)};
  const startVoice=()=>{const SR=(window as any).SpeechRecognition||(window as any).webkitSpeechRecognition;if(!SR)return;const r=new SR();r.lang='ar-KW';r.interimResults=false;r.continuous=false;r.onstart=()=>setListening(true);r.onend=()=>setListening(false);r.onerror=()=>setListening(false);r.onresult=(e:any)=>{const t=e.results?.[0]?.[0]?.transcript||'';setInput(t);submit(t)};recognitionRef.current=r;r.start()};

  const submitBooking=async(e:FormEvent)=>{e.preventDefault();setBookingNotice('');const width=Number(wallWidth);if(!customerName.trim()||!customerPhone.trim()||!area.trim()||!Number.isFinite(width)||width<=0){setBookingNotice('أكمل الاسم ورقم الهاتف والمنطقة وعرض الحائط.');return}const offer=offerForWidth(width,offers),total=offer?Number(installation?offer.price_with_installation:offer.price_without_installation):0;setBookingBusy(true);const{error}=await supabase.from('orders').insert({customer_name:customerName.trim(),customer_phone:customerPhone.trim(),design_id:null,installation,total,status:'new',area:area.trim(),wall_width:width,source:'voice_assistant'});setBookingBusy(false);if(error){setBookingNotice(`تعذر تسجيل الطلب: ${error.message}`);return}const reply=total>0?`تمام، تم تسجيل طلبك. السعر المبدئي ${money(total)} دينار ${installation?'مع التركيب':'بدون تركيب'}. وفريق ماركوز هوم هيتواصل معك.`:'تمام، تم تسجيل طلبك الخاص، وفريق ماركوز هوم هيتواصل معك للتسعير.';setBookingNotice(reply);setMessages(v=>[...v,{role:'assistant',text:reply}]);void speak(reply);setCustomerName('');setCustomerPhone('');setArea('');setWallWidth('')};

  const rtLabel=realtimeState==='connecting'?'جاري الاتصال...':realtimeState==='connected'?'إنهاء المحادثة الصوتية':realtimeState==='error'?'تعذر الاتصال — جرّب مرة أخرى':'محادثة صوتية مباشرة';

  return <div className={embedded?'mh-assistant mh-assistant-embedded':'mh-assistant'} dir="rtl">
    {open&&<section className="mh-assistant-panel"><header><div><strong>مساعد ماركوز هوم</strong><small>صوت مباشر + الأسعار والمعلومات الحالية</small></div>{!embedded&&<button onClick={()=>setOpen(false)} aria-label="إغلاق">×</button>}</header>
      <div className="mh-realtime-bar"><button className={`mh-realtime-button ${realtimeState}`} onClick={()=>void startRealtime()} disabled={realtimeState==='connecting'}>🎧 {rtLabel}</button>{realtimeState==='connected'&&<small>اتكلم طبيعي — المساعد يسمعك ويرد عليك مباشرة.</small>}</div>
      <div className="mh-assistant-messages">{messages.map((m,i)=><div key={i} className={`mh-msg ${m.role}`}>{m.text}</div>)}{bookingOpen&&<form className="mh-booking" onSubmit={submitBooking}><strong>تسجيل طلب / معاينة</strong><input value={customerName} onChange={e=>setCustomerName(e.target.value)} placeholder="الاسم"/><input value={customerPhone} onChange={e=>setCustomerPhone(e.target.value)} inputMode="tel" placeholder="رقم الهاتف"/><input value={area} onChange={e=>setArea(e.target.value)} placeholder="المنطقة"/><input value={wallWidth} onChange={e=>setWallWidth(e.target.value)} inputMode="decimal" placeholder="عرض الحائط بالمتر"/><div className="mh-booking-options"><button type="button" className={!installation?'active':''} onClick={()=>setInstallation(false)}>بدون تركيب</button><button type="button" className={installation?'active':''} onClick={()=>setInstallation(true)}>مع التركيب</button></div><button className="mh-booking-submit" disabled={bookingBusy}>{bookingBusy?'جاري التسجيل...':'تسجيل الطلب'}</button>{bookingNotice&&<small>{bookingNotice}</small>}</form>}</div>
      <div className="mh-assistant-input"><button className={listening?'mic listening':'mic'} onClick={startVoice} disabled={!speechSupported||realtimeState==='connected'}>{listening?'●':'🎙'}</button><input value={input} onChange={e=>setInput(e.target.value)} onKeyDown={e=>e.key==='Enter'&&submit()} placeholder="اتكلم أو اكتب سؤالك"/><button onClick={()=>submit()}>إرسال</button></div>{!speechSupported&&<p className="mh-assistant-note">يمكنك استخدام المحادثة الصوتية المباشرة أو الكتابة.</p>}</section>}
    {!embedded&&<button className="mh-assistant-launcher" onClick={()=>setOpen(v=>!v)} aria-label="مساعد ماركوز هوم">🎙<span>اسأل ماركوز هوم</span></button>}
  </div>
}
