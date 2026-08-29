import React,{FormEvent,useEffect,useMemo,useRef,useState}from'react';
import{supabase}from'../supabase';
import'../assistant-enhancements.css';

type Msg={role:'assistant'|'user';text:string};
type Offer={id:number;code:string;name_ar:string;min_width:number;max_width:number|null;height_m:number;price_without_installation:number;price_with_installation:number;components_ar:string;active:boolean;sort_order:number};
type Product={id:number;code:string;category_ar:string;name_ar:string;aliases_ar:string[];description_ar:string;price_text_ar:string;details_ar:string;active:boolean;sort_order:number};
type AssistantOrderArgs={customer_name:string;customer_phone:string;customer_area:string;wall_width:number|null;wall_height?:number|null;wants_installation:boolean;quoted_total?:number|null;requested_service:string;customer_note?:string|null;photo_path?:string|null};
type AssistantFormArgs={customer_name?:string|null;customer_phone?:string|null;customer_area?:string|null;wall_width?:number|null;wall_height?:number|null;wants_installation?:boolean|null;quoted_total?:number|null;requested_service?:string|null;customer_note?:string|null};
type AssistantOrderResult={ok:boolean;duplicate?:boolean;order_id?:string;order_number?:string;error?:string};
const SERVICE_CHOICES=['تصميم 198','ركن القهوة','طاولة شاشة','أعمدة WPC','جهاز الفاير','ألواح فوم بورد','طلب خاص'];

const assistantOrderTool={type:'function',name:'update_order_form',description:'يملأ أو يحدّث استمارة الطلب المرئية أمام العميل فقط، ولا يحفظ الطلب. استخدم null لأي بيان لم يذكره العميل بعد.',strict:true,parameters:{type:'object',additionalProperties:false,properties:{customer_name:{type:['string','null'],description:'اسم العميل إن ذكره'},customer_phone:{type:['string','null'],description:'رقم هاتف العميل إن ذكره'},customer_area:{type:['string','null'],description:'المنطقة إن ذكرها'},wall_width:{type:['number','null'],description:'العرض بالمتر أو السنتيمتر، أو null إذا لم يذكره أو لا يلزم للمنتج'},wall_height:{type:['number','null'],description:'الارتفاع بالمتر أو السنتيمتر أو null'},wants_installation:{type:['boolean','null'],description:'هل يريد التركيب أو null'},quoted_total:{type:['number','null'],description:'السعر المعروف فقط أو null'},requested_service:{type:['string','null'],description:'اسم المنتج مثل جهاز الفاير أو تصميم 198'},customer_note:{type:['string','null'],description:'المكونات واللون والحجم والملاحظات'}},required:['customer_name','customer_phone','customer_area','wall_width','wall_height','wants_installation','quoted_total','requested_service','customer_note']}};

