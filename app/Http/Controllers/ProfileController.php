<?php

/**
 * LearnSync -- HTTP controller
 *
 * Module 1: Identity, Access & Digital Credentialing
 *
 * @author Serena Lim Sze Kee
 */

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use App\Models\User;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        // EduSystem.md 1A -- bio and avatar are part of profile management.
        $request->validate([
            'bio' => ['nullable', 'string', 'max:1000'],
            'avatar' => ['nullable', 'image', 'max:2048'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]+$/'],
            'school_email' => ['nullable', 'email', 'max:255'],
        ], [
            'phone.regex' => 'A phone number may contain only digits, spaces and + - ( ).',
        ]);

        $user->bio = $request->input('bio');

        /*
         * Contact details for the public lecturer card. Publishing a number is
         * opt-in and off by default: a lecturer may want one on record without
         * handing it to every student.
         */
        if ($request->user()->can('course.create')) {
            $user->school_email = $request->input('school_email');
            $user->phone = $request->input('phone');
            $user->show_phone = $request->boolean('show_phone') && filled($request->input('phone'));
        }

        if ($request->hasFile('avatar')) {
            $user->avatar_path = $this->storeAvatar($request, $user->avatar_path);
        }

        $user->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Normalise an uploaded avatar to a square 256px PNG.
     *
     * Cropping on upload rather than in CSS means every avatar in every list is
     * the same shape and a modest size, whatever the user supplied.
     */
    private function storeAvatar(Request $request, ?string $previous): string
    {
        $image = (new ImageManager(new Driver()))->read($request->file('avatar')->getRealPath());
        $image->cover(256, 256);

        $path = 'avatars/'.uniqid('avatar_', true).'.png';
        Storage::disk('public')->put($path, (string) $image->toPng());

        // Do not leave the replaced file behind.
        if (filled($previous) && Storage::disk('public')->exists($previous)) {
            Storage::disk('public')->delete($previous);
        }

        return $path;
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    /*
     * A lecturer's public contact card, linked from wherever their name
     * appears.
     *
     * Read-only by design and it must stay that way: there is no update path
     * from here, so nothing a student can reach writes to another user's
     * record. Only lecturers have a card -- asking for a student's or the
     * administrator's id returns 404.
     */
    public function showInstructor(Request $request, User $user): View
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
