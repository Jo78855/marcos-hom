import React from 'react';
import ReactDOM from 'react-dom/client';
import App from './App';
import MarcosAssistant from './components/MarcosAssistant';
import './styles.css';
import './assistant.css';

const rootElement = document.getElementById('root');
if (!rootElement) {
  throw new Error("Could not find root element to mount to");
}

const root = ReactDOM.createRoot(rootElement);
const isFire = window.location.hostname.startsWith('fire.') || window.location.pathname.startsWith('/fire');
const isAdmin = window.location.pathname.startsWith('/admin');

if (isFire) {
  document.title = 'ماركوز هوم | جهاز الفير المعطر';
  document.querySelector<HTMLLinkElement>('link[rel="manifest"]')?.setAttribute('href', '/fire-manifest.webmanifest');
  document.querySelector<HTMLMetaElement>('meta[name="theme-color"]')?.setAttribute('content', '#b45309');
}

root.render(
  <React.StrictMode>
    <App />
    {!isAdmin && !isFire && <MarcosAssistant />}
  </React.StrictMode>
);

if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js'));
}
