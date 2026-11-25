<?php
/**
 * AI Voice Recording Summarizer - PDF Report Generator
 *
 * Generates an executive A4 PDF report containing:
 * - Metadata header (File name, Duration, Size, Language, Processing Time)
 * - 7 structured summary sections
 * - Professional print-optimised layout
 * - Pure PHP implementation without external binary dependencies
 */

class PdfReport {

    private array $pages = [];
    private int $currentPage = 0;
    private float $pageWidth = 595.28;  // A4 Width in points (72 pt/inch)
    private float $pageHeight = 841.89; // A4 Height in points
    private float $marginLeft = 45.0;
    private float $marginRight = 45.0;
    private float $marginTop = 50.0;
    private float $marginBottom = 50.0;
    private float $cursorY = 50.0;
    private string $currentFont = 'F1';
    private float $currentFontSize = 10.0;
    private array $fontColor = [30, 41, 59]; // Slate 800
    private array $fillColor = [255, 255, 255];
    private array $drawColor = [203, 213, 225];

    public function __construct() {
        $this->addPage();
    }

    /**
     * Add a new page
     */
    public function addPage(): void {
        $this->currentPage++;
        $this->pages[$this->currentPage] = '';
        $this->cursorY = $this->marginTop;

        // Draw top subtle decorative accent line on page 2+
        if ($this->currentPage > 1) {
            $this->setDrawColor(37, 99, 235);
            $this->drawRect($this->marginLeft, 20, $this->getContentWidth(), 2, 'F');

            // Running header
            $this->setFont('STSong', '', 8);
            $this->setTextColor(148, 163, 184);
            $this->text($this->marginLeft, 32, "AI Voice Recording Summarizer - Executive Intelligence Report");
            $this->cursorY = 55.0;
        }
    }

    public function getContentWidth(): float {
        return $this->pageWidth - $this->marginLeft - $this->marginRight;
    }

    public function setFont(string $family, string $style, float $size): void {
        $family = strtolower($family);
        if ($family === 'stsong' || $family === 'cjk' || $family === 'chinese') {
            $this->currentFont = (str_contains(strtolower($style), 'b')) ? 'F3' : 'F3';
        } elseif (str_contains(strtolower($style), 'b')) {
            $this->currentFont = 'F2';
        } else {
            $this->currentFont = 'F1';
        }
        $this->currentFontSize = $size;
    }

    public function setTextColor(int $r, int $g, int $b): void {
        $this->fontColor = [$r, $g, $b];
        $this->pages[$this->currentPage] .= sprintf("%.3f %.3f %.3f rg\n", $r / 255, $g / 255, $b / 255);
    }

    public function setFillColor(int $r, int $g, int $b): void {
        $this->fillColor = [$r, $g, $b];
        $this->pages[$this->currentPage] .= sprintf("%.3f %.3f %.3f rg\n", $r / 255, $g / 255, $b / 255);
    }

    public function setDrawColor(int $r, int $g, int $b): void {
        $this->drawColor = [$r, $g, $b];
        $this->pages[$this->currentPage] .= sprintf("%.3f %.3f %.3f RG\n", $r / 255, $g / 255, $b / 255);
    }

    public function drawRect(float $x, float $y, float $w, float $h, string $style = 'D'): void {
        $pdfY = $this->pageHeight - $y - $h;
        $op = match($style) {
            'F' => 'f',
            'FD', 'DF' => 'B',
            default => 'S'
        };
        $this->pages[$this->currentPage] .= sprintf("%.2f %.2f %.2f %.2f re %s\n", $x, $pdfY, $w, $h, $op);
    }

    public function drawLine(float $x1, float $y1, float $x2, float $y2): void {
        $pdfY1 = $this->pageHeight - $y1;
        $pdfY2 = $this->pageHeight - $y2;
        $this->pages[$this->currentPage] .= sprintf("%.2f %.2f m %.2f %.2f l S\n", $x1, $pdfY1, $x2, $pdfY2);
    }

    public function drawRoundedCard(float $x, float $y, float $w, float $h, float $r = 4.0): void {
        // Draw card background with border
        $this->drawRect($x, $y, $w, $h, 'FD');
    }

