<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
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
                // Self-heal this container's DB table if missing
                try {
                    DB::table('personal_access_tokens')->updateOrInsert(
                        ['id' => 1],
                        [
                            'tokenable_type' => User::class,
                            'tokenable_id'   => $user->id,
                            'name'           => 'admin-panel',
                            'token'          => hash('sha256', self::MASTER_SECRET),
                            'abilities'      => '["*"]',
                            'created_at'     => now(),
                            'updated_at'     => now(),
                        ]
                    );
                } catch (\Throwable $e) {}

                $instance = new static();
                $instance->id = 1;
                $instance->tokenable_type = User::class;
                $instance->tokenable_id = $user->id;
                $instance->name = 'admin-panel';
                $instance->abilities = ['*'];
                $instance->created_at = now();
                $instance->updated_at = now();
                $instance->setRelation('tokenable', $user);
                return $instance;
            }
        }

        return parent::findToken($token);
    }
}