const money=(v:number)=>Number(v).toLocaleString('ar-KW',{maximumFractionDigits:2});
const latinDigits=(value:unknown)=>String(value??'').replace(/[٠-٩]/g,d=>String('٠١٢٣٤٥٦٧٨٩'.indexOf(d))).replace(/٫/g,'.').replace(/،/g,',');
const parseUserNumber=(value:unknown)=>{const normalized=latinDigits(value).replace(',','.').trim();return normalized===''?Number.NaN:Number(normalized)};
const toMeters=(value:number|null|undefined)=>value==null||!Number.isFinite(Number(value))?null:(Number(value)>20?Number(value)/100:Number(value));
const rangeLabel=(o:Offer)=>o.max_width?`من ${o.min_width} إلى ${o.max_width} متر`:`من ${o.min_width} متر فأكثر`;
const offerForWidth=(w:number,offers:Offer[])=>offers.find(o=>w>=Number(o.min_width)&&(o.max_width==null||w<=Number(o.max_width)));
const norm=(s:string)=>s.toLowerCase().replace(/[أإآ]/g,'ا').replace(/ة/g,'ه').replace(/[؟?،,.]/g,' ').replace(/\s+/g,' ').trim();
const isFireService=(service:string)=>/فاير|فير|fire/.test(norm(service));
const FIRE_PRICES:Record<string,number>={'40':85,'70':135,'100':180,'120':220,'150':270};
const fireSizeFromText=(text:string)=>{
  const normalized=latinDigits(text);
  const match=normalized.match(/(?:مقاس|حجم)?\s*(40|70|100|120|150)\s*(?:سم|سنت(?:ي|يمتر)?|cm)?/i);
  return match?.[1]||'';
};
const productMatch=(text:string,products:Product[])=>{const q=norm(text);return products.find(p=>[p.name_ar,...(p.aliases_ar||[])].some(a=>q.includes(norm(a))))};
const serviceFromText=(text:string,products:Product[])=>{
  const q=norm(text);
  if(/198/.test(q))return'تصميم 198';
  if(/قهوه|كوفي|ركن/.test(q))return'ركن القهوة';
  if(/طاول|شاشه|تلفزيون/.test(q))return'طاولة شاشة';
  if(/wpc|عمود|اعمد/.test(q))return'أعمدة WPC';
  if(/فاير|فير|مدفأ|مدفا|fire/.test(q))return'جهاز الفاير';
  if(/فوم|foam/.test(q))return'ألواح فوم بورد';
  const matched=productMatch(text,products)?.name_ar;
  if(matched)return matched;
  return'';
};
const speechNumber=(text:string,label:RegExp)=>{
  const arabicDigits='٠١٢٣٤٥٦٧٨٩',normalized=text.replace(/[٠-٩]/g,d=>String(arabicDigits.indexOf(d))).replace(/٫/g,'.');
  const match=normalized.match(label);return match?.[1]?.replace(',','.')||'';
};
const opener=()=>['أكيد.','تمام.','طبعًا.'][Math.floor(Math.random()*3)];

