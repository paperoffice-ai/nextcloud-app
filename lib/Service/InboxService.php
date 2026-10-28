<?php

declare(strict_types=1);

namespace OCA\PaperOfficeAI\Service;

use OCA\PaperOfficeAI\AppInfo\Application;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\Files\Storage\ISharedStorage;
use OCP\IL10N;
use OCP\L10N\IFactory;
use Psr\Log\LoggerInterface;

/** Uploads new files from a user's inbox folder and moves them to _uploaded. */
class InboxService {
	public const DONE_DIR = '_uploaded';
	public const FAIL_DIR = '_failed';
	/** Files must be unchanged this long before upload (still being copied otherwise). */
	public const MIN_AGE = 60;
	/** Upper bound per user and run, the rest follows in the next run. */
	public const MAX_FILES_PER_RUN = 50;
	/** Larger files are not uploaded automatically. */
	public const MAX_FILE_BYTES = 80 * 1024 * 1024;
	/** A file that cannot be read now; it is tried again in the next run. */
	private const SKIPPED = 'skipped';

	private IL10N $l;

	public function __construct(
		private IRootFolder $rootFolder,
		private SettingsService $settings,
		private ApiClient $api,
		private LoggerInterface $logger,
		IFactory $l10nFactory,
	) {
		$this->l = $l10nFactory->get(Application::APP_ID);
	}

	/**
	 * Returns an error text when $path cannot be used as inbox, else null.
	 * The inbox must be a real sub folder of the user's own files: not the
	 * home folder itself, not a received share and not external storage.
	 */
	public function inboxProblem(string $uid, string $path): ?string {
		$segments = explode('/', $path);
		foreach ($segments as $segment) {
			if ($segment === '' || $segment === '.' || $segment === '..') {
				return $this->l->t('Enter a sub folder of your files for the inbox, for example PaperOffice-Inbox.');
			}
		}
		if (in_array(end($segments), [self::DONE_DIR, self::FAIL_DIR], true)) {
			return $this->l->t('The inbox folder cannot be named _uploaded or _failed.');
		}
		$userFolder = $this->rootFolder->getUserFolder($uid);
		if (!$userFolder->nodeExists($path)) {
			// A new inbox would be created inside its nearest existing parent.
			$parent = dirname($path);
			while ($parent !== '.' && !$userFolder->nodeExists($parent)) {
				$parent = dirname($parent);
			}
			if ($parent !== '.' && $this->isForeign($userFolder->get($parent))) {
				return $this->l->t('The inbox folder must be in your own files, not in a share or external storage.');
			}
			return null;
		}
		$node = $userFolder->get($path);
		if (!$node instanceof Folder) {
			return $this->l->t('The inbox must be a folder.');
		}
		if ($this->isForeign($node) || $node->getPath() === $userFolder->getPath()) {
			return $this->l->t('The inbox folder must be in your own files, not in a share or external storage.');
		}
		return null;
	}

	public function processUser(string $uid): void {
		try {
			$token = $this->settings->getToken($uid);
		} catch (KeyUnreadableException $e) {
			$this->logger->warning('PaperOffice AI inbox of {uid} paused: {msg}', ['uid' => $uid, 'msg' => $e->getMessage(), 'app' => Application::APP_ID]);
			return;
		}
		if ($token === '') {
			return;
		}
		$path = trim($this->settings->getInbox($uid), '/');
		if ($this->inboxProblem($uid, $path) !== null) {
			$this->logger->warning('PaperOffice AI inbox of {uid} paused: invalid inbox folder', ['uid' => $uid, 'app' => Application::APP_ID]);
			return;
		}
		$inbox = $this->inboxFolder($uid, $path);
		if ($inbox === null) {
			return;
		}
		$this->cleanupMarkers($uid);
		$workspace = $this->settings->getWorkspace($uid);
		$count = 0;
		foreach ($this->files($inbox) as $file) {
			if ($count >= self::MAX_FILES_PER_RUN) {
				break;
			}
			// The workspace check is a paid call: only when there is something to upload, and cached.
			if ($count === 0 && !$this->workspaceUsable($uid, $token, $workspace)) {
				return;
			}
			// Stop only for problems that affect every file: key, workspace, or PaperOffice AI unreachable/busy.
			if ($this->processFile($uid, $token, $workspace, $inbox, $file)) {
				break;
			}
			$count++;
		}
	}

