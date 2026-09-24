<?php

namespace App\Http\Controllers;

use App\Support\GearListExtractor;
use App\Support\GearListParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class CartController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user || ($user->role->role_name ?? '') !== 'client') {
            return response()->json(['error' => 'Not logged in as client']);
        }

        $uid = $user->user_id;
        $action = $request->input('action', $request->query('action', ''));

        return match ($action) {
            'get' => $this->get($uid),
            'add_equipment' => $this->addEquipment($request, $uid),
            'add_crew' => $this->addCrew($request, $uid),
            'remove' => $this->remove($request, $uid),
            'update_days' => $this->updateDays($request, $uid),
            'clear' => $this->clear($uid),
            'copy_from_booking' => $this->copyFromBooking($request, $uid),
            'bulk_add' => $this->bulkAdd($request, $uid),
            'submit_booking' => $this->submitBooking($request, $user),
            default => response()->json(['error' => 'Unknown action']),
        };
    }

    private function get(int $uid): JsonResponse
    {
        $items = DB::table('booking_cart as c')
            ->leftJoin('equipment as e', 'c.equipment_id', '=', 'e.equipment_id')
            ->leftJoin('equipment_categories as ec', 'e.category_id', '=', 'ec.category_id')
            ->leftJoin('crew_members as cm', 'c.crew_id', '=', 'cm.crew_id')
            ->leftJoin('crew_positions as cp', 'c.position_id', '=', 'cp.position_id')
            ->where('c.user_id', $uid)
            ->orderBy('c.added_at')
            ->select(
                'c.*', 'e.equipment_name', 'e.brand', 'e.model', 'e.daily_rate', 'e.image_path',
                'e.availability_status', 'e.requires_operator', 'ec.category_name',
                DB::raw("CONCAT(cm.first_name,' ',cm.last_name) AS crew_name"),
                'cm.photo_path', 'cm.employment_type', 'cm.base_rate_12hr', 'cm.monthly_salary', 'cp.position_name'
            )
            ->get();

        $requiredOps = [];
        foreach ($items as $it) {
            if ($it->item_type === 'equipment' && $it->requires_operator) {
                $ops = DB::table('equipment_operators as eo')
                    ->join('crew_positions as cp', 'eo.position_id', '=', 'cp.position_id')
                    ->leftJoin('crew_members as cm', function ($j) {
                        $j->on('cm.primary_position_id', '=', 'eo.position_id')->where('cm.status', 'active');
                    })
                    ->where('eo.equipment_id', $it->equipment_id)
                    ->groupBy('eo.position_id', 'cp.position_name')
                    ->select('eo.position_id', 'cp.position_name', DB::raw('COALESCE(AVG(cm.base_rate_12hr),0) AS avg_rate'))
                    ->get();
                $requiredOps[$it->equipment_id] = $ops;
            }
        }

        return response()->json(['items' => $items, 'required_operators' => $requiredOps]);
    }

    private function addEquipment(Request $request, int $uid): JsonResponse
    {
        $eid = (int) $request->input('equipment_id', 0);
        $qty = max(1, (int) $request->input('quantity', 1));
        $days = max(1, (int) $request->input('days', 1));
        if (! $eid) {
            return response()->json(['error' => 'No equipment']);
        }

        $equipment = DB::table('equipment')->where('equipment_id', $eid)->first();
        if (! $equipment) {
            return response()->json(['error' => 'Equipment not found']);
        }
        if ($equipment->availability_status !== 'available') {
            return response()->json(['error' => 'Equipment is not available for booking']);
        }

        // Only accessory_id is ever trusted from the client — name/price are client-supplied
        // in the raw payload but must never be persisted from here; submitBooking() re-derives
        // both from the accessories table at insert time, so storing them here would be pointless
        // (and previously let a tampered daily_rate ride all the way into the final cost estimate).
        $accRaw = trim($request->input('accessories_json', ''));
        $validIds = [];
        if ($accRaw) {
            $decoded = json_decode($accRaw, true);
            if (is_array($decoded) && ! empty($decoded)) {
                $accIds = array_values(array_unique(array_filter(array_map(
                    fn ($a) => (int) ($a['accessory_id'] ?? 0), $decoded
                ))));
                $validIds = $accIds ? DB::table('accessories')->whereIn('accessory_id', $accIds)->pluck('accessory_id')->all() : [];
            }
        }

        $exists = DB::table('booking_cart')->where('user_id', $uid)->where('equipment_id', $eid)->value('cart_id');
        if ($exists) {
            DB::table('booking_cart')->where('cart_id', $exists)->update(['quantity' => $qty, 'days' => $days]);
            $cartId = $exists;
        } else {
            DB::table('booking_cart')->insert(['user_id' => $uid, 'item_type' => 'equipment', 'equipment_id' => $eid, 'quantity' => $qty, 'days' => $days]);
            $cartId = DB::getPdo()->lastInsertId();
        }

        // Always replace with the server-validated set for this cart row, same as the old
        // accessories_json column did on every add/update.
        DB::table('booking_cart_accessories')->where('cart_id', $cartId)->delete();
        if ($validIds) {
            DB::table('booking_cart_accessories')->insert(array_map(
                fn ($accId) => ['cart_id' => $cartId, 'accessory_id' => $accId], $validIds
            ));
        }

        return response()->json(['ok' => true]);
    }

    private function addCrew(Request $request, int $uid): JsonResponse
    {
        $cid = (int) $request->input('crew_id', 0);
        $pid = (int) $request->input('position_id', 0);
        $days = max(1, (int) $request->input('days', 1));
        if (! $cid) {
            return response()->json(['error' => 'No crew']);
        }

        $exists = DB::table('booking_cart')->where('user_id', $uid)->where('crew_id', $cid)->value('cart_id');
        if ($exists) {
            DB::table('booking_cart')->where('cart_id', $exists)->update(['days' => $days]);
        } else {
            DB::table('booking_cart')->insert(['user_id' => $uid, 'item_type' => 'crew', 'crew_id' => $cid, 'position_id' => $pid ?: null, 'days' => $days]);
        }

        return response()->json(['ok' => true]);
    }

    private function remove(Request $request, int $uid): JsonResponse
    {
        $cid = (int) $request->input('cart_id', 0);
        DB::table('booking_cart')->where('cart_id', $cid)->where('user_id', $uid)->delete();

        return response()->json(['ok' => true]);
    }

    private function updateDays(Request $request, int $uid): JsonResponse
    {
        $cid = (int) $request->input('cart_id', 0);
        $days = max(1, (int) $request->input('days', 1));
        DB::table('booking_cart')->where('cart_id', $cid)->where('user_id', $uid)->update(['days' => $days]);

        return response()->json(['ok' => true]);
    }

    private function clear(int $uid): JsonResponse
    {
        DB::table('booking_cart')->where('user_id', $uid)->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * "Book Again" (Part 11) — not a full re-run of the admin's duplicate-booking flow (that
     * also copies specific crew members, which clients never pick directly). Just bulk-adds a
     * past completed booking's still-available equipment into the client's cart; days default
     * to 1 since the client hasn't chosen new shoot dates yet — they set those during the normal
     * checkout flow same as any other cart submission.
     */
    private function copyFromBooking(Request $request, int $uid): JsonResponse
    {
        $bookingId = (int) $request->input('booking_id', 0);

        $booking = DB::table('bookings as b')
            ->join('clients as c', 'b.client_id', '=', 'c.client_id')
            ->where('b.booking_id', $bookingId)->where('c.user_id', $uid)
            ->select('b.booking_id', 'b.booking_status')
            ->first();
        if (! $booking) {
            return response()->json(['error' => 'Booking not found']);
        }
        if ($booking->booking_status !== 'completed') {
            return response()->json(['error' => 'Only completed bookings can be booked again']);
        }

        $lines = DB::table('booking_equipment as be')
            ->join('equipment as e', 'be.equipment_id', '=', 'e.equipment_id')
            ->where('be.booking_id', $bookingId)
            ->select('be.equipment_id', 'be.quantity', 'e.equipment_name', 'e.availability_status')
            ->get();

        $copied = 0;
        $skipped = [];
        foreach ($lines as $line) {
            if ($line->availability_status !== 'available') {
                $skipped[] = $line->equipment_name;
                continue;
            }

            $exists = DB::table('booking_cart')->where('user_id', $uid)->where('equipment_id', $line->equipment_id)->value('cart_id');
            if ($exists) {
                DB::table('booking_cart')->where('cart_id', $exists)->update(['quantity' => $line->quantity]);
            } else {
                DB::table('booking_cart')->insert([
                    'user_id' => $uid, 'item_type' => 'equipment', 'equipment_id' => $line->equipment_id,
                    'quantity' => $line->quantity, 'days' => 1,
                ]);
            }
            $copied++;
        }

        if (! $copied && ! $skipped) {
            return response()->json(['error' => 'This booking had no equipment to copy.']);
        }

        return response()->json(['ok' => true, 'copied' => $copied, 'skipped' => $skipped]);
    }

    /**
     * Bulk-add from an uploaded gear list (Word/Excel/CSV) — the same "loop lines, skip what
     * doesn't qualify, upsert the rest" shape as copyFromBooking() above, except the lines
     * come from a client-authored file instead of a past booking, and misses are reported
     * back (never silently dropped) via the `flagged` array.
     */
    private const GEAR_LIST_MAX_SIZE_KB = 2048; // 2MB — these are text lists, not media

    private const GEAR_LIST_ALLOWED_EXT = ['csv', 'txt', 'xlsx', 'xls', 'doc', 'docx'];

    private function bulkAdd(Request $request, int $uid): JsonResponse
    {
        $file = $request->file('file');
        if (! $file || ! $file->isValid()) {
            return response()->json(['error' => 'Choose a file to upload.']);
        }

        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, self::GEAR_LIST_ALLOWED_EXT, true)) {
            return response()->json(['error' => 'Please upload a Word, Excel, or CSV/text file.']);
        }
        if ($file->getSize() > self::GEAR_LIST_MAX_SIZE_KB * 1024) {
            return response()->json(['error' => 'File too large. Max 2MB.']);
        }

        $days = max(1, (int) $request->input('days', 1));

        try {
            $lines = GearListExtractor::extractLines($file);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Could not read that file. Please check it isn\'t corrupted or password-protected.']);
        }

        if (! $lines) {
            return response()->json(['error' => 'No readable lines found in that file.']);
        }

        $parsed = GearListParser::parse($lines);
        $rows = $parsed['rows'];
        if (! $rows) {
            return response()->json(['error' => 'No equipment lines found in that file.']);
        }

        $added = 0;
        $flagged = [];

        foreach ($rows as $row) {
            if ($row['external']) {
                $flagged[] = ['line' => $row['raw'], 'reason' => 'external_source', 'category' => $row['category']];
                continue;
            }

            $matches = $this->findMatchingEquipment($row['text']);
            if ($matches->count() === 0) {
                $flagged[] = ['line' => $row['raw'], 'reason' => 'not_found', 'category' => $row['category']];
                continue;
            }
            if ($matches->count() > 1) {
                $flagged[] = ['line' => $row['raw'], 'reason' => 'ambiguous', 'category' => $row['category']];
                continue;
            }

            $equipment = $matches->first();
            if ($equipment->availability_status !== 'available') {
                $flagged[] = ['line' => $row['raw'], 'reason' => 'unavailable', 'category' => $row['category']];
                continue;
            }

            $exists = DB::table('booking_cart')->where('user_id', $uid)->where('equipment_id', $equipment->equipment_id)->value('cart_id');
            if ($exists) {
                DB::table('booking_cart')->where('cart_id', $exists)->update(['quantity' => $row['quantity'], 'days' => $days]);
            } else {
                DB::table('booking_cart')->insert([
                    'user_id' => $uid, 'item_type' => 'equipment', 'equipment_id' => $equipment->equipment_id,
                    'quantity' => $row['quantity'], 'days' => $days,
                ]);
            }
            $added++;
        }

        if (! $added && ! $flagged) {
            return response()->json(['error' => 'The file had no usable rows.']);
        }

        return response()->json(['ok' => true, 'added' => $added, 'flagged' => $flagged, 'truncated' => $parsed['truncated']]);
    }

    /**
     * A gear-list line is a descriptive phrase ("Sony FX3 and lens set, tripods"), not a short
     * search query — so unlike the catalog's own search (EquipmentController::index(), which
     * checks "does equipment_name contain what the user typed"), this checks the other
     * direction: "does the line contain a real equipment_name/brand/model/serial_number as a
     * substring". A NULL brand/model naturally never matches (CONCAT with NULL is NULL in
     * MySQL), so this is safe against nullable columns without extra guards.
     */
    private function findMatchingEquipment(string $text): Collection
    {
        // CHAR_LENGTH guards matter here: a stray one/two-letter brand or model value (bad
        // data, but real data is never guaranteed clean) would otherwise substring-match
        // almost any line of text and silently poison the results.
        return DB::table('equipment')
            ->where('availability_status', '!=', 'retired')
            ->where(function ($w) use ($text) {
                $w->whereRaw('CHAR_LENGTH(equipment_name) >= 3 AND ? LIKE CONCAT(\'%\', equipment_name, \'%\')', [$text])
                    ->orWhereRaw('CHAR_LENGTH(brand) >= 3 AND ? LIKE CONCAT(\'%\', brand, \'%\')', [$text])
                    ->orWhereRaw('CHAR_LENGTH(model) >= 3 AND ? LIKE CONCAT(\'%\', model, \'%\')', [$text])
                    ->orWhereRaw('CHAR_LENGTH(serial_number) >= 3 AND ? LIKE CONCAT(\'%\', serial_number, \'%\')', [$text]);
            })
            ->get();
    }

    private function submitBooking(Request $request, $user): JsonResponse
    {
        $uid = $user->user_id;

        try {
            $items = DB::table('booking_cart as c')
                ->leftJoin('equipment as e', 'c.equipment_id', '=', 'e.equipment_id')
                ->leftJoin('crew_members as cm', 'c.crew_id', '=', 'cm.crew_id')
                ->where('c.user_id', $uid)
                ->select('c.*', 'e.daily_rate', 'e.requires_operator', 'cm.base_rate_12hr')
                ->get();

            if ($items->isEmpty()) {
                return response()->json(['error' => 'Cart is empty']);
            }

            if (! $request->boolean('tc_accepted') || ! $request->boolean('pp_accepted')) {
                return response()->json(['error' => 'You must agree to the Terms and Conditions and Privacy Policy before submitting.']);
            }

            $projTitle = $request->input('project_title', '');
            $projType = $request->input('project_type', 'commercial');
            $dateStart = $request->input('shoot_date_start', now()->toDateString());
            $dateEnd = $request->input('shoot_date_end', now()->toDateString());
            $location = $request->input('shoot_location', '');
            $notes = $request->input('notes', '');
            $zone = $request->input('location_zone', '');
            $lat = (float) $request->input('location_lat', 0);
            $lng = (float) $request->input('location_lng', 0);
            $zoneMultMap = ['manila' => 1.0, 'luzon' => 1.5, 'luzon_far' => 2.0];
            $multiplier = $zoneMultMap[$zone] ?? 1.0;

            // Client bookings: no transport cost yet (admin assigns later)
            $latSql = ($lat != 0 || $lng != 0) ? $lat : null;
            $lngSql = ($lat != 0 || $lng != 0) ? $lng : null;

            $cid = DB::table('clients')->where('user_id', $uid)->value('client_id');
            if (! $cid) {
                $u = DB::table('users')->where('user_id', $uid)->first();
                $cid = DB::table('clients')->insertGetId([
                    'user_id' => $uid,
                    'contact_person' => trim($u->first_name . ' ' . $u->last_name),
                    'email' => $u->email,
                    'phone' => $u->phone ?? '',
                    'client_type' => 'first_time',
                ]);
            } elseif (! DB::table('clients')->where('client_id', $cid)->value('is_active')) {
                return response()->json(['error' => 'Your account has been deactivated. Please contact FilmSpec for assistance.']);
            }

            $numDays = max(1, (int) (new \DateTime($dateStart))->diff(new \DateTime($dateEnd))->days + 1);

            $year = date('Y');
            $lastNum = (int) DB::table('bookings')
                ->where('booking_reference', 'like', "FS-$year-%")
                ->selectRaw("COALESCE(MAX(CAST(SUBSTRING_INDEX(booking_reference,'-',-1) AS UNSIGNED)),0) AS m")
                ->value('m');
            $ref = 'FS-' . $year . '-' . str_pad($lastNum + 1, 4, '0', STR_PAD_LEFT);

            $bid = DB::table('bookings')->insertGetId([
                'booking_reference' => $ref,
                'client_id' => $cid,
                'booking_type' => 'package',
                'project_title' => $projTitle,
                'project_type' => $projType,
                'shoot_date_start' => $dateStart,
                'shoot_date_end' => $dateEnd,
                'shoot_location' => $location,
                'notes' => $notes,
                'created_by' => $uid,
                'transportation_cost' => 0,
                'delivery_address' => '',
                'location_zone' => $zone ?: null,
                'transport_multiplier' => 1.00,
                'location_lat' => $latSql,
                'location_lng' => $lngSql,
            ]);

            if (! $bid) {
                return response()->json(['error' => 'Failed to create booking record.']);
            }

            DB::table('terms_acceptances')->insert([
                'user_id' => $uid, 'booking_id' => $bid,
                'tc_version' => config('filmspec.tc_version'), 'pp_version' => config('filmspec.pp_version'),
                'accepted_at' => now(), 'ip_address' => $request->ip(),
            ]);

            $equipTotal = 0;
            $accTotal = 0;
            foreach ($items as $it) {
                if ($it->item_type === 'equipment' && $it->equipment_id) {
                    $rate = (float) $it->daily_rate;
                    $sub = $it->quantity * $numDays * $rate;
                    $equipTotal += $sub;
                    // subtotal is a STORED GENERATED column (quantity*days*daily_rate) — must not be set explicitly
                    DB::table('booking_equipment')->insert([
                        'booking_id' => $bid, 'equipment_id' => $it->equipment_id, 'quantity' => $it->quantity,
                        'days' => $numDays, 'daily_rate' => $rate,
                    ]);

                    $accIds = DB::table('booking_cart_accessories')->where('cart_id', $it->cart_id)->pluck('accessory_id')->all();
                    if ($accIds) {
                        // Price and name always come from the accessories table, never from
                        // the client-supplied JSON — see addEquipment()'s comment.
                        $realAccessories = DB::table('accessories')->whereIn('accessory_id', $accIds)->get()->keyBy('accessory_id');
                        foreach ($accIds as $accId) {
                            $real = $realAccessories->get($accId);
                            if (! $real) {
                                continue;
                            }
                            $accRate = (float) $real->daily_rate;
                            $accSub = $numDays * $accRate;
                            $accTotal += $accSub;
                            DB::table('booking_accessories')->insertOrIgnore([
                                'booking_id' => $bid, 'accessory_id' => $accId, 'quantity' => 1, 'days' => $numDays,
                                'daily_rate' => $accRate, 'is_included' => 0, 'subtotal' => $accSub,
                                'notes' => 'Added with equipment: ' . $real->accessory_name,
                            ]);
                        }
                    }
                }
                // Crew items from cart are intentionally skipped — admin assigns crew
            }

            $ceRef = 'CE-' . $year . '-' . str_pad($bid, 4, '0', STR_PAD_LEFT);
            $grandForCe = $equipTotal + $accTotal;
            // Same VAT-inclusive extraction as BookingCosting::extractVat() — via the configurable
            // rate, not a hardcoded 12/112, so this stays correct if filmspec.vat_rate ever changes.
            $vatRate = (float) config('filmspec.vat_rate');
            $vat = round($grandForCe * $vatRate / (1 + $vatRate), 2);
            $netEx = $grandForCe - $vat;

            DB::table('cost_estimates')->insert([
                'booking_id' => $bid, 'ce_reference' => $ceRef, 'generated_by' => $uid,
                'equipment_total' => $equipTotal, 'crew_total' => 0, 'accessories_total' => $accTotal,
                'subtotal' => $netEx, 'vat_amount' => $vat, 'grand_total' => $grandForCe,
            ]);
            DB::table('bookings')->where('booking_id', $bid)->update(['final_amount' => 0, 'total_amount' => 0, 'vat_amount' => 0]);
            DB::table('quotation_log')->insert(['booking_id' => $bid, 'log_type' => 'created', 'logged_by' => $uid, 'new_status' => 'pending', 'log_date' => now()]);

            DB::table('booking_cart')->where('user_id', $uid)->delete();

            return response()->json(['ok' => true, 'booking_reference' => $ref, 'booking_id' => $bid, 'total' => $equipTotal]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Booking failed: ' . $e->getMessage()]);
        }
    }
}
