export type CatalogProduct = {
  id: number;
  code: string;
  category_ar: string;
  name_ar: string;
  description_ar: string;
  price_text_ar: string;
  details_ar: string;
  active: boolean;
  sort_order: number;
};

export type GalleryImage = {src:string;name:string;category:string};

export const websiteProducts:CatalogProduct[] = [
  {id:-1,code:'design-130',category_ar:'خلفيات شاشة',name_ar:'تصميم 130',description_ar:'لوح شاشة 2×1.20م بإضاءة خلفية، طاولة معلقة 2م ولوح رأسي حتى ارتفاع 2.90م.',price_text_ar:'يبدأ من 98 د.ك شامل التوريد والتركيب',details_ar:'قابل لتعديل اللون والمقاس بعد مراجعة صورة الحائط.',active:true,sort_order:1},
  {id:-2,code:'design-198',category_ar:'خلفيات شاشة',name_ar:'تصميم 198',description_ar:'خلفية شاشة متكاملة بالخشب الهرمي، طاولة معلقة وكابينة أرفف بإضاءة داخلية.',price_text_ar:'من 130 د.ك بدون تركيب / من 170 د.ك مع التركيب',details_ar:'ارتفاع قياسي 2.90م والتسعير حسب عرض الحائط.',active:true,sort_order:2},
  {id:-3,code:'coffee-corner',category_ar:'ركن القهوة',name_ar:'ركن القهوة',description_ar:'لوح فوم بورد V حتى السقف مع كبت معلق بعرض متر، متاح بسبعة ألوان.',price_text_ar:'35 د.ك بدون تركيب / 50 د.ك مع التركيب',details_ar:'الكبت بارتفاع 25سم وعمق 32سم.',active:true,sort_order:3},
  {id:-4,code:'tv-console',category_ar:'طاولات الشاشة',name_ar:'طاولات التلفزيون المعلقة',description_ar:'طاولات معلقة بألوان خشبية ومحايدة، ارتفاع 25سم وعمق 32سم.',price_text_ar:'1.5م: 40/50 د.ك • 2م: 50/60 د.ك',details_ar:'السعر بدون تركيب/مع التركيب.',active:true,sort_order:4},
  {id:-5,code:'parquet',category_ar:'الأرضيات',name_ar:'أرضيات باركيه',description_ar:'درجات خشبية تضيف دفئًا للمساحة، مع حساب الكمية حسب الطول والعرض.',price_text_ar:'يُحسب حسب المساحة والدرجة',details_ar:'نراجع المساحة ونسبة الاحتياط قبل تأكيد الطلب.',active:true,sort_order:5},
  {id:-6,code:'wpc-columns',category_ar:'أعمدة WPC',name_ar:'فواصل وأعمدة WPC',description_ar:'فواصل بديل الخشب لتقسيم المساحات مع الحفاظ على الضوء والاتساع.',price_text_ar:'5 د.ك للعمود / 7 د.ك مع التركيب',details_ar:'مقاس 5×10سم وارتفاع حتى 2.90م.',active:true,sort_order:6},
  {id:-7,code:'fire',category_ar:'أجهزة ديكور',name_ar:'جهاز Fire Blaze المعطر',description_ar:'لهب مائي ثلاثي الأبعاد يعمل بالماء والكهرباء مع إمكانية إضافة زيت عطري.',price_text_ar:'من 85 د.ك',details_ar:'خمسة مقاسات من 40سم إلى 1.50م، مع صيانة وقطع غيار.',active:true,sort_order:7},
  {id:-8,code:'bedroom-wall',category_ar:'خلفيات سرير',name_ar:'خلفيات السرير',description_ar:'تكوينات هادئة بدرجات خشبية ومحايدة وإضاءة مخفية تناسب غرفة النوم.',price_text_ar:'السعر حسب المقاس والخامات',details_ar:'يُعتمد بعد صورة المكان والمقاسات.',active:true,sort_order:8},
];

