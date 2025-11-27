<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Show settings page
     */
    public function showSettings()
    {
        return view('settings');
    }

    /**
     * Show residential clients
     */
    public function showResidentialClients()
    {
        $clients = Client::where('type', 'residential')->get();
        return view('clients_residential', ['clients' => $clients]);
    }

    /**
     * Show business clients
     */
    public function showBusinessClients()
    {
        $clients = Client::where('type', 'business')->get();
        return view('clients_business', ['clients' => $clients]);
    }
}
