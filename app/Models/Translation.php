<?php

namespace App\Models;


use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Translation extends Model
{
    use HasFactory;
    
    protected $fillable = ['locale', 'group', 'key', 'value'];

    // Get Default Language (Cached)
    public static function getTrnaslactionsByLocale(string $locale)
    {
        return Cache::rememberForever('translations_by_locale_'.$locale, function () use ($locale) {
            return self::where('locale', $locale)
                ->get()
                ->mapWithKeys(function ($item) {
                    return [$item->group . '.' . $item->key => $item->value];
                })
                ->toArray();
        });
    }

    public static function forgetCachedTranslations()
    {
        Cache::forget('translations_by_locale');
        Cache::forget('languages_list');
        $locales = ['en', 'fr'];
        try {
            if (Schema::hasTable('languages')) {
                $locales = array_unique(array_merge(
                    $locales,
                    DB::table('languages')->pluck('language')->filter()->all()
                ));
            }
        } catch (\Throwable $e) {
            $locales = ['en', 'fr'];
        }
        foreach ($locales as $locale) {
            Cache::forget('translations_by_locale_'.$locale);
            Cache::forget('translations_'.$locale);
        }
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class, 'locale');
    }
}
