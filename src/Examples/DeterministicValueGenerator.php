<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Examples;

use Illuminate\Support\Str;
use Oxhq\Oxcribe\Examples\Data\ExampleField;
use Oxhq\Oxcribe\Examples\Data\ScenarioContext;

final class DeterministicValueGenerator
{
    public function generate(ExampleField $field, ScenarioContext $context, ?int $index = null): mixed
    {
        $salt = $field->path.($index !== null ? '#'.$index : '');

        return match ($field->semanticType) {
            'email' => $index === null ? ($context->person?->email ?? $this->indexedEmail($context, $salt)) : $this->indexedEmail($context, $salt),
            'password', 'password_confirmation' => $context->auth?->password ?? 'Str0ng!Pass2026',
            'first_name' => $index === null ? ($context->person?->firstName ?? $this->indexedFirstName($context, $salt)) : $this->indexedFirstName($context, $salt),
            'last_name' => $index === null ? ($context->person?->lastName ?? $this->indexedLastName($context, $salt)) : $this->indexedLastName($context, $salt),
            'full_name' => $index === null ? ($context->person?->fullName ?? $this->indexedFullName($context, $salt)) : $this->indexedFullName($context, $salt),
            'username' => $index === null ? ($context->person?->username ?? $this->indexedUsername($context, $salt)) : $this->indexedUsername($context, $salt),
            'phone' => $index === null ? ($context->person?->phone ?? $this->indexedPhone($context, $salt)) : $this->indexedPhone($context, $salt),
            'company_name' => $context->company?->name ?? 'Acme Logistics',
            'url' => $this->urlValue($field, $context),
            'slug' => $index === null
                ? Str::slug($context->person?->fullName ?? $this->indexedFullName($context, $salt))
                : Str::slug($this->indexedFullName($context, $salt)),
            'uuid' => $this->uuid($context->seed, $salt),
            'ulid' => $this->ulid($context->seed, $salt),
            'token' => $context->auth?->token ?? 'tok_test_8f4a1c29b2',
            'api_key' => $context->auth?->apiKey ?? 'oxc_live_3baf9c1d8a',
            'amount' => $this->decimal($context->seed, $salt, 99, 499),
            'percentage' => $this->integer($context->seed, $salt, 5, 95),
            'quantity' => $this->integer($context->seed, $salt, 1, 5),
            'foreign_key_id' => $this->integer($context->seed, $salt, 1, 999),
            'date' => $this->dateValue($context->seed, $salt),
            'datetime' => $this->dateTimeValue($context->seed, $salt),
            'postal_code' => $this->digits($context->seed, $salt, 5),
            'city' => $this->pick(['Tijuana', 'Monterrey', 'Guadalajara', 'Merida'], $context->seed, $salt),
            'state' => $this->pick(['Baja California', 'Jalisco', 'Nuevo Leon', 'Yucatan'], $context->seed, $salt),
            'country' => 'Mexico',
            'role', 'status', 'state', 'type', 'enum' => $field->allowedValues[0] ?? 'default',
            'boolean' => true,
            'integer' => $this->integer($context->seed, $salt, 1, 999),
            'number' => $this->decimal($context->seed, $salt, 1, 999),
            default => $this->fallbackValue($field, $context, $salt),
        };
    }

    private function fallbackValue(ExampleField $field, ScenarioContext $context, string $salt): mixed
    {
        return match ($field->baseType) {
            'boolean' => true,
            'integer' => $this->integer($context->seed, $salt, 1, 999),
            'number' => $this->decimal($context->seed, $salt, 1, 999),
            default => $this->stringFallback($field, $context, $salt),
        };
    }

    private function stringFallback(ExampleField $field, ScenarioContext $context, string $salt): string
    {
        if ($field->allowedValues !== []) {
            return $field->allowedValues[0];
        }

        return match ($field->name) {
            'name' => $this->indexedFullName($context, $salt),
            default => 'example_'.$this->slugPart($field->name).'_'.$this->hashSuffix($context->seed, $salt, 4),
        };
    }

    private function indexedFirstName(ScenarioContext $context, string $salt): string
    {
        $values = ['Ana', 'Carlos', 'Elena', 'Mateo', 'Sofia', 'Diego', 'Lucia', 'Javier'];

        return $this->pick($values, $context->seed, $salt);
    }

