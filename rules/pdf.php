<?php
declare(strict_types=1);

require_once __DIR__.'/signing.php';
require_once __DIR__.'/../vendor/fpdf/fpdf.php';

function waiver_pdf_text(mixed $value): string
{
    $text = str_replace(["\r\n", "\r"], "\n", (string)$value);
    $converted = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
    return $converted === false ? preg_replace('/[^\x20-\x7E\n]/', '?', $text) : $converted;
}

final class WaiverPdf extends FPDF
{
    public function Header(): void
    {
        $this->SetFont('Helvetica', 'B', 12);
        $this->Cell(0, 6, 'LA ROSE NOIRE PHILIPPINES, INC.', 0, 1, 'L');
        $this->SetFont('Helvetica', '', 7.5);
        $this->Cell(0, 4, 'Clark Freeport Zone, Philippines | Internal Waiver Record', 0, 1, 'L');
        $this->SetDrawColor(30, 48, 39);
        $this->SetLineWidth(.6);
        $this->Line(15, 24, 195, 24);
        $this->Ln(7);
    }

    public function Footer(): void
    {
        $this->SetY(-12);
        $this->SetDrawColor(190, 196, 191);
        $this->Line(15, $this->GetY(), 195, $this->GetY());
        $this->Ln(2);
        $this->SetFont('Helvetica', '', 7);
        $this->SetTextColor(90, 96, 92);
        $this->Cell(0, 5, 'Finalized waiver record | Page '.$this->PageNo().'/{nb}', 0, 0, 'C');
    }

    public function section(string $title): void
    {
        $this->Ln(2);
        $this->SetFillColor(238, 242, 238);
        $this->SetTextColor(28, 58, 42);
        $this->SetFont('Helvetica', 'B', 8);
        $this->Cell(0, 7, strtoupper(waiver_pdf_text($title)), 0, 1, 'L', true);
        $this->SetTextColor(20, 20, 20);
        $this->Ln(1.5);
    }

    public function pair(string $leftLabel, mixed $leftValue, string $rightLabel, mixed $rightValue): void
    {
        $x = $this->GetX();
        $y = $this->GetY();
        $width = 86;
        $this->SetFont('Helvetica', '', 7);
        $this->SetTextColor(95, 99, 96);
        $this->Cell($width, 4, strtoupper(waiver_pdf_text($leftLabel)), 0, 0);
        $this->SetX($x + 94);
        $this->Cell($width, 4, strtoupper(waiver_pdf_text($rightLabel)), 0, 1);
        $this->SetX($x);
        $this->SetFont('Helvetica', 'B', 9);
        $this->SetTextColor(20, 20, 20);
        $this->MultiCell($width, 5, waiver_pdf_text($leftValue), 0, 'L');
        $leftY = $this->GetY();
        $this->SetXY($x + 94, $y + 4);
        $this->MultiCell($width, 5, waiver_pdf_text($rightValue), 0, 'L');
        $this->SetY(max($leftY, $this->GetY()) + 2);
    }
}

function waiver_pdf_signature_file(string $data): string
{
    if (!signing_signature_valid($data)) throw new RuntimeException('A finalized signature image is invalid.');
    $binary = base64_decode(substr($data, 22), true);
    if ($binary === false) throw new RuntimeException('A finalized signature image could not be decoded.');
    $path = tempnam(sys_get_temp_dir(), 'mw_signature_');
    if ($path === false || file_put_contents($path, $binary, LOCK_EX) === false) throw new RuntimeException('A temporary signature image could not be prepared.');
    return $path;
}

