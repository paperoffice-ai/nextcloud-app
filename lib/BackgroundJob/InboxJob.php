<?php

declare(strict_types=1);

namespace OCA\PaperOfficeAI\BackgroundJob;

use OCA\PaperOfficeAI\Service\InboxService;
use OCA\PaperOfficeAI\Service\SettingsService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

/** Every 5 minutes: upload new files from the inbox folders of all users who enabled it. */
class InboxJob extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		private SettingsService $settings,
		private InboxService $inbox,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);
		$this->setInterval(300);
		$this->setTimeSensitivity(IJob::TIME_SENSITIVE);
		$this->setAllowParallelRuns(false);
	}

	protected function run($argument): void {
		foreach ($this->settings->getInboxUsers() as $uid) {
			try {
				$this->inbox->processUser((string)$uid);
			} catch (\Throwable $e) {
				$this->logger->error('PaperOffice AI inbox failed for {uid}: {msg}', ['uid' => $uid, 'msg' => $e->getMessage(), 'app' => 'paperoffice_ai']);
			}
		}
	}
}
