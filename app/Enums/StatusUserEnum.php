<?php declare(strict_types=1);

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static OptionOne()
 * @method static static OptionTwo()
 * @method static static OptionThree()
 */
final class StatusUserEnum extends Enum
{
    const All = 'all';
    const ACTIVE = 'active';
    const INACTIVE = 'inactive';
    const BANNED = 'banned';

    public static function getDescription($value): string
    {
        return __('status-user.' . $value);
    }
}
