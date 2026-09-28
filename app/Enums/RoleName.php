<?php

declare(strict_types=1);

namespace App\Enums;

enum RoleName: string
{
    case SuperAdmin = 'Super Admin';
    case Admin = 'Admin';
    case Accountant = 'Accountant';
    case InventoryManager = 'Inventory Manager';
    case SalesManager = 'Sales Manager';
    case Partner = 'Partner';
    case Viewer = 'Viewer';

    public function label(): string
    {
        return $this->value;
    }
}
