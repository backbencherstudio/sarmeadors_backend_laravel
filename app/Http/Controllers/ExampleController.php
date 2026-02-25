<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ExampleController extends Controller
{
    public function settings()
    {
        return view('example.settings');
    }

    public function transactions()
    {
        return view('example.transactions');
    }

    public function resetPassword()
    {
        return view('example.reset-password');
    }

    public function error404()
    {
        return view('example.404');
    }

    public function error500()
    {
        return view('example.500');
    }

    public function buttons()
    {
        return view('example.buttons');
    }

    public function notifications()
    {
        return view('example.notifications');
    }

    public function forms()
    {
        return view('example.forms');
    }

    public function modals()
    {
        return view('example.modals');
    }
    
    public function typography()
    {
        return view('example.typography');
    }
}
