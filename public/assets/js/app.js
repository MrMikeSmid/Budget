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

const oneSignalAppId = document.body.dataset.onesignalAppId;
const oneSignalUser = document.body.dataset.onesignalUser;
const pushSettings = document.querySelector('[data-push-settings]');

if (oneSignalAppId && oneSignalUser) {
  window.OneSignalDeferred = window.OneSignalDeferred || [];
  window.OneSignalDeferred.push(async (OneSignal) => {
    const status = pushSettings?.querySelector('[data-push-status]');
    const toggle = pushSettings?.querySelector('[data-push-toggle]');
    const deleteButton = pushSettings?.querySelector('[data-push-delete]');
    const workerPath = document.body.dataset.onesignalWorker;
    const workerScope = document.body.dataset.onesignalScope;

    try {
      await OneSignal.init({
        appId: oneSignalAppId,
        serviceWorkerPath: workerPath,
        serviceWorkerParam: { scope: workerScope },
      });
      await OneSignal.login(oneSignalUser);

      const nativePermission = () => {
        if (typeof Notification !== 'undefined' && Notification.permission) {
          return Notification.permission;
        }
        return OneSignal.Notifications.permissionNative || 'default';
      };

      const waitFor = async (readValue, timeout = 15000) => {
        const deadline = Date.now() + timeout;
        let value = readValue();
        while (!value && Date.now() < deadline) {
          await new Promise((resolve) => window.setTimeout(resolve, 250));
          value = readValue();
        }
        return value;
      };

      const waitForPermission = () => waitFor(
        () => nativePermission() === 'granted',
        5000,
      );
      const hasActivePushSubscription = () => nativePermission() === 'granted'
        && OneSignal.User.PushSubscription.optedIn
        && Boolean(OneSignal.User.PushSubscription.id)
        && Boolean(OneSignal.User.PushSubscription.token);
      const waitForActiveSubscription = () => waitFor(
        () => hasActivePushSubscription(),
      );

      const updatePushStatus = () => {
        if (!pushSettings || !status || !toggle) return;
        const isIosBrowser = /iphone|ipad|ipod/i.test(window.navigator.userAgent);
        const runsStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
        const supported = OneSignal.Notifications.isPushSupported();
        const permission = nativePermission();
        const optedIn = OneSignal.User.PushSubscription.optedIn;
        const subscriptionId = OneSignal.User.PushSubscription.id;
        const active = hasActivePushSubscription();

        pushSettings.dataset.pushActive = String(active);
        if (deleteButton) deleteButton.hidden = !subscriptionId;
        toggle.disabled = false;
        if (active) {
          status.textContent = 'Meldingen staan aan op dit apparaat.';
          toggle.textContent = 'Meldingen uitzetten';
        } else if (isIosBrowser && !runsStandalone) {
          status.textContent = 'Installeer Samen eerst op je beginscherm; daarna kun je meldingen aanzetten.';
          toggle.textContent = 'Bekijk installatiestappen';
        } else if (!supported) {
          status.textContent = 'Deze browser ondersteunt helaas geen pushnotificaties.';
          toggle.textContent = 'Niet beschikbaar';
          toggle.disabled = true;
        } else if (permission === 'denied') {
          status.textContent = 'Notificaties zijn door je browser geblokkeerd. Sta ze toe via de site-instellingen van je browser en probeer opnieuw.';
          toggle.textContent = 'Opnieuw controleren';
        } else if (optedIn && !subscriptionId) {
          status.textContent = 'Het pushabonnement wordt nog aangemaakt. Probeer het over een paar seconden opnieuw.';
          toggle.textContent = 'Opnieuw proberen';
        } else {
          status.textContent = 'Ontvang een seintje als iemand een gedeeld lijstje bijwerkt.';
          toggle.textContent = 'Meldingen aanzetten';
        }
      };

      toggle?.addEventListener('click', async () => {
        const isIosBrowser = /iphone|ipad|ipod/i.test(window.navigator.userAgent);
        const runsStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
        if (isIosBrowser && !runsStandalone) {
          document.getElementById('install-help')?.showModal();
          return;
        }

        toggle.disabled = true;
        status.textContent = 'De notificatie-instellingen worden bijgewerkt…';
        let errorMessage = '';
        try {
          if (hasActivePushSubscription()) {
            await OneSignal.User.PushSubscription.optOut();
          } else {
            // optIn requests browser permission when needed and, unlike
            // requestPermission alone, also clears OneSignal's opted-out state.
            await OneSignal.User.PushSubscription.optIn();

            if (nativePermission() !== 'granted' && !(await waitForPermission())) {
              updatePushStatus();
              return;
            }
            if (!(await waitForActiveSubscription())) {
              throw new Error('OneSignal heeft het pushabonnement niet geactiveerd.');
            }
          }
        } catch (error) {
          console.warn('Pushnotificaties wijzigen is mislukt.', error);
          errorMessage = 'Meldingen konden niet worden ingeschakeld. Controleer de browsertoestemming en probeer opnieuw.';
        } finally {
          updatePushStatus();
          if (errorMessage) status.textContent = errorMessage;
          toggle.disabled = false;
        }
      });

      deleteButton?.addEventListener('click', async () => {
        const subscriptionId = OneSignal.User.PushSubscription.id;
        if (!subscriptionId) return;
        if (!window.confirm('Pushabonnement van dit apparaat definitief verwijderen?')) return;
        deleteButton.disabled = true;
        try {
          await OneSignal.User.PushSubscription.optOut();
          const response = await fetch(deleteButton.dataset.endpoint, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
            body: new URLSearchParams({_token: deleteButton.dataset.csrfToken, subscription_id: subscriptionId}),
          });
          if (!response.ok) throw new Error('Abonnement verwijderen mislukt.');
          await OneSignal.logout();
          status.textContent = 'Het pushabonnement van dit apparaat is verwijderd.';
        } catch (error) {
          console.warn('Pushabonnement verwijderen is mislukt.', error);
          status.textContent = 'Het pushabonnement kon niet worden verwijderd.';
        } finally {
          updatePushStatus();
          deleteButton.disabled = false;
        }
      });

      OneSignal.User.PushSubscription.addEventListener('change', updatePushStatus);
      OneSignal.Notifications.addEventListener('permissionChange', updatePushStatus);
      updatePushStatus();

      document.querySelector('[data-logout-form]')?.addEventListener('submit', async (event) => {
        const form = event.currentTarget;
        if (form.dataset.pushLogoutDone === 'true') return;
        event.preventDefault();
        await OneSignal.User.PushSubscription.optOut();
        await OneSignal.logout();
        form.dataset.pushLogoutDone = 'true';
        form.submit();
      });
    } catch (error) {
      console.warn('Pushnotificaties initialiseren is mislukt.', error);
      if (status) status.textContent = 'De notificatie-instellingen konden niet worden geladen.';
    }
  });
}


