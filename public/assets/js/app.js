document.addEventListener('click', (event) => {
  const opener = event.target.closest('[data-open-modal]');
  if (opener) document.getElementById(opener.dataset.openModal)?.showModal();
  if (event.target.closest('[data-close-modal]')) event.target.closest('dialog')?.close();
  if (event.target.matches('.modal')) event.target.close();
  const toastClose = event.target.closest('.toast button');
  if (toastClose) toastClose.parentElement.remove();
});
setTimeout(() => document.querySelectorAll('.toast').forEach((toast) => toast.classList.add('toast--leaving')), 4500);

const liveList = document.querySelector('[data-live-list]');

if (liveList) {
  const stateUrl = liveList.dataset.stateUrl;
  const toggleUrl = liveList.dataset.toggleUrl;
  const csrfToken = liveList.dataset.csrfToken;
  let currentRevision = null;
  let requestSequence = 0;
  let appliedSequence = 0;
  let pollInProgress = false;

  const memberAvatar = (member) => {
    const avatar = document.createElement('i');
    avatar.className = member.is_online ? 'member-avatar--online' : '';
    avatar.title = `${member.name} is ${member.is_online ? 'online' : 'offline'}`;
    avatar.setAttribute('aria-label', avatar.title);
    avatar.textContent = Array.from(member.name.trim().toLocaleUpperCase('nl-NL'))[0] || '?';
    return avatar;
  };

  const renderMembers = (members) => {
    liveList.querySelector('[data-plan-label]').textContent = members.length > 1
      ? 'Een plan van jullie samen'
      : 'Jouw persoonlijke plan';
    liveList.querySelector('[data-member-count]').textContent = `${members.length} ${members.length === 1 ? 'persoon' : 'personen'}`;

    liveList.querySelectorAll('[data-member-stack]').forEach((stack) => {
      const limit = stack.dataset.memberStack === 'header' ? 3 : 4;
      stack.replaceChildren(...members.slice(0, limit).map(memberAvatar));
    });
  };

  const renderEmptyState = () => {
    const empty = document.createElement('div');
    empty.className = 'tasks-empty';
    const icon = document.createElement('span');
    icon.textContent = '☻';
    const title = document.createElement('h3');
    title.textContent = 'Nog lekker rustig hier';
    const copy = document.createElement('p');
    copy.textContent = 'Welke kleine stap zetten jullie als eerste?';
    empty.append(icon, title, copy);
    return empty;
  };

  const renderTask = (item) => {
    const form = document.createElement('form');
    form.method = 'post';
    form.action = toggleUrl.replace('__ITEM_ID__', encodeURIComponent(item.id));
    form.className = `task${item.is_completed ? ' task--done' : ''}`;
    form.dataset.liveToggle = '';

    const token = document.createElement('input');
    token.type = 'hidden';
    token.name = '_token';
    token.value = csrfToken;

    const check = document.createElement('button');
    check.className = 'task-check';
    check.setAttribute('aria-label', item.is_completed ? 'Markeer als niet gedaan' : 'Vink af');
    check.textContent = item.is_completed ? '✓' : '';

    const content = document.createElement('button');
    content.className = 'task-content';
    const title = document.createElement('strong');
    title.textContent = item.title;
    const attribution = document.createElement('small');
    attribution.textContent = item.is_completed
      ? `Afgevinkt door ${item.completer_name}`
      : `Toegevoegd door ${item.creator_name}`;
    content.append(title, attribution);

    form.append(token, check, content);
    return form;
  };

  const applyState = (state, sequence) => {
    if (sequence < appliedSequence) return;
    appliedSequence = sequence;
    if (state.revision === currentRevision) return;
    currentRevision = state.revision;

    const { done, total, open, percent } = state.stats;
    liveList.querySelector('[data-progress-copy]').textContent = total
      ? `${done} van de ${total} taken afgevinkt`
      : 'Voeg hieronder jullie eerste taak toe.';
    liveList.querySelector('[data-progress-bar]').style.width = `${percent}%`;
    liveList.querySelector('[data-open-count]').textContent = `${open} open`;

    const taskContainer = liveList.querySelector('[data-task-container]');
    if (state.items.length === 0) {
      taskContainer.replaceChildren(renderEmptyState());
    } else {
      const taskList = document.createElement('div');
      taskList.className = 'task-list';
      taskList.append(...state.items.map(renderTask));
      taskContainer.replaceChildren(taskList);
    }

    renderMembers(state.members);
  };

  const requestState = async () => {
    if (pollInProgress || document.hidden) return;
    pollInProgress = true;
    const sequence = ++requestSequence;
    try {
      const response = await fetch(stateUrl, {
        headers: { Accept: 'application/json' },
        cache: 'no-store',
      });
      if (!response.ok) throw new Error(`Synchroniseren mislukt (${response.status})`);
      applyState(await response.json(), sequence);
    } catch (error) {
      console.warn(error);
    } finally {
      pollInProgress = false;
    }
  };

  document.addEventListener('submit', async (event) => {
    const form = event.target.closest('[data-live-add], [data-live-toggle]');
    if (!form || !liveList.contains(form)) return;
    event.preventDefault();
    if (form.dataset.submitting === 'true') return;

    form.dataset.submitting = 'true';
    const sequence = ++requestSequence;
    try {
      const response = await fetch(form.action, {
        method: 'POST',
        headers: { Accept: 'application/json' },
        body: new FormData(form),
      });
      if (!response.ok) throw new Error(`Wijziging opslaan mislukt (${response.status})`);
      applyState(await response.json(), sequence);
      if (form.matches('[data-live-add]')) form.reset();
    } catch (error) {
      console.error(error);
      window.alert('De wijziging kon niet worden opgeslagen. Probeer het opnieuw.');
    } finally {
      delete form.dataset.submitting;
    }
  });

  requestState();
  const pollTimer = window.setInterval(requestState, 2000);
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) requestState();
  });
  window.addEventListener('focus', requestState);
  window.addEventListener('pagehide', () => window.clearInterval(pollTimer), { once: true });
}

