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
    if(m)return`${opener()} لو عرض الحائط ${w} متر، الفئة المناسبة ${rangeLabel(m)}. السعر ${money(m.price_without_installation)} دينار بدون تركيب، أو ${money(m.price_with_installation)} دينار مع التركيب. ولتأكيد الملاءمة ابعت صورة واضحة للحائط، ولو تعرف الارتفاع اكتبه كمان.`;
    if(w>Math.max(...offers.map(o=>Number(o.max_width||o.min_width))))return'المقاس أكبر من الفئات الجاهزة. ابعت عرض وارتفاع الحائط وصورة واضحة للمكان، ونسجله كطلب خاص للتسعير بدون الحاجة لمعاينة مبدئيًا.';
  }
  if(/معاينه|معاينة|زيارة|يجى|يجي|مهندس/.test(q))return'حاليًا بنحاول ننجز التقييم بدون معاينة قدر الإمكان. ابعت عرض الحائط، الارتفاع لو متاح، وصورة واضحة للمكان، ومعاهم المنطقة ورقم التواصل. غالبًا نقدر نحدد المناسب مبدئيًا من البيانات دي.';
  if(/صوره|صورة|اصور|أصور/.test(q))return'تمام. صوّر الحائط كامل من الأمام قدر الإمكان، وخلي الأرضية والسقف ظاهرين في الصورة. ولو في فيش كهرباء أو مكيف أو باب قريب خليه ظاهر. بعدها ارفع الصورة مع المقاسات.';
  if(/عندكم|ايه المنتجات|ايه الخدمات|الخدمات|المنتجات|بتبيعوا ايه|بتعملوا ايه/.test(q))return`أكيد. عندنا ${products.slice(0,8).map(p=>p.name_ar).join('، ')}. قول لي إيه اللي مهتم بيه وأنا أقول لك السعر والتفاصيل.`;
  if(/سعر|كام|بكام|تكلف/.test(q)&&offers.length)return`لو تقصد تصميم 198، السعر بيتحدد حسب عرض الحائط. قول لي العرض كام متر، ولو تقدر ابعت صورة للحائط، وأنا أحدد لك الفئة المناسبة.`;
  return'أكيد. اسألني عن أي تصميم أو منتج عند ماركوز هوم. ولو عايز نحدد المناسب لمكانك، ابعت عرض الحائط وصورة واضحة للمكان، والارتفاع لو متاح، وأنا أساعدك قبل التفكير في أي معاينة.';
}

function catalogInstructions(offers:Offer[],products:Product[]){
  const productsText=products.map(p=>`${p.name_ar}: ${p.description_ar}. السعر: ${p.price_text_ar}. ${p.details_ar||''}`).join('\n');
  const offersText=offers.map(o=>`${o.name_ar} ${rangeLabel(o)}: ${o.components_ar}. بدون تركيب ${o.price_without_installation} د.ك، مع التركيب ${o.price_with_installation} د.ك.`).join('\n');
  return `أنت مساعد ماركوز هوم الصوتي. تحدث بالعربية الخليجية بلهجة كويتية خفيفة ومفهومة، بصوت طبيعي ودافئ وهادئ، مثل موظف مبيعات محترف داخل المعرض وليس مذيعًا. اجعل الجمل قصيرة وسلسة ولا تكرر الترحيب. أجب مباشرة عن السعر أو المقاس. لا تخترع أي سعر أو معلومة.
سياسة مهمة: المعاينات الميدانية غير متاحة حاليًا إلا للضرورة القصوى. قبل اقتراح أي معاينة، اطلب من العميل عرض الحائط، والارتفاع التقريبي إن أمكن، وصورة واضحة للمكان من الأمام يظهر فيها السقف والأرضية والعوائق مثل المكيف والباب والفيش، ثم اطلب المنطقة ورقم التواصل. حاول الوصول لتقييم وتسعير مبدئي من المقاسات والصورة أولًا. لا تعد العميل بمعاينة. قل إن الفريق يراجع الصور والمقاسات ويتواصل عند الحاجة.
إذا سأل العميل كيف يصور المكان، اطلب صورة واسعة من الأمام وصورة إضافية جانبية عند وجود عائق. إذا لم يجد المعلومة، اطلب تسجيل بياناته وصورته بدل إنهاء المحادثة.

هذه هي معلومات ماركوز هوم الحالية التي يجب الالتزام بها:\n${productsText}\n${offersText}`;
}

