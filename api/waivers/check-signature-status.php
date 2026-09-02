<?php
declare(strict_types=1);

require_once __DIR__.'/../../rules/signing.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

function signing_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}

try {
    if (!user()) signing_json(['error'=>'Your staff session has expired.'], 401);
    if (!in_array(user()['role_name'], array_merge(SIGNING_STAFF_ROLES, SIGNING_SUPERVISOR_ROLES), true)) {
        signing_json(['error'=>'Access denied.'], 403);
    }

    $waiverId = filter_input(INPUT_GET, 'waiver_id', FILTER_VALIDATE_INT);
    if (!$waiverId) signing_json(['error'=>'Invalid waiver.'], 422);
    $waiver = signing_waiver((int)$waiverId);
    if (!$waiver) signing_json(['error'=>'Waiver not found.'], 404);

    $token = signing_latest_token((int)$waiverId);
    $verification = null;
    if (!empty($waiver['employee_signature_id'])) {
        $verification = one("SELECT TOP 1 verification_method,verified_at FROM dbo.acd_mw_signing_tokens WHERE waiver_id=? AND status='USED' ORDER BY used_at DESC,token_id DESC", [(int)$waiverId]);
        unset($_SESSION['signing_request_tokens'][(int)$waiverId]);
    }

    signing_json([
        'signed'=>!empty($waiver['employee_signature_id']),
        'status'=>$waiver['status'],
        'printed_name'=>$waiver['employee_printed'] ?? null,
        'signature_data'=>$waiver['employee_signature'] ?? null,
        'signed_at'=>$waiver['employee_signed_at'] ?? null,
        'verification_method'=>$verification['verification_method'] ?? null,
        'verified_at'=>$verification['verified_at'] ?? null,
        'request_status'=>$token['status'] ?? 'NOT_REQUESTED',
        'expires_at'=>$token['expires_at'] ?? null,
    ]);
} catch (Throwable $exception) {
    error_log('Signature polling failed: '.$exception->getMessage());
    signing_json(['error'=>'The signature status is temporarily unavailable.'], 500);
}
