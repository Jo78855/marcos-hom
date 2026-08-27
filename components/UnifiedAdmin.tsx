import React,{FormEvent,useEffect,useMemo,useState}from'react';
import type{Session}from'@supabase/supabase-js';
import{supabase}from'../supabase';
import'../unified-operations.css';

type Product='coffee'|'fire'|'assistant';
type SourceTable='orders'|'fire_orders'|'mh_orders';
type Technician={id:string;name:string};
type UnifiedOrder={
  id:string;product:Product;customerName:string;customerPhone:string;selection:string;
  total:number|null;status:string;createdAt:string;area?:string|null;wallWidth?:number|null;
  wallHeight?:number|null;notes?:string|null;photoUrl?:string;source?:string|null;
  followUpAt?:string|null;followUpNote?:string|null;sourceTable:SourceTable;installationDate?:string|null;
  confirmation?:{confirmation_type:string;rating:number|null;comment:string|null;created_at:string}|null
};

const productLabel:Record<Product,string>={coffee:'ركن القهوة',fire:'الفير المعطر',assistant:'تصميم / مساعد ماركوز'};
const statusLabel:Record<string,string>={
  new:'جديد',needs_photo:'تحتاج صورة',measurements_received:'المقاسات والصورة وصلت',
  ready_for_review:'جاهز للمراجعة',contacted:'تم التواصل',draft:'قيد التجهيز',awaiting_customer:'بانتظار تأكيد العميل',
  confirmed:'مؤكد',scheduled:'تم تحديد الموعد',technician_assigned:'تم تعيين الفني',en_route:'الفني في الطريق',
  arrived:'وصل الفني',in_progress:'بدأ التنفيذ',blocked:'توجد ملاحظة',technician_done:'انتهى التنفيذ',
  awaiting_customer_handover:'بانتظار استلام العميل',completed:'مكتمل',cancelled:'ملغي'
};
const statusOptions=['new','needs_photo','measurements_received','ready_for_review','contacted','confirmed','completed','cancelled'];
const operationsStatusOptions=['draft','awaiting_customer','confirmed','scheduled','technician_assigned','en_route','arrived','in_progress','blocked','technician_done','awaiting_customer_handover','completed','cancelled'];
const slackUrl='https://marcoshome.slack.com/archives/C0BRVGRB3T3';

export default function UnifiedAdmin(){
  const[session,setSession]=useState<Session|null>(null),[loading,setLoading]=useState(true);
  useEffect(()=>{
    supabase.auth.getSession().then(({data})=>{setSession(data.session);setLoading(false)});
    const{data}=supabase.auth.onAuthStateChange((_e,s)=>setSession(s));
    return()=>data.subscription.unsubscribe();
  },[]);
  if(loading)return<div className="admin-center" dir="rtl">جاري التحميل...</div>;
  return session?<Dashboard onLogout={()=>supabase.auth.signOut()}/>:<Login/>;
}

function Login(){
  const[email,setEmail]=useState('joseph.sobhy2022@gmail.com'),[password,setPassword]=useState(''),[error,setError]=useState(''),[busy,setBusy]=useState(false);
  const submit=async(e:FormEvent)=>{e.preventDefault();setBusy(true);setError('');const{error:x}=await supabase.auth.signInWithPassword({email,password});if(x)setError('بيانات الدخول غير صحيحة');setBusy(false)};
  return<main className="admin-login" dir="rtl"><form onSubmit={submit}>
    <div className="brand-mark unified-mark">MH</div><h1>لوحة طلبات ماركوز هوم</h1><p>طلبات المساعد والمنتجات في مكان واحد</p>
    <label>البريد الإلكتروني<input type="email" value={email} onChange={e=>setEmail(e.target.value)} required/></label>
    <label>كلمة المرور<input type="password" value={password} onChange={e=>setPassword(e.target.value)} required/></label>
    {error&&<div className="admin-error">{error}</div>}<button disabled={busy}>{busy?'جاري الدخول...':'دخول'}</button>
  </form></main>;
}

