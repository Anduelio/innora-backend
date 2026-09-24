<?php

namespace App\Channels;

final class ChannelCatalog
{
    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_keys(self::providers());
    }

    /**
     * @return array<string, array{label: string, base_url: string, fields: list<array{key: string, secret: bool, required: bool}>}>
     */
    public static function providers(): array
    {
        return (array) config('channels.providers', []);
    }

    public static function has(string $code): bool
    {
        return isset(self::providers()[$code]);
    }

    /**
     * @return array{label: string, base_url: string, fields: list<array{key: string, secret: bool, required: bool}>}
     */
    public static function get(string $code): array
    {
        return self::providers()[$code];
    }

    /**
     * @return list<array{code: string, label: string, baseUrl: string, fields: list<array{key: string, secret: bool, required: bool}>}>
     */
    public static function catalog(): array
    {
        $rows = [];

        foreach (self::providers() as $code => $provider) {
            $rows[] = [
                'code' => $code,
                'label' => $provider['label'],
                'baseUrl' => $provider['base_url'],
                'fields' => $provider['fields'],
            ];
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    public static function secretKeys(string $code): array
    {
        $keys = [];

        foreach (self::get($code)['fields'] as $field) {
            if ($field['secret']) {
                $keys[] = $field['key'];
            }
        }

        return $keys;
    }
}
