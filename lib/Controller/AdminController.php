<?php

declare(strict_types=1);

namespace OCA\PaperOfficeAI\Controller;

use OCA\PaperOfficeAI\AppInfo\Application;
use OCA\PaperOfficeAI\Service\SettingsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;

/** Admin only (no NoAdminRequired): restrict PaperOffice AI to groups. */
class AdminController extends Controller {
	public function __construct(
		IRequest $request,
		private SettingsService $settings,
		private IGroupManager $groupManager,
		private IL10N $l,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	public function save(string $groups = ''): JSONResponse {
		$list = array_values(array_unique(array_filter(array_map('trim', explode(',', $groups)), fn ($g) => $g !== '')));
		foreach ($list as $gid) {
			if (!$this->groupManager->groupExists($gid)) {
				return new JSONResponse(['message' => $this->l->t('Group %s does not exist.', [$gid])], Http::STATUS_BAD_REQUEST);
			}
		}
		$this->settings->setAllowedGroups($list);
		return new JSONResponse(['groups' => $list, 'removedKeys' => $this->settings->revokeDisallowed()]);
	}
}
