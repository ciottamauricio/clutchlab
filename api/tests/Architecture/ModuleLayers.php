<?php

namespace Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

// The dependency rule of a ports-and-adapters module in app/ (specs 002 and 004), written once.
// Run by PHPStan through phpstan.neon, not by PHPUnit; each module's subclass is registered there
// and names its own namespace and provider, so a broken rule reports under the module's name.
// Each layer may use only the layers inside it, PHP's own classes and Carbon; Laravel and Eloquent
// appear only in the adapters, and outside them only the module's provider may name one.
abstract class ModuleLayers
{
    /** The module's root namespace, e.g. App\PlatformSync. */
    abstract protected function module(): string;

    /** The provider that binds the module's ports: its composition root. */
    abstract protected function provider(): string;

    public function test_the_domain_depends_only_on_itself(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace($this->layer('Domain')))
            ->canOnly()->dependOn()
            ->classes(Selector::inNamespace($this->layer('Domain')), Selector::inNamespace('Carbon'))
            ->because('the domain is plain PHP: no framework, database, cache or HTTP');
    }

    public function test_the_ports_depend_only_on_the_domain(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace($this->layer('Ports')))
            ->canOnly()->dependOn()
            ->classes(
                Selector::inNamespace($this->layer('Ports')),
                Selector::inNamespace($this->layer('Domain')),
                Selector::inNamespace('Carbon'),
            )
            ->because('a port speaks the module\'s own types');
    }

    public function test_the_use_cases_depend_only_on_the_domain_and_the_ports(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace($this->layer('Application')))
            ->canOnly()->dependOn()
            ->classes(
                Selector::inNamespace($this->layer('Application')),
                Selector::inNamespace($this->layer('Ports')),
                Selector::inNamespace($this->layer('Domain')),
                Selector::inNamespace('Carbon'),
            )
            ->because('use cases reach the outside world only through ports');
    }

    public function test_the_adapters_do_not_call_the_use_cases(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace($this->layer('Adapters')))
            ->shouldNot()->dependOn()
            ->classes(Selector::inNamespace($this->layer('Application')))
            ->because('an adapter implements a port; the use cases drive it, not the other way round');
    }

    public function test_only_the_provider_names_an_adapter(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::Not(Selector::AnyOf(
                Selector::inNamespace($this->layer('Adapters')),
                Selector::classname($this->provider()),
            )))
            ->shouldNot()->dependOn()
            ->classes(Selector::inNamespace($this->layer('Adapters')))
            ->because('the provider is the composition root; everything else sees the ports');
    }

    private function layer(string $name): string
    {
        return $this->module().'\\'.$name;
    }
}
