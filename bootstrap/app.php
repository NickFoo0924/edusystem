<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsurePasswordIsChanged;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
         * Runs on every web request. A deactivated or locked account is signed
         * out rather than merely being shown empty pages (EduSystem.md 1A).
         */
        $middleware->web(append: [
            EnsureAccountIsActive::class,
            EnsurePasswordIsChanged::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /*
         * A stale tab.
         *
         * A browser holds one login at a time, so signing in as somebody else
         * in a second tab replaces the session everywhere. The first tab is
         * still showing a form carrying the previous session's CSRF token, and
         * submitting it raises TokenMismatchException.
         *
         * That refusal is correct and is what stops the stale tab acting as
         * the new user -- but Laravel's default answer is a bare "419 Page
         * Expired", which reads like a fault rather than an explanation. Send
         * them to the login screen and say what happened instead.
         */
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            /*
             * Matched on the converted exception rather than on
             * TokenMismatchException: the framework runs prepareException()
             * before consulting these callbacks, so by this point a CSRF
             * failure has already become an HttpException carrying 419.
             */
            if ($e->getStatusCode() !== 419) {
                return null;
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Your session changed in another tab. Sign in again.',
                ], 419);
            }

            /*
             * Two different situations reach here, and sending both to the
             * login screen would be wrong. If the session simply expired,
             * nobody is signed in and login is the right destination. If it was
             * replaced by a different user signing in, this browser *is*
             * authenticated -- the guest middleware would bounce them straight
             * off the login page and the explanation would be lost with it, so
             * they go to the dashboard and are told who they are now.
             */
            if ($request->user()) {
                return redirect()->route('dashboard')->with('error',
                    'That page was left open while another sign-in replaced the session, so the '
                    .'action was not carried out. This browser is now signed in as '
                    .$request->user()->name.'.');
            }

            return redirect()->guest(route('login'))->with('status',
                'This page was left open until the session expired, so the action was not '
                .'carried out. Sign in and try again.');
        });
    })->create();
