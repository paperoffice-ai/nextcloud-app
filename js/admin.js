/* PaperOffice AI admin settings (no build step). */
(function () {
	'use strict'
	document.addEventListener('DOMContentLoaded', function () {
		const button = document.getElementById('poai-admin-save')
		if (!button) {
			return
		}
		const status = document.getElementById('poai-admin-status')
		button.addEventListener('click', function () {
			fetch(OC.generateUrl('/apps/paperoffice_ai/admin'), {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', requesttoken: OC.requestToken },
				body: JSON.stringify({ groups: document.getElementById('poai-groups').value }),
			}).then(function (r) {
				return r.json().catch(function () { return {} }).then(function (data) {
					status.textContent = r.ok ? t('paperoffice_ai', 'Saved.') : (data.message || t('paperoffice_ai', 'Could not save.'))
					status.style.color = r.ok ? 'var(--color-success-text, green)' : 'var(--color-error-text, #c00)'
				})
			})
		})
	})
})()