function build_waiver_pdf(array $waiver, array $acknowledgments): string
{
    if (($waiver['status'] ?? '') !== 'COMPLETED' || empty($waiver['document_hash']) || empty($waiver['finalized_at'])) {
        throw new RuntimeException('Only completed waivers can be exported as PDF.');
    }
    if (empty($waiver['employee_signature']) || empty($waiver['supervisor_signature'])) {
        throw new RuntimeException('Both finalized signatures are required for PDF export.');
    }

    $temporaryFiles = [];
    try {
        $temporaryFiles[] = $employeeImage = waiver_pdf_signature_file((string)$waiver['employee_signature']);
        $temporaryFiles[] = $supervisorImage = waiver_pdf_signature_file((string)$waiver['supervisor_signature']);

        $pdf = new WaiverPdf('P', 'mm', 'A4');
        $pdf->AliasNbPages();
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 16);
        $pdf->SetTitle(waiver_pdf_text(($waiver['waiver_number'] ?? 'Waiver').' - Waiver of Liability'));
        $pdf->SetAuthor('La Rose Noire Philippines, Inc.');
        $pdf->AddPage();

        $pdf->SetFont('Helvetica', 'B', 18);
        $pdf->Cell(0, 8, 'WAIVER OF LIABILITY', 0, 1, 'C');
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->SetTextColor(47, 122, 77);
        $pdf->Cell(0, 6, strtoupper(waiver_pdf_text($waiver['type_name'])), 0, 1, 'C');
        $pdf->SetTextColor(20, 20, 20);
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->Cell(0, 5, waiver_pdf_text($waiver['waiver_number']), 0, 1, 'C');

        $pdf->section('Employee information');
        $pdf->pair('Employee', $waiver['employee_name'], 'Employee number', $waiver['employee_number']);
        $pdf->pair('Department / area', $waiver['department_name'], 'Position', $waiver['position']);

        $pdf->section('Waiver details');
        $pdf->pair('Date', $waiver['waiver_date'], 'Time', $waiver['waiver_time']);
        $pdf->pair('Medical / nurse personnel', $waiver['medical_personnel_name'], 'Transportation offered', $waiver['transportation_offered']);
        $pdf->SetFont('Helvetica', '', 7);$pdf->SetTextColor(95,99,96);$pdf->Cell(0,4,'RECOMMENDATION',0,1);
        $pdf->SetFont('Helvetica', '', 9);$pdf->SetTextColor(20,20,20);$pdf->MultiCell(0,5,waiver_pdf_text($waiver['recommendation']));
        if (trim((string)($waiver['remarks'] ?? '')) !== '') {$pdf->Ln(1);$pdf->SetFont('Helvetica','',7);$pdf->SetTextColor(95,99,96);$pdf->Cell(0,4,'REMARKS',0,1);$pdf->SetFont('Helvetica','',9);$pdf->SetTextColor(20,20,20);$pdf->MultiCell(0,5,waiver_pdf_text($waiver['remarks']));}

        $pdf->section('Exact waiver text');
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->MultiCell(0, 5.2, waiver_pdf_text($waiver['signed_waiver_text']), 0, 'J');

        $pdf->section('Acknowledgments');
        $pdf->SetFont('Helvetica', '', 8.5);
        foreach ($acknowledgments as $acknowledgment) {
            $mark = !empty($acknowledgment['is_acknowledged']) ? '[X] ' : '[ ] ';
            $pdf->MultiCell(0, 5, $mark.waiver_pdf_text($acknowledgment['acknowledgment_text']));
        }

        if ($pdf->GetY() > 215) $pdf->AddPage();
        $pdf->section('Final signatures');
        $signatureY = $pdf->GetY();
        $pdf->Image($employeeImage, 20, $signatureY, 75, 24, 'PNG');
        $pdf->Image($supervisorImage, 115, $signatureY, 75, 24, 'PNG');
        $pdf->SetY($signatureY + 26);
        $pdf->SetDrawColor(70, 70, 70);
        $pdf->Line(20, $pdf->GetY(), 95, $pdf->GetY());
        $pdf->Line(115, $pdf->GetY(), 190, $pdf->GetY());
        $pdf->Ln(2);
        $pdf->SetFont('Helvetica', 'B', 8.5);
        $pdf->Cell(95, 5, waiver_pdf_text($waiver['employee_printed']), 0, 0, 'C');
        $pdf->Cell(85, 5, waiver_pdf_text($waiver['supervisor_printed']), 0, 1, 'C');
        $pdf->SetFont('Helvetica', '', 7.5);
        $pdf->Cell(95, 4, 'Employee - signed '.waiver_pdf_text($waiver['employee_signed_at']), 0, 0, 'C');
        $pdf->Cell(85, 4, 'Supervisor - signed '.waiver_pdf_text($waiver['supervisor_signed_at']), 0, 1, 'C');

        $pdf->Ln(3);
        $pdf->SetFillColor(247, 248, 247);
        $pdf->SetFont('Helvetica', '', 7.5);
        $pdf->MultiCell(0, 5, waiver_pdf_text('Finalized at: '.$waiver['finalized_at']."\nDocument SHA-256: ".$waiver['document_hash']), 1, 'L', true);
        return $pdf->Output('S');
    } finally {
        foreach ($temporaryFiles as $file) if (is_string($file) && is_file($file)) @unlink($file);
    }
}
