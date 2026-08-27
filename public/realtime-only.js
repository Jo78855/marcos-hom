(() => {
  const TOKEN_URL = 'https://xusgwrcjdoueajuoesvp.supabase.co/functions/v1/marcos-realtime-token';
  let pc = null;
  let stream = null;
  let audio = null;
  let active = false;
  let busy = false;

  function setButtons(text) {
    document.querySelectorAll('.mh-realtime-bar button, .mh-assistant-input .mic').forEach((b) => {
      b.textContent = text;
      b.disabled = false;
    });
  }

  function cleanup(label = '🎧 محادثة صوتية مباشرة') {
    try { pc?.close(); } catch {}
    try { stream?.getTracks().forEach(t => t.stop()); } catch {}
    try { audio?.pause(); } catch {}
    pc = null;
    stream = null;
    audio = null;
    active = false;
    busy = false;
    setButtons(label);
  }

  async function startRealtime() {
    if (active || busy) {
      cleanup();
      return;
    }
    busy = true;
    setButtons('جاري الاتصال بالصوت المباشر...');

    try {
      const tokenResp = await fetch(TOKEN_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: '{}',
        cache: 'no-store'
      });
      if (!tokenResp.ok) throw new Error(`TOKEN_${tokenResp.status}`);
      const token = await tokenResp.json();
      const key = token?.value || token?.client_secret?.value || token?.client_secret;
      if (!key) throw new Error('NO_TOKEN');

      pc = new RTCPeerConnection();
      audio = document.createElement('audio');
      audio.autoplay = true;
      audio.setAttribute('playsinline', 'true');
      document.body.appendChild(audio);
      pc.ontrack = (e) => {
        audio.srcObject = e.streams[0];
        audio.play().catch(() => {});
      };

      stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      stream.getTracks().forEach(t => pc.addTrack(t, stream));

      const dc = pc.createDataChannel('oai-events');
      dc.onopen = () => {
        active = true;
        busy = false;
        setButtons('🔴 إنهاء المحادثة الصوتية');
      };
      dc.onerror = () => cleanup('⚠️ تعذر اتصال الصوت — حاول مرة أخرى');

      const offer = await pc.createOffer();
      await pc.setLocalDescription(offer);
      const sdpResp = await fetch('https://api.openai.com/v1/realtime/calls', {
        method: 'POST',
        headers: {
          Authorization: `Bearer ${key}`,
          'Content-Type': 'application/sdp'
        },
        body: offer.sdp || ''
      });
      if (!sdpResp.ok) throw new Error(`WEBRTC_${sdpResp.status}`);
      await pc.setRemoteDescription({ type: 'answer', sdp: await sdpResp.text() });
      pc.onconnectionstatechange = () => {
        if (['failed', 'disconnected', 'closed'].includes(pc?.connectionState || '')) cleanup();
      };
    } catch (e) {
      console.error('Realtime-only error', e);
      cleanup(`⚠️ ${String(e?.message || e).slice(0, 60)}`);
    }
  }

  document.addEventListener('click', (e) => {
    const target = e.target?.closest?.('.mh-realtime-bar button, .mh-assistant-input .mic');
    if (!target) return;
    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();
    startRealtime();
  }, true);
})();
