<?php
// Minimal PDF writer for report downloads and email attachments: text, filled
// boxes and auto-paginating tables in the built-in Helvetica font. No libraries.

class SimplePdf
{
    private array $pages = [];
    private string $cur = '';
    public float $w;
    public float $h;
    public float $y = 0;
    public float $margin = 36;
    private string $footer;

    // Glyph widths (1/1000 em) for ASCII 32..126 from the standard Helvetica metrics
    private const W = [
        'F1' => [278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,1015,667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,333,556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,556,556,333,500,278,556,500,722,500,500,500,334,260,334,584],
        'F2' => [278,333,474,556,556,889,722,238,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,333,333,584,584,584,611,975,722,722,722,722,667,611,778,722,278,556,722,611,833,722,778,667,778,722,667,611,722,667,944,667,667,611,333,278,333,584,556,333,556,611,556,611,556,333,611,611,278,278,556,278,889,611,611,611,611,389,556,333,611,556,778,556,556,500,389,280,389,584],
    ];

    public function __construct(bool $landscape = false, string $footer = '')
    {
        [$this->w, $this->h] = $landscape ? [792, 612] : [612, 792]; // US Letter
        $this->footer = $footer;
        $this->addPage();
    }

    public function addPage(): void
    {
        if ($this->cur !== '') {
            $this->pages[] = $this->cur;
        }
        $this->cur = '';
        $this->y = $this->margin;
        if ($this->footer !== '') {
            $n = count($this->pages) + 1;
            $this->text($this->margin, $this->h - 20, $this->footer, 8, false, [0.45, 0.47, 0.6]);
            $label = 'Page ' . $n;
            $this->text($this->w - $this->margin - $this->width($label, 8), $this->h - 20, $label, 8, false, [0.45, 0.47, 0.6]);
        }
    }

    /** Make sure $need points fit on this page; start a new one otherwise. */
    public function ensure(float $need): bool
    {
        if ($this->y + $need > $this->h - $this->margin - 10) {
            $this->addPage();
            return true;
        }
        return false;
    }

