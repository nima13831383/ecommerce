<?php

namespace App\Settings;

final readonly class SettingDefinition
{
    /** @param array<int, string> $rules */
    public function __construct(
        public string $key,
        public string $group,
        public string $type,
        public mixed $default,
        public string $label,
        public array $rules = [],
        public bool $sensitive = false,
    ) {}
}
