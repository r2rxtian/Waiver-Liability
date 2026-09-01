<?php
declare(strict_types=1);
// Login POST handling is dispatched by pages/index.php to preserve one route.
$_GET['page']='login';
require dirname(__DIR__).'/pages/index.php';
