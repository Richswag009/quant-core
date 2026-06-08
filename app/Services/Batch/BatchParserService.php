<?php

namespace App\Services\Batch;

class BatchParserService
{

    public function parseCSV($file, int $maxRows = 10000): array
    {
        $items = [];
        $handle = fopen($file->getPathname(), 'r');
        $header = fgetcsv($handle);
        $expectedColumns = ['beneficiary_name', 'account_number', 'bank_code', "amount", 'narration', 'external_reference'];
        if ($header !== $expectedColumns) {
            throw new \InvalidArgumentException('CSV header mismatch');
        }
        $rowNum = 1;
        while (($row = fgetcsv($handle)) !== false) {
            if (++$rowNum > $maxRows) {
                throw new \InvalidArgumentException('CSV exceeds max rows');
            }

            if (count($row) !== count($expectedColumns)) {
                throw new \InvalidArgumentException("Row $rowNum: column count mismatch");
            }

            $items[] = array_combine($expectedColumns, $row);
        }

        if (empty($items)) {
            throw new \InvalidArgumentException('CSV is empty');
        }

        return $items;
    }
}
