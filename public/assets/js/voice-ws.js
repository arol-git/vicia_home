(() => {
  const $ = id => document.getElementById(id);
  const brokerEl = $('broker');
  const topicEl = $('topic');
  const userEl = $('username');
  const passEl = $('password');
  const connectBtn = $('connect');
  const disconnectBtn = $('disconnect');
  const connStatus = $('connStatus');
  const startBtn = $('startListen');
  const stopBtn = $('stopListen');
  const transcriptEl = $('transcript');
  const publishBtn = $('publish');
  const logEl = $('log');

  let client = null;
  let recognition = null;

  function log(...args) {
    const line = '[' + new Date().toISOString() + '] ' + args.join(' ');
    logEl.textContent = line + '\n' + logEl.textContent;
    console.log(...args);
  }

  function setConnected(connected) {
    connectBtn.disabled = connected;
    disconnectBtn.disabled = !connected;
    startBtn.disabled = !connected;
    connStatus.textContent = connected ? 'Connecté' : 'Non connecté';
  }

  function connect() {
    const broker = brokerEl.value.trim();
    if (!broker) return alert('Indiquez l\'URL du broker (ws:// ou wss://)');
    const topic = topicEl.value.trim() || 'home/voice/command';
    const opts = { reconnectPeriod: 2000 };
    if (userEl.value) opts.username = userEl.value;
    if (passEl.value) opts.password = passEl.value;

    try {
      client = mqtt.connect(broker, opts);
    } catch (e) {
      log('Erreur connexion mqtt:', e.message || e);
      return;
    }

    client.on('connect', () => {
      log('MQTT connecté', broker);
      setConnected(true);
    });
    client.on('reconnect', () => log('MQTT reconnecting'));
    client.on('error', err => log('MQTT erreur', err && err.message || err));
    client.on('close', () => { log('MQTT closed'); setConnected(false); });
  }

  function disconnect() {
    if (!client) return;
    client.end(true, () => log('MQTT déconnecté'));
    client = null;
    setConnected(false);
  }

  function publishMessage(text, extra = {}) {
    if (!client || client.connected === false) return alert('Non connecté au broker');
    const topic = topicEl.value.trim() || 'home/voice/command';
    const payload = Object.assign({
      text: text,
      timestamp: new Date().toISOString()
    }, extra);
    const msg = JSON.stringify(payload);
    client.publish(topic, msg, { qos: 0 }, err => {
      if (err) log('Erreur publish', err);
      else log('Publié', topic, msg);
    });
  }

  function setupRecognition() {
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SpeechRecognition) {
      startBtn.disabled = true;
      stopBtn.disabled = true;
      transcriptEl.textContent = 'API SpeechRecognition non disponible dans ce navigateur.';
      return;
    }
    recognition = new SpeechRecognition();
    recognition.lang = 'fr-FR';
    recognition.interimResults = false;
    recognition.maxAlternatives = 1;

    recognition.onstart = () => { log('Reconnaissance démarrée'); };
    recognition.onerror = e => { log('Reconnaissance erreur', e.error || e.message); };
    recognition.onend = () => { log('Reconnaissance terminée'); startBtn.disabled = false; stopBtn.disabled = true; };
    recognition.onresult = (ev) => {
      const text = ev.results[0][0].transcript;
      transcriptEl.textContent = text;
      publishBtn.disabled = false;
      // Auto publish
      publishMessage(text, { confidence: ev.results[0][0].confidence });
    };
  }

  connectBtn.addEventListener('click', () => connect());
  disconnectBtn.addEventListener('click', () => disconnect());
  startBtn.addEventListener('click', () => {
    if (!recognition) return;
    try { recognition.start(); startBtn.disabled = true; stopBtn.disabled = false; } catch (e) { log('start error', e); }
  });
  stopBtn.addEventListener('click', () => { if (recognition) recognition.stop(); });
  publishBtn.addEventListener('click', () => {
    const text = transcriptEl.textContent || '';
    if (!text) return alert('Aucun texte à publier');
    publishMessage(text);
  });

  // Init
  setConnected(false);
  setupRecognition();
  log('Interface prête');
})();
