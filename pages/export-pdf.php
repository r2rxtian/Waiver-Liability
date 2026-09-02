<?php
declare(strict_types=1);

require_once __DIR__.'/../rules/pdf.php';
require_login();

try {
    $waiverId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$waiverId) throw new RuntimeException('Invalid waiver.');
    $waiver = signing_waiver((int)$waiverId);
    if (!$waiver) throw new RuntimeException('Waiver not found.');
    if (user()['role_name'] === 'EMPLOYEE' && (int)(user()['employee_id'] ?? 0) !== (int)$waiver['employee_id']) {
        http_response_code(403);
        throw new RuntimeException('Access denied.');
    }
    if ($waiver['status'] !== 'COMPLETED') throw new RuntimeException('Only completed waivers can be exported.');
    $acknowledgments = all('SELECT acknowledgment_text,is_acknowledged,acknowledged_at FROM dbo.acd_mw_waiver_acknowledgments WHERE waiver_id=? ORDER BY display_order', [(int)$waiverId]);
    $pdf = build_waiver_pdf($waiver, $acknowledgments);
    signing_audit('EXPORT_PDF', 'WAIVER', (int)$waiverId, 'Exported finalized waiver PDF');
    $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', (string)$waiver['waiver_number']).'.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    header('Content-Length: '.strlen($pdf));
    header('Cache-Control: no-store, private');
    echo $pdf;
} catch (Throwable $exception) {
    error_log('Waiver PDF export failed: '.$exception->getMessage());
    if (!headers_sent()) http_response_code($exception instanceof RuntimeException ? 422 : 500);
    echo 'The finalized waiver PDF could not be generated. '.e($exception instanceof RuntimeException ? $exception->getMessage() : 'Please contact the administrator.');
}
