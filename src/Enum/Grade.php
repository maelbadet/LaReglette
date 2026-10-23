<?php

namespace App\Enum;

enum Grade: int
{
    case Three = 1;
    case Four = 2;
    case Five = 3;
    case FivePlus = 4;
    case SixA = 5;
    case SixAPlus = 6;
    case SixB = 7;
    case SixBPlus = 8;
    case SixC = 9;
    case SixCPlus = 10;
    case SevenA = 11;
    case SevenAPlus = 12;
    case SevenB = 13;
    case SevenBPlus = 14;
    case SevenC = 15;
    case SevenCPlus = 16;
    case EightA = 17;
    case EightAPlus = 18;

    public const MIN = 1;
    public const MAX = 18;

    public function label(): string
    {
        return match ($this) {
            self::Three => '3', self::Four => '4',
            self::Five => '5', self::FivePlus => '5+',
            self::SixA => '6A', self::SixAPlus => '6A+',
            self::SixB => '6B', self::SixBPlus => '6B+',
            self::SixC => '6C', self::SixCPlus => '6C+',
            self::SevenA => '7A', self::SevenAPlus => '7A+',
            self::SevenB => '7B', self::SevenBPlus => '7B+',
            self::SevenC => '7C', self::SevenCPlus => '7C+',
            self::EightA => '8A', self::EightAPlus => '8A+',
        };
    }

    /** @return array<string, int> */
    public static function choices(): array
    {
        $choices = [];
        foreach (self::cases() as $grade) {
            $choices[$grade->label()] = $grade->value;
        }

        return $choices;
    }
}
