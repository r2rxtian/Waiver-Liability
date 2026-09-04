<?php
declare(strict_types=1);

function employee_signing_panel(array $waiver): void
{
    $waiverId = (int)$waiver['waiver_id'];
    if (!signing_schema_ready()) {
        ?>
        <div class="signing-step-intro">
            <div>
                <h2>Employee signature</h2>
                <p>Ask the employee to scan the secure QR code using their phone.</p>
            </div>
            <div class="signing-header-actions">
                <span class="status-pill-badge expired">NOT INSTALLED</span>
            </div>
        </div>
        <section class="wizard-panel">
            <div class="signing-panel-heading">
                <h2>Signing setup required</h2>
                <p>Run <b>sql/add_signing_workflow.sql</b> against LRNPH_OJT before requesting a mobile signature.</p>
            </div>
        </section>
        <?php
        return;
    }

    if (!empty($waiver['employee_signature_id']) || in_array($waiver['status'], ['AWAITING_SUPERVISOR', 'COMPLETED'], true)) {
        unset($_SESSION['signing_request_tokens'][$waiverId]);
        $verification = one("SELECT TOP 1 verification_method,verified_at FROM dbo.acd_mw_signing_tokens WHERE waiver_id=? AND status='USED' ORDER BY used_at DESC,token_id DESC", [$waiverId]);
        $empName = e($waiver['employee_name']);
        $empInitial = strtoupper(substr((string)$waiver['employee_name'], 0, 1));
        $empNumber = e($waiver['employee_number']);
        $empDept = e($waiver['department_name'] ?? '');
        $empPos = e($waiver['position'] ?? '');
        $typeBadge = $waiver['type_code'] === 'REFUSED_MEDICAL_TREATMENT' ? 'REFUSED MEDICAL TREATMENT' : 'UNFIT TO WORK';
        $verifiedAt = display_datetime($waiver['employee_signed_at']);
        $methodLabel = (($verification['verification_method'] ?? 'COMPANY_CREDENTIALS') === 'COMPANY_CREDENTIALS') ? 'Company credentials' : str_replace('_', ' ', (string)$verification['verification_method']);
        ?>
        <div class="signing-step-intro">
            <div>
                <h2>Employee signature recorded</h2>
                <p>The employee has successfully reviewed, acknowledged, and signed the waiver.</p>
            </div>
            <div class="signing-header-actions">
                <a class="btn a4-preview-btn-top" target="_blank" href="pages/print.php?id=<?=$waiverId?>" data-a4-preview="pages/print.php?id=<?=$waiverId?>&amp;modal=1">
                    <?=wizard_svg('document')?> Preview A4 document
                </a>
                <span class="status-pill-badge active-pill">&#10003; SIGNED &amp; VERIFIED</span>
            </div>
        </div>

        <div class="wizard-layout wizard-layout-signed">
            <!-- Left Panel: Employee Signature Evidence -->
            <section class="wizard-panel signed-review-panel">
                <div class="wizard-panel-heading">
                    <div class="details-heading-row">
                        <h2>Employee signature record</h2>
                        <span class="type-pill-badge"><?=$typeBadge?></span>
                    </div>
                    <p>Verified digital signature and employee identity confirmation.</p>
                </div>

                <div class="signed-content-scroll">
                    <!-- Employee Card -->
                    <div class="review-card-section">
                        <div class="review-emp-grid">
                            <span class="avatar employee-avatar"><?=$empInitial?></span>
                            <div class="review-cell"><small>Name</small><b><?=$empName?></b></div>
                            <div class="review-cell"><small>Employee number</small><b><?=$empNumber?></b></div>
                            <div class="review-cell"><small>Department</small><b><?=$empDept?></b></div>
                            <div class="review-cell"><small>Position</small><b><?=$empPos?></b></div>
                        </div>
                    </div>

                    <!-- Digital Signature Box -->
                    <div class="signed-evidence-box">
                        <div class="evidence-header">
                            <span class="evidence-title">Captured digital signature</span>
                            <span class="verified-chip">&#10003; Verified via <?=$methodLabel?></span>
                        </div>
                        <div class="signed-canvas-frame">
                            <img src="<?=e($waiver['employee_signature'])?>" alt="Employee signature" class="signed-signature-img">
                        </div>
                        <div class="evidence-meta-row">
                            <div>
                                <small>Signer printed name</small>
                                <b><?=e($waiver['employee_printed'])?></b>
                            </div>
                            <div>
                                <small>Signed timestamp</small>
                                <b><?=$verifiedAt?></b>
                            </div>
                            <div>
                                <small>Authentication</small>
                                <b><?=$methodLabel?></b>
                            </div>
                        </div>
                    </div>

                    <!-- Legal Acknowledgments -->
                    <div class="signed-acks-box">
                        <div class="ack-badge-row">
                            <span class="check-icon"><?=wizard_svg('check-circle')?></span>
                            <b>Medical advisement and liability terms acknowledged</b>
                        </div>
                        <p>The employee confirmed that they were briefed on their clinical recommendation and agreed to the waiver terms.</p>
                    </div>
                </div>
            </section>

            <!-- Right Panel: Next Steps & Routing -->
            <aside class="wizard-panel signed-next-panel">
                <div class="wizard-panel-heading">
                    <h2>Next step</h2>
                    <p>Supervisor endorsement and document finalization.</p>
                </div>

                <div class="signed-status-card">
                    <div class="status-step-row done">
                        <span class="step-badge">&#10003;</span>
                        <div>
                            <b>Employee signature completed</b>
                            <small>Recorded on <?=$verifiedAt?></small>
                        </div>
                    </div>
                    <div class="status-step-row current">
                        <span class="step-badge pulse">2</span>
                        <div>
                            <b>Awaiting supervisor acknowledgment</b>
                            <small>Authorized supervisor endorsement required</small>
                        </div>
                    </div>
                    <div class="status-step-row pending">
                        <span class="step-badge">3</span>
                        <div>
                            <b>Final record completion</b>
                            <small>Official archival and PDF export ready</small>
                        </div>
                    </div>
                </div>

                <div class="a4-ready-box">
                    <div class="a4-header">
                        <span class="a4-icon"><?=wizard_svg('document')?></span>
                        <b>A4 document ready</b>
                    </div>
                    <p>View the full A4 print layout with the newly captured employee signature.</p>
                    <a class="btn a4-preview-btn" target="_blank" href="pages/print.php?id=<?=$waiverId?>" data-a4-preview="pages/print.php?id=<?=$waiverId?>&amp;modal=1">
                        <?=wizard_svg('document')?> Preview A4 document
                    </a>
                </div>

                <div class="summary-note">
                    <span class="note-icon"><?=wizard_svg('info')?></span>
                    <span>This waiver record is safely preserved and ready for supervisor review.</span>
                </div>
            </aside>
        </div>

        <footer class="wizard-universal-footer">
            <div class="footer-nav-left">
                <a class="btn secondary btn-nav-secondary" href="index.php?page=waivers" data-leave-workflow="true">Back to waivers</a>
            </div>
            <div class="footer-nav-center">
                <span class="footer-step-hint">&#10003; Employee signature complete &bull; Awaiting supervisor review</span>
            </div>
            <div class="footer-nav-right">
                <?php if (in_array(user()['role_name'], SIGNING_SUPERVISOR_ROLES, true)): ?>
                    <a class="btn btn-nav-primary" href="index.php?page=approval-sign&amp;id=<?=$waiverId?>">Review for Supervisor &rarr;</a>
                <?php else: ?>
                    <a class="btn btn-nav-primary" href="index.php?page=waiver-view&amp;id=<?=$waiverId?>">View signed waiver &rarr;</a>
                <?php endif; ?>
            </div>
        </footer>
        <?php
        return;
    }

    $token = signing_latest_token($waiverId);
    $request = $_SESSION['signing_request_tokens'][$waiverId] ?? null;
    $hasRaw = $token && $request
        && (int)($request['token_id'] ?? 0) === (int)$token['token_id']
        && hash_equals((string)$token['token_hash'], hash('sha256', (string)($request['raw_token'] ?? '')));
    $active = $token && $token['status'] === 'ACTIVE' && (int)($token['seconds_remaining'] ?? 0) > 0;

    $empName = e($waiver['employee_name']);
    $empInitial = strtoupper(substr((string)$waiver['employee_name'], 0, 1));
    $empNumber = e($waiver['employee_number']);
    $empDept = e($waiver['department_name'] ?? '');
    $empPos = e($waiver['position'] ?? '');
    $subLine = trim($empDept . ' &bull; ' . $empPos, ' &bull;');
    $typeBadge = $waiver['type_code'] === 'REFUSED_MEDICAL_TREATMENT' ? 'REFUSED MEDICAL TREATMENT' : 'UNFIT TO WORK';

    $createdAtFormatted = date('g:i A', strtotime($waiver['created_at']));
    $sessionId = 'WV-' . date('ymd', strtotime($waiver['created_at'])) . '-' . $waiverId;
    $totalSeconds = signing_token_minutes() * 60;
    $secondsRemaining = $active ? max(0, (int)$token['seconds_remaining']) : 0;
    $percentRemaining = $totalSeconds > 0 ? min(100, max(0, round(($secondsRemaining / $totalSeconds) * 100))) : 0;
    $minutesLeft = str_pad((string)floor($secondsRemaining / 60), 2, '0', STR_PAD_LEFT);
    $secondsLeft = str_pad((string)($secondsRemaining % 60), 2, '0', STR_PAD_LEFT);
    $countdownFormatted = "{$minutesLeft}:{$secondsLeft}";
    $mailSubject = rawurlencode("Medical Waiver Signing Link - " . $waiver['employee_name']);
    $mailBody = rawurlencode("Hello {$waiver['employee_name']},\n\nPlease review and sign your medical waiver using this secure link:\n" . ($request['url'] ?? '') . "\n\nThis one-time link is valid for " . signing_token_minutes() . " minutes.");
    ?>
    <div class="signing-step-intro">
        <div>
            <h2>Employee signature</h2>
            <p>Ask the employee to scan the secure QR code using their phone.</p>
        </div>
        <div class="signing-header-actions">
            <a class="btn a4-preview-btn-top" target="_blank" href="pages/print.php?id=<?=$waiverId?>">
                <?=wizard_svg('document')?> Preview A4 document
            </a>
            <span class="status-pill-badge active-pill"><?=($active && $hasRaw) ? 'LINK ACTIVE' : 'EXPIRED'?></span>
        </div>
    </div>

    <div class="wizard-layout wizard-layout-signing" data-signing-waiting data-waiver-id="<?=$waiverId?>" data-seconds-remaining="<?=$secondsRemaining?>" data-total-seconds="<?=$totalSeconds?>" data-can-supervise="<?=in_array(user()['role_name'], SIGNING_SUPERVISOR_ROLES, true) ? '1' : '0'?>">
        <!-- Left Panel: Scan to review and sign -->
        <section class="wizard-panel scan-panel">
            <h2>Scan to review and sign</h2>

            <div class="scan-grid-top">
                <div class="qr-box-frame">
                    <?php if ($active && $hasRaw): ?>
                        <?=signing_qr_svg((string)$request['url'])?>
                    <?php else: ?>
                        <div class="qr-placeholder">
                            <span class="qr-placeholder-icon"><?=wizard_svg('lock')?></span>
                            <p>QR expired or not requested</p>
                            <form method="post">
                                <?=csrf_field()?>
                                <input type="hidden" name="action" value="request_employee_signature">
                                <input type="hidden" name="waiver_id" value="<?=$waiverId?>">
                                <button class="btn btn-sm">Generate new QR</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="scan-emp-info">
                    <div class="scan-emp-header">
                        <span class="avatar employee-avatar"><?=$empInitial?></span>
                        <div class="scan-emp-names">
                            <h3><?=$empName?></h3>
                            <p class="emp-num"><?=$empNumber?></p>
                            <p class="side-emp-line"><?=$subLine?></p>
                            <div><span class="type-pill-badge-orange"><?=$typeBadge?></span></div>
                        </div>
                    </div>

                    <div class="scan-steps-list">
                        <div class="scan-step-item">
                            <span class="step-num-badge">1</span>
                            <span>Open the phone camera</span>
                        </div>
                        <div class="scan-step-item">
                            <span class="step-num-badge">2</span>
                            <span>Scan the QR code</span>
                        </div>
                        <div class="scan-step-item">
                            <span class="step-num-badge">3</span>
                            <span>Review, acknowledge, and sign</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="scan-divider">
                <span>OR SEND A SECURE LINK</span>
            </div>

            <div class="secure-link-row">
                <input type="text" readonly value="<?=e($request['url'] ?? '')?>" data-signing-link aria-label="Secure signing link">
                <button type="button" class="btn copy-link-btn" data-copy-signing-link>
                    <?=wizard_svg('copy')?> Copy link
                </button>
            </div>

            <div class="send-email-row">
                <a class="btn send-email-btn" href="mailto:<?=e($waiver['email'] ?? '')?>?subject=<?=$mailSubject?>&amp;body=<?=$mailBody?>">
                    <?=wizard_svg('mail')?> Send by email
                </a>
            </div>

            <p class="one-time-lock-note">
                <span class="lock-icon"><?=wizard_svg('lock')?></span>
                <span>This one-time link can only be used by the selected employee.</span>
            </p>
        </section>

        <!-- Right Panel: Signing status -->
        <aside class="wizard-panel status-panel">
            <h2>Signing status</h2>

            <div class="status-live-header">
                <div class="status-live-dot-row">
                    <span class="live-dot pulse"></span>
                    <b>Waiting for employee</b>
                </div>
                <p>This page updates automatically when the employee opens or completes the request.</p>
            </div>

            <div class="status-timeline">
                <div class="timeline-step done">
                    <span class="timeline-icon"><?=wizard_svg('check-circle')?></span>
                    <span class="timeline-label">Signing request created</span>
                    <span class="timeline-time"><?=$createdAtFormatted?></span>
                </div>
                <div class="timeline-step done">
                    <span class="timeline-icon"><?=wizard_svg('check-circle')?></span>
                    <span class="timeline-label">Secure link activated</span>
                    <span class="timeline-time"><?=$createdAtFormatted?></span>
                </div>
                <div class="timeline-step current">
                    <span class="timeline-icon pulse-ring"></span>
                    <span class="timeline-label"><b>Waiting for employee to open</b></span>
                </div>
                <div class="timeline-step pending">
                    <span class="timeline-icon gray-ring"></span>
                    <span class="timeline-label">Signature completed</span>
                </div>
            </div>

            <div class="expiration-box">
                <div class="expiration-clock-col">
                    <span class="clock-icon"><?=wizard_svg('clock')?></span>
                    <div class="expiration-time-copy">
                        <small>Link expires in</small>
                        <strong data-signing-countdown><?=$countdownFormatted?></strong>
                    </div>
                </div>
                <div class="expiration-bar-col">
                    <div class="progress-track">
                        <div class="progress-fill" id="signing-progress-bar" style="width: <?=$percentRemaining?>%;"></div>
                    </div>
                    <small>A new secure link can be generated after expiration.</small>
                </div>
            </div>

            <p class="signing-footer-meta">Session ID: <?=e($sessionId)?> &bull; Last checked just now</p>
        </aside>
    </div>

    <footer class="wizard-universal-footer">
        <div class="footer-nav-left">
            <a class="btn secondary btn-nav-secondary" href="index.php?page=new-waiver&amp;step=4&amp;id=<?=$waiverId?>" data-leave-workflow="true">Back to review</a>
        </div>
        <div class="footer-nav-center">
            <span class="footer-step-hint">Step 5 of 6 &bull; Waiting for employee signature &bull; Session <?=e($sessionId)?></span>
        </div>
        <div class="footer-nav-right">
            <form method="post" class="inline-action-form">
                <?=csrf_field()?>
                <input type="hidden" name="action" value="request_employee_signature">
                <input type="hidden" name="waiver_id" value="<?=$waiverId?>">
                <button class="btn secondary btn-nav-secondary" type="submit">
                    <?=wizard_svg('refresh')?> Generate new QR
                </button>
            </form>
            <form method="post" class="inline-action-form">
                <?=csrf_field()?>
                <input type="hidden" name="action" value="cancel_signing_request">
                <input type="hidden" name="waiver_id" value="<?=$waiverId?>">
                <button class="btn-nav-danger" data-confirm="Cancel this signing request? The QR will stop working immediately.">
                    Cancel signing request
                </button>
            </form>
        </div>
    </footer>
    <?php
}
