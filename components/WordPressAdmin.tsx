import React,{useEffect,useState}from'react';
import{supabase}from'../supabase';

const site='https://marcohom.com';
const wpAdmin=`${site}/wp-admin/`;

export default function WordPressAdmin(){
 const[status,setStatus]=useState<'checking'|'online'|'needs-auth'|'offline'>('checking');
 const[message,setMessage]=useState('جاري فحص اتصال WordPress...');
 const[user,setUser]=useState('');
 const check=async()=>{
  setStatus('checking');setMessage('جاري فحص اتصال WordPress...');
  try{
   const{data,error}=await supabase.functions.invoke('wordpress-bridge',{body:{action:'status'}});
   if(error)throw error;
   if(!data?.public_api){setStatus('offline');setMessage('تعذر الوصول إلى WordPress REST API.');return}
   if(!data?.credentials_configured){setStatus('needs-auth');setMessage('الموقع متصل، ويتبقى إضافة بيانات مصادقة WordPress الآمنة مرة واحدة داخل Supabase Secrets.');return}
   if(!data?.authenticated){setStatus('needs-auth');setMessage('بيانات المصادقة موجودة لكن WordPress لم يقبلها. نراجع اسم المستخدم وApplication Password.');return}
   setUser(data?.user?.name||data?.user?.slug||'');setStatus('online');setMessage('WordPress متصل بصلاحية التعديل من خلال الجسر الآمن.');
  }catch{setStatus('offline');setMessage('تعذر فحص جسر WordPress الآن.')}
 };
 useEffect(()=>{check()},[]);
 const badge=status==='checking'?'جاري الفحص':status==='online'?'متصل بالكامل':status==='needs-auth'?'متصل — يحتاج مصادقة':'غير متصل';
 return <main dir="rtl" className="admin-shell unified-admin"><header className="admin-header unified-header"><div><strong>WordPress — موقع ماركوز هوم</strong><small>ربط الموقع مع نظام ماركوز هوم الموحد</small></div><a href="/admin/overview">العودة للوحة التحكم</a></header><div className="admin-content"><section className="admin-card"><div className="card-title unified-title"><div><h2>حالة الاتصال</h2><small>الجسر الآمن: Supabase Edge Function → WordPress REST API</small></div><button onClick={check}>إعادة الفحص</button></div><p><b>{badge}</b> — {message}</p>{user&&<p>الحساب المتصل: <b>{user}</b></p>}<div className="order-actions"><a href={site} target="_blank" rel="noreferrer">فتح الموقع</a><a href={wpAdmin} target="_blank" rel="noreferrer">فتح لوحة WordPress</a><a href={`${site}/wp-json/`} target="_blank" rel="noreferrer">REST API</a></div></section><section className="admin-card"><h2>مصدر البيانات الموحد</h2><p>الأسعار والمواصفات تبقى في Supabase كمصدر أساسي. مساعد ماركوز يقرأ منها، وWordPress يتم تحديثه من نفس البيانات عن طريق الجسر الخلفي بدون كشف بيانات الدخول في المتصفح.</p><div className="unified-stats"><article><small>المنتجات والأسعار</small><strong>Supabase</strong></article><article><small>المساعد</small><strong>متزامن</strong></article><article><small>WordPress</small><strong>{status==='online'?'متصل':'جاهز للمصادقة'}</strong></article><article><small>Slack / واتساب</small><strong>إجراءات الطلب</strong></article></div></section><section className="admin-card"><h2>المتبقي لتفعيل التعديل التلقائي</h2><p>إنشاء Application Password من حساب WordPress المخصص وإضافته مع اسم المستخدم داخل Supabase Secrets باسم <code>WP_USERNAME</code> و<code>WP_APPLICATION_PASSWORD</code>. بعد ذلك الجسر يقدر يقرأ الصفحات ويحدّثها من لوحة ماركوز بدون تخزين كلمة المرور في GitHub أو المتصفح.</p></section></div></main>
}
