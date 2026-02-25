@extends('layouts.master')

@section('title', 'Softvence :: Welcome')
@section('page-title', 'Welcome')

@section('content')
<style>
    body {
        font-family: 'Arial', sans-serif;
        background-color: #f4f6f9;
        margin: 0;
        padding: 0;
    }

    .content-wrapper {
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 90vh;
        padding: 1rem;
        background: linear-gradient(135deg, #396fb4, #00608d);
        color: #fff;
    }
    .left-border-gap {
        margin-left: 16px;
    }
    .welcome-container {
        text-align: center;
        padding: 2.5rem 2rem;
        width: 100%;
        max-width: 800px;
        border-radius: 12px;
        background: rgba(0, 0, 0, 0.4);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.25);
    }

    .welcome-container h1 {
        font-size: 2.5rem;
        margin-bottom: 1rem;
        line-height: 1.2;
    }

    .welcome-container p {
        font-size: 1.2rem;
        margin-bottom: 2rem;
        opacity: 0.9;
    }

    .icon-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 1.2rem;
        margin-top: 2rem;
    }

    .icon-box {
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        padding: 1.3rem 1rem;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 10px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.25);
    }

    .icon-box i {
        font-size: 2.2rem;
        margin-bottom: 0.5rem;
        color: #fff;
    }

    .icon-box span {
        font-size: 1rem;
        color: #fff;
        font-weight: 500;
    }

    .content {
        padding-left: 0;
        padding-right: 0;
    }

    /* =======================
       Responsive Breakpoints
       ======================= */

    @media (max-width: 768px) {
        .welcome-container {
            padding: 2rem 1.5rem;
        }

        .welcome-container h1 {
            font-size: 2rem;
        }

        .welcome-container p {
            font-size: 1.05rem;
        }

        .icon-box i {
            font-size: 2rem;
        }
    }

    @media (max-width: 480px) {
        .welcome-container h1 {
            font-size: 1.7rem;
        }

        .welcome-container p {
            font-size: 0.95rem;
        }

        .icon-grid {
            grid-template-columns: 1fr 1fr;
        }

        .icon-box {
            padding: 1.1rem 0.8rem;
        }

        .icon-box span {
            font-size: 0.9rem;
        }
    }
</style>

<div class="content-wrapper">
    <div class="welcome-container">
        <h1 class="text-white"><b>Welcome to the App Admin Portal!</b></h1>
        <p>Manage your platform effectively with our easy-to-use interface.</p>

        <div class="icon-grid">
            <div class="icon-box">
                <i class="bi bi-people"></i>
                <span>Manage Users</span>
            </div>
            <div class="icon-box">
                <i class="bi bi-graph-up"></i>
                <span>View Analytics</span>
            </div>
            <div class="icon-box">
                <i class="bi bi-person-badge"></i>
                <span>Manage Clients</span>
            </div>
            <div class="icon-box">
                <i class="bi bi-file-earmark-text"></i>
                <span>Reports</span>
            </div>
        </div>
    </div>
</div>
@endsection
