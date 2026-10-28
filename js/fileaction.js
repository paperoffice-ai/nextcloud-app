/* PaperOffice AI: "Send to PaperOffice AI" action in the Files app (no build step).
 * Registers where @nextcloud/files registerFileAction() keeps its actions:
 * v3 (Nextcloud 31/32) window._nc_fileactions, v4 (Nextcloud 33+) window._nc_files_scope.v4_0.
 * v3 calls enabled(nodes, view) and exec(node, view, dir); v4 passes one context object { nodes, view, folder, contents }. */
(function () {
	'use strict'
	const ICON = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 2384 2384"><path fill="currentColor" d="M1168.62 2374.28l-1158.94 -1158.94c-12.9,-12.9 -12.9,-33.82 0,-46.72l1158.94 -1158.94c12.9,-12.9 33.82,-12.9 46.72,0l413.91 413.91c12.9,12.9 12.9,33.82 0,46.72l-165.56 165.56c-12.9,12.9 -33.82,12.9 -46.72,0l-224.99 -224.99 -781.1 781.1 781.1 781.09 970.02 -970.02c12.9,-12.9 33.82,-12.9 46.72,0l165.56 165.56c12.9,12.9 12.9,33.82 0,46.72l-1158.95 1158.94c-12.9,12.9 -33.82,12.9 -46.72,0z"/><path fill="currentColor" d="M2124.67 966.99l-910.6 910.6c-12.9,12.9 -33.82,12.9 -46.72,0l-662.26 -662.25c-12.9,-12.9 -12.9,-33.82 0,-46.72l662.26 -662.25c12.9,-12.9 33.82,-12.9 46.72,0l165.56 165.56c12.9,12.9 12.9,33.82 0,46.72l-473.33 473.33 284.41 284.41 721.68 -721.68c12.9,-12.9 33.82,-12.9 46.72,0l165.56 165.56c12.9,12.9 12.9,33.82 0,46.72z"/><path fill="currentColor" d="M1006.95 1168.62l662.26 -662.25c12.9,-12.9 33.82,-12.9 46.72,0l165.56 165.56c12.9,12.9 12.9,33.82 0,46.72l-662.26 662.25c-12.9,12.9 -33.82,12.9 -46.72,0l-165.56 -165.56c-12.9,-12.9 -12.9,-33.82 0,-46.72z"/></svg>'
	// OC.Notification is gone in Nextcloud 35. Without it, the Files app reports
	// the boolean result of exec() itself (true = success, false = failed).
	function notify(text, isError) {
		if (window.OC && OC.Notification && typeof OC.Notification.showTemporary === 'function') {
			OC.Notification.showTemporary(text, isError ? { type: 'error' } : {})
			return null
		}
		return !isError
	}
	function nodesOf(arg) {
		return Array.isArray(arg) ? arg : (arg && arg.nodes) || []
	}
	const action = {
		id: 'paperoffice-ai-send',
		order: 80,
		displayName: function () { return t('paperoffice_ai', 'Send to PaperOffice AI') },
		title: function () { return t('paperoffice_ai', 'Upload this file to your PaperOffice AI workspace') },
		iconSvgInline: function () { return ICON },
		enabled: function (arg) {
			const nodes = nodesOf(arg)
			return nodes.length === 1 && nodes[0].type === 'file' && (nodes[0].permissions & 1) !== 0
		},
		exec: function (arg) {
			const node = Array.isArray(arg) || !arg.nodes ? arg : arg.nodes[0]
			return fetch(OC.generateUrl('/apps/paperoffice_ai/send'), {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', requesttoken: OC.requestToken },
				body: JSON.stringify({ fileId: node.fileid }),
			}).then(function (r) {
				return r.json().then(function (data) {
					if (r.ok) {
						return notify(t('paperoffice_ai', 'Sent to PaperOffice AI: {name}', { name: node.basename }), false)
					}
					return notify(data.message || t('paperoffice_ai', 'Could not send the file.'), true)
				})
			}).catch(function () {
				return notify(t('paperoffice_ai', 'Could not send the file.'), true)
			})
		},
	}
	// Each register is filled on its own, so an unexpected structure in one does not block the other.
	try {
		if (window._nc_fileactions === undefined) {
			window._nc_fileactions = []
		}
		if (!window._nc_fileactions.find(function (a) { return a.id === action.id })) {
			window._nc_fileactions.push(action)
		}
	} catch (e) {
		console.warn('PaperOffice AI: file action (v3) not registered', e)
	}
	try {
		window._nc_files_scope = window._nc_files_scope || {}
		const scope = window._nc_files_scope.v4_0 = window._nc_files_scope.v4_0 || {}
		scope.fileActions = scope.fileActions || new Map()
		if (!scope.fileActions.has(action.id)) {
			scope.fileActions.set(action.id, action)
			if (scope.registry && typeof scope.registry.dispatchEvent === 'function') {
				scope.registry.dispatchEvent(new CustomEvent('register:action', { detail: action }))
			}
		}
	} catch (e) {
		console.warn('PaperOffice AI: file action (v4) not registered', e)
	}
})()
