<?php

namespace App\Http\Middleware;

use App\Enums\ApiErrorCode;
use App\Models\Store;
use App\Models\User;
use App\Services\ShiftService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps a cashier at the till: while the user has an open shift they cannot log out or open the central pages,
 * they close the shift first. Store pages stay open, so an admin can still look up stock during a shift.
 *
 * Central pages and logout run outside any store, so the session remembers which stores hold a shift (see
 * {@see RememberOpenShift}); the real shift is checked there, and a code that no longer matches one is dropped.
 */
class EnsureUserHasNoOpenShift
{
    /** Session key holding the codes of the stores where the user has an open shift. */
    public const SESSION_KEY = 'open_shift_stores';

    private const LOCKED_MESSAGE = 'Tutup shift dulu sebelum keluar atau membuka halaman pusat.';

    public function __construct(private readonly ShiftService $shifts) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();
        $store = $user === null ? null : $this->storeWithOpenShift($request, $user);

        if ($store === null) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'code' => ApiErrorCode::ShiftIsOpen->value,
                'message' => self::LOCKED_MESSAGE,
                'more_info' => 'Tutup shift di kasir toko '.$store->name.'.',
            ], Response::HTTP_LOCKED);
        }

        return redirect()->route('tenant.cashier.index', ['tenant' => $store->code])->with('error', self::LOCKED_MESSAGE);
    }

    /**
     * The first remembered store that still has the user's shift open; remembered stores that no longer do are forgotten.
     */
    private function storeWithOpenShift(Request $request, User $user): ?Store
    {
        $rememberedCodes = (array) $request->session()->get(self::SESSION_KEY, []);
        $stillOpenCodes = [];
        $firstOpenStore = null;

        foreach (Store::query()->whereIn('code', $rememberedCodes)->get() as $store) {
            if ($store->run(fn (): bool => $this->shifts->openShiftOf($user) !== null)) {
                $stillOpenCodes[] = $store->code;
                $firstOpenStore ??= $store;
            }
        }

        if ($stillOpenCodes === []) {
            $request->session()->forget(self::SESSION_KEY);
        } else {
            $request->session()->put(self::SESSION_KEY, $stillOpenCodes);
        }

        return $firstOpenStore;
    }
}
