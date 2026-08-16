<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    {{-- enctype added for the avatar upload (EduSystem.md 1A). --}}
    <form method="post" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div>
            {{-- Avatar, normalised to a 256px square PNG on upload. --}}
            <div class="mb-6 flex items-center gap-4">
                @if ($user->avatar_path)
                    <img src="{{ Storage::disk('public')->url($user->avatar_path) }}" alt=""
                         class="h-16 w-16 rounded-full object-cover">
                @else
                    <span class="flex h-16 w-16 items-center justify-center rounded-full bg-gray-200 text-xl font-semibold text-gray-500">
                        {{ Str::of($user->name)->explode(' ')->take(2)->map(fn ($p) => Str::substr($p, 0, 1))->implode('') }}
                    </span>
                @endif
                <div>
                    <x-input-label for="avatar" :value="__('Avatar')" />
                    <input id="avatar" name="avatar" type="file" accept="image/*"
                           class="mt-1 block w-full text-sm text-gray-600 file:mr-4 file:rounded-lg file:border-0 file:bg-gray-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-gray-700 hover:file:bg-gray-200">
                    <x-input-error class="mt-2" :messages="$errors->get('avatar')" />
                </div>
            </div>

            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            {{-- Contact details, shown only to people who teach: they are the
                 only users with a public profile card. --}}
            @can('course.create')
                <div class="mb-6 rounded-lg border border-gray-200 bg-gray-50 p-4">
                    <p class="text-sm font-medium text-gray-900">Contact details shown to students</p>
                    <p class="mt-1 text-xs text-gray-500">
                        Students can open your name from any course page. Your email address is always
                        shown so they can reach you about coursework.
                    </p>

                    <div class="mt-4">
                        <x-input-label for="school_email" :value="__('School email')" />
                        <x-text-input id="school_email" name="school_email" type="email" class="mt-1 block w-full"
                                      :value="old('school_email', $user->school_email)"
                                      placeholder="yourname@tarc.edu.my" />
                        <p class="mt-1 text-xs text-gray-500">
                            Shown on your profile instead of your sign-in address. Leave blank to show
                            your sign-in address instead.
                        </p>
                        <x-input-error class="mt-2" :messages="$errors->get('school_email')" />
                    </div>

                    <div class="mt-4">
                        <x-input-label for="phone" :value="__('Phone number (optional)')" />
                        <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full"
                                      :value="old('phone', $user->phone)" placeholder="+60 12-345 6789" />
                        <x-input-error class="mt-2" :messages="$errors->get('phone')" />
                    </div>

                    <label class="mt-3 flex items-start gap-2">
                        <input type="checkbox" name="show_phone" value="1"
                               @checked(old('show_phone', $user->show_phone))
                               class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="text-sm text-gray-700">
                            Show my phone number on my profile
                            <span class="block text-xs text-gray-500">
                                Leave this unticked to keep it private. Students then see "Not shared —
                                please use email".
                            </span>
                        </span>
                    </label>
                </div>
            @endcan

            <x-input-label for="bio" :value="__('Bio')" />
            <textarea id="bio" name="bio" rows="3"
                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('bio', $user->bio) }}</textarea>
            <x-input-error class="mt-2" :messages="$errors->get('bio')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-gray-800">
                        {{ __('Your email address is unverified.') }}

                        <button form="send-verification" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-green-600">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-gray-600"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
