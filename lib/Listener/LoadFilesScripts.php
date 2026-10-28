<?php

declare(strict_types=1);

namespace OCA\PaperOfficeAI\Listener;

use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\PaperOfficeAI\AppInfo\Application;
use OCA\PaperOfficeAI\Service\SettingsService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IUserSession;
use OCP\Util;

/** Adds the "Send to PaperOffice AI" file action for users who connected a key. */
class LoadFilesScripts implements IEventListener {
	public function __construct(
		private IUserSession $userSession,
		private SettingsService $settings,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof LoadAdditionalScriptsEvent) {
			return;
		}
		$user = $this->userSession->getUser();
		if ($user === null || !$this->settings->hasToken($user->getUID())) {
			return;
		}
		Util::addScript(Application::APP_ID, 'fileaction');
	}
}
