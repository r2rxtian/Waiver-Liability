<?php
declare(strict_types=1);
require_once __DIR__.'/../auth/session.php';
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
[$script,$username,$password,$fullName,$roleName]=$argv+array_fill(0,5,'');
if(!$username||strlen($password)<12||!in_array($roleName,ROLES,true)){fwrite(STDERR,"Usage: php scripts/create-user.php username password(12+) \"Full Name\" \"SYSTEM ADMIN\"\n");exit(1);}
$role=one('SELECT role_id FROM dbo.acd_mw_roles WHERE role_name=?',[$roleName]);if(!$role){fwrite(STDERR,"Run sql/schema.sql and sql/seed.sql first.\n");exit(1);}if(one('SELECT user_id FROM dbo.acd_mw_users WHERE username=?',[$username])){fwrite(STDERR,"Username already exists.\n");exit(1);}
run('INSERT INTO dbo.acd_mw_users(username,password_hash,full_name,role_id,is_active,created_at) VALUES(?,?,?,?,1,SYSDATETIME())',[$username,password_hash($password,PASSWORD_DEFAULT),$fullName,$role['role_id']]);fwrite(STDOUT,"User created.\n");
