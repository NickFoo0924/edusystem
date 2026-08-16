<?php

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
}