    private function indexedLastName(ScenarioContext $context, string $salt): string
    {
        $values = ['Lopez', 'Mendez', 'Torres', 'Garcia', 'Navarro', 'Vega', 'Santos', 'Reyes'];

        return $this->pick($values, $context->seed, $salt);
    }

    private function indexedFullName(ScenarioContext $context, string $salt): string
    {
        return $this->indexedFirstName($context, $salt).' '.$this->indexedLastName($context, $salt);
    }

    private function indexedUsername(ScenarioContext $context, string $salt): string
    {
        return Str::slug($this->indexedFullName($context, $salt), '_');
    }

    private function indexedEmail(ScenarioContext $context, string $salt): string
    {
        $username = $this->indexedUsername($context, $salt);
        $domain = $context->company?->domain ?? 'example.test';

        return $username.'@'.$domain;
    }

    private function indexedPhone(ScenarioContext $context, string $salt): string
    {
        return '+52664'.$this->digits($context->seed, $salt, 7);
    }

    private function urlValue(ExampleField $field, ScenarioContext $context): string
    {
        $lowerName = strtolower($field->name);
        if (str_contains($lowerName, 'callback')) {
            return 'https://example.test/callback';
        }
        if (str_contains($lowerName, 'avatar') || str_contains($lowerName, 'image')) {
            return 'https://images.example.test/avatar.jpg';
        }

        return $context->company?->website ?? 'https://example.test';
    }

    private function uuid(string $seed, string $salt): string
    {
        $hash = hash('sha256', $seed.'|'.$salt);

        return sprintf(
            '%s-%s-4%s-a%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 13, 3),
            substr($hash, 17, 3),
            substr($hash, 20, 12),
        );
    }

    private function ulid(string $seed, string $salt): string
    {
        $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
        $hash = hash('sha256', $seed.'|'.$salt);
        $value = '01';

        for ($i = 0; strlen($value) < 26; $i++) {
            $value .= $alphabet[hexdec($hash[$i % strlen($hash)]) % strlen($alphabet)];
        }

        return substr($value, 0, 26);
    }

    private function integer(string $seed, string $salt, int $min, int $max): int
    {
        $range = max(1, $max - $min + 1);
        $hash = hash('sha256', $seed.'|'.$salt);

        return $min + (hexdec(substr($hash, 0, 8)) % $range);
    }

    private function decimal(string $seed, string $salt, int $min, int $max): float
    {
        $whole = $this->integer($seed, $salt.':whole', $min, $max);
        $fraction = $this->integer($seed, $salt.':fraction', 0, 99);

        return (float) sprintf('%d.%02d', $whole, $fraction);
    }

    private function dateValue(string $seed, string $salt): string
    {
        $day = $this->integer($seed, $salt.':day', 1, 27);
        $month = $this->integer($seed, $salt.':month', 1, 12);

        return sprintf('2026-%02d-%02d', $month, $day);
    }

    private function dateTimeValue(string $seed, string $salt): string
    {
        $date = $this->dateValue($seed, $salt);
        $hour = $this->integer($seed, $salt.':hour', 8, 18);
        $minute = $this->integer($seed, $salt.':minute', 0, 59);

        return sprintf('%sT%02d:%02d:00Z', $date, $hour, $minute);
    }

    /**
     * @param  list<string>  $values
     */
    private function pick(array $values, string $seed, string $salt): string
    {
        $hash = hash('sha256', $seed.'|'.$salt);
        $index = hexdec(substr($hash, 0, 8)) % count($values);

        return $values[$index];
    }

    private function digits(string $seed, string $salt, int $length): string
    {
        $hash = hash('sha256', $seed.'|'.$salt);
        $digits = '';

        for ($i = 0; strlen($digits) < $length && $i < strlen($hash); $i++) {
            $digits .= (string) (hexdec($hash[$i]) % 10);
        }

        return substr(str_pad($digits, $length, '5'), 0, $length);
    }

    private function slugPart(string $value): string
    {
        $slug = Str::slug($value);

        return $slug !== '' ? $slug : 'field';
    }

    private function hashSuffix(string $seed, string $salt, int $length): string
    {
        return substr(hash('sha256', $seed.'|'.$salt), 0, $length);
    }
}
