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
  const deleteUrl = liveList.dataset.deleteUrl;
  const commentUrl = liveList.dataset.commentUrl;
  const updateUrl = liveList.dataset.updateUrl;
  const imageUrl = liveList.dataset.imageUrl;
  const memberDeleteUrl = liveList.dataset.memberDeleteUrl;
  const isOwner = liveList.dataset.isOwner === 'true';
  const csrfToken = liveList.dataset.csrfToken;
  const taskCreateModal = document.getElementById('new-task');
  const commentsModal = document.getElementById('task-comments');
  const commentsTitle = commentsModal?.querySelector('[data-comments-task-title]');
  const commentsList = commentsModal?.querySelector('[data-comment-list]');
  const commentForm = commentsModal?.querySelector('[data-live-comment]');
  const detailMedia = commentsModal?.querySelector('[data-task-detail-media]');
  const detailImage = commentsModal?.querySelector('[data-task-detail-image]');
  const detailBadges = commentsModal?.querySelector('[data-task-detail-badges]');
  const taskEditToggle = commentsModal?.querySelector('[data-toggle-task-edit]');
  const taskEditForm = commentsModal?.querySelector('[data-live-edit]');
  const initialStateElement = document.querySelector('[data-initial-list-state]');
  const taskSort = liveList.querySelector('[data-task-sort]');
  const sortStorageKey = `samen-task-sort-${liveList.dataset.listId}`;
  const taskSortOptions = new Set(['priority_due', 'due_date', 'priority', 'newest', 'alphabetical']);
  let currentSort = 'priority_due';
  try {
    const storedSort = window.localStorage.getItem(sortStorageKey);
    if (taskSortOptions.has(storedSort)) currentSort = storedSort;
  } catch (error) {
    console.warn('Sorteervoorkeur kon niet worden geladen.', error);
  }
  if (taskSort) taskSort.value = currentSort;
  let currentState = initialStateElement ? JSON.parse(initialStateElement.textContent) : null;
  const imageItemIds = new Set(
    (currentState?.items ?? []).filter((item) => item.has_image).map((item) => Number(item.id)),
  );
  let activeCommentItemId = null;
  let currentRevision = currentState?.revision ?? null;
  let requestSequence = 0;
  let appliedSequence = 0;
  let pollInProgress = false;

  const commentCountLabel = (count) => `${count} ${count === 1 ? 'reactie' : 'reacties'}`;
  const commentDateTimeLabel = (value) => {
    if (!value) return '';
    const normalizedValue = value.includes('T') ? value : `${value.replace(' ', 'T')}Z`;
    const date = new Date(normalizedValue);
    if (Number.isNaN(date.getTime())) return '';
    return new Intl.DateTimeFormat('nl-NL', {
      day: 'numeric',
      month: 'long',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    }).format(date);
  };
  const priorityLabels = { low: 'Lage prioriteit', medium: 'Normale prioriteit', high: 'Hoge prioriteit' };
  const dueDateLabel = (date, isOverdue = false) => {
    if (!date) return '';
    const [year, month, day] = date.split('-');
    return `${isOverdue ? 'Vervallen' : 'Vervalt'} ${day}-${month}-${year}`;
  };

  const priorityRank = { none: 0, low: 1, medium: 2, high: 3 };
  const comparePriority = (first, second) => (priorityRank[second.priority] ?? 0) - (priorityRank[first.priority] ?? 0);
  const compareDueDate = (first, second) => {
    if (!first.due_date && !second.due_date) return 0;
    if (!first.due_date) return 1;
    if (!second.due_date) return -1;
    return first.due_date.localeCompare(second.due_date);
  };
  const compareNewest = (first, second) => Number(second.id) - Number(first.id);
  const sortTasks = (items) => [...items].sort((first, second) => {
    if (currentSort === 'due_date') return compareDueDate(first, second) || comparePriority(first, second) || compareNewest(first, second);
    if (currentSort === 'priority') return comparePriority(first, second) || compareNewest(first, second);
    if (currentSort === 'newest') return compareNewest(first, second);
    if (currentSort === 'alphabetical') return first.title.localeCompare(second.title, 'nl-NL', { sensitivity: 'base' }) || compareNewest(first, second);
    return comparePriority(first, second) || compareDueDate(first, second) || compareNewest(first, second);
  });

  const taskHasImage = (item) => item.has_image || imageItemIds.has(Number(item.id));

  const taskImageUrl = (itemId) => imageUrl.replace('__ITEM_ID__', encodeURIComponent(itemId));

  const handleTaskImageError = (event) => {
    const image = event.currentTarget;
    if (image.dataset.retryAttempted === 'true') return;
    image.dataset.retryAttempted = 'true';
    const retryUrl = new URL(image.src, window.location.href);
    retryUrl.searchParams.set('retry', Date.now().toString());
    window.setTimeout(() => { image.src = retryUrl.toString(); }, 250);
  };

  liveList.querySelectorAll('.task-thumbnail').forEach((thumbnail) => {
    thumbnail.addEventListener('error', handleTaskImageError);
  });

  const taskBadge = (label, modifier) => {
    const badge = document.createElement('span');
    badge.className = `task-badge task-badge--${modifier}`;
    badge.textContent = label;
    return badge;
  };

  const memberAvatar = (member) => {
    const avatar = document.createElement('i');
    const status = !member.is_active ? 'uitgenodigd' : (member.is_online ? 'online' : 'actief');
    avatar.className = `${member.is_online ? 'member-avatar--online' : ''}${!member.is_active ? ' member-avatar--pending' : ''}`.trim();
    avatar.title = `${member.name} is ${status}`;
    avatar.setAttribute('aria-label', avatar.title);
    if (member.profile_image_url) {
      const image = document.createElement('img');
      image.src = member.profile_image_url;
      image.alt = '';
      avatar.append(image);
    } else {
      avatar.textContent = Array.from(member.name.trim().toLocaleUpperCase('nl-NL'))[0] || '?';
    }
    return avatar;
  };

  const memberCard = (member) => {
    const card = document.createElement('article');
    card.className = `member-card${member.is_active ? '' : ' member-card--pending'}`;
    const avatar = document.createElement('div');
    avatar.className = 'member-card__avatar';
    if (member.profile_image_url) {
      const image = document.createElement('img');
      image.src = member.profile_image_url;
      image.alt = `Profielfoto van ${member.name}`;
      avatar.append(image);
    } else {
      avatar.append(document.createTextNode(Array.from(member.name.trim().toLocaleUpperCase('nl-NL'))[0] || '?'));
    }
    const presence = document.createElement('span');
    presence.className = 'member-card__presence';
    avatar.append(presence);
    const copy = document.createElement('div');
    const name = document.createElement('strong');
    name.textContent = member.name;
    const detail = document.createElement('small');
    const statusText = !member.is_active ? 'Uitgenodigd' : (member.is_online ? 'Nu actief' : 'Actief op lijst');
    detail.textContent = `${member.is_owner ? 'Eigenaar · ' : ''}${statusText}`;
    copy.append(name, detail);
    const badge = document.createElement('span');
    badge.className = `member-status member-status--${member.is_active ? 'active' : 'pending'}`;
    badge.textContent = member.is_active ? 'Actief' : 'Uitgenodigd';
    card.append(avatar, copy, badge);
    if (isOwner && !member.is_owner) {
      const form = document.createElement('form');
      form.method = 'post';
      form.action = memberDeleteUrl.replace('__MEMBER_ID__', encodeURIComponent(member.id));
      form.className = 'member-remove-form';
      form.addEventListener('submit', (event) => {
        const label = member.is_active ? 'dit lid' : 'deze uitnodiging';
        if (!window.confirm(`Weet je zeker dat je ${label} wilt verwijderen?`)) event.preventDefault();
      });
      const token = document.createElement('input');
      token.type = 'hidden';
      token.name = '_token';
      token.value = csrfToken;
      const button = document.createElement('button');
      button.className = 'member-remove';
      button.setAttribute('aria-label', `${member.name} verwijderen`);
      button.title = member.is_active ? 'Lid verwijderen' : 'Uitnodiging verwijderen';
      button.textContent = '×';
      form.append(token, button);
      card.append(form);
    }
    return card;
  };

  const renderMembers = (members) => {
    const activeMembers = members.filter((member) => member.is_active);
    liveList.querySelector('[data-plan-label]').textContent = activeMembers.length > 1
      ? 'Een plan van jullie samen'
      : 'Jouw persoonlijke plan';
    liveList.querySelector('[data-member-count]').textContent = `${members.length} ${members.length === 1 ? 'persoon' : 'personen'}`;

    liveList.querySelectorAll('[data-member-stack]').forEach((stack) => {
      const limit = stack.dataset.memberStack === 'header' ? 3 : 4;
      stack.replaceChildren(...members.slice(0, limit).map(memberAvatar));
    });
    liveList.querySelector('[data-member-list]')?.replaceChildren(...members.map(memberCard));
  };

  const renderEmptyState = () => {
    const empty = document.createElement('div');
    empty.className = 'tasks-empty tasks-empty--compact';
    const icon = document.createElement('span');
    icon.textContent = '✓';
    const title = document.createElement('h3');
    title.textContent = 'Alles is gedaan';
    const copy = document.createElement('p');
    copy.textContent = 'Voeg gerust een nieuwe taak toe.';
    empty.append(icon, title, copy);
    return empty;
  };

  const renderTask = (item) => {
    const task = document.createElement('article');
    task.className = `task${item.is_completed ? ' task--done' : ''}${item.is_overdue ? ' task--overdue' : ''}`;

    const toggleForm = document.createElement('form');
    toggleForm.method = 'post';
    toggleForm.action = toggleUrl.replace('__ITEM_ID__', encodeURIComponent(item.id));
    toggleForm.className = 'task-toggle-form';
    toggleForm.dataset.liveToggle = '';

    const token = document.createElement('input');
    token.type = 'hidden';
    token.name = '_token';
    token.value = csrfToken;

    const check = document.createElement('button');
    check.className = 'task-check';
    check.setAttribute('aria-label', item.is_completed ? 'Markeer als niet gedaan' : 'Vink af');
    check.textContent = item.is_completed ? '✓' : '';
    toggleForm.append(token, check);

    let thumbnail = null;
    if (taskHasImage(item)) {
      thumbnail = document.createElement('img');
      thumbnail.className = 'task-thumbnail';
      thumbnail.src = taskImageUrl(item.id);
      thumbnail.alt = '';
      thumbnail.width = 48;
      thumbnail.height = 48;
      thumbnail.decoding = 'async';
      thumbnail.addEventListener('error', handleTaskImageError);
    }

    const content = document.createElement('button');
    content.type = 'button';
    content.className = 'task-content';
    content.dataset.taskDetails = item.id;
    const title = document.createElement('strong');
    title.textContent = item.title;
    if (item.priority !== 'none' || item.due_date) {
      const badges = document.createElement('span');
      badges.className = 'task-badges';
      if (item.priority !== 'none') badges.append(taskBadge(priorityLabels[item.priority], item.priority));
      if (item.due_date) badges.append(taskBadge(dueDateLabel(item.due_date, item.is_overdue), 'date'));
      content.append(title, badges);
    } else {
      content.append(title);
    }
    const meta = document.createElement('small');
    const attribution = document.createElement('span');
    attribution.textContent = item.is_completed
      ? `Afgevinkt door ${item.completer_name}`
      : `Toegevoegd door ${item.creator_name}`;
    const commentCount = document.createElement('span');
    commentCount.className = 'task-comment-count';
    if (item.comment_count > 0) commentCount.classList.add('task-comment-count--active');
    commentCount.dataset.commentCount = '';
    commentCount.textContent = commentCountLabel(item.comment_count);
    meta.append(attribution, commentCount);
    content.append(meta);

    task.append(toggleForm);
    if (thumbnail) task.append(thumbnail);
    task.append(content);

    if (item.is_completed) {
      const deleteForm = document.createElement('form');
      deleteForm.method = 'post';
      deleteForm.action = deleteUrl.replace('__ITEM_ID__', encodeURIComponent(item.id));
      deleteForm.className = 'task-delete-form';
      deleteForm.dataset.liveDelete = '';

      const deleteToken = token.cloneNode();
      const button = document.createElement('button');
      button.className = 'task-delete';
      button.setAttribute('aria-label', `Verwijder ${item.title}`);
      const icon = document.createElement('span');
      icon.setAttribute('aria-hidden', 'true');
      icon.textContent = '×';
      button.append(icon);
      deleteForm.append(deleteToken, button);
      task.append(deleteForm);
    }

    return task;
  };

  const renderComments = (item) => {
    if (!commentsModal || !commentsTitle || !commentsList || !commentForm) return;
    commentsTitle.textContent = item.title;
    commentForm.action = commentUrl.replace('__ITEM_ID__', encodeURIComponent(item.id));
    if (taskEditForm?.hidden) {
      taskEditForm.action = updateUrl.replace('__ITEM_ID__', encodeURIComponent(item.id));
      taskEditForm.querySelector('[data-edit-task-title]').value = item.title;
      taskEditForm.querySelector('[data-edit-task-priority]').value = item.priority;
      taskEditForm.querySelector('[data-edit-task-due-date]').value = item.due_date ?? '';
    }

    if (detailMedia && detailImage) {
      const hasImage = taskHasImage(item);
      detailMedia.hidden = !hasImage;
      detailImage.src = hasImage ? taskImageUrl(item.id) : '';
      detailImage.alt = hasImage ? `Afbeelding bij ${item.title}` : '';
    }
    if (detailBadges) {
      const badges = [];
      if (item.priority !== 'none') badges.push(taskBadge(priorityLabels[item.priority], item.priority));
      if (item.due_date) badges.push(taskBadge(dueDateLabel(item.due_date, item.is_overdue), 'date'));
      detailBadges.replaceChildren(...badges);
      detailBadges.hidden = badges.length === 0;
    }

    if (item.comments.length === 0) {
      const empty = document.createElement('div');
      empty.className = 'comments-empty';
      const title = document.createElement('strong');
      title.textContent = 'Nog geen reacties';
      const copy = document.createElement('span');
      copy.textContent = 'Plaats hieronder de eerste reactie op deze taak.';
      empty.append(title, copy);
      commentsList.replaceChildren(empty);
      return;
    }

    commentsList.replaceChildren(...item.comments.map((comment) => {
      const entry = document.createElement('article');
      entry.className = 'comment';
      const header = document.createElement('header');
      const author = document.createElement('strong');
      author.textContent = comment.author_name;
      const timestamp = document.createElement('time');
      timestamp.dateTime = comment.created_at;
      timestamp.textContent = commentDateTimeLabel(comment.created_at);
      header.append(author, timestamp);
      const body = document.createElement('p');
      body.textContent = comment.body;
      entry.append(header, body);
      return entry;
    }));
  };

  const openComments = (itemId) => {
    const item = currentState?.items.find((candidate) => candidate.id === Number(itemId));
    if (!item || !commentsModal) return;
    activeCommentItemId = item.id;
    if (taskEditForm) taskEditForm.hidden = true;
    if (taskEditToggle) taskEditToggle.hidden = false;
    renderComments(item);
    commentsModal.showModal();
  };

  const applyState = (state, sequence, force = false) => {
    if (sequence < appliedSequence) return;
    appliedSequence = sequence;
    state.items.forEach((item) => {
      if (item.has_image) imageItemIds.add(Number(item.id));
    });
    currentState = state;
    if (!force && state.revision === currentRevision) return;
    currentRevision = state.revision;

    const { done, total, open, percent } = state.stats;
    liveList.querySelector('[data-progress-copy]').textContent = total
      ? `${done} van de ${total} taken afgevinkt`
      : 'Voeg hieronder jullie eerste taak toe.';
    liveList.querySelector('[data-progress-bar]').style.width = `${percent}%`;
    liveList.querySelector('[data-open-count]').textContent = `${open} open taken`;
    liveList.querySelector('[data-completed-count]').textContent = `${done} klaar`;

    const openItems = sortTasks(state.items.filter((item) => !item.is_completed));
    const completedItems = sortTasks(state.items.filter((item) => item.is_completed));
    const openContainer = liveList.querySelector('[data-task-container="open"]');
    const completedContainer = liveList.querySelector('[data-task-container="completed"]');
    const completedSection = liveList.querySelector('[data-task-section="completed"]');

    if (openItems.length === 0) {
      openContainer.replaceChildren(renderEmptyState());
    } else {
      const taskList = document.createElement('div');
      taskList.className = 'task-list';
      taskList.append(...openItems.map(renderTask));
      openContainer.replaceChildren(taskList);
    }

    completedSection.hidden = completedItems.length === 0;
    if (completedItems.length === 0) {
      completedContainer.replaceChildren();
    } else {
      const taskList = document.createElement('div');
      taskList.className = 'task-list';
      taskList.append(...completedItems.map(renderTask));
      completedContainer.replaceChildren(taskList);
    }

    if (activeCommentItemId !== null && commentsModal?.open) {
      const activeItem = state.items.find((item) => item.id === activeCommentItemId);
      if (activeItem) renderComments(activeItem);
      else commentsModal.close();
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

  taskSort?.addEventListener('change', () => {
    if (!taskSortOptions.has(taskSort.value)) return;
    currentSort = taskSort.value;
    try {
      window.localStorage.setItem(sortStorageKey, currentSort);
    } catch (error) {
      console.warn('Sorteervoorkeur kon niet worden opgeslagen.', error);
    }
    if (currentState) applyState(currentState, ++requestSequence, true);
  });

  liveList.addEventListener('click', (event) => {
    const detailsButton = event.target.closest('[data-task-details]');
    if (detailsButton) openComments(detailsButton.dataset.taskDetails);
  });

  taskEditToggle?.addEventListener('click', () => {
    if (!taskEditForm) return;
    taskEditForm.hidden = false;
    taskEditToggle.hidden = true;
    taskEditForm.querySelector('[data-edit-task-title]')?.focus();
  });

  commentsModal?.querySelector('[data-cancel-task-edit]')?.addEventListener('click', () => {
    if (!taskEditForm || !taskEditToggle || activeCommentItemId === null) return;
    taskEditForm.hidden = true;
    taskEditToggle.hidden = false;
    const item = currentState?.items.find((candidate) => candidate.id === activeCommentItemId);
    if (item) renderComments(item);
  });

  commentsModal?.addEventListener('close', () => {
    activeCommentItemId = null;
    commentForm?.reset();
    if (taskEditForm) taskEditForm.hidden = true;
    if (taskEditToggle) taskEditToggle.hidden = false;
  });

  document.addEventListener('submit', async (event) => {
    const form = event.target.closest('[data-live-add], [data-live-toggle], [data-live-delete], [data-live-comment], [data-live-edit]');
    if (!form || (!liveList.contains(form) && !taskCreateModal?.contains(form) && !commentsModal?.contains(form))) return;
    event.preventDefault();
    if (form.dataset.submitting === 'true') return;
    if (form.matches('[data-live-delete]') && !window.confirm('Wil je deze afgeronde taak verwijderen?')) return;

    form.dataset.submitting = 'true';
    const sequence = ++requestSequence;
    try {
      const response = await fetch(form.action, {
        method: 'POST',
        headers: { Accept: 'application/json' },
        body: new FormData(form),
      });
      const result = await response.json();
      if (!response.ok) throw new Error(result.message || `Wijziging opslaan mislukt (${response.status})`);
      if (form.matches('[data-live-edit]')) {
        form.hidden = true;
        if (taskEditToggle) taskEditToggle.hidden = false;
      }
      applyState(result, sequence, true);
      if (form.matches('[data-live-add], [data-live-comment]')) form.reset();
      if (form.matches('[data-task-create-form]')) taskCreateModal.close();
    } catch (error) {
      console.error(error);
      window.alert(error.message || 'De wijziging kon niet worden opgeslagen. Probeer het opnieuw.');
    } finally {
      delete form.dataset.submitting;
    }
  });

  const taskImageInput = taskCreateModal?.querySelector('[data-task-image-input]');
  const taskImagePreview = taskCreateModal?.querySelector('[data-task-image-preview]');
  let taskImageObjectUrl = null;

  taskImageInput?.addEventListener('change', () => {
    if (taskImageObjectUrl) URL.revokeObjectURL(taskImageObjectUrl);
    taskImageObjectUrl = null;
    const [file] = taskImageInput.files;
    if (!file || !taskImagePreview) {
      taskImagePreview?.replaceChildren(Object.assign(document.createElement('strong'), { textContent: '＋' }));
      return;
    }
    taskImageObjectUrl = URL.createObjectURL(file);
    const preview = document.createElement('img');
    preview.src = taskImageObjectUrl;
    preview.alt = 'Voorbeeld van gekozen afbeelding';
    taskImagePreview.replaceChildren(preview);
  });

  taskCreateModal?.addEventListener('close', () => {
    if (taskImageObjectUrl) URL.revokeObjectURL(taskImageObjectUrl);
    taskImageObjectUrl = null;
    taskImagePreview?.replaceChildren(Object.assign(document.createElement('strong'), { textContent: '＋' }));
  });

  if (currentState && currentSort !== 'priority_due') {
    applyState(currentState, ++requestSequence, true);
  }
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
const notificationPanel = document.querySelector('[data-notification-push]');
const consentCard = document.querySelector('[data-push-consent]');
const consentButton = document.querySelector('[data-push-consent-button]');
const consentText = document.querySelector('[data-push-consent-text]');

if (pushRoot) {
  const status = notificationPanel?.querySelector('[data-notification-status]');
  const subscribeButton = notificationPanel?.querySelector('[data-notification-subscribe]');
  const unsubscribeButton = notificationPanel?.querySelector('[data-notification-unsubscribe]');
  let oneSignalClient = null;
  let currentSubscriptionId = '';

  const setStatus = (message, isError = false) => {
    if (status) {
      status.textContent = message;
      status.classList.toggle('form-error', isError);
    }
    if (consentText && message) consentText.textContent = message;
  };

  const setControls = (registered) => {
    if (subscribeButton) subscribeButton.hidden = Boolean(registered);
    if (unsubscribeButton) unsubscribeButton.hidden = !registered;
    if (consentCard) consentCard.hidden = Boolean(registered || ('Notification' in window && Notification.permission === 'denied'));
  };

  const readJsonResponse = async (response) => {
    const text = await response.text();
    if (!text.trim()) throw new Error(response.ok ? 'De server gaf geen antwoord terug.' : `Opslaan mislukt (${response.status}).`);
    try {
      return JSON.parse(text);
    } catch (error) {
      throw new Error(response.ok ? 'De server gaf geen geldige JSON terug.' : `Opslaan mislukt (${response.status}).`);
    }
  };

  const postSubscription = async (endpoint, subscriptionId) => {
    const body = new FormData();
    body.append('_token', pushRoot.dataset.csrfToken);
    body.append('subscription_id', subscriptionId);
    const response = await fetch(endpoint, { method: 'POST', headers: { Accept: 'application/json' }, body });
    const result = await readJsonResponse(response);
    if (!response.ok || !result.ok) throw new Error(result.message || 'Opslaan mislukt.');
    return result;
  };

  const iosInstallMessage = 'Op iPhone/iPad werkt push vanaf iOS 16.4 nadat je Samen via Safari aan het beginscherm hebt toegevoegd. Open daarna die geïnstalleerde app.';

  const initializeOneSignal = () => new Promise((resolve, reject) => {
    if (!window.isSecureContext) {
      reject(new Error('Pushnotificaties vereisen HTTPS.'));
      return;
    }
    window.OneSignalDeferred = window.OneSignalDeferred || [];
    window.OneSignalDeferred.push(async (OneSignal) => {
      try {
        if (!oneSignalClient) {
          await OneSignal.init({
            appId: pushRoot.dataset.onesignalAppId,
            serviceWorkerPath: serviceWorkerUrl,
            serviceWorkerParam: { scope: appScope },
            notifyButton: { enable: false },
            autoResubscribe: true,
          });
          oneSignalClient = OneSignal;
        }
        resolve(oneSignalClient);
      } catch (error) {
        reject(error);
      }
    });
  });

  const syncSubscription = async (OneSignal) => {
    const subscriptionId = OneSignal.User.PushSubscription.id || '';
    const optedIn = Boolean(OneSignal.User.PushSubscription.optedIn);
    if (!subscriptionId || !optedIn) {
      setControls(false);
      return false;
    }
    currentSubscriptionId = subscriptionId;
    const result = await postSubscription(pushRoot.dataset.pushSubscribeEndpoint, subscriptionId);
    setStatus(result.message || 'Meldingen zijn actief op dit apparaat.');
    setControls(true);
    return true;
  };

  const registerDevice = async (allowPrompt = false) => {
    if (isIos && !isInstalledApp) throw new Error(iosInstallMessage);
    const OneSignal = await initializeOneSignal();
    if (!OneSignal.Notifications.isPushSupported()) {
      throw new Error(isIos && !isInstalledApp ? iosInstallMessage : 'Deze browser ondersteunt geen web-push.');
    }
    if (Notification.permission === 'denied') {
      setStatus('Notificaties zijn geblokkeerd. Pas de browser- of app-instellingen aan om updates te ontvangen.', true);
      setControls(false);
      return false;
    }
    if (!allowPrompt && !OneSignal.Notifications.permission) {
      setStatus(isIos && !isInstalledApp ? iosInstallMessage : 'Tik één keer op toestaan om updates van gedeelde lijstjes te ontvangen.');
      setControls(false);
      return false;
    }
    if (!OneSignal.User.PushSubscription.optedIn) {
      await OneSignal.User.PushSubscription.optIn();
    }
    return syncSubscription(OneSignal);
  };

  const unregisterDevice = async () => {
    const OneSignal = await initializeOneSignal();
    currentSubscriptionId = currentSubscriptionId || OneSignal.User.PushSubscription.id || '';
    if (currentSubscriptionId) {
      await postSubscription(pushRoot.dataset.pushUnsubscribeEndpoint, currentSubscriptionId);
    }
    await OneSignal.User.PushSubscription.optOut();
    currentSubscriptionId = '';
    setStatus('Dit apparaat is afgemeld.');
    setControls(false);
  };

  const runRegistration = async (button) => {
    button.disabled = true;
    try {
      await registerDevice(true);
    } catch (error) {
      setStatus(error.message, true);
      setControls(false);
    } finally {
      button.disabled = false;
    }
  };

  consentButton?.addEventListener('click', () => runRegistration(consentButton));
  subscribeButton?.addEventListener('click', () => runRegistration(subscribeButton));
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

  window.addEventListener('load', async () => {
    if (isIos && !isInstalledApp) {
      setStatus(iosInstallMessage);
      setControls(false);
      return;
    }
    try {
      const OneSignal = await initializeOneSignal();
      OneSignal.User.PushSubscription.addEventListener('change', (event) => {
        if (event.current.optedIn && event.current.id) {
          syncSubscription(OneSignal).catch((error) => setStatus(error.message, true));
        }
      });
      await registerDevice(false);
    } catch (error) {
      setStatus(error.message, true);
      setControls(false);
    }
  });
} else if (notificationPanel) {
  const status = notificationPanel.querySelector('[data-notification-status]');
  if (status) status.textContent = 'Configureer OneSignal voordat je apparaten kunt registreren.';
}
