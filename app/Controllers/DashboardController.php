<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\Auth;
use App\Models\Company;
use App\Models\Communication;
use App\Models\FollowUp;

class DashboardController extends Controller
{
    public function index()
    {
        Auth::requireLogin();
        
        $companyModel = new Company();
        $commModel = new Communication();
        $fuModel = new FollowUp();

        $data = [
            'total_companies' => $companyModel->countAll(),
            'new_leads' => $companyModel->countByStatus('new'),
            'contacted' => $companyModel->countByStatus('contacted') + $companyModel->countByStatus('replied') + $companyModel->countByStatus('proposal'),
            'converted' => $companyModel->countByStatus('customer'),
            'latest_companies' => $companyModel->getLatest(5),
            
            // Sales Operations Data
            'today_followups' => $fuModel->getDueToday(),
            'overdue_followups' => $fuModel->getOverdue(),
            'recent_activities' => $commModel->getRecentActivity(8),
            'pipeline' => [
                'new' => $companyModel->countByStatus('new'),
                'contacted' => $companyModel->countByStatus('contacted'),
                'replied' => $companyModel->countByStatus('replied'),
                'proposal' => $companyModel->countByStatus('proposal'),
                'customer' => $companyModel->countByStatus('customer'),
                'negative' => $companyModel->countByStatus('negative')
            ],
            'page_title' => 'Dashboard'
        ];

        $this->view('dashboard/index', $data, 'main');
    }
}
