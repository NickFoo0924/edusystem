<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The read-only contact card for a lecturer.
 *
 * A student who needs to reach the person teaching them should not have to hunt
 * for an address, so every place a lecturer is named links here.
 *
 * Strictly read-only. There is no edit, update or destroy action on this
 * controller at all -- a lecturer changes their own details through their
 * profile page and nowhere else. Nothing a student can reach writes to another
 * user's record.
 */
class InstructorProfileController extends Controller
{
    public function show(Request $request, User $user): View
    {
        /*
         * Only people who teach have a card. Students must never be browsable
         * this way: Section 7 forbids a student viewing another student's
         * details, and an admin's contact details are not a student's business
         * either.
         */
        abort_unless($user->hasPublicProfile(), 404);

        // A deactivated or deleted account is not a contact.
        abort_if($user->trashed() || ! $user->is_active, 404);

        $user->loadCount('coursesTeaching');

        return view('instructors.show', [
            'instructor' => $user,
            'courses' => $user->coursesTeaching()->withCount('students')->orderBy('code')->get(),
            // Resolved through the model so the opt-in cannot be sidestepped.
            'phone' => $user->publicPhone(),
            // Whether the viewer is currently taught by this lecturer, which is
            // worth saying on the card.
            'sharesCourse' => $request->user()->can('course.enroll')
                && $request->user()->courses()
                    ->whereIn('courses.id', $user->coursesTeaching()->select('id'))
                    ->exists(),
        ]);
    }
}
