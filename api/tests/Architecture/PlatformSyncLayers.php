<?php

namespace Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

// app/PlatformSync's dependency rule (spec 002). Run by PHPStan through phpstan.neon, not by
// PHPUnit. Each layer may use only the layers inside it, PHP's own classes and Carbon; Laravel and
// Eloquent appear only in the adapters, and outside them only the module's provider may name one.
final class PlatformSyncLayers
{
    private const DOMAIN = 'App\PlatformSync\Domain';

    private const PORTS = 'App\PlatformSync\Ports';

    private const APPLICATION = 'App\PlatformSync\Application';

    private const ADAPTERS = 'App\PlatformSync\Adapters';

    private const PROVIDER = 'App\Providers\PlatformSyncServiceProvider';

    public function test_the_domain_depends_only_on_itself(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace(self::DOMAIN))
            ->canOnly()->dependOn()
            ->classes(Selector::inNamespace(self::DOMAIN), Selector::inNamespace('Carbon'))
            ->because('the domain is plain PHP: no framework, database, cache or HTTP');
    }

    public function test_the_ports_depend_only_on_the_domain(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace(self::PORTS))
            ->canOnly()->dependOn()
            ->classes(
                Selector::inNamespace(self::PORTS),
                Selector::inNamespace(self::DOMAIN),
                Selector::inNamespace('Carbon'),
            )
            ->because('a port speaks the module\'s own types');
    }

    public function test_the_use_cases_depend_only_on_the_domain_and_the_ports(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace(self::APPLICATION))
            ->canOnly()->dependOn()
            ->classes(
                Selector::inNamespace(self::APPLICATION),
                Selector::inNamespace(self::PORTS),
                Selector::inNamespace(self::DOMAIN),
                Selector::inNamespace('Carbon'),
            )
            ->because('use cases reach the outside world only through ports');
    }

    public function test_the_adapters_do_not_call_the_use_cases(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace(self::ADAPTERS))
            ->shouldNot()->dependOn()
            ->classes(Selector::inNamespace(self::APPLICATION))
            ->because('an adapter implements a port; the use cases drive it, not the other way round');
    }

    public function test_only_the_provider_names_an_adapter(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::Not(Selector::AnyOf(
                Selector::inNamespace(self::ADAPTERS),
                Selector::classname(self::PROVIDER),
            )))
            ->shouldNot()->dependOn()
            ->classes(Selector::inNamespace(self::ADAPTERS))
            ->because('the provider is the composition root; everything else sees the ports');
    }
}
