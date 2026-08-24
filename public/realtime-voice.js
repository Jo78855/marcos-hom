(() => {
  const TOKEN_URL = 'https://xusgwrcjdoueajuoesvp.supabase.co/functions/v1/marcos-realtime-token';
  let pc = null;
  let stream = null;
  let remoteAudio = null;
  let activeButton = null;
  let connecting = false;

  function cleanup(label = '🎧 محادثة صوتية مباشرة') {
    try { pc?.close(); } catch {}
    try { stream?.getTracks().forEach(t => t.stop()); } catch {}
    if (remoteAudio) {
      try { remoteAudio.pause(); } catch {}
      remoteAudio.srcObject = null;
    }
    pc = null;
    stream = null;
    remoteAudio = null;
    connecting = false;
    if (activeButton) {
      activeButton.textContent = label;
      activeButton.dataset.rtActive = '0';
    }
  }

  async function start(button) {
    if (button.dataset.rtActive === '1' || connecting) {
      cleanup();
      return;
    }

    activeButton = button;
    connecting = true;
    button.textContent = 'جاري فتح المحادثة الصوتية...';

    try {
      const tokenResp = await fetch(TOKEN_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: '{}',
        cache: 'no-store'
      });
      if (!tokenResp.ok) throw new Error(`token ${tokenResp.status}: ${await tokenResp.text()}`);
      const token = await tokenResp.json();
      const key = token?.value || token?.client_secret?.value || token?.client_secret;
      if (!key || typeof key !== 'string') throw new Error('لم يصل مفتاح الجلسة المؤقت');

      pc = new RTCPeerConnection();
      remoteAudio = document.createElement('audio');
      remoteAudio.autoplay = true;
      remoteAudio.playsInline = true;
      remoteAudio.style.display = 'none';
      document.body.appendChild(remoteAudio);
      pc.ontrack = (event) => {
        remoteAudio.srcObject = event.streams[0];
        remoteAudio.play().catch(() => {});
      };

      stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      stream.getTracks().forEach(track => pc.addTrack(track, stream));

      const dc = pc.createDataChannel('oai-events');
      dc.onopen = () => {
        connecting = false;
        button.dataset.rtActive = '1';
        button.textContent = '🔴 إنهاء المحادثة الصوتية';
      };
      dc.onerror = () => {
        button.textContent = 'تعذر اتصال الصوت — اضغط للمحاولة';
      };

      const offer = await pc.createOffer();
      await pc.setLocalDescription(offer);

      const sdpResp = await fetch('https://api.openai.com/v1/realtime/calls', {
        method: 'POST',
        headers: {
          Authorization: `Bearer ${key}`,
          'Content-Type': 'application/sdp'
        },
        body: offer.sdp
      });
      if (!sdpResp.ok) throw new Error(`webrtc ${sdpResp.status}: ${await sdpResp.text()}`);
      await pc.setRemoteDescription({ type: 'answer', sdp: await sdpResp.text() });

      pc.onconnectionstatechange = () => {
        if (['failed', 'disconnected', 'closed'].includes(pc?.connectionState || '')) cleanup('🎧 محادثة صوتية مباشرة');
      };
    } catch (err) {
      console.error('Marcos Realtime direct error', err);
      const msg = String(err?.message || err).replace(/\s+/g, ' ').slice(0, 100);
      cleanup(`⚠️ ${msg}`);
    }
  }

  document.addEventListener('click', (event) => {
    const button = event.target.closest?.('.mh-realtime-bar button');
    if (!button) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    start(button);
  }, true);
})();
