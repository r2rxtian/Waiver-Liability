<?php
declare(strict_types=1);
require_once __DIR__.'/../conn/db.php';
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}

$pdo=database();
echo "PROJECT TABLES AND ROW COUNTS\n";
$tables=$pdo->query("SELECT t.name,SUM(CASE WHEN p.index_id IN(0,1) THEN p.rows ELSE 0 END) row_count FROM sys.tables t LEFT JOIN sys.partitions p ON p.object_id=t.object_id WHERE t.schema_id=SCHEMA_ID('dbo') AND t.name LIKE 'acd_mw_%' GROUP BY t.name ORDER BY t.name")->fetchAll();
foreach($tables as $table)echo $table['name'].'|'.$table['row_count'].PHP_EOL;

echo "\nPROJECT COLUMNS\n";
$columns=$pdo->query("SELECT t.name table_name,c.column_id,c.name column_name,TYPE_NAME(c.user_type_id) data_type,c.max_length,c.is_nullable FROM sys.tables t JOIN sys.columns c ON c.object_id=t.object_id WHERE t.schema_id=SCHEMA_ID('dbo') AND t.name LIKE 'acd_mw_%' ORDER BY t.name,c.column_id")->fetchAll();
foreach($columns as $column)echo implode('|',$column).PHP_EOL;

echo "\nPROJECT FOREIGN KEYS (MUST BE EMPTY)\n";
$foreignKeys=$pdo->query("SELECT fk.name,t.name table_name FROM sys.foreign_keys fk JOIN sys.tables t ON t.object_id=fk.parent_object_id WHERE t.schema_id=SCHEMA_ID('dbo') AND t.name LIKE 'acd_mw_%' ORDER BY t.name,fk.name")->fetchAll();
if(!$foreignKeys)echo "NONE\n";
foreach($foreignKeys as $foreignKey)echo implode('|',$foreignKey).PHP_EOL;

echo "\nSIGNING USERS\n";
$users=$pdo->query("SELECT u.user_id,u.username,u.employee_id,r.role_name,e.employee_number FROM dbo.acd_mw_users u JOIN dbo.acd_mw_roles r ON r.role_id=u.role_id LEFT JOIN dbo.acd_mw_employees e ON e.employee_id=u.employee_id ORDER BY u.user_id")->fetchAll();
foreach($users as $row)echo implode('|',array_map(static fn($value)=>$value===null?'NULL':(string)$value,$row)).PHP_EOL;

