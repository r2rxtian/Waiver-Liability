<?php
declare(strict_types=1);

function employee_signing_panel(array $waiver): void
{
    $waiverId = (int)$waiver['waiver_id'];
    if (!signing_schema_ready()) {
        ?><section class="card employee-signing-panel"><div class="signing-panel-heading"><div><p class="signing-kicker">Employee signature</p><h2>Signing setup required</h2><p>Run <b>sql/add_signing_workflow.sql</b> against LRNPH_OJT before requesting a mobile signature.</p></div><span class="request-status expired">NOT INSTALLED</span></div></section><?php
        return;
    }
    if (!empty($waiver['employee_signature_id'])) {
        unset($_SESSION['signing_request_tokens'][$waiverId]);
        $verification = one("SELECT TOP 1 verification_method,verified_at FROM dbo.acd_mw_signing_tokens WHERE waiver_id=? AND status='USED' ORDER BY used_at DESC,token_id DESC", [$waiverId]);
        ?>
        <section class="card employee-signing-panel signed-state" data-signing-state="signed">
            <div class="signed-icon">&#10003;</div>
            <div class="signed-copy">
                <p class="signing-kicker">Employee signature</p>
                <h2>Employee signed</h2>
                <p><?=e($waiver['employee_printed'])?></p>
                <img src="<?=e($waiver['employee_signature'])?>" alt="Employee signature">
                <dl>
                    <div><dt>Signed</dt><dd><?=e(display_datetime($waiver['employee_signed_at']))?></dd></div>
                    <div><dt>Verification</dt><dd><?=e(($verification['verification_method'] ?? 'COMPANY_CREDENTIALS') === 'COMPANY_CREDENTIALS' ? 'Company credentials' : str_replace('_', ' ', (string)$verification['verification_method']))?></dd></div>
                </dl>
                <div class="next-step"><b>Next step</b><span>Supervisor acknowledgment required</span>
                    <?php if (in_array(user()['role_name'], SIGNING_SUPERVISOR_ROLES, true)): ?>
                        <a class="btn" href="index.php?page=approval-sign&amp;id=<?=$waiverId?>">Review for Supervisor</a>
                    <?php else: ?>
                        <a class="btn secondary" href="index.php?page=waiver-view&amp;id=<?=$waiverId?>">View signed waiver</a>
                    <?php endif ?>
                </div>
            </div>
        </section>
        <?php
        return;
    }

    $token = signing_latest_token($waiverId);
    $request = $_SESSION['signing_request_tokens'][$waiverId] ?? null;
    $hasRaw = $token && $request
        && (int)($request['token_id'] ?? 0) === (int)$token['token_id']
        && hash_equals((string)$token['token_hash'], hash('sha256', (string)($request['raw_token'] ?? '')));
    $active = $token && $token['status'] === 'ACTIVE' && (int)($token['seconds_remaining'] ?? 0) > 0;
    ?>
    <section class="card employee-signing-panel" data-signing-state="<?=e($active && $hasRaw ? 'waiting' : 'idle')?>"<?php if ($active && $hasRaw): ?> data-signing-waiting data-waiver-id="<?=$waiverId?>" data-seconds-remaining="<?=e(max(0,(int)$token['seconds_remaining']))?>" data-can-supervise="<?=in_array(user()['role_name'], SIGNING_SUPERVISOR_ROLES, true) ? '1' : '0'?>"<?php endif ?>>
        <div class="signing-panel-heading">
            <div><p class="signing-kicker">Employee signature</p><h2><?=($active && $hasRaw) ? 'Waiting for employee' : 'Not requested'?></h2><p><?=($active && $hasRaw) ? 'Scan this QR code using the employee\'s phone to review and sign.' : 'Create a secure, one-time mobile signing session when the employee is ready.'?></p></div>
            <span class="request-status <?=e(strtolower((string)($token['status'] ?? 'not-requested')))?>"><?=e(str_replace('_', ' ', (string)($token['status'] ?? 'NOT REQUESTED')))?></span>
        </div>
        <?php if ($active && $hasRaw): ?>
            <div class="qr-waiting-grid">
                <div class="qr-frame"><?=signing_qr_svg((string)$request['url'])?></div>
                <div class="waiting-details">
                    <b><?=e($waiver['employee_name'])?></b><span><?=e($waiver['employee_number'])?></span>
                    <div class="waiting-pulse"><i></i>Waiting for employee signature...</div>
                    <p>Expires in <strong data-signing-countdown>--:--</strong></p>
                    <div class="signing-link-row"><input type="text" readonly value="<?=e($request['url'])?>" data-signing-link aria-label="Secure signing link"><button type="button" data-copy-signing-link>Copy link</button></div>
                    <?php if (signing_is_local_url((string)$request['url'])): ?><div class="network-warning">No private LAN address could be detected. Set <b>APP_BASE_URL</b> in Settings, then generate a new QR.</div><?php endif ?>
                    <form method="post"><?=csrf_field()?><input type="hidden" name="action" value="cancel_signing_request"><input type="hidden" name="waiver_id" value="<?=$waiverId?>"><button class="btn secondary" data-confirm="Cancel this signing request? The QR will stop working immediately.">Cancel Signing Request</button></form>
                </div>
            </div>
        <?php else: ?>
            <?php if ($token && $token['status'] === 'EXPIRED'): ?><div class="signing-state-message"><b>Signing request expired.</b><span>The previous QR cannot be reused.</span></div><?php elseif ($active && !$hasRaw): ?><div class="signing-state-message"><b>An active request was created in another browser session.</b><span>Generate a new QR to replace it securely.</span></div><?php endif ?>
            <form method="post" class="request-signature-form"><?=csrf_field()?><input type="hidden" name="action" value="request_employee_signature"><input type="hidden" name="waiver_id" value="<?=$waiverId?>"><button class="btn"><?=$token ? 'Generate New QR' : 'Request Employee Signature'?></button></form>
        <?php endif ?>
    </section>
    <?php
}
