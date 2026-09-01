<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../conn/db.php';

const APP_NAME = 'Waiver of Liability';
const ROLES = ['SYSTEM ADMIN', 'CLINIC NURSE', 'SUPERVISOR', 'HR / CLINIC ADMIN', 'EMPLOYEE'];

function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function csrf_token(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="'.e(csrf_token()).'">'; }
function verify_csrf(): void {
    if (!isset($_POST['csrf']) || !hash_equals(csrf_token(), (string)$_POST['csrf'])) {
        http_response_code(419); exit('Your session expired. Go back and try again.');
    }
}
function redirect(string $url): never { header('Location: '.$url); exit; }
function flash(string $type, string $message): void { $_SESSION['flash'] = [$type, $message]; }
function take_flash(): ?array { $f=$_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f; }
function user(): ?array { return $_SESSION['user'] ?? null; }
function require_login(): void { if (!user()) redirect('index.php?page=login'); }
function require_role(array $roles): void {
    require_login();
    if (!in_array(user()['role_name'], $roles, true)) { http_response_code(403); exit('Access denied.'); }
}
function one(string $sql, array $args=[]): ?array { $s=database()->prepare($sql); $s->execute($args); return $s->fetch() ?: null; }
function all(string $sql, array $args=[]): array { $s=database()->prepare($sql); $s->execute($args); return $s->fetchAll(); }
function run(string $sql, array $args=[]): PDOStatement { $s=database()->prepare($sql); $s->execute($args); return $s; }
function exists(string $table, string $column, int $id): bool {
    $allowed=['acd_mw_employees'=>'employee_id','acd_mw_users'=>'user_id','acd_mw_waivers'=>'waiver_id','acd_mw_waiver_versions'=>'waiver_version_id'];
    return isset($allowed[$table]) && $allowed[$table] === $column && one("SELECT $column FROM dbo.$table WHERE $column = ?", [$id]) !== null;
}
function audit(string $action, string $type, ?int $id, string $description): void {
    try { run('INSERT INTO dbo.acd_mw_activity_logs (user_id,action,entity_type,entity_id,description,ip_address,created_at) VALUES (?,?,?,?,?,?,SYSDATETIME())', [user()['user_id'] ?? null,$action,$type,$id,$description,$_SERVER['REMOTE_ADDR'] ?? 'CLI']); } catch(Throwable $e) { error_log($e->getMessage()); }
}
function next_number(string $prefix, string $table, string $column): string {
    $year=date('Y'); $row=one("SELECT COUNT(*) AS n FROM dbo.$table WHERE $column LIKE ?", ["$prefix-$year-%"]);
    return sprintf('%s-%s-%06d',$prefix,$year,((int)$row['n'])+1);
}
function post(string $key, string $default=''): string { return trim((string)($_POST[$key] ?? $default)); }
function signature_valid(string $data): bool { return str_starts_with($data,'data:image/png;base64,') && strlen($data)>500; }

set_exception_handler(function(Throwable $e): void {
    error_log($e->__toString()); http_response_code(500);
    echo '<!doctype html><meta charset="utf-8"><title>System error</title><style>body{font:16px system-ui;padding:3rem;color:#24352c}</style><h1>We could not complete that request.</h1><p>The technical details were logged. Please contact the administrator.</p>';
});
