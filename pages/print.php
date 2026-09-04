<?php
declare(strict_types=1);

require_once __DIR__.'/../auth/session.php';
require_once __DIR__.'/../rules/constants.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$w = one("SELECT w.*,e.employee_number,e.full_name employee_name,e.position,d.department_name,t.type_name,t.type_code,v.version_number,es.printed_name employee_printed,es.signature_data employee_signature,es.signed_at employee_signed_at,ss.printed_name supervisor_printed,ss.signature_data supervisor_signature,ss.signed_at supervisor_signed_at FROM dbo.acd_mw_waivers w JOIN dbo.acd_mw_employees e ON e.employee_id=w.employee_id LEFT JOIN dbo.acd_mw_departments d ON d.department_id=e.department_id JOIN dbo.acd_mw_waiver_types t ON t.waiver_type_id=w.waiver_type_id JOIN dbo.acd_mw_waiver_versions v ON v.waiver_version_id=w.waiver_version_id OUTER APPLY(SELECT TOP 1 * FROM dbo.acd_mw_waiver_signatures WHERE waiver_id=w.waiver_id AND signer_type='EMPLOYEE')es OUTER APPLY(SELECT TOP 1 * FROM dbo.acd_mw_waiver_signatures WHERE waiver_id=w.waiver_id AND signer_type='SUPERVISOR')ss WHERE w.waiver_id=?", [$id]);
if (!$w) exit('Waiver not found.');

if (user()['role_name'] === 'EMPLOYEE' && (int)(user()['employee_id'] ?? 0) !== (int)$w['employee_id']) {
    http_response_code(403);
    exit('Access denied.');
}

$isCompleted = ($w['status'] === 'COMPLETED' && !empty($w['employee_signature']) && !empty($w['supervisor_signature']));
$isModal = isset($_GET['modal']) && $_GET['modal'] === '1';

$acks = all('SELECT acknowledgment_text FROM dbo.acd_mw_waiver_acknowledgments WHERE waiver_id=? ORDER BY display_order', [$id]);
if (empty($acks) && function_exists('acknowledgments')) {
    $fallbackAcks = acknowledgments($w['type_code'] ?? 'UNFIT_TO_WORK');
    $acks = array_map(fn($t) => ['acknowledgment_text' => $t], $fallbackAcks);
}

