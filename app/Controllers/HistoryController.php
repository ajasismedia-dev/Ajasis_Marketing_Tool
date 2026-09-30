<?php
namespace App\Controllers;
use App\Core\Controller;
use App\Helpers\Auth;

class HistoryController extends Controller {
    public function index() {
        Auth::requireLogin();
        $this->view('errors/not_implemented', ['page_title' => 'İletişim Geçmişi'], 'main');
    }
}
