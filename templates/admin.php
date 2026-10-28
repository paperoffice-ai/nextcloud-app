<?php
/** @var array $_ */
/** @var \OCP\IL10N $l */
?>
<div id="paperoffice-ai-admin" class="section">
	<h2>PaperOffice AI</h2>
	<p>
		<label for="poai-groups"><?php p($l->t('Only these groups may connect PaperOffice AI')); ?></label><br>
		<input type="text" id="poai-groups" value="<?php p($_['groups']); ?>" placeholder="<?php p($l->t('All users')); ?>" style="width: 32em; max-width: 100%;">
		<br><em><?php p($l->t('Comma-separated group IDs. Leave empty to allow all users. Each user connects with their own PaperOffice AI key. Stored keys of users outside these groups are removed, also when a user leaves a group later.')); ?></em>
	</p>
	<p>
		<button id="poai-admin-save" class="primary"><?php p($l->t('Save')); ?></button>
		<span id="poai-admin-status" role="status"></span>
	</p>
</div>
