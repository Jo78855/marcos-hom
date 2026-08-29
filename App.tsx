import React, { useMemo, useState } from 'react';
import { LayoutDashboard, ClipboardList, Users, CalendarDays, PackageSearch, MessageCircleMore, Plus, Search, ChevronLeft, ArrowRight } from 'lucide-react';

type View = 'home' | 'new-order' | 'orders' | 'customers' | 'schedule' | 'catalog' | 'assistant';

type Order = {
  id: string;
  customer: string;
  phone: string;
  area: string;
  service: string;
  width: string;
  color: string;
  installation: boolean;
  status: 'جديد' | 'مؤكد' | 'قيد التنفيذ' | 'مكتمل';
};

const starterOrders: Order[] = [];

const navItems: { id: View; label: string; icon: React.ComponentType<{size?: number}> }[] = [
  { id: 'home', label: 'الرئيسية', icon: LayoutDashboard },
  { id: 'orders', label: 'الطلبات', icon: ClipboardList },
  { id: 'customers', label: 'العملاء', icon: Users },
  { id: 'schedule', label: 'المواعيد', icon: CalendarDays },
  { id: 'catalog', label: 'المنتجات والأسعار', icon: PackageSearch },
  { id: 'assistant', label: 'مساعد ماركو', icon: MessageCircleMore },
];

export default function App() {
  const [view, setView] = useState<View>('home');
  const [orders, setOrders] = useState<Order[]>(starterOrders);

  const title = useMemo(() => navItems.find(item => item.id === view)?.label ?? 'طلب جديد', [view]);

  return (
    <div className="app" dir="rtl">
      <aside className="sidebar">
        <button className="brand" onClick={() => setView('home')}>
          <span className="brand-mark">MH</span>
          <span><strong>ماركوز هوم</strong><small>إدارة الديكورات</small></span>
        </button>
        <nav>
          {navItems.map(item => {
            const Icon = item.icon;
            return <button key={item.id} className={view === item.id ? 'active' : ''} onClick={() => setView(item.id)}><Icon size={20}/><span>{item.label}</span></button>;
          })}
        </nav>
        <button className="new-order-side" onClick={() => setView('new-order')}><Plus size={19}/> طلب جديد</button>
      </aside>

      <main className="main">
        <header className="topbar">
          <div><small>Marco’s Home</small><h1>{view === 'new-order' ? 'طلب جديد' : title}</h1></div>
          <button className="primary" onClick={() => setView('new-order')}><Plus size={18}/> طلب جديد</button>
        </header>

        {view === 'home' && <Home onOpen={setView} orderCount={orders.length} />}
        {view === 'new-order' && <NewOrder onCancel={() => setView('home')} onCreate={(order) => { setOrders(current => [order, ...current]); setView('orders'); }} />}
        {view === 'orders' && <Orders orders={orders} />}
        {view === 'customers' && <Empty title="العملاء" text="هنا هتظهر ملفات العملاء وتاريخ الطلبات والعناوين في مكان واحد." />}
        {view === 'schedule' && <Empty title="المواعيد والتركيبات" text="تقويم بسيط للمعاينات والتركيبات والمتابعات بدون قوائم معقدة." />}
        {view === 'catalog' && <Catalog />}
        {view === 'assistant' && <Empty title="مساعد ماركو" text="هنربطه بعد تثبيت الأساس الجديد بحيث يجهز الطلب ثم يطلب تأكيد واحد فقط قبل التسجيل." />}
      </main>
    </div>
  );
}

function Home({ onOpen, orderCount }: { onOpen: (view: View) => void; orderCount: number }) {
  const actions: { id: View; title: string; text: string; icon: React.ComponentType<{size?: number}> }[] = [
    { id: 'new-order', title: 'تسجيل طلب', text: 'أسرع طريق لإضافة طلب جديد', icon: Plus },
    { id: 'orders', title: 'الطلبات', text: `${orderCount} طلب مسجل`, icon: ClipboardList },
    { id: 'customers', title: 'العملاء', text: 'بيانات العميل وتاريخه', icon: Users },
    { id: 'schedule', title: 'المواعيد', text: 'المعاينات والتركيبات', icon: CalendarDays },
    { id: 'catalog', title: 'الأسعار والمنتجات', text: 'تعديل سريع وواضح', icon: PackageSearch },
    { id: 'assistant', title: 'مساعد ماركو', text: 'إنشاء طلب بالمحادثة', icon: MessageCircleMore },
  ];
  return <section className="page home-page">
    <div className="welcome"><div><span>نسخة جديدة ونظيفة</span><h2>كل شغلك من شاشة واحدة</h2><p>أقل ضغطات، أقسام واضحة، والوصول لأي مهمة في ثوانٍ.</p></div><button onClick={() => onOpen('new-order')}>ابدأ طلب جديد <ArrowRight size={18}/></button></div>
    <div className="quick-grid">{actions.map(action => { const Icon = action.icon; return <button key={action.id} onClick={() => onOpen(action.id)}><span className="icon"><Icon size={24}/></span><div><strong>{action.title}</strong><small>{action.text}</small></div><ChevronLeft size={18}/></button>; })}</div>
    <div className="status-strip"><div><small>طلبات جديدة</small><strong>{orderCount}</strong></div><div><small>تركيبات اليوم</small><strong>0</strong></div><div><small>معاينات اليوم</small><strong>0</strong></div><div><small>طلبات تحتاج متابعة</small><strong>0</strong></div></div>
  </section>;
}

