<?php
declare(strict_types=1);
namespace App\Domain\Gst\Returns;

/** Contract only. No implementation until an official schema/sample is checked in. */
interface Gstr1JsonExporter
{
    public function export(array $dataset):string;
}
