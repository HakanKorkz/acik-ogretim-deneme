<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Yöneticinin ad, e-posta ve şifre ayarları. Formlar Fortify'ın
     * `user-profile-information.update` ve `user-password.update` uçlarına gider.
     */
    public function edit(): Response
    {
        return Inertia::render('admin/profile');
    }
}
