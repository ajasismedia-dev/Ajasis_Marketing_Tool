<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        Auth::requireLogin();
        
        $companyModel = new \App\Models\Company();

        $data = [
            'total_companies' => $companyModel->countAll(),
            'new_leads' => $companyModel->countByStatus('new'),
            'contacted' => $companyModel->countByStatus('contacted') + $companyModel->countByStatus('replied') + $companyModel->countByStatus('proposal'),
            'converted' => $companyModel->countByStatus('customer'),
            'latest_companies' => $companyModel->getLatest(5),
            'page_title' => 'Dashboard'
        ];

        $this->view('dashboard/index', $data, 'main');
    }
}
