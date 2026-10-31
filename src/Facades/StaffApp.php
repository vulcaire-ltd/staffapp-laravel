<?php

namespace Vulcaire\StaffApp\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use Vulcaire\StaffApp\Client;

/**
 * @method static \Vulcaire\StaffApp\Resources\Jobs jobs()
 * @method static \Vulcaire\StaffApp\Resources\Workers workers()
 *
 * @see Client
 */
final class StaffApp extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Client::class;
    }
}
