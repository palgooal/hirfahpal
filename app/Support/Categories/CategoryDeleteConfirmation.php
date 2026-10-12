<?php

namespace App\Support\Categories;

use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Str;

/**
 * N-CAT-1 (AD-UI-CAT-01E-3C): a category is deleted only with a confirmation the server issued. Each one is a
 * random token kept in the admin's session, bound to one category and one admin, valid for TTL seconds and
 * consumed by its first use (accepted or not). It is issued when the admin is shown a delete step (the list's
 * dialog or the confirmation page); a value the server did not issue, or issued for another category or
 * admin, is refused. CSRF still protects every request; this adds the explicit, single-use confirmation.
 */
class CategoryDeleteConfirmation
{
    public const SESSION_KEY = 'admin_category_delete_confirmations';

    public const TTL = 600;

    /** Oldest confirmations are dropped beyond this, so the session stays small. */
    private const MAX = 100;

    public function __construct(private readonly Session $session) {}

    public function issue(int $categoryId, int $adminId): string
    {
        $now = now()->getTimestamp();
        $pending = array_filter($this->pending(), fn (array $entry) => $entry['expires_at'] > $now);

        $token = Str::random(48);
        $pending[$token] = ['category' => $categoryId, 'admin' => $adminId, 'expires_at' => $now + self::TTL];

        if (count($pending) > self::MAX) {
            uasort($pending, fn (array $a, array $b) => $a['expires_at'] <=> $b['expires_at']);
            $pending = array_slice($pending, -self::MAX, null, true);
        }

        $this->session->put(self::SESSION_KEY, $pending);

        return $token;
    }

    /** True only for a live confirmation of this category by this admin. The token is removed either way. */
    public function consume(mixed $token, int $categoryId, int $adminId): bool
    {
        if (! is_string($token) || $token === '') {
            return false;
        }

        $pending = $this->pending();
        $entry = $pending[$token] ?? null;

        if ($entry === null) {
            return false;
        }

        unset($pending[$token]);
        $this->session->put(self::SESSION_KEY, $pending);

        return $entry['category'] === $categoryId
            && $entry['admin'] === $adminId
            && $entry['expires_at'] > now()->getTimestamp();
    }

    /** @return array<string, array{category: int, admin: int, expires_at: int}> */
    private function pending(): array
    {
        $pending = $this->session->get(self::SESSION_KEY, []);

        return is_array($pending) ? $pending : [];
    }
}
