<?php

namespace App\Enums;

enum MayaBillerState: string
{
    case New = 'NEW';
    case Processing = 'PROCESSING';
    case Authorized = 'AUTHORIZED';
    case Posting = 'POSTING';
    case Failed = 'FAILED';
    case Fulfilled = 'FULFILLED';
    case PostingFailed = 'POSTING_FAILED';

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::New => in_array($next, [self::Processing, self::Authorized, self::Failed], true),
            self::Processing => in_array($next, [self::Authorized, self::Failed], true),
            self::Authorized => in_array($next, [self::Posting, self::Failed], true),
            self::Posting => in_array($next, [self::Fulfilled, self::PostingFailed, self::Failed], true),
            self::Fulfilled, self::Failed, self::PostingFailed => false,
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Fulfilled, self::Failed, self::PostingFailed], true);
    }
}
