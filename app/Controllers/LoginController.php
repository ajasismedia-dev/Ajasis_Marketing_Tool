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

            // Rate Limit Check
            if (isset($_SESSION['lockout_time']) && time() < $_SESSION['lockout_time']) {
                $remaining = ceil(($_SESSION['lockout_time'] - time()) / 60);
                return $this->view('auth/login', ['error' => "Çok fazla başarısız deneme yaptınız. Lütfen $remaining dakika sonra tekrar deneyin."], 'auth');
            }

            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($username) || empty($password)) {
                return $this->view('auth/login', ['error' => 'Lütfen tüm alanları doldurun.'], 'auth');
            }

            $userModel = new User();
            $user = $userModel->findByUsername($username);

            if ($user && password_verify($password, $user['password_hash'])) {
                // Reset rate limit on success
                unset($_SESSION['login_attempts'], $_SESSION['lockout_time']);
                Auth::login($user);
                $this->redirect('/dashboard');
            } else {
                // Increment failed attempts
                $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
                
                if ($_SESSION['login_attempts'] >= 5) {
                    $_SESSION['lockout_time'] = time() + (15 * 60); // 15 minutes lockout
                    return $this->view('auth/login', ['error' => 'Çok fazla başarısız deneme yaptınız. 15 dakika boyunca engellendiniz.'], 'auth');
                }
                
                return $this->view('auth/login', ['error' => 'Kullanıcı adı veya şifre hatalı.'], 'auth');
            }
        }

        $this->redirect('/login');
    }

    public function logout()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/dashboard');
        }
        Security::checkCsrfToken($_POST['csrf_token'] ?? '');
        Auth::logout();
        $this->redirect('/login');
    }
}
