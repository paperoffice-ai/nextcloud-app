<?php

declare(strict_types=1);

namespace OCA\PaperOfficeAI\Listener;

use OCA\PaperOfficeAI\Service\SettingsService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Group\Events\GroupDeletedEvent;
use OCP\Group\Events\UserRemovedEvent;

/** Removes stored keys of users who are no longer in an allowed group. */
class GroupMembershipListener implements IEventListener {
	public function __construct(
		private SettingsService $settings,
	) {
	}

	public function handle(Event $event): void {
		if ($this->settings->getAllowedGroups() === []) {
			return;
		}
		if ($event instanceof UserRemovedEvent) {
			$uid = $event->getUser()->getUID();
			if ($this->settings->hasToken($uid) && !$this->settings->isAllowed($uid)) {
				$this->settings->setToken($uid, '');
				$this->settings->disableInbox($uid);
			}
		} elseif ($event instanceof GroupDeletedEvent) {
			$this->settings->revokeDisallowed();
		}
	}
}
