(() => {
  if (!('serviceWorker' in navigator)) {
    return;
  }
  const swUrl = document.body.dataset.serviceWorker;
  const scope = document.body.dataset.appScope;
  if (!swUrl || !scope) {
    return;
  }
  window.addEventListener('load', () => {
    navigator.serviceWorker.register(swUrl, { scope }).catch(() => {});
  });
})();
