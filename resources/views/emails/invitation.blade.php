{{--
    LearnSync -- Blade view
    Module 1: Identity, Access & Digital Credentialing
    @author Serena Lim Sze Kee
--}}
{{-- emails/invitation.blade.php --}}
<x-mail::message>
# You have been invited to {{ config('app.name') }}

An administrator has invited you to join {{ config('app.name') }} as a **{{ $role }}**.

Use the button below to choose a password and activate your account. This is the only
way to register &mdash; {{ config('app.name') }} has no public sign-up page.

<x-mail::button :url="$url">
Accept invitation
</x-mail::button>

This invitation expires on **{{ $expiresAt->format('j F Y, g:ia') }}**. After that an
administrator will need to issue you a new one.

If you were not expecting this invitation you can ignore this email; no account is
created until the link is used.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
