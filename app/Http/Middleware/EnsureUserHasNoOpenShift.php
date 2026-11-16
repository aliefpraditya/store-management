<?php

namespace App\Http\Middleware;

use App\Enums\ApiErrorCode;
use App\Enums\CashShiftStatus;
use App\Models\CashShift;
use App\Models\Store;
use App\Models\User;
use App\Services\ShiftService;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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

    /** A shift that was counted but not decided is not one the user can close: only waiting is left. */
    private const AWAITING_REVIEW_MESSAGE = 'Shift Anda sudah dihitung dan menunggu keputusan Admin. Anda bisa keluar setelah shift ditutup.';

    public function __construct(private readonly ShiftService $shifts) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();
        $heldShift = $user === null ? null : $this->storeWithOpenShift($request, $user);

        if ($heldShift === null) {
            return $next($request);
        }

        ['store' => $store, 'is_awaiting_review' => $isAwaitingReview] = $heldShift;
        $message = $isAwaitingReview ? self::AWAITING_REVIEW_MESSAGE : self::LOCKED_MESSAGE;

        if ($request->expectsJson()) {
            return response()->json([
                'code' => ApiErrorCode::ShiftIsOpen->value,
                'message' => $message,
                'more_info' => $isAwaitingReview ? 'Shift di kasir toko '.$store->name.' menunggu keputusan Admin.' : 'Tutup shift di kasir toko '.$store->name.'.',
            ], Response::HTTP_LOCKED);
        }

        return redirect()->route('tenant.cashier.index', ['tenant' => $store->code])->with('error', $message);
    }

    /**
     * The first remembered store that still has the user's shift open; remembered stores that no longer do are forgotten.
     * A store whose database cannot be reached stays remembered but does not lock the user, so sign-out never fails
     * because of one broken store.
     *
     * @return array{store: Store, is_awaiting_review: bool}|null
     */
    private function storeWithOpenShift(Request $request, User $user): ?array
    {
        $rememberedCodes = (array) $request->session()->get(self::SESSION_KEY, []);
        $stillOpenCodes = [];
        $firstHeld = null;

        foreach (Store::query()->whereIn('code', $rememberedCodes)->get() as $store) {
            try {
                $heldShift = $store->run(fn (): ?CashShift => $this->shifts->openShiftOf($user));
            } catch (QueryException $exception) {
                tenancy()->end();
                Log::error('Open shift check skipped: store database unreachable.', ['store_code' => $store->code, 'exception' => $exception]);
                $stillOpenCodes[] = $store->code;

                continue;
            }

            if ($heldShift !== null) {
                $stillOpenCodes[] = $store->code;
                $firstHeld ??= ['store' => $store, 'is_awaiting_review' => $heldShift->status === CashShiftStatus::PendingReview];
            }
        }

        if ($stillOpenCodes === []) {
            $request->session()->forget(self::SESSION_KEY);
        } else {
            $request->session()->put(self::SESSION_KEY, $stillOpenCodes);
        }

        return $firstHeld;
    }
}