    private static function enc(string $s): string
    {
        $s = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $s);
        return $s === false ? '' : $s;
    }

    public function width(string $s, float $size, bool $bold = false): float
    {
        $table = self::W[$bold ? 'F2' : 'F1'];
        $w = 0;
        foreach (str_split(self::enc($s)) as $ch) {
            $o = ord($ch);
            $w += ($o >= 32 && $o <= 126) ? $table[$o - 32] : 556;
        }
        return $w * $size / 1000;
    }

    /** Shorten text with "..." so it fits in $max points. */
    public function fit(string $s, float $max, float $size, bool $bold = false): string
    {
        if ($this->width($s, $size, $bold) <= $max) {
            return $s;
        }
        while ($s !== '' && $this->width($s . '...', $size, $bold) > $max) {
            $s = mb_substr($s, 0, -1);
        }
        return $s . '...';
    }

    /** Text with its top-left corner at x, y (y measured from the top of the page). */
    public function text(float $x, float $y, string $s, float $size = 10, bool $bold = false, array $rgb = [0.12, 0.14, 0.25]): void
    {
        $esc = strtr(self::enc($s), ['\\' => '\\\\', '(' => '\\(', ')' => '\\)', "\r" => '', "\n" => ' ']);
        $this->cur .= sprintf("%.3F %.3F %.3F rg BT /%s %.1F Tf %.2F %.2F Td (%s) Tj ET\n",
            $rgb[0], $rgb[1], $rgb[2], $bold ? 'F2' : 'F1', $size, $x, $this->h - $y - $size * 0.8, $esc);
    }

    public function rect(float $x, float $y, float $w, float $h, array $rgb): void
    {
        $this->cur .= sprintf("%.3F %.3F %.3F rg %.2F %.2F %.2F %.2F re f\n", $rgb[0], $rgb[1], $rgb[2], $x, $this->h - $y - $h, $w, $h);
    }

    public function hline(float $y, array $rgb = [0.88, 0.89, 0.94]): void
    {
        $this->rect($this->margin, $y, $this->w - 2 * $this->margin, 0.6, $rgb);
    }

    /**
     * Table across the page. $cols = [[label, relative width], ...]; rows are arrays of strings.
     * $rowColor(row) may return an RGB for that row's last cell (e.g. status colors).
     */
    public function table(array $cols, array $rows, ?callable $rowColor = null): void
    {
        $total = array_sum(array_column($cols, 1));
        $avail = $this->w - 2 * $this->margin;
        $widths = array_map(fn($c) => $c[1] / $total * $avail, $cols);
        $header = function () use ($cols, $widths) {
            $this->rect($this->margin, $this->y, $this->w - 2 * $this->margin, 18, [0.42, 0.36, 0.91]);
            $x = $this->margin + 5;
            foreach ($cols as $i => [$label]) {
                $this->text($x, $this->y + 5, $this->fit(strtoupper($label), $widths[$i] - 8, 7.5, true), 7.5, true, [1, 1, 1]);
                $x += $widths[$i];
            }
            $this->y += 18;
        };
        $header();
        foreach ($rows as $n => $row) {
            if ($this->ensure(16)) {
                $header();
            }
            if ($n % 2 === 1) {
                $this->rect($this->margin, $this->y, $this->w - 2 * $this->margin, 16, [0.965, 0.965, 0.985]);
            }
            $x = $this->margin + 5;
            foreach (array_values($row) as $i => $cell) {
                $color = ($rowColor && $i === count($row) - 1) ? $rowColor($row) : [0.12, 0.14, 0.25];
                $this->text($x, $this->y + 4, $this->fit((string) $cell, $widths[$i] - 8, 8.5), 8.5, false, $color);
                $x += $widths[$i];
            }
            $this->y += 16;
        }
        if (!$rows) {
            $this->text($this->margin + 5, $this->y + 5, 'No entries.', 9, false, [0.45, 0.47, 0.6]);
            $this->y += 18;
        }
        $this->y += 10;
    }

    public function heading(string $s): void
    {
        $this->ensure(40);
        $this->text($this->margin, $this->y, $s, 12, true);
        $this->y += 18;
    }

    public function output(): string
    {
        $pages = array_merge($this->pages, [$this->cur]);
        $objs = [];
        $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objs[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objs[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $kids = [];
        $next = 5;
        foreach ($pages as $content) {
            $pageId = $next++;
            $contentId = $next++;
            $kids[] = "$pageId 0 R";
            $objs[$pageId] = sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %d %d] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>', $this->w, $this->h, $contentId);
            $objs[$contentId] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "endstream";
        }
        $objs[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($kids) . ' >>';
        ksort($objs);
        $out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objs as $id => $body) {
            $offsets[$id] = strlen($out);
            $out .= "$id 0 obj\n$body\nendobj\n";
        }
        $xref = strlen($out);
        $out .= "xref\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
        foreach ($offsets as $off) {
            $out .= sprintf("%010d 00000 n \n", $off);
        }
        return $out . "trailer\n<< /Size " . (count($objs) + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";
    }
}

function pdf_title(SimplePdf $pdf, array $t, string $title, string $subtitle): void
{
    $pdf->rect(0, 0, $pdf->w, 74, [0.42, 0.36, 0.91]);
    $pdf->text($pdf->margin, 16, $t['name'], 10, false, [0.9, 0.9, 1]);
    $pdf->text($pdf->margin, 30, $title, 18, true, [1, 1, 1]);
    $pdf->text($pdf->margin, 54, $subtitle, 9, false, [0.9, 0.9, 1]);
    $pdf->y = 92;
}

/** Daily attendance sheet: who came, dropped off by, in, picked up by, out, status. */
function pdf_attendance(array $t, string $type, string $date, array $rows, array $count): string
{
    $pdf = new SimplePdf(true, $t['name'] . ' · ' . cfg('app_name') . ' · printed ' . tenant_now()->format('M j, Y g:i A'));
    pdf_title($pdf, $t, term($type === 'teacher' ? 'b1' : ($type === 'visitor' ? 'v1' : 'a1'), $t) . ' attendance', fmt_date($date));
    $pdf->text($pdf->margin, $pdf->y, sprintf('Checked in: %d    Checked out: %d    Not checked out: %d    Absent: %d', $count['present'], $count['out'], $count['in'], $count['absent']), 10, true);
    $pdf->y += 22;
    $label = ['in' => 'Not checked out', 'out' => 'Checked out', 'absent' => 'Absent'];
    $data = [];
    foreach ($rows as $r) {
        $dur = $r['minutes'] !== null ? fmt_minutes($r['minutes']) : '';
        $data[] = $type === 'teacher'
            ? [$r['name'], fmt_time($r['in']), fmt_time($r['out']), $dur, $label[$r['status']]]
            : [$r['name'], (string) $r['grade'], (string) $r['in_by'], fmt_time($r['in']), (string) $r['out_by'], fmt_time($r['out']), $dur, $label[$r['status']]];
    }
    if ($type === 'visitor') {
        $data = array_map(fn($r) => [$r['name'], (string) $r['in_by'], (string) $r['grade'], fmt_time($r['in']), fmt_time($r['out']),
            $r['minutes'] !== null ? fmt_minutes($r['minutes']) : '', $r['status'] === 'out' ? 'Checked out' : 'Not checked out'], $rows);
    }
    $cols = $type === 'visitor'
        ? [[term('v1', $t), 3.2], ['Company', 2.6], ['Visiting', 2.6], ['Sign-in', 1.6], ['Sign-out', 1.6], ['Time', 1.5], ['Status', 2.2]]
        : ($type === 'teacher'
        ? [[term('b1', $t), 4], ['Check-in', 2], ['Check-out', 2], ['Hours', 2], ['Status', 2.4]]
        : [[term('a1', $t), 3.2], ['Group', 1.4], ['Came with', 3], ['Check-in', 1.6], ['Left with', 3], ['Check-out', 1.6], ['Time', 1.5], ['Status', 2.2]]);
    $pdf->table($cols, $data, function ($row) {
        $s = end($row);
        return $s === 'Checked out' ? [0.03, 0.48, 0.36] : ($s === 'Absent' ? [0.45, 0.47, 0.6] : [0.75, 0.42, 0]);
    });
    return $pdf->output();
}

/** Daily / weekly / monthly summary as a PDF. */
function pdf_report(array $t, string $title, array $r): string
{
    $pdf = new SimplePdf(false, $t['name'] . ' · ' . cfg('app_name') . ' · printed ' . tenant_now()->format('M j, Y g:i A'));
    pdf_title($pdf, $t, $title, fmt_date($r['from']) . ($r['from'] !== $r['to'] ? ' – ' . fmt_date($r['to']) : ''));
    $tiles = [
        [term('a1', $t) . ' sign-ins', $r['totals']['student_in'], [0.42, 0.36, 0.91]],
        [term('a1', $t) . ' sign-outs', $r['totals']['student_out'], [0.04, 0.52, 0.89]],
        [term('b1', $t) . ' sign-ins', $r['totals']['teacher_in'], [0, 0.72, 0.58]],
        ['Material pickups', $r['totals']['pickups'], [0.88, 0.44, 0.33]],
    ];
    $tw = ($pdf->w - 2 * $pdf->margin - 30) / 4;
    foreach ($tiles as $i => [$label, $num, $rgb]) {
        $x = $pdf->margin + $i * ($tw + 10);
        $pdf->rect($x, $pdf->y, $tw, 52, $rgb);
        $pdf->text($x + 10, $pdf->y + 9, (string) $num, 20, true, [1, 1, 1]);
        $pdf->text($x + 10, $pdf->y + 35, $label, 8.5, false, [1, 1, 1]);
    }
    $pdf->y += 66;
    $pdf->text($pdf->margin, $pdf->y, term('a2', $t) . ': ' . $r['unique_students'] . '     ' . term('b2', $t) . ': ' . $r['unique_teachers'], 10);
    $pdf->y += 24;
    if (count($r['by_day']) > 1) {
        $pdf->heading('By day');
        $rows = [];
        foreach ($r['by_day'] as $day => $v) {
            $rows[] = [date('D, M j', strtotime($day)), $v['student_in'], $v['student_out'], $v['teacher_in'], $v['pickups']];
        }
        $pdf->table([['Day', 3], [term('a2', $t) . ' in', 2], [term('a2', $t) . ' out', 2], [term('b2', $t) . ' in', 2], ['Pickups', 2]], $rows);
    }
    if ($r['teacher_minutes']) {
        $pdf->heading(term('b1', $t) . ' hours');
        $rows = [];
        foreach ($r['teacher_minutes'] as $name => $m) {
            $rows[] = [$name, fmt_minutes($m)];
        }
        $pdf->table([[term('b1', $t), 4], ['Hours', 2]], $rows);
    }
    $pdf->heading('Signed in but never signed out (' . count($r['not_signed_out']) . ')');
    $rows = array_map(fn($ev) => [$ev['person_name'], ucfirst($ev['person_type']), date('D, M j', strtotime($ev['event_date'])), fmt_time($ev['event_time'])], $r['not_signed_out']);
    $pdf->table([['Name', 4], ['Type', 2], ['Day', 2.5], ['Signed in', 2]], $rows);
    $pdf->heading('Material pickups (' . count($r['pickups']) . ')');
    $rows = array_map(fn($ev) => [date('M j', strtotime($ev['event_date'])) . ' ' . fmt_time($ev['event_time']), $ev['person_name'], (string) $ev['guardian_name'], (string) $ev['materials']], $r['pickups']);
    $pdf->table([['When', 2], [term('a1', $t), 3], ['Picked up by', 3], ['Items', 5]], $rows);
    return $pdf->output();
}
