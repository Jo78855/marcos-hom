import React, { useMemo, useState } from 'react';

type Design = { name: string; image: string };

const designs: Design[] = [
  { name: 'أبيض مع خشب فاتح', image: '/coffee/white-lightwood.webp' },
  { name: 'بني مع ترافنتينو', image: '/coffee/brown-travertine.webp' },
  { name: 'أسود مع خشب فاتح', image: '/coffee/black-lightwood.webp' },
  { name: 'رمادي غامق مع شيفرون', image: '/coffee/darkgray-chevron.webp' },
  { name: 'رمادي فاتح مع شيفرون', image: '/coffee/lightgray-chevron.webp' },
  { name: 'خشبي فاتح مع شيفرون', image: '/coffee/lightwood-chevron.webp' },
  { name: 'عسلي ماركوز هوم', image: '/coffee/honey-wood.webp' },
];

const options = [
  { label: 'بدون تركيب', price: 35 },
  { label: 'شامل التركيب', price: 50 },
];

export default function App() {
  const [design, setDesign] = useState(designs[0]);
  const [optionIndex, setOptionIndex] = useState(0);
  const [approved, setApproved] = useState(false);
  const option = options[optionIndex];
  const total = approved ? option.price : 0;

  const message = useMemo(() => [
    'مرحباً ماركوز هوم، أرغب بطلب ركن القهوة التالي:',
    `اللون: ${design.name}`,
    `طريقة الطلب: ${option.label}`,
    `السعر الإجمالي: ${option.price} د.ك`,
  ].join('\n'), [design, option]);

  const chooseDesign = (next: Design) => {
    setDesign(next);
    setApproved(false);
  };

  const chooseOption = (index: number) => {
    setOptionIndex(index);
    setApproved(false);
  };

  return (
    <main dir="rtl" className="site-shell">
      <header className="topbar">
        <div className="brand">
          <span className="brand-mark">MH</span>
          <span><strong>ماركوز هوم</strong><small>ركن القهوة</small></span>
        </div>
        <span className="status">متاح للطلب الآن</span>
      </header>

      <section className="hero">
        <div>
          <span className="eyebrow">ركن القهوة من ماركوز هوم</span>
          <h1>اختر اللون وطريقة الطلب، وأرسله مباشرة</h1>
          <p>سبعة ألوان جاهزة بسعر 35 د.ك بدون تركيب أو 50 د.ك شامل التركيب.</p>
        </div>
        <div className="total-card"><small>إجمالي طلبك</small><strong>{total} د.ك</strong></div>
      </section>

      <section className="workspace">
        <aside className="controls">
          <div className="step">
            <span>1</span>
            <div>
              <h2>اختر طريقة الطلب</h2>
              <div className="option-grid">
                {options.map((item, index) => (
                  <button key={item.label} className={optionIndex === index ? 'option active' : 'option'} onClick={() => chooseOption(index)}>
                    <strong>{item.label}</strong><small>{item.price} د.ك</small>
                  </button>
                ))}
              </div>
            </div>
          </div>

          <div className="step">
            <span>2</span>
            <div>
              <h2>اختر اللون: {design.name}</h2>
              <div className="design-grid">
                {designs.map((item) => (
                  <button key={item.name} className={design.name === item.name ? 'design active' : 'design'} onClick={() => chooseDesign(item)}>
                    <img src={item.image} alt={item.name} loading="lazy" />
                    <span>{item.name}</span>
                  </button>
                ))}
              </div>
            </div>
          </div>

          <button className="approve" onClick={() => setApproved(true)}>اعتماد الاختيار — {option.price} د.ك</button>
        </aside>

        <section className="preview">
          <div className="preview-title"><div><small>الصورة الفعلية</small><h2>ركن القهوة المختار</h2></div><span>{approved ? 'تم الاعتماد' : 'اختر ثم اعتمد'}</span></div>
          <div className="photo-wrap">
            <img src={design.image} alt={`ركن القهوة - ${design.name}`} />
            <div className="photo-label"><strong>{design.name}</strong><span>{option.price} د.ك — {option.label}</span></div>
          </div>
          <div className="summary">
            <div><small>السعر الإجمالي</small><strong>{total} د.ك</strong></div>
            <button disabled={!approved} onClick={() => window.open(`https://wa.me/96550204320?text=${encodeURIComponent(message)}`, '_blank')}>إرسال الطلب عبر واتساب</button>
          </div>
          <p className="hint">غيّر اللون أو طريقة الطلب ثم اضغط «اعتماد الاختيار» قبل الإرسال.</p>
        </section>
      </section>
    </main>
  );
}
