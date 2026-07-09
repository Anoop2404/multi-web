<?php

namespace App\Services\Spreadsheet;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

class SpreadsheetWriter
{
    /** @param  list<list<string>>  $rows */
    public static function xlsx(array $rows): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');

        $writer = new Writer;

        try {
            $writer->openToFile($tmp);

            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues($row));
            }

            $writer->close();

            return (string) file_get_contents($tmp);
        } finally {
            @unlink($tmp);
        }
    }
}
