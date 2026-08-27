import React,{useEffect,useMemo,useState}from'react';
import{supabase}from'../supabase';
import'./CustomerStorefront.css';
import'./CustomerCatalog.css';
import{galleryImages,imageForProduct,websiteProducts}from'./catalogContent';

type Product={id:number;code:string;category_ar:string;name_ar:string;description_ar:string;price_text_ar:string;details_ar:string;active:boolean;sort_order:number};
type Offer={code:string;name_ar:string;min_width:number;max_width:number|null;price_without_installation:number;price_with_installation:number;components_ar:string};
interface InstallPromptEvent extends Event{prompt:()=>Promise<void>;userChoice:Promise<{outcome:'accepted'|'dismissed'}>}

const coffeeImages=[
  ['/coffee/white-lightwood.webp','أبيض مع خشب فاتح'],['/coffee/brown-travertine.webp','بني مع ترافرتينو'],
  ['/coffee/black-lightwood.webp','أسود مع خشب فاتح'],['/coffee/darkgray-chevron.webp','رمادي غامق'],
  ['/coffee/lightgray-chevron.webp','رمادي فاتح'],['/coffee/lightwood-chevron.webp','خشبي فاتح'],['/coffee/honey-wood.webp','عسلي ماركوز هوم']
];
const productKey=(item:Product)=>{const value=`${item.code} ${item.name_ar}`.replace(/[_-]/g,' ').replace(/\s+/g,' ').trim().toLowerCase();if(value.includes('fire')||value.includes('فاير'))return'fire';if(value.includes('coffee')||value.includes('قهوة'))return'coffee';if(value.includes('wpc')||value.includes('عمود')||value.includes('أعمدة')||value.includes('فواصل'))return'wpc';return item.name_ar.replace(/\s+/g,' ').trim()};
const uniqueProducts=(items:Product[])=>{const seen=new Set<string>();return items.filter(item=>{const key=productKey(item);if(seen.has(key))return false;seen.add(key);return true})};
const mergeProducts=(remote:Product[])=>{const map=new Map<string,Product>();websiteProducts.forEach(item=>map.set(productKey(item),item));remote.forEach(item=>map.set(productKey(item),item));return [...map.values()].sort((a,b)=>a.sort_order-b.sort_order)};

