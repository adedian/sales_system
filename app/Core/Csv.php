<?php

namespace App\Core;

/**
 * Phase 13 — streams a report's filtered rows as CSV (opens fine in Excel),
 * gated separately from report.view by the report.export permission. No
 * temp file, no library — fputcsv straight to php://output like the rest
 * of this app avoids on-disk artifacts (same spirit as dompdf's stream()).
 */
class Csv
{
    /**
     * @param array<string,string> $columns key (row array key) => column header label
     * @param array<int,array<string,mixed>> $rows
     */
    public static function download(string $filename, array $columns, array $rows): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel doesn't mangle non-ASCII text
        fputcsv($out, array_values($columns));

        foreach ($rows as $row) {
            $line = [];
            foreach (array_keys($columns) as $key) {
                $line[] = $row[$key] ?? '';
            }
            fputcsv($out, $line);
        }

        fclose($out);
        exit;
    }
}
