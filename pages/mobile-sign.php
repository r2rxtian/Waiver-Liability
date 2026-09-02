<?php
declare(strict_types=1);

require_once __DIR__.'/../rules/signing.php';

header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

$rawToken = strtolower(trim((string)($_GET['token'] ?? '')));
$step = (string)($_GET['step'] ?? 'verify');
$allowedSteps = ['verify','review','sign','confirm','success'];
if (!in_array($step, $allowedSteps, true)) $step = 'verify';
$error = '';
$token = null;
$waiver = null;

try {
    $token = signing_token_by_raw($rawToken);
    if ($token) $waiver = signing_waiver((int)$token['waiver_id']);
} catch (Throwable $exception) {
    error_log('Mobile signing token lookup failed: '.$exception->getMessage());
}

function mobile_redirect(string $rawToken, string $step): never
{
    redirect('mobile-sign.php?token='.rawurlencode($rawToken).'&step='.$step);
}

function mobile_session_valid(array $token): bool
{
    $session = signing_session_get((int)$token['token_id']);
    return !empty($session['verified'])
        && (int)($session['token_id'] ?? 0) === (int)$token['token_id']
        && (int)($session['waiver_id'] ?? 0) === (int)$token['waiver_id']
        && (int)($session['employee_id'] ?? 0) === (int)$token['employee_id'];
}

function mobile_acks_valid(array $waiver, array $session): bool
{
    $required = acknowledgments((string)$waiver['type_code']);
    $given = array_values(array_unique(array_map('strval', $session['acknowledgments'] ?? [])));
    return count($required) === count($given) && !array_diff($required, $given) && !array_diff($given, $required);
}

$completedThisSession = $token && !empty(signing_session_get((int)$token['token_id'])['completed']);
$invalid = (!$token || !$waiver || !signing_token_is_usable($token)) && !$completedThisSession;
$alreadySigned = $token && ($token['status'] === 'USED' || !empty($token['employee_has_signed']));

