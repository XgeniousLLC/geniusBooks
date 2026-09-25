<?php

namespace App\Enums;

enum IntegrationProvider: string
{
    case Xero = 'xero';
    case QuickBooks = 'quickbooks';
    case FreshBooks = 'freshbooks';
    case HubSpot = 'hubspot';
    case ZohoBooks = 'zoho_books';
    case Wave = 'wave';
    case Sage = 'sage';
    case NetSuite = 'netsuite';
    case Myob = 'myob';
    case Kashoo = 'kashoo';

    public function label(): string
    {
        return match ($this) {
            self::Xero => 'Xero',
            self::QuickBooks => 'QuickBooks Online',
            self::FreshBooks => 'FreshBooks',
            self::HubSpot => 'HubSpot',
            self::ZohoBooks => 'Zoho Books',
            self::Wave => 'Wave',
            self::Sage => 'Sage Business Cloud',
            self::NetSuite => 'Oracle NetSuite',
            self::Myob => 'MYOB',
            self::Kashoo => 'Kashoo',
        };
    }

    public function docsUrl(): string
    {
        return match ($this) {
            self::Xero => 'https://developer.xero.com/documentation/',
            self::QuickBooks => 'https://developer.intuit.com/app/developer/qbo/docs/api/accounting/all-entities',
            self::FreshBooks => 'https://www.freshbooks.com/api',
            self::HubSpot => 'https://developers.hubspot.com/docs/api/overview',
            self::ZohoBooks => 'https://www.zoho.com/books/api/v3/',
            self::Wave => 'https://developer.waveapps.com/hc/en-us',
            self::Sage => 'https://developer.sage.com/api/',
            self::NetSuite => 'https://docs.oracle.com/en/cloud/saas/netsuite/',
            self::Myob => 'https://developer.myob.com/api/',
            self::Kashoo => 'https://www.kashoo.com/api/',
        };
    }

    public function apiBaseUrl(): string
    {
        return match ($this) {
            self::Xero => 'https://api.xero.com/api.xro/2.0',
            self::QuickBooks => 'https://quickbooks.api.intuit.com/v3',
            self::FreshBooks => 'https://api.freshbooks.com',
            self::HubSpot => 'https://api.hubapi.com',
            self::ZohoBooks => 'https://www.zohoapis.com/books/v3',
            self::Wave => 'https://gql.waveapps.com/graphql/public',
            self::Sage => 'https://api.sage.com',
            self::NetSuite => 'https://{accountId}.suitetalk.api.netsuite.com/services/rest',
            self::Myob => 'https://api.myob.com/accountright',
            self::Kashoo => 'https://api.kashoo.com',
        };
    }

    public function supportsTwoWaySync(): bool
    {
        return true;
    }

    public static function options(): array
    {
        return array_map(fn (self $p) => [
            'value' => $p->value,
            'label' => $p->label(),
            'docs_url' => $p->docsUrl(),
        ], self::cases());
    }
}
