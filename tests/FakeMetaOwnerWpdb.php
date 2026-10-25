<?php

namespace POTOGH\Tests;

/**
 * Minimal $wpdb double: get_var() answers the "lowest post ID owning this
 * export path" lookup with a fixed value.
 */
class FakeMetaOwnerWpdb
{
    public string $postmeta = 'wp_postmeta';

    /** @var string[] */
    public array $queries = [];

    private ?string $ownerId;

    public function __construct(?int $ownerId)
    {
        $this->ownerId = $ownerId === null ? null : (string) $ownerId;
    }

    public function prepare(string $query, ...$args): string
    {
        return vsprintf(str_replace('%s', "'%s'", $query), $args);
    }

    public function get_var(string $sql): ?string
    {
        $this->queries[] = $sql;

        return $this->ownerId;
    }
}
