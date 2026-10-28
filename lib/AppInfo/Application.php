<?php

declare(strict_types=1);

namespace OCA\PaperOfficeAI\AppInfo;

use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\PaperOfficeAI\Listener\GroupMembershipListener;
use OCA\PaperOfficeAI\Listener\LoadFilesScripts;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Group\Events\GroupDeletedEvent;
use OCP\Group\Events\UserRemovedEvent;

class Application extends App implements IBootstrap {
	public const APP_ID = 'paperoffice_ai';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(LoadAdditionalScriptsEvent::class, LoadFilesScripts::class);
		$context->registerEventListener(UserRemovedEvent::class, GroupMembershipListener::class);
		$context->registerEventListener(GroupDeletedEvent::class, GroupMembershipListener::class);
	}

	public function boot(IBootContext $context): void {
	}
}
