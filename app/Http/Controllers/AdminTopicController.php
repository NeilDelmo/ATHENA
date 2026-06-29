<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AdminTopicController extends Controller
{
    public function index()
{
    return view('research_head.dashboard'); 
}
}
