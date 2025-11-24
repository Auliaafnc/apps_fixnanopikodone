<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use League\Csv\Reader;

class PostalCodesSeeder extends Seeder
{
    public function run(): void
    {
        $csvPath = database_path('seeders/data_pos/indonesia_postal_codes.csv');

        if (!file_exists($csvPath)) {
            $this->command->error("CSV file not found at: $csvPath");
            return;
        }

        $csv = Reader::createFromPath($csvPath, 'r');
        $csv->setHeaderOffset(0);

        foreach ($csv->getRecords() as $record) {
            $villageCode = str_replace('.', '', trim($record['id']));
            $villageName = trim($record['nm']);
            $postalCode  = trim($record['zip']);

            if (!$villageCode || !$postalCode) continue;

            DB::table('postal_codes')->updateOrInsert(
                ['village_code' => $villageCode],
                [
                    'village_name' => $villageName,
                    'postal_code'  => $postalCode,
                ]
            );
        }

        $this->command->info('PostalCodesSeeder selesai.');
    }
}
