<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class SiretExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('siret_format', [$this, 'formatSiret']),
        ];
    }

    public function formatSiret(?string $siret): string
    {
        if ($siret === null) {
            return '';
        }

        $digits = preg_replace('/\s+/', '', $siret) ?? $siret;
        if (strlen($digits) !== 14) {
            return $siret;
        }

        return sprintf(
            '%s %s %s %s',
            substr($digits, 0, 3),
            substr($digits, 3, 3),
            substr($digits, 6, 3),
            substr($digits, 9, 5)
        );
    }
}
