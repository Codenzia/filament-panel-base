<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Services;

use Carbon\Carbon;
use Codenzia\FilamentPanelBase\Models\Translation;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;

class TranslationScanner
{
    /**
     * Scan the configured paths for translation keys and sync them to the database.
     *
     * @param  array<string>|null  $paths  Paths to scan. Null uses config or defaults.
     * @return array{created: int, restored: int, total: int}
     */
    public function scan(?array $paths = null): array
    {
        $paths = $paths
            ?? config('filament-panel-base.translations.scan_paths')
            ?? [app_path(), resource_path('views')];

        $disk = app(Filesystem::class);
        [$grouped, $json] = $this->extractKeys($disk, $paths);

        $locales = Translation::getLocales();
        $created = 0;
        $restored = 0;

        DB::transaction(function () use ($grouped, $json, $locales, &$created, &$restored) {
            // Soft-delete all existing translations
            Translation::query()
                ->whereNull('deleted_at')
                ->update(['deleted_at' => Carbon::now()]);

            // Process grouped translations: trans('group.key')
            $grouped->each(function (string $match) use ($locales, &$created, &$restored) {
                [$group, $key] = explode('.', $match, 2);
                $namespaceAndGroup = explode('::', $group, 2);

                if (count($namespaceAndGroup) === 1) {
                    $namespace = '*';
                    $group = $namespaceAndGroup[0];
                } else {
                    [$namespace, $group] = $namespaceAndGroup;
                }

                $this->createOrRestore($namespace, $group, $key, $locales, $created, $restored);
            });

            // Process JSON translations: __('key')
            $json->each(function (string $key) use ($locales, &$created, &$restored) {
                $this->createOrRestore('*', '*', $key, $locales, $created, $restored);
            });
        });

        $total = Translation::query()->whereNull('deleted_at')->count();

        return [
            'created' => $created,
            'restored' => $restored,
            'total' => $total,
        ];
    }

    /**
     * Extract translation keys from source files in the given paths.
     *
     * Pattern A: Grouped PHP translations — trans('group.key'), @lang('pkg::group.key').
     *   Only matches strict dot-notation keys (alphanumeric + dots).
     *
     * Patterns B/C: JSON translations — __("text") / __('text') plus any extra
     *   functions from config('filament-panel-base.translations.scan_functions').
     *   Single-line only, handles escaped quotes.
     *
     * @param  array<string>  $paths
     * @return array{0: \Illuminate\Support\Collection, 1: \Illuminate\Support\Collection}
     */
    private function extractKeys(Filesystem $disk, array $paths): array
    {
        // Pattern A: Grouped PHP translations — trans('group.key'), @lang('pkg::group.key')
        // Only captures strict dot-notation keys (alphanumeric, underscore, hyphen, slash).
        $groupedFunctions = 'trans|trans_choice|Lang::get|Lang::choice|Lang::trans|Lang::transChoice|@lang|@choice';
        $patternA = "/(?<!->)(?:{$groupedFunctions})\(\s*['\"]([a-zA-Z0-9_\-\/]+(?:::[a-zA-Z0-9_\-\/]+)?(?:\.[a-zA-Z0-9_\-\/]+)+)['\"]\s*[,)]/";

        // Build JSON function alternation: __ + any extra functions from config (e.g. $t, i18n.t)
        $extraFunctions = config('filament-panel-base.translations.scan_functions', []);
        $jsonFunctions = collect(['__', ...$extraFunctions])
            ->map(fn (string $fn): string => preg_quote($fn, '/'))
            ->implode('|');

        // Pattern B: fn("text") with double quotes — single-line, handles escaped quotes
        $patternB = '/(?<!\w)(?<!->)(?:' . $jsonFunctions . ')\(\s*"([^"\n\\\\]*(?:\\\\.[^"\n\\\\]*)*)"\s*[,)]/';

        // Pattern C: fn('text') with single quotes — single-line, handles escaped quotes
        $patternC = "/(?<!\w)(?<!->)(?:{$jsonFunctions})\(\s*'([^'\n\\\\]*(?:\\\\.[^'\n\\\\]*)*)'\s*[,)]/";

        $grouped = collect();
        $json = collect();

        $extensions = config('filament-panel-base.translations.scan_extensions', ['php']);
        $validPaths = array_filter($paths, fn (string $path): bool => is_dir($path));

        foreach ($disk->allFiles($validPaths) as $file) {
            if (! in_array($file->getExtension(), $extensions, true)) {
                continue;
            }

            $contents = $file->getContents();

            if (preg_match_all($patternA, $contents, $matches)) {
                $grouped->push(...$matches[1]);
            }

            if (preg_match_all($patternB, $contents, $matches)) {
                $json->push(...$matches[1]);
            }

            if (preg_match_all($patternC, $contents, $matches)) {
                $json->push(...$matches[1]);
            }
        }

        // Sanitise: reject keys that contain HTML or Blade syntax (false positives)
        $isClean = fn (string $key): bool => ! preg_match('/<[a-zA-Z\/!]|{{|}}/', $key);

        $grouped = $grouped->unique()
            ->filter(fn (string $key): bool => strlen($key) <= 200 && str_contains($key, '.') && $isClean($key))
            ->values();

        $json = $json->unique()
            ->filter(fn (string $key): bool => strlen($key) > 0 && strlen($key) <= 500 && $isClean($key))
            ->values();

        return [$grouped, $json];
    }

    /**
     * Restore a soft-deleted translation or create a new one.
     *
     * @param  array<string>  $locales
     */
    private function createOrRestore(
        string $namespace,
        string $group,
        string $key,
        array $locales,
        int &$created,
        int &$restored,
    ): void {
        $existing = Translation::withTrashed()
            ->where('namespace', $namespace)
            ->where('group', $group)
            ->where('key', $key)
            ->first();

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
                $restored++;
            }

            return;
        }

        // Build initial text values from existing language files
        $text = [];
        foreach ($locales as $locale) {
            $langKey = $group === '*' ? $key : "{$group}.{$key}";

            if ($namespace !== '*') {
                $langKey = "{$namespace}::{$langKey}";
            }

            $translation = Lang::get($langKey, [], $locale);
            $text[$locale] = ! is_array($translation) ? (string) $translation : '';
        }

        Translation::query()->create([
            'namespace' => $namespace,
            'group' => $group,
            'key' => $key,
            'text' => $text,
        ]);

        $created++;
    }
}
