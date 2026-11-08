<?php

use App\Models\Translation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $labels = [
            'en' => [
                'Day' => 'Day',
                'Week' => 'Week',
                'Quarter' => 'Quarter',
                'Product List' => 'Product List',
                'Order No' => 'Order No',
                'Closing Date' => 'Closing Date',
                'Total Amount' => 'Total Amount',
                'Closed By' => 'Closed By',
                'Close' => 'Close',
            ],
            'fr' => [
                'Day' => 'Jour',
                'Week' => 'Semaine',
                'Quarter' => 'Trimestre',
                'Product List' => 'Liste des produits',
                'Order No' => 'N° de commande',
                'Closing Date' => 'Date de clôture',
                'Total Amount' => 'Montant total',
                'Closed By' => 'Fermé par',
                'Close' => 'Fermer',
            ],
        ];

        $now = now();
        foreach ($labels as $locale => $pairs) {
            foreach ($pairs as $key => $value) {
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

                if ((string) $existing->value !== $value) {
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
