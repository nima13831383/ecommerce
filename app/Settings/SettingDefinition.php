<?php

namespace App\Settings;

final readonly class SettingDefinition
{
    /**
     * @param  array<int, mixed>  $rules
     * @param  array<int|string, string>  $options
     */
    public function __construct(
        public string $key,
        public string $group,
        public string $type,
        public mixed $default,
        public string $label,
        public array $rules = [],
        public bool $sensitive = false,
        public bool $nullable = false,
        public ?string $description = null,
        public array $options = [],
        public bool $core = true,
    ) {}
}
