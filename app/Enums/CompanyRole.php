<?php

namespace App\Enums;

/**
 * Tenant roles. Abilities are the coarse permission keys checked via gates.
 */
enum CompanyRole: string
{
    case Owner = 'owner';
    case Accountant = 'accountant';
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner / Admin',
            self::Accountant => 'Accountant',
            self::Staff => 'Staff',
        };
    }

    /**
     * @return list<string>
     */
    public function abilities(): array
    {
        return match ($this) {
            self::Owner => [
                Permission::ManageCompany,
                Permission::ManageUsers,
                Permission::ManageSettings,
                Permission::ManageFinances,
                Permission::ViewReports,
                Permission::ManageSales,
                Permission::ManageExpenses,
            ],
            self::Accountant => [
                Permission::ManageFinances,
                Permission::ViewReports,
                Permission::ManageSales,
                Permission::ManageExpenses,
            ],
            self::Staff => [
                Permission::ManageSales,
                Permission::ManageExpenses,
            ],
        };
    }

    public static function names(): array
    {
        return array_map(fn (self $role) => $role->value, self::cases());
    }

    public static function fromName(string $name): ?self
    {
        return self::tryFrom($name);
    }

    /**
     * Whether any of the given role names grants the ability.
     *
     * @param  iterable<string>  $roleNames
     */
    public static function anyCan(iterable $roleNames, string $ability): bool
    {
        foreach ($roleNames as $name) {
            $role = self::fromName($name);

            if ($role && in_array($ability, $role->abilities(), true)) {
                return true;
            }
        }

        return false;
    }
}
