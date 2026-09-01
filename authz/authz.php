<?php
declare(strict_types=1);
require_once __DIR__.'/../auth/session.php';
require_once __DIR__.'/capabilities.php';

function require_capability(string $capability): void
{
    require_login();
    if(!can((string)user()['role_name'],$capability)){http_response_code(403);exit('Access denied.');}
}
