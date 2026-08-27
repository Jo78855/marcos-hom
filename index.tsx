import React from 'react';
import ReactDOM from 'react-dom/client';
import App from './App';
import DecorAdmin from './components/DecorAdmin';
import OrderAccessPortal from './components/OrderAccessPortal';
import MarcosAssistant from './components/MarcosAssistant';
import AssistantAdmin from './components/AssistantAdmin';
import UnifiedAdmin from './components/UnifiedAdmin';
import WordPressAdmin from './components/WordPressAdmin';
import './styles.css';
import './assistant.css';
import './unified-admin.css';

const rootElement = document.getElementById('root');
if (!rootElement) throw new Error("Could not find root element to mount to");

const root = ReactDOM.createRoot(rootElement);
const previewBase = window.location.hostname.endsWith('github.io') ? '/marcos-hom' : '';
const path = previewBase && window.location.pathname.startsWith(previewBase)
  ? window.location.pathname.slice(previewBase.length) || '/'
  : window.location.pathname;
const isFire = window.location.hostname.startsWith('fire.') || path.startsWith('/fire');
const isAdmin = path.startsWith('/admin');
const isDecorAdmin = path.startsWith('/decor-admin');
const isOrderPortal = path.startsWith('/order/');
const isWordPressAdmin = path.startsWith('/admin/wordpress');
const isUnifiedAdmin = (path === '/admin' || path.startsWith('/admin/overview')) && !isWordPressAdmin;
const isAssistantAdmin = path.startsWith('/admin/assistant');
const isAssistantEmbed = path.startsWith('/assistant/embed');

if (isDecorAdmin) {
  document.title = 'Marco’s Home | إدارة الديكورات';
  document.querySelector<HTMLMetaElement>('meta[name="theme-color"]')?.setAttribute('content', '#111827');
} else if (isUnifiedAdmin || isWordPressAdmin) {
  document.title = isWordPressAdmin ? 'ماركوز هوم | WordPress' : 'ماركوز هوم | لوحة التحكم';
  document.querySelector<HTMLLinkElement>('link[rel="manifest"]')?.setAttribute('href', '/admin-manifest.webmanifest');
  document.querySelector<HTMLMetaElement>('meta[name="theme-color"]')?.setAttribute('content', '#0a376a');
} else if (isFire) {
  document.title = 'ماركوز هوم | جهاز الفير المعطر';
  document.querySelector<HTMLLinkElement>('link[rel="manifest"]')?.setAttribute('href', '/fire-manifest.webmanifest');
  document.querySelector<HTMLMetaElement>('meta[name="theme-color"]')?.setAttribute('content', '#b45309');
}
if (isAssistantEmbed) document.title = 'مساعد ماركوز هوم';

root.render(
  <React.StrictMode>
    {isDecorAdmin ? <DecorAdmin /> : isOrderPortal ? <OrderAccessPortal /> : isAssistantEmbed ? <MarcosAssistant embedded /> : isAssistantAdmin ? <AssistantAdmin /> : isWordPressAdmin ? <WordPressAdmin /> : isUnifiedAdmin ? <UnifiedAdmin /> : <App />}
    {!isAssistantEmbed && !isAdmin && !isDecorAdmin && !isOrderPortal && !isFire && <MarcosAssistant />}
  </React.StrictMode>
);

if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js'));
}
