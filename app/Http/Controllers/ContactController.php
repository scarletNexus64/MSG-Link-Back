<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserContact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ContactController extends Controller
{
    /**
     * Synchronize user contacts with the app
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sync(Request $request)
    {
        $request->validate([
            'contacts' => 'required|array',
            'contacts.*' => 'string',
        ]);

        $userId = Auth::id();
        $phoneNumbers = $request->input('contacts', []);

        Log::info('[CONTACTS] Sync request', [
            'user_id' => $userId,
            'contacts_count' => count($phoneNumbers),
        ]);

        try {
            // Find users with matching phone numbers
            $matchedUsers = User::whereIn('phone', $phoneNumbers)
                ->where('id', '!=', $userId) // Exclude the current user
                ->get(['id', 'phone']);

            Log::info('[CONTACTS] Found matches', [
                'matched_count' => $matchedUsers->count(),
            ]);

            $contactIds = [];

            // Create user_contacts relationships
            DB::beginTransaction();
            try {
                foreach ($matchedUsers as $matchedUser) {
                    // Use updateOrCreate to avoid duplicates
                    UserContact::updateOrCreate(
                        [
                            'user_id' => $userId,
                            'contact_user_id' => $matchedUser->id,
                        ],
                        [
                            'phone_number' => $matchedUser->phone,
                        ]
                    );

                    $contactIds[] = $matchedUser->id;
                }

                DB::commit();

                Log::info('[CONTACTS] Sync completed', [
                    'user_id' => $userId,
                    'contacts_saved' => count($contactIds),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Contacts synchronized successfully',
                    'contacts_count' => count($contactIds),
                    'contact_ids' => $contactIds,
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('[CONTACTS] Database error', [
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('[CONTACTS] Sync error', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to synchronize contacts',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get user's synced contacts
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $userId = Auth::id();

        try {
            $contacts = UserContact::where('user_id', $userId)
                ->with('contactUser:id,first_name,last_name,username,phone,avatar')
                ->get();

            return response()->json([
                'success' => true,
                'contacts' => $contacts->map(function ($contact) {
                    return [
                        'id' => $contact->contactUser->id,
                        'name' => $contact->contactUser->first_name . ' ' . $contact->contactUser->last_name,
                        'username' => $contact->contactUser->username,
                        'phone' => $contact->contactUser->phone,
                        'avatar' => $contact->contactUser->avatar,
                        'synced_at' => $contact->created_at,
                    ];
                }),
            ]);

        } catch (\Exception $e) {
            Log::error('[CONTACTS] Index error', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve contacts',
            ], 500);
        }
    }
}
