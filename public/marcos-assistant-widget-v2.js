(function(){
  if(window.__marcosAssistantWidgetLoaded) return;
  window.__marcosAssistantWidgetLoaded=true;
  var host='https://coffee.marcohom.com';
  var wrap=document.createElement('div');
  wrap.id='marcos-assistant-widget-host';
  wrap.style.cssText='position:fixed;z-index:2147483000;left:18px;right:auto;bottom:18px;font-family:Arial,sans-serif;direction:rtl';
  var btn=document.createElement('button');
  btn.type='button';
  btn.innerHTML='🎙 <span>اسأل ماركوز هوم</span>';
  btn.style.cssText='border:0;border-radius:999px;padding:14px 18px;background:#1258a6;color:#fff;font-weight:800;box-shadow:0 12px 32px rgba(0,0,0,.22);cursor:pointer';
  var frame=document.createElement('iframe');
  frame.src=host+'/assistant/embed';
  frame.title='مساعد ماركوز هوم';
  frame.allow='microphone';
  frame.style.cssText='display:none;width:min(410px,calc(100vw - 24px));height:min(620px,75vh);border:0;border-radius:22px;box-shadow:0 22px 70px rgba(17,24,39,.25);background:transparent;margin-bottom:10px';
  btn.onclick=function(){frame.style.display=frame.style.display==='none'?'block':'none';};
  wrap.appendChild(frame);wrap.appendChild(btn);document.body.appendChild(wrap);
})();