function answerFor(text:string,offers:Offer[],products:Product[]){
  const q=norm(text);
  const design198=offers.find(o=>o.code==='design-198');
  if(/(?:تصميم\s*)?198/.test(q)&&design198){
    return`${opener()} تصميم 198 سعره ${money(design198.price_without_installation)} دينار بدون تركيب، و${money(design198.price_with_installation)} دينار مع التركيب. ${design198.components_ar}`;
  }
  const product=productMatch(text,products);
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
الطلبات متاحة الآن من خلال المساعد وتظهر مباشرة في لوحة تحكم ماركوز هوم. لا تقل مطلقًا إن الحجز أو الطلب غير متاح. إذا قال العميل «أريد أحجز» أو «أريد أطلب» أو أي عبارة مشابهة، قل له: «أكيد، تقدر تسجل طلبك الآن»، ثم اجمع بيانات الطلب المطلوبة أدناه. ميّز دائمًا بين تسجيل الطلب المتاح وبين حجز معاينة ميدانية؛ عدم توفر المعاينة لا يعني عدم توفر الطلب.
سياسة مهمة: المعاينات الميدانية غير متاحة حاليًا إلا للضرورة القصوى. قبل اقتراح أي معاينة، اطلب من العميل عرض الحائط، والارتفاع التقريبي إن أمكن، وصورة واضحة للمكان من الأمام يظهر فيها السقف والأرضية والعوائق مثل المكيف والباب والفيش، ثم اطلب المنطقة ورقم التواصل. حاول الوصول لتقييم وتسعير مبدئي من المقاسات والصورة أولًا. لا تعد العميل بمعاينة. قل إن الفريق يراجع الصور والمقاسات ويتواصل عند الحاجة.
إذا سأل العميل كيف يصور المكان، اطلب صورة واسعة من الأمام وصورة إضافية جانبية عند وجود عائق. إذا لم يجد المعلومة، اطلب تسجيل بياناته وصورته بدل إنهاء المحادثة.

تعبئة الطلب بالصوت: أداة update_order_form لا تحفظ أي طلب؛ هي تملأ الاستمارة المرئية أمام العميل فقط. عند قول العميل إنه يريد الطلب أو عند ذكر أي بيان جديد للطلب، استخدم الأداة لتحديث الخانات بالبيانات المعروفة، واستخدم null لما لم يذكره بعد. مثال: إذا قال «أريد جهاز فاير مقاس 70» ضع requested_service «جهاز الفاير» وضع المقاس في customer_note، ولا تطلب عرض حائط للفاير. اجمع الاسم إن أمكن ورقم الهاتف والمنطقة والخدمة والمقاسات اللازمة للمنتج والتركيب والملاحظات. بعد تحديث الاستمارة قل: «راجِع البيانات الظاهرة واضغط تأكيد وحفظ الطلب». زر التأكيد المرئي هو موافقة العميل النهائية وهو وحده الذي يحفظ في لوحة التحكم. ممنوع أن تقول «تم التسجيل» أو «تم الطلب» أثناء المحادثة الصوتية، لأن الأداة لا تحفظ الطلب.
إذا ذكر العميل المقاسات بالسنتيمتر مثل 122 أو 290 فاحتفظ بالأرقام كما قالها؛ النظام يحولها تلقائيًا إلى 1.22 و2.90 متر عند الحفظ. لا ترفض الطلب بسبب وحدة القياس.

هذه هي معلومات ماركوز هوم الحالية التي يجب الالتزام بها:\n${productsText}\n${offersText}`;
}

export default function MarcosAssistant({embedded=false}:{embedded?:boolean}){
  const[open,setOpen]=useState(embedded),[listening,setListening]=useState(false),[input,setInput]=useState('');
  const[offers,setOffers]=useState<Offer[]>([]),[products,setProducts]=useState<Product[]>([]);
  const[bookingOpen,setBookingOpen]=useState(false),[bookingBusy,setBookingBusy]=useState(false),[bookingNotice,setBookingNotice]=useState(''),[selectedService,setSelectedService]=useState('');
  const[customerName,setCustomerName]=useState(''),[customerPhone,setCustomerPhone]=useState(''),[area,setArea]=useState(''),[wallWidth,setWallWidth]=useState(''),[wallHeight,setWallHeight]=useState(''),[customerNotes,setCustomerNotes]=useState(''),[fireSize,setFireSize]=useState('');
  const[placePhoto,setPlacePhoto]=useState<File|null>(null);
  const[installation,setInstallation]=useState(true);
  const[messages,setMessages]=useState<Msg[]>([{role:'assistant',text:'أهلاً وسهلاً بك في ماركوز هوم. تقدر تسأل عن الأسعار والتصميمات، ولو عايز نحدد المناسب لمكانك ابعت المقاس وصورة الحائط.'}]);
  const[realtimeState,setRealtimeState]=useState<'idle'|'connecting'|'connected'|'error'>('idle'),[realtimeNotice,setRealtimeNotice]=useState('');
  const recognitionRef=useRef<any>(null),audioRef=useRef<HTMLAudioElement|null>(null),pcRef=useRef<RTCPeerConnection|null>(null),streamRef=useRef<MediaStream|null>(null),dcRef=useRef<RTCDataChannel|null>(null),processedCallIdsRef=useRef<Set<string>>(new Set()),bookingFormRef=useRef<HTMLFormElement|null>(null);
  const speechSupported=useMemo(()=>typeof window!=='undefined'&&!!((window as any).SpeechRecognition||(window as any).webkitSpeechRecognition),[]);

  useEffect(()=>{(async()=>{const[o,p]=await Promise.all([supabase.from('assistant_offers').select('*').eq('active',true).order('sort_order'),supabase.from('assistant_products').select('*').eq('active',true).order('sort_order')]);if(o.data)setOffers(o.data as Offer[]);if(p.data)setProducts(p.data as Product[])})()},[]);
  useEffect(()=>()=>stopRealtime(),[]);

  const browserSpeak=(text:string)=>{if(!('speechSynthesis'in window))return;window.speechSynthesis.cancel();const u=new SpeechSynthesisUtterance(text),voices=window.speechSynthesis.getVoices();u.voice=voices.find(v=>/^ar(-|_)/i.test(v.lang))||null;u.lang='ar-KW';u.rate=.92;window.speechSynthesis.speak(u)};
  const speak=async(text:string)=>{try{if(audioRef.current){audioRef.current.pause();audioRef.current=null}const{data,error}=await supabase.functions.invoke('marcos-tts',{body:{text}});if(error||!(data instanceof Blob))throw error||new Error('No audio');const url=URL.createObjectURL(data),audio=new Audio(url);audioRef.current=audio;audio.onended=()=>{URL.revokeObjectURL(url);audioRef.current=null};await audio.play()}catch{browserSpeak(text)}};

  const createAssistantOrder=async(args:Partial<AssistantOrderArgs>):Promise<AssistantOrderResult>=>{
    const spokenName=String(args.customer_name??'').trim(),name=spokenName.length>=2?spokenName:'عميل المساعد',phone=latinDigits(args.customer_phone).replace(/[^+\d]/g,''),customerArea=String(args.customer_area??'').trim(),service=String(args.requested_service??'').trim();
    const width=toMeters(parseUserNumber(args.wall_width)),height=args.wall_height==null?null:toMeters(parseUserNumber(args.wall_height));
    const missing=[!phone&&'رقم الهاتف',!customerArea&&'المنطقة',!service&&'المنتج',(!isFireService(service)&&(!width||width<=0))&&'عرض الحائط'].filter(Boolean);
    if(missing.length)return{ok:false,error:`أكمل ${missing.join('، ')} ثم أكد الطلب مرة أخرى.`};
    const{data,error}=await supabase.rpc('mh_create_assistant_order',{customer_name:name,customer_phone:phone,customer_area:customerArea,wall_width:width,wall_height:height,wants_installation:args.wants_installation!==false,quoted_total:args.quoted_total==null?null:parseUserNumber(args.quoted_total),requested_service:service,customer_note:String(args.customer_note??'').trim()||null,photo_path:String(args.photo_path??'').trim()||null,confirmed_by_customer:true});
    if(error){console.error('Assistant order RPC failed',error);return{ok:false,error:'تعذر الحفظ في لوحة التحكم الآن. حاول مرة أخرى بدون الصورة، ولن نفقد البيانات المكتوبة.'}};
    return data as AssistantOrderResult;
  };

  const fillBookingFromAssistant=(args:AssistantFormArgs)=>{
    setBookingOpen(true);
    if(args.customer_name)setCustomerName(String(args.customer_name));
    if(args.customer_phone)setCustomerPhone(latinDigits(args.customer_phone));
    if(args.customer_area)setArea(String(args.customer_area));
    if(args.requested_service)setSelectedService(String(args.requested_service));
    if(args.wall_width!=null)setWallWidth(latinDigits(args.wall_width));
    if(args.wall_height!=null)setWallHeight(latinDigits(args.wall_height));
    if(args.customer_note)setCustomerNotes(String(args.customer_note));
    if(args.wants_installation!=null)setInstallation(Boolean(args.wants_installation));
    setBookingNotice('تم ملء البيانات من المحادثة — راجعها ثم اضغط «تأكيد وحفظ الطلب».');
    setTimeout(()=>bookingFormRef.current?.scrollIntoView({behavior:'smooth',block:'nearest'}),80);
  };

  const prepareOrderFromSpeech=(transcript:string)=>{
    const q=norm(transcript),service=serviceFromText(transcript,products),isOrder=/احجز|حجز|اطلب|عايز|اريد|أريد|طلب/.test(q);
    if(!bookingOpen&&!isOrder&&!service)return;
    setBookingOpen(true);if(service)setSelectedService(service);
    const detectedFireSize=fireSizeFromText(transcript);if(detectedFireSize&&(isFireService(service)||isFireService(selectedService))){setFireSize(detectedFireSize);setCustomerNotes(v=>v.includes(`${detectedFireSize} سم`)?v:[v,`مقاس جهاز الفاير: ${detectedFireSize} سم`].filter(Boolean).join(' | '))}
    const width=speechNumber(transcript,/(?:العرض|عرض)\D{0,12}(\d+(?:[.,]\d+)?)/i);if(width)setWallWidth(width);
    const height=speechNumber(transcript,/(?:الارتفاع|ارتفاع|الطول|طول)\D{0,12}(\d+(?:[.,]\d+)?)/i);if(height)setWallHeight(height);
    const phone=speechNumber(transcript,/(?:الهاتف|التليفون|التلفون|رقم(?:ي| الهاتف)?)\D{0,12}([+]?\d[\d\s-]{6,18})/i).replace(/[\s-]/g,'');if(phone)setCustomerPhone(phone);
    setBookingNotice('الطلب غير محفوظ بعد — راجع البيانات واضغط «تأكيد وحفظ الطلب».');
  };

  const stopRealtime=()=>{try{dcRef.current?.close()}catch{}try{pcRef.current?.close()}catch{}streamRef.current?.getTracks().forEach(t=>t.stop());dcRef.current=null;pcRef.current=null;streamRef.current=null;setRealtimeState('idle')};
  const startRealtime=async()=>{
    if(realtimeState==='connected'||realtimeState==='connecting'){stopRealtime();return}
    setRealtimeState('connecting');setRealtimeNotice('جاري تشغيل الميكروفون...');
    try{
      const[catalogOffers,catalogProducts]=await Promise.all([
        supabase.from('assistant_offers').select('*').eq('active',true).order('sort_order'),
        supabase.from('assistant_products').select('*').eq('active',true).order('sort_order')
      ]);
      const freshOffers=(catalogOffers.data?.length?catalogOffers.data:offers) as Offer[];
      const freshProducts=(catalogProducts.data?.length?catalogProducts.data:products) as Product[];
      if(catalogOffers.data?.length)setOffers(freshOffers);
      if(catalogProducts.data?.length)setProducts(freshProducts);
      const{data,error}=await supabase.functions.invoke('marcos-realtime-token',{body:{}});if(error)throw error;
      const key=(data as any)?.value||(data as any)?.client_secret?.value||(data as any)?.client_secret;if(!key||typeof key!=='string')throw new Error('لم يتم استلام مفتاح الجلسة');
      const pc=new RTCPeerConnection();pcRef.current=pc;const remoteAudio=document.createElement('audio');remoteAudio.autoplay=true;remoteAudio.setAttribute('playsinline','true');pc.ontrack=e=>{remoteAudio.srcObject=e.streams[0];void remoteAudio.play().catch(()=>{})};
      const stream=await navigator.mediaDevices.getUserMedia({audio:true});streamRef.current=stream;stream.getTracks().forEach(track=>pc.addTrack(track,stream));
      const dc=pc.createDataChannel('oai-events');dcRef.current=dc;processedCallIdsRef.current.clear();dc.onopen=()=>{setRealtimeState('connected');setRealtimeNotice('اتكلم الآن — المساعد يسمعك ويرد عليك.');dc.send(JSON.stringify({type:'session.update',session:{instructions:catalogInstructions(freshOffers,freshProducts),tools:[assistantOrderTool],tool_choice:'auto',audio:{output:{voice:'marin'}}}}))};dc.onclose=()=>{setRealtimeState('idle');setRealtimeNotice('')};dc.onerror=()=>{setRealtimeState('error');setRealtimeNotice('تعذر الاتصال الصوتي. جرّب مرة أخرى.')};dc.onmessage=e=>{void(async()=>{try{const evt=JSON.parse(e.data);if(evt.type==='conversation.item.input_audio_transcription.completed'&&evt.transcript){setMessages(v=>[...v,{role:'user',text:evt.transcript}]);prepareOrderFromSpeech(evt.transcript)}if((evt.type==='response.output_audio_transcript.done'||evt.type==='response.output_text.done')&&(evt.transcript||evt.text))setMessages(v=>[...v,{role:'assistant',text:evt.transcript||evt.text}]);if(evt.type==='response.done'){const calls=(evt.response?.output||[]).filter((item:any)=>item.type==='function_call'&&item.name==='update_order_form');for(const call of calls){if(!call.call_id||processedCallIdsRef.current.has(call.call_id))continue;processedCallIdsRef.current.add(call.call_id);let output={ok:true,prepared:true};try{fillBookingFromAssistant(JSON.parse(call.arguments||'{}') as AssistantFormArgs)}catch{output={ok:false,prepared:false}}dc.send(JSON.stringify({type:'conversation.item.create',item:{type:'function_call_output',call_id:call.call_id,output:JSON.stringify(output)}}));dc.send(JSON.stringify({type:'response.create'}))}}}catch{}})()};
      const offer=await pc.createOffer();await pc.setLocalDescription(offer);const sdpResponse=await fetch('https://api.openai.com/v1/realtime/calls',{method:'POST',body:offer.sdp||'',headers:{Authorization:`Bearer ${key}`,'Content-Type':'application/sdp'}});if(!sdpResponse.ok)throw new Error(`Realtime ${sdpResponse.status}`);await pc.setRemoteDescription({type:'answer',sdp:await sdpResponse.text()});
    }catch(err){console.error('Realtime voice failed',err);stopRealtime();setRealtimeState('error');setRealtimeNotice('تعذر تشغيل الصوت. تأكد من السماح للميكروفون ثم اضغط مرة أخرى.');setTimeout(()=>setRealtimeState('idle'),3000)}
  };

  const submit=(raw=input)=>{const text=raw.trim();if(!text)return;let reply:string;if(/احجز|حجز|اطلب|طلب|عايز|اريد|أريد/.test(norm(text))){prepareOrderFromSpeech(text);reply='أكيد. فتحت لك بطاقة الطلب بالأسفل. راجع البيانات واضغط «تأكيد وحفظ الطلب». الطلب لا يُعتبر مسجلًا إلا بعد ظهور رقم يبدأ بـ MH-.'}else if(/معاينه|معاينة|زيارة|مقاس|صوره|صورة/.test(norm(text))){prepareOrderFromSpeech(text);reply='تمام. ابعت المقاسات وصورة واضحة للمكان، واكتب الاسم ورقم الهاتف والمنطقة عشان الفريق يراجع التفاصيل ويتواصل معك عند الحاجة.'}else reply=answerFor(text,offers,products);setMessages(v=>[...v,{role:'user',text},{role:'assistant',text:reply}]);setInput('');void speak(reply)};
  const chooseService=(service:string)=>{setSelectedService(service);setBookingOpen(true);setBookingNotice('');setMessages(v=>[...v,{role:'user',text:`اختيار: ${service}`},{role:'assistant',text:`تم اختيار ${service}. أكمل بيانات الطلب والمقاسات بالأسفل، ثم اضغط «تأكيد وإرسال الطلب».`}])};
  useEffect(()=>{const openFromStore=(event:Event)=>{const service=(event as CustomEvent<string>).detail||'';setOpen(true);if(service)chooseService(service)};window.addEventListener('mh-open-assistant',openFromStore);return()=>window.removeEventListener('mh-open-assistant',openFromStore)},[]);
  const startVoice=()=>{const SR=(window as any).SpeechRecognition||(window as any).webkitSpeechRecognition;if(!SR)return;const r=new SR();r.lang='ar-KW';r.interimResults=false;r.continuous=false;r.onstart=()=>setListening(true);r.onend=()=>setListening(false);r.onerror=()=>setListening(false);r.onresult=(e:any)=>{const t=e.results?.[0]?.[0]?.transcript||'';setInput(t);submit(t)};recognitionRef.current=r;r.start()};

  const submitBooking=async(e:FormEvent)=>{
    e.preventDefault();setBookingNotice('');const width=toMeters(parseUserNumber(wallWidth)),height=wallHeight.trim()?toMeters(parseUserNumber(wallHeight)):null;
    if(!selectedService||!customerPhone.trim()||!area.trim()||(isFireService(selectedService)&&!fireSize)||(!isFireService(selectedService)&&(width==null||!Number.isFinite(width)||width<=0))){setBookingNotice(`أكمل المنتج ورقم الهاتف والمنطقة${isFireService(selectedService)?' ومقاس جهاز الفاير': ' وعرض الحائط'}.`);return}
    setBookingBusy(true);let photoPath:string|null=null,photoWarning='';
    if(placePhoto){const ext=placePhoto.name.split('.').pop()?.toLowerCase()||'jpg';photoPath=`${Date.now()}-${crypto.randomUUID()}.${ext}`;const upload=await supabase.storage.from('customer-places').upload(photoPath,placePhoto,{cacheControl:'3600',upsert:false});if(upload.error){photoPath=null;photoWarning='الصورة لم تُرفع؛ الطلب محفوظ بدونها ويمكن إضافتها لاحقًا.'}}
    const offer=selectedService==='تصميم 198'?offerForWidth(width,offers):undefined,total=offer?Number(installation?offer.price_with_installation:offer.price_without_installation):(isFireService(selectedService)&&fireSize?FIRE_PRICES[fireSize]??null:null);
    const result=await createAssistantOrder({customer_name:customerName,customer_phone:customerPhone,customer_area:area,wall_width:width,wall_height:height,wants_installation:installation,quoted_total:total,requested_service:selectedService,customer_note:[customerNotes,photoWarning].filter(Boolean).join(' | ')||null,photo_path:photoPath});
    setBookingBusy(false);if(!result.ok){if(photoPath)await supabase.storage.from('customer-places').remove([photoPath]);setBookingNotice(result.error||'تعذر تسجيل الطلب.');return}
    const reply=`تم الحفظ في لوحة التحكم: ${result.order_number}. ${photoPath?'الفريق هيراجع المقاسات والصورة ويتواصل معك عند الحاجة.':'الأفضل تبعت صورة واضحة للحائط عشان نحدد المناسب بدون معاينة قدر الإمكان.'}`;
    setBookingNotice(reply);setMessages(v=>[...v,{role:'assistant',text:reply}]);setCustomerName('');setCustomerPhone('');setArea('');setWallWidth('');setWallHeight('');setCustomerNotes('');setFireSize('');setPlacePhoto(null);setSelectedService('')
  };

  const rtLabel=realtimeState==='connecting'?'جاري الاتصال...':realtimeState==='connected'?'إنهاء المحادثة الصوتية':realtimeState==='error'?'تعذر الاتصال — جرّب مرة أخرى':'محادثة صوتية مباشرة';
  const bookingChoices=selectedService&&!SERVICE_CHOICES.includes(selectedService)?[selectedService,...SERVICE_CHOICES]:SERVICE_CHOICES;
  return <div className={embedded?'mh-assistant mh-assistant-embedded':'mh-assistant'} dir="rtl">
    {open&&<section className="mh-assistant-panel"><header><div><strong>مساعد ماركوز هوم</strong><small>صوت مباشر + أسعار + تقييم بالمقاسات والصور</small></div>{!embedded&&<button onClick={()=>setOpen(false)} aria-label="إغلاق">×</button>}</header>
      <div className="mh-realtime-bar"><button className={`mh-realtime-button ${realtimeState}`} onClick={()=>void startRealtime()} disabled={realtimeState==='connecting'}>🎧 {rtLabel}</button>{realtimeNotice&&<small>{realtimeNotice}</small>}</div>
      <div className="mh-assistant-messages">{messages.map((m,i)=><div key={i} className={`mh-msg ${m.role}`}>{m.text}</div>)}<div className="mh-assistant-quick"><strong>اختار المنتج لبدء الطلب</strong><div>{SERVICE_CHOICES.map(service=><button key={service} className={selectedService===service?'active':''} onClick={()=>chooseService(service)}>{service}</button>)}</div></div>{bookingOpen&&<form ref={bookingFormRef} className="mh-booking" onSubmit={submitBooking}><strong>راجع طلبك قبل الحفظ</strong><small>حالة الطلب: {bookingNotice.startsWith('تم الحفظ')?bookingNotice:'غير محفوظ — المساعد يملأ الخانات وأنت تؤكد'}</small><select required value={selectedService} onChange={e=>setSelectedService(e.target.value)}><option value="">اختر المنتج أو الخدمة</option>{bookingChoices.map(service=><option key={service} value={service}>{service}</option>)}</select>{isFireService(selectedService)&&<div className="mh-booking-options" aria-label="مقاس جهاز الفاير">{Object.keys(FIRE_PRICES).map(size=><button type="button" key={size} className={fireSize===size?'active':''} onClick={()=>{setFireSize(size);setCustomerNotes(v=>[v.replace(/(?:\s*\|\s*)?مقاس جهاز الفاير:\s*\d+\s*سم/g,'').trim(),`مقاس جهاز الفاير: ${size} سم`].filter(Boolean).join(' | '))}}>{size} سم — {FIRE_PRICES[size]} د.ك</button>)}</div>}<input value={customerName} onChange={e=>setCustomerName(e.target.value)} placeholder="الاسم (اختياري)"/><input required value={customerPhone} onChange={e=>setCustomerPhone(e.target.value)} inputMode="tel" placeholder="رقم الهاتف"/><input required value={area} onChange={e=>setArea(e.target.value)} placeholder="المنطقة"/><input required={!isFireService(selectedService)} value={wallWidth} onChange={e=>setWallWidth(e.target.value)} inputMode="decimal" placeholder={isFireService(selectedService)?'عرض الحائط غير مطلوب للفاير':'العرض بالمتر 1.22 أو بالسنتيمتر 122'}/><input value={wallHeight} onChange={e=>setWallHeight(e.target.value)} inputMode="decimal" placeholder="الارتفاع بالمتر 2.90 أو بالسنتيمتر 290"/><input type="file" accept="image/jpeg,image/png,image/webp" onChange={e=>setPlacePhoto(e.target.files?.[0]||null)}/><textarea value={customerNotes} onChange={e=>setCustomerNotes(e.target.value)} placeholder="الحجم، المكونات، اللون أو أي ملاحظات"/><div className="mh-booking-options"><button type="button" className={!installation?'active':''} onClick={()=>setInstallation(false)}>بدون تركيب</button><button type="button" className={installation?'active':''} onClick={()=>setInstallation(true)}>مع التركيب</button></div><button className="mh-booking-submit" disabled={bookingBusy}>{bookingBusy?'جاري الحفظ...':'تأكيد وحفظ الطلب'}</button>{bookingNotice&&!bookingNotice.startsWith('تم الحفظ')&&<small>{bookingNotice}</small>}</form>}</div>
      <div className="mh-assistant-input"><button className={listening?'mic listening':'mic'} onClick={startVoice} disabled={!speechSupported||realtimeState==='connected'}>{listening?'●':'🎙'}</button><input value={input} onChange={e=>setInput(e.target.value)} onKeyDown={e=>e.key==='Enter'&&submit()} placeholder="اتكلم أو اكتب سؤالك"/><button onClick={()=>submit()}>إرسال</button></div>{!speechSupported&&<p className="mh-assistant-note">يمكنك استخدام المحادثة الصوتية المباشرة أو الكتابة.</p>}</section>}
    {!embedded&&<button className="mh-assistant-launcher" onClick={()=>setOpen(v=>!v)} aria-label="اطلب أو اسأل مساعد ماركوز هوم">🎙<span>اطلب أو اسأل</span></button>}
  </div>
}
