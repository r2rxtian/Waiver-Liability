<?php
declare(strict_types=1);

ini_set('session.save_path', __DIR__.'/../tmp/sessions');
@mkdir(__DIR__.'/../tmp/sessions', 0777, true);
require_once __DIR__.'/../rules/pdf.php';

function png_chunk(string $type, string $data): string
{
    return pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
}

function sample_signature(): string
{
    $width = 900;
    $height = 360;
    $raw = '';
    for ($y = 0; $y < $height; $y++) {
        $line = "\x00";
        for ($x = 0; $x < $width; $x++) {
            $ink = abs($y - (170 + (int)(50 * sin($x / 65)))) < 3 && $x > 70 && $x < 830;
            $line .= $ink ? "\x22\x55\x38" : "\xff\xff\xff";
        }
        $raw .= $line;
    }
    $png = "\x89PNG\r\n\x1a\n";
    $png .= png_chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0));
    $png .= png_chunk('IDAT', gzcompress($raw, 9));
    $png .= png_chunk('IEND', '');
    return 'data:image/png;base64,'.base64_encode($png);
}

if (($argv[1] ?? '') === '--signature') {
    echo sample_signature();
    exit;
}

$signature = sample_signature();
$waiver = [
    'status'=>'COMPLETED','document_hash'=>str_repeat('a',64),'finalized_at'=>'2026-09-02T15:45:12.1234567',
    'waiver_number'=>'MW-2026-000123','type_name'=>'UNFIT TO WORK','employee_name'=>'Juan Dela Cruz',
    'employee_number'=>'EMP-00218','department_name'=>'Production','position'=>'Machine Operator',
    'waiver_date'=>'2026-09-02','waiver_time'=>'15:32:00','medical_personnel_name'=>'Nurse Maria Santos',
    'transportation_offered'=>'None','recommendation'=>'Employee was advised not to continue working due to the current medical condition.',
    'remarks'=>'Employee elected to continue working after the recommendation was explained.',
    'signed_waiver_text'=>str_repeat('I acknowledge that the recommendation was explained to me and that I accept responsibility for my voluntary decision. ', 10),
    'employee_signature'=>$signature,'supervisor_signature'=>$signature,'employee_printed'=>'Juan Dela Cruz',
    'supervisor_printed'=>'Carlo Miguel Reyes','employee_signed_at'=>'2026-09-02T15:32:00',
    'supervisor_signed_at'=>'2026-09-02T15:45:12',
];
$acknowledgments = array_map(static fn(string $text): array => ['acknowledgment_text'=>$text,'is_acknowledged'=>1], acknowledgments('UNFIT_TO_WORK'));
$output = __DIR__.'/../tmp/pdfs/waiver-pdf-smoke.pdf';
@mkdir(dirname($output), 0777, true);
file_put_contents($output, build_waiver_pdf($waiver, $acknowledgments));
echo realpath($output).PHP_EOL;
