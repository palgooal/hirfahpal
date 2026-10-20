<?php

namespace App\Support\SiteAvailability;

/**
 * SITE-AVAILABILITY (docs/deployment/HIRFAH-SITE-AVAILABILITY-DESIGN.md, SA-01): the three mutually exclusive
 * availability states. The single source of truth for the allowed values.
 */
enum SiteAvailabilityState: string
{
    case Open = 'open';
    case ComingSoon = 'coming_soon';
    case Maintenance = 'maintenance';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function isClosed(): bool
    {
        return $this !== self::Open;
    }

    /**
     * DEC-SA-02: a blocked safe request (GET, HEAD) gets 200 during coming_soon and 503 during maintenance; a blocked
     * state-changing request always gets 503.
     */
    public function statusFor(bool $safeMethod): int
    {
        return $this === self::ComingSoon && $safeMethod ? 200 : 503;
    }
}