	/** Uploads one file without moving it (file action). */
	public function sendFile(string $uid, int $fileId): array {
		try {
			$token = $this->settings->getToken($uid);
		} catch (KeyUnreadableException $e) {
			return ['status' => ApiClient::AUTH, 'message' => $this->l->t('The stored key cannot be read. Enter the key again in the settings.')];
		}
		if ($token === '') {
			return ['status' => ApiClient::AUTH, 'message' => $this->api->keyHelp()];
		}
		$file = $this->rootFolder->getUserFolder($uid)->getFirstNodeById($fileId);
		if (!$file instanceof File || !$file->isReadable()) {
			return ['status' => ApiClient::FAILED, 'message' => $this->l->t('File not found.')];
		}
		$blocked = $this->downloadBlocked($file);
		if ($blocked !== null) {
			return ['status' => ApiClient::FAILED, 'message' => $blocked];
		}
		if ($file->getSize() > self::MAX_FILE_BYTES) {
			return ['status' => ApiClient::FAILED, 'message' => $this->l->t('The file is larger than 80 MB.')];
		}
		$workspace = $this->settings->getWorkspace($uid);
		if (!$this->workspaceUsable($uid, $token, $workspace, $check)) {
			return $check;
		}
		return $this->upload($token, $workspace, $this->settings->getTier($uid), $file);
	}

	private function workspaceUsable(string $uid, string $token, string $workspace, ?array &$result = null): bool {
		$result = ['status' => ApiClient::OK, 'message' => ''];
		$cached = $this->settings->getWorkspaceCheck($uid, $workspace);
		if ($cached === 'bad') {
			// Paused until the settings are saved again, without another paid call.
			$result = ['status' => ApiClient::WORKSPACE, 'message' => $this->l->t('Workspace %s is not available for this key.', [$workspace])];
			return false;
		}
		if ($cached === 'ok' || !ctype_digit($workspace)) {
			return true;
		}
		$result = $this->api->workspaceExists($token, $workspace);
		if ($result['status'] === ApiClient::OK) {
			$this->settings->setWorkspaceCheck($uid, $workspace, true);
			return true;
		}
		if ($result['status'] === ApiClient::WORKSPACE) {
			$this->settings->setWorkspaceCheck($uid, $workspace, false);
		}
		$this->logger->warning('PaperOffice AI inbox of {uid} paused: {msg}', ['uid' => $uid, 'msg' => $result['message'], 'app' => Application::APP_ID]);
		return false;
	}

	/** Handles one inbox file; returns true when the whole run has to stop. */
	private function processFile(string $uid, string $token, string $workspace, Folder $inbox, File $file): bool {
		$fileId = (int)$file->getId();
		$stamp = $file->getSize() . '|' . $file->getMTime();
		$marker = $this->settings->getSentMarker($uid, $fileId);
		if ($marker === 'done|' . $stamp) {
			if ($this->moveTo($inbox, $file, self::DONE_DIR, $uid) !== null) {
				$this->settings->clearSentMarker($uid, $fileId);
			}
			return false;
		}
		if ($marker === 'pending|' . $stamp) {
			// An earlier run stopped during the upload: the outcome is unknown.
			$this->fail($uid, $inbox, $file, $this->l->t('An earlier upload of this file did not finish. It may already be in PaperOffice AI. Check your workspace; to upload again, move the file back into the inbox folder.'));
			return false;
		}
		if ($file->getSize() > self::MAX_FILE_BYTES) {
			$this->fail($uid, $inbox, $file, $this->l->t('The file is larger than 80 MB.'));
			return false;
		}
		$this->settings->setSentMarker($uid, $fileId, 'pending', $stamp);
		$res = $this->upload($token, $workspace, $this->settings->getTier($uid), $file);
		switch ($res['status']) {
			case ApiClient::OK:
				$this->settings->setSentMarker($uid, $fileId, 'done', $stamp);
				if ($this->moveTo($inbox, $file, self::DONE_DIR, $uid) !== null) {
					$this->settings->clearSentMarker($uid, $fileId);
				}
				return false;
			case ApiClient::FAILED:
				$this->fail($uid, $inbox, $file, $res['message']);
				return false;
			case ApiClient::UNCLEAR:
				// No answer after sending: PaperOffice AI may be in trouble, stop this run.
				$this->fail($uid, $inbox, $file, $res['message']);
				return true;
			case ApiClient::WORKSPACE:
				// Paused until the settings are saved again (also for workspace names).
				$this->settings->clearSentMarker($uid, $fileId);
				$this->settings->setWorkspaceCheck($uid, $workspace, false);
				$this->logger->warning('PaperOffice AI inbox of {uid} paused: {msg}', ['uid' => $uid, 'msg' => $res['message'], 'app' => Application::APP_ID]);
				return true;
			case ApiClient::AUTH:
				$this->settings->clearSentMarker($uid, $fileId);
				$this->settings->clearWorkspaceCheck($uid);
				$this->logger->warning('PaperOffice AI inbox of {uid} paused: {msg}', ['uid' => $uid, 'msg' => $res['message'], 'app' => Application::APP_ID]);
				return true;
			case self::SKIPPED:
				$this->settings->clearSentMarker($uid, $fileId);
				return false;
			default:
				// TEMPORARY: connection failed, 408, 429 or 5xx.
				$this->settings->clearSentMarker($uid, $fileId);
				return true;
		}
	}

