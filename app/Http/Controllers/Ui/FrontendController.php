<?php

namespace App\Http\Controllers\Ui;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FrontendController extends Controller
{

    public function index(Request $request): RedirectResponse
    {
        return redirect('/sign-in');
    }

    public function signIn(): Response
    {
        return Inertia::render('frontend/pages/auth/SignInPage');
    }

    public function register(): Response
    {
        return Inertia::render('frontend/pages/auth/RegisterPage');
    }

    public function forgotPassword(): Response
    {
        return Inertia::render('frontend/pages/auth/ForgotPasswordPage');
    }

    public function resetPassword(string $token): Response
    {
        return Inertia::render('frontend/pages/auth/ResetPasswordPage', [
            'token' => $token,
        ]);
    }

    public function reports(): Response
    {
        return Inertia::render('common/reports/ReportsPage');
    }

    public function siteInfo(): Response
    {
        return Inertia::render('frontend/pages/site-info/SiteInfoPage');
    }

  
}
