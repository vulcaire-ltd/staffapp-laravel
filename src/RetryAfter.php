<?php

namespace Vulcaire\StaffApp\Laravel;

final class RetryAfter
{
    /** @return list<int> */
    public static function seconds(int $tries = 5, int $base = 10): array
    {
        $delays = [];
        $delay = $base;
        for ($attempt = 1; $attempt < $tries; $attempt++) {
            $delays[] = $delay;
            $delay *= 3;
        }

        return $delays;
    }
}
