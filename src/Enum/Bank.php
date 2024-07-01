<?php declare(strict_types=1);

namespace Avk\Enum;

enum Bank: string
{
    case AVANZA = 'AVANZA';
    case NORDNET = 'NORDNET';
    case NOT_SPECIFIED = 'NOT_SPECIFIED';
}
