<?php

namespace App\Translation;

use Illuminate\Support\Arr;
use Illuminate\Translation\Translator;

/**
 * Database translation keys are stored with one casing. MySQL matches those
 * keys without regard to case, so a second row cannot be inserted for
 * "Customer name" when "Customer Name" already exists. Views call both.
 */
class CaseInsensitiveTranslator extends Translator
{
    /** @var array<string, array<string, mixed>> */
    private array $dbIndex = [];

    public function addLines(array $lines, $locale, $namespace = '*')
    {
        unset($this->dbIndex[$locale]);
        foreach ($lines as $key => $value) {
            [$group, $item] = explode('.', $key, 2);
            // Keep dots inside the key. Arr::set would turn "Processing..." into
            // a nested array and hide the "Processing" translation.
            $this->loaded[$namespace][$group][$locale][$item] = $value;
        }
    }

    protected function getLine($namespace, $group, $locale, $item, array $replace)
    {
        $this->load($namespace, $group, $locale);

        $lines = $this->loaded[$namespace][$group][$locale] ?? [];
        $line = null;
        if (is_array($lines)) {
            if (array_key_exists($item, $lines) && is_string($lines[$item])) {
                $line = $lines[$item];
            } else {
                $found = Arr::get($lines, $item);
                $line = is_string($found) ? $found : null;
            }
        }

        if ($line === null && $group === 'db' && is_array($lines)) {
            $line = $this->matchDbLine($lines, (string) $locale, (string) $item);
        }

        if (is_string($line)) {
            return $this->makeReplacements($line, $replace);
        }
    }

    private function matchDbLine(array $lines, string $locale, string $item)
    {
        if (!isset($this->dbIndex[$locale])) {
            $this->dbIndex[$locale] = $this->flatten($lines);
        }

        return $this->dbIndex[$locale][$this->normalizeKey($item)] ?? null;
    }

    /**
     * @return array<string, string>
     */
    private function flatten(array $lines): array
    {
        $flat = [];
        foreach ($lines as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                continue;
            }
            $flat[$this->normalizeKey($key)] = $value;
        }

        return $flat;
    }

    private function normalizeKey(string $key): string
    {
        return mb_strtolower(rtrim($key));
    }
}
