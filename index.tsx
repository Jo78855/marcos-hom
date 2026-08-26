import React from 'react';
import ReactDOM from 'react-dom/client';
import App from './App';
import DecorAdmin from './components/DecorAdmin';
import OrderAccessPortal from './components/OrderAccessPortal';
import './styles.css';

const rootElement = document.getElementById('root');
if (!rootElement) {
  throw new Error("Could not find root element to mount to");
}

const root = ReactDOM.createRoot(rootElement);
const isDecorAdmin = window.location.pathname.startsWith('/decor-admin');
const isOrderPortal = window.location.pathname.startsWith('/order/');

if (isDecorAdmin) {
  document.title = 'Marco’s Home | إدارة الديكورات';
  document.querySelector<HTMLMetaElement>('meta[name="theme-color"]')?.setAttribute('content', '#111827');
} else if (window.location.hostname.startsWith('fire.') || window.location.pathname.startsWith('/fire')) {
  document.title = 'ماركوز هوم | جهاز الفير المعطر';
  document.querySelector<HTMLLinkElement>('link[rel="manifest"]')?.setAttribute('href', '/fire-manifest.webmanifest');
  document.querySelector<HTMLMetaElement>('meta[name="theme-color"]')?.setAttribute('content', '#b45309');
}

root.render(
  <React.StrictMode>
    {isDecorAdmin ? <DecorAdmin /> : isOrderPortal ? <OrderAccessPortal /> : <App />}
  </React.StrictMode>
);

if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js'));
}