function NewOrder({ onCancel, onCreate }: { onCancel: () => void; onCreate: (order: Order) => void }) {
  const [form, setForm] = useState({ customer:'', phone:'', area:'', service:'خلفية شاشة', width:'', color:'العسلي', installation:true });
  const set = (key: string, value: string | boolean) => setForm(current => ({ ...current, [key]: value }));
  const submit = (event: React.FormEvent) => {
    event.preventDefault();
    onCreate({ id: `MH-${Date.now().toString().slice(-6)}`, ...form, status:'جديد' });
  };
  return <section className="page"><div className="section-head"><div><h2>تسجيل طلب جديد</h2><p>كل البيانات المهمة في نموذج واحد فقط.</p></div></div>
    <form className="order-form" onSubmit={submit}>
      <div className="form-grid">
        <label>اسم العميل<input required value={form.customer} onChange={e => set('customer', e.target.value)} placeholder="اسم العميل"/></label>
        <label>رقم الهاتف<input required value={form.phone} onChange={e => set('phone', e.target.value)} placeholder="رقم الهاتف" inputMode="tel"/></label>
        <label>المنطقة<input value={form.area} onChange={e => set('area', e.target.value)} placeholder="مثال: حولي"/></label>
        <label>نوع الخدمة<select value={form.service} onChange={e => set('service', e.target.value)}><option>خلفية شاشة</option><option>ركن قهوة</option><option>فاير معطر</option><option>طاولة شاشة</option><option>أعمدة WPC</option><option>بانوهات</option><option>طلب خاص</option></select></label>
        <label>العرض / المقاس<input value={form.width} onChange={e => set('width', e.target.value)} placeholder="مثال: 4 متر"/></label>
        <label>اللون<select value={form.color} onChange={e => set('color', e.target.value)}><option>العسلي</option><option>أبيض</option><option>أسود</option><option>رمادي فاتح</option><option>رمادي غامق</option><option>بيج خشبي</option></select></label>
      </div>
      <div className="install-choice"><span>طريقة التنفيذ</span><button type="button" className={form.installation ? 'selected' : ''} onClick={() => set('installation', true)}>مع التركيب</button><button type="button" className={!form.installation ? 'selected' : ''} onClick={() => set('installation', false)}>بدون تركيب</button></div>
      <div className="form-actions"><button type="button" className="ghost" onClick={onCancel}>إلغاء</button><button className="primary" type="submit">تأكيد وتسجيل الطلب</button></div>
    </form>
  </section>;
}

function Orders({ orders }: { orders: Order[] }) {
  const [query, setQuery] = useState('');
  const filtered = orders.filter(o => `${o.id} ${o.customer} ${o.phone} ${o.service}`.toLowerCase().includes(query.toLowerCase()));
  return <section className="page"><div className="toolbar"><div className="search"><Search size={18}/><input value={query} onChange={e => setQuery(e.target.value)} placeholder="ابحث برقم الطلب أو العميل"/></div></div>
    <div className="table-card">{filtered.length === 0 ? <div className="empty-inline">لا توجد طلبات حتى الآن.</div> : filtered.map(order => <div className="order-row" key={order.id}><div><small>{order.id}</small><strong>{order.customer}</strong><span>{order.phone}</span></div><div><small>الخدمة</small><strong>{order.service}</strong><span>{order.width || 'بدون مقاس'}</span></div><div><small>المنطقة</small><strong>{order.area || '—'}</strong><span>{order.color}</span></div><div><span className="badge">{order.status}</span><small>{order.installation ? 'مع التركيب' : 'بدون تركيب'}</small></div></div>)}</div>
  </section>;
}

function Catalog() {
  const items = [
    ['تصميم 130','130 بدون تركيب / 170 مع التركيب'],
    ['تصميم 198','150 بدون تركيب / 198 مع التركيب'],
    ['ركن القهوة','35 بدون تركيب / 50 مع التركيب'],
    ['طاولة شاشة 1.5م','40 بدون تركيب / 50 مع التركيب'],
    ['طاولة شاشة 2م','50 بدون تركيب / 60 مع التركيب'],
    ['أعمدة WPC','5 بدون تركيب / 7 مع التركيب'],
  ];
  return <section className="page"><div className="catalog-grid">{items.map(([name,price]) => <article key={name}><strong>{name}</strong><span>{price}</span><button>تعديل</button></article>)}</div></section>;
}

function Empty({ title, text }: { title: string; text: string }) {
  return <section className="page"><div className="empty-card"><h2>{title}</h2><p>{text}</p><span>سيتم ربط البيانات في المرحلة التالية بدون تغيير شكل الاستخدام.</span></div></section>;
}