function Dashboard({onLogout}:{onLogout:()=>void}){
  const[orders,setOrders]=useState<UnifiedOrder[]>([]),[loading,setLoading]=useState(false),[error,setError]=useState(''),[lastUpdated,setLastUpdated]=useState('');
  const[query,setQuery]=useState(''),[productFilter,setProductFilter]=useState<'all'|Product>('all'),[tab,setTab]=useState<'home'|'orders'|'customers'>('home');
  const[installPrompt,setInstallPrompt]=useState<any>(null),[updating,setUpdating]=useState('');
  const[technicians,setTechnicians]=useState<Technician[]>([]),[selectedTechnicians,setSelectedTechnicians]=useState<Record<string,string>>({});

  useEffect(()=>{const h=(e:any)=>{e.preventDefault();setInstallPrompt(e)};window.addEventListener('beforeinstallprompt',h);return()=>window.removeEventListener('beforeinstallprompt',h)},[]);
  const installAdmin=async()=>{if(installPrompt){await installPrompt.prompt();setInstallPrompt(null);return}alert('على آيفون: Safari ← مشاركة ← إضافة إلى الشاشة الرئيسية. على أندرويد/Chrome: اختر تثبيت التطبيق.')};

  const load=async()=>{
    setLoading(true);setError('');
    const[o,d,f,m,t]=await Promise.all([
      supabase.from('orders').select('*').order('created_at',{ascending:false}),
      supabase.from('designs').select('id,name_ar'),
      supabase.from('fire_orders').select('*').order('created_at',{ascending:false}),
      supabase.from('mh_orders').select('*, customer:mh_customers(name,phone,area), confirmations:mh_customer_confirmations(confirmation_type,rating,comment,created_at)').order('created_at',{ascending:false}),
      supabase.from('mh_technicians').select('id,name').eq('active',true).order('name')
    ]);
    if(o.error||f.error||m.error||t.error){setError('تعذر تحميل بعض الطلبات.');setLoading(false);return}
    const designNames=new Map((d.data||[]).map((x:any)=>[x.id,x.name_ar]));
    const normal:UnifiedOrder[]=await Promise.all((o.data||[]).map(async(x:any)=>{
      const assistant=x.source==='voice_assistant'||x.source==='assistant';
      let photoUrl='';
      if(x.place_photo_path){const s=await supabase.storage.from('customer-places').createSignedUrl(x.place_photo_path,3600);photoUrl=s.data?.signedUrl||''}
      return{
        id:x.id,product:assistant?'assistant':'coffee',customerName:x.customer_name||'عميل',customerPhone:x.customer_phone||'بدون رقم',
        selection:assistant?(x.wall_width?`حائط ${x.wall_width}م`:'طلب تصميم'):(designNames.get(x.design_id)||(x.installation?'شامل التركيب':'بدون تركيب')),
        total:x.total??null,status:x.status||'new',createdAt:x.created_at,area:x.area,wallWidth:x.wall_width,wallHeight:x.wall_height,
        notes:x.customer_notes||x.assistant_summary||null,photoUrl,source:x.source,followUpAt:x.follow_up_at,followUpNote:x.follow_up_note,sourceTable:'orders'
      };
    }));
    const fire:UnifiedOrder[]=(f.data||[]).map((x:any)=>({
      id:x.id,product:'fire',customerName:x.customer_name||'عميل',customerPhone:x.customer_phone||'بدون رقم',selection:x.size_name||'مقاس غير محدد',
      total:x.total??null,status:x.status||'new',createdAt:x.created_at,source:'fire',followUpAt:x.follow_up_at,followUpNote:x.follow_up_note,sourceTable:'fire_orders'
    }));
    const operations:UnifiedOrder[]=(m.data||[]).map((x:any)=>({
      id:x.id,product:'assistant',customerName:x.customer?.name||'عميل',customerPhone:x.customer?.phone||'بدون رقم',selection:x.service_type||'طلب تصميم',
      total:x.total??null,status:x.status||'confirmed',createdAt:x.created_at,area:x.customer?.area,wallWidth:x.width_m,wallHeight:x.height_m,
      notes:x.customer_notes||null,source:x.source,sourceTable:'mh_orders',installationDate:x.installation_date,
      confirmation:(x.confirmations||[]).find((c:any)=>c.confirmation_type==='handover')||(x.confirmations||[]).find((c:any)=>c.confirmation_type==='details')||null
    }));
    setTechnicians((t.data||[]) as Technician[]);
    setOrders([...normal,...fire,...operations].sort((a,b)=>Date.parse(b.createdAt)-Date.parse(a.createdAt)));
    setLastUpdated(new Date().toLocaleTimeString('ar-KW'));setLoading(false);
  };

  useEffect(()=>{void load();const t=window.setInterval(()=>void load(),10000);window.addEventListener('focus',load);return()=>{window.clearInterval(t);window.removeEventListener('focus',load)}},[]);

  const updateStatus=async(o:UnifiedOrder,status:string)=>{
    setUpdating(o.id);const{error:x}=await supabase.from(o.sourceTable).update({status}).eq('id',o.id);
    if(x)setError('تعذر تحديث حالة الطلب');else setOrders(v=>v.map(i=>i.id===o.id&&i.product===o.product?{...i,status}:i));setUpdating('');
  };

  const createAccessLink=async(o:UnifiedOrder,audience:'customer'|'technician')=>{
    setError('');
    if(o.sourceTable!=='mh_orders')return;
    const technicianId=selectedTechnicians[o.id]||'';
    if(audience==='technician'&&!technicianId){setError('اختر الفني أولًا.');return}
    const bytes=crypto.getRandomValues(new Uint8Array(32));
    const token=Array.from(bytes,v=>v.toString(16).padStart(2,'0')).join('');
    const digest=await crypto.subtle.digest('SHA-256',new TextEncoder().encode(token));
    const tokenHash=`\\x${Array.from(new Uint8Array(digest),v=>v.toString(16).padStart(2,'0')).join('')}`;
    if(audience==='technician'){
      const assignment=await supabase.from('mh_assignments').upsert({order_id:o.id,technician_id:technicianId,scheduled_at:o.installationDate||null},{onConflict:'order_id,technician_id'});
      if(assignment.error){setError('تعذر تعيين الفني.');return}
    }
    const created=await supabase.from('mh_order_access_links').insert({order_id:o.id,audience,technician_id:audience==='technician'?technicianId:null,token_hash:tokenHash});
    if(created.error){setError('تعذر إنشاء الرابط الآمن.');return}
    const nextStatus=audience==='customer'?'awaiting_customer':'technician_assigned';
    await supabase.from('mh_orders').update({status:nextStatus}).eq('id',o.id);
    setOrders(v=>v.map(i=>i.id===o.id&&i.sourceTable==='mh_orders'?{...i,status:nextStatus}:i));
    const previewBase=window.location.hostname.endsWith('github.io')?'/marcos-hom':'';
    const path=audience==='customer'?'/order/customer':'/order/technician';
    const link=`${window.location.origin}${previewBase}${path}?token=${token}`;
    try{await navigator.clipboard.writeText(link);alert(`تم نسخ رابط ${audience==='customer'?'العميل':'الفني'}.`)}catch{window.prompt('انسخ الرابط:',link)}
  };

  const whatsapp=(o:UnifiedOrder)=>{const p=o.customerPhone.replace(/\D/g,'');if(!p)return'';const msg=`مرحباً ${o.customerName}، معك ماركوز هوم بخصوص ${productLabel[o.product]}.`;return`https://wa.me/${p}?text=${encodeURIComponent(msg)}`};
  const filtered=useMemo(()=>orders.filter(o=>(productFilter==='all'||o.product===productFilter)&&(!query.trim()||`${o.customerName} ${o.customerPhone} ${o.selection} ${o.area||''}`.toLowerCase().includes(query.trim().toLowerCase()))),[orders,productFilter,query]);
  const customers=useMemo(()=>{const m=new Map<string,{name:string;phone:string;count:number;last:string}>();orders.forEach(o=>{const k=o.customerPhone||o.id,old=m.get(k);if(old){old.count++;if(Date.parse(o.createdAt)>Date.parse(old.last))old.last=o.createdAt}else m.set(k,{name:o.customerName,phone:o.customerPhone,count:1,last:o.createdAt})});return[...m.values()].sort((a,b)=>Date.parse(b.last)-Date.parse(a.last))},[orders]);
  const newCount=orders.filter(o=>['new','needs_photo','measurements_received','ready_for_review'].includes(o.status)).length;
  const assistantCount=orders.filter(o=>o.product==='assistant').length;

  return<main className="admin-shell unified-admin" dir="rtl">
    <header className="admin-header unified-header"><div><strong>لوحة طلبات ماركوز هوم</strong><small>تحديث تلقائي كل 10 ثواني</small></div><div><button onClick={installAdmin}>تثبيت اللوحة</button><a href={slackUrl} target="_blank" rel="noreferrer">فتح Slack</a><button onClick={onLogout}>تسجيل الخروج</button></div></header>
    <div className="admin-content">
      <nav className="unified-nav"><button className={tab==='home'?'active':''} onClick={()=>setTab('home')}>الرئيسية</button><button className={tab==='orders'?'active':''} onClick={()=>setTab('orders')}>الطلبات</button><button className={tab==='customers'?'active':''} onClick={()=>setTab('customers')}>العملاء</button></nav>
      {tab==='home'&&<><section className="unified-stats"><article><small>كل الطلبات</small><strong>{orders.length}</strong></article><article><small>طلبات جديدة / مراجعة</small><strong>{newCount}</strong></article><article><small>طلبات مساعد ماركوز</small><strong>{assistantCount}</strong></article><article><small>العملاء</small><strong>{customers.length}</strong></article></section><section className="admin-card"><h2>آخر حالة</h2><p>آخر تحديث: {lastUpdated||'—'}</p><button onClick={()=>void load()}>{loading?'جاري التحديث...':'تحديث الآن'}</button></section></>}
      {tab==='orders'&&<section className="admin-card"><div className="card-title unified-title"><div><h2>الطلبات</h2><small>أي طلب من المساعد يظهر هنا بعد التسجيل مباشرة</small></div><button onClick={()=>void load()}>{loading?'جاري التحديث...':'تحديث الآن'}</button></div>
        <div className="unified-filters"><input value={query} onChange={e=>setQuery(e.target.value)} placeholder="ابحث بالاسم أو الهاتف أو المنطقة"/><select value={productFilter} onChange={e=>setProductFilter(e.target.value as any)}><option value="all">كل الأقسام</option><option value="assistant">مساعد ماركوز</option><option value="coffee">ركن القهوة</option><option value="fire">الفير</option></select></div>
        {error&&<p className="admin-error">{error}</p>}<div className="unified-orders">{filtered.length===0?<p>لا توجد طلبات مطابقة.</p>:filtered.map(o=><div className="unified-order-card" key={`${o.product}-${o.id}`}>
          <div className="unified-order-main"><span className={`product-pill ${o.product}`}>{productLabel[o.product]}</span><strong>{o.customerName}<small>{o.customerPhone}</small></strong><span>{o.selection}{o.area?` — ${o.area}`:''}{o.wallHeight?` — ارتفاع ${o.wallHeight}م`:''}</span><b>{o.total===null?'حسب الطلب':`${o.total} د.ك`}</b>
          <select value={o.status} disabled={updating===o.id} onChange={e=>void updateStatus(o,e.target.value)}>{(o.sourceTable==='mh_orders'?operationsStatusOptions:statusOptions).map(s=><option key={s} value={s}>{statusLabel[s]}</option>)}</select><time>{new Date(o.createdAt).toLocaleString('ar-KW')}</time></div>
          <div className="order-actions">{whatsapp(o)&&<a href={whatsapp(o)} target="_blank" rel="noreferrer">واتساب العميل</a>}{o.photoUrl&&<a href={o.photoUrl} target="_blank" rel="noreferrer">صورة المكان</a>}{o.sourceTable==='mh_orders'&&<><button onClick={()=>void createAccessLink(o,'customer')}>نسخ رابط العميل</button><select value={selectedTechnicians[o.id]||''} onChange={e=>setSelectedTechnicians(v=>({...v,[o.id]:e.target.value}))}><option value="">اختر الفني</option>{technicians.map(t=><option key={t.id} value={t.id}>{t.name}</option>)}</select><button onClick={()=>void createAccessLink(o,'technician')}>نسخ رابط الفني</button></>}<a href={slackUrl} target="_blank" rel="noreferrer">Slack</a></div>
          {o.confirmation&&<div className="unified-confirmation"><strong>{o.confirmation.confirmation_type==='handover'?'✓ العميل استلم الأعمال':'✓ العميل أكد التفاصيل'}</strong>{o.confirmation.rating&&<span>التقييم: {o.confirmation.rating} / 5</span>}{o.confirmation.comment&&<small>{o.confirmation.comment}</small>}</div>}
          {o.notes&&<small>{o.notes}</small>}
        </div>)}</div>
      </section>}
      {tab==='customers'&&<section className="admin-card"><h2>العملاء</h2><div className="unified-customers">{customers.map(c=><div key={c.phone||c.name}><strong>{c.name}<small>{c.phone}</small></strong><span>{c.count} طلب</span><time>{new Date(c.last).toLocaleString('ar-KW')}</time></div>)}</div></section>}
    </div>
  </main>;
}
