<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        Auth::requireLogin();
        
        $data = [
            'total_companies' => 0,
            'new_leads' => 0,
            'contacted' => 0,
            'converted' => 0,
            'page_title' => 'Dashboard'
        ];

        $this->view('dashboard/index', $data, 'main');
    }
}
