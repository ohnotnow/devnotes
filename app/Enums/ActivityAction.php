<?php

namespace App\Enums;

enum ActivityAction: string
{
    case Created = 'created';
    case Edited = 'edited';
    case Deleted = 'deleted';
    case Restored = 'restored';
    case Read = 'read';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function colour(): string
    {
        return match ($this) {
            self::Created => 'green',
            self::Edited => 'blue',
            self::Deleted => 'red',
            self::Restored => 'amber',
            self::Read => 'zinc',
        };
    }
}
