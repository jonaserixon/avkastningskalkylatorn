<?php declare(strict_types=1);

namespace Avk\Enum;

enum CommandOptionName: string
{
    case EXPORT_CSV = 'export-csv';
    case BANK = 'bank';
    case ACCOUNT = 'account';
    case DATE_FROM = 'date-from';
    case DATE_TO = 'date-to';
    case ASSET = 'asset';
    case ISIN = 'isin';
    case CURRENT_HOLDINGS = 'current-holdings';
    case VERBOSE = 'verbose';
    case OVERVIEW = 'overview';
    case DISPLAY_LOG = 'display-log';
    case TWR = 'twr';
    case TYPE = 'type';
    case FEE = 'fee';
    case CASH_FLOW = 'cash-flow';
}
