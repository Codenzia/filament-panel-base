<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Forms\Components;

use Closure;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Model;

/**
 * Country Select with flag icons for Filament forms.
 *
 * Renders a Select field where each option is prefixed with a flag icon
 * using the bundled flag-icons CSS (auto-loaded via @filamentStyles).
 *
 * Usage (from relationship):
 *   CountrySelect::make('country_id')
 *       ->relationship('country', 'name')
 *
 * Usage (from array — keys are ISO country codes):
 *   CountrySelect::make('country')
 *       ->countries(['jo' => 'Jordan', 'sa' => 'Saudi Arabia'])
 *
 * Usage (from array — keys are IDs, with explicit codes):
 *   CountrySelect::make('country_id')
 *       ->countries([1 => ['name' => 'Jordan', 'code' => 'jo']])
 */
class CountrySelect extends Select
{
    /** Column on the related model that holds the ISO country code. */
    protected string | Closure $codeAttribute = 'code';

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->allowHtml()
            ->searchable()
            ->preload();
    }

    /**
     * Set the attribute name that holds the ISO country code on the related model.
     */
    public function codeAttribute(string | Closure $attribute): static
    {
        $this->codeAttribute = $attribute;

        return $this;
    }

    public function getCodeAttribute(): string
    {
        return (string) $this->evaluate($this->codeAttribute);
    }

    /**
     * Populate options from an array of countries.
     *
     * Accepted formats:
     *   ['jo' => 'Jordan', 'sa' => 'Saudi Arabia']          — keys are ISO codes (stored as value)
     *   [1 => ['name' => 'Jordan', 'code' => 'jo'], ...]    — keys are IDs, with explicit code
     *
     * Also accepts a Closure that returns an array in the same formats.
     */
    public function countries(array | Closure $countries): static
    {
        return $this
            ->options(function () use ($countries): array {
                return static::buildFlagOptions($this->evaluate($countries))['options'];
            })
            ->getSearchResultsUsing(function (string $search) use ($countries): array {
                $built = static::buildFlagOptions($this->evaluate($countries));
                $search = mb_strtolower($search);

                return collect($built['options'])
                    ->filter(fn ($label, $key) => str_contains(mb_strtolower($built['searchMap'][$key] ?? ''), $search))
                    ->toArray();
            });
    }

    /**
     * Build flag-prefixed options and a plain-text search map from a countries array.
     *
     * @return array{options: array, searchMap: array}
     */
    protected static function buildFlagOptions(array $countries): array
    {
        $options = [];
        $searchMap = [];

        foreach ($countries as $key => $country) {
            if (is_array($country)) {
                $code = strtolower($country['code']);
                $name = $country['name'];
            } else {
                $code = strtolower((string) $key);
                $name = $country;
            }

            $options[$key] = static::flagHtml($code) . ' ' . e($name);
            $searchMap[$key] = $name;
        }

        return compact('options', 'searchMap');
    }

    /**
     * Override relationship to auto-add flag rendering.
     */
    public function relationship(string | Closure | null $name = null, string | Closure | null $titleAttribute = null, ?Closure $modifyQueryUsing = null, bool $ignoreRecord = false): static
    {
        parent::relationship($name, $titleAttribute, $modifyQueryUsing, $ignoreRecord);

        $codeAttr = $this->codeAttribute;

        $this->getOptionLabelFromRecordUsing(function (Model $record) use ($codeAttr): string {
            $code = strtolower((string) $record->getAttribute(
                (string) $this->evaluate($codeAttr)
            ));
            $name = $record->getAttribute($this->getRelationshipTitleAttribute());

            return static::flagHtml($code) . ' ' . e($name);
        });

        return $this;
    }

    /**
     * Generate the flag icon HTML span.
     */
    protected static function flagHtml(string $code): string
    {
        $code = e($code);

        return '<span class="flag flag-' . $code . '" style="display:inline-block;vertical-align:middle;"></span>';
    }
}
