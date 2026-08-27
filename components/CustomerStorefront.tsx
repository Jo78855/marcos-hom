import React,{useEffect,useMemo,useState}from'react';
import{supabase}from'../supabase';
import'./CustomerStorefront.css';

type Product={id:number;code:string;category_ar:string;name_ar:string;description_ar:string;price_text_ar:string;details_ar:string;active:boolean;sort_order:number};
type Offer={code:string;name_ar:string;min_width:number;max_width:number|null;price_without_installation:number;price_with_installation:number;components_ar:string};
interface InstallPromptEvent extends Event{prompt:()=>Promise<void>;userChoice:Promise<{outcome:'accepted'|'dismissed'}>}

const fallbackProducts:Product[]=[
  {id:1,code:'design-198',category_ar:'خلفيات شاشة',name_ar:'تصميم 198',description_ar:'خلفية شاشة متكاملة بارتفاع 2.90م والتسعير حسب عرض الحائط.',price_text_ar:'من 130 د.ك بدون تركيب / من 170 د.ك مع التركيب',details_ar:'',active:true,sort_order:1},
  {id:2,code:'coffee-corner',category_ar:'ركن القهوة',name_ar:'ركن القهوة',description_ar:'لوح فوم بورد V حتى السقف مع كبت بعرض متر.',price_text_ar:'35 د.ك بدون تركيب / 50 د.ك مع التركيب',details_ar:'',active:true,sort_order:2},
  {id:3,code:'tv-console-20',category_ar:'طاولات الشاشة',name_ar:'طاولة شاشة 2 متر',description_ar:'طاولة معلقة 4 أبواب، ارتفاع 25سم وعمق 32سم.',price_text_ar:'50 د.ك بدون تركيب / 60 د.ك مع التركيب',details_ar:'',active:true,sort_order:3},
  {id:4,code:'wpc-columns',category_ar:'أعمدة ديكور',name_ar:'أعمدة WPC',description_ar:'مقاس 5×10سم وارتفاع 2.90م.',price_text_ar:'5 د.ك للقطعة / 7 د.ك مع التركيب',details_ar:'',active:true,sort_order:4},
  {id:5,code:'foam-board',category_ar:'ألواح ديكور',name_ar:'ألواح فوم بورد',description_ar:'لوح V بعرض 1.22م وارتفاع 2.90م.',price_text_ar:'25 د.ك للوح',details_ar:'',active:true,sort_order:5},
  {id:6,code:'fire',category_ar:'أجهزة ديكور',name_ar:'جهاز الفاير المعطر',description_ar:'ديكور وتعطير يعمل بالماء والكهرباء مع إمكانية إضافة الزيوت.',price_text_ar:'من 85 د.ك',details_ar:'',active:true,sort_order:6},
];
const coffeeImages=[
  ['/coffee/white-lightwood.webp','أبيض مع خشب فاتح'],['/coffee/brown-travertine.webp','بني مع ترافرتينو'],
  ['/coffee/black-lightwood.webp','أسود مع خشب فاتح'],['/coffee/darkgray-chevron.webp','رمادي غامق'],
  ['/coffee/lightgray-chevron.webp','رمادي فاتح'],['/coffee/lightwood-chevron.webp','خشبي فاتح'],['/coffee/honey-wood.webp','عسلي ماركوز هوم']
];
const categoryIcon:Record<string,string>={'خلفيات شاشة':'▣','ركن القهوة':'☕','طاولات الشاشة':'▰','الطاولات':'▰','أعمدة ديكور':'╫','أعمدة WPC':'╫','ألواح ديكور':'◇','أجهزة ديكور':'♨','الفاير':'♨','خدمات إضافية':'+'};
const productImage=(code:string)=>code.includes('coffee')?'/coffee/brown-travertine.webp':code.includes('fire')?'/fire/main-product.webp':'';
const uniqueProducts=(items:Product[])=>{const seen=new Set<string>();return items.filter(item=>{const key=item.name_ar.replace(/\s+/g,' ').trim();if(seen.has(key))return false;seen.add(key);return true})};

