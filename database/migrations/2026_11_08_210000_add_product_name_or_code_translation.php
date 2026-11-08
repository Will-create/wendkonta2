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
                'Product name or code' => 'Product name or code',
            ],
            'fr' => [
                'Product name or code' => 'Nom ou code du produit',
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
