/**
 * TDCMS — Application JavaScript
 * Handles: dark mode toggle, copy-to-clipboard workflow + popup, AJAX helpers
 * for admin actions (toggle status, delete user, bulk select).
 */
(function () {
  'use strict';

  const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const APP_URL = document.querySelector('meta[name="app-url"]')?.content || '';

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

  // -------------------------------------------------------------
  // Assign workflow (user dashboard)
  //
  // The "Assign" button starts disabled on every card. It only unlocks
  // once the user has done the required action for that specific item:
  //   - Item HAS a reference link  -> must open the reference link first
  //   - Item has NO reference link -> must copy the text first
  // Only after unlocking can "Assign" be clicked, which marks the item
  // completed on the server.
  // -------------------------------------------------------------

  function unlockAssign(card) {
    const assignBtn = card.querySelector('.btn-assign-complete');
    const hint = card.querySelector('.unlock-hint');
    if (assignBtn) assignBtn.disabled = false;
    if (hint) hint.classList.add('d-none');
  }

  // Step 1a: Copy Text — always available. Also unlocks Assign for
  // items that have NO reference link (data-has-link="0").
  document.querySelectorAll('.btn-copy-text').forEach(function (btn) {
    btn.addEventListener('click', async function () {
      const card = btn.closest('.assignment-card');
      const textContent = card.querySelector('.assignment-text').innerText;

      try {
        await copyToClipboard(textContent);
      } catch (err) {
        Swal.fire({ icon: 'error', title: 'Copy failed', text: 'Your browser blocked clipboard access. Please copy manually.' });
        return;
      }

      btn.innerHTML = '<i class="bi bi-clipboard-check"></i> Copied';

      const assignBtn = card.querySelector('.btn-assign-complete');
      if (assignBtn && assignBtn.dataset.hasLink === '0') {
        unlockAssign(card);
      }
    });
  });

  // Step 1b: Open Reference Link — the anchor navigates normally
  // (target="_blank"); we just also unlock Assign for this card.
  document.querySelectorAll('.btn-open-reference').forEach(function (btn) {
    btn.addEventListener('click', async function (event) {
      event.preventDefault();
      const card = btn.closest('.assignment-card');
      unlockAssign(card);
      const url = btn.getAttribute('href');
      if (url) window.open(url, '_blank', 'noopener,noreferrer');

      const confirmation = await Swal.fire({
        title: 'Have you completed this task?',
        text: 'Confirm only after you have pasted or submitted the required content.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes',
        cancelButtonText: 'No',
      });
      if (confirmation.isConfirmed) card.querySelector('.btn-assign-complete')?.click();
    });
  });

  // Step 2: Assign — only reachable once unlocked above. Marks the
  // assignment completed on the server.
  document.querySelectorAll('.btn-assign-complete').forEach(function (btn) {
    btn.addEventListener('click', async function () {
      if (btn.disabled) return; // safety net, shouldn't fire while locked

      const card = btn.closest('.assignment-card');
      const assignmentId = btn.dataset.id;

      btn.disabled = true;
      showLoading();

      try {
        const res = await fetch(APP_URL + '/ajax/copy_text.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': CSRF_TOKEN,
          },
          body: new URLSearchParams({ assignment_id: assignmentId, csrf_token: CSRF_TOKEN }),
        });
        const data = await res.json();
        hideLoading();

        if (!data.success) {
          Swal.fire({ icon: 'warning', title: 'Not completed', text: data.message || 'This item could not be marked complete.' });
          btn.disabled = false;
          return;
        }

        Swal.fire({ icon: 'success', title: 'Assigned', timer: 1200, showConfirmButton: false });

        // Remove the card from the active dashboard
        card.classList.add('opacity-0');
        setTimeout(() => card.remove(), 200);
        updatePendingCountBadge();
      } catch (e) {
        hideLoading();
        btn.disabled = false;
        Swal.fire({ icon: 'error', title: 'Network error', text: 'Please check your connection and try again.' });
      }
    });
  });

  async function copyToClipboard(text) {
    if (navigator.clipboard && window.isSecureContext) {
      await navigator.clipboard.writeText(text);
    } else {
      const ta = document.createElement('textarea');
      ta.value = text;
      ta.style.position = 'fixed';
      ta.style.opacity = '0';
      document.body.appendChild(ta);
      ta.select();
      document.execCommand('copy');
      document.body.removeChild(ta);
    }
  }

  function updatePendingCountBadge() {
    const badge = document.getElementById('pendingCountBadge');
    const remaining = document.querySelectorAll('.assignment-card').length - 1;
    if (badge) badge.textContent = Math.max(0, remaining);
    const emptyState = document.getElementById('emptyState');
    if (remaining <= 0 && emptyState) {
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
  // Admin: Assign Task / Bulk Assign (manual entry) — referral link
  // "verify" + text content "copy" + Assign button gating.
  //
  // Works generically on any form containing a text_content field, so it
  // applies to both the single Assign Task form and the Bulk Assign
  // "Manual Entry" tab without needing separate wiring for each.
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
    if (!textField || !submitBtn) return; // only Assign Task / Bulk Assign manual tab qualify

    // --- Copy button for Text Content ---
    const textWrap = textField.parentElement;
    const copyBtn = document.createElement('button');
    copyBtn.type = 'button';
    copyBtn.className = 'btn btn-sm btn-outline-secondary mt-2 me-2';
    copyBtn.innerHTML = '<i class="bi bi-clipboard"></i> Copy';
    textWrap.appendChild(copyBtn);

    const copyConfirm = document.createElement('span');
    copyConfirm.className = 'text-success small d-none';
    copyConfirm.innerHTML = '<i class="bi bi-check2"></i> Copied!';
    textWrap.appendChild(copyConfirm);

    copyBtn.addEventListener('click', async function () {
      const value = textField.value;
      if (!value.trim()) return;
      try {
        if (navigator.clipboard && window.isSecureContext) {
          await navigator.clipboard.writeText(value);
        } else {
          textField.select();
          document.execCommand('copy');
        }
        copyConfirm.classList.remove('d-none');
        setTimeout(() => copyConfirm.classList.add('d-none'), 1500);
      } catch (e) {
        Swal.fire({ icon: 'error', title: 'Copy failed', text: 'Your browser blocked clipboard access. Please copy manually.' });
      }
    });

    // --- Reference Link: "open to verify" button + inline validation ---
    let linkOpenBtn = null;
    let linkMsg = null;
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
    }

    function revalidate() {
      const textOk = textField.value.trim().length > 0;
      const linkValue = linkField ? linkField.value.trim() : '';
      const linkOk = isValidHttpUrl(linkValue);

      if (linkField) {
        if (linkValue !== '' && linkOk) {
          linkOpenBtn.href = linkValue;
          linkOpenBtn.classList.remove('d-none');
        } else {
          linkOpenBtn.classList.add('d-none');
        }
        linkField.classList.toggle('is-invalid', linkValue !== '' && !linkOk);
        linkMsg.classList.toggle('d-none', linkOk);
      }

      submitBtn.disabled = !(textOk && linkOk);
    }

    textField.addEventListener('input', revalidate);
    if (linkField) linkField.addEventListener('input', revalidate);

    revalidate(); // set initial state on page load
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
  // Admin: bulk assign "select all users" checkbox
  // -------------------------------------------------------------
  const selectAll = document.getElementById('selectAllUsers');
  if (selectAll) {
    selectAll.addEventListener('change', function () {
      document.querySelectorAll('.user-checkbox').forEach(cb => (cb.checked = selectAll.checked));
    });
  }

  // -------------------------------------------------------------
  // Admin: bulk assign — the user picker is shared above both the
  // "Manual Entry" and "Import File" tabs/forms. Before whichever form
  // is submitted, copy the checked user IDs into it as hidden inputs.
  // Registered before the generic loading-spinner handler below so that
  // a failed validation here also stops that spinner from showing.
  // -------------------------------------------------------------
  document.querySelectorAll('form.bulk-assign-form').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      const checked = document.querySelectorAll('.user-checkbox:checked');
      if (checked.length === 0) {
        e.preventDefault();
        e.stopImmediatePropagation();
        Swal.fire({ icon: 'warning', title: 'Select at least one user', text: 'Please choose at least one user before assigning.' });
        return;
      }
      const mount = form.querySelector('.user-ids-mount');
      if (mount) {
        mount.innerHTML = '';
        checked.forEach(function (cb) {
          const input = document.createElement('input');
          input.type = 'hidden';
          input.name = 'user_ids[]';
          input.value = cb.value;
          mount.appendChild(input);
        });
      }
    });
  });

  // Show a spinner automatically on any form submission (non-AJAX forms)
  document.querySelectorAll('form.js-submit-loading').forEach(function (form) {
    form.addEventListener('submit', function () {
      showLoading();
    });
  });
})();
