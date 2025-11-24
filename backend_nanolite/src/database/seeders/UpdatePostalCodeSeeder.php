<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Laravolt\Indonesia\Models\Village;
use League\Csv\Reader;

class UpdatePostalCodeSeeder extends Seeder
{
    public function run()
    {
        $csvPath = database_path('seeders/data_pos/indonesia_postal_codes.csv');

        if (!file_exists($csvPath)) {
            $this->command->error("CSV file not found at: $csvPath");
            return;
        }

        $csv = Reader::createFromPath($csvPath, 'r');
        $csv->setHeaderOffset(0);

        foreach ($csv->getRecords() as $record) {
            $postalCode = trim($record['zip']);
            $villageCode = trim($record['id']);

            if (!$villageCode || !$postalCode) continue;

            $village = Village::where('code', $villageCode)->first();

            if ($village) {
                $village->postal_code = $postalCode;
                $village->save();
                $this->command->info("Updated {$village->name} with postal code {$postalCode}");
            }
        }

        $this->command->info('Seeder selesai.');
    }
}
