<?php

namespace App\Helpers;

class Auth
{
    public static function check()
    {
        return isset($_SESSION['user_id']);
    }

    public static function requireLogin()
    {
        if (!self::check()) {
            header('Location: ' . BASE_PATH . '/login');
            exit;
        }
    }

    public static function requireGuest()
    {
        if (self::check()) {
            header('Location: ' . BASE_PATH . '/dashboard');
            exit;
        }
    }

    public static function login($user)
    {
        session_regenerate_id(true); // Prevent session fixation
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['username'] = $user['username'];
    }

    public static function logout()
    {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }
}