    /**
     * Check if Y cursor exceeds bottom margin and page-break if needed
     */
    public function checkPageBreak(float $heightNeeded): void {
        if ($this->cursorY + $heightNeeded > ($this->pageHeight - $this->marginBottom)) {
            $this->addPage();
        }
    }

    /**
     * Render text at explicit coordinate (x, y from top)
     */
    public function text(float $x, float $y, string $text): void {
        $pdfY = $this->pageHeight - $y;
        $encoded = $this->encodeTextForPdf($text);
        $this->pages[$this->currentPage] .= sprintf(
            "BT /%s %.2f Tf %.3f %.3f %.3f rg 1 0 0 1 %.2f %.2f Tm %s Tj ET\n",
            $this->currentFont,
            $this->currentFontSize,
            $this->fontColor[0] / 255,
            $this->fontColor[1] / 255,
            $this->fontColor[2] / 255,
            $x,
            $pdfY,
            $encoded
        );
    }

    /**
     * Multi-line text block with automatic line wrap for both Latin and CJK text
     */
    public function multiCell(float $width, float $lineHeight, string $text, string $align = 'L'): void {
        $lines = $this->splitTextIntoLines($text, $width);
        foreach ($lines as $line) {
            $this->checkPageBreak($lineHeight);
            $this->text($this->marginLeft, $this->cursorY + $lineHeight - 2, $line);
            $this->cursorY += $lineHeight;
        }
    }

    /**
     * Indented bullet item
     */
    public function bulletItem(string $text, string $bulletChar = "• "): void {
        $bulletWidth = 14.0;
        $contentWidth = $this->getContentWidth() - $bulletWidth;
        $lineHeight = $this->currentFontSize * 1.45;

        $lines = $this->splitTextIntoLines($text, $contentWidth);
        if (empty($lines)) return;

        $this->checkPageBreak($lineHeight * count($lines));

        // Draw bullet marker
        $this->setFont('STSong', 'B', $this->currentFontSize);
        $this->setTextColor(37, 99, 235); // Blue bullet
        $this->text($this->marginLeft + 2, $this->cursorY + $lineHeight - 2, $bulletChar);

        // Draw text lines
        $this->setFont('STSong', '', $this->currentFontSize);
        $this->setTextColor(51, 65, 85);

        foreach ($lines as $idx => $line) {
            $this->text($this->marginLeft + $bulletWidth, $this->cursorY + $lineHeight - 2, $line);
            $this->cursorY += $lineHeight;
        }
        $this->cursorY += 3; // Item spacing
    }

    /**
     * Approximate character width in points for text wrapping
     */
    private function getCharWidth(string $char): float {
        // CJK characters have roughly full-width aspect (1em = fontSize)
        if (ord($char) > 127) {
            return $this->currentFontSize * 0.95;
        }
        // Latin letters average ~ 0.52em
        return $this->currentFontSize * 0.52;
    }

    /**
     * Split string into lines fitting $maxWidth
     */
    private function splitTextIntoLines(string $text, float $maxWidth): array {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $paragraphs = explode("\n", $text);
        $resultLines = [];

        foreach ($paragraphs as $para) {
            $para = trim($para);
            if ($para === '') {
                $resultLines[] = '';
                continue;
            }

            // Segment text into tokens (words or CJK characters)
            $tokens = [];
            $len = mb_strlen($para, 'UTF-8');
            $currentLatinWord = '';

            for ($i = 0; $i < $len; $i++) {
                $char = mb_substr($para, $i, 1, 'UTF-8');
                if (ord($char) > 127 || in_array($char, [' ', "\t", '，', '。', '；', '：', '！', '？', '（', '）', '【', '】'])) {
                    if ($currentLatinWord !== '') {
                        $tokens[] = $currentLatinWord;
                        $currentLatinWord = '';
                    }
                    $tokens[] = $char;
                } else {
                    $currentLatinWord .= $char;
                }
            }
            if ($currentLatinWord !== '') {
                $tokens[] = $currentLatinWord;
            }

            // Build lines
            $currentLine = '';
            $currentLineWidth = 0.0;

            foreach ($tokens as $token) {
                $tokenLen = mb_strlen($token, 'UTF-8');
                $tokenWidth = 0.0;
                for ($k = 0; $k < $tokenLen; $k++) {
                    $tokenWidth += $this->getCharWidth(mb_substr($token, $k, 1, 'UTF-8'));
                }

                if ($currentLineWidth + $tokenWidth <= $maxWidth) {
                    $currentLine .= $token;
                    $currentLineWidth += $tokenWidth;
                } else {
                    if (trim($currentLine) !== '') {
                        $resultLines[] = trim($currentLine);
                    }
                    $currentLine = ltrim($token);
                    $currentLineWidth = $tokenWidth;
                }
            }

            if (trim($currentLine) !== '') {
                $resultLines[] = trim($currentLine);
            }
        }

        return $resultLines;
    }

