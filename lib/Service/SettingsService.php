<?php

declare(strict_types=1);

namespace OCA\PaperOfficeAI\Service;

use OCA\PaperOfficeAI\AppInfo\Application;
use OCP\IConfig;
use OCP\Security\ICrypto;

/**
 * Per-user settings. The API key is stored encrypted and never returned to
 * the browser.
 */
class SettingsService {
	public const TIERS = ['', 'basic', 'premium', 'ultra'];
	public const DEFAULT_INBOX = 'PaperOffice-Inbox';
	public const DEFAULT_WORKSPACE = 'Nextcloud Inbox';
	/** A successful workspace check is reused this long (it is a paid call). */
	public const WORKSPACE_CHECK_TTL = 86400;

	public function __construct(
		private IConfig $config,
		private ICrypto $crypto,
	) {
	}

	public function hasToken(string $uid): bool {
		return $this->get($uid, 'token') !== '';
	}

	public function getToken(string $uid): string {
		$enc = $this->get($uid, 'token');
		if ($enc === '') {
			return '';
		}
		try {
			return $this->crypto->decrypt($enc);
		} catch (\Exception $e) {
			// Happens when the instance secret changed; the key has to be entered again.
			throw new KeyUnreadableException('The stored PaperOffice AI key cannot be decrypted. Enter the key again.', 0, $e);
		}
	}

	public function setToken(string $uid, string $token): void {
		$this->set($uid, 'token', $token === '' ? '' : $this->crypto->encrypt($token));
		$this->config->deleteUserValue($uid, Application::APP_ID, 'workspace_checked');
	}

	/** Settings that may be shown in the browser (no key). */
	public function getPublic(string $uid): array {
		return [
			'hasToken' => $this->hasToken($uid),
			'workspace' => $this->get($uid, 'workspace', self::DEFAULT_WORKSPACE),
			'inbox' => $this->get($uid, 'inbox', self::DEFAULT_INBOX),
			'inboxEnabled' => $this->get($uid, 'inbox_enabled', '0') === '1',
			'tier' => $this->get($uid, 'tier'),
		];
	}

	public function getWorkspace(string $uid): string {
		return $this->get($uid, 'workspace', self::DEFAULT_WORKSPACE);
	}

	public function getInbox(string $uid): string {
		return $this->get($uid, 'inbox', self::DEFAULT_INBOX);
	}

	public function getTier(string $uid): string {
		$tier = $this->get($uid, 'tier');
		return in_array($tier, self::TIERS, true) ? $tier : '';
	}

	public function setValues(string $uid, string $workspace, string $inbox, bool $inboxEnabled, string $tier): void {
		$this->set($uid, 'workspace', $workspace);
		$this->set($uid, 'inbox', $inbox);
		$this->set($uid, 'inbox_enabled', $inboxEnabled ? '1' : '0');
		$this->set($uid, 'tier', $tier);
		$this->config->deleteUserValue($uid, Application::APP_ID, 'workspace_checked');
	}

	/**
	 * Cached result of the paid workspace check: "ok" within the TTL, "bad"
	 * until the settings or the key are saved again, "" when unknown.
	 */
	public function getWorkspaceCheck(string $uid, string $workspace): string {
		[$state, $ws, $time] = array_pad(explode('|', $this->get($uid, 'workspace_checked'), 3), 3, '0');
		if ($ws !== $workspace) {
			return '';
		}
		if ($state === 'bad') {
			return 'bad';
		}
		return $state === 'ok' && time() - (int)$time < self::WORKSPACE_CHECK_TTL ? 'ok' : '';
	}

	public function setWorkspaceCheck(string $uid, string $workspace, bool $ok): void {
		$this->set($uid, 'workspace_checked', ($ok ? 'ok' : 'bad') . '|' . $workspace . '|' . time());
	}

	public function clearWorkspaceCheck(string $uid): void {
		$this->config->deleteUserValue($uid, Application::APP_ID, 'workspace_checked');
	}

	/** @return string[] users with an enabled inbox */
	public function getInboxUsers(): array {
		return $this->config->getUsersForUserValue(Application::APP_ID, 'inbox_enabled', '1');
	}

	/**
	 * Upload marker per file: "pending|<stamp>" while the upload runs,
	 * "done|<stamp>" after success until the file has been moved.
	 */
	public function setSentMarker(string $uid, int $fileId, string $state, string $stamp): void {
		$this->set($uid, 'sent_' . $fileId, $state . '|' . $stamp);
	}

	/** True at most once per day and file: report a file that cannot be moved. */
	public function shouldReportStuck(string $uid, int $fileId): bool {
		$today = date('Y-m-d');
		if ($this->get($uid, 'stuck_' . $fileId) === $today) {
			return false;
		}
		$this->set($uid, 'stuck_' . $fileId, $today);
		return true;
	}

	/** @return int[] file IDs that have a marker */
	public function getMarkedFileIds(string $uid): array {
		$ids = [];
		foreach ($this->config->getUserKeys($uid, Application::APP_ID) as $key) {
			if (str_starts_with($key, 'sent_')) {
				$ids[] = (int)substr($key, 5);
			}
		}
		return $ids;
	}

	public function getSentMarker(string $uid, int $fileId): string {
		return $this->get($uid, 'sent_' . $fileId);
	}

	public function clearSentMarker(string $uid, int $fileId): void {
		$this->config->deleteUserValue($uid, Application::APP_ID, 'sent_' . $fileId);
		$this->config->deleteUserValue($uid, Application::APP_ID, 'stuck_' . $fileId);
	}

	private function get(string $uid, string $key, string $default = ''): string {
		return (string)$this->config->getUserValue($uid, Application::APP_ID, $key, $default);
	}

	private function set(string $uid, string $key, string $value): void {
		$this->config->setUserValue($uid, Application::APP_ID, $key, $value);
	}
}
