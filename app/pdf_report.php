<?php
declare(strict_types=1);

final class TrustGridPdf
{
    private const PAGE_WIDTH = 595;
    private const PAGE_HEIGHT = 842;
    private const LEFT = 48;
    private const RIGHT = 547;
    private const BOTTOM = 52;

    private array $pages = [];
    private string $content = '';
    private float $y = 0;

    public function __construct(private readonly string $reportLabel)
    {
        $this->startPage();
    }

    public function title(string $title, string $subtitle = ''): void
    {
        $this->paragraph($title, 22, 0, true, [0.09, 0.17, 0.29], 1.15);
        if ($subtitle !== '') {
            $this->paragraph($subtitle, 10, 0, false, [0.35, 0.40, 0.47]);
        }
        $this->y -= 8;
    }

    public function section(string $heading): void
    {
        $this->ensureSpace(32);
        $this->content .= sprintf(
            "q 0.94 0.96 0.98 rg %d %.2f 499 24 re f Q\n",
            self::LEFT,
            $this->y - 19
        );
        $this->drawText(self::LEFT + 10, $this->y - 13, $heading, 10, true, [0.09, 0.17, 0.29]);
        $this->y -= 34;
    }

    public function metrics(array $metrics): void
    {
        $columnGap = 12;
        $cardWidth = (self::RIGHT - self::LEFT - $columnGap) / 2;
        foreach (array_chunk($metrics, 2) as $row) {
            $this->ensureSpace(56);
            foreach ($row as $index => $metric) {
                $x = self::LEFT + $index * ($cardWidth + $columnGap);
                $this->content .= sprintf(
                    "q 0.96 0.97 0.98 rg %.2f %.2f %.2f 46 re f Q\n",
                    $x,
                    $this->y - 42,
                    $cardWidth
                );
                $this->drawText($x + 10, $this->y - 13, (string) $metric['label'], 8, true, [0.35, 0.40, 0.47]);
                $this->drawText($x + 10, $this->y - 31, (string) $metric['value'], 13, true, [0.09, 0.17, 0.29]);
            }
            $this->y -= 56;
        }
        $this->y -= 6;
    }

    public function paragraph(
        string $text,
        float $fontSize = 10,
        float $indent = 0,
        bool $bold = false,
        array $color = [0.18, 0.22, 0.28],
        float $lineSpacing = 1.42
    ): void {
        $maxWidth = self::RIGHT - self::LEFT - $indent;
        $lines = $this->wrapText($text, $fontSize, $maxWidth);
        $lineHeight = $fontSize * $lineSpacing;
        foreach ($lines as $line) {
            $this->ensureSpace($lineHeight);
            $this->drawText(self::LEFT + $indent, $this->y, $line, $fontSize, $bold, $color);
            $this->y -= $lineHeight;
        }
        $this->y -= 3;
    }

    public function question(string $title, string $question, string $response, string $guidance, string $notes): void
    {
        $this->ensureSpace(42);
        $this->paragraph($title, 11, 0, true, [0.09, 0.17, 0.29], 1.25);
        $this->paragraph($question, 9.5, 0, false, [0.18, 0.22, 0.28], 1.35);
        $this->paragraph('Response: ' . $response, 9, 0, true, [0.13, 0.31, 0.43]);
        if ($guidance !== '') {
            $this->paragraph('Guidance: ' . $guidance, 8.5, 0, false, [0.35, 0.40, 0.47]);
        }
        if ($notes !== '') {
            $this->paragraph('Evidence note: ' . $notes, 8.5, 0, false, [0.25, 0.30, 0.36]);
        }
        $this->y -= 5;
    }

