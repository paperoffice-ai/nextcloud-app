# Changelog

All notable changes to this app are documented in this file.

## 1.0.5 – 2026-10-28

### Changed
- Users outside the allowed groups can still remove their stored key.
- Stored keys of users who leave the allowed groups (or whose group is deleted) are removed automatically.

## 1.0.4 – 2026-10-28

### Changed
- Store listing: source repository, issue tracker and screenshots.
- Admin setting: only these groups may connect PaperOffice AI (default: all users).
- A gateway error (HTTP 502/504) during an upload is not retried automatically; the file goes to `_failed` with a note.

## 1.0.3 – 2026-10-28

### Changed
- A run stops only when the key, the workspace or the connection to PaperOffice AI fails; a single unreadable or unmovable file no longer blocks the inbox.
- A workspace rejected during upload pauses the inbox until the settings are saved again.
- Shared files that cannot be checked are not sent.

## 1.0.2 – 2026-10-28

### Changed
- Wording of the store description and the settings page about billing.
- Files in shares with blocked download are not sent.
- An unavailable workspace pauses the inbox until the settings are saved again.
- An upload is retried automatically only when the connection could not be opened; a run stops at the first temporary error.

## 1.0.1 – 2026-10-28

### Changed
- The workspace check runs only when there are files to upload and is reused for 24 hours.
- The inbox must be a sub folder of the user's own files; received shares and external storage are skipped.
- Files larger than 80 MB and uploads without an answer go to `_failed` with a note instead of being retried.
- Error messages are translatable; the key check is rate limited.

## 1.0.0 – 2026-10-28

### Added
- Personal settings: PaperOffice AI API key (stored encrypted), workspace, inbox folder, optional AI analysis on upload.
- Inbox folder: new files are uploaded every 5 minutes and moved to `_uploaded`; failed files go to `_failed` with a note.
- File action "Send to PaperOffice AI" in the Files app.
