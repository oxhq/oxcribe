<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Examples\Data;

final readonly class ScenarioPerson
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $fullName,
        public string $email,
        public string $phone,
        public string $username,
    ) {}

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'fullName' => $this->fullName,
            'email' => $this->email,
            'phone' => $this->phone,
            'username' => $this->username,
        ];
    }
}
