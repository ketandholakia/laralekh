<?php

// PDF text helpers for DomPDF output assertions. DomPDF emits one glyph per
// text-show operator, so extracted text has spaces between every character;
// comparisons run on the space-stripped form.

function pdfText(string $bytes): string
{
    $text = '';
    if (! str_starts_with($bytes, '%PDF')) {
        return '';
    }
    preg_match_all('/stream\r?\n(.*?)endstream/s', $bytes, $streams);
    foreach ($streams[1] as $stream) {
        $decoded = @gzuncompress($stream);
        if ($decoded === false) {
            $decoded = @gzdecode($stream);
        }
        if (! is_string($decoded) || ! str_contains($decoded, 'BT')) {
            continue;
        }
        preg_match_all('/\(((?:\\\\.|[^\\\\()])*)\)/', $decoded, $fragments);
        $text .= implode('', array_map(
            fn (string $f): string => str_replace(
                ['\\(', '\\)', '\\\\', "\x00"],
                ['(', ')', '\\', ''],
                $f,
            ),
            $fragments[1],
        )).' ';
    }

    return $text;
}

function pdfHas(string $bytes, string $needle): bool
{
    return str_contains(str_replace(' ', '', pdfText($bytes)), str_replace(' ', '', $needle));
}

function pdfPageCount(string $bytes): int
{
    return (int) preg_match_all('/\/Type\s*\/Page[^s]/', $bytes);
}
