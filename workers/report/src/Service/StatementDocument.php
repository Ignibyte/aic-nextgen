<?php

declare(strict_types=1);

namespace Worker\Report\Service;

use FPDF;
use RuntimeException;

use function is_string;
use function preg_replace;
use function str_replace;
use function strlen;

/**
 * A statement as a PDF, made with FPDF (#336), a library with no native path.
 * FPDF stamps the creation date with the time it runs; the stamp is replaced
 * with the statement's own date, in as many bytes, so the same statement
 * makes the same file and the cross-reference offsets after it hold.
 */
final readonly class StatementDocument
{
    /**
     * @param string $account The account the statement is for.
     * @param string $date    Its date, as `2026-11-26`.
     *
     * @throws RuntimeException When FPDF makes no document or its stamp cannot be fixed.
     *
     * @return string The PDF.
     */
    public function pdf(string $account, string $date): string
    {
        $fpdf = new FPDF();
        $fpdf->SetTitle('Statement ' . $date);
        $fpdf->AddPage();
        $fpdf->SetFont('Helvetica', 'B', 16);
        $fpdf->Cell(0, 10, 'Statement', 0, 1);
        $fpdf->SetFont('Helvetica', '', 12);
        $fpdf->Cell(0, 8, 'Account: ' . $account, 0, 1);
        $fpdf->Cell(0, 8, 'Date: ' . $date, 0, 1);
        $fpdf->Ln();
        $fpdf->Cell(0, 8, 'Made by the report worker from a queued job.', 0, 1);

        $pdf = $fpdf->Output('S');

        if (!is_string($pdf)) {
            throw new RuntimeException('FPDF made no document.');
        }

        $stamp = '/CreationDate (D:' . str_replace('-', '', $date) . "000000+00'00')";
        $fixed = preg_replace("#/CreationDate \\(D:\\d{14}[+-]\\d{2}'\\d{2}'\\)#", $stamp, $pdf, 1);

        if ($fixed === null || strlen($fixed) !== strlen($pdf)) {
            throw new RuntimeException("The statement's creation date could not be fixed.");
        }

        return $fixed;
    }
}
