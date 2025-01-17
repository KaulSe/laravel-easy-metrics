<?php

namespace SaKanjo\EasyMetrics\Metrics;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Macroable;
use SaKanjo\EasyMetrics\Enums\Range;

abstract class Metric
{
    use Conditionable;
    use Macroable;

    protected Builder $query;

    protected string $type;

    protected string $column;

    protected int|Range|null $range = null;

    /**
     * @var int[]|Range[]
     */
    protected array $ranges = [
        15, 30, 60, 365,
        Range::TODAY, Range::YESTERDAY, Range::MTD, Range::QTD, Range::YTD, Range::ALL,
    ];

    public function __construct(
        string|Builder $query,
    ) {
        $this->query = is_string($query) ? $query::query() : $query;
    }

    public static function make(string|Builder $query): static
    {
        return App::make(static::class, [
            'query' => $query,
        ]);
    }

    abstract protected function resolve(): mixed;

    public function modifyQuery(callable $callback): static
    {
        $callback($this->query);

        return $this;
    }

    public function range(int|string|Range|null $range): static
    {
        $range = Range::tryFrom($range) ?? $range;

        if (in_array($range, $this->getRanges())) {
            $this->range = $range;
        }

        return $this;
    }

    public function ranges(array $ranges): static
    {
        $this->ranges = Arr::map($ranges,
            fn ($range) => is_string($range) ? Range::from($range) : $range
        );

        return $this;
    }

    public function rangesFromOptions(array $options): static
    {
        return $this->ranges(
            array_keys($options)
        );
    }

    public function getRange(): int|Range
    {
        return $this->range ?? $this->getRanges()[0] ?? Range::ALL;
    }

    public function getRanges(): array
    {
        return $this->ranges;
    }

    protected function getDateColumn(): string
    {
        return $this->query->getModel()->getCreatedAtColumn();
    }

    protected function resolveBetween(array $range): array
    {
        return [
            $this->getDateColumn(),
            $range,
        ];
    }

    protected function currentRange(): ?array
    {
        $range = $this->getRange();

        if ($range instanceof Range) {
            return $range->getRange();
        }

        return [
            Date::now()->subDays($range),
            Date::now(),
        ];
    }

    protected function transformResult(int|float $data): float
    {
        return round($data, 2);
    }
}
