import React from 'react';
import ReactDOM from 'react-dom/client';
import App from './App';
import PublicOrder from './PublicOrder';
import './styles.css';
import './v2-extra.css';

const rootElement=document.getElementById('root');
if(!rootElement)throw new Error('Missing root element');
const path=window.location.pathname;
const customer=path.match(/^\/order\/customer\/([0-9a-f-]+)$/i);
const technician=path.match(/^\/order\/technician\/([0-9a-f-]+)$/i);
const screen=customer?<PublicOrder role="customer" token={customer[1]}/>:technician?<PublicOrder role="technician" token={technician[1]}/>:<App/>;
ReactDOM.createRoot(rootElement).render(<React.StrictMode>{screen}</React.StrictMode>);
