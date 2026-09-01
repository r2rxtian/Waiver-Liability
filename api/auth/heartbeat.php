<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/auth/session.php';
header('Content-Type: application/json');
if(!user()){http_response_code(401);echo json_encode(['authenticated'=>false]);exit;}
echo json_encode(['authenticated'=>true,'csrf'=>csrf_token()]);
