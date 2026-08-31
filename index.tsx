import React from 'react';
import ReactDOM from 'react-dom/client';
import App from './App';
import PublicOrder from './PublicOrder';
import CoffeeStorefront from './CoffeeStorefront';
import FireStorefront from './components/FireStorefront';
import './styles.css';
import './v2-extra.css';
import './storefront.css';

const rootElement=document.getElementById('root');
if(!rootElement)throw new Error('Missing root element');
const path=window.location.pathname;
const customer=path.match(/^\/order\/customer\/([0-9a-f]{64})\/?$/i);
const technician=path.match(/^\/order\/technician\/([0-9a-f]{64})\/?$/i);
const isAdmin=path.startsWith('/admin');
const isFire=!isAdmin&&(window.location.hostname.startsWith('fire.')||path==='/fire'||path.startsWith('/fire/'));
const isCoffee=!isAdmin&&(window.location.hostname.startsWith('coffee.')||path==='/coffee'||path.startsWith('/coffee/'));
if(isFire){
  document.title='ماركوز هوم | جهاز الفير المعطر';
  document.querySelector<HTMLLinkElement>('link[rel="manifest"]')?.setAttribute('href','/fire-manifest.webmanifest');
  document.querySelector<HTMLMetaElement>('meta[name="theme-color"]')?.setAttribute('content','#b45309');
}else if(isCoffee){
  document.title='ماركوز هوم | ركن القهوة';
  document.querySelector<HTMLLinkElement>('link[rel="manifest"]')?.setAttribute('href','/manifest.webmanifest');
}
const screen=customer?<PublicOrder role="customer" token={customer[1]}/>:technician?<PublicOrder role="technician" token={technician[1]}/>:isFire?<FireStorefront/>:isCoffee?<CoffeeStorefront/>:<App/>;
ReactDOM.createRoot(rootElement).render(<React.StrictMode>{screen}</React.StrictMode>);

if('serviceWorker' in navigator){window.addEventListener('load',()=>navigator.serviceWorker.register('/sw.js'));}