if ($token && !$invalid) {
    $openedKey = 'opened_'.(int)$token['token_id'];
    if (empty($_SESSION['mobile_signing_events'][$openedKey])) {
        signing_audit('EMPLOYEE_SIGNING_OPENED', 'WAIVER', (int)$token['waiver_id'], 'Employee signing page opened');
        $_SESSION['mobile_signing_events'][$openedKey] = true;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $action = post('action');

        if ($action === 'verify_identity') {
            $biometrics = post('biometrics_number');
            $password = (string)($_POST['password'] ?? '');
            $accounts = all("SELECT u.user_id,u.password_hash,u.employee_id,e.employee_number,e.full_name
                FROM dbo.acd_mw_users u JOIN dbo.acd_mw_employees e ON e.employee_id=u.employee_id
                WHERE e.employee_number=? AND e.is_active=1 AND u.is_active=1", [$biometrics]);
            $matched = null;
            foreach ($accounts as $account) {
                if (password_verify($password, (string)$account['password_hash'])) { $matched = $account; break; }
            }
            if (!$matched) {
                $error = 'The biometrics number or password is incorrect.';
            } elseif ((int)$matched['employee_id'] !== (int)$token['employee_id']) {
                $error = 'This waiver is assigned to another employee.';
            } else {
                session_regenerate_id(true);
                signing_session_set((int)$token['token_id'], [
                    'verified'=>true,
                    'token_id'=>(int)$token['token_id'],
                    'waiver_id'=>(int)$token['waiver_id'],
                    'employee_id'=>(int)$token['employee_id'],
                    'verification_user_id'=>(int)$matched['user_id'],
                    'verified_at'=>date('Y-m-d H:i:s'),
                ]);
                run("UPDATE dbo.acd_mw_signing_tokens SET verification_user_id=?,verification_method='COMPANY_CREDENTIALS',verified_at=SYSDATETIME()
                    WHERE token_id=? AND status='ACTIVE' AND expires_at>SYSDATETIME()", [(int)$matched['user_id'], (int)$token['token_id']]);
                signing_audit('EMPLOYEE_IDENTITY_VERIFIED', 'WAIVER', (int)$token['waiver_id'], 'Assigned employee identity verified using company credentials', (int)$matched['user_id']);
                mobile_redirect($rawToken, 'review');
            }
        } elseif ($action === 'accept_acknowledgments') {
            if (!mobile_session_valid($token)) mobile_redirect($rawToken, 'verify');
            $required = acknowledgments((string)$waiver['type_code']);
            $given = array_values(array_unique(array_map('strval', (array)($_POST['ack'] ?? []))));
            if (count($required) !== count($given) || array_diff($required, $given) || array_diff($given, $required)) {
                $error = 'Please check every acknowledgment before continuing.';
                $step = 'review';
            } else {
                signing_session_set((int)$token['token_id'], ['acknowledgments'=>$given]);
                mobile_redirect($rawToken, 'sign');
            }
        } elseif ($action === 'prepare_signature') {
            $session = signing_session_get((int)$token['token_id']);
            if (!mobile_session_valid($token)) mobile_redirect($rawToken, 'verify');
            if (!mobile_acks_valid($waiver, $session)) mobile_redirect($rawToken, 'review');
            $printedName = post('printed_name');
            $signature = (string)($_POST['signature'] ?? '');
            if (mb_strtolower($printedName) !== mb_strtolower(trim((string)$waiver['employee_name']))) {
                $error = 'Confirm the assigned employee name exactly as shown.';
                $step = 'sign';
            } elseif (!signing_signature_valid($signature)) {
                $error = 'Please draw a valid signature in the signature box.';
                $step = 'sign';
            } else {
                signing_session_set((int)$token['token_id'], ['printed_name'=>$printedName, 'signature'=>$signature]);
                mobile_redirect($rawToken, 'confirm');
            }
        } elseif ($action === 'confirm_signature') {
            $session = signing_session_get((int)$token['token_id']);
            if (!mobile_session_valid($token)) mobile_redirect($rawToken, 'verify');
            if (!mobile_acks_valid($waiver, $session)) mobile_redirect($rawToken, 'review');
            if (!signing_signature_valid((string)($session['signature'] ?? ''))) mobile_redirect($rawToken, 'sign');

            $database = database();
            $database->beginTransaction();
            try {
                $lockedToken = signing_token_by_raw($rawToken, true);
                $lockedWaiver = $lockedToken ? signing_waiver((int)$lockedToken['waiver_id'], true) : null;
                if (!$lockedToken || !signing_token_is_usable($lockedToken)) throw new RuntimeException('This signing link is no longer valid.');
                if (!$lockedWaiver || $lockedWaiver['status'] !== 'AWAITING_EMPLOYEE_SIGNATURE' || !empty($lockedWaiver['employee_signature_id']) || !empty($lockedWaiver['finalized_at'])) {
                    throw new RuntimeException('This waiver can no longer accept an employee signature.');
                }
                if ((int)$lockedToken['employee_id'] !== (int)$session['employee_id']
                    || (int)($session['verification_user_id'] ?? 0) !== (int)($lockedToken['verification_user_id'] ?? 0)
                    || empty($lockedToken['verified_at'])) {
                    throw new RuntimeException('Employee identity verification is no longer valid.');
                }

                run('DELETE FROM dbo.acd_mw_waiver_acknowledgments WHERE waiver_id=?', [(int)$lockedWaiver['waiver_id']]);
                foreach (acknowledgments((string)$lockedWaiver['type_code']) as $order => $text) {
                    run('INSERT INTO dbo.acd_mw_waiver_acknowledgments(waiver_id,acknowledgment_text,is_acknowledged,display_order,acknowledged_at) VALUES(?,?,1,?,SYSDATETIME())', [(int)$lockedWaiver['waiver_id'], $text, $order + 1]);
                }
                run("INSERT INTO dbo.acd_mw_waiver_signatures(waiver_id,signer_type,signer_user_id,printed_name,signature_data,ip_address,user_agent,signed_at)
                    VALUES(?,'EMPLOYEE',?,?,?,?,?,SYSDATETIME())", [(int)$lockedWaiver['waiver_id'], (int)$session['verification_user_id'], (string)$session['printed_name'], (string)$session['signature'], $_SERVER['REMOTE_ADDR'] ?? '', mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500)]);
                if (run("UPDATE dbo.acd_mw_signing_tokens SET status='USED',used_at=SYSDATETIME() WHERE token_id=? AND status='ACTIVE' AND expires_at>SYSDATETIME()", [(int)$lockedToken['token_id']])->rowCount() !== 1) {
                    throw new RuntimeException('The signing session expired before it could be saved.');
                }
                if (run("UPDATE dbo.acd_mw_waivers SET status='AWAITING_SUPERVISOR' WHERE waiver_id=? AND status='AWAITING_EMPLOYEE_SIGNATURE'", [(int)$lockedWaiver['waiver_id']])->rowCount() !== 1) {
                    throw new RuntimeException('The waiver status changed before it could be saved.');
                }
                run('INSERT INTO dbo.acd_mw_activity_logs(user_id,action,entity_type,entity_id,description,ip_address,created_at) VALUES(?,?,?,?,?,?,SYSDATETIME())', [(int)$session['verification_user_id'], 'EMPLOYEE_SIGNED', 'WAIVER', (int)$lockedWaiver['waiver_id'], 'Employee signed after company credential verification', $_SERVER['REMOTE_ADDR'] ?? '']);
                $database->commit();
                $signed = one("SELECT TOP 1 signed_at FROM dbo.acd_mw_waiver_signatures WHERE waiver_id=? AND signer_type='EMPLOYEE' ORDER BY signature_id DESC", [(int)$lockedWaiver['waiver_id']]);
                signing_session_set((int)$token['token_id'], ['completed'=>true, 'signed_at'=>$signed['signed_at'] ?? date('Y-m-d H:i:s'), 'signature'=>null]);
                mobile_redirect($rawToken, 'success');
            } catch (Throwable $exception) {
                if ($database->inTransaction()) $database->rollBack();
                error_log('Employee signature transaction failed: '.$exception->getMessage());
                $error = $exception instanceof RuntimeException ? $exception->getMessage() : 'The signature could not be saved. Please ask staff to generate a new signing request.';
                $step = 'confirm';
            }
        }
    }

    if ($step !== 'verify' && !mobile_session_valid($token)) $step = 'verify';
    if ($step === 'sign' && !mobile_acks_valid($waiver, signing_session_get((int)$token['token_id']))) $step = 'review';
    if ($step === 'confirm' && empty(signing_session_get((int)$token['token_id'])['signature'])) $step = 'sign';
}

$labels = ['verify'=>'Verify identity', 'review'=>'Review waiver', 'sign'=>'Sign', 'confirm'=>'Confirm', 'success'=>'Complete'];
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title>Employee signature · Waiver Desk</title>
<link rel="stylesheet" href="../styles/signing.css"></head>
<body class="mobile-signing"><main class="mobile-shell">
<header class="mobile-brand"><span>W</span><div><b>Waiver Desk</b><small>Secure employee signature</small></div></header>
<?php if ($invalid): ?>
    <section class="mobile-card mobile-result <?= $alreadySigned ? 'success' : 'invalid' ?>">
        <span class="result-mark"><?= $alreadySigned ? '&#10003;' : '!' ?></span>
        <h1><?= $alreadySigned ? 'Waiver already signed' : 'Signing link unavailable' ?></h1>
        <p><?= $alreadySigned ? 'This employee signature has already been recorded. A second submission is not allowed.' : 'This signing link is no longer valid. Please request a new signing session from the authorized staff member.' ?></p>
    </section>
<?php else: ?>
    <nav class="mobile-progress" aria-label="Signing progress">
        <?php foreach (['verify','review','sign'] as $position=>$name): ?><span class="<?= array_search($step,$allowedSteps,true) >= array_search($name,$allowedSteps,true) ? 'active' : '' ?>"><i><?= $position+1 ?></i><?=e($labels[$name])?></span><?php endforeach ?>
    </nav>
    <?php if ($error): ?><div class="mobile-alert" role="alert"><?=e($error)?></div><?php endif ?>

    <?php if ($step === 'verify'): ?>
    <section class="mobile-card"><p class="mobile-kicker">Step 1</p><h1>Verify your identity</h1><p>Enter the company credentials assigned to the employee named on this waiver.</p>
        <form method="post" action="mobile-sign.php?token=<?=e(rawurlencode($rawToken))?>&amp;step=verify" class="mobile-form"><?=csrf_field()?><input type="hidden" name="action" value="verify_identity">
            <label>Biometrics number<input name="biometrics_number" autocomplete="username" inputmode="text" required></label>
            <label>Password<input type="password" name="password" autocomplete="current-password" required></label>
            <button class="mobile-primary">Verify &amp; Continue</button>
        </form>
    </section>
    <?php elseif ($step === 'review'): ?>
    <section class="mobile-card mobile-document"><p class="mobile-kicker">Step 2</p><h1>Review exact waiver</h1>
        <dl><div><dt>Waiver type</dt><dd><?=e($waiver['type_name'])?></dd></div><div><dt>Employee</dt><dd><?=e($waiver['employee_name'])?></dd></div><div><dt>Employee number</dt><dd><?=e($waiver['employee_number'])?></dd></div><div><dt>Department</dt><dd><?=e($waiver['department_name'])?></dd></div><div><dt>Position</dt><dd><?=e($waiver['position'])?></dd></div><div><dt>Date / time</dt><dd><?=e($waiver['waiver_date'].' '.$waiver['waiver_time'])?></dd></div><div><dt>Medical / nurse personnel</dt><dd><?=e($waiver['medical_personnel_name'])?></dd></div><div><dt>Recommendation</dt><dd><?=e($waiver['recommendation'])?></dd></div><?php if($waiver['type_code']==='REFUSED_MEDICAL_TREATMENT'):?><div><dt>Transportation</dt><dd><?=e($waiver['transportation_offered'])?></dd></div><?php endif?></dl>
        <div class="mobile-waiver-copy"><h2>Waiver of liability</h2><p><?=nl2br(e($waiver['signed_waiver_text']))?></p></div>
        <form method="post" action="mobile-sign.php?token=<?=e(rawurlencode($rawToken))?>&amp;step=review" class="mobile-form"><?=csrf_field()?><input type="hidden" name="action" value="accept_acknowledgments"><fieldset><legend>Required acknowledgments</legend><?php foreach(acknowledgments($waiver['type_code']) as $ack):?><label class="mobile-check"><input type="checkbox" name="ack[]" value="<?=e($ack)?>" required><span><?=e($ack)?></span></label><?php endforeach?></fieldset><button class="mobile-primary">Continue to Signature</button></form>
    </section>
    <?php elseif ($step === 'sign'): ?>
    <section class="mobile-card"><p class="mobile-kicker">Step 3</p><h1>Employee signature</h1><p>Sign inside the box using your finger or a stylus.</p>
        <form method="post" action="mobile-sign.php?token=<?=e(rawurlencode($rawToken))?>&amp;step=sign" class="mobile-form"><?=csrf_field()?><input type="hidden" name="action" value="prepare_signature">
            <label>Printed name<input name="printed_name" value="<?=e($waiver['employee_name'])?>" required></label>
            <label>Signature</label><div class="mobile-signature"><canvas width="900" height="360" data-signature="mobile_employee_signature"></canvas><button type="button" data-clear>Clear signature</button></div><input type="hidden" id="mobile_employee_signature" name="signature">
            <button class="mobile-primary">Continue</button>
        </form>
    </section>
    <?php elseif ($step === 'confirm'): $session=signing_session_get((int)$token['token_id']); ?>
    <section class="mobile-card confirm-card"><p class="mobile-kicker">Final confirmation</p><h1>Confirm your signature</h1><p>You are electronically signing the <b><?=e($waiver['type_name'])?></b> waiver for <b><?=e($waiver['employee_name'])?></b>.</p><img src="<?=e($session['signature'])?>" alt="Your signature preview"><p>Once submitted, the signature cannot be changed through the normal workflow.</p>
        <div class="mobile-actions"><a href="mobile-sign.php?token=<?=e(rawurlencode($rawToken))?>&amp;step=sign" class="mobile-secondary">Go Back</a><form method="post" action="mobile-sign.php?token=<?=e(rawurlencode($rawToken))?>&amp;step=confirm"><?=csrf_field()?><input type="hidden" name="action" value="confirm_signature"><button class="mobile-primary">Confirm &amp; Sign</button></form></div>
    </section>
    <?php else: $session=signing_session_get((int)$token['token_id']); ?>
    <section class="mobile-card mobile-result success"><span class="result-mark">&#10003;</span><h1>Waiver signed</h1><p>Your signature has been recorded successfully.</p><dl><div><dt>Signed</dt><dd><?=e((string)($session['signed_at'] ?? 'Recorded'))?></dd></div></dl><p>Please return to the authorized staff member.</p></section>
    <?php endif ?>
<?php endif ?>
</main><script src="../scripts/app.js"></script></body></html>
