<?php

declare(strict_types=1);

namespace OCA\PaperOfficeAI\Service;

/** The stored API key exists but cannot be decrypted (instance secret changed). */
class KeyUnreadableException extends \RuntimeException {
}
