<?php

namespace App\Services\Batch;

class BatchParserService
{

    public function parseCSV($file): array
    {
        $items = [];
        $handle = fopen($file->getPathname(), 'r');
        fgetcsv($handle);

        while (($row = fgetcsv($handle)) !== false) {
            $items[] = [
                'beneficiary_name'   => $row[0],
                'account_number'     => $row[1],
                'bank_code'          => $row[2],
                'amount'             => $row[3],
                'narration'          => $row[4],
                'external_reference' => $row[5],
            ];
        }

        fclose($handle);
        return $items;
    }
}
