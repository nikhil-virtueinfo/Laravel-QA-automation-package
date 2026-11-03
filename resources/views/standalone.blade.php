<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Autonomous System Tests Dashboard')</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fe;
            color: #32325d;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        .header-bg {
            background: linear-gradient(87deg, #5e72e4 0, #825ee4 100%);
            padding-bottom: 6rem;
            padding-top: 2rem;
            color: #fff;
        }
        .content-container {
            margin-top: -4.5rem;
        }
        .card {
            border: 0;
            box-shadow: 0 0 2rem 0 rgba(136, 152, 170, 0.15);
            border-radius: 0.375rem;
        }
    </style>
    @stack('css')
</head>
<body>
    <header class="header-bg">
        <div class="container-fluid px-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="text-white mb-0">🚀 Autonomous System-Test Dashboard</h2>
                    <small class="text-white-50">Enterprise QA & Deep UI Contract Testing Engine</small>
                </div>
                <div>
                    <span class="badge bg-success px-3 py-2">PHP {{ PHP_VERSION }} | Laravel {{ app()->version() }}</span>
                </div>
            </div>
            <div class="mt-3">
                @yield('breadcrumb')
            </div>
        </div>
    </header>

    <main class="container-fluid px-4 content-container">
        @yield('content')
    </main>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    @stack('scripts')
</body>
</html>
