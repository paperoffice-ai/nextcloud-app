<?php

declare(strict_types=1);

namespace OCA\PaperOfficeAI\Settings;

use OCA\PaperOfficeAI\AppInfo\Application;
use OCA\PaperOfficeAI\Service\SettingsService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IUserSession;
use OCP\Settings\ISettings;
use OCP\Util;

class Personal implements ISettings {
	public function __construct(
		private IUserSession $userSession,
		private SettingsService $settings,
	) {
	}

	public function getForm(): TemplateResponse {
		$uid = (string)$this->userSession->getUser()?->getUID();
		Util::addScript(Application::APP_ID, 'personal');
		return new TemplateResponse(Application::APP_ID, 'personal', [
			'settings' => $this->settings->getPublic($uid),
			'allowed' => $this->settings->isAllowed($uid),
		]);
	}

	public function getSection(): string {
		return Application::APP_ID;
	}

	public function getPriority(): int {
		return 10;
	}
}
