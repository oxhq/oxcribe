<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Examples;

use Illuminate\Support\Str;
use Oxhq\Oxcribe\Examples\Data\EndpointExampleContext;
use Oxhq\Oxcribe\Examples\Data\ScenarioAuth;
use Oxhq\Oxcribe\Examples\Data\ScenarioCompany;
use Oxhq\Oxcribe\Examples\Data\ScenarioContext;
use Oxhq\Oxcribe\Examples\Data\ScenarioPerson;

final class ScenarioContextFactory
{
    /**
     * @param  array<string, mixed>  $resources
     */
    public function make(string $projectSeed, EndpointExampleContext $endpoint, ExampleMode $mode, array $resources = []): ScenarioContext
    {
        $seed = hash('sha256', implode('|', [
            $projectSeed,
            strtoupper($endpoint->method),
            $endpoint->path,
            $endpoint->operationKind,
            $mode->value,
        ]));

        $person = $this->makePerson($seed);
        $company = $this->makeCompany($seed);
        $auth = $this->makeAuth($seed);

        return new ScenarioContext(
            seed: $seed,
            mode: $mode,
            person: $person,
            company: $company,
            auth: $auth,
            resources: $resources,
        );
    }

    private function makePerson(string $seed): ScenarioPerson
    {
        $firstNames = ['Ana', 'Carlos', 'Elena', 'Mateo', 'Sofia', 'Diego', 'Lucia', 'Javier'];
        $lastNames = ['Lopez', 'Mendez', 'Torres', 'Garcia', 'Navarro', 'Vega', 'Santos', 'Reyes'];

        $firstName = $this->pick($firstNames, $seed, 'person:first');
        $lastName = $this->pick($lastNames, $seed, 'person:last');
        $fullName = $firstName.' '.$lastName;
        $username = Str::slug($firstName.' '.$lastName, '_');
        $domain = $this->pick(['acme', 'northwind', 'lumen', 'atlas', 'orion'], $seed, 'person:domain');

        return new ScenarioPerson(
            firstName: $firstName,
            lastName: $lastName,
            fullName: $fullName,
            email: $username.'@'.$domain.'.test',
            phone: '+52664'.$this->digits($seed, 'person:phone', 7),
            username: $username,
        );
    }

    private function makeCompany(string $seed): ScenarioCompany
    {
        $prefixes = ['Acme', 'Northwind', 'Atlas', 'Nimbus', 'Vertex', 'Summit', 'Orion'];
        $suffixes = ['Logistics', 'Systems', 'Health', 'Labs', 'Commerce', 'Works', 'Cloud'];

        $name = $this->pick($prefixes, $seed, 'company:prefix').' '.$this->pick($suffixes, $seed, 'company:suffix');
        $domain = Str::slug($name).'.test';

        return new ScenarioCompany(
            name: $name,
            email: 'contact@'.$domain,
            website: 'https://'.$domain,
            domain: $domain,
        );
    }

    private function makeAuth(string $seed): ScenarioAuth
    {
        $suffix = strtoupper(substr($seed, 0, 4));

        return new ScenarioAuth(
            password: 'Str0ng!Pass'.$suffix,
            token: 'tok_test_'.substr($seed, 0, 10),
            apiKey: 'oxc_live_'.substr($seed, 10, 16),
        );
    }

    /**
     * @param  list<string>  $values
     */
    private function pick(array $values, string $seed, string $scope): string
    {
        $hash = hash('sha256', $seed.'|'.$scope);
        $index = hexdec(substr($hash, 0, 8)) % count($values);

        return $values[$index];
    }

    private function digits(string $seed, string $scope, int $length): string
    {
        $hash = hash('sha256', $seed.'|'.$scope);
        $digits = '';

        for ($i = 0; strlen($digits) < $length && $i < strlen($hash); $i++) {
            $digits .= (string) (hexdec($hash[$i]) % 10);
        }

        return substr(str_pad($digits, $length, '7'), 0, $length);
    }
}
