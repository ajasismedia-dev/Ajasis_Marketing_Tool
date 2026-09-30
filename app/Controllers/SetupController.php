<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\Security;
use App\Models\User;

class SetupController extends Controller
{
    private $userModel;

    public function __construct()
    {
        $this->userModel = new User();
        // If there's at least 1 user, block access to setup
        if ($this->userModel->countUsers() > 0) {
            http_response_code(404);
            $this->view('errors/404', ['page_title' => 'Sayfa Bulunamadı'], 'main');
            exit;
        }
    }

    public function index()
    {
        $this->view('auth/setup', ['error' => ''], 'auth');
    }

    public function submit()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Security::checkCsrfToken($_POST['csrf_token'] ?? '');

            $name = trim($_POST['name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $password_confirm = $_POST['password_confirm'] ?? '';

            if (empty($name) || empty($username) || empty($password) || empty($password_confirm)) {
                return $this->view('auth/setup', ['error' => 'Lütfen tüm alanları doldurun.'], 'auth');
            }

            if (strlen($password) < 10) {
                return $this->view('auth/setup', ['error' => 'Şifreniz en az 10 karakter olmalıdır.'], 'auth');
            }

            if ($password !== $password_confirm) {
                return $this->view('auth/setup', ['error' => 'Şifreler birbiriyle eşleşmiyor.'], 'auth');
            }

            // Check if username unique (already checked by user count, but good practice)
            if ($this->userModel->findByUsername($username)) {
                return $this->view('auth/setup', ['error' => 'Bu kullanıcı adı zaten alınmış.'], 'auth');
            }

            $password_hash = password_hash($password, PASSWORD_BCRYPT);
            
            if ($this->userModel->create($name, $username, $password_hash)) {
                $this->redirect('/login');
            } else {
                return $this->view('auth/setup', ['error' => 'Kullanıcı oluşturulurken bir hata meydana geldi.'], 'auth');
            }
        }

        $this->redirect('/setup');
    }
}
