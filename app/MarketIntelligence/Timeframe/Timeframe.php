<?php

declare(strict_types=1);

namespace Collector369\MarketIntelligence\Timeframe;

/**
 * Períodos gráficos reconhecidos pela Phi.
 *
 * A ordem hierárquica permite relacionar contexto superior,
 * estrutura intermediária e período de entrada.
 */
enum Timeframe: string
{
    case M1 = '1m';
    case M5 = '5m';
    case M15 = '15m';
    case M30 = '30m';
    case H1 = '1h';
    case H4 = '4h';
    case D1 = '1d';
    case W1 = '1w';
    case MN1 = '1M';

    public function rank(): int
    {
        return match ($this) {
            self::M1 => 1,
            self::M5 => 2,
            self::M15 => 3,
            self::M30 => 4,
            self::H1 => 5,
            self::H4 => 6,
            self::D1 => 7,
            self::W1 => 8,
            self::MN1 => 9,
        };
    }

    public function isHigherThan(self $other): bool
    {
        return $this->rank() > $other->rank();
    }

    public function isLowerThan(self $other): bool
    {
        return $this->rank() < $other->rank();
    }
}
