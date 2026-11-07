<?php

namespace App\Support\Reports;

use App\Models\Sale;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * El período de un reporte: qué fechas mira, con qué las compara y cómo agrupa.
 * Todos los gráficos y cifras de una pantalla salen del mismo período.
 */
class ReportPeriod
{
    public const PRESETS = [
        'this_month' => 'Este mes',
        'last_month' => 'Mes pasado',
        'last_30' => 'Últimos 30 días',
        'last_90' => 'Últimos 90 días',
        'this_year' => 'Este año',
        'last_year' => 'Año pasado',
        'all' => 'Todo',
    ];

    public const GROUPS = ['day' => 'Día', 'month' => 'Mes', 'year' => 'Año'];

    /** Largo de la fecha 'Y-m-d' que identifica a cada agrupación. */
    private const KEY_LENGTH = ['day' => 10, 'month' => 7, 'year' => 4];

    /** Más días que estos no se leen como barras o puntos diarios. */
    private const MAX_DAILY_DAYS = 92;

    public function __construct(
        public readonly string $preset,
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly ?CarbonImmutable $previousFrom,
        public readonly ?CarbonImmutable $previousTo,
        public readonly ?string $previousLabel,
        public readonly string $group,
        public readonly ?int $branchId = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $today = CarbonImmutable::now()->endOfDay();
        $from = self::parse($request->query('from'));
        $to = self::parse($request->query('to'));
        $preset = $request->query('preset');

        if ($preset === 'custom' && $from && $to) {
            [$from, $to] = $from->lte($to) ? [$from, $to] : [$to, $from];
            $days = (int) $from->diffInDays($to) + 1;

            return self::make('custom', $from->startOfDay(), $to->endOfDay(), $from->subDays($days)->startOfDay(), $from->subDay()->endOfDay(),
                'el período anterior', $request);
        }

        $start = $today->startOfDay();

        // "Este mes" va por la mitad: se compara con los mismos días del mes anterior.
        return match ($preset) {
            'last_month' => self::make('last_month', $start->subMonthNoOverflow()->startOfMonth(), $start->subMonthNoOverflow()->endOfMonth(),
                $start->subMonthsNoOverflow(2)->startOfMonth(), $start->subMonthsNoOverflow(2)->endOfMonth(), 'el mes anterior', $request),
            'last_30' => self::make('last_30', $start->subDays(29), $today, $start->subDays(59), $start->subDays(30)->endOfDay(), 'los 30 días anteriores', $request),
            'last_90' => self::make('last_90', $start->subDays(89), $today, $start->subDays(179), $start->subDays(90)->endOfDay(), 'los 90 días anteriores', $request),
            'this_year' => self::make('this_year', $start->startOfYear(), $today, $start->subYearNoOverflow()->startOfYear(), $today->subYearNoOverflow(), 'el mismo período del año anterior', $request),
            'last_year' => self::make('last_year', $start->subYearNoOverflow()->startOfYear(), $start->subYearNoOverflow()->endOfYear(),
                $start->subYearsNoOverflow(2)->startOfYear(), $start->subYearsNoOverflow(2)->endOfYear(), 'el año anterior', $request),
            'all' => self::make('all', self::firstActivity(), $today, null, null, null, $request),
            default => self::make('this_month', $start->startOfMonth(), $today, $start->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow(), 'los mismos días del mes anterior', $request),
        };
    }

    private static function make(string $preset, CarbonImmutable $from, CarbonImmutable $to, ?CarbonImmutable $previousFrom, ?CarbonImmutable $previousTo, ?string $previousLabel, Request $request): self
    {
        $branch = $request->integer('branch_id') ?: null;
        $requested = $request->query('group');

        return new self($preset, $from, $to, $previousFrom, $previousTo, $previousLabel,
            in_array($requested, self::allowedGroups($from, $to), true) ? $requested : self::autoGroup($from, $to), $branch);
    }

    private static function parse(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);

        // createFromFormat acepta 2026-02-31 corriéndolo a marzo: eso no es una fecha válida.
        return $date && $date->format('Y-m-d') === $value ? $date : null;
    }

    private static function firstActivity(): CarbonImmutable
    {
        $first = Sale::min('created_at') ?? app(Tenancy::class)->company()?->created_at;

        return CarbonImmutable::parse($first ?? 'now')->startOfDay();
    }

    /** @return array<int, string> */
    private static function allowedGroups(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return array_values(array_filter([
            $from->diffInDays($to) < self::MAX_DAILY_DAYS ? 'day' : null,
            $from->format('Y-m') !== $to->format('Y-m') ? 'month' : null,
            $from->year !== $to->year ? 'year' : null,
        ]));
    }

    private static function autoGroup(CarbonImmutable $from, CarbonImmutable $to): string
    {
        return match (true) {
            $from->diffInDays($to) < self::MAX_DAILY_DAYS => 'day',
            $to->year - $from->year >= 3 => 'year',
            default => 'month',
        };
    }

    /** @return array<string, string> agrupaciones que tienen sentido para estas fechas */
    public function groupOptions(): array
    {
        return array_intersect_key(self::GROUPS, array_flip(self::allowedGroups($this->from, $this->to)));
    }

    /** @return array<string, string> clave de cada tramo del gráfico => etiqueta */
    public function buckets(): array
    {
        $buckets = [];
        $step = ['day' => 'addDay', 'month' => 'addMonthNoOverflow', 'year' => 'addYearNoOverflow'][$this->group];
        $format = ['day' => 'j M', 'month' => 'M y', 'year' => 'Y'][$this->group];
        $cursor = match ($this->group) {
            'month' => $this->from->startOfMonth(),
            'year' => $this->from->startOfYear(),
            default => $this->from,
        };

        while ($cursor->lte($this->to)) {
            $buckets[$this->key($cursor->format('Y-m-d'))] = $cursor->locale('es')->translatedFormat($format);
            $cursor = $cursor->{$step}();
        }

        return $buckets;
    }

    public function key(string $date): string
    {
        return substr($date, 0, self::KEY_LENGTH[$this->group]);
    }

    /**
     * Reparte importes por día en los tramos del gráfico, incluidos los vacíos.
     *
     * @param  iterable<string, float>  $daily
     * @return array<int, float>
     */
    public function series(iterable $daily): array
    {
        $values = array_fill_keys(array_keys($this->buckets()), 0.0);

        foreach ($daily as $date => $amount) {
            $key = $this->key((string) $date);
            if (isset($values[$key])) {
                $values[$key] += (float) $amount;
            }
        }

        return array_map(fn ($v) => round($v, 2), array_values($values));
    }

    public function label(): string
    {
        return self::rangeLabel($this->from, $this->to);
    }

    public function previousRangeLabel(): ?string
    {
        return $this->previousFrom ? self::rangeLabel($this->previousFrom, $this->previousTo) : null;
    }

    private static function rangeLabel(CarbonImmutable $from, CarbonImmutable $to): string
    {
        $end = $to->locale('es')->translatedFormat('j M Y');

        if ($from->isSameDay($to)) {
            return $end;
        }

        return $from->locale('es')->translatedFormat($from->year === $to->year ? 'j M' : 'j M Y').' – '.$end;
    }

    /** Parámetros de la URL para conservar el período al cambiar de pantalla o exportar. */
    public function query(array $overrides = []): array
    {
        return array_filter(array_merge([
            'preset' => $this->preset,
            'from' => $this->preset === 'custom' ? $this->from->toDateString() : null,
            'to' => $this->preset === 'custom' ? $this->to->toDateString() : null,
            'group' => $this->group,
            'branch_id' => $this->branchId,
        ], $overrides));
    }
}