export default function CustomerStorefront(){
  const[products,setProducts]=useState<Product[]>(websiteProducts),[offer,setOffer]=useState<Offer|null>(null),[category,setCategory]=useState('الكل');
  const[installPrompt,setInstallPrompt]=useState<InstallPromptEvent|null>(null),[installed,setInstalled]=useState(window.matchMedia('(display-mode: standalone)').matches);
  const base=window.location.hostname.endsWith('github.io')?'/marcos-hom':'';

  useEffect(()=>{Promise.all([
    supabase.from('assistant_products').select('*').eq('active',true).order('sort_order'),
    supabase.from('assistant_offers').select('*').eq('code','design-198').eq('active',true).maybeSingle()
  ]).then(([p,o])=>{if(p.data?.length)setProducts(uniqueProducts(mergeProducts(p.data as Product[])));if(o.data)setOffer(o.data as Offer)})},[]);
  useEffect(()=>{const capture=(event:Event)=>{event.preventDefault();setInstallPrompt(event as InstallPromptEvent)};const done=()=>{setInstalled(true);setInstallPrompt(null)};window.addEventListener('beforeinstallprompt',capture);window.addEventListener('appinstalled',done);return()=>{window.removeEventListener('beforeinstallprompt',capture);window.removeEventListener('appinstalled',done)}},[]);

  const categories=useMemo(()=>['الكل',...Array.from(new Set(products.map(p=>p.category_ar)))],[products]);
  const visible=category==='الكل'?products:products.filter(p=>p.category_ar===category);
  const openAssistant=(service='')=>window.dispatchEvent(new CustomEvent('mh-open-assistant',{detail:service}));
  const install=async()=>{if(installPrompt){await installPrompt.prompt();const choice=await installPrompt.userChoice;if(choice.outcome==='accepted')setInstallPrompt(null);return}alert('على آيفون: افتح التطبيق في Safari، اضغط مشاركة، ثم «إضافة إلى الشاشة الرئيسية». على أندرويد: افتح قائمة Chrome واختر «تثبيت التطبيق».')};

  return <main className="customer-app" dir="rtl">
    <header className="customer-header">
      <a className="customer-brand" href={`${base}/`}><img src={`${base}/marcos-home-logo.jpg`} alt="شعار ماركوز هوم"/><span><strong>ماركوز هوم</strong><small>ديكور منزلك من مكان واحد</small></span></a>
      <nav><a href="#products">المنتجات</a><a href="#gallery">أعمالنا</a><a href="#coffee">الألوان</a><button onClick={()=>openAssistant()}>مساعد ماركوز</button></nav>
      {!installed&&<button className="customer-install" onClick={install}>⌄ تثبيت التطبيق</button>}
    </header>

    <section className="customer-hero">
      <div className="hero-copy"><span>تنفيذ داخل الكويت • أسعار واضحة</span><h1>اختار تصميمك<br/>وخلي الباقي علينا</h1><p>خلفيات شاشة، ركن قهوة، طاولات، أعمدة WPC وأجهزة الفاير. اختار المنتج واسأل مساعد ماركوز أو أرسل طلبك مباشرة.</p><div><a href="#products">شاهد المنتجات</a><button onClick={()=>openAssistant()}>🎙 تحدث مع المساعد</button></div></div>
      <div className="hero-visual"><img src={`${base}/catalog/design-198/beige-wood.webp`} alt="تصميم 198 من ماركوز هوم"/><div><small>العرض المميز</small><strong>تصميم 198</strong><span>{offer?`${offer.price_without_installation} د.ك بدون تركيب • ${offer.price_with_installation} د.ك مع التركيب`:'أسعار حسب عرض الحائط'}</span></div></div>
    </section>

    <section className="customer-benefits"><div><b>✓</b><span><strong>أسعار معلنة</strong><small>بدون تركيب ومع التركيب</small></span></div><div><b>⌂</b><span><strong>تنفيذ داخل الكويت</strong><small>صور ومقاسات قبل التنفيذ</small></span></div><div><b>◎</b><span><strong>متابعة موثقة</strong><small>رابط مستقل لكل طلب</small></span></div></section>

    <section className="catalog-section" id="products"><div className="section-title"><span>كتالوج ماركوز هوم</span><h2>المنتجات والتصميمات</h2><p>الأسعار والمعلومات تُقرأ من نفس لوحة التحكم.</p></div>
      <div className="category-tabs">{categories.map(item=><button key={item} className={category===item?'active':''} onClick={()=>setCategory(item)}>{item}</button>)}</div>
      <div className="product-grid">{visible.map(product=>{const image=imageForProduct(product.code,product.name_ar);return <article key={`${product.code}-${product.id}`} className="product-card">
        <div className="product-media has-image"><img src={`${base}${image}`} alt={product.name_ar} loading="lazy"/></div>
        <div className="product-body"><small>{product.category_ar}</small><h3>{product.name_ar}</h3><p>{product.description_ar}</p><strong>{product.price_text_ar}</strong><button onClick={()=>openAssistant(product.name_ar)}>اختار وابدأ الطلب</button></div>
      </article>})}</div>
    </section>

    <section className="works-gallery" id="gallery"><div className="section-title"><span>أعمال وصور ماركوز هوم</span><h2>كل التصميمات في مكان واحد</h2><p>صور المنتجات والألوان وأعمال التنفيذ المنقولة من موقع ماركوز هوم الحالي.</p></div>
      <div className="works-grid">{galleryImages.map((item,index)=><button key={item.src} onClick={()=>openAssistant(item.name)} className={index===0?'featured':''}><img src={`${base}${item.src}`} alt={item.name} loading="lazy"/><span><small>{item.category}</small><strong>{item.name}</strong></span></button>)}</div>
    </section>

    <section className="coffee-gallery" id="coffee"><div className="section-title"><span>ركن القهوة</span><h2>اختار اللون المناسب</h2><p>سبعة ألوان جاهزة، 35 د.ك بدون تركيب أو 50 د.ك مع التركيب.</p></div><div>{coffeeImages.map(([src,name])=><button key={src} onClick={()=>openAssistant(`ركن القهوة — ${name}`)}><img src={`${base}${src}`} alt={`ركن القهوة ${name}`}/><span>{name}</span></button>)}</div></section>

    <section className="assistant-banner"><div><span>محتاج مساعدة في الاختيار؟</span><h2>مساعد ماركوز يجاوبك ويسجل طلبك</h2><p>اكتب أو اتكلم، وحدد المنتج والمقاسات والصورة. الطلب لا يُسجل إلا بعد تأكيدك.</p></div><button onClick={()=>openAssistant()}>🎙 افتح مساعد ماركوز</button></section>
    <footer><div className="customer-brand"><img src={`${base}/marcos-home-logo.jpg`} alt="ماركوز هوم"/><span><strong>Marco’s Home</strong><small>الكويت</small></span></div><p>الأسعار قابلة للتحديث من لوحة التحكم، والتسعير النهائي حسب المقاسات والمكونات.</p></footer>
    <a className="whatsapp-float" href="https://wa.me/96550204320?text=%D9%85%D8%B1%D8%AD%D8%A8%D8%A7%D9%8B%20%D9%85%D8%A7%D8%B1%D9%83%D9%88%D8%B2%20%D9%87%D9%88%D9%85%D8%8C%20%D8%A3%D8%B1%D8%BA%D8%A8%20%D9%81%D9%8A%20%D8%A7%D9%84%D8%A7%D8%B3%D8%AA%D9%81%D8%B3%D8%A7%D8%B1%20%D8%B9%D9%86%20%D8%A7%D9%84%D9%85%D9%86%D8%AA%D8%AC%D8%A7%D8%AA" target="_blank" rel="noreferrer" aria-label="تواصل مع ماركوز هوم على واتساب">واتساب <b>◉</b></a>
  </main>;
}
