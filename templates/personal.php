<?php
/** @var array $_ */
/** @var \OCP\IL10N $l */
$s = $_['settings'];
?>
<div id="paperoffice-ai-settings" class="section">
	<h2>PaperOffice AI</h2>
	<p class="settings-hint"><?php p($l->t('Send documents from Nextcloud to PaperOffice AI. Document processing (OCR, extraction, classification) runs on PaperOffice\'s own hardware in its own data center in Navarra, Spain (EU), without external AI providers.')); ?></p>

	<p>
		<label for="poai-token"><?php p($l->t('API key')); ?></label><br>
		<input type="password" id="poai-token" autocomplete="off" style="width: 32em; max-width: 100%;"
			placeholder="<?php p($s['hasToken'] ? $l->t('Saved – enter a new key to replace it') : 'po_ut_…'); ?>">
		<br><em><?php p($l->t('Create a User token (po_ut_…) in PaperOffice AI under Account → API. The key is stored encrypted and never shown again.')); ?></em>
	</p>
	<p>
		<label for="poai-workspace"><?php p($l->t('Workspace')); ?></label><br>
		<input type="text" id="poai-workspace" value="<?php p($s['workspace']); ?>" style="width: 20em;">
		<br><em><?php p($l->t('Enter the workspace ID or a name. A new name creates the workspace on the first upload.')); ?></em>
	</p>
	<p>
		<input type="checkbox" class="checkbox" id="poai-inbox-enabled" <?php if ($s['inboxEnabled']) { print_unescaped('checked'); } ?>>
		<label for="poai-inbox-enabled"><?php p($l->t('Upload new files from my inbox folder automatically')); ?></label>
	</p>
	<p>
		<label for="poai-inbox"><?php p($l->t('Inbox folder')); ?></label><br>
		<input type="text" id="poai-inbox" value="<?php p($s['inbox']); ?>" style="width: 20em;">
		<br><em><?php p($l->t('New files are uploaded about every 5 minutes and then moved to _uploaded in this folder. Files that cannot be uploaded go to _failed with a short note.')); ?></em>
	</p>
	<p>
		<label for="poai-tier"><?php p($l->t('AI analysis on upload')); ?></label><br>
		<select id="poai-tier">
			<option value="" <?php if ($s['tier'] === '') { print_unescaped('selected'); } ?>><?php p($l->t('Workspace default')); ?></option>
			<option value="basic" <?php if ($s['tier'] === 'basic') { print_unescaped('selected'); } ?>><?php p($l->t('AI-DMS Basic (paid)')); ?></option>
			<option value="premium" <?php if ($s['tier'] === 'premium') { print_unescaped('selected'); } ?>><?php p($l->t('AI-DMS Premium (paid)')); ?></option>
			<option value="ultra" <?php if ($s['tier'] === 'ultra') { print_unescaped('selected'); } ?>><?php p($l->t('AI-DMS Ultra (paid)')); ?></option>
		</select>
	</p>
	<p>
		<button id="poai-save" class="primary"><?php p($l->t('Save')); ?></button>
		<button id="poai-test"><?php p($l->t('Test connection')); ?></button>
		<button id="poai-clear" <?php if (!$s['hasToken']) { print_unescaped('disabled'); } ?>><?php p($l->t('Remove key')); ?></button>
		<span id="poai-status" role="status"></span>
	</p>
	<p class="settings-hint"><?php p($l->t('No specific plan is required. Usage is billed to your PaperOffice AI account as usual.')); ?></p>
</div>
