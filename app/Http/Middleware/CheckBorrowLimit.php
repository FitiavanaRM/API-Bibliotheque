<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Borrow;

class CheckBorrowLimit
{
    public const FREE_LIMIT = 2;
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isPremium()) {
            $active = Borrow::where('user_id', $user->id)
                ->whereNull('returned_at')
                ->count();

            if ($active >= self::FREE_LIMIT) {
                return response()->json([
                    'success' => false,
                    'message' => 'Limite d\'emprunts atteinte : un abonnement FREE permet au maximum ' . self::FREE_LIMIT . ' emprunts simultanés. Passez à Premium pour emprunter sans limite.',
                    'errors' => (object) [],
                ], 403);
            }
        }

        return $next($request);
    }
}