const emailEditorForm = document.querySelector('[data-email-editor-form]');
if (emailEditorForm) {
  const editor = emailEditorForm.querySelector('[data-editor-content]');
  const input = emailEditorForm.querySelector('[data-editor-input]');
  const preview = document.querySelector('[data-email-preview]');

  const syncEmailEditor = () => {
    input.value = editor.innerHTML;
    const previewMessage = preview?.contentDocument?.getElementById('email-message');
    if (previewMessage) previewMessage.innerHTML = editor.innerHTML;
  };

  emailEditorForm.querySelectorAll('[data-editor-command]').forEach((button) => {
    button.addEventListener('click', () => {
      editor.focus();
      document.execCommand(button.dataset.editorCommand, false, button.dataset.editorValue || null);
      syncEmailEditor();
    });
  });

  emailEditorForm.querySelector('[data-editor-link]')?.addEventListener('click', () => {
    const href = window.prompt('Naar welke URL moet de link verwijzen?', 'https://');
    if (!href) return;
    editor.focus();
    document.execCommand('createLink', false, href);
    syncEmailEditor();
  });

  emailEditorForm.querySelectorAll('[data-editor-token]').forEach((button) => {
    button.addEventListener('click', () => {
      editor.focus();
      document.execCommand('insertText', false, button.dataset.editorToken);
      syncEmailEditor();
    });
  });

  editor.addEventListener('input', syncEmailEditor);
  preview?.addEventListener('load', syncEmailEditor);
  emailEditorForm.addEventListener('submit', syncEmailEditor);
}
