<?php
declare(strict_types=1);
require_once __DIR__.'/../auth/session.php';
require_role(['SYSTEM ADMIN']);
$mode=$_GET['mode']??'';
if($mode!=='activity'){http_response_code(404);exit('Export not found.');}

function csv_date(string $key,array &$where,array &$args,string $column):void{$value=(string)($_GET[$key]??'');if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$value)){$where[]="$column ".($key==='from'?'>=':'<=').' ?';$args[]=$value;}}

$where=[];$args=[];
if(trim((string)($_GET['action']??''))!==''){$where[]='a.action=?';$args[]=trim((string)$_GET['action']);}
if((int)($_GET['user_id']??0)>0){$where[]='a.user_id=?';$args[]=(int)$_GET['user_id'];}
csv_date('from',$where,$args,'CAST(a.created_at AS date)');csv_date('to',$where,$args,'CAST(a.created_at AS date)');
$q=trim((string)($_GET['q']??''));if($q!==''){$where[]='(a.description LIKE ? OR a.entity_type LIKE ? OR CAST(a.entity_id AS VARCHAR(30)) LIKE ?)';$like="%$q%";array_push($args,$like,$like,$like);}
$rows=all('SELECT CONVERT(varchar(19),a.created_at,120) created_at,u.username,u.full_name,a.action,a.entity_type,a.entity_id,a.description FROM dbo.acd_mw_activity_logs a LEFT JOIN dbo.acd_mw_users u ON u.user_id=a.user_id'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY a.created_at DESC',$args);
$headers=['Date / Time','Username','User','Action','Entity Type','Entity ID','Description'];$filename='waiver-activity-logs.csv';audit('EXPORT_ACTIVITY_LOG','ACTIVITY_LOG',null,'Filtered activity log exported');
header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="'.$filename.'"');$out=fopen('php://output','wb');fwrite($out,"\xEF\xBB\xBF");fputcsv($out,$headers);foreach($rows as $row)fputcsv($out,array_values($row));fclose($out);
