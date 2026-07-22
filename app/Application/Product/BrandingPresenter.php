<?php

declare(strict_types=1);

namespace Eva\Application\Product;

final readonly class BrandingPresenter
{
    /** @param array<string, mixed> $config */
    public function __construct(private array $config)
    {
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [
            'name' => $this->text('name', 'EVA', 80),
            'tagline' => $this->text('tagline', 'Memória cognitiva documental verificável', 180),
            'primary_color' => $this->color('primary_color', '#17324d'),
            'secondary_color' => $this->color('secondary_color', '#f4f7f9'),
            'accent_color' => $this->color('accent_color', '#1f8a70'),
            'logo_url' => $this->logoUrl(),
        ];
    }

    private function text(string $key, string $default, int $maxLength): string
    {
        $value = trim((string) ($this->config[$key] ?? $default));

        return $value === '' ? $default : mb_substr($value, 0, $maxLength, 'UTF-8');
    }

    private function color(string $key, string $default): string
    {
        $value = (string) ($this->config[$key] ?? $default);

        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1 ? strtolower($value) : $default;
    }

    private function logoUrl(): string
    {
        $value = trim((string) ($this->config['logo_url'] ?? ''));

        if ($value === '' || str_starts_with($value, '/')) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_URL) !== false && str_starts_with($value, 'https://')
            ? $value
            : '';
    }
}
