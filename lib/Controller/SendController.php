<?php

declare(strict_types=1);

namespace OCA\PaperOfficeAI\Controller;

use OCA\PaperOfficeAI\AppInfo\Application;
use OCA\PaperOfficeAI\Service\ApiClient;
use OCA\PaperOfficeAI\Service\InboxService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class SendController extends Controller {
	public function __construct(
		IRequest $request,
		private ?string $userId,
		private InboxService $inbox,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	#[UserRateLimit(limit: 60, period: 60)]
	public function send(?int $fileId = null): JSONResponse {
		if ($fileId === null || $fileId <= 0) {
			return new JSONResponse(['message' => 'fileId is required.'], Http::STATUS_BAD_REQUEST);
		}
		$res = $this->inbox->sendFile((string)$this->userId, $fileId);
		if ($res['status'] === ApiClient::OK) {
			return new JSONResponse(['pofid' => $res['pofid'] ?? '']);
		}
		$code = $res['status'] === ApiClient::TEMPORARY ? Http::STATUS_SERVICE_UNAVAILABLE : Http::STATUS_BAD_REQUEST;
		return new JSONResponse(['message' => $res['message']], $code);
	}
}
