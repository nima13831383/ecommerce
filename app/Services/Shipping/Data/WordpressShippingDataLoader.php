<?php

namespace App\Services\Shipping\Data;

use JsonException;
use RuntimeException;

class WordpressShippingDataLoader
{
    private ?array $locationData = null;

    private ?array $neighboringProvinces = null;

    private ?array $packageSizes = null;

    public function __construct(private readonly ?string $configuredPluginPath = null) {}

    /**
     * @return array<int, string>
     */
    public function provinces(): array
    {
        $provinces = [];

        foreach ($this->locationData() as $provinceId => $province) {
            if (! isset($province['title'], $province['cities']) || ! is_array($province['cities'])) {
                throw new RuntimeException("Invalid province record in plugin data: {$provinceId}");
            }

            $provinces[(int) $provinceId] = trim((string) $province['title']);
        }

        return array_filter($provinces);
    }

    public function provinceName(int $provinceId): ?string
    {
        return $this->provinces()[$provinceId] ?? null;
    }

    /**
     * @return array<int, string>
     */
    public function cities(int $provinceId): array
    {
        $province = $this->locationData()[$provinceId] ?? null;

        if (! is_array($province) || ! isset($province['cities']) || ! is_array($province['cities'])) {
            return [];
        }

        $cities = [];

        foreach ($province['cities'] as $cityId => $city) {
            $cities[(int) $cityId] = trim((string) $city);
        }

        // Source: includes/class-tapin.php::cities().
        unset($cities[376]);
        asort($cities);

        return $cities;
    }

