<?php

declare(strict_types=1);

namespace OCA\PaperOfficeAI\Controller;

use OCA\PaperOfficeAI\AppInfo\Application;
use OCA\PaperOfficeAI\Service\ApiClient;
use OCA\PaperOfficeAI\Service\InboxService;
use OCA\PaperOfficeAI\Service\KeyUnreadableException;
use OCA\PaperOfficeAI\Service\SettingsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IL10N;
use OCP\IRequest;

class SettingsController extends Controller {
	public function __construct(
		IRequest $request,
		private ?string $userId,
		private SettingsService $settings,
		private ApiClient $api,
		private InboxService $inbox,
		private IL10N $l,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	public function get(): JSONResponse {
		return new JSONResponse($this->settings->getPublic((string)$this->userId));
	}

	/** An empty token keeps the stored key; clearKey removes it. */
	#[NoAdminRequired]
	public function save(string $token = '', bool $clearKey = false, string $workspace = '', string $inbox = '', bool $inboxEnabled = false, string $tier = ''): JSONResponse {
		$uid = (string)$this->userId;
		$token = trim($token);
		$workspace = trim($workspace);
		$inbox = trim(str_replace('\\', '/', $inbox), '/ ');
		if ($token !== '') {
			$problem = $this->api->keyProblem($token);
			if ($problem !== null) {
				return new JSONResponse(['message' => $problem], Http::STATUS_BAD_REQUEST);
			}
		}
		if ($workspace === '' || mb_strlen($workspace) > 200) {
			return new JSONResponse(['message' => $this->l->t('Enter a workspace ID or name.')], Http::STATUS_BAD_REQUEST);
		}
		$inboxProblem = $this->inbox->inboxProblem($uid, $inbox);
		if ($inboxProblem !== null) {
			return new JSONResponse(['message' => $inboxProblem], Http::STATUS_BAD_REQUEST);
		}
		if (!in_array($tier, SettingsService::TIERS, true)) {
			$tier = '';
		}
		if ($clearKey) {
			$this->settings->setToken($uid, '');
			$inboxEnabled = false;
		} elseif ($token !== '') {
			$this->settings->setToken($uid, $token);
		}
		$this->settings->setValues($uid, $workspace, $inbox, $inboxEnabled && $this->settings->hasToken($uid), $tier);
		return new JSONResponse($this->settings->getPublic($uid));
	}

	/** Checks the stored key (free) and, for a numeric workspace, that it belongs to the key. */
	#[NoAdminRequired]
	#[UserRateLimit(limit: 10, period: 60)]
	public function test(): JSONResponse {
		$uid = (string)$this->userId;
		try {
			$token = $this->settings->getToken($uid);
		} catch (KeyUnreadableException $e) {
			return new JSONResponse(['ok' => false, 'message' => $this->l->t('The stored key cannot be read. Enter the key again in the settings.')]);
		}
		if ($token === '') {
			return new JSONResponse(['ok' => false, 'message' => $this->l->t('Save an API key first.')]);
		}
		$res = $this->api->capabilities($token);
		if ($res['status'] !== ApiClient::OK) {
			return new JSONResponse(['ok' => false, 'message' => $res['message']]);
		}
		$workspace = $this->settings->getWorkspace($uid);
		if (ctype_digit($workspace)) {
			$ws = $this->api->workspaceExists($token, $workspace);
			if ($ws['status'] === ApiClient::WORKSPACE) {
				$this->settings->setWorkspaceCheck($uid, $workspace, false);
			}
			if ($ws['status'] !== ApiClient::OK) {
				return new JSONResponse(['ok' => false, 'message' => $ws['message']]);
			}
			$this->settings->setWorkspaceCheck($uid, $workspace, true);
		}
		return new JSONResponse(['ok' => true, 'message' => $this->l->t('Connected to PaperOffice AI.')]);
	}
}