    /**
     * Encode text for PDF stream.
     * Uses UTF-16BE hex string <FEFF...> for CIDFont STSong-Light which renders both CJK and Latin perfectly.
     */
    private function encodeTextForPdf(string $text): string {
        $utf16 = mb_convert_encoding($text, 'UTF-16BE', 'UTF-8');
        // Hexadecimal string format: <FEFF....>
        return '<FEFF' . bin2hex($utf16) . '>';
    }

    /**
     * Build and write final PDF document
     */
    public function output(): string {
        $totalPageCount = $this->currentPage;

        // Append footers to each page
        for ($p = 1; $p <= $totalPageCount; $p++) {
            $this->currentPage = $p;
            $footerY = $this->pageHeight - 28.0;

            // Footer border line
            $this->setDrawColor(226, 232, 240);
            $this->drawLine($this->marginLeft, 28.0, $this->pageWidth - $this->marginRight, 28.0);

            // Left brand
            $this->setFont('STSong', '', 8);
            $this->setTextColor(148, 163, 184);
            $this->text($this->marginLeft, 18.0, "AI Voice Recording Summarizer  •  Stateless Realtime Intelligence");

            // Right page number
            $pageStr = "Page {$p} of {$totalPageCount}";
            $this->text($this->pageWidth - $this->marginRight - 55.0, 18.0, $pageStr);
        }

        $objects = [];
        $objCount = 0;

        // 1: Catalog
        $objCount++;
        $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";

        // 2: Pages root
        $objCount++;
        $kids = [];
        for ($i = 1; $i <= $totalPageCount; $i++) {
            $kids[] = (2 + $i) . " 0 R";
        }
        $objects[2] = "<< /Type /Pages /Kids [" . implode(' ', $kids) . "] /Count " . $totalPageCount . " >>";

        // Pages 3..(2 + totalPageCount)
        $contentObjStart = 2 + $totalPageCount + 1;
        for ($i = 1; $i <= $totalPageCount; $i++) {
            $pageObjId = 2 + $i;
            $contentObjId = $contentObjStart + $i - 1;
            $objects[$pageObjId] = sprintf(
                "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2f %.2f] /Contents %d 0 R /Resources << /Font << /F1 %d 0 R /F2 %d 0 R /F3 %d 0 R >> /ProcSet [/PDF /Text] >> >>",
                $this->pageWidth,
                $this->pageHeight,
                $contentObjId,
                $contentObjStart + $totalPageCount,
                $contentObjStart + $totalPageCount + 1,
                $contentObjStart + $totalPageCount + 2
            );
        }

        // Content Streams
        for ($i = 1; $i <= $totalPageCount; $i++) {
            $cId = $contentObjStart + $i - 1;
            $stream = $this->pages[$i];
            $objects[$cId] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream";
        }

        // Font 1: Standard Helvetica
        $f1Id = $contentObjStart + $totalPageCount;
        $objects[$f1Id] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";

        // Font 2: Standard Helvetica-Bold
        $f2Id = $contentObjStart + $totalPageCount + 1;
        $objects[$f2Id] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";

        // Font 3: Type0 CIDFont STSong-Light with UniGB-UTF16-H (Universal CJK + Latin Unicode support)
        $f3Id = $contentObjStart + $totalPageCount + 2;
        $cidFontId = $f3Id + 1;
        $objects[$f3Id] = sprintf(
            "<< /Type /Font /Subtype /Type0 /BaseFont /STSong-Light /Encoding /UniGB-UTF16-H /DescendantFonts [%d 0 R] >>",
            $cidFontId
        );

        $objects[$cidFontId] = "<< /Type /Font /Subtype /CIDFontType0 /BaseFont /STSong-Light /CIDSystemInfo << /Registry (Adobe) /Ordering (GB1) /Supplement 4 >> /DW 1000 >>";

        // Assemble PDF
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $xref = [];
        $xref[0] = "0000000000 65535 f \n";

        $totalObjs = count($objects);
        for ($id = 1; $id <= $totalObjs; $id++) {
            $offset = strlen($pdf);
            $xref[$id] = sprintf("%010d 00000 n \n", $offset);
            $pdf .= $id . " 0 obj\n" . $objects[$id] . "\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n";
        $pdf .= "0 " . ($totalObjs + 1) . "\n";
        for ($id = 0; $id <= $totalObjs; $id++) {
            $pdf .= $xref[$id];
        }
        $pdf .= "trailer\n";
        $pdf .= sprintf("<< /Size %d /Root 1 0 R >>\n", $totalObjs + 1);
        $pdf .= "startxref\n";
        $pdf .= $xrefOffset . "\n";
        $pdf .= "%%EOF\n";

        return $pdf;
    }

    /**
     * Generate the complete executive report from summary data and metadata
     */
    public static function generate(array $data, array $metadata): string {
        $report = new self();
        $summaryLang = $metadata['summary_language'] ?? 'Chinese';

        // 1. Executive Top Header Banner
        $report->setFillColor(30, 41, 59); // Deep Slate Navy
        $report->drawRect($report->marginLeft, 35, $report->getContentWidth(), 56, 'F');

        // Header Accent Stripe
        $report->setFillColor(37, 99, 235); // Royal Blue
        $report->drawRect($report->marginLeft, 35, $report->getContentWidth(), 4, 'F');

        // Header Title
        $report->setFont('STSong', 'B', 15);
        $report->setTextColor(255, 255, 255);
        $report->text($report->marginLeft + 16, 58, "AI Voice Recording Summarizer");

        // Header Subtitle
        $report->setFont('STSong', '', 9);
        $report->setTextColor(203, 213, 225);
        $report->text($report->marginLeft + 16, 76, "Executive Intelligence Report  •  Multimodal AI Analysis");

        $report->cursorY = 106.0;

        // 2. Metadata Information Box
        $boxHeight = 68.0;
        $report->setFillColor(248, 250, 252); // Light background
        $report->setDrawColor(226, 232, 240); // Border
        $report->drawRect($report->marginLeft, $report->cursorY, $report->getContentWidth(), $boxHeight, 'FD');

        // Left vertical blue accent bar
        $report->setFillColor(37, 99, 235);
        $report->drawRect($report->marginLeft, $report->cursorY, 4, $boxHeight, 'F');

        // Metadata Text
        $col1X = $report->marginLeft + 16;
        $col2X = $report->marginLeft + 270;
        $yBase = $report->cursorY + 18;

        $report->setFont('STSong', 'B', 9);
        $report->setTextColor(71, 85, 105);
        $report->text($col1X, $yBase, "Recording: ");
        $report->setFont('STSong', '', 9);
        $report->setTextColor(15, 23, 42);
        $origName = Security::sanitizeFilename($metadata['original_name'] ?? 'recording.mp3');
        $report->text($col1X + 60, $yBase, mb_strimwidth($origName, 0, 36, '...'));

        $report->setFont('STSong', 'B', 9);
        $report->setTextColor(71, 85, 105);
        $report->text($col2X, $yBase, "Duration: ");
        $report->setFont('STSong', '', 9);
        $report->setTextColor(15, 23, 42);
        $report->text($col2X + 55, $yBase, ($metadata['duration'] ?? 'N/A') . "  •  Size: " . ($metadata['file_size'] ?? 'N/A'));

        $report->setFont('STSong', 'B', 9);
        $report->setTextColor(71, 85, 105);
        $report->text($col1X, $yBase + 18, "Languages: ");
        $report->setFont('STSong', '', 9);
        $report->setTextColor(15, 23, 42);
        $report->text($col1X + 60, $yBase + 18, "Audio: " . ($metadata['detected_language'] ?? 'Auto') . "  ➜  Summary: " . $summaryLang);

        $report->setFont('STSong', 'B', 9);
        $report->setTextColor(71, 85, 105);
        $report->text($col2X, $yBase + 18, "Generated: ");
        $report->setFont('STSong', '', 9);
        $report->setTextColor(15, 23, 42);
        $report->text($col2X + 55, $yBase + 18, $metadata['processed_at'] ?? date('Y-m-d H:i:s'));

        $report->cursorY += $boxHeight + 20.0;

        // Titles localized based on Summary Language
        $titles = self::getSectionTitles($summaryLang);

        // Section 1: Overall Summary
        $report->renderSectionHeader("1. " . $titles['overall_summary']);
        $report->setFont('STSong', '', 9.5);
        $report->setTextColor(51, 65, 85);
        $overall = !empty($data['overall_summary']) ? $data['overall_summary'] : 'No overall summary available.';
        $report->multiCell($report->getContentWidth(), 14.5, $overall);
        $report->cursorY += 10.0;

        // Section 2: Main Topics Discussed
        $report->renderSectionHeader("2. " . $titles['main_topics']);
        $topics = $data['main_topics'] ?? [];
        if (!empty($topics)) {
            foreach ($topics as $topic) {
                $report->bulletItem($topic, "• ");
            }
        } else {
            $report->renderEmptyState($summaryLang === 'Chinese' ? '未提取到特定议题。' : 'No distinct topics listed.');
        }
        $report->cursorY += 10.0;

        // Section 3: Important Points
        $report->renderSectionHeader("3. " . $titles['important_points']);
        $points = $data['important_points'] ?? [];
        if (!empty($points)) {
            foreach ($points as $point) {
                $report->bulletItem($point, "▸ ");
            }
        } else {
            $report->renderEmptyState($summaryLang === 'Chinese' ? '未记录重点陈述。' : 'No important points listed.');
        }
        $report->cursorY += 10.0;

        // Section 4: Key Metrics, References & Information
        $report->renderSectionHeader("4. " . $titles['key_metrics']);
        $metrics = $data['key_metrics'] ?? [];
        if (!empty($metrics)) {
            foreach ($metrics as $metric) {
                $report->bulletItem($metric, "▪ ");
            }
        } else {
            $report->renderEmptyState($summaryLang === 'Chinese' ? '录音中未明确提及特定数据指标或参考信息。' : 'No key metrics or specific references mentioned.');
        }
        $report->cursorY += 10.0;

        // Section 5: Decisions & Conclusions
        $report->renderSectionHeader("5. " . $titles['decisions']);
        $decisions = $data['decisions'] ?? [];
        if (!empty($decisions)) {
            foreach ($decisions as $decision) {
                $report->bulletItem($decision, "✓ ");
            }
        } else {
            $emptyMsg = match($summaryLang) {
                'English' => 'No formal decisions or final conclusions were explicitly stated in this recording.',
                'Malay' => 'Tiada keputusan rasmi atau kesimpulan akhir dinyatakan dalam rakaman ini.',
                default => '本录音中未明确提及正式决策或最终结论。'
            };
            $report->renderEmptyState($emptyMsg);
        }
        $report->cursorY += 10.0;

        // Section 6: Action Items & Next Steps
        $report->renderSectionHeader("6. " . $titles['action_items']);
        $actions = $data['action_items'] ?? [];
        if (!empty($actions)) {
            foreach ($actions as $item) {
                $task = is_array($item) ? ($item['task'] ?? '') : (string)$item;
                $assignee = is_array($item) ? ($item['assignee'] ?? '') : '';
                $deadline = is_array($item) ? ($item['deadline'] ?? '') : '';

                $itemText = $task;
                if ($assignee !== '' && $assignee !== 'Not specified') {
                    $itemText .= "  [Assignee: " . $assignee . "]";
                }
                if ($deadline !== '' && $deadline !== 'Not specified') {
                    $itemText .= "  [Due: " . $deadline . "]";
                }
                $report->bulletItem($itemText, "❑ ");
            }
        } else {
            $emptyMsg = match($summaryLang) {
                'English' => 'No specific action items or responsibilities were identified in this recording.',
                'Malay' => 'Tiada tindakan susulan atau tanggungjawab khusus dikenal pasti dalam rakaman ini.',
                default => '本录音中未明确指派具体的后续待办事项。'
            };
            $report->renderEmptyState($emptyMsg);
        }
        $report->cursorY += 10.0;

        // Section 7: High-Level Synthesis
        $report->renderSectionHeader("7. " . $titles['high_level_synthesis']);
        $synthesis = !empty($data['high_level_synthesis']) ? $data['high_level_synthesis'] : 'No high-level synthesis provided.';
        $report->setFont('STSong', '', 9.5);
        $report->setTextColor(51, 65, 85);
        $report->multiCell($report->getContentWidth(), 14.5, $synthesis);
        $report->cursorY += 15.0;

        // Save PDF to storage/temp/
        $reportId = bin2hex(random_bytes(16));
        $filename = "report_{$reportId}.pdf";
        $filePath = TEMP_PATH . DIRECTORY_SEPARATOR . $filename;

        $pdfContent = $report->output();
        file_put_contents($filePath, $pdfContent, LOCK_EX);

        return $filename;
    }

    /**
     * Render Section Heading with Blue underline/pill
     */
    private function renderSectionHeader(string $title): void {
        $this->checkPageBreak(38.0);

        // Section title text
        $this->setFont('STSong', 'B', 11.5);
        $this->setTextColor(30, 64, 175); // Royal Blue
        $this->text($this->marginLeft, $this->cursorY + 12, $title);

        // Underline accent
        $this->setDrawColor(226, 232, 240);
        $this->drawLine($this->marginLeft, $this->cursorY + 18, $this->pageWidth - $this->marginRight, $this->cursorY + 18);
        $this->setDrawColor(37, 99, 235);
        $this->drawLine($this->marginLeft, $this->cursorY + 18, $this->marginLeft + 60, $this->cursorY + 18);

        $this->cursorY += 26.0;
    }

    /**
     * Render Empty State Message Box
     */
    private function renderEmptyState(string $message): void {
        $this->checkPageBreak(25.0);
        $this->setFont('STSong', '', 9);
        $this->setTextColor(148, 163, 184); // Slate 400 italic style
        $this->multiCell($this->getContentWidth(), 13.0, "- " . $message);
        $this->cursorY += 4.0;
    }

    /**
     * Localized Section Titles
     */
    private static function getSectionTitles(string $lang): array {
        if ($lang === 'Chinese') {
            return [
                'overall_summary' => '整体概述 (Overall Summary)',
                'main_topics' => '讨论要点 (Main Topics Discussed)',
                'important_points' => '重要陈述与论点 (Important Points)',
                'key_metrics' => '关键指标与参考信息 (Key Metrics, References & Information)',
                'decisions' => '决策与结论 (Decisions & Conclusions)',
                'action_items' => '待办事项与后续行动 (Action Items & Next Steps)',
                'high_level_synthesis' => '高阶综述与战略总结 (High-Level Synthesis)'
            ];
        } elseif ($lang === 'Malay') {
            return [
                'overall_summary' => 'Ringkasan Keseluruhan (Overall Summary)',
                'main_topics' => 'Topik Utama Dibincangkan (Main Topics Discussed)',
                'important_points' => 'Perkara-perkara Penting (Important Points)',
                'key_metrics' => 'Metrik Utama, Rujukan & Maklumat (Key Metrics & References)',
                'decisions' => 'Keputusan & Kesimpulan (Decisions & Conclusions)',
                'action_items' => 'Tindakan & Langkah Seterusnya (Action Items & Next Steps)',
                'high_level_synthesis' => 'Sintesis Peringkat Tinggi (High-Level Synthesis)'
            ];
        } else {
            return [
                'overall_summary' => 'Overall Summary',
                'main_topics' => 'Main Topics Discussed',
                'important_points' => 'Important Points',
                'key_metrics' => 'Key Metrics, References & Information',
                'decisions' => 'Decisions & Conclusions',
                'action_items' => 'Action Items & Next Steps',
                'high_level_synthesis' => 'High-Level Synthesis'
            ];
        }
    }
}
