<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    protected function activeGroup(Request $request): ?object
    {
        $abilities = $request->user()->currentAccessToken()->abilities ?? [];

        $grupaId  = null;
        $cbNumber = null;

        foreach ($abilities as $ability) {
            if (str_starts_with($ability, 'grupa_id:')) {
                $grupaId = (int) str_replace('grupa_id:', '', $ability);
            }
            if (str_starts_with($ability, 'cb_number:')) {
                $cbNumber = str_replace('cb_number:', '', $ability);
            }
        }

        if (!$grupaId) {
            return null;
        }

        return (object) [
            'grupa_id'  => $grupaId,
            'cb_number' => $cbNumber,
        ];
    }
}