	/** Moves the file to _failed with a note and drops its marker. */
	private function fail(string $uid, Folder $inbox, File $file, string $note): void {
		$fileId = (int)$file->getId();
		$dest = $this->moveTo($inbox, $file, self::FAIL_DIR, $uid);
		if ($dest === null) {
			return;
		}
		$this->settings->clearSentMarker($uid, $fileId);
		try {
			$dest->getParent()->newFile($dest->getName() . '.error.txt', $note . "\n");
		} catch (\Exception $e) {
			$this->logger->warning('PaperOffice AI could not write the error note for {path}: {msg}', ['path' => $dest->getPath(), 'msg' => $e->getMessage(), 'app' => Application::APP_ID]);
		}
	}

	/** Drops markers of files that no longer exist. */
	private function cleanupMarkers(string $uid): void {
		$userFolder = $this->rootFolder->getUserFolder($uid);
		foreach ($this->settings->getMarkedFileIds($uid) as $fileId) {
			if ($userFolder->getFirstNodeById($fileId) === null) {
				$this->settings->clearSentMarker($uid, $fileId);
			}
		}
	}

	private function upload(string $token, string $workspace, string $tier, File $file): array {
		$stream = $file->fopen('r');
		if ($stream === false) {
			return ['status' => self::SKIPPED, 'message' => $this->l->t('Cannot read the file.')];
		}
		try {
			return $this->api->upload($token, $workspace, $tier, $file->getName(), $stream);
		} finally {
			if (is_resource($stream)) {
				fclose($stream);
			}
		}
	}

	private function inboxFolder(string $uid, string $path): ?Folder {
		$userFolder = $this->rootFolder->getUserFolder($uid);
		try {
			$node = $userFolder->get($path);
		} catch (NotFoundException $e) {
			$node = $userFolder->newFolder($path);
		}
		return $node instanceof Folder ? $node : null;
	}

	/**
	 * Null when the file may be sent; otherwise the reason. Files in a received
	 * share whose owner blocked downloads are never sent, and neither are shares
	 * that cannot be checked (for example federated shares).
	 */
	private function downloadBlocked(Node $node): ?string {
		try {
			$storage = $node->getStorage();
			if (!$storage->instanceOfStorage(ISharedStorage::class)) {
				return null;
			}
			/** @var ISharedStorage $storage */
			$attributes = $storage->getShare()->getAttributes();
			if ($attributes !== null && $attributes->getAttribute('permissions', 'download') === false) {
				return $this->l->t('The owner of this share does not allow downloads, so the file cannot be sent.');
			}
			return null;
		} catch (\Throwable $e) {
			return $this->l->t('This shared file cannot be checked, so it is not sent.');
		}
	}

	/** Received shares and external storage are never moved: the file would vanish for its owner. */
	private function isForeign(Node $node): bool {
		return $node->isShared() || $node->isMounted();
	}

	/** @return \Generator<File> */
	private function files(Folder $folder, bool $root = true): \Generator {
		$now = time();
		foreach ($folder->getDirectoryListing() as $node) {
			$name = $node->getName();
			if (str_starts_with($name, '.') || ($root && ($name === self::DONE_DIR || $name === self::FAIL_DIR))) {
				continue;
			}
			if ($this->isForeign($node)) {
				continue;
			}
			if ($node instanceof Folder) {
				yield from $this->files($node, false);
			} elseif ($node instanceof File && $node->isReadable() && $now - $node->getMTime() >= self::MIN_AGE) {
				yield $node;
			}
		}
	}

	/**
	 * Moves a file below $inbox/$dir, keeping subfolders and never overwriting.
	 * A file that cannot be moved is reported once per day and skipped.
	 */
	private function moveTo(Folder $inbox, Node $file, string $dir, string $uid): ?Node {
		$rel = ltrim(substr($file->getPath(), strlen($inbox->getPath())), '/');
		$target = $dir . '/' . $rel;
		$parentPath = dirname($target);
		try {
			$parent = $inbox->nodeExists($parentPath) ? $inbox->get($parentPath) : $inbox->newFolder($parentPath);
			if (!$parent instanceof Folder) {
				return null;
			}
			$name = $file->getName();
			if ($parent->nodeExists($name)) {
				$name = $parent->getNonExistingName($name);
			}
			return $file->move($parent->getPath() . '/' . $name);
		} catch (\Exception $e) {
			if ($this->settings->shouldReportStuck($uid, (int)$file->getId())) {
				$this->logger->warning('PaperOffice AI could not move {path}: {msg}', ['path' => $file->getPath(), 'msg' => $e->getMessage(), 'app' => Application::APP_ID]);
			}
			return null;
		}
	}
}
