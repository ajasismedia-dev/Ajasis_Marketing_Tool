<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\Auth;

class LeadsController extends Controller
{
    public function index()
    {
        Auth::requireLogin();
        $this->view('errors/not_implemented', ['page_title' => 'Firma Bul'], 'main');
    }
}
