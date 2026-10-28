<?php

declare(strict_types=1);

namespace OCA\PaperOfficeAI\Settings;

use OCA\PaperOfficeAI\AppInfo\Application;
use OCA\PaperOfficeAI\Service\SettingsService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;
use OCP\Util;

/** Admin setting: which groups may connect PaperOffice AI (default: all users). */
class Admin implements ISettings {
	public function __construct(
		private SettingsService $settings,
	) {
	}

	public function getForm(): TemplateResponse {
		Util::addScript(Application::APP_ID, 'admin');
		return new TemplateResponse(Application::APP_ID, 'admin', ['groups' => implode(', ', $this->settings->getAllowedGroups())]);
	}

	public function getSection(): string {
		return Application::APP_ID;
	}

	public function getPriority(): int {
		return 10;
	}
}
