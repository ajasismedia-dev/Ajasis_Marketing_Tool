<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\Auth;
use App\Helpers\Security;
use App\Models\User;

class LoginController extends Controller
{
    public function index()
    {
        Auth::requireGuest();
        $this->view('auth/login', ['error' => ''], 'auth');
    }

    public function submit()
    {
        Auth::requireGuest();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Security::checkCsrfToken($_POST['csrf_token'] ?? '');

            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($username) || empty($password)) {
                return $this->view('auth/login', ['error' => 'Lütfen tüm alanları doldurun.'], 'auth');
            }

            $userModel = new User();
            $user = $userModel->findByUsername($username);

            if ($user && password_verify($password, $user['password_hash'])) {
                Auth::login($user);
                $this->redirect('/dashboard');
            } else {
                return $this->view('auth/login', ['error' => 'Kullanıcı adı veya şifre hatalı.'], 'auth');
            }
        }

        $this->redirect('/login');
    }

    public function logout()
    {
        Auth::logout();
        $this->redirect('/login');
    }
}
