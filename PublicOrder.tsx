import React,{useEffect,useState} from 'react';
import {supabase} from './supabase';

type PublicOrderData={order_number:string;customer_name:string;area:string|null;service_type:string;measurement:string|null;color:string|null;installation:boolean;status:string;technician_name:string|null;customer_confirmed:boolean;technician_status:string};

export default function PublicOrder({role,token}:{role:'customer'|'technician';token:string}){
 const[data,setData]=useState<PublicOrderData|null>(null),[loading,setLoading]=useState(true),[error,setError]=useState('');
 useEffect(()=>{(async()=>{const column=role==='customer'?'customer_token':'technician_token';const{data,error}=await supabase.from('mh2_orders').select('order_number,customer_name,area,service_type,measurement,color,installation,status,technician_name,customer_confirmed,technician_status').eq(column,token).maybeSingle();if(error||!data)setError('الرابط غير صالح أو الطلب غير موجود');else setData(data as PublicOrderData);setLoading(false)})()},[role,token]);
 if(loading)return <main className="public-order" dir="rtl"><div className="public-card">جاري تحميل الطلب…</div></main>;
 if(error||!data)return <main className="public-order" dir="rtl"><div className="public-card"><h1>ماركوز هوم</h1><p>{error}</p></div></main>;
 return <main className="public-order" dir="rtl"><div className="public-card"><div className="public-brand"><b>MH</b><span><strong>ماركوز هوم</strong><small>{role==='customer'?'متابعة طلبك':'مهمة الفني'}</small></span></div><h1>طلب {data.order_number}</h1><div className="public-status">{data.status}</div><div className="public-details"><p><span>الخدمة</span><b>{data.service_type}</b></p><p><span>المقاس</span><b>{data.measurement||'—'}</b></p><p><span>اللون</span><b>{data.color||'—'}</b></p><p><span>المنطقة</span><b>{data.area||'—'}</b></p><p><span>التنفيذ</span><b>{data.installation?'مع التركيب':'بدون تركيب'}</b></p>{role==='technician'&&<p><span>العميل</span><b>{data.customer_name}</b></p>}</div>{role==='customer'?<div className="public-note">من هنا يتابع العميل حالة الطلب، وبعد التنفيذ سيتم تفعيل تأكيد الاستلام والتقييم.</div>:<div className="public-note">من هنا يرى الفني المهمة فقط بدون أسعار. سيتم تفعيل أزرار: في الطريق، بدأ العمل، تم، توجد مشكلة.</div>}</div></main>;
}