const serviceWorkerUrl = document.body.dataset.serviceWorker;
const appScope = document.body.dataset.appScope;
if ('serviceWorker' in navigator && serviceWorkerUrl) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register(serviceWorkerUrl, { scope: appScope }).catch((error) => {
      console.warn('Service worker registreren is mislukt.', error);
    });
  });
}

const installCard = document.querySelector('[data-pwa-install]');
const installButton = document.querySelector('[data-install-button]');
let deferredInstallPrompt = null;

const standaloneDisplay = window.matchMedia('(display-mode: standalone)');
const isInstalledApp = standaloneDisplay.matches || window.navigator.standalone === true;
const isIos = /iphone|ipad|ipod/i.test(window.navigator.userAgent);

const hideInstallCard = () => {
  deferredInstallPrompt = null;
  if (installCard) installCard.hidden = true;
};

if (installCard && installButton && !isInstalledApp) {
  if (isIos) {
    installCard.hidden = false;
    installButton.textContent = 'Bekijk stappen';
    installButton.addEventListener('click', () => document.getElementById('install-help')?.showModal());
  }

  window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    deferredInstallPrompt = event;
    installCard.hidden = false;
  });

  installButton.addEventListener('click', async () => {
    if (!deferredInstallPrompt) return;
    deferredInstallPrompt.prompt();
    await deferredInstallPrompt.userChoice;
    deferredInstallPrompt = null;
    installCard.hidden = true;
  });

  window.addEventListener('appinstalled', hideInstallCard);
  standaloneDisplay.addEventListener?.('change', (event) => {
    if (event.matches) hideInstallCard();
  });
} else if (installCard) {
  hideInstallCard();
}

const avatarInput = document.querySelector('[data-avatar-input]');
const avatarPreview = document.querySelector('[data-avatar-preview]');
let avatarObjectUrl = null;

avatarInput?.addEventListener('change', () => {
  const [file] = avatarInput.files;
  if (!file || !avatarPreview) return;

  if (avatarObjectUrl) URL.revokeObjectURL(avatarObjectUrl);
  avatarObjectUrl = URL.createObjectURL(file);
  const image = avatarPreview.querySelector('img');
  const initial = avatarPreview.querySelector('strong');
  image.src = avatarObjectUrl;
  image.hidden = false;
  if (initial) initial.hidden = true;
});

window.addEventListener('pagehide', () => {
  if (avatarObjectUrl) URL.revokeObjectURL(avatarObjectUrl);
}, { once: true });


const pushRoot = document.body.matches('[data-push-notifications]') ? document.body : null;
const beamsPush = document.querySelector('[data-beams-push]');
const consentCard = document.querySelector('[data-push-consent]');
const consentButton = document.querySelector('[data-push-consent-button]');
const consentText = document.querySelector('[data-push-consent-text]');

