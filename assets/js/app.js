/**
 * TDCMS — Application JavaScript
 * Handles: dark mode toggle, copy-to-clipboard workflow + popup, AJAX helpers
 * for admin actions (toggle status, delete user, bulk select).
 */
(function () {
  'use strict';

  const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const APP_URL = document.querySelector('meta[name="app-url"]')?.content || '';

  const appNavbar = document.querySelector('.app-navbar');
  const appNavbarSpacer = document.querySelector('.app-navbar-spacer');
  if (appNavbar && appNavbarSpacer) {
    const syncNavbarSpacing = function () {
      appNavbarSpacer.style.height = `${appNavbar.offsetHeight}px`;
    };

    syncNavbarSpacing();
    if (typeof ResizeObserver !== 'undefined') {
      new ResizeObserver(syncNavbarSpacing).observe(appNavbar);
    } else {
      window.addEventListener('resize', syncNavbarSpacing);
    }
  }

  const businessContentForm = document.querySelector('[data-business-content-form]');
  if (businessContentForm) {
    const contentRows = [...businessContentForm.querySelectorAll('[data-business-content-row]')];
    const operationInput = businessContentForm.querySelector('[name="operation"]');
    const contentIdInput = businessContentForm.querySelector('[name="content_id"]');
    const selectAll = businessContentForm.querySelector('.business-content-select-all');
    let clickedSubmitter = null;

    const setContentEditing = function (row, editing) {
      row.dataset.editing = editing ? 'true' : 'false';
      row.querySelectorAll('[data-content-display]').forEach((element) => {
        element.classList.toggle('d-none', editing);
      });
      row.querySelectorAll('[data-content-input]').forEach((element) => {
        element.classList.toggle('d-none', !editing);
        element.disabled = !editing;
      });
      row.querySelector('[data-operation="save_one"]').classList.toggle('d-none', !editing);
      row.querySelector('[data-edit-row]').classList.toggle('d-none', editing);
      row.querySelector('[data-cancel-row]').classList.toggle('d-none', !editing);
    };

    const restoreContentRow = function (row) {
      row.querySelectorAll('[data-content-input]').forEach((input) => {
        input.value = input.dataset.original;
      });
      setContentEditing(row, false);
    };

    const updateSelectAll = function () {
      const selectedCount = contentRows.filter((row) => row.querySelector('.business-content-checkbox').checked).length;
      selectAll.checked = contentRows.length > 0 && selectedCount === contentRows.length;
      selectAll.indeterminate = selectedCount > 0 && selectedCount < contentRows.length;
    };

    businessContentForm.addEventListener('click', function (event) {
      const editButton = event.target.closest('[data-edit-row]');
      if (editButton) {
        setContentEditing(editButton.closest('[data-business-content-row]'), true);
        return;
      }

      const cancelButton = event.target.closest('[data-cancel-row]');
      if (cancelButton) {
        restoreContentRow(cancelButton.closest('[data-business-content-row]'));
        return;
      }

      const submitter = event.target.closest('button[type="submit"][data-operation]');
      if (submitter) {
        clickedSubmitter = submitter;
      }
    });

    const bulkEditButton = businessContentForm.querySelector('[data-bulk-edit]');
    if (bulkEditButton) {
      bulkEditButton.addEventListener('click', function () {
        const selectedRows = contentRows.filter((row) => row.querySelector('.business-content-checkbox').checked);
        if (selectedRows.length === 0) {
          window.alert('Select at least one content record to edit.');
          return;
        }
        selectedRows.forEach((row) => setContentEditing(row, true));
      });
    }

    const cancelBulkButton = businessContentForm.querySelector('[data-cancel-bulk]');
    if (cancelBulkButton) {
      cancelBulkButton.addEventListener('click', function () {
        contentRows.forEach((row) => {
          if (row.querySelector('.business-content-checkbox').checked && row.dataset.editing === 'true') {
            restoreContentRow(row);
          }
          row.querySelector('.business-content-checkbox').checked = false;
        });
        selectAll.checked = false;
        selectAll.indeterminate = false;
      });
    }

    if (selectAll) {
      selectAll.addEventListener('change', function () {
        contentRows.forEach((row) => {
          row.querySelector('.business-content-checkbox').checked = selectAll.checked;
        });
        selectAll.indeterminate = false;
      });
    }

    contentRows.forEach((row) => {
      row.querySelector('.business-content-checkbox').addEventListener('change', updateSelectAll);
    });

    businessContentForm.addEventListener('submit', function (event) {
      const submitter = event.submitter || clickedSubmitter;
      clickedSubmitter = null;
      if (!submitter || !submitter.dataset.operation || businessContentForm.dataset.submitting === 'true') {
        event.preventDefault();
        return;
      }

      const operation = submitter.dataset.operation;
      const selectedRows = contentRows.filter((row) => row.querySelector('.business-content-checkbox').checked);
      if (operation === 'bulk_save') {
        const selectedEditingRows = selectedRows.filter((row) => row.dataset.editing === 'true');
        if (selectedEditingRows.length === 0) {
          event.preventDefault();
          window.alert('Select and edit at least one content record before saving.');
          return;
        }
        selectedRows
          .filter((row) => row.dataset.editing !== 'true')
          .forEach((row) => { row.querySelector('.business-content-checkbox').checked = false; });
      }

      if (operation === 'bulk_delete' && selectedRows.length === 0) {
        event.preventDefault();
        window.alert('Select at least one content record to delete.');
        return;
      }

      if (operation === 'delete_one' || operation === 'bulk_delete') {
        const targetCount = operation === 'delete_one' ? 1 : selectedRows.length;
        const confirmation = operation === 'delete_one'
          ? 'Remove this content record? Existing assignments will remain unchanged.'
          : `Remove ${targetCount} selected content records? Existing assignments will remain unchanged.`;
        if (!window.confirm(confirmation)) {
          event.preventDefault();
          return;
        }
      }

      operationInput.value = operation;
      contentIdInput.value = operation === 'save_one' || operation === 'delete_one'
        ? submitter.dataset.contentId || ''
        : '';
      businessContentForm.dataset.submitting = 'true';
      businessContentForm.querySelectorAll('button[type="submit"]').forEach((button) => {
        if (button !== submitter) button.disabled = true;
      });
    });
  }

  const taskImportFiles = document.getElementById('taskImportFiles');
  const taskImportFileCount = document.getElementById('taskImportFileCount');
  if (taskImportFiles && taskImportFileCount) {
    taskImportFiles.addEventListener('change', function () {
      const count = taskImportFiles.files.length;
      taskImportFileCount.textContent = count === 0 ? 'No files selected. Select up to 100 files.' : `${count} file(s) selected${count > 100 ? ' - maximum is 100.' : '.'}`;
      taskImportFileCount.classList.toggle('text-danger', count > 100);
    });
  }

  const manualBusinessSelect = document.querySelector('#manual-pane [data-manual-business-select]');
  const manualTaskTitle = document.querySelector('#manual-pane [name="title"]');
  if (manualBusinessSelect && manualTaskTitle) {
    const syncBusinessTitleRequirement = function () {
      manualTaskTitle.required = manualBusinessSelect.value !== '';
    };
    manualBusinessSelect.addEventListener('change', syncBusinessTitleRequirement);
    syncBusinessTitleRequirement();
  }

  // -------------------------------------------------------------
  // Dark mode toggle
  // -------------------------------------------------------------
  const root = document.documentElement;
  const themeBtn = document.getElementById('themeToggle');
  const savedTheme = localStorage.getItem('tdcms-theme') || 'light';
  root.setAttribute('data-bs-theme', savedTheme);
  updateThemeIcon(savedTheme);

  if (themeBtn) {
    themeBtn.addEventListener('click', function () {
      const current = root.getAttribute('data-bs-theme');
      const next = current === 'dark' ? 'light' : 'dark';
      root.setAttribute('data-bs-theme', next);
      localStorage.setItem('tdcms-theme', next);
      updateThemeIcon(next);
    });
  }

  function updateThemeIcon(theme) {
    if (!themeBtn) return;
    themeBtn.innerHTML = theme === 'dark'
      ? '<i class="bi bi-sun"></i>'
      : '<i class="bi bi-moon-stars"></i>';
  }

  // -------------------------------------------------------------
  // Loading overlay helpers
  // -------------------------------------------------------------
  function showLoading() {
    let overlay = document.getElementById('loadingOverlay');
    if (!overlay) {
      overlay = document.createElement('div');
      overlay.id = 'loadingOverlay';
      overlay.innerHTML = '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>';
      document.body.appendChild(overlay);
    }
    overlay.style.display = 'flex';
  }
  function hideLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) overlay.style.display = 'none';
  }

  const loginForm = document.getElementById('loginForm');
  const continueButton = document.getElementById('continueButton');
  if (loginForm && continueButton) {
    const username = document.getElementById('username');
    const password = document.getElementById('password');
    const passwordGroup = document.getElementById('passwordGroup');
    const verificationCode = document.getElementById('verificationCode');
    const verificationCodeGroup = document.getElementById('verificationCodeGroup');
    const loginButton = document.getElementById('loginButton');
    const error = document.getElementById('loginError');
    const status = document.getElementById('loginStatus');
    const hasEmailCodeInput = Boolean(verificationCode && verificationCodeGroup);
    let submittingLogin = false;
    let checkingUsername = false;

    const showLoginError = function (message) {
      error.textContent = message;
      error.classList.remove('d-none');
    };
    const clearLoginError = function () {
      error.textContent = '';
      error.classList.add('d-none');
    };
    const showLoginStatus = function (message) {
      if (!status) return;
      status.textContent = message;
      status.classList.toggle('d-none', !message);
    };
    const resetCredentialForm = function () {
      password.value = '';
      password.required = false;
      passwordGroup.classList.add('d-none');
      if (hasEmailCodeInput) {
        verificationCode.value = '';
        verificationCode.required = false;
        verificationCodeGroup.classList.add('d-none');
      }
      loginForm.dataset.authMethod = '';
      loginButton.classList.add('d-none');
      loginButton.innerHTML = '<i class="bi bi-box-arrow-in-right me-1"></i> Login';
      continueButton.classList.remove('d-none');
      continueButton.innerHTML = '<i class="bi bi-arrow-right me-1"></i> Continue';
      continueButton.disabled = false;
      showLoginStatus('');
    };
    const identifyUsername = async function () {
      clearLoginError();
      username.value = username.value.trim();
      if (!username.value) {
        showLoginError('Please enter your username.');
        username.focus();
        return false;
      }
      if (!username.checkValidity()) {
        showLoginError('Username must be 3-60 characters: letters, numbers, dot, underscore, or hyphen.');
        username.focus();
        return false;
      }
      if (checkingUsername) return false;

      checkingUsername = true;
      continueButton.disabled = true;
      const requestedUsername = username.value;
      try {
        const token = loginForm.querySelector('input[name="csrf_token"]')?.value || CSRF_TOKEN;
        const response = await fetch(APP_URL + '/index.php?action=login_check', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': token,
          },
          body: new URLSearchParams({ username: requestedUsername, csrf_token: token }),
        });
        const data = await response.json();
        if (username.value.trim() !== requestedUsername) return false;
        if (!response.ok || !data.success) {
          showLoginError(data.message || 'Unable to verify this username.');
          username.focus();
          return false;
        }

        loginForm.dataset.authMethod = data.auth_method;
        loginButton.classList.remove('d-none');
        if (data.auth_method === 'password') {
          continueButton.classList.add('d-none');
          passwordGroup.classList.remove('d-none');
          password.required = true;
          password.focus();
        } else if (data.auth_method === 'username') {
          loginForm.requestSubmit(loginButton);
        } else if (data.auth_method === 'email_code') {
          if (!hasEmailCodeInput) {
            resetCredentialForm();
            showLoginError('Email verification is unavailable in this login mode.');
            return false;
          }
          password.value = '';
          password.required = false;
          passwordGroup.classList.add('d-none');
          verificationCodeGroup.classList.remove('d-none');
          verificationCode.required = true;
          verificationCode.value = '';
          continueButton.classList.remove('d-none');
          continueButton.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i> Resend code';
          loginButton.innerHTML = '<i class="bi bi-shield-check me-1"></i> Verify code';
          showLoginStatus(data.message || 'Check your email for a sign-in code.');
          verificationCode.focus();
        } else {
          resetCredentialForm();
          showLoginError('Unable to verify this account.');
          return false;
        }
        return true;
      } catch (requestError) {
        showLoginError('Unable to verify the username. Please try again.');
        return false;
      } finally {
        checkingUsername = false;
        continueButton.disabled = false;
      }
    };

    continueButton.addEventListener('click', identifyUsername);
    username.addEventListener('keydown', function (event) {
      if (event.key === 'Enter') {
        event.preventDefault();
        identifyUsername();
      }
    });
    username.addEventListener('input', function () {
      resetCredentialForm();
      clearLoginError();
    });
    password.addEventListener('keydown', function (event) {
      if (event.key === 'Enter') {
        event.preventDefault();
        if (!submittingLogin) loginForm.requestSubmit(loginButton);
      }
    });
    if (hasEmailCodeInput) {
      verificationCode.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
          event.preventDefault();
          if (!submittingLogin) loginForm.requestSubmit(loginButton);
        }
      });
    }
    loginForm.addEventListener('submit', function (event) {
      clearLoginError();
      if (!username.checkValidity()) {
        event.preventDefault();
        showLoginError('Enter a valid username before logging in.');
        username.focus();
        return;
      }
      const method = loginForm.dataset.authMethod;
      const hasCredential = (method === 'username')
        || (method === 'password'
          && !passwordGroup.classList.contains('d-none')
          && password.value !== '')
        || (method === 'email_code' && hasEmailCodeInput
          && !verificationCodeGroup.classList.contains('d-none')
          && /^[0-9]{6}$/.test(verificationCode.value));
      if (!hasCredential) {
        event.preventDefault();
        if (!method) {
          identifyUsername();
        } else if (method === 'password') {
          showLoginError('Please enter your password.');
          password.focus();
        } else if (method === 'email_code' && hasEmailCodeInput) {
          showLoginError('Enter the six-digit code sent to your email.');
          verificationCode.focus();
        }
        return;
      }
      if (submittingLogin) {
        event.preventDefault();
        return;
      }
      submittingLogin = true;
      loginButton.disabled = true;
      continueButton.disabled = true;
    });
  }

  const userForm = document.getElementById('userForm');
  if (userForm) {
    const roleSelect = userForm.querySelector('[name="role"]');
    const passwordGroup = document.getElementById('adminPasswordGroup');
    const adminPassword = document.getElementById('adminPassword');
    const hasAdminCredential = userForm.dataset.hasAdminCredential === 'true';
    const syncAdminPasswordField = function () {
      const needsAdminPassword = roleSelect.value === 'admin';
      passwordGroup.classList.toggle('d-none', !needsAdminPassword);
      adminPassword.required = needsAdminPassword && !hasAdminCredential;
      if (!needsAdminPassword) adminPassword.value = '';
    };

    roleSelect.addEventListener('change', syncAdminPasswordField);
    syncAdminPasswordField();
  }

  // -------------------------------------------------------------
  // Assignment content and reference links are released by the server
  // only after it validates the current employee/task state.
  // -------------------------------------------------------------

  const assignmentStatusRequests = new WeakMap();

  function invalidateAssignmentStatus(card) {
    assignmentStatusRequests.set(card, (assignmentStatusRequests.get(card) || 0) + 1);
  }

  async function recordAssignmentProgress(card, eventName) {
    const assignmentId = card.querySelector('[data-id]')?.dataset.id;
    if (!assignmentId) {
      console.error('[TDCMS] No assignment ID found');
      return null;
    }

    showLoading();
    try {
      const payload = { assignment_id: assignmentId, event: eventName, csrf_token: CSRF_TOKEN };
      const res = await fetch(APP_URL + '/ajax/copy_text.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-Token': CSRF_TOKEN,
        },
        body: new URLSearchParams(payload),
      });
      const data = await res.json();
      if (!data.success) {
        if (eventName === 'copy_text' && res.status === 409) {
          Swal.fire({ icon: 'warning', title: 'Copy blocked', text: data.message });
          await syncAssignmentStatus(card);
          return null;
        }
        throw new Error(data.message || 'This task could not be updated.');
      }

      invalidateAssignmentStatus(card);

      if (eventName === 'copy_text') {
        await copyToClipboard(data.content);
        const contentDisplay = card.querySelector('.assignment-text');
        if (contentDisplay) contentDisplay.textContent = data.content;
      }

      const copyBtn = card.querySelector('.btn-copy-text');
      const linkBtn = card.querySelector('.btn-open-reference');
      const statusHint = card.querySelector('.status-hint');

      if (copyBtn) {
        copyBtn.dataset.copied = data.text_copied ? '1' : '0';
        copyBtn.innerHTML = data.text_copied
          ? '<i class="bi bi-clipboard-check"></i> Copied'
          : '<i class="bi bi-clipboard"></i> Copy Text';
      }

      if (linkBtn) {
        linkBtn.dataset.clicked = data.link_clicked ? '1' : '0';
        linkBtn.disabled = !data.text_copied;
      }

      card.dataset.promptPending = data.completion_prompt_pending ? '1' : '0';
      if (statusHint) {
        statusHint.textContent = data.text_copied && (!linkBtn || data.link_clicked)
          ? 'Is the task completed? Confirm using the popup.'
          : (data.text_copied ? 'Text copied. Open the reference link, then mark the task as completed.' : 'Copy the text to begin.');
      }

      return data;
    } catch (error) {
      Swal.fire({ icon: 'error', title: 'Task action failed', text: error.message || 'Please check your connection and try again.' });
      return null;
    } finally {
      hideLoading();
    }
  }

  document.querySelectorAll('.btn-copy-text').forEach(function (btn) {
    btn.addEventListener('click', async function () {
      const card = btn.closest('.assignment-card');
      const result = await recordAssignmentProgress(card, 'copy_text');
      if (result?.completion_prompt_pending) openTaskCompletionModal(card);
    });
  });

  document.querySelectorAll('.btn-open-reference').forEach(function (btn) {
    btn.addEventListener('click', async function () {
      if (btn.disabled) return;
      // Reserve the tab during the user gesture so the async state request cannot trigger popup blocking.
      const referenceWindow = window.open('about:blank', '_blank');
      if (referenceWindow) referenceWindow.opener = null;
      const card = btn.closest('.assignment-card');
      const result = await recordAssignmentProgress(card, 'link_click');
      const url = result?.assignment?.reference_link;
      if (result?.success && url) {
        if (result.completion_prompt_pending) openTaskCompletionModal(card);
        if (referenceWindow) {
          referenceWindow.location.replace(url);
        } else {
          window.open(url, '_blank', 'noopener,noreferrer');
        }
      } else if (referenceWindow) {
        referenceWindow.close();
      }
    });
  });

  function openTaskCompletionModal(card) {
    const confirmModal = document.getElementById('taskConfirmModal');
    const confirmSubmit = document.getElementById('confirmTaskCompletion');
    const confirmCancel = confirmModal?.querySelector('.task-confirm-cancel');
    const noConfirmationModal = document.getElementById('taskNoConfirmationModal');
    const noConfirmationYes = document.getElementById('confirmTaskNoConfirmation');
    const noConfirmationNo = document.getElementById('cancelTaskNoConfirmation');
    const successModal = document.getElementById('taskSuccessModal');
    const successClose = document.getElementById('closeTaskSuccess');
    const assignmentId = card?.querySelector('[data-id]')?.dataset.id;
    if (!confirmModal || !confirmSubmit || !confirmCancel || !noConfirmationModal || !noConfirmationYes || !noConfirmationNo || !successModal || !successClose || !assignmentId || confirmModal.classList.contains('is-open')) return;

    const setModalState = function (modal, open) {
      modal.classList.toggle('is-open', open);
      modal.setAttribute('aria-hidden', open ? 'false' : 'true');
    };

    const closeNoConfirmation = function () {
      setModalState(noConfirmationModal, false);
      confirmModal.setAttribute('aria-hidden', 'false');
      confirmCancel.focus();
    };

    confirmSubmit.disabled = false;
    confirmSubmit.dataset.completing = '0';
    confirmCancel.disabled = false;
    confirmModal.dataset.assignmentId = assignmentId;
    setModalState(confirmModal, true);
    document.body.classList.add('modal-open');
    if (history.state?.tdcmsTaskConfirmation !== assignmentId) {
      history.pushState({ tdcmsTaskConfirmation: assignmentId }, '', location.href);
    }
    confirmCancel.onclick = function () {
      if (!noConfirmationModal || noConfirmationModal.classList.contains('is-open')) return;
      confirmModal.setAttribute('aria-hidden', 'true');
      setModalState(noConfirmationModal, true);
      noConfirmationYes.focus();
    };
    noConfirmationYes.onclick = function () {
      closeNoConfirmation();
      card.dataset.completionPromptDismissed = '1';
      setModalState(confirmModal, false);
      confirmModal.dataset.assignmentId = '';
      document.body.classList.remove('modal-open');
      if (history.state?.tdcmsTaskConfirmation === assignmentId) history.back();

      const reviewContent = card.querySelector('.assignment-text');
      if (reviewContent) {
        reviewContent.scrollIntoView({ behavior: 'smooth', block: 'center' });
        reviewContent.focus({ preventScroll: true });
      }
    };
    noConfirmationNo.onclick = closeNoConfirmation;
    confirmSubmit.focus();
    confirmSubmit.onclick = async function () {
      if (confirmSubmit.dataset.completing === '1') return;
      confirmSubmit.dataset.completing = '1';
      confirmSubmit.disabled = true;
      showLoading();
      try {
        const res = await fetch(APP_URL + '/index.php?action=mark_assignment_completed', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': CSRF_TOKEN },
          body: new URLSearchParams({
            assignment_id: assignmentId,
            csrf_token: CSRF_TOKEN,
          }),
        });
        const data = await res.json();
        if (!res.ok || !data.success) throw new Error(data.message || 'This task could not be completed.');
        setModalState(confirmModal, false);
        confirmModal.dataset.assignmentId = '';
        document.body.classList.remove('modal-open');
        if (history.state?.tdcmsTaskConfirmation === assignmentId) history.back();
        updateCompletedCard(card);
        setModalState(successModal, true);
        successClose.focus();
      } catch (error) {
        confirmSubmit.disabled = false;
        confirmSubmit.dataset.completing = '0';
        Swal.fire({ icon: 'warning', title: 'Unable to complete task', text: error.message });
      } finally {
        hideLoading();
      }
    };

    const closeSuccessModal = function () {
      setModalState(successModal, false);
      document.body.classList.remove('modal-open');
      openNextPendingCompletion();
    };
    successClose.onclick = closeSuccessModal;
    successModal.querySelectorAll('[data-success-close]').forEach(closeButton => {
      closeButton.onclick = closeSuccessModal;
    });
  }

  function updateCompletedCard(card) {
    const statusBadge = card.querySelector('.badge-pending');
    const statusHint = card.querySelector('.status-hint');
    card.dataset.promptPending = '0';
    if (statusBadge) {
      statusBadge.classList.remove('badge-pending');
      statusBadge.classList.add('badge-completed');
      statusBadge.textContent = 'Completed';
    }
    card.querySelectorAll('.task-verification-input, .btn-copy-text, .btn-open-reference, .btn-mark-completed').forEach(control => {
      control.disabled = true;
      control.classList.add('disabled');
      control.setAttribute('aria-disabled', 'true');
    });
    if (statusHint) {
      statusHint.textContent = 'This task has been completed successfully.';
      statusHint.classList.remove('text-muted');
      statusHint.classList.add('text-success');
    }
    updatePendingCountBadge(card);
  }

  function openNextPendingCompletion() {
    const card = Array.from(document.querySelectorAll('.assignment-card'))
      .find(item => item.dataset.promptPending === '1'
        && item.dataset.completionPromptDismissed !== '1'
        && item.querySelector('.badge-pending'));
    if (card) openTaskCompletionModal(card);
  }

  // Keep browser history on the dashboard while a server-persisted prompt is unresolved.
  window.addEventListener('popstate', function () {
    const modal = document.getElementById('taskConfirmModal');
    if (!modal?.classList.contains('is-open')) return;
    history.pushState({ tdcmsTaskConfirmation: modal.dataset.assignmentId }, '', location.href);
  });

  document.addEventListener('keydown', function (event) {
    const modal = document.getElementById('taskConfirmModal');
    const secondaryModal = document.getElementById('taskNoConfirmationModal');
    if (secondaryModal?.classList.contains('is-open') && event.key === 'Tab') {
      const buttons = Array.from(secondaryModal.querySelectorAll('button:not(:disabled)'));
      const firstButton = buttons[0];
      const lastButton = buttons[buttons.length - 1];
      if (event.shiftKey && document.activeElement === firstButton) {
        event.preventDefault();
        lastButton.focus();
      } else if (!event.shiftKey && document.activeElement === lastButton) {
        event.preventDefault();
        firstButton.focus();
      }
      return;
    }
    if (event.key === 'Escape' && modal?.classList.contains('is-open')) {
      event.preventDefault();
      event.stopImmediatePropagation();
    }
  }, true);

  document.addEventListener('click', function (event) {
    const modal = document.getElementById('taskConfirmModal');
    if (modal?.classList.contains('is-open') && event.target?.classList.contains('task-confirm-backdrop')) {
      event.preventDefault();
      event.stopImmediatePropagation();
    }
  }, true);

  async function syncAssignmentStatus(card) {
    const assignmentId = card.querySelector('[data-id]')?.dataset.id;
    if (!assignmentId) return;

    const requestVersion = (assignmentStatusRequests.get(card) || 0) + 1;
    assignmentStatusRequests.set(card, requestVersion);

    try {
      const res = await fetch(APP_URL + '/ajax/copy_text.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-Token': CSRF_TOKEN,
        },
        body: new URLSearchParams({
          assignment_id: assignmentId,
          event: 'status',
          csrf_token: CSRF_TOKEN,
        }),
      });
      const data = await res.json();
      if (!res.ok || !data.success) throw new Error(data.message || 'Unable to refresh task status.');
      if (assignmentStatusRequests.get(card) !== requestVersion) return;

      if (data.status === 'completed') {
        updateCompletedCard(card);
        const modal = document.getElementById('taskConfirmModal');
        if (modal?.dataset.assignmentId === assignmentId) {
          modal.classList.remove('is-open');
          modal.setAttribute('aria-hidden', 'true');
          modal.dataset.assignmentId = '';
          document.body.classList.remove('modal-open');
        }
        return;
      }

      card.dataset.promptPending = data.completion_prompt_pending ? '1' : '0';
      const copyBtn = card.querySelector('.btn-copy-text');
      const linkBtn = card.querySelector('.btn-open-reference');
      if (copyBtn) {
        copyBtn.disabled = card.dataset.hasContent !== '1';
        copyBtn.dataset.copied = data.text_copied ? '1' : '0';
        copyBtn.innerHTML = data.text_copied
          ? '<i class="bi bi-clipboard-check"></i> Copied'
          : '<i class="bi bi-clipboard"></i> Copy Text';
      }
      if (linkBtn) {
        linkBtn.dataset.clicked = data.link_clicked ? '1' : '0';
        linkBtn.disabled = card.dataset.hasContent !== '1' || !data.text_copied;
      }

      const modal = document.getElementById('taskConfirmModal');
      if (!data.completion_prompt_pending && modal?.dataset.assignmentId === assignmentId) {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        modal.dataset.assignmentId = '';
        document.body.classList.remove('modal-open');
      } else if (data.completion_prompt_pending) {
        openNextPendingCompletion();
      }
    } catch (error) {
      console.error('[TDCMS] Task status refresh failed:', error);
    }
  }

  function refreshAssignmentStatuses() {
    document.querySelectorAll('.assignment-card').forEach(syncAssignmentStatus);
  }

  window.addEventListener('focus', refreshAssignmentStatuses);
  window.addEventListener('pageshow', refreshAssignmentStatuses);
  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'visible') refreshAssignmentStatuses();
  });
  window.setInterval(refreshAssignmentStatuses, 15000);
  openNextPendingCompletion();

  async function copyToClipboard(text) {
    if (navigator.clipboard && window.isSecureContext) {
      await navigator.clipboard.writeText(text);
    } else {
      const ta = document.createElement('textarea');
      ta.value = text;
      ta.style.position = 'fixed';
      ta.style.opacity = '0';
      ta.setAttribute('readonly', '');
      document.body.appendChild(ta);
      ta.focus();
      ta.select();
      const copied = document.execCommand('copy');
      document.body.removeChild(ta);
      if (!copied) {
        throw new Error('Clipboard access was blocked.');
      }
    }
  }

  function updatePendingCountBadge(completedCard) {
    const badge = document.getElementById('pendingCountBadge');
    if (!badge) return;
    if (completedCard && completedCard.dataset.pendingCounted !== '1') {
      badge.textContent = Math.max(0, Number(badge.textContent) - 1);
      completedCard.dataset.pendingCounted = '1';
    }
    const emptyState = document.getElementById('emptyState');
    if (Number(badge.textContent) <= 0 && emptyState) {
      setTimeout(() => emptyState.classList.remove('d-none'), 250);
    }
  }

  // -------------------------------------------------------------
  // Admin: toggle user status (enable/disable) via AJAX
  // -------------------------------------------------------------
  document.querySelectorAll('.btn-toggle-status').forEach(function (btn) {
    btn.addEventListener('click', async function () {
      const id = btn.dataset.id;
      const newStatus = btn.dataset.newStatus;
      const confirmResult = await Swal.fire({
        title: newStatus === 'disabled' ? 'Disable this user?' : 'Enable this user?',
        text: newStatus === 'disabled' ? 'They will not be able to log in until re-enabled.' : 'They will regain access immediately.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, continue',
      });
      if (!confirmResult.isConfirmed) return;

      showLoading();
      const res = await fetch(APP_URL + '/index.php?action=toggle_user_status', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': CSRF_TOKEN },
        body: new URLSearchParams({ id, status: newStatus, csrf_token: CSRF_TOKEN }),
      });
      const data = await res.json();
      hideLoading();
      if (data.success) {
        location.reload();
      } else {
        Swal.fire({ icon: 'error', title: 'Failed', text: data.message || 'Action could not be completed.' });
      }
    });
  });

  // -------------------------------------------------------------
  // Admin: delete user via AJAX
  // -------------------------------------------------------------
  document.querySelectorAll('.btn-delete-user').forEach(function (btn) {
    btn.addEventListener('click', async function () {
      const id = btn.dataset.id;
      const confirmResult = await Swal.fire({
        title: 'Delete this user permanently?',
        text: 'This cannot be undone. Their assignment history will also be removed.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Yes, delete',
      });
      if (!confirmResult.isConfirmed) return;

      showLoading();
      const res = await fetch(APP_URL + '/index.php?action=delete_user', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': CSRF_TOKEN },
        body: new URLSearchParams({ id, csrf_token: CSRF_TOKEN }),
      });
      const data = await res.json();
      hideLoading();
      if (data.success) {
        location.reload();
      } else {
        Swal.fire({ icon: 'error', title: 'Failed', text: data.message || 'Action could not be completed.' });
      }
    });
  });

  // -------------------------------------------------------------
  // Admin: view, edit, and archive assignments by their database ID.
  // -------------------------------------------------------------
  const editAssignmentModalElement = document.getElementById('editAssignmentModal');
  const editAssignmentForm = document.getElementById('editAssignmentForm');
  const editAssignmentModal = editAssignmentModalElement && window.bootstrap
    ? new bootstrap.Modal(editAssignmentModalElement)
    : null;
  const viewAssignmentModalElement = document.getElementById('viewAssignmentModal');
  const viewAssignmentModal = viewAssignmentModalElement && window.bootstrap
    ? new bootstrap.Modal(viewAssignmentModalElement)
    : null;

  const userAssignmentContentModalElement = document.getElementById('userAssignmentContentModal');
  const userAssignmentContentModal = userAssignmentContentModalElement && window.bootstrap
    ? new bootstrap.Modal(userAssignmentContentModalElement)
    : null;
  const userAssignmentContentBody = document.getElementById('userAssignmentContentBody');
  const userAssignmentContentPage = document.getElementById('userAssignmentContentPage');
  const userAssignmentContentPrevious = document.getElementById('userAssignmentContentPrevious');
  const userAssignmentContentNext = document.getElementById('userAssignmentContentNext');
  let selectedUserContent = null;
  let userContentRequestId = 0;

  async function loadUserAssignmentContent(page) {
    if (!selectedUserContent || !userAssignmentContentBody) return;
    const requestId = ++userContentRequestId;
    const params = new URLSearchParams({
      action: 'get_user_assignment_content',
      user_id: selectedUserContent.userId,
      status: selectedUserContent.status,
      p: String(page),
    });
    userAssignmentContentBody.textContent = 'Loading content...';
    if (userAssignmentContentPrevious) userAssignmentContentPrevious.disabled = true;
    if (userAssignmentContentNext) userAssignmentContentNext.disabled = true;
    if (userAssignmentContentPage) userAssignmentContentPage.textContent = '';

    try {
      const response = await fetch(APP_URL + '/index.php?' + params, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      });
      const data = await response.json();
      if (!response.ok || !data.success || !Array.isArray(data.rows)) {
        throw new Error(data.message || 'Assignment content could not be loaded.');
      }
      if (requestId !== userContentRequestId) return;

      userAssignmentContentBody.replaceChildren();
      if (data.rows.length === 0) {
        userAssignmentContentBody.textContent = 'No matching assignment content.';
      } else {
        data.rows.forEach(function (item) {
          const entry = document.createElement('article');
          entry.className = 'border rounded p-3 mb-3';
          if (item.title) {
            const title = document.createElement('h6');
            title.className = 'mb-2';
            title.textContent = item.title;
            entry.appendChild(title);
          }
          const content = document.createElement('div');
          content.className = 'text-break';
          content.style.whiteSpace = 'pre-wrap';
          content.textContent = item.text_content || '';
          entry.appendChild(content);
          userAssignmentContentBody.appendChild(entry);
        });
      }

      if (userAssignmentContentPage) {
        userAssignmentContentPage.textContent = data.pages > 1
          ? `Page ${data.page} of ${data.pages} · ${data.total} item(s)`
          : `${data.total} item(s)`;
      }
      if (userAssignmentContentPrevious) userAssignmentContentPrevious.disabled = data.page <= 1;
      if (userAssignmentContentNext) userAssignmentContentNext.disabled = data.page >= data.pages;
      selectedUserContent.page = data.page;
    } catch (error) {
      if (requestId !== userContentRequestId) return;
      userAssignmentContentBody.textContent = error.message || 'Assignment content could not be loaded.';
    }
  }

  document.querySelectorAll('.btn-user-assignment-content').forEach(function (button) {
    button.addEventListener('click', function () {
      if (!userAssignmentContentModal) return;
      const status = button.dataset.status || '';
      const statusLabel = status ? `${status[0].toUpperCase()}${status.slice(1)}` : 'All';
      const title = document.getElementById('userAssignmentContentTitle');
      if (title) title.textContent = `${statusLabel} Content · ${button.dataset.userName || 'User'}`;
      selectedUserContent = {
        userId: button.dataset.userId,
        status: status,
        page: 1,
      };
      userAssignmentContentModal.show();
      loadUserAssignmentContent(1);
    });
  });

  if (userAssignmentContentPrevious) {
    userAssignmentContentPrevious.addEventListener('click', function () {
      if (selectedUserContent && selectedUserContent.page > 1) {
        loadUserAssignmentContent(selectedUserContent.page - 1);
      }
    });
  }
  if (userAssignmentContentNext) {
    userAssignmentContentNext.addEventListener('click', function () {
      if (selectedUserContent) loadUserAssignmentContent(selectedUserContent.page + 1);
    });
  }

  if (userAssignmentContentModalElement) {
    userAssignmentContentModalElement.addEventListener('hidden.bs.modal', function () {
      userContentRequestId++;
      selectedUserContent = null;
      if (userAssignmentContentBody) userAssignmentContentBody.replaceChildren();
      if (userAssignmentContentPage) userAssignmentContentPage.textContent = '';
    });
  }

  if (editAssignmentModalElement && editAssignmentForm) {
    editAssignmentModalElement.addEventListener('hidden.bs.modal', function () {
      editAssignmentForm.reset();
      editAssignmentForm.elements.assignment_id.value = '';
      editAssignmentForm.elements.user_id.disabled = false;
      const error = editAssignmentForm.querySelector('#editAssignmentError');
      error.textContent = '';
      error.classList.add('d-none');
    });
  }

  async function fetchAdminAssignment(assignmentId) {
    const query = new URLSearchParams({ action: 'get_assignment', assignment_id: assignmentId });
    const response = await fetch(APP_URL + '/index.php?' + query, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    });
    const data = await response.json();
    if (!response.ok || !data.success || !data.assignment) {
      throw new Error(data.message || 'Assignment details could not be loaded.');
    }
    return data.assignment;
  }

  function setAssignmentDetail(id, value) {
    const element = document.getElementById(id);
    if (element) element.textContent = value === null || value === '' ? '—' : String(value);
  }

  function populateAssignmentView(assignment) {
    setAssignmentDetail('viewAssignmentId', assignment.id);
    setAssignmentDetail('viewAssignmentEmployee', `${assignment.user_full_name} (${assignment.user_username})`);
    setAssignmentDetail('viewAssignmentName', assignment.title);
    setAssignmentDetail('viewAssignmentCategory', assignment.category);
    setAssignmentDetail('viewAssignmentTags', assignment.tags);
    setAssignmentDetail('viewAssignmentStatus', assignment.status);
    setAssignmentDetail('viewAssignmentAssigner', assignment.assigned_by_name);
    setAssignmentDetail('viewAssignmentAssignedAt', assignment.assigned_at);
    setAssignmentDetail('viewAssignmentCompletedAt', assignment.completed_at);
    setAssignmentDetail('viewAssignmentCompletionIp', assignment.ip_on_completion);
    setAssignmentDetail(
      'viewAssignmentProgress',
      `Text copied: ${Number(assignment.text_copied) ? 'Yes' : 'No'}; reference link opened: ${Number(assignment.link_clicked) ? 'Yes' : 'No'}; completion confirmation pending: ${Number(assignment.completion_prompt_pending) ? 'Yes' : 'No'}`
    );
    setAssignmentDetail('viewAssignmentText', assignment.text_content);

    const link = document.getElementById('viewAssignmentLink');
    const noLink = document.getElementById('viewAssignmentNoLink');
    if (assignment.reference_link) {
      link.textContent = assignment.reference_link;
      link.removeAttribute('href');
      link.classList.remove('d-none');
      noLink.classList.add('d-none');
      if (isValidHttpUrl(assignment.reference_link)) link.href = assignment.reference_link;
    } else {
      link.removeAttribute('href');
      link.textContent = '';
      link.classList.add('d-none');
      noLink.classList.remove('d-none');
    }
  }

  document.querySelectorAll('.btn-view-assignment').forEach(function (btn) {
    btn.addEventListener('click', async function () {
      if (!viewAssignmentModal) return;
      btn.disabled = true;
      showLoading();
      try {
        const assignment = await fetchAdminAssignment(btn.dataset.id);
        populateAssignmentView(assignment);
        viewAssignmentModal.show();
      } catch (error) {
        Swal.fire({ icon: 'error', title: 'Unable to load assignment', text: error.message || 'Assignment details could not be loaded.' });
      } finally {
        hideLoading();
        btn.disabled = false;
      }
    });
  });
  const linkedAssignmentView = document.getElementById('linkedAssignmentView');
  if (linkedAssignmentView) linkedAssignmentView.click();

  document.querySelectorAll('.btn-edit-assignment').forEach(function (btn) {
    btn.addEventListener('click', async function () {
      if (!editAssignmentForm || !editAssignmentModal) return;
      btn.disabled = true;
      showLoading();
      try {
        const assignment = await fetchAdminAssignment(btn.dataset.id);
        const form = editAssignmentForm;
        form.reset();
        const employeeSelect = form.elements.user_id;
        employeeSelect.disabled = false;
        let employeeOption = Array.from(employeeSelect.options)
          .find(option => option.value === String(assignment.user_id));
        if (!employeeOption) {
          employeeOption = new Option(
            `${assignment.user_full_name} (${assignment.user_username})${assignment.user_status === 'active' ? '' : ' (disabled)'}`,
            String(assignment.user_id)
          );
          employeeOption.disabled = assignment.user_status !== 'active';
          employeeSelect.add(employeeOption);
        }

        form.elements.assignment_id.value = assignment.id;
        employeeSelect.value = String(assignment.user_id);
        employeeSelect.disabled = assignment.status === 'completed';
        form.elements.title.value = assignment.title || '';
        form.elements.category.value = assignment.category || '';
        form.elements.tags.value = assignment.tags || '';
        form.elements.text_content.value = assignment.text_content || '';
        form.elements.reference_link.value = assignment.reference_link || '';
        form.querySelector('#editAssignmentStatusNote').textContent = assignment.status === 'completed'
          ? 'Completed assignment history is preserved; its employee cannot be changed.'
          : 'Changing the employee, task text, or reference link resets this pending task’s progress.';
        const error = form.querySelector('#editAssignmentError');
        error.textContent = '';
        error.classList.add('d-none');
        editAssignmentModal.show();
      } catch (error) {
        Swal.fire({ icon: 'error', title: 'Unable to load assignment', text: error.message || 'Assignment details could not be loaded.' });
      } finally {
        hideLoading();
        btn.disabled = false;
      }
    });
  });

  if (editAssignmentForm && editAssignmentModal) {
    editAssignmentForm.addEventListener('submit', async function (event) {
      event.preventDefault();
      const submitButton = editAssignmentForm.querySelector('#saveAssignmentChanges');
      const errorBox = editAssignmentForm.querySelector('#editAssignmentError');
      const payload = new URLSearchParams(new FormData(editAssignmentForm));
      payload.set('user_id', editAssignmentForm.elements.user_id.value);
      payload.set('csrf_token', CSRF_TOKEN);
      submitButton.disabled = true;
      errorBox.textContent = '';
      errorBox.classList.add('d-none');
      showLoading();

      try {
        const response = await fetch(APP_URL + '/index.php?action=update_assignment', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': CSRF_TOKEN,
          },
          body: payload,
        });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'Assignment could not be updated.');
        editAssignmentModal.hide();
        await Swal.fire({ icon: 'success', title: 'Assignment updated', text: data.message, timer: 1400, showConfirmButton: false });
        location.reload();
      } catch (error) {
        errorBox.textContent = error.message || 'Assignment could not be updated.';
        errorBox.classList.remove('d-none');
      } finally {
        hideLoading();
        submitButton.disabled = false;
      }
    });
  }

  document.querySelectorAll('.btn-delete-assignment').forEach(function (btn) {
    btn.addEventListener('click', async function () {
      const confirmation = await Swal.fire({
        title: 'Delete assignment?',
        text: 'Are you sure you want to delete this assignment? This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Delete',
        cancelButtonText: 'Cancel',
      });
      if (!confirmation.isConfirmed || btn.disabled) return;

      btn.disabled = true;
      showLoading();
      try {
        const response = await fetch(APP_URL + '/index.php?action=delete_assignment', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': CSRF_TOKEN,
          },
          body: new URLSearchParams({ assignment_id: btn.dataset.id, csrf_token: CSRF_TOKEN }),
        });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'Assignment could not be deleted.');
        btn.closest('tr').remove();
        hideLoading();
        await Swal.fire({ icon: 'success', title: 'Assignment deleted', text: data.message, timer: 1400, showConfirmButton: false });
        location.reload();
      } catch (error) {
        hideLoading();
        btn.disabled = false;
        Swal.fire({ icon: 'error', title: 'Delete failed', text: error.message || 'Assignment could not be deleted.' });
      } finally {
        hideLoading();
      }
    });
  });

  // -------------------------------------------------------------
  // Admin: Bulk Assign (manual entry) — referral link
  // Reference-link verification + Assign button gating.
  //
  // Works generically on any form containing a text_content field, so it
  // applies to the Bulk Assign "Manual Entry" tab.
  // This is a client-side UX layer only — the server (admin_controller.php)
  // still re-validates text_content and reference_link on submit exactly
  // as before, so nothing about the existing backend/API changes.
  // -------------------------------------------------------------
  function isValidHttpUrl(value) {
    if (!value) return true; // empty is fine — reference link stays optional
    try {
      const u = new URL(value);
      return u.protocol === 'http:' || u.protocol === 'https:';
    } catch (e) {
      return false;
    }
  }

  document.querySelectorAll('form').forEach(function (form) {
    if (form.id === 'editAssignmentForm') return;
    const textField = form.querySelector('textarea[name="text_content"]');
    const linkField = form.querySelector('input[name="reference_link"]');
    const submitBtn = form.querySelector('button[type="submit"]');
    if (!textField || !submitBtn) return; // only the Bulk Assign manual tab qualifies

    // Tracks whether the user has actually DONE the required action for
    // the reference link currently in the field — not just typed a valid
    // URL. Resets whenever the link value changes, so re-editing the link
    // re-locks Assign until it's opened again.
    let linkOpened = false;
    let lastCheckedLinkValue = null;

    // --- Reference Link: "open to verify" button + inline validation ---
    let linkOpenBtn = null;
    let linkMsg = null;
    let linkHint = null;
    if (linkField) {
      const linkWrap = linkField.parentElement;

      linkOpenBtn = document.createElement('a');
      linkOpenBtn.target = '_blank';
      linkOpenBtn.rel = 'noopener noreferrer';
      linkOpenBtn.className = 'btn btn-sm btn-outline-primary mt-2 d-none';
      linkOpenBtn.innerHTML = '<i class="bi bi-box-arrow-up-right"></i> Open link to verify';
      linkWrap.appendChild(linkOpenBtn);

      linkMsg = document.createElement('div');
      linkMsg.className = 'text-danger small mt-1 d-none';
      linkMsg.textContent = 'Enter a valid http(s) URL, or leave this field empty.';
      linkWrap.appendChild(linkMsg);

      linkHint = document.createElement('div');
      linkHint.className = 'text-muted small mt-1 d-none';
      linkHint.innerHTML = '<i class="bi bi-info-circle"></i> Open the link above to unlock the submit button.';
      linkWrap.appendChild(linkHint);

      linkOpenBtn.addEventListener('click', function () {
        linkOpened = true;
        revalidate();
      });
    }

    function revalidate() {
      const textOk = textField.value.trim().length > 0;
      const linkValue = linkField ? linkField.value.trim() : '';
      const linkOk = isValidHttpUrl(linkValue);

      // Editing the link value invalidates any previous "opened" click.
      if (linkValue !== lastCheckedLinkValue) {
        linkOpened = false;
        lastCheckedLinkValue = linkValue;
      }

      let readyToSubmit = false;

      if (linkField) {
        if (linkValue !== '' && linkOk) {
          linkOpenBtn.href = linkValue;
          linkOpenBtn.classList.remove('d-none');
          linkHint.classList.toggle('d-none', linkOpened);
          // Link present and valid: must be opened before submitting.
          readyToSubmit = textOk && linkOpened;
        } else {
          linkOpenBtn.classList.add('d-none');
          linkHint.classList.add('d-none');
          // No link: text content is sufficient for submitting.
          readyToSubmit = textOk && linkValue === '';
        }
        linkField.classList.toggle('is-invalid', linkValue !== '' && !linkOk);
        linkMsg.classList.toggle('d-none', linkOk);
      } else {
        // Form has no reference_link field at all — just require text content.
        readyToSubmit = textOk;
      }

      submitBtn.disabled = !readyToSubmit;
    }

    textField.addEventListener('input', revalidate);
    if (linkField) linkField.addEventListener('input', revalidate);

    revalidate(); // set initial state on page load
  });
