<?php

use App\Domain\Gst\Returns\OfficialGstr1CsvExporter;
use Database\Seeders\StateSeeder;

it('matches fixed golden CSV output for every exported GSTR-1 section', function () {
    config(['gst_returns.rules.document_issued_categories.verified' => true]);
    (new StateSeeder)->run();
    $dataset = require __DIR__.'/../Support/gstr1-csv-golden-dataset.php';
    $files = app(OfficialGstr1CsvExporter::class)->export($dataset);
    foreach ($files as $filename => $csv) {
        $expected = file_get_contents(__DIR__.'/../Fixtures/gst-returns/'.$filename);
        $parse = fn (string $contents) => array_map('str_getcsv', preg_split('/\r\n|\n|\r/', trim($contents)));
        expect($parse($csv))->toBe($parse($expected));
    }
});
