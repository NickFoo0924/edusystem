<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Ifa;
use App\Support\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * MODULE 3 (Ong Shun Yan) exposes: sendNotification.
 *
 * Consumed by Module 4, which calls it to tell a student their quiz has been
 * marked. Any module can use it to reach a user's inbox without knowing how
 * the inbox is stored or how preferences work.
 *
 * The service writes through the same Notifier the Observer uses, so a
 * notification arriving over HTTP obeys the recipient's preferences and the
 * duplicate guard exactly as an internally produced one does. That is the
 * point of routing it through the Notifier instead of writing the row here.
 */
class NotificationApiController extends Controller
{
    /**
     * Types an outside caller is permitted to send.
     *
     * An allow-list, not a free-text field. Without it a caller could invent a
     * type nobody can switch off in their preferences, which would turn this
     * service into a way of bypassing the opt-out.
     */
    private const SENDABLE_TYPES = [
        'grade.recorded',
        'forum.mention',
        'announcement.posted',
        'certificate.issued',
    ];

    public function send(Request $request): JsonResponse
    {
        $validator = validator($request->all(), Ifa::baseRules() + [
            'userId' => ['required', 'integer', 'min:1'],
            'type' => ['required', 'string', 'in:'.implode(',', self::SENDABLE_TYPES)],
            'message' => ['required', 'string', 'max:255'],
            'link' => ['nullable', 'string', 'max:2048', 'url'],
            'reference' => ['nullable', 'string', 'max:64'],
        ]);

        if ($validator->fails()) {
            return Ifa::fail($request, [
                'delivered' => false,
                'errors' => $validator->errors()->all(),
            ]);
        }

        try {
            $user = User::find($request->integer('userId'));

            if ($user === null) {
                return Ifa::fail($request, [
                    'delivered' => false,
                    'message' => 'No user exists with that ID.',
                ], 404);
            }

            $delivered = Notifier::send(
                $user->id,
                $request->string('type')->toString(),
                $request->string('message')->toString(),
                $request->input('link') ?: route('notifications.index'),
                $request->input('reference')
            );

            /*
             * Not delivering is a success, not a failure. The recipient
             * switched this type off, or has already been told this exact
             * thing. The caller needs to know which happened, so `delivered`
             * reports it rather than the status pretending something broke.
             */
            return Ifa::success($request, [
                'delivered' => $delivered,
                'reason' => $delivered
                    ? 'Notification written to the inbox.'
                    : 'Suppressed by the user preference or the duplicate guard.',
            ]);
        } catch (Throwable $e) {
            Log::error('sendNotification failed', ['error' => $e->getMessage()]);

            return Ifa::error($request, ['delivered' => false]);
        }
    }
}
