<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    /**
     * Find the token instance matching the given token.
     * Supports both database-stored tokens and serverless-resilient signed tokens.
     */
    public static function findToken($token): ?static
    {
        if (empty($token) || !is_string($token)) {
            return null;
        }

        // 1. Check for stateless signed token: s|{userId}|{signature}
        if (str_starts_with($token, 's|')) {
            $parts = explode('|', $token, 3);
            if (count($parts) === 3) {
                $userId = (int) $parts[1];
                $sig = $parts[2];
                $appKey = (string) config('app.key');
                if (!empty($appKey) && $userId > 0) {
                    $expectedSig = hash_hmac('sha256', "albi_auth_{$userId}", $appKey);
                    if (hash_equals($expectedSig, $sig)) {
                        $user = User::find($userId);
                        if ($user) {
                            $instance = new static();
                            $instance->id = 999000 + $userId;
                            $instance->tokenable_type = User::class;
                            $instance->tokenable_id = $user->id;
                            $instance->name = 'stateless-admin-panel';
                            $instance->abilities = ['*'];
                            $instance->setRelation('tokenable', $user);
                            return $instance;
                        }
                    }
                }
            }
        }

        // 2. Standard database lookup
        return parent::findToken($token);
    }
}