    public function finish(): string
    {
        $pageCount = count($this->pages) + 1;
        $this->pages[] = $this->content;
        foreach ($this->pages as $index => &$page) {
            $this->addFooter($page, $index + 1, $pageCount);
        }
        unset($page);

        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
        ];
        $pageIds = [];
        foreach ($this->pages as $index => $pageContent) {
            $pageId = 5 + $index * 2;
            $contentId = $pageId + 1;
            $pageIds[] = $pageId . ' 0 R';
            $objects[$pageId] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %d %d] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>',
                self::PAGE_WIDTH,
                self::PAGE_HEIGHT,
                $contentId
            );
            $objects[$contentId] = '<< /Length ' . strlen($pageContent) . " >>\nstream\n"
                . $pageContent . "endstream";
        }
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $pageIds) . '] /Count ' . count($this->pages) . ' >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $object . "\nendobj\n";
        }
        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        for ($id = 1; $id <= count($objects); $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id]);
        }
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n" . $xrefOffset . "\n%%EOF";

        return $pdf;
    }

    private function startPage(): void
    {
        if ($this->content !== '') {
            $this->pages[] = $this->content;
        }
        $this->content = "q 0.08 0.16 0.27 rg 0 770 595 72 re f Q\n";
        $this->content .= "q 0.13 0.68 0.62 rg 0 766 595 4 re f Q\n";
        $this->drawText(self::LEFT, 813, 'TRUSTGRID AFRICA', 12, true, [1, 1, 1]);
        $this->drawText(self::LEFT, 794, $this->reportLabel, 8, false, [0.86, 0.90, 0.94]);
        $this->y = 742;
    }

    private function ensureSpace(float $height): void
    {
        if ($this->y - $height < self::BOTTOM) {
            $this->startPage();
        }
    }

    private function drawText(float $x, float $y, string $text, float $fontSize, bool $bold, array $color): void
    {
        $text = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
        if ($text === false) {
            throw new RuntimeException('Unable to encode report text for PDF output.');
        }
        $text = str_replace(["\\", '(', ')', "\r", "\n"], ["\\\\", '\\(', '\\)', '', ' '], $text);
        $font = $bold ? 'F2' : 'F1';
        $this->content .= sprintf(
            "BT /%s %.2f Tf %.3f %.3f %.3f rg %.2f %.2f Td (%s) Tj ET\n",
            $font,
            $fontSize,
            $color[0],
            $color[1],
            $color[2],
            $x,
            $y,
            $text
        );
    }

    private function wrapText(string $text, float $fontSize, float $maxWidth): array
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        if ($text === '') {
            return [''];
        }

        $maxCharacters = max(12, (int) floor($maxWidth / ($fontSize * 0.52)));
        $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $lines = [];
        $line = '';
        foreach ($words as $word) {
            if (mb_strwidth($word, 'UTF-8') > $maxCharacters) {
                if ($line !== '') {
                    $lines[] = $line;
                    $line = '';
                }
                foreach (mb_str_split($word, $maxCharacters, 'UTF-8') as $piece) {
                    $lines[] = $piece;
                }
                continue;
            }
            $candidate = $line === '' ? $word : $line . ' ' . $word;
            if ($line !== '' && mb_strwidth($candidate, 'UTF-8') > $maxCharacters) {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $candidate;
            }
        }
        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines;
    }

    private function addFooter(string &$page, int $pageNumber, int $pageCount): void
    {
        $oldContent = $this->content;
        $this->content = $page;
        $this->drawText(self::LEFT, 30, 'TrustGrid Africa · ' . gmdate('F j, Y'), 7, false, [0.42, 0.46, 0.52]);
        $this->drawText(self::RIGHT - 54, 30, 'Page ' . $pageNumber . ' of ' . $pageCount, 7, false, [0.42, 0.46, 0.52]);
        $page = $this->content;
        $this->content = $oldContent;
    }
}

function send_pdf_report(TrustGridPdf $report, string $filename): never
{
    $pdf = $report->finish();
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit;
}

function pdf_filename(string $name): string
{
    $name = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
    if ($name === false) {
        throw new RuntimeException('Unable to create the report filename.');
    }
    $name = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name) ?? '', '-'));

    return ($name === '' ? 'trustgrid-report' : substr($name, 0, 72)) . '.pdf';
}
