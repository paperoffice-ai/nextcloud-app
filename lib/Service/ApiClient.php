<?php

declare(strict_types=1);

namespace OCA\PaperOfficeAI\Service;

use OCA\PaperOfficeAI\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IL10N;
use OCP\L10N\IFactory;

/** Calls to the public PaperOffice AI API. Keys are never logged. */
class ApiClient {
	public const API_BASE = 'https://api.paperoffice.ai/latest';

	public const OK = 'ok';
	public const AUTH = 'auth';
	public const WORKSPACE = 'workspace';
	public const TEMPORARY = 'temporary';
	public const FAILED = 'failed';
	/** The request may have reached PaperOffice AI, but no answer came back. Never retried automatically. */
	public const UNCLEAR = 'unclear';

	private IL10N $l;

	public function __construct(
		private IClientService $clientService,
		private IAppManager $appManager,
		IFactory $l10nFactory,
	) {
		$this->l = $l10nFactory->get(Application::APP_ID);
	}

	public function keyHelp(): string {
		return $this->l->t('Create a User token (po_ut_…) in PaperOffice AI under Account → API.');
	}

	/** Returns null when the key looks usable, else an error text. */
	public function keyProblem(string $key): ?string {
		if ($key === '') {
			return $this->l->t('Enter an API key.');
		}
		if (str_starts_with($key, 'po_pk_')) {
			return $this->l->t('Publishable keys (po_pk_…) only work in the browser.') . ' ' . $this->keyHelp();
		}
		if (!preg_match('/^po_(ut|gt|sk)_[A-Za-z0-9_-]+$/', $key)) {
			return $this->l->t('This does not look like a PaperOffice AI key.') . ' ' . $this->keyHelp();
		}
		return null;
	}

	/** Free key check. */
	public function capabilities(string $token): array {
		return $this->call('GET', '/paperoffice_account/mcp-token-capabilities', $token);
	}

	/** Checks that a numeric workspace belongs to this key (one paid call). */
	public function workspaceExists(string $token, string $workspaceId): array {
		$res = $this->call('GET', '/documents/workspaces-list', $token);
		if ($res['status'] !== self::OK) {
			return $res;
		}
		foreach (($res['data']['workspaces'] ?? []) as $ws) {
			if ((string)($ws['id'] ?? '') === $workspaceId) {
				return $res;
			}
		}
		return ['status' => self::WORKSPACE, 'message' => $this->l->t('Workspace %s is not available for this key.', [$workspaceId]), 'data' => [], 'http' => 200];
	}

	/**
	 * Uploads one file. $workspace is a numeric ID or a name (a new name
	 * creates the workspace).
	 *
	 * @param resource $stream
	 */
	public function upload(string $token, string $workspace, string $tier, string $fileName, $stream): array {
		// Guzzle does not escape the multipart filename; quotes, backslashes and
		// control characters would break the Content-Disposition header.
		$safeName = preg_replace('/["\\\\\x00-\x1F\x7F]/', '_', $fileName) ?? 'document';
		$multipart = [
			['name' => 'file', 'contents' => $stream, 'filename' => $safeName],
		];
		if (ctype_digit($workspace)) {
			$multipart[] = ['name' => 'workspace_id', 'contents' => $workspace];
		} else {
			$multipart[] = ['name' => 'workspace_name', 'contents' => $workspace];
		}
		if ($tier !== '') {
			$multipart[] = ['name' => 'ai_dms_tier', 'contents' => $tier];
		}
		$res = $this->call('POST', '/documents/document-put', $token, ['multipart' => $multipart, 'timeout' => 300], true);
		if ($res['status'] === self::OK) {
			$res['pofid'] = (string)($res['data']['results'][0]['pofid'] ?? '');
			if (($res['data']['status'] ?? '') !== 'success' || $res['pofid'] === '') {
				$res['status'] = self::FAILED;
				$res['message'] = trim($this->l->t('Unexpected answer from PaperOffice AI (HTTP %s).', [(string)$res['http']]) . ' ' . (string)($res['data']['code'] ?? '') . ' ' . (string)($res['data']['message'] ?? ''));
			}
		}
		return $res;
	}

	private function call(string $method, string $path, string $token, array $options = [], bool $isWrite = false): array {
		$options['headers'] = [
			'Authorization' => 'Bearer ' . $token,
			'User-Agent' => 'PaperOffice-Nextcloud/' . $this->appManager->getAppVersion(Application::APP_ID),
			'Accept' => 'application/json',
		];
		$options['timeout'] = $options['timeout'] ?? 30;
		$options['http_errors'] = false;
		$client = $this->clientService->newClient();
		try {
			$response = $method === 'GET'
				? $client->get(self::API_BASE . $path, $options)
				: $client->post(self::API_BASE . $path, $options);
		} catch (\Exception $e) {
			if ($isWrite && !self::neverConnected($e)) {
				return ['status' => self::UNCLEAR, 'message' => $this->l->t('No answer from PaperOffice AI. The file may already have been uploaded. Check your workspace; to upload again, move the file back into the inbox folder.'), 'data' => [], 'http' => 0];
			}
			return ['status' => self::TEMPORARY, 'message' => $this->l->t('No connection to api.paperoffice.ai.'), 'data' => [], 'http' => 0];
		}
		return $this->result($response);
	}

	/**
	 * True only when the request cannot have reached the server: DNS failure
	 * (cURL 6), connection refused (7) or a timeout while connecting (28).
	 * An empty reply (52) or a timeout after sending is not safe to repeat.
	 */
	private static function neverConnected(\Exception $e): bool {
		$context = method_exists($e, 'getHandlerContext') ? $e->getHandlerContext() : [];
		$errno = (int)($context['errno'] ?? 0);
		if ($errno === 6 || $errno === 7) {
			return true;
		}
		return $errno === 28 && str_contains($e->getMessage(), 'Connection timed out');
	}

	private function result(IResponse $response): array {
		$code = $response->getStatusCode();
		$data = json_decode((string)$response->getBody(), true);
		$data = is_array($data) ? $data : [];
		$errCode = (string)($data['code'] ?? $data['error_code'] ?? '');
		$message = (string)($data['message'] ?? '');
		if ($code >= 200 && $code < 300) {
			return ['status' => self::OK, 'message' => '', 'data' => $data, 'http' => $code];
		}
		if (($code === 401 || $code === 403) && str_contains($errCode, 'WORKSPACE')) {
			return ['status' => self::WORKSPACE, 'message' => $this->l->t('The workspace cannot be used with this key (%s). When you enter a workspace name, the key needs the right to create workspaces.', [$errCode]), 'data' => $data, 'http' => $code];
		}
		if ($code === 401 || $code === 403) {
			return ['status' => self::AUTH, 'message' => $this->l->t('The key was rejected (HTTP %s).', [(string)$code]) . ' ' . $this->keyHelp(), 'data' => $data, 'http' => $code];
		}
		if ($code === 408 || $code === 429 || $code >= 500) {
			return ['status' => self::TEMPORARY, 'message' => $this->l->t('PaperOffice AI is busy (HTTP %s). It will be retried.', [(string)$code]), 'data' => $data, 'http' => $code];
		}
		return ['status' => self::FAILED, 'message' => trim('HTTP ' . $code . ' ' . $errCode . ': ' . $message), 'data' => $data, 'http' => $code];
	}
}
