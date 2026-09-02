<?php
declare(strict_types=1);

require_once __DIR__.'/../auth/session.php';
require_once __DIR__.'/validation.php';
require_once __DIR__.'/../vendor/phpqrcode/autoload.php';

const SIGNING_STAFF_ROLES = ['SYSTEM ADMIN', 'CLINIC NURSE', 'HR / CLINIC ADMIN'];
const SIGNING_SUPERVISOR_ROLES = ['SYSTEM ADMIN', 'SUPERVISOR'];

function signing_schema_ready(): bool
{
    try {
        return (int)(one("SELECT CASE WHEN OBJECT_ID('dbo.acd_mw_signing_tokens','U') IS NULL THEN 0 ELSE 1 END ready")['ready'] ?? 0) === 1;
    } catch (Throwable $exception) {
        error_log('Signing schema check failed: '.$exception->getMessage());
        return false;
    }
}

function signing_require_schema(): void
{
    if (!signing_schema_ready()) {
        throw new RuntimeException('The signing workflow is not installed. Run sql/add_signing_workflow.sql first.');
    }
}

function signing_setting(string $key, ?string $default = null): ?string
{
    try {
        $row = one('SELECT TOP 1 setting_value FROM dbo.acd_mw_settings WHERE setting_key=? ORDER BY setting_id DESC', [$key]);
        $value = trim((string)($row['setting_value'] ?? ''));
        return $value !== '' ? $value : $default;
    } catch (Throwable $exception) {
        error_log('Signing setting lookup failed: '.$exception->getMessage());
        return $default;
    }
}

function signing_token_minutes(): int
{
    $minutes = (int)signing_setting('SIGNING_TOKEN_MINUTES', '10');
    return min(60, max(1, $minutes));
}

function signing_app_path(): string
{
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/Waiver-Liability/index.php'));
    foreach (['/pages/', '/api/'] as $marker) {
        $position = strpos($script, $marker);
        if ($position !== false) return substr($script, 0, $position);
    }
    $directory = rtrim(str_replace('\\', '/', dirname($script)), '/.');
    return $directory === '' ? '' : $directory;
}

function signing_private_ipv4(?string $address): bool
{
    if (!$address || !filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) return false;
    return preg_match('/^10\./', $address) === 1
        || preg_match('/^192\.168\./', $address) === 1
        || (preg_match('/^172\.(\d+)\./', $address, $match) === 1
            && (int)$match[1] >= 16 && (int)$match[1] <= 31);
}

function signing_detect_lan_address(): ?string
{
    $candidates = [$_SERVER['SERVER_ADDR'] ?? null];
    $hostname = gethostname();
    if ($hostname) $candidates[] = gethostbyname($hostname);
    foreach ($candidates as $candidate) {
        if (signing_private_ipv4(is_string($candidate) ? $candidate : null)) return $candidate;
    }
    return null;
}

function signing_suggested_lan_url(): ?string
{
    $address = signing_detect_lan_address();
    if (!$address) return null;
    $secure = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || (string)($_SERVER['SERVER_PORT'] ?? '') === '443';
    $port = (string)($_SERVER['SERVER_PORT'] ?? '80');
    $portPart = ($secure && $port === '443') || (!$secure && $port === '80') ? '' : ':'.$port;
    return ($secure ? 'https://' : 'http://').$address.$portPart.signing_app_path();
}

function signing_base_url(): string
{
    $configured = rtrim((string)signing_setting('APP_BASE_URL', ''), '/');
    if ($configured !== '') {
        $parts = parse_url($configured);
        if (!preg_match('~^https?://~i', $configured) || !$parts || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new RuntimeException('APP_BASE_URL must begin with http:// or https://.');
        }
        return signing_is_local_url($configured) ? (signing_suggested_lan_url() ?? $configured) : $configured;
    }

    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    if ($host === '' || !preg_match('/^[A-Za-z0-9.\-:\[\]]+$/', $host)) {
        throw new RuntimeException('Configure APP_BASE_URL before generating a signing QR.');
    }
    $secure = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || (string)($_SERVER['SERVER_PORT'] ?? '') === '443';
    $current = ($secure ? 'https://' : 'http://').$host.signing_app_path();
    return signing_is_local_url($current) ? (signing_suggested_lan_url() ?? $current) : $current;
}

