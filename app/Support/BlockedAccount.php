<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class BlockedAccount
{
    public const REASON_PENDING_APPROVAL = 'pending_approval';

    public const REASON_SUSPENDED = 'suspended';

    public static function reason(User $user): string
    {
        $verifiedAt = $user->email_verified_at;
        $deactivatedAt = $user->deactivated_at;

        if ($verifiedAt === null || $deactivatedAt === null) {
            return self::REASON_PENDING_APPROVAL;
        }

        return $deactivatedAt->greaterThan($verifiedAt)
            ? self::REASON_SUSPENDED
            : self::REASON_PENDING_APPROVAL;
    }

    public static function emailVerifiedAt(User $user): ?string
    {
        if ($user->email_verified_at === null) {
            return null;
        }

        return $user->email_verified_at->clone()->utc()->format('Y-m-d\TH:i:s\Z');
    }

    /**
     * @return array{message: string, reason: string, email_verified_at: string|null}
     */
    public static function payload(User $user): array
    {
        return [
            'message' => ApiMessage::DEACTIVATED,
            'reason' => self::reason($user),
            'email_verified_at' => self::emailVerifiedAt($user),
        ];
    }

    public static function response(User $user): JsonResponse
    {
        return response()->json(self::payload($user), Response::HTTP_FORBIDDEN);
    }
}