export const galleryImages:GalleryImage[] = [
  {src:'/catalog/design-198/beige-wood.webp',name:'تصميم 198 — بيج خشبي',category:'تصميم 198'},
  {src:'/catalog/design-198/white.webp',name:'تصميم 198 — أبيض',category:'تصميم 198'},
  {src:'/catalog/design-198/charcoal.webp',name:'تصميم 198 — رمادي غامق',category:'تصميم 198'},
  {src:'/catalog/design-198/dimensions.webp',name:'مقاسات تصميم 198',category:'تصميم 198'},
  {src:'/catalog/tv-walls/tv-wall-modern.jpg',name:'خلفية شاشة مودرن',category:'خلفيات الشاشة'},
  {src:'/catalog/tv-walls/tv-wall-executed.jpg',name:'خلفية شاشة وطاولة معلقة',category:'خلفيات الشاشة'},
  {src:'/catalog/tv-walls/tv-wall-wood.jpg',name:'خلفية شاشة خشبية',category:'خلفيات الشاشة'},
  {src:'/catalog/tv-walls/tv-wall-neutral.jpg',name:'تصميم حائط بألوان محايدة',category:'خلفيات الشاشة'},
  {src:'/catalog/tv-walls/tv-wall-concept.png',name:'تصميم خلفية شاشة',category:'خلفيات الشاشة'},
  {src:'/catalog/tv-walls/tv-wall-complete.png',name:'خلفية شاشة متكاملة',category:'خلفيات الشاشة'},
  {src:'/catalog/works/bedroom-gray.jpeg',name:'خلفية سرير رمادية',category:'خلفيات السرير'},
  {src:'/catalog/works/bedroom-wall.jpeg',name:'خلفية سرير منفذة',category:'خلفيات السرير'},
  {src:'/catalog/works/warm-bedroom.jpeg',name:'غرفة بإضاءة دافئة',category:'خلفيات السرير'},
  {src:'/catalog/works/wood-room.jpeg',name:'غرفة بدرجات خشبية',category:'خلفيات السرير'},
  {src:'/catalog/tables/table-hero.png',name:'طاولة تلفزيون معلقة',category:'طاولات الشاشة'},
  {src:'/catalog/tables/walnut.webp',name:'عسلي خشبي',category:'طاولات الشاشة'},
  {src:'/catalog/tables/charcoal.webp',name:'رمادي غامق',category:'طاولات الشاشة'},
  {src:'/catalog/tables/light-gray.webp',name:'رمادي فاتح',category:'طاولات الشاشة'},
  {src:'/catalog/tables/white.webp',name:'أبيض',category:'طاولات الشاشة'},
  {src:'/catalog/tables/black.webp',name:'أسود',category:'طاولات الشاشة'},
  {src:'/catalog/tables/light-oak.webp',name:'بيج خشبي',category:'طاولات الشاشة'},
  {src:'/catalog/tables/dark-walnut.webp',name:'جوزي غامق',category:'طاولات الشاشة'},
  {src:'/catalog/tables/installed-white.jpg',name:'تنفيذ أبيض',category:'تنفيذ الطاولات'},
  {src:'/catalog/tables/installed-black.jpg',name:'تنفيذ أسود',category:'تنفيذ الطاولات'},
  {src:'/catalog/tables/installed-light-gray.jpg',name:'تنفيذ رمادي فاتح',category:'تنفيذ الطاولات'},
  {src:'/catalog/tables/installed-dark-gray.jpg',name:'تنفيذ رمادي غامق',category:'تنفيذ الطاولات'},
  {src:'/catalog/tables/installed-light-oak.jpg',name:'تنفيذ بيج خشبي',category:'تنفيذ الطاولات'},
  {src:'/catalog/tables/installed-honey.jpg',name:'تنفيذ عسلي',category:'تنفيذ الطاولات'},
  {src:'/catalog/parquet/room-main.jpeg',name:'باركيه داخل غرفة معيشة',category:'الباركيه'},
  {src:'/catalog/parquet/warm-wood.jpg',name:'باركيه بدرجة دافئة',category:'الباركيه'},
  {src:'/catalog/parquet/interior.jpg',name:'أرضيات باركيه',category:'الباركيه'},
  {src:'/catalog/wpc/main.jpg',name:'فواصل WPC',category:'أعمدة WPC'},
  {src:'/catalog/wpc/interior.jpg',name:'فاصل WPC داخل الصالة',category:'أعمدة WPC'},
  {src:'/catalog/wpc/walnut-divider.webp',name:'فاصل WPC جوزي',category:'أعمدة WPC'},
  {src:'/catalog/wpc/columns.png',name:'قاطع أعمدة بديل الخشب',category:'أعمدة WPC'},
  {src:'/catalog/fire/product-main.webp',name:'جهاز Fire Blaze',category:'الفاير'},
  {src:'/catalog/fire/inside-unit.webp',name:'الفاير داخل وحدة ديكور',category:'الفاير'},
  {src:'/catalog/fire/flame-1.webp',name:'لهب مائي ثلاثي الأبعاد',category:'الفاير'},
  {src:'/catalog/fire/flame-2.webp',name:'الفاير للمساحات الداخلية',category:'الفاير'},
];

export const imageForProduct = (code:string,name:string) => {
  const value=`${code} ${name}`.toLowerCase();
  if(value.includes('198'))return '/catalog/design-198/beige-wood.webp';
  if(value.includes('130')||value.includes('خلف'))return '/catalog/tv-walls/tv-wall-modern.jpg';
  if(value.includes('coffee')||value.includes('قهوة'))return '/coffee/brown-travertine.webp';
  if(value.includes('table')||value.includes('console')||value.includes('طاولة'))return '/catalog/tables/walnut.webp';
  if(value.includes('parquet')||value.includes('باركيه')||value.includes('أرض'))return '/catalog/parquet/room-main.jpeg';
  if(value.includes('wpc')||value.includes('عمود')||value.includes('فاصل'))return '/catalog/wpc/main.jpg';
  if(value.includes('fire')||value.includes('فاير'))return '/catalog/fire/product-main.webp';
  if(value.includes('bedroom')||value.includes('سرير'))return '/catalog/works/bedroom-wall.jpeg';
  if(value.includes('foam')||value.includes('فوم'))return '/catalog/design-198/dimensions.webp';
  return '/catalog/tv-walls/tv-wall-concept.png';
};
