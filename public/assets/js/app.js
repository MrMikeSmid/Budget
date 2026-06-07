document.addEventListener('click', (event) => {
  const opener = event.target.closest('[data-open-modal]');
  if (opener) document.getElementById(opener.dataset.openModal)?.showModal();
  if (event.target.closest('[data-close-modal]')) event.target.closest('dialog')?.close();
  if (event.target.matches('.modal')) event.target.close();
  const toastClose = event.target.closest('.toast button');
  if (toastClose) toastClose.parentElement.remove();
});
setTimeout(() => document.querySelectorAll('.toast').forEach((toast) => toast.classList.add('toast--leaving')), 4500);