export default function CustomerStorefront(){
  const[products,setProducts]=useState<Product[]>(fallbackProducts),[offer,setOffer]=useState<Offer|null>(null),[category,setCategory]=useState('الكل');
  const[installPrompt,setInstallPrompt]=useState<InstallPromptEvent|null>(null),[installed,setInstalled]=useState(window.matchMedia('(display-mode: standalone)').matches);
  const base=window.location.hostname.endsWith('github.io')?'/marcos-hom':'';

  useEffect(()=>{Promise.all([
    supabase.from('assistant_products').select('*').eq('active',true).order('sort_order'),
    supabase.from('assistant_offers').select('*').eq('code','design-198').eq('active',true).maybeSingle()
  ]).then(([p,o])=>{if(p.data?.length)setProducts(uniqueProducts(p.data as Product[]));if(o.data)setOffer(o.data as Offer)})},[]);
  useEffect(()=>{const capture=(event:Event)=>{event.preventDefault();setInstallPrompt(event as InstallPromptEvent)};const done=()=>{setInstalled(true);setInstallPrompt(null)};window.addEventListener('beforeinstallprompt',capture);window.addEventListener('appinstalled',done);return()=>{window.removeEventListener('beforeinstallprompt',capture);window.removeEventListener('appinstalled',done)}},[]);

  const categories=useMemo(()=>['الكل',...Array.from(new Set(products.map(p=>p.category_ar)))],[products]);
  const visible=category==='الكل'?products:products.filter(p=>p.category_ar===category);
  const openAssistant=(service='')=>window.dispatchEvent(new CustomEvent('mh-open-assistant',{detail:service}));
  const install=async()=>{if(installPrompt){await installPrompt.prompt();const choice=await installPrompt.userChoice;if(choice.outcome==='accepted')setInstallPrompt(null);return}alert('على آيفون: افتح التطبيق في Safari، اضغط مشاركة، ثم «إضافة إلى الشاشة الرئيسية». على أندرويد: افتح قائمة Chrome واختر «تثبيت التطبيق».')};

  return <main className="customer-app" dir="rtl">
    <header className="customer-header">
      <a className="customer-brand" href={`${base}/`}><img src={`${base}/marcos-home-logo.jpg`} alt="شعار ماركوز هوم"/><span><strong>ماركوز هوم</strong><small>ديكور منزلك من مكان واحد</small></span></a>
      <nav><a href="#products">المنتجات</a><a href="#coffee">الألوان</a><button onClick={()=>openAssistant()}>مساعد ماركوز</button></nav>
      {!installed&&<button className="customer-install" onClick={install}>⌄ تثبيت التطبيق</button>}
    </header>

    <section className="customer-hero">
      <div className="hero-copy"><span>تنفيذ داخل الكويت • أسعار واضحة</span><h1>اختار تصميمك<br/>وخلي الباقي علينا</h1><p>خلفيات شاشة، ركن قهوة، طاولات، أعمدة WPC وأجهزة الفاير. اختار المنتج واسأل مساعد ماركوز أو أرسل طلبك مباشرة.</p><div><a href="#products">شاهد المنتجات</a><button onClick={()=>openAssistant()}>🎙 تحدث مع المساعد</button></div></div>
      <div className="hero-visual"><img src={`${base}/coffee/brown-travertine.webp`} alt="تصميم ركن قهوة من ماركوز هوم"/><div><small>العرض المميز</small><strong>تصميم 198</strong><span>{offer?`${offer.price_without_installation} د.ك بدون تركيب • ${offer.price_with_installation} د.ك مع التركيب`:'أسعار حسب عرض الحائط'}</span></div></div>
    </section>

    <section className="customer-benefits"><div><b>✓</b><span><strong>أسعار معلنة</strong><small>بدون تركيب ومع التركيب</small></span></div><div><b>⌂</b><span><strong>تنفيذ داخل الكويت</strong><small>صور ومقاسات قبل التنفيذ</small></span></div><div><b>◎</b><span><strong>متابعة موثقة</strong><small>رابط مستقل لكل طلب</small></span></div></section>

    <section className="catalog-section" id="products"><div className="section-title"><span>كتالوج ماركوز هوم</span><h2>المنتجات والتصميمات</h2><p>الأسعار والمعلومات تُقرأ من نفس لوحة التحكم.</p></div>
      <div className="category-tabs">{categories.map(item=><button key={item} className={category===item?'active':''} onClick={()=>setCategory(item)}>{item}</button>)}</div>
      <div className="product-grid">{visible.map(product=>{const image=productImage(product.code);return <article key={product.id} className="product-card">
        <div className={image?'product-media has-image':'product-media'}>{image?<img src={`${base}${image}`} alt={product.name_ar}/>:<><span>{categoryIcon[product.category_ar]||'MH'}</span><small>{product.category_ar}</small></>}</div>
        <div className="product-body"><small>{product.category_ar}</small><h3>{product.name_ar}</h3><p>{product.description_ar}</p><strong>{product.price_text_ar}</strong><button onClick={()=>openAssistant(product.name_ar)}>اختار وابدأ الطلب</button></div>
      </article>})}</div>
    </section>

    <section className="coffee-gallery" id="coffee"><div className="section-title"><span>ركن القهوة</span><h2>اختار اللون المناسب</h2><p>سبعة ألوان جاهزة، 35 د.ك بدون تركيب أو 50 د.ك مع التركيب.</p></div><div>{coffeeImages.map(([src,name])=><button key={src} onClick={()=>openAssistant(`ركن القهوة — ${name}`)}><img src={`${base}${src}`} alt={`ركن القهوة ${name}`}/><span>{name}</span></button>)}</div></section>

    <section className="assistant-banner"><div><span>محتاج مساعدة في الاختيار؟</span><h2>مساعد ماركوز يجاوبك ويسجل طلبك</h2><p>اكتب أو اتكلم، وحدد المنتج والمقاسات والصورة. الطلب لا يُسجل إلا بعد تأكيدك.</p></div><button onClick={()=>openAssistant()}>🎙 افتح مساعد ماركوز</button></section>
    <footer><div className="customer-brand"><img src={`${base}/marcos-home-logo.jpg`} alt="ماركوز هوم"/><span><strong>Marco’s Home</strong><small>الكويت</small></span></div><p>الأسعار قابلة للتحديث من لوحة التحكم، والتسعير النهائي حسب المقاسات والمكونات.</p></footer>
    <a className="whatsapp-float" href="https://wa.me/96550204320?text=%D9%85%D8%B1%D8%AD%D8%A8%D8%A7%D9%8B%20%D9%85%D8%A7%D8%B1%D9%83%D9%88%D8%B2%20%D9%87%D9%88%D9%85%D8%8C%20%D8%A3%D8%B1%D8%BA%D8%A8%20%D9%81%D9%8A%20%D8%A7%D9%84%D8%A7%D8%B3%D8%AA%D9%81%D8%B3%D8%A7%D8%B1%20%D8%B9%D9%86%20%D8%A7%D9%84%D9%85%D9%86%D8%AA%D8%AC%D8%A7%D8%AA" target="_blank" rel="noreferrer" aria-label="تواصل مع ماركوز هوم على واتساب">واتساب <b>◉</b></a>
  </main>;
}