    public function cityName(int $cityId, int $provinceId): ?string
    {
        return $this->cities($provinceId)[$cityId] ?? null;
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function citiesByProvince(): array
    {
        $cities = [];

        foreach (array_keys($this->provinces()) as $provinceId) {
            $cities[$provinceId] = $this->cities($provinceId);
        }

        return $cities;
    }

    public function cityBelongsToProvince(int $cityId, int $provinceId): bool
    {
        return isset($this->cities($provinceId)[$cityId]);
    }

    public function areNeighboringProvinces(int $originProvinceId, int $destinationProvinceId): bool
    {
        if ($originProvinceId === $destinationProvinceId) {
            return false;
        }

        return isset($this->neighboringProvinces()[$originProvinceId][$destinationProvinceId]);
    }

    /**
     * @return array<int, string>
     */
    public function pluginPackageSizes(): array
    {
        if ($this->packageSizes !== null) {
            return $this->packageSizes;
        }

        $source = $this->readPluginFile('includes/class-tapin.php');

        if (! preg_match('/function\s+box_sizes\s*\(\s*\)\s*:\s*array\s*\{.*?return\s*\[(.*?)\];/su', $source, $match)) {
            throw new RuntimeException('Could not locate box_sizes() in the WordPress plugin.');
        }

        preg_match_all("/(\d+)\s*=>\s*'([^']+)'/u", $match[1], $matches, PREG_SET_ORDER);

        $sizes = [];
        foreach ($matches as $size) {
            $sizes[(int) $size[1]] = $size[2];
        }

        if ($sizes === []) {
            throw new RuntimeException('No package sizes were found in the WordPress plugin.');
        }

        return $this->packageSizes = $sizes;
    }

    /**
     * @return array<int, array<int, array<string, int>>>
     */
    public function rateTable(string $service, int $originProvinceId): array
    {
        $relativePath = $this->rateTableRelativePath($service, $originProvinceId);

        return $this->loadSafePhpArray($relativePath);
    }

    public function rateTableRelativePath(string $service, int $originProvinceId): string
    {
        $methodFile = match ($service) {
            'pishtaz' => 'methods/tapin-pishtaz-method.php',
            'vijeh' => 'methods/tapin-special-method.php',
            default => throw new RuntimeException("Unsupported postal service: {$service}"),
        };

        $rateName = $service === 'pishtaz' ? 'tapin-pishtaz' : 'tapin-special';
        $suffix = in_array($originProvinceId, $this->borderOriginProvinceIds($methodFile), true)
            ? '-border'
            : '';

        return "data/rates/{$rateName}{$suffix}.php";
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function locationData(): array
    {
        if ($this->locationData !== null) {
            return $this->locationData;
        }

        $json = $this->readPluginFile('data/tapin.json');

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('The plugin location data is not valid JSON.', previous: $exception);
        }

        if (! is_array($data)) {
            throw new RuntimeException('The plugin location data must decode to an array.');
        }

        return $this->locationData = $data;
    }

    /**
     * The plugin keeps this large mapping inside a WordPress class. Parsing the assignments
     * avoids bootstrapping WordPress and avoids copying the mapping into Laravel.
     *
     * @return array<int, array<int, true>>
     */
    private function neighboringProvinces(): array
    {
        if ($this->neighboringProvinces !== null) {
            return $this->neighboringProvinces;
        }

        $source = $this->readPluginFile('includes/class-tapin.php');
        preg_match_all('/\$is_beside\[(\d+)\]\[(\d+)\]\s*=\s*true\s*;/u', $source, $matches, PREG_SET_ORDER);

        $neighbors = [];
        foreach ($matches as $pair) {
            $neighbors[(int) $pair[1]][(int) $pair[2]] = true;
        }

        if ($neighbors === []) {
            throw new RuntimeException('Could not parse the neighboring-province map from the plugin.');
        }

        return $this->neighboringProvinces = $neighbors;
    }

    /**
     * @return array<int>
     */
    private function borderOriginProvinceIds(string $methodFile): array
    {
        $source = $this->readPluginFile($methodFile);

        if (! preg_match('~in_array\(\s*\$args\[\'from_province\'\]\s*,\s*\[([0-9,\s]+)\]~u', $source, $match)) {
            throw new RuntimeException("Could not parse border provinces from {$methodFile}.");
        }

        preg_match_all('/\d+/', $match[1], $ids);

        return array_map('intval', $ids[0]);
    }

    /**
     * Plugin rate files were inspected and contain only a returned PHP array.
     *
     * @return array<mixed>
     */
    private function loadSafePhpArray(string $relativePath): array
    {
        $path = $this->resolvePluginFile($relativePath);
        $source = file_get_contents($path);

        if ($source === false || ! preg_match('/\A\s*<\?php\s+return\s*\[/u', $source)) {
            throw new RuntimeException("Refusing to execute a non-data plugin file: {$relativePath}");
        }

        $allowedTokens = [
            T_OPEN_TAG,
            T_RETURN,
            T_ARRAY,
            T_WHITESPACE,
            T_LNUMBER,
            T_DNUMBER,
            T_CONSTANT_ENCAPSED_STRING,
            T_DOUBLE_ARROW,
            T_COMMENT,
            T_DOC_COMMENT,
        ];

        foreach (token_get_all($source) as $token) {
            if (is_array($token) && ! in_array($token[0], $allowedTokens, true)) {
                throw new RuntimeException("Refusing unexpected PHP in plugin data file: {$relativePath}");
            }

            if (is_string($token) && ! str_contains('[](),;-.+', $token)) {
                throw new RuntimeException("Refusing unexpected syntax in plugin data file: {$relativePath}");
            }
        }

        $data = (static fn (string $dataFile): mixed => require $dataFile)($path);

        if (! is_array($data)) {
            throw new RuntimeException("Plugin rate file did not return an array: {$relativePath}");
        }

        return $data;
    }

    private function readPluginFile(string $relativePath): string
    {
        $contents = file_get_contents($this->resolvePluginFile($relativePath));

        if ($contents === false) {
            throw new RuntimeException("Could not read plugin file: {$relativePath}");
        }

        return $contents;
    }

    private function resolvePluginFile(string $relativePath): string
    {
        $root = realpath($this->pluginPath());
        $path = realpath($this->pluginPath().DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath));

        if ($root === false || $path === false || ! str_starts_with($path, $root.DIRECTORY_SEPARATOR)) {
            throw new RuntimeException("Plugin source file was not found: {$relativePath}");
        }

        return $path;
    }

    private function pluginPath(): string
    {
        return rtrim(
            $this->configuredPluginPath ?? (string) config('postal-shipping.plugin_path'),
            '/\\'
        );
    }
}