// -------------------------------------------------------------
  // Login page: look up the browser's own public IP address so it can be
  // logged instead of "::1"/"127.0.0.1" when testing on localhost/XAMPP.
  // -------------------------------------------------------------
 // Look up the browser's own public IP address (on every page) so it can be
// logged instead of "::1"/"127.0.0.1" when testing on localhost/XAMPP.
(function () {
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 2500);
  fetch('https://api.ipify.org?format=json', { signal: controller.signal })
    .then(res => res.json())
    .then(data => {
      if (data && data.ip) {
        // Fill the login form's hidden field if present (kept for backward compatibility)
        const clientIpField = document.getElementById('clientPublicIp');
        if (clientIpField) clientIpField.value = data.ip;

        // Also store it server-side in the session so EVERY page/action can use it.
        // Build the path from the app's own base URL (meta tag) instead of an
        // absolute root path, since the app can be installed in a subfolder
        // (e.g. http://localhost/tdcms) — a hardcoded '/ajax/...' path would
        // 404 in that case and silently fail.
        const appUrlMeta = document.querySelector('meta[name="app-url"]');
        const baseUrl = appUrlMeta ? appUrlMeta.content.replace(/\/$/, '') : '';
        fetch(baseUrl + '/ajax/set_client_ip.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ ip: data.ip })
        }).catch(() => {});
      }
    })
    .catch(() => { /* silently ignore — page still works without it */ })
    .finally(() => clearTimeout(timeout));
})();
  

  
  // Show a spinner automatically on any form submission (non-AJAX forms)
  document.querySelectorAll('form.js-submit-loading').forEach(function (form) {
    form.addEventListener('submit', function () {
      showLoading();
    });
  });
})();
