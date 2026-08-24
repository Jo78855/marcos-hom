import React,{useEffect,useState}from'react';

const site='https://marcohom.com';
const wpAdmin=`${site}/wp-admin/`;
const api=`${site}/wp-json/wp/v2/`;

export default function WordPressAdmin(){
 const[status,setStatus]=useState<'checking'|'online'|'offline'>('checking');
 const[message,setMessage]=useState('جاري فحص اتصال WordPress...');
 useEffect(()=>{(async()=>{try{const r=await fetch(`${site}/wp-json/`,{cache:'no-store'});if(!r.ok)throw new Error(String(r.status));setStatus('online');setMessage('WordPress متاح والـ REST API يرد بنجاح. يتبقى فقط ربط صلاحية التعديل بحساب WordPress مخصص.')}catch{setStatus('offline');setMessage('تعذر الوصول إلى WordPress REST API من المتصفح الآن.')}})()},[]);
 return <main dir="rtl" className="admin-shell unified-admin"><header className="admin-header unified-header"><div><strong>WordPress — موقع ماركوز هوم</strong><small>ربط الموقع مع نظام ماركوز هوم الموحد</small></div><a href="/admin/overview">العودة للوحة التحكم</a></header><div className="admin-content"><section className="admin-card"><h2>حالة الاتصال</h2><p><b>{status==='checking'?'جاري الفحص':status==='online'?'متصل':'غير متصل'}</b> — {message}</p><div className="order-actions"><a href={site} target="_blank" rel="noreferrer">فتح الموقع</a><a href={wpAdmin} target="_blank" rel="noreferrer">فتح لوحة WordPress</a><a href={`${site}/wp-json/`} target="_blank" rel="noreferrer">REST API</a></div></section><section className="admin-card"><h2>الخطة بعد المصادقة</h2><p>هيكون عندك مصدر بيانات واحد: السعر أو الوصف يتعدل من لوحة ماركوز هوم، ثم يتحدث مساعد ماركوز وWordPress من نفس البيانات.</p><div className="unified-stats"><article><small>المنتجات والأسعار</small><strong>Supabase</strong></article><article><small>المساعد</small><strong>متزامن</strong></article><article><small>WordPress</small><strong>جاهز للربط</strong></article><article><small>Slack / واتساب</small><strong>إجراءات الطلب</strong></article></div></section><section className="admin-card"><h2>المطلوب لإتاحة التعديل التلقائي</h2><p>حساب WordPress مخصص مع Application Password وصلاحية تحرير الصفحات أو المنتجات. كلمة المرور العادية لا يتم تخزينها في التطبيق. بعد إضافة بيانات المصادقة كسِرّ على الخادم، نقدر نقرأ ونعدل الصفحات عبر REST API.</p></section></div></main>
}
