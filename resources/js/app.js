import './bootstrap';
import { Passkeys } from '@laravel/passkeys';

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

Passkeys.configure({
	fetch: {
		headers: {
			Accept: 'application/json',
			'X-CSRF-TOKEN': csrfToken,
			'X-Requested-With': 'XMLHttpRequest',
		},
	},
});

function renderPasskeyFeedback(target, message, tone = 'info') {
	if (! target) {
		return;
	}

	const alertClass = {
		success: 'alert-success',
		error: 'alert-danger',
		info: 'alert-secondary',
	}[tone] ?? 'alert-secondary';

	target.innerHTML = message
		? `<div class="alert ${alertClass} py-2 px-3 mb-0">${message}</div>`
		: '';
}

function getErrorMessage(error, fallback) {
	return error?.message || error?.cause?.message || fallback;
}

async function deletePasskey(url) {
	const response = await fetch(url, {
		method: 'DELETE',
		headers: {
			Accept: 'application/json',
			'X-CSRF-TOKEN': csrfToken,
			'X-Requested-With': 'XMLHttpRequest',
		},
		credentials: 'same-origin',
	});

	if (! response.ok) {
		const payload = await response.json().catch(() => null);
		throw new Error(payload?.message || 'Unable to remove this passkey right now.');
	}

	return response.json().catch(() => ({}));
}

function setBusyState(button, isBusy) {
	if (! button) {
		return;
	}

	if (! button.dataset.idleHtml) {
		button.dataset.idleHtml = button.innerHTML;
	}

	button.disabled = isBusy;
	button.innerHTML = isBusy
		? (button.dataset.busyLabel ?? 'Working...')
		: button.dataset.idleHtml;
}

function initialiseStorefrontDropdowns() {
	const toggles = Array.from(document.querySelectorAll('[data-mf-dropdown-toggle]'));

	if (! toggles.length) {
		return;
	}

	const closeMenu = (toggle) => {
		const dropdown = toggle.closest('.dropdown');
		const menu = dropdown?.querySelector('.dropdown-menu');

		if (! menu) {
			return;
		}

		toggle.setAttribute('aria-expanded', 'false');
		menu.classList.remove('show');
	};

	const openMenu = (toggle) => {
		const dropdown = toggle.closest('.dropdown');
		const menu = dropdown?.querySelector('.dropdown-menu');

		if (! menu) {
			return;
		}

		toggles.forEach((candidate) => {
			if (candidate !== toggle) {
				closeMenu(candidate);
			}
		});

		toggle.setAttribute('aria-expanded', 'true');
		menu.classList.add('show');
	};

	toggles.forEach((toggle) => {
		toggle.addEventListener('click', (event) => {
			event.preventDefault();

			const menu = toggle.closest('.dropdown')?.querySelector('.dropdown-menu');
			const isOpen = menu?.classList.contains('show');

			if (isOpen) {
				closeMenu(toggle);
				return;
			}

			openMenu(toggle);
		});
	});

	document.addEventListener('click', (event) => {
		toggles.forEach((toggle) => {
			const dropdown = toggle.closest('.dropdown');

			if (dropdown && ! dropdown.contains(event.target)) {
				closeMenu(toggle);
			}
		});
	});

	document.addEventListener('keydown', (event) => {
		if (event.key !== 'Escape') {
			return;
		}

		toggles.forEach(closeMenu);
	});
}

function initialiseLoginPasskeys() {
	const loginButton = document.querySelector('[data-passkey-login]');

	if (! loginButton) {
		return;
	}

	const feedback = document.querySelector('[data-passkey-login-feedback]');
	const helpText = document.querySelector('[data-passkey-login-help]');

	if (! Passkeys.isSupported()) {
		loginButton.disabled = true;
		helpText?.classList.add('text-warning');
		renderPasskeyFeedback(feedback, 'Passkeys are not available in this browser or on this device.', 'info');
		return;
	}

	Passkeys.autofill().catch(() => {
		// Ignore autofill failures. The explicit sign-in button remains available.
	});

	loginButton.addEventListener('click', async () => {
		renderPasskeyFeedback(feedback, '');
		setBusyState(loginButton, true);

		try {
			const response = await Passkeys.verify();

			if (response?.redirect) {
				window.location.assign(response.redirect);
				return;
			}

			window.location.assign('/my-account');
		} catch (error) {
			renderPasskeyFeedback(feedback, getErrorMessage(error, 'Passkey sign-in failed. Try again or use your password.'), 'error');
		} finally {
			setBusyState(loginButton, false);
		}
	});
}

function initialisePasskeyManager() {
	const manager = document.querySelector('[data-passkey-manager]');

	if (! manager) {
		return;
	}

	const form = manager.querySelector('[data-passkey-register-form]');
	const nameInput = manager.querySelector('[data-passkey-name]');
	const registerButton = manager.querySelector('[data-passkey-register-button]');
	const feedback = manager.querySelector('[data-passkey-feedback]');
	const deleteButtons = manager.querySelectorAll('[data-passkey-delete]');

	if (! Passkeys.isSupported()) {
		nameInput?.setAttribute('disabled', 'disabled');
		registerButton?.setAttribute('disabled', 'disabled');
		renderPasskeyFeedback(feedback, 'This browser cannot register passkeys. Use a recent browser on a secure HTTPS connection.', 'info');
		return;
	}

	form?.addEventListener('submit', async (event) => {
		event.preventDefault();

		const name = nameInput?.value.trim() || '';

		if (! name) {
			renderPasskeyFeedback(feedback, 'Enter a device name so you can recognize this passkey later.', 'error');
			nameInput?.focus();
			return;
		}

		renderPasskeyFeedback(feedback, '');
		setBusyState(registerButton, true);

		try {
			await Passkeys.register({ name });
			renderPasskeyFeedback(feedback, 'Passkey added. Refreshing your security list...', 'success');
			window.location.reload();
		} catch (error) {
			renderPasskeyFeedback(feedback, getErrorMessage(error, 'Unable to register a passkey right now.'), 'error');
		} finally {
			setBusyState(registerButton, false);
		}
	});

	deleteButtons.forEach((button) => {
		button.addEventListener('click', async () => {
			const name = button.getAttribute('data-passkey-name-label') || 'this passkey';
			const url = button.getAttribute('data-passkey-delete-url');

			if (! url || ! window.confirm(`Remove ${name}? You can still sign in with your password or other passkeys.`)) {
				return;
			}

			renderPasskeyFeedback(feedback, '');
			setBusyState(button, true);

			try {
				await deletePasskey(url);
				renderPasskeyFeedback(feedback, 'Passkey removed. Refreshing your security list...', 'success');
				window.location.reload();
			} catch (error) {
				renderPasskeyFeedback(feedback, getErrorMessage(error, 'Unable to remove this passkey right now.'), 'error');
				setBusyState(button, false);
			}
		});
	});
}

document.addEventListener('DOMContentLoaded', () => {
	initialiseStorefrontDropdowns();
	initialiseLoginPasskeys();
	initialisePasskeyManager();
});
