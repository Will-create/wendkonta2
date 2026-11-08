<?php

use App\Models\Translation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $map = require database_path('translations/fr_interface.php');
        $force = ['Cheque', 'Credit Card', 'Delivering', 'POS', 'submit'];
        $now = now();

        foreach ($map as $key => $values) {
            foreach (['en' => $values['en'], 'fr' => $values['fr']] as $locale => $value) {
                $existing = DB::table('translations')
                    ->where('locale', $locale)
                    ->where('group', 'db')
                    ->where('key', $key)
                    ->first();

                if (!$existing) {
                    DB::table('translations')->insert([
                        'locale' => $locale,
                        'group' => 'db',
                        'key' => $key,
                        'value' => $value,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    continue;
                }

                if ($locale !== 'fr') {
                    continue;
                }

                $current = (string) $existing->value;
                $storedKey = (string) $existing->key;
                $stillEnglish = $current === ''
                    || strcasecmp($current, $key) === 0
                    || strcasecmp($current, $storedKey) === 0
                    || strcasecmp($current, (string) $values['en']) === 0;
                if (($stillEnglish || in_array($key, $force, true)) && $current !== $value) {
                    DB::table('translations')->where('id', $existing->id)->update([
                        'value' => $value,
                        'updated_at' => $now,
                    ]);
                }
            }
        }

        Translation::forgetCachedTranslations();
    }

    public function down(): void
    {
        Translation::forgetCachedTranslations();
    }
};
