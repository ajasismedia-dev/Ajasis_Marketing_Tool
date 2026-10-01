<?php

namespace App\Services\Messaging;

use App\Helpers\Auth;

class TemplateRenderer
{
    /**
     * Whitelist of supported template variables
     */
    public const ALLOWED_VARIABLES = [
        'company_name',
        'sector',
        'district',
        'city',
        'website',
        'sender_name',
        'agency_name'
    ];

    /**
     * Return associative array of whitelisted variables with Turkish descriptions
     *
     * @return array
     */
    public static function getWhitelistedVariables(): array
    {
        return [
            'company_name' => 'Firma Adı',
            'sector'       => 'Sektör',
            'district'     => 'İlçe',
            'city'         => 'Şehir',
            'website'      => 'Web Sitesi',
            'sender_name'  => 'Gönderen Yetkili',
            'agency_name'  => 'Ajans / Şirket Adı'
        ];
    }

    /**
     * Render template string by substituting whitelisted placeholders with provided data.
     * Supports both single `{var}` and double `{{var}}` braces without code execution risk.
     *
     * @param string|null $templateText
     * @param array $company
     * @param string|array|null $senderName
     * @param string|null $agencyName
     * @return string
     */
    public static function render(?string $templateText, array $company = [], $senderName = null, ?string $agencyName = null): string
    {
        if ($templateText === null || $templateText === '') {
            return '';
        }

        // Handle array passed as sender overrides
        if (is_array($senderName)) {
            $agencyName = $senderName['agency_name'] ?? $agencyName;
            $senderName = $senderName['sender_name'] ?? null;
        }

        // Determine sender name
        if ($senderName === null || trim((string)$senderName) === '') {
            $user = (class_exists(\App\Helpers\Auth::class) && method_exists(\App\Helpers\Auth::class, 'user')) ? \App\Helpers\Auth::user() : null;
            $senderName = $user['name'] ?? (defined('SENDER_NAME') ? SENDER_NAME : 'Ajasis');
        }

        // Determine agency name
        if ($agencyName === null || trim((string)$agencyName) === '') {
            $agencyName = defined('BUSINESS_NAME') ? BUSINESS_NAME : 'Ajasis Media';
        }

        // Prepare replacement map for both single and double curly brackets
        $replacements = [
            '{{company_name}}' => trim($company['name'] ?? ''),
            '{company_name}'   => trim($company['name'] ?? ''),
            '{{sector}}'       => trim($company['sector'] ?? ''),
            '{sector}'         => trim($company['sector'] ?? ''),
            '{{district}}'     => trim($company['district'] ?? ''),
            '{district}'       => trim($company['district'] ?? ''),
            '{{city}}'         => trim($company['city'] ?? ''),
            '{city}'           => trim($company['city'] ?? ''),
            '{{website}}'      => trim($company['website'] ?? ''),
            '{website}'        => trim($company['website'] ?? ''),
            '{{sender_name}}'  => trim((string)$senderName),
            '{sender_name}'    => trim((string)$senderName),
            '{{agency_name}}'  => trim((string)$agencyName),
            '{agency_name}'    => trim((string)$agencyName),
        ];

        // Perform safe string replacement
        $rendered = str_replace(array_keys($replacements), array_values($replacements), $templateText);

        return $rendered;
    }
}
