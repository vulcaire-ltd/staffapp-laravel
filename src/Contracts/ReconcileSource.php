<?php

namespace Vulcaire\StaffApp\Laravel\Contracts;

use Vulcaire\StaffApp\Laravel\MirrorRecord;

interface ReconcileSource
{
    /** @return iterable<int, MirrorRecord> */
    public function records(): iterable;
}
