/* PaperOffice AI personal settings (no build step). */
(function () {
	'use strict'
	function $(id) { return document.getElementById(id) }
	function status(text, ok) {
		const el = $('poai-status')
		el.textContent = text
		el.style.color = ok ? 'var(--color-success-text, green)' : 'var(--color-error-text, #c00)'
	}
	function request(method, path, body) {
		return fetch(OC.generateUrl('/apps/paperoffice_ai' + path), {
			method,
			headers: { 'Content-Type': 'application/json', requesttoken: OC.requestToken },
			body: body ? JSON.stringify(body) : undefined,
		}).then(function (r) {
			return r.json().catch(function () { return {} }).then(function (data) {
				if (r.status === 429) {
					data = { message: t('paperoffice_ai', 'Too many attempts. Try again in a minute.') }
				}
				return { ok: r.ok, data }
			})
		})
	}
	function save(clearKey) {
		const body = {
			token: $('poai-token').value.trim(),
			clearKey: !!clearKey,
			workspace: $('poai-workspace').value.trim(),
			inbox: $('poai-inbox').value.trim(),
			inboxEnabled: $('poai-inbox-enabled').checked,
			tier: $('poai-tier').value,
		}
		return request('POST', '/settings', body).then(function (res) {
			if (!res.ok) {
				status(res.data.message || t('paperoffice_ai', 'Could not save.'), false)
				return false
			}
			$('poai-token').value = ''
			$('poai-token').placeholder = res.data.hasToken ? t('paperoffice_ai', 'Saved – enter a new key to replace it') : 'po_ut_…'
			$('poai-clear').disabled = !res.data.hasToken
			$('poai-inbox-enabled').checked = res.data.inboxEnabled
			status(t('paperoffice_ai', 'Saved.'), true)
			return true
		})
	}
	document.addEventListener('DOMContentLoaded', function () {
		if (!$('paperoffice-ai-settings')) {
			return
		}
		// Without the group right only the stored key can be removed.
		if ($('poai-clear-only')) {
			$('poai-clear-only').addEventListener('click', function () {
				request('POST', '/settings', { clearKey: true }).then(function (res) {
					status(res.ok ? t('paperoffice_ai', 'Key removed.') : (res.data.message || t('paperoffice_ai', 'Could not save.')), res.ok)
					if (res.ok) {
						$('poai-clear-only').disabled = true
					}
				})
			})
			return
		}
		$('poai-save').addEventListener('click', function () { save(false) })
		$('poai-clear').addEventListener('click', function () { save(true) })
		$('poai-test').addEventListener('click', function () {
			status(t('paperoffice_ai', 'Testing…'), true)
			request('POST', '/settings/test').then(function (res) {
				status(res.data.message || '', !!res.data.ok)
			})
		})
	})
})()
