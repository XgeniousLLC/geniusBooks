<?php

namespace App\Enums;

/**
 * Coarse, role-derived permission keys. Fine-grained per-model authorization
 * is layered on top of these gates as modules land.
 */
final class Permission
{
    public const ManageCompany = 'manage-company';

    public const ManageUsers = 'manage-users';

    public const ManageSettings = 'manage-settings';

    public const ManageFinances = 'manage-finances';

    public const ViewReports = 'view-reports';

    public const ManageSales = 'manage-sales';

    public const ManageExpenses = 'manage-expenses';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::ManageCompany,
            self::ManageUsers,
            self::ManageSettings,
            self::ManageFinances,
            self::ViewReports,
            self::ManageSales,
            self::ManageExpenses,
        ];
    }
}