function signing_url(string $rawToken): string
{
    return signing_base_url().'/pages/mobile-sign.php?token='.rawurlencode($rawToken);
}

function signing_qr_svg(string $url): string
{
    return \GlobusStudio\QRCode\QRCode::svg($url, [
        'size' => 6,
        'margin' => 12,
        'mask' => 0,
        'foreground' => '#173f2c',
        'background' => '#ffffff',
    ]);
}

function signing_is_local_url(string $url): bool
{
    $host = strtolower((string)(parse_url($url, PHP_URL_HOST) ?? ''));
    return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
}

function signing_audit(string $action, string $entityType, ?int $entityId, string $description, ?int $userId = null): void
{
    try {
        run(
            'INSERT INTO dbo.acd_mw_activity_logs(user_id,action,entity_type,entity_id,description,ip_address,created_at) VALUES(?,?,?,?,?,?,SYSDATETIME())',
            [$userId ?? (user()['user_id'] ?? null), $action, $entityType, $entityId, $description, $_SERVER['REMOTE_ADDR'] ?? 'CLI']
        );
    } catch (Throwable $exception) {
        error_log('Signing audit failed: '.$exception->getMessage());
    }
}

function signing_waiver(int $waiverId, bool $lock = false): ?array
{
    $hint = $lock ? ' WITH (UPDLOCK,HOLDLOCK)' : '';
    return one("SELECT w.*,e.employee_number,e.full_name employee_name,e.position,e.is_active employee_active,
        d.department_name,t.type_code,t.type_name,v.version_number,
        es.signature_id employee_signature_id,es.signer_user_id employee_signer_user_id,
        es.printed_name employee_printed,es.signature_data employee_signature,es.signed_at employee_signed_at,
        ss.signature_id supervisor_signature_id,ss.signer_user_id supervisor_signer_user_id,
        ss.printed_name supervisor_printed,ss.signature_data supervisor_signature,ss.signed_at supervisor_signed_at
        FROM dbo.acd_mw_waivers w$hint
        JOIN dbo.acd_mw_employees e ON e.employee_id=w.employee_id
        LEFT JOIN dbo.acd_mw_departments d ON d.department_id=e.department_id
        JOIN dbo.acd_mw_waiver_types t ON t.waiver_type_id=w.waiver_type_id
        JOIN dbo.acd_mw_waiver_versions v ON v.waiver_version_id=w.waiver_version_id
        OUTER APPLY(SELECT TOP 1 * FROM dbo.acd_mw_waiver_signatures WHERE waiver_id=w.waiver_id AND signer_type='EMPLOYEE' ORDER BY signature_id DESC) es
        OUTER APPLY(SELECT TOP 1 * FROM dbo.acd_mw_waiver_signatures WHERE waiver_id=w.waiver_id AND signer_type='SUPERVISOR' ORDER BY signature_id DESC) ss
        WHERE w.waiver_id=?", [$waiverId]);
}

function signing_expire_token_if_needed(array $token): array
{
    $expiredByServer = isset($token['is_expired']) ? (int)$token['is_expired'] === 1 : (int)($token['seconds_remaining'] ?? 1) <= 0;
    if ($token['status'] === 'ACTIVE' && $expiredByServer) {
        $affected = run("UPDATE dbo.acd_mw_signing_tokens SET status='EXPIRED' WHERE token_id=? AND status='ACTIVE' AND expires_at<=SYSDATETIME()", [(int)$token['token_id']])->rowCount();
        if ($affected > 0) {
            signing_audit('EXPIRE_SIGNING_TOKEN', 'SIGNING_TOKEN', (int)$token['token_id'], 'Employee signing token expired for waiver #'.(int)$token['waiver_id']);
            $token['status'] = 'EXPIRED';
        }
    }
    return $token;
}

function signing_token_by_raw(string $rawToken, bool $lock = false): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $rawToken)) return null;
    signing_require_schema();
    $hint = $lock ? ' WITH (UPDLOCK,HOLDLOCK)' : '';
    $token = one("SELECT st.*,CASE WHEN st.expires_at<=SYSDATETIME() THEN 1 ELSE 0 END is_expired,
        DATEDIFF_BIG(SECOND,SYSDATETIME(),st.expires_at) seconds_remaining,
        w.status waiver_status,w.finalized_at,e.full_name employee_name,e.employee_number,
        t.type_code,t.type_name,
        CASE WHEN es.signature_id IS NULL THEN 0 ELSE 1 END employee_has_signed
        FROM dbo.acd_mw_signing_tokens st$hint
        JOIN dbo.acd_mw_waivers w ON w.waiver_id=st.waiver_id
        JOIN dbo.acd_mw_employees e ON e.employee_id=st.employee_id AND e.employee_id=w.employee_id
        JOIN dbo.acd_mw_waiver_types t ON t.waiver_type_id=w.waiver_type_id
        OUTER APPLY(SELECT TOP 1 signature_id FROM dbo.acd_mw_waiver_signatures WHERE waiver_id=w.waiver_id AND signer_type='EMPLOYEE' ORDER BY signature_id DESC) es
        WHERE st.token_hash=?", [hash('sha256', $rawToken)]);
    return $token ? signing_expire_token_if_needed($token) : null;
}