audit('PREVIEW_A4_WAIVER', 'WAIVER', $id, $isCompleted ? 'Formal waiver opened for printing' : 'A4 waiver draft preview viewed');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?=e($w['waiver_number'] ?? 'DRAFT')?> — A4 Waiver Document Preview</title>
    <style>
        @page { size: A4; margin: 12mm 15mm; }
        * { box-sizing: border-box; }
        body {
            font: 10pt Arial, sans-serif;
            color: #1a1a1a;
            margin: 0;
            background: #f4f6f4;
            padding: <?= $isModal ? '10px' : '20px' ?>;
        }
        .page-container {
            max-width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            background: #ffffff;
            padding: 16mm 18mm;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #dbe2db;
            position: relative;
        }
        .toolbar {
            position: sticky;
            top: 10px;
            max-width: 210mm;
            margin: 0 auto 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #187b4a;
            color: #fff;
            padding: 8px 16px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(24, 123, 74, 0.2);
            z-index: 100;
        }
        .toolbar-copy {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 12px;
            font-weight: 600;
        }
        .toolbar-badge {
            background: rgba(255,255,255,0.25);
            padding: 2px 7px;
            border-radius: 4px;
            font-size: 10.5px;
            letter-spacing: 0.05em;
        }
        .toolbar-btn {
            background: #ffffff;
            color: #187b4a;
            border: none;
            padding: 6px 14px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 12px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .toolbar-btn:hover {
            background: #f0f7f2;
        }
        .preview-ribbon {
            background: #fff3db;
            color: #8a5717;
            border: 1px solid #eed9b0;
            padding: 6px 12px;
            text-align: center;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.06em;
            margin-bottom: 12px;
            border-radius: 6px;
        }
        .letterhead {
            display: grid;
            grid-template-columns: 72px 1fr;
            gap: 14px;
            align-items: center;
            border-bottom: 2px solid #222;
            padding-bottom: 10px;
        }
        .letterhead img {
            width: 68px;
            height: 68px;
            object-fit: contain;
        }
        .letterhead b {
            font-size: 12.5pt;
            display: block;
            color: #15291f;
        }
        .letterhead span {
            display: block;
            font-size: 8pt;
            line-height: 1.35;
            color: #444;
        }
        .title {
            text-align: center;
            padding: 14px 0 10px;
        }
        .title h1 {
            font: 700 16pt Arial, sans-serif;
            margin: 0 0 4px;
            color: #111;
            letter-spacing: 0.03em;
        }
        .title h2 {
            font-size: 10pt;
            margin: 0 0 6px;
            color: #187b4a;
            font-weight: bold;
        }
        .meta {
            display: flex;
            justify-content: space-between;
            font-size: 8.5pt;
            color: #555;
            padding-top: 4px;
            border-top: 1px solid #e0e0e0;
        }
        section {
            padding: 9px 0;
            border-bottom: 1px solid #999;
        }
        h3 {
            font-size: 8pt;
            letter-spacing: .08em;
            text-transform: uppercase;
            margin: 0 0 7px;
            color: #187b4a;
            font-weight: bold;
        }
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 7px 22px;
        }
        .grid .full-span {
            grid-column: 1 / -1;
        }
        dt {
            font-size: 7pt;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        dd {
            margin: 1px 0 0;
            font-size: 9pt;
            font-weight: bold;
            color: #111;
        }
        .text {
            line-height: 1.5;
            text-align: justify;
            font-size: 8.5pt;
            margin: 0;
            color: #222;
        }
        .acks-list {
            margin: 0;
            padding: 0;
            list-style: none;
        }
        .ack {
            font-size: 8pt;
            margin: 4px 0;
            display: flex;
            align-items: baseline;
            gap: 6px;
            color: #222;
        }
        .ack-box {
            font-family: monospace;
            font-weight: bold;
            color: #187b4a;
        }
        .signatures {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 36px;
            margin-top: 6px;
        }
        .sig-box {
            text-align: center;
        }
        .sig-box img {
            width: 100%;
            height: 56px;
            object-fit: contain;
            border-bottom: 1px solid #222;
        }
        .sig-placeholder {
            height: 56px;
            border: 1.5px dashed #a8b8aa;
            border-radius: 4px;
            background: #fafdfa;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #65756a;
            padding: 4px;
        }
        .sig-placeholder span {
            font-size: 8pt;
            font-weight: bold;
            color: #187b4a;
        }
        .sig-placeholder small {
            font-size: 6.5pt;
            color: #78857d;
        }
        .sig-box b, .sig-box small {
            display: block;
            text-align: center;
            margin-top: 3px;
        }
        .sig-box b {
            font-size: 8.5pt;
            color: #111;
        }
        .sig-box small {
            font-size: 7pt;
            color: #666;
        }
        .footer-meta {
            margin-top: 8px;
            font-size: 7pt;
            color: #777;
            display: flex;
            justify-content: space-between;
        }
        .hash {
            font: 6.5pt monospace;
            word-break: break-all;
        }
        @media print {
            body { padding: 0; background: #fff; }
            .toolbar { display: none !important; }
            .page-container { border: none; box-shadow: none; padding: 0; }
        }
    </style>
</head>
<body>

<div class="toolbar">
    <div class="toolbar-copy">
        <span>A4 Official Document View</span>
        <span class="toolbar-badge"><?= $isCompleted ? 'COMPLETED RECORD' : 'DRAFT PREVIEW' ?></span>
    </div>
    <div>
        <button class="toolbar-btn" onclick="window.print()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
            Print A4 Document
        </button>
    </div>
</div>

<div class="page-container">
    <?php if (!$isCompleted): ?>
        <div class="preview-ribbon">
            DRAFT DOCUMENT PREVIEW &bull; STATUS: <?=e(str_replace('_', ' ', $w['status']))?> &bull; NOT FINALIZED
        </div>
    <?php endif; ?>

    <div class="letterhead">
        <img src="/QRS_new/assets/images/la-rose-noire-logo.png" alt="La Rose Noire logo" onerror="this.style.display='none'">
        <div>
            <b>La Rose Noire Philippines, Inc.</b>
            <span>Lot 1 - A &amp; B, Clark IE-05 Area, M.A. Roxas Highway, Clark Freeport Zone, Philippines</span>
            <span>Tel: +63 45 499-3010 &bull; Fax: +63 45 499-2346 &bull; Email: clinic@la-rose-noire.com</span>
        </div>
    </div>

    <div class="title">
        <h1>WAIVER OF LIABILITY</h1>
        <h2><?=e($w['type_name'])?></h2>
        <div class="meta">
            <span>Date: <?=e($w['waiver_date'])?></span>
            <span>Ref: <?=e($w['waiver_number'] ?? 'DRAFT')?></span>
            <span>Time: <?=e($w['waiver_time'])?></span>
        </div>
    </div>

    <section>
        <h3>Employee Information</h3>
        <dl class="grid">
            <div><dt>Name</dt><dd><?=e($w['employee_name'])?></dd></div>
            <div><dt>Employee number</dt><dd><?=e($w['employee_number'])?></dd></div>
            <div><dt>Department / area</dt><dd><?=e($w['department_name'])?></dd></div>
            <div><dt>Position</dt><dd><?=e($w['position'])?></dd></div>
        </dl>
    </section>

    <section>
        <h3>Waiver Details</h3>
        <dl class="grid">
            <div><dt>Medical / nurse personnel</dt><dd><?=e($w['medical_personnel_name'])?></dd></div>
            <div><dt>Transportation offered</dt><dd><?=e($w['transportation_offered'] ?: 'None')?></dd></div>
            <div class="full-span"><dt>Recommendation</dt><dd><?=e($w['recommendation'])?></dd></div>
            <?php if (!empty($w['remarks'])): ?>
                <div class="full-span"><dt>Remarks</dt><dd><?=e($w['remarks'])?></dd></div>
            <?php endif; ?>
        </dl>
    </section>

    <section>
        <h3>Waiver Statement</h3>
        <p class="text"><?=nl2br(e($w['signed_waiver_text']))?></p>
    </section>

    <section>
        <h3>Employee Acknowledgments</h3>
        <ul class="acks-list">
            <?php foreach($acks as $a): ?>
                <li class="ack"><span class="ack-box">&#9745;</span> <span><?=e($a['acknowledgment_text'])?></span></li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section>
        <h3>Signatures &amp; Endorsements</h3>
        <div class="signatures">
            <div class="sig-box">
                <?php if (!empty($w['employee_signature'])): ?>
                    <img src="<?=e($w['employee_signature'])?>" alt="Employee signature">
                    <b><?=e($w['employee_printed'])?></b>
                    <small>Employee Signature &bull; <?=e($w['employee_signed_at'])?></small>
                <?php else: ?>
                    <div class="sig-placeholder">
                        <span>Pending Employee Signature</span>
                        <small>Awaiting mobile signature verification</small>
                    </div>
                    <b><?=e($w['employee_name'])?></b>
                    <small>Employee Signature (Pending)</small>
                <?php endif; ?>
            </div>

            <div class="sig-box">
                <?php if (!empty($w['supervisor_signature'])): ?>
                    <img src="<?=e($w['supervisor_signature'])?>" alt="Supervisor signature">
                    <b><?=e($w['supervisor_printed'])?></b>
                    <small>Supervisor Endorsement &bull; <?=e($w['supervisor_signed_at'])?></small>
                <?php else: ?>
                    <div class="sig-placeholder">
                        <span>Pending Supervisor Signature</span>
                        <small>Will be signed upon supervisor review</small>
                    </div>
                    <b><?=e($w['supervisor_printed'] ?? 'Supervisor on Duty')?></b>
                    <small>Supervisor Signature (Pending)</small>
                <?php endif; ?>
            </div>
        </div>

        <div class="footer-meta">
            <span>Finalized: <?=e($w['finalized_at'] ?? 'Pending supervisor completion')?></span>
            <span class="hash"><?= !empty($w['document_hash']) ? 'SHA256: '.e($w['document_hash']) : 'Hash generated upon completion' ?></span>
        </div>
    </section>
</div>

</body>
</html>
