<?php
namespace App\Controllers;
use App\Core\Controller;
use App\Helpers\Auth;

class CompaniesController extends Controller {
    public function index() {
        Auth::requireLogin();
        $this->view('errors/not_implemented', ['page_title' => 'Firmalar'], 'main');
    }
}