function signing_latest_token(int $waiverId): ?array
{
    if (!signing_schema_ready()) return null;
    $token = one("SELECT TOP 1 *,CASE WHEN expires_at<=SYSDATETIME() THEN 1 ELSE 0 END is_expired,
        DATEDIFF_BIG(SECOND,SYSDATETIME(),expires_at) seconds_remaining
        FROM dbo.acd_mw_signing_tokens WHERE waiver_id=? ORDER BY token_id DESC", [$waiverId]);
    return $token ? signing_expire_token_if_needed($token) : null;
}

function signing_token_is_usable(array $token): bool
{
    return $token['status'] === 'ACTIVE'
        && (int)($token['is_expired'] ?? 0) === 0
        && (int)($token['seconds_remaining'] ?? 1) > 0
        && $token['waiver_status'] !== 'COMPLETED'
        && empty($token['employee_has_signed']);
}

function signing_create_request(int $waiverId, int $staffUserId): array
{
    signing_require_schema();
    $baseUrl = signing_base_url();
    $database = database();
    $database->beginTransaction();
    try {
        $waiver = signing_waiver($waiverId, true);
        if (!$waiver || !in_array($waiver['status'], ['DRAFT', 'AWAITING_EMPLOYEE_SIGNATURE'], true)) {
            throw new RuntimeException('This waiver is not eligible for employee signature.');
        }
        if (!empty($waiver['employee_signature_id']) || !empty($waiver['finalized_at'])) {
            throw new RuntimeException('The employee has already signed this waiver.');
        }

        $previousTokens = all("SELECT token_id FROM dbo.acd_mw_signing_tokens WITH (UPDLOCK,HOLDLOCK) WHERE waiver_id=? AND status='ACTIVE'", [$waiverId]);
        run("UPDATE dbo.acd_mw_signing_tokens SET status='CANCELLED',cancelled_at=SYSDATETIME() WHERE waiver_id=? AND status='ACTIVE'", [$waiverId]);
        $rawToken = bin2hex(random_bytes(32));
        $statement = run("INSERT INTO dbo.acd_mw_signing_tokens(waiver_id,employee_id,token_hash,status,expires_at,created_by,created_at)
            OUTPUT INSERTED.token_id,CONVERT(VARCHAR(27),INSERTED.expires_at,126) expires_at
            VALUES(?,?,?,'ACTIVE',DATEADD(MINUTE,CAST(? AS INT),SYSDATETIME()),?,SYSDATETIME())", [$waiverId, (int)$waiver['employee_id'], hash('sha256', $rawToken), signing_token_minutes(), $staffUserId]);
        $createdToken = $statement->fetch();
        if (!$createdToken) throw new RuntimeException('The signing token could not be created.');
        $tokenId = (int)$createdToken['token_id'];
        $expiresAt = (string)$createdToken['expires_at'];
        run("UPDATE dbo.acd_mw_waivers SET status='AWAITING_EMPLOYEE_SIGNATURE' WHERE waiver_id=? AND status IN ('DRAFT','AWAITING_EMPLOYEE_SIGNATURE')", [$waiverId]);
        run('INSERT INTO dbo.acd_mw_activity_logs(user_id,action,entity_type,entity_id,description,ip_address,created_at) VALUES(?,?,?,?,?,?,SYSDATETIME())', [$staffUserId, 'REQUEST_EMPLOYEE_SIGNATURE', 'WAIVER', $waiverId, 'Authorized staff requested the employee signature', $_SERVER['REMOTE_ADDR'] ?? '']);
        foreach ($previousTokens as $previousToken) {
            run('INSERT INTO dbo.acd_mw_activity_logs(user_id,action,entity_type,entity_id,description,ip_address,created_at) VALUES(?,?,?,?,?,?,SYSDATETIME())', [$staffUserId, 'CANCEL_SIGNING_TOKEN', 'SIGNING_TOKEN', (int)$previousToken['token_id'], 'Replaced prior signing token for waiver #'.$waiverId, $_SERVER['REMOTE_ADDR'] ?? '']);
        }
        run('INSERT INTO dbo.acd_mw_activity_logs(user_id,action,entity_type,entity_id,description,ip_address,created_at) VALUES(?,?,?,?,?,?,SYSDATETIME())', [$staffUserId, 'GENERATE_SIGNING_TOKEN', 'SIGNING_TOKEN', $tokenId, 'Generated one-time signing token for waiver #'.$waiverId, $_SERVER['REMOTE_ADDR'] ?? '']);
        $database->commit();
        return ['token_id'=>$tokenId, 'raw_token'=>$rawToken, 'expires_at'=>$expiresAt, 'url'=>$baseUrl.'/pages/mobile-sign.php?token='.rawurlencode($rawToken)];
    } catch (Throwable $exception) {
        if ($database->inTransaction()) $database->rollBack();
        throw $exception;
    }
}

function signing_cancel_request(int $waiverId, int $staffUserId): bool
{
    signing_require_schema();
    $database = database();
    $database->beginTransaction();
    try {
        $waiver = signing_waiver($waiverId, true);
        if (!$waiver || $waiver['status'] !== 'AWAITING_EMPLOYEE_SIGNATURE' || !empty($waiver['employee_signature_id'])) {
            throw new RuntimeException('There is no active employee signing request to cancel.');
        }
        $token = one("SELECT TOP 1 token_id FROM dbo.acd_mw_signing_tokens WITH (UPDLOCK,HOLDLOCK) WHERE waiver_id=? AND status='ACTIVE' ORDER BY token_id DESC", [$waiverId]);
        if (!$token) throw new RuntimeException('There is no active employee signing request to cancel.');
        run("UPDATE dbo.acd_mw_signing_tokens SET status='CANCELLED',cancelled_at=SYSDATETIME() WHERE token_id=? AND status='ACTIVE'", [(int)$token['token_id']]);
        run("UPDATE dbo.acd_mw_waivers SET status='DRAFT' WHERE waiver_id=? AND status='AWAITING_EMPLOYEE_SIGNATURE'", [$waiverId]);
        run('INSERT INTO dbo.acd_mw_activity_logs(user_id,action,entity_type,entity_id,description,ip_address,created_at) VALUES(?,?,?,?,?,?,SYSDATETIME())', [$staffUserId, 'CANCEL_SIGNING_TOKEN', 'SIGNING_TOKEN', (int)$token['token_id'], 'Cancelled employee signing request for waiver #'.$waiverId, $_SERVER['REMOTE_ADDR'] ?? '']);
        $database->commit();
        return true;
    } catch (Throwable $exception) {
        if ($database->inTransaction()) $database->rollBack();
        throw $exception;
    }
}

function signing_signature_valid(string $data): bool
{
    if (!str_starts_with($data, 'data:image/png;base64,') || strlen($data) < 600 || strlen($data) > 1_500_000) return false;
    $binary = base64_decode(substr($data, 22), true);
    if ($binary === false || strlen($binary) < 300 || !str_starts_with($binary, "\x89PNG\r\n\x1a\n")) return false;
    $dimensions = @getimagesizefromstring($binary);
    return is_array($dimensions)
        && ($dimensions[2] ?? null) === IMAGETYPE_PNG
        && ($dimensions[0] ?? 0) >= 250 && ($dimensions[0] ?? 0) <= 2000
        && ($dimensions[1] ?? 0) >= 100 && ($dimensions[1] ?? 0) <= 1000;
}

function signing_session_key(int $tokenId): string
{
    return 'token_'.$tokenId;
}

function signing_session_get(int $tokenId): array
{
    return $_SESSION['mobile_signing'][signing_session_key($tokenId)] ?? [];
}

function signing_session_set(int $tokenId, array $values): void
{
    $key = signing_session_key($tokenId);
    $_SESSION['mobile_signing'][$key] = array_merge($_SESSION['mobile_signing'][$key] ?? [], $values);
}

function signing_session_clear(int $tokenId): void
{
    unset($_SESSION['mobile_signing'][signing_session_key($tokenId)]);
}

function signing_finalize_supervisor(int $waiverId, int $supervisorUserId, string $printedName, string $signature): string
{
    if ($printedName === '' || !signing_signature_valid($signature)) {
        throw new RuntimeException('A valid supervisor signature and printed name are required.');
    }

    $database = database();
    $database->beginTransaction();
    try {
        $waiver = signing_waiver($waiverId, true);
        if (!$waiver) throw new RuntimeException('Waiver not found.');
        if ($waiver['status'] !== 'AWAITING_SUPERVISOR' || !empty($waiver['finalized_at'])) {
            throw new RuntimeException('This waiver is not awaiting a supervisor signature.');
        }
        if (empty($waiver['employee_signature_id'])) throw new RuntimeException('The employee must sign before the supervisor.');
        if (!empty($waiver['supervisor_signature_id'])) throw new RuntimeException('The supervisor signature has already been recorded.');

        $year = date('Y');
        $sequence = one("SELECT COALESCE(MAX(TRY_CONVERT(INT,RIGHT(waiver_number,6))),0)+1 next_number
            FROM dbo.acd_mw_waivers WITH (UPDLOCK,HOLDLOCK) WHERE waiver_number LIKE ?", ['MW-'.$year.'-%']);
        $waiverNumber = $waiver['waiver_number'] ?: sprintf('MW-%s-%06d', $year, (int)($sequence['next_number'] ?? 1));

        $signatureStatement = run("INSERT INTO dbo.acd_mw_waiver_signatures(waiver_id,signer_type,signer_user_id,printed_name,signature_data,ip_address,user_agent,signed_at)
            OUTPUT INSERTED.signature_id,CONVERT(VARCHAR(27),INSERTED.signed_at,126) signed_at
            VALUES(?,'SUPERVISOR',?,?,?,?,?,SYSDATETIME())", [$waiverId, $supervisorUserId, $printedName, $signature, $_SERVER['REMOTE_ADDR'] ?? '', mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500)]);
        $supervisorSignature = $signatureStatement->fetch();
        if (!$supervisorSignature) throw new RuntimeException('The supervisor signature could not be recorded.');

        $acknowledgments = all('SELECT acknowledgment_text,is_acknowledged,acknowledged_at FROM dbo.acd_mw_waiver_acknowledgments WHERE waiver_id=? ORDER BY display_order', [$waiverId]);
        if (count($acknowledgments) !== count(acknowledgments((string)$waiver['type_code']))) {
            throw new RuntimeException('The employee acknowledgments are incomplete.');
        }
        $finalized = one("SELECT CONVERT(VARCHAR(27),SYSDATETIME(),126) finalized_at");
        $finalizedAt = (string)($finalized['finalized_at'] ?? date('Y-m-d\TH:i:s'));
        $documentPayload = [
            'waiver_id'=>$waiverId,
            'waiver_number'=>$waiverNumber,
            'employee_id'=>(int)$waiver['employee_id'],
            'waiver_type_id'=>(int)$waiver['waiver_type_id'],
            'waiver_version_id'=>(int)$waiver['waiver_version_id'],
            'waiver_date'=>(string)$waiver['waiver_date'],
            'waiver_time'=>(string)$waiver['waiver_time'],
            'medical_personnel_name'=>(string)$waiver['medical_personnel_name'],
            'recommendation'=>(string)$waiver['recommendation'],
            'transportation_offered'=>(string)$waiver['transportation_offered'],
            'remarks'=>(string)$waiver['remarks'],
            'signed_waiver_text'=>(string)$waiver['signed_waiver_text'],
            'acknowledgments'=>$acknowledgments,
            'employee_signature'=>[
                'signature_id'=>(int)$waiver['employee_signature_id'],
                'printed_name'=>(string)$waiver['employee_printed'],
                'signed_at'=>(string)$waiver['employee_signed_at'],
                'sha256'=>hash('sha256', (string)$waiver['employee_signature']),
            ],
            'supervisor_signature'=>[
                'signature_id'=>(int)$supervisorSignature['signature_id'],
                'printed_name'=>$printedName,
                'signed_at'=>(string)$supervisorSignature['signed_at'],
                'sha256'=>hash('sha256', $signature),
            ],
            'finalized_at'=>$finalizedAt,
        ];
        $documentHash = hash('sha256', json_encode($documentPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        if (run("UPDATE dbo.acd_mw_waivers SET waiver_number=?,status='COMPLETED',finalized_at=?,document_hash=?
            WHERE waiver_id=? AND status='AWAITING_SUPERVISOR' AND finalized_at IS NULL", [$waiverNumber, $finalizedAt, $documentHash, $waiverId])->rowCount() !== 1) {
            throw new RuntimeException('The waiver changed before finalization could complete.');
        }
        run('INSERT INTO dbo.acd_mw_activity_logs(user_id,action,entity_type,entity_id,description,ip_address,created_at) VALUES(?,?,?,?,?,?,SYSDATETIME())', [$supervisorUserId, 'SUPERVISOR_SIGNED', 'WAIVER', $waiverId, 'Supervisor signature recorded', $_SERVER['REMOTE_ADDR'] ?? '']);
        run('INSERT INTO dbo.acd_mw_activity_logs(user_id,action,entity_type,entity_id,description,ip_address,created_at) VALUES(?,?,?,?,?,?,SYSDATETIME())', [$supervisorUserId, 'FINALIZE_WAIVER', 'WAIVER', $waiverId, 'Finalized '.$waiverNumber, $_SERVER['REMOTE_ADDR'] ?? '']);
        $database->commit();
        return $waiverNumber;
    } catch (Throwable $exception) {
        if ($database->inTransaction()) $database->rollBack();
        throw $exception;
    }
}
