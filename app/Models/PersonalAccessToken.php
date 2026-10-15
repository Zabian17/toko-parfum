<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    public const MASTER_TOKEN = '1|AlbiParfumMasterKey2026';
    public const MASTER_SECRET = 'AlbiParfumMasterKey2026';

    /**
     * Find the token instance matching the given token.
     * Supports standard Sanctum tokens + serverless-permanent master token.
     */
    public static function findToken($token): ?static
    {
        if (empty($token) || !is_string($token)) {
            return null;
        }

        // Serverless-resilient master token for Super Admin
        if ($token === self::MASTER_TOKEN) {
            $user = User::find(1) ?? User::where('email', 'admin@albiparfum.com')->first();
            if ($user) {
                // Build the master token in memory; serverless instances cannot rely on token-table writes.
                $instance = new static();
                $instance->forceFill([
                    'id'             => 1,
                    'tokenable_type' => User::class,
                    'tokenable_id'   => $user->id,
                    'name'           => 'admin-panel',
                    'token'          => hash('sha256', self::MASTER_SECRET),
                    'abilities'      => ['*'],
                ]);
                $instance->exists = true;

                $instance->setRelation('tokenable', $user);
                return $instance;
            }
        }

        return parent::findToken($token);
    }
}