if (pushRoot) {
  const status = beamsPush?.querySelector('[data-beams-status]');
  const subscribeButton = beamsPush?.querySelector('[data-beams-subscribe]');
  const unsubscribeButton = beamsPush?.querySelector('[data-beams-unsubscribe]');
  let beamsClient = null;
  let currentInterest = '';

  const setStatus = (message, isError = false) => {
    if (status) {
      status.textContent = message;
      status.classList.toggle('form-error', isError);
    }
    if (consentText && message) consentText.textContent = message;
  };

  const notificationPermission = () => ('Notification' in window ? Notification.permission : 'unsupported');

  const setControls = (registered) => {
    if (subscribeButton) subscribeButton.hidden = Boolean(registered);
    if (unsubscribeButton) unsubscribeButton.hidden = !registered;
    if (consentCard) consentCard.hidden = Boolean(registered || notificationPermission() === 'denied');
  };

  const deviceInterest = (deviceId) => `samen_device_${String(deviceId || '').replace(/[^A-Za-z0-9_\-=@,.;]/g, '_').slice(0, 150)}`;

  const readJsonResponse = async (response) => {
    const text = await response.text();
    if (!text.trim()) {
      throw new Error(response.ok ? 'De server gaf geen antwoord terug.' : `Opslaan mislukt (${response.status}).`);
    }
    try {
      return JSON.parse(text);
    } catch (error) {
      throw new Error(response.ok ? 'De server gaf geen geldige JSON terug.' : `Opslaan mislukt (${response.status}).`);
    }
  };

  const postToken = async (endpoint, token) => {
    const body = new FormData();
    body.append('_token', pushRoot.dataset.csrfToken);
    body.append('token', token);
    const response = await fetch(endpoint, { method: 'POST', headers: { Accept: 'application/json' }, body });
    const result = await readJsonResponse(response);
    if (!response.ok || !result.ok) throw new Error(result.message || 'Opslaan mislukt.');
    return result;
  };

  const initializeBeams = async () => {
    if (!window.isSecureContext) throw new Error('Pushnotificaties vereisen HTTPS.');
    if (!('Notification' in window) || !('serviceWorker' in navigator)) throw new Error('Deze browser ondersteunt geen web-push.');
    if (!window.PusherPushNotifications?.Client) throw new Error('De Pusher Beams SDK kon niet worden geladen.');
    const serviceWorkerRegistration = await navigator.serviceWorker.ready;
    beamsClient = beamsClient || new window.PusherPushNotifications.Client({
      instanceId: pushRoot.dataset.pushInstanceId,
      serviceWorkerRegistration,
    });
    return beamsClient;
  };

  const registerDevice = async (allowPrompt = false) => {
    const client = await initializeBeams();
    if (notificationPermission() === 'denied') {
      setStatus('Notificaties zijn in de browser geblokkeerd. Pas de site-instellingen aan om updates te ontvangen.', true);
      setControls(false);
      return false;
    }

    if (!allowPrompt && notificationPermission() !== 'granted') {
      setStatus('Tik één keer op toestaan om updates van gedeelde lijstjes te ontvangen.');
      setControls(false);
      return false;
    }

    await client.start();
    const deviceId = await client.getDeviceId();
    if (!deviceId) throw new Error('Pusher Beams kon geen apparaat-ID aanmaken.');
    currentInterest = deviceInterest(deviceId);
    const interests = await client.getDeviceInterests().catch(() => []);
    if (!interests.includes(currentInterest)) {
      await client.addDeviceInterest(currentInterest);
    }
    const result = await postToken(pushRoot.dataset.pushSubscribeEndpoint, currentInterest);
    setStatus(result.message || 'Meldingen zijn actief op dit apparaat.');
    setControls(true);
    return true;
  };

  const unregisterDevice = async () => {
    const client = await initializeBeams();
    if (!currentInterest) {
      const deviceId = await client.getDeviceId().catch(() => '');
      currentInterest = deviceId ? deviceInterest(deviceId) : '';
    }
    if (currentInterest) {
      await client.removeDeviceInterest(currentInterest).catch(() => {});
      await postToken(pushRoot.dataset.pushUnsubscribeEndpoint, currentInterest);
    }
    await client.stop();
    currentInterest = '';
    setStatus('Dit apparaat is afgemeld.');
    setControls(false);
  };

  consentButton?.addEventListener('click', async () => {
    consentButton.disabled = true;
    try {
      await registerDevice(true);
    } catch (error) {
      setStatus(error.message, true);
    } finally {
      consentButton.disabled = false;
    }
  });

  subscribeButton?.addEventListener('click', async () => {
    subscribeButton.disabled = true;
    try {
      await registerDevice(true);
    } catch (error) {
      setStatus(error.message, true);
    } finally {
      subscribeButton.disabled = false;
    }
  });

  unsubscribeButton?.addEventListener('click', async () => {
    unsubscribeButton.disabled = true;
    try {
      await unregisterDevice();
    } catch (error) {
      setStatus(error.message, true);
    } finally {
      unsubscribeButton.disabled = false;
    }
  });

  window.addEventListener('load', () => {
    registerDevice(false).catch((error) => {
      setStatus(error.message, true);
      setControls(false);
    });
  });
} else if (beamsPush) {
  const status = beamsPush.querySelector('[data-beams-status]');
  if (status) status.textContent = 'Configureer Pusher Beams voordat je apparaten kunt registreren.';
}
