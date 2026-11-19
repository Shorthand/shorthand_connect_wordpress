/**
 * Controls the Project removal confirmation dialog.
 *
 * @package Shorthand Connect
 */

(() => {
  const dialogs = Array.from(document.querySelectorAll('.shorthand-project-removal-dialog'));

  if (!dialogs.length) {
    return;
  }

  const openDialog = (dialog) => {
    if (typeof dialog.showModal === 'function') {
      dialog.showModal();
      return;
    }
    dialog.setAttribute('open', '');
  };

  const closeDialog = (dialog) => {
    if (typeof dialog.close === 'function') {
      dialog.close();
      return;
    }
    dialog.removeAttribute('open');
  };

  document.querySelectorAll('[data-shorthand-project-dialog-open]').forEach((trigger) => {
    trigger.addEventListener('click', (event) => {
      const target = trigger.getAttribute('data-shorthand-project-dialog-open');
      const dialog = target ? document.getElementById(target) : dialogs[0];

      if (!dialog) {
        return;
      }
      event.preventDefault();
      openDialog(dialog);
    });
  });

  dialogs.forEach((dialog) => {
    dialog.querySelectorAll('[data-shorthand-project-dialog-close]').forEach((trigger) => {
      trigger.addEventListener('click', () => closeDialog(dialog));
    });

    dialog.addEventListener('click', (event) => {
      if (event.target === dialog) {
        closeDialog(dialog);
      }
    });
  });

  if (new URLSearchParams(window.location.search).has('shorthand_remove_project')) {
    openDialog(dialogs[0]);
  }
})();
