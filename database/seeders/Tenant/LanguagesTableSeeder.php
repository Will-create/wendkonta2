<?php

namespace Database\Seeders\Tenant;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LanguagesTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {

        if (DB::table('languages')->count() > 0) {

            $lang = DB::table('languages')->orderBy('id')->get(); // Fetch all languages ordered by ID

            $first = $lang->first(); // Get the first record from the collection
            $default = $lang->where('is_default', 1)->first(); // Check if there's already a default language

            if (!$default && $first) {
                // Set all is_default = 0
                DB::table('languages')->update(['is_default' => 0]);

                // Set is_default = 1 for the first row
                DB::table('languages')->where('id', $first->id)->update(['is_default' => 1]);
            }

        }else{

            DB::table('languages')->insert(array (
                0 =>
                array (
                    'id' => 1,
                    'language' => 'en',
                    'created_at' => '2025-02-15 19:31:18',
                    'updated_at' => '2025-02-23 14:24:00',
                    'name' => 'English',
                    'is_default' => 1,
                ),
                1 =>
                array (
                    'id' => 2,
                    'language' => 'bn',
                    'created_at' => '2025-02-15 19:31:36',
                    'updated_at' => '2025-02-23 14:24:00',
                    'name' => 'Bangla',
                    'is_default' => 0,
                ),
                2 =>
                array (
                    'id' => 3,
                    'language' => 'ar',
                    'created_at' => '2025-02-16 11:54:58',
                    'updated_at' => '2025-02-23 14:24:00',
                    'name' => 'Arabic',
                    'is_default' => 0,
                ),
                3 =>
                array (
                    'id' => 4,
                    'language' => 'al',
                    'created_at' => '2025-02-20 19:07:34',
                    'updated_at' => '2025-02-23 14:24:00',
                    'name' => 'Albania',
                    'is_default' => 0,
                ),
                4 =>
                array (
                    'id' => 5,
                    'language' => 'az',
                    'created_at' => '2025-02-23 10:43:59',
                    'updated_at' => '2025-02-23 14:24:00',
                    'name' => 'Azerbaijan',
                    'is_default' => 0,
                ),
                5 =>
                array (
                    'id' => 6,
                    'language' => 'bg',
                    'created_at' => '2025-02-23 10:52:01',
                    'updated_at' => '2025-02-23 14:24:00',
                    'name' => 'Bulgaria',
                    'is_default' => 0,
                ),
                6 =>
                array (
                    'id' => 7,
                    'language' => 'de',
                    'created_at' => '2025-02-23 11:04:53',
                    'updated_at' => '2025-02-23 14:24:00',
                    'name' => 'Germany',
                    'is_default' => 0,
                ),
                7 =>
                array (
                    'id' => 8,
                    'language' => 'es',
                    'created_at' => '2025-02-23 11:10:30',
                    'updated_at' => '2025-02-23 14:24:00',
                    'name' => 'Spanish',
                    'is_default' => 0,
                ),
                8 =>
                array (
                    'id' => 9,
                    'language' => 'fr',
                    'created_at' => '2025-02-23 14:12:28',
                    'updated_at' => '2025-02-23 14:24:00',
                    'name' => 'French',
                    'is_default' => 0,
                ),
                9 =>
                array (
                    'id' => 10,
                    'language' => 'id',
                    'created_at' => '2025-02-23 14:13:28',
                    'updated_at' => '2025-02-23 14:24:00',
                    'name' => 'Indonesian',
                    'is_default' => 0,
                ),
                10 =>
                array (
                    'id' => 11,
                    'language' => 'tr',
                    'created_at' => '2025-03-20 12:55:53',
                    'updated_at' => '2025-03-20 12:55:53',
                    'name' => 'Turkish',
                    'is_default' => 0,
                ),
                11 =>
                array (
                    'id' => 12,
                    'language' => 'vi',
                    'created_at' => '2025-03-24 16:49:54',
                    'updated_at' => '2025-03-24 16:49:54',
                    'name' => 'Vietnamese',
                    'is_default' => 0,
                ),
                12 =>
                array (
                    'id' => 13,
                    'language' => 'pt',
                    'created_at' => '2025-03-24 16:49:54',
                    'updated_at' => '2025-03-24 16:49:54',
                    'name' => 'Portuguese',
                    'is_default' => 0,
                ),
                13 =>
                array (
                    'id' => 14,
                    'language' => 'ms',
                    'created_at' => '2025-03-24 16:49:54',
                    'updated_at' => '2025-03-24 16:49:54',
                    'name' => 'Malay',
                    'is_default' => 0,
                ),
                14 =>
                array (
                    'id' => 15,
                    'language' => 'sr',
                    'created_at' => '2025-03-24 16:49:54',
                    'updated_at' => '2025-03-24 16:49:54',
                    'name' => 'Serbian',
                    'is_default' => 0,
                ),
            ));
        }

    }
}
