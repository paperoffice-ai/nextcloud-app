<?php

declare(strict_types=1);

return [
	'routes' => [
		['name' => 'settings#get', 'url' => '/settings', 'verb' => 'GET'],
		['name' => 'settings#save', 'url' => '/settings', 'verb' => 'POST'],
		['name' => 'settings#test', 'url' => '/settings/test', 'verb' => 'POST'],
		['name' => 'send#send', 'url' => '/send', 'verb' => 'POST'],
		['name' => 'admin#save', 'url' => '/admin', 'verb' => 'POST'],
	],
];
