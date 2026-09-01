<?php
declare(strict_types=1);
require_once __DIR__.'/../conn/db.php';
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$statement=database()->query("SELECT name FROM sys.tables WHERE schema_id=SCHEMA_ID('dbo') AND name LIKE 'acd_mw_%' ORDER BY name");
$tables=$statement->fetchAll(PDO::FETCH_COLUMN);
echo "Connected to LRNPH_OJT.\n";
echo $tables ? "Existing project tables:\n - ".implode("\n - ",$tables)."\n" : "No acd_mw_ project tables exist yet.\n";