export default function MarcosAssistant({embedded=false}:{embedded?:boolean}){
  const[open,setOpen]=useState(embedded),[listening,setListening]=useState(false),[input,setInput]=useState('');
  const[offers,setOffers]=useState<Offer[]>([]),[products,setProducts]=useState<Product[]>([]);
  const[bookingOpen,setBookingOpen]=useState(false),[bookingBusy,setBookingBusy]=useState(false),[bookingNotice,setBookingNotice]=useState('');
  const[customerName,setCustomerName]=useState(''),[customerPhone,setCustomerPhone]=useState(''),[area,setArea]=useState(''),[wallWidth,setWallWidth]=useState(''),[wallHeight,setWallHeight]=useState(''),[customerNotes,setCustomerNotes]=useState('');
  const[placePhoto,setPlacePhoto]=useState<File|null>(null);
  const[installation,setInstallation]=useState(true);
  const[messages,setMessages]=useState<Msg[]>([{role:'assistant',text:'أهلاً وسهلاً بك في ماركوز هوم. تقدر تسأل عن الأسعار والتصميمات، ولو عايز نحدد المناسب لمكانك ابعت المقاس وصورة الحائط.'}]);
  const[realtimeState,setRealtimeState]=useState<'idle'|'connecting'|'connected'|'error'>('idle');
  const recognitionRef=useRef<any>(null),audioRef=useRef<HTMLAudioElement|null>(null),pcRef=useRef<RTCPeerConnection|null>(null),streamRef=useRef<MediaStream|null>(null),dcRef=useRef<RTCDataChannel|null>(null);
  const speechSupported=useMemo(()=>typeof window!=='undefined'&&!!((window as any).SpeechRecognition||(window as any).webkitSpeechRecognition),[]);

  useEffect(()=>{(async()=>{const[o,p]=await Promise.all([supabase.from('assistant_offers').select('*').eq('active',true).order('sort_order'),supabase.from('assistant_products').select('*').eq('active',true).order('sort_order')]);if(o.data)setOffers(o.data as Offer[]);if(p.data)setProducts(p.data as Product[])})()},[]);
  useEffect(()=>()=>stopRealtime(),[]);

  const browserSpeak=(text:string)=>{if(!('speechSynthesis'in window))return;window.speechSynthesis.cancel();const u=new SpeechSynthesisUtterance(text),voices=window.speechSynthesis.getVoices();u.voice=voices.find(v=>/^ar(-|_)/i.test(v.lang))||null;u.lang='ar-KW';u.rate=.92;window.speechSynthesis.speak(u)};
  const speak=async(text:string)=>{try{if(audioRef.current){audioRef.current.pause();audioRef.current=null}const{data,error}=await supabase.functions.invoke('marcos-tts',{body:{text}});if(error||!(data instanceof Blob))throw error||new Error('No audio');const url=URL.createObjectURL(data),audio=new Audio(url);audioRef.current=audio;audio.onended=()=>{URL.revokeObjectURL(url);audioRef.current=null};await audio.play()}catch{browserSpeak(text)}};

  const stopRealtime=()=>{try{dcRef.current?.close()}catch{}try{pcRef.current?.close()}catch{}streamRef.current?.getTracks().forEach(t=>t.stop());dcRef.current=null;pcRef.current=null;streamRef.current=null;setRealtimeState('idle')};
  const startRealtime=async()=>{
    if(realtimeState==='connected'||realtimeState==='connecting'){stopRealtime();return}
    setRealtimeState('connecting');
    try{
      const{data,error}=await supabase.functions.invoke('marcos-realtime-token',{body:{}});if(error)throw error;
      const key=(data as any)?.value||(data as any)?.client_secret?.value||(data as any)?.client_secret;if(!key||typeof key!=='string')throw new Error('لم يتم استلام مفتاح الجلسة');
      const pc=new RTCPeerConnection();pcRef.current=pc;const remoteAudio=document.createElement('audio');remoteAudio.autoplay=true;remoteAudio.setAttribute('playsinline','true');pc.ontrack=e=>{remoteAudio.srcObject=e.streams[0];void remoteAudio.play().catch(()=>{})};
      const stream=await navigator.mediaDevices.getUserMedia({audio:true});streamRef.current=stream;stream.getTracks().forEach(track=>pc.addTrack(track,stream));
      const dc=pc.createDataChannel('oai-events');dcRef.current=dc;dc.onopen=()=>{setRealtimeState('connected');dc.send(JSON.stringify({type:'session.update',session:{instructions:catalogInstructions(offers,products),audio:{output:{voice:'marin'}}}}))};dc.onclose=()=>setRealtimeState('idle');dc.onerror=()=>setRealtimeState('error');dc.onmessage=e=>{try{const evt=JSON.parse(e.data);if(evt.type==='conversation.item.input_audio_transcription.completed'&&evt.transcript)setMessages(v=>[...v,{role:'user',text:evt.transcript}]);if((evt.type==='response.output_audio_transcript.done'||evt.type==='response.output_text.done')&&(evt.transcript||evt.text))setMessages(v=>[...v,{role:'assistant',text:evt.transcript||evt.text}])}catch{}};
      const offer=await pc.createOffer();await pc.setLocalDescription(offer);const sdpResponse=await fetch('https://api.openai.com/v1/realtime/calls',{method:'POST',body:offer.sdp||'',headers:{Authorization:`Bearer ${key}`,'Content-Type':'application/sdp'}});if(!sdpResponse.ok)throw new Error(`Realtime ${sdpResponse.status}`);await pc.setRemoteDescription({type:'answer',sdp:await sdpResponse.text()});
    }catch(err){console.error('Realtime voice failed',err);stopRealtime();setRealtimeState('error');setTimeout(()=>setRealtimeState('idle'),3000)}
  };

  const submit=(raw=input)=>{const text=raw.trim();if(!text)return;let reply:string;if(/احجز|حجز|معاينه|معاينة|زيارة|اطلب|طلب|مقاس|صوره|صورة/.test(norm(text))){setBookingOpen(true);reply='تمام. خلينا نبدأ بالمقاسات والصورة بدل المعاينة. اكتب الاسم ورقم الهاتف والمنطقة وعرض الحائط، ولو تعرف الارتفاع اكتبه، وارفع صورة واضحة للمكان.'}else reply=answerFor(text,offers,products);setMessages(v=>[...v,{role:'user',text},{role:'assistant',text:reply}]);setInput('');void speak(reply)};
  const startVoice=()=>{const SR=(window as any).SpeechRecognition||(window as any).webkitSpeechRecognition;if(!SR)return;const r=new SR();r.lang='ar-KW';r.interimResults=false;r.continuous=false;r.onstart=()=>setListening(true);r.onend=()=>setListening(false);r.onerror=()=>setListening(false);r.onresult=(e:any)=>{const t=e.results?.[0]?.[0]?.transcript||'';setInput(t);submit(t)};recognitionRef.current=r;r.start()};

  const submitBooking=async(e:FormEvent)=>{
    e.preventDefault();setBookingNotice('');const width=Number(wallWidth),height=wallHeight.trim()?Number(wallHeight):null;
    if(!customerName.trim()||!customerPhone.trim()||!area.trim()||!Number.isFinite(width)||width<=0){setBookingNotice('أكمل الاسم ورقم الهاتف والمنطقة وعرض الحائط. الصورة والارتفاع يساعدونا نحدد المناسب بدون معاينة.');return}
    setBookingBusy(true);let photoPath:string|null=null;
    if(placePhoto){const ext=placePhoto.name.split('.').pop()?.toLowerCase()||'jpg';photoPath=`${Date.now()}-${crypto.randomUUID()}.${ext}`;const upload=await supabase.storage.from('customer-places').upload(photoPath,placePhoto,{cacheControl:'3600',upsert:false});if(upload.error){setBookingBusy(false);setBookingNotice(`تعذر رفع الصورة: ${upload.error.message}`);return}}
    const offer=offerForWidth(width,offers),total=offer?Number(installation?offer.price_with_installation:offer.price_without_installation):0;
    const summary=`المنطقة: ${area.trim()} | عرض الحائط: ${width}م${height?` | الارتفاع: ${height}م`:''}${photoPath?' | صورة المكان مرفوعة':' | يحتاج صورة للمكان'}`;
    const{error}=await supabase.from('orders').insert({customer_name:customerName.trim(),customer_phone:customerPhone.trim(),design_id:null,installation,total,status:photoPath?'measurements_received':'needs_photo',area:area.trim(),wall_width:width,wall_height:height,place_photo_path:photoPath,customer_notes:customerNotes.trim()||null,preferred_contact:'whatsapp',assistant_summary:summary,source:'voice_assistant'});
    setBookingBusy(false);if(error){if(photoPath)await supabase.storage.from('customer-places').remove([photoPath]);setBookingNotice(`تعذر تسجيل الطلب: ${error.message}`);return}
    const reply=photoPath?'تمام، تم تسجيل المقاسات والصورة. الفريق هيراجعهم ويحدد المناسب مبدئيًا، ولو احتجنا أي معلومة إضافية هنتواصل معك.':'تم تسجيل المقاسات. الأفضل تبعت صورة واضحة للحائط عشان نقدر نحدد المناسب بدون معاينة قدر الإمكان.';
    setBookingNotice(reply);setMessages(v=>[...v,{role:'assistant',text:reply}]);setCustomerName('');setCustomerPhone('');setArea('');setWallWidth('');setWallHeight('');setCustomerNotes('');setPlacePhoto(null)
  };

  const rtLabel=realtimeState==='connecting'?'جاري الاتصال...':realtimeState==='connected'?'إنهاء المحادثة الصوتية':realtimeState==='error'?'تعذر الاتصال — جرّب مرة أخرى':'محادثة صوتية مباشرة';
  return <div className={embedded?'mh-assistant mh-assistant-embedded':'mh-assistant'} dir="rtl">
    {open&&<section className="mh-assistant-panel"><header><div><strong>مساعد ماركوز هوم</strong><small>صوت مباشر + أسعار + تقييم بالمقاسات والصور</small></div>{!embedded&&<button onClick={()=>setOpen(false)} aria-label="إغلاق">×</button>}</header>
      <div className="mh-realtime-bar"><button className={`mh-realtime-button ${realtimeState}`} onClick={()=>void startRealtime()} disabled={realtimeState==='connecting'}>🎧 {rtLabel}</button>{realtimeState==='connected'&&<small>اتكلم طبيعي — المساعد يسمعك ويرد عليك مباشرة.</small>}</div>
      <div className="mh-assistant-messages">{messages.map((m,i)=><div key={i} className={`mh-msg ${m.role}`}>{m.text}</div>)}{bookingOpen&&<form className="mh-booking" onSubmit={submitBooking}><strong>إرسال المقاسات وصورة المكان</strong><small>نراجع البيانات أولًا بدل المعاينة قدر الإمكان.</small><input value={customerName} onChange={e=>setCustomerName(e.target.value)} placeholder="الاسم"/><input value={customerPhone} onChange={e=>setCustomerPhone(e.target.value)} inputMode="tel" placeholder="رقم الهاتف"/><input value={area} onChange={e=>setArea(e.target.value)} placeholder="المنطقة"/><input value={wallWidth} onChange={e=>setWallWidth(e.target.value)} inputMode="decimal" placeholder="عرض الحائط بالمتر"/><input value={wallHeight} onChange={e=>setWallHeight(e.target.value)} inputMode="decimal" placeholder="ارتفاع الحائط بالمتر — إن أمكن"/><input type="file" accept="image/jpeg,image/png,image/webp" onChange={e=>setPlacePhoto(e.target.files?.[0]||null)}/><textarea value={customerNotes} onChange={e=>setCustomerNotes(e.target.value)} placeholder="ملاحظات: مكيف، باب، فيش كهرباء، أو أي عائق"/><div className="mh-booking-options"><button type="button" className={!installation?'active':''} onClick={()=>setInstallation(false)}>بدون تركيب</button><button type="button" className={installation?'active':''} onClick={()=>setInstallation(true)}>مع التركيب</button></div><button className="mh-booking-submit" disabled={bookingBusy}>{bookingBusy?'جاري الرفع والتسجيل...':'إرسال المقاسات والصورة'}</button>{bookingNotice&&<small>{bookingNotice}</small>}</form>}</div>
      <div className="mh-assistant-input"><button className={listening?'mic listening':'mic'} onClick={startVoice} disabled={!speechSupported||realtimeState==='connected'}>{listening?'●':'🎙'}</button><input value={input} onChange={e=>setInput(e.target.value)} onKeyDown={e=>e.key==='Enter'&&submit()} placeholder="اتكلم أو اكتب سؤالك"/><button onClick={()=>submit()}>إرسال</button></div>{!speechSupported&&<p className="mh-assistant-note">يمكنك استخدام المحادثة الصوتية المباشرة أو الكتابة.</p>}</section>}
    {!embedded&&<button className="mh-assistant-launcher" onClick={()=>setOpen(v=>!v)} aria-label="مساعد ماركوز هوم">🎙<span>اسأل ماركوز هوم</span></button>}
  </div>
}
