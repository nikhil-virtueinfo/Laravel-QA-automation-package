@extends($layout ?? (isset($adminTheme) && !empty($adminTheme) ? $adminTheme : (config('system-test.dashboard.layout') ?? 'system-test::standalone')))

@section('title', 'System Test Detailed Report — Run #' . $run['id'])

@section('breadcrumb')
<h6 class="h3 text-white d-inline-block mb-0">System Test Execution Report</h6>
<nav aria-label="breadcrumb" class="d-none d-md-inline-block ml-md-4">
  <ol class="breadcrumb breadcrumb-links breadcrumb-dark">
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="fas fa-home"></i></a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.system-tests.index') }}">System Tests</a></li>
    <li class="breadcrumb-item active" aria-current="page">Run #{{ $run['id'] }}</li>
  </ol>
</nav>
@endsection

@section('style')
<style>
  :root {
    --qa-primary: #5e72e4;
    --qa-success: #2dce89;
    --qa-danger: #f5365c;
    --qa-warning: #fb6340;
    --qa-info: #11cdef;
    --qa-dark: #172b4d;
  }

  .qa-card-hover {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
  }
  .qa-card-hover:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(50, 50, 93, 0.1), 0 5px 15px rgba(0, 0, 0, 0.07) !important;
  }

  .qa-pulse-dot {
    display: inline-block;
    width: 9px;
    height: 9px;
    border-radius: 50%;
    margin-right: 6px;
    animation: qaPulse 1.8s infinite;
  }
  .qa-pulse-success { background: var(--qa-success); box-shadow: 0 0 0 rgba(45, 206, 137, 0.4); }
  .qa-pulse-danger { background: var(--qa-danger); box-shadow: 0 0 0 rgba(245, 54, 92, 0.4); }

  @keyframes qaPulse {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(45, 206, 137, 0.7); }
    70% { transform: scale(1.1); box-shadow: 0 0 0 8px rgba(45, 206, 137, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(45, 206, 137, 0); }
  }

  .badge-jira {
    background-color: #0052cc;
    color: #fff;
    font-weight: 600;
  }

  /* Gallery Item Hover Overlay */
  .evidence-card {
    transition: all 0.25s ease;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    overflow: hidden;
  }
  .evidence-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 24px rgba(0,0,0,0.12);
  }
  .evidence-thumb-wrapper {
    position: relative;
    height: 190px;
    overflow: hidden;
    background: #0f172a;
    cursor: pointer;
  }
  .evidence-thumb {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: top;
    transition: transform 0.3s ease;
  }
  .evidence-card:hover .evidence-thumb {
    transform: scale(1.04);
  }
  .evidence-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(15, 23, 42, 0.65);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.2s ease;
  }
  .evidence-card:hover .evidence-overlay {
    opacity: 1;
  }

  /* Enhanced Lightbox Modal */
  .lightbox-modal .modal-content {
    background: #0f172a;
    color: #fff;
    border: 1px solid #334155;
    border-radius: 12px;
  }
  .lightbox-modal .modal-header {
    border-bottom: 1px solid #1e293b;
    padding: 14px 20px;
  }
  .lightbox-modal .modal-footer {
    border-top: 1px solid #1e293b;
    padding: 12px 20px;
  }
  .lightbox-img-container {
    position: relative;
    max-height: 72vh;
    min-height: 380px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #020617;
    overflow: hidden;
  }
  .lightbox-img {
    max-height: 70vh;
    max-width: 100%;
    object-fit: contain;
    transition: transform 0.2s ease;
  }
  .lightbox-nav-btn {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(15, 23, 42, 0.75);
    color: #fff;
    border: 1px solid rgba(255, 255, 255, 0.2);
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    cursor: pointer;
    transition: all 0.2s ease;
    z-index: 10;
  }
  .lightbox-nav-btn:hover {
    background: #5e72e4;
    color: #fff;
  }
  .lightbox-nav-prev { left: 16px; }
  .lightbox-nav-next { right: 16px; }

  /* Filmstrip */
  .filmstrip-strip {
    display: flex;
    gap: 8px;
    overflow-x: auto;
    padding: 8px 4px;
    background: #090d16;
    border-top: 1px solid #1e293b;
  }
  .filmstrip-item {
    width: 72px;
    height: 48px;
    flex-shrink: 0;
    border-radius: 4px;
    overflow: hidden;
    cursor: pointer;
    opacity: 0.5;
    border: 2px solid transparent;
    transition: all 0.2s ease;
  }
  .filmstrip-item:hover, .filmstrip-item.active {
    opacity: 1;
    border-color: #5e72e4;
  }
  .filmstrip-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }

  /* Suite Filter Pills in Report */
  .suite-pill-btn {
    cursor: pointer;
    transition: all 0.2s ease;
  }
  .suite-pill-btn.active {
    background: #5e72e4 !important;
    color: #fff !important;
    border-color: #5e72e4 !important;
  }

  /* Print Styles */
  @media print {
    .sidenav, .navbar-top, .header, .btn, .breadcrumb, .filmstrip-strip, .lightbox-nav-btn {
      display: none !important;
    }
    .main-content {
      margin-left: 0 !important;
      padding: 0 !important;
    }
    .card {
      box-shadow: none !important;
      border: 1px solid #ddd !important;
    }
  }
</style>
@endsection

@section('content')
<div class="container-fluid mt--6">

  @php
    $isPassed = (($run['status'] ?? '') === 'passed');
    $totalChecks = $run['counts']['total'] ?? 0;
    $passedChecks = $run['counts']['passed'] ?? 0;
    $failedChecks = $run['counts']['failed'] ?? 0;
    $passRate = $totalChecks > 0 ? round(($passedChecks / $totalChecks) * 100, 1) : 0;

    $durSec = $run['duration_seconds'] ?? 0;
    if ($durSec >= 60) {
        $m = floor($durSec / 60);
        $s = round($durSec % 60);
        $durFormatted = "{$m}m {$s}s";
    } else {
        $durFormatted = round($durSec, 1) . 's';
    }
  @endphp

  <!-- Executive Verdict Header Card -->
  <div class="row">
    <div class="col-12">
      <div class="card shadow-sm border-0 mb-4 overflow-hidden" style="border-left: 6px solid {{ $isPassed ? '#2dce89' : '#f5365c' }} !important;">
        <div class="card-body p-4">
          <div class="d-flex justify-content-between align-items-center flex-wrap">
            <div class="mb-3 mb-lg-0">
              <div class="d-flex align-items-center mb-2">
                @if($isPassed)
                  <span class="badge badge-success px-3 py-2 font-weight-bold mr-2 text-uppercase letter-spacing">
                    <span class="qa-pulse-dot qa-pulse-success"></span> HEALTHY: ZERO REGRESSIONS
                  </span>
                @else
                  <span class="badge badge-danger px-3 py-2 font-weight-bold mr-2 text-uppercase letter-spacing">
                    <span class="qa-pulse-dot qa-pulse-danger"></span> FAILED: {{ count($run['defects'] ?? []) }} DEFECTS DETECTED
                  </span>
                @endif

                <span class="badge badge-secondary px-3 py-2 font-weight-bold text-uppercase">
                  <i class="fas fa-play mr-1"></i> {{ strtoupper($run['trigger'] ?? 'manual') }}
                </span>

                <span class="badge badge-light border ml-2 text-muted">
                  PHP {{ $run['metadata']['php_version'] ?? PHP_VERSION }} | Laravel {{ $run['metadata']['laravel_version'] ?? app()->version() }}
                </span>
              </div>

              <h2 class="mb-1 text-dark font-weight-bold">
                Run Manifest: <code class="text-primary">{{ $run['id'] }}</code>
                <i class="far fa-copy copy-btn ml-1 small text-muted" title="Copy Run ID" onclick="copyToClipboard('{{ $run['id'] }}')"></i>
              </h2>
              <p class="text-muted mb-0 small">
                Started: <strong>{{ \Carbon\Carbon::parse($run['started_at'])->format('M d, Y H:i:s') }}</strong>
                @if(!empty($run['completed_at']))
                  | Completed: <strong>{{ \Carbon\Carbon::parse($run['completed_at'])->format('H:i:s') }}</strong>
                @endif
                | Duration: <strong class="text-dark">{{ $durFormatted }}</strong>
                | Environment: <strong class="text-uppercase">{{ $run['environment'] ?? 'local' }}</strong>
              </p>
            </div>

            <!-- Action Controls -->
            <div class="d-flex align-items-center flex-wrap" style="gap: 8px;">
              <a href="{{ route('admin.system-tests.index') }}" class="btn btn-outline-secondary btn-md shadow-sm">
                <i class="fas fa-arrow-left mr-1"></i> All Runs
              </a>
              <button onclick="window.print()" class="btn btn-outline-dark btn-md shadow-sm" title="Print or save as PDF">
                <i class="fas fa-print mr-1"></i> Print / PDF
              </button>
              <a href="{{ route('admin.system-tests.download', $run['id']) }}" class="btn btn-outline-primary btn-md shadow-sm" title="Download raw JSON manifest">
                <i class="fas fa-file-code mr-1"></i> Export JSON
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Key Health Summary Row -->
  <div class="row">
    <div class="col-xl-3 col-md-6 mb-4">
      <div class="card card-stats shadow-sm border-0 h-100 qa-card-hover">
        <div class="card-body">
          <div class="row">
            <div class="col">
              <h6 class="card-title text-uppercase text-muted mb-0 font-weight-bold">Total Checks</h6>
              <span class="h2 font-weight-bold mb-0 text-dark">{{ $totalChecks }}</span>
            </div>
            <div class="col-auto">
              <div class="icon icon-shape bg-gradient-primary text-white rounded-circle shadow">
                <i class="fas fa-tasks"></i>
              </div>
            </div>
          </div>
          <p class="mt-3 mb-0 text-sm">
            <span class="text-muted">{{ count($suiteStats) }} Suites Executed</span>
          </p>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
      <div class="card card-stats shadow-sm border-0 h-100 qa-card-hover">
        <div class="card-body">
          <div class="row">
            <div class="col">
              <h6 class="card-title text-uppercase text-muted mb-0 font-weight-bold">Passed Checks</h6>
              <span class="h2 font-weight-bold text-success mb-0">{{ $passedChecks }}</span>
            </div>
            <div class="col-auto">
              <div class="icon icon-shape bg-gradient-success text-white rounded-circle shadow">
                <i class="fas fa-check-circle"></i>
              </div>
            </div>
          </div>
          <p class="mt-3 mb-0 text-sm">
            <span class="text-success font-weight-bold mr-1">{{ $passRate }}%</span>
            <span class="text-muted">Pass Rate</span>
          </p>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
      <div class="card card-stats shadow-sm border-0 h-100 qa-card-hover">
        <div class="card-body">
          <div class="row">
            <div class="col">
              <h6 class="card-title text-uppercase text-muted mb-0 font-weight-bold">Failed Checks</h6>
              <span class="h2 font-weight-bold text-danger mb-0">{{ $failedChecks }}</span>
            </div>
            <div class="col-auto">
              <div class="icon icon-shape bg-gradient-danger text-white rounded-circle shadow">
                <i class="fas fa-times-circle"></i>
              </div>
            </div>
          </div>
          <p class="mt-3 mb-0 text-sm">
            @if($failedChecks === 0)
              <span class="text-success font-weight-bold"><i class="fas fa-shield-alt"></i> Zero Regressions</span>
            @else
              <span class="text-danger font-weight-bold">{{ count($run['defects'] ?? []) }} Critical Defects</span>
            @endif
          </p>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
      <div class="card card-stats shadow-sm border-0 h-100 qa-card-hover">
        <div class="card-body">
          <div class="row">
            <div class="col">
              <h6 class="card-title text-uppercase text-muted mb-0 font-weight-bold">Screenshots Captured</h6>
              <span class="h2 font-weight-bold text-info mb-0">{{ count($evidenceItems) }}</span>
            </div>
            <div class="col-auto">
              <div class="icon icon-shape bg-gradient-info text-white rounded-circle shadow">
                <i class="fas fa-camera"></i>
              </div>
            </div>
          </div>
          <p class="mt-3 mb-0 text-sm">
            <span class="text-muted">Dusk Real Chrome Captures</span>
          </p>
        </div>
      </div>
    </div>
  </div>

  <!-- Exception Alert (if run aborted) -->
  @if(($run['status'] ?? '') === 'failed' && str_starts_with($run['current_check'] ?? '', 'Aborted due to exception:'))
  <div class="row">
    <div class="col-12">
      <div class="alert alert-danger shadow-sm border-0 mb-4 d-flex align-items-center">
        <div class="mr-3">
          <i class="fas fa-exclamation-triangle fa-2x"></i>
        </div>
        <div>
          <h5 class="mb-1 text-white font-weight-bold">Execution Interrupted by System Exception</h5>
          <p class="mb-0 text-white small" style="opacity: 0.95;">{{ $run['current_check'] }}</p>
        </div>
      </div>
    </div>
  </div>
  @endif

  <!-- Discovered Defects Detailed Breakdown (if any) -->
  @if(!empty($run['defects']))
  <div class="row">
    <div class="col-12">
      <div class="card border-0 shadow-sm mb-4" style="border-left: 6px solid #f5365c !important;">
        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
          <h4 class="mb-0 text-danger font-weight-bold">
            <i class="fas fa-bug mr-2"></i> Discovered Regression Defects ({{ count($run['defects']) }})
          </h4>
          <span class="badge badge-danger badge-pill px-3 py-2 font-weight-bold">Requires Immediate Attention</span>
        </div>
        <div class="card-body pt-0">
          <div class="list-group list-group-flush">
            @foreach($run['defects'] as $d)
            <div class="list-group-item px-0 py-3 border-bottom">
              <div class="d-flex justify-content-between align-items-start flex-wrap">
                <div class="mb-2 mb-md-0">
                  <span class="badge badge-light border text-muted mr-1 font-weight-bold">[{{ $d['suite'] }}]</span>
                  <strong class="text-dark h5 mb-0">{{ $d['name'] }}</strong>
                </div>
                @if(!empty($d['evidence']))
                  <button type="button" 
                          class="btn btn-sm btn-outline-danger shadow-sm"
                          onclick="openEvidenceModalByPath('{{ $d['evidence'] }}')">
                    <i class="fas fa-camera mr-1"></i> View Defect Screenshot
                  </button>
                @endif
              </div>
              <div class="bg-light p-3 rounded mt-2 border text-danger small font-weight-bold">
                <i class="fas fa-times-circle mr-1"></i> {{ $d['error'] }}
              </div>
            </div>
            @endforeach
          </div>
        </div>
      </div>
    </div>
  </div>
  @endif

  <!-- Suite Performance & Filter Breakdown -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="card shadow-sm border-0">
        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center pb-2">
          <div>
            <h4 class="mb-0 text-dark font-weight-bold"><i class="fas fa-layer-group text-primary mr-2"></i> Suite Health Breakdown</h4>
            <p class="text-muted small mb-0">Click any suite pill to filter the checks table below instantly.</p>
          </div>
          <button type="button" class="btn btn-sm btn-link text-primary font-weight-bold" id="btnResetSuiteFilter">
            <i class="fas fa-redo-alt mr-1"></i> Show All Suites
          </button>
        </div>
        <div class="card-body pt-2">
          <div class="d-flex flex-wrap" style="gap: 10px;">
            <button type="button" class="btn btn-sm btn-outline-primary suite-pill-btn active" data-suite="all">
              All Suites ({{ $totalChecks }})
            </button>
            @foreach($suiteStats as $sName => $s)
              @php
                $sFailed = ($s['failed'] > 0);
                $isTicket = str_starts_with($sName, 'Ticket Regression');
                if ($isTicket && preg_match('/\[([A-Z]+-\d+)\]/', $sName, $mt)) {
                    $pillLabel = $mt[1];
                } else {
                    $pillLabel = $sName;
                }
              @endphp
              <button type="button" class="btn btn-sm {{ $sFailed ? 'btn-outline-danger' : 'btn-outline-secondary' }} suite-pill-btn" data-suite="{{ $sName }}">
                @if($isTicket)
                  <i class="fab fa-jira mr-1 text-primary"></i>
                @endif
                {{ $pillLabel }}: <strong>{{ $s['passed'] }}/{{ $s['total'] }}</strong>
                @if($sFailed)
                  <span class="badge badge-danger ml-1">{{ $s['failed'] }} FAIL</span>
                @else
                  <i class="fas fa-check text-success ml-1"></i>
                @endif
              </button>
            @endforeach
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Browser Visual Evidence Gallery (Categorized with tabs) -->
  @if(!empty($evidenceItems))
  <div class="row mb-4" id="gallerySection">
    <div class="col-12">
      <div class="card shadow-sm border-0">
        <!-- Gallery Header & Category Tabs -->
        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center flex-wrap pb-2">
          <div class="mb-2 mb-md-0">
            <h4 class="mb-0 text-dark font-weight-bold">
              <i class="fas fa-camera text-primary mr-2"></i> Browser Visual Verification ({{ count($evidenceItems) }} Screenshots)
            </h4>
            <p class="text-muted small mb-0">High-resolution step captures from live Laravel Dusk Chrome browser execution.</p>
          </div>
          
          <!-- Category Tabs -->
          <div class="d-flex flex-wrap" style="gap: 6px;">
            <button type="button" class="btn btn-sm btn-outline-primary gallery-filter-btn active" data-cat="all">
              All ({{ count($evidenceItems) }})
            </button>
            @foreach($evidenceCategories as $catName => $catCount)
              <button type="button" class="btn btn-sm btn-outline-secondary gallery-filter-btn" data-cat="{{ $catName }}">
                @if($catName !== 'Core Browser')
                  <i class="fab fa-jira mr-1 text-primary"></i>
                @endif
                {{ $catName }} ({{ $catCount }})
              </button>
            @endforeach
          </div>
        </div>

        <!-- Gallery Grid -->
        <div class="card-body">
          <div class="row" id="evidenceGrid">
            @foreach($evidenceItems as $idx => $ev)
            @php
              $imgUrl = route('admin.system-tests.screenshot', ['run_id' => $run['id'], 'filename' => $ev['evidence']]);
            @endphp
            <div class="col-xl-3 col-lg-4 col-md-6 mb-4 evidence-grid-item" data-category="{{ $ev['category'] }}" data-idx="{{ $idx }}">
              <div class="card h-100 evidence-card">
                <div class="evidence-thumb-wrapper" onclick="openLightbox({{ $idx }})">
                  <img src="{{ $imgUrl }}" class="evidence-thumb" alt="{{ $ev['name'] }}" loading="lazy">
                  <div class="evidence-overlay">
                    <span class="btn btn-sm btn-light shadow-sm">
                      <i class="fas fa-search-plus mr-1"></i> Inspect
                    </span>
                  </div>
                  <span class="badge badge-dark position-absolute" style="top: 8px; right: 8px; opacity: 0.85;">
                    #{{ $idx + 1 }}
                  </span>
                  @if($ev['category'] !== 'Core Browser')
                    <span class="badge badge-jira position-absolute" style="top: 8px; left: 8px;">
                      {{ $ev['category'] }}
                    </span>
                  @endif
                </div>
                <div class="card-body p-3">
                  <h6 class="font-weight-bold text-dark mb-1 text-truncate" title="{{ $ev['name'] }}">{{ $ev['name'] }}</h6>
                  <p class="small text-muted mb-2 text-truncate">{{ $ev['message'] ?? 'Rendered cleanly' }}</p>
                  <div class="d-flex justify-content-between align-items-center">
                    <button type="button" class="btn btn-sm btn-outline-primary py-1 px-2" onclick="openLightbox({{ $idx }})">
                      <i class="fas fa-expand mr-1"></i> Fullscreen
                    </button>
                    <a href="{{ $imgUrl }}" download class="btn btn-sm btn-outline-secondary py-1 px-2" title="Download Screenshot">
                      <i class="fas fa-download"></i>
                    </a>
                  </div>
                </div>
              </div>
            </div>
            @endforeach
          </div>
        </div>
      </div>
    </div>
  </div>
  @else
  <div class="row mb-4">
    <div class="col-12">
      <div class="alert alert-secondary border-0 shadow-sm d-flex align-items-center mb-0" style="background-color: #f8f9fe; border-left: 4px solid #5e72e4 !important;">
        <div class="mr-3">
          <i class="fas fa-info-circle fa-2x text-primary"></i>
        </div>
        <div>
          <h5 class="mb-0 font-weight-bold text-dark">Quick Matrix Mode (No Browser Screenshots)</h5>
          <small class="text-muted">This run was executed in <strong>Quick Matrix</strong> mode (testing 110 backend endpoints & database rules). To capture live Chrome screenshots with visual UI verification, click <strong>"Run Full System Test"</strong> from the dashboard.</small>
        </div>
      </div>
    </div>
  </div>
  @endif

  <!-- All Checks Results Table (Supercharged with Search, Filter & Syntax Badges) -->
  <div class="row">
    <div class="col-12">
      <div class="card shadow-sm border-0">
        <!-- Table Toolbar -->
        <div class="card-header border-0 bg-white d-flex justify-content-between align-items-center flex-wrap">
          <div>
            <h4 class="mb-0 text-dark font-weight-bold">
              <i class="fas fa-list-check text-primary mr-2"></i> All Check Results
              <span class="badge badge-light border ml-1" id="checksCounterBadge">{{ count($run['checks'] ?? []) }}</span>
            </h4>
            <p class="text-muted small mb-0">Detailed granular assertions for every endpoint, page, and scenario.</p>
          </div>

          <!-- Live Table Filters -->
          <div class="d-flex align-items-center flex-wrap mt-3 mt-lg-0" style="gap: 10px;">
            <!-- Status Buttons -->
            <div class="btn-group btn-group-sm" role="group">
              <button type="button" class="btn btn-outline-primary check-status-filter active" data-filter="all">All</button>
              <button type="button" class="btn btn-outline-success check-status-filter" data-filter="passed">Passed</button>
              <button type="button" class="btn btn-outline-danger check-status-filter" data-filter="failed">Failed</button>
            </div>

            <!-- Suite Filter Dropdown -->
            <select class="form-control form-control-sm" id="suiteSelectFilter" style="width: 170px;">
              <option value="all">All Suites</option>
              @foreach($suiteStats as $sName => $s)
                <option value="{{ $sName }}">{{ $sName }} ({{ $s['total'] }})</option>
              @endforeach
            </select>

            <!-- Search Input -->
            <div class="form-group mb-0">
              <div class="input-group input-group-sm input-group-alternative">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fas fa-search"></i></span>
                </div>
                <input class="form-control" id="searchChecksInput" placeholder="Filter checks by name/message..." type="text">
              </div>
            </div>
          </div>
        </div>

        <!-- Responsive Table -->
        <div class="table-responsive">
          <table class="table align-items-center table-flush table-hover" id="checksTable">
            <thead class="thead-light">
              <tr>
                <th style="width: 15%;">Suite</th>
                <th style="width: 32%;">Assertion / Check Name</th>
                <th style="width: 10%;">Status</th>
                <th style="width: 28%;">Outcome Message</th>
                <th style="width: 8%;">Evidence</th>
                <th style="width: 7%;" class="text-right">Latency</th>
              </tr>
            </thead>
            <tbody>
              @foreach($run['checks'] ?? [] as $cIdx => $c)
              @php
                $suiteLabel = $c['suite'] ?? 'General';
                $isTicketSuite = str_starts_with($suiteLabel, 'Ticket Regression');
                $ticketBadge = null;
                if ($isTicketSuite && preg_match('/\[([A-Z]+-\d+)\]/', $suiteLabel, $tm)) {
                    $ticketBadge = $tm[1];
                }
                $cStatus = strtolower($c['status'] ?? 'passed');
                $durationMs = $c['duration_ms'] ?? 0;
              @endphp
              <tr class="check-row {{ $cStatus === 'failed' ? 'table-danger' : '' }}" 
                  data-suite="{{ $suiteLabel }}" 
                  data-status="{{ $cStatus }}"
                  data-search="{{ strtolower($suiteLabel . ' ' . ($c['name'] ?? '') . ' ' . ($c['message'] ?? '')) }}">
                <!-- Suite Badge -->
                <td>
                  @if($isTicketSuite)
                    <span class="badge badge-pill badge-jira" title="Jira Ticket Regression">
                      <i class="fab fa-jira mr-1"></i> {{ $ticketBadge ?? $suiteLabel }}
                    </span>
                  @else
                    <span class="badge badge-light border text-muted font-weight-bold">{{ $suiteLabel }}</span>
                  @endif
                </td>

                <!-- Check Name -->
                <td>
                  <strong class="text-dark">{{ $c['name'] ?? '' }}</strong>
                </td>

                <!-- Status -->
                <td>
                  @if($cStatus === 'passed')
                    <span class="badge badge-success px-2 py-1"><i class="fas fa-check mr-1"></i> PASS</span>
                  @elseif($cStatus === 'warnings')
                    <span class="badge badge-warning px-2 py-1"><i class="fas fa-exclamation-triangle mr-1"></i> WARN</span>
                  @else
                    <span class="badge badge-danger px-2 py-1"><i class="fas fa-times mr-1"></i> FAIL</span>
                  @endif
                </td>

                <!-- Result Message with syntax styling -->
                <td>
                  <span class="small {{ $cStatus === 'failed' ? 'text-danger font-weight-bold' : 'text-muted' }}">
                    {{ $c['message'] ?? '' }}
                  </span>
                </td>

                <!-- Evidence -->
                <td>
                  @if(!empty($c['evidence']))
                    <button type="button" 
                            class="btn btn-sm btn-outline-primary py-0 px-2 shadow-sm"
                            onclick="openEvidenceModalByPath('{{ $c['evidence'] }}')">
                      <i class="fas fa-camera mr-1"></i> Image
                    </button>
                  @else
                    <span class="text-muted small">—</span>
                  @endif
                </td>

                <!-- Latency -->
                <td class="text-right">
                  <span class="badge badge-light border font-weight-bold {{ $durationMs > 2000 ? 'text-primary' : ($durationMs > 200 ? 'text-warning' : 'text-success') }}">
                    {{ round($durationMs) }}ms
                  </span>
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Advanced Lightbox Modal with Filmstrip & Navigation -->
  <div class="modal fade lightbox-modal" id="lightboxModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
      <div class="modal-content shadow-lg border-0">
        <!-- Header -->
        <div class="modal-header d-flex justify-content-between align-items-center">
          <div>
            <h5 class="modal-title font-weight-bold text-white mb-0" id="lightboxTitle">
              <i class="fas fa-camera text-primary mr-2"></i> Browser Screenshot
            </h5>
            <small class="text-muted" id="lightboxSubTitle">Loading evidence...</small>
          </div>
          <div>
            <span class="badge badge-secondary mr-2" id="lightboxCounter">1 / 1</span>
            <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
        </div>

        <!-- Body Image Area with Nav Arrows -->
        <div class="modal-body p-0 position-relative">
          <div class="lightbox-img-container">
            <button class="lightbox-nav-btn lightbox-nav-prev" onclick="prevLightbox()"><i class="fas fa-chevron-left"></i></button>
            <img id="lightboxImg" src="" alt="Screenshot" class="lightbox-img">
            <button class="lightbox-nav-btn lightbox-nav-next" onclick="nextLightbox()"><i class="fas fa-chevron-right"></i></button>
          </div>

          <!-- Bottom Filmstrip -->
          <div class="filmstrip-strip" id="lightboxFilmstrip"></div>
        </div>

        <!-- Footer -->
        <div class="modal-footer justify-content-between">
          <span class="text-muted small">
            <i class="fas fa-keyboard mr-1"></i> Tip: Use <kbd>←</kbd> and <kbd>→</kbd> arrow keys to navigate, <kbd>Esc</kbd> to close.
          </span>
          <div class="d-flex align-items-center" style="gap: 8px;">
            <a id="lightboxDownloadBtn" href="" download class="btn btn-sm btn-primary">
              <i class="fas fa-download mr-1"></i> Download
            </a>
            <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>
  </div>

</div>
@endsection

@section('script')
<script>
  function copyToClipboard(text) {
    if (navigator.clipboard) {
      navigator.clipboard.writeText(text).then(function() {
        if (typeof toastr !== 'undefined') {
          toastr.success('Run ID copied to clipboard: ' + text);
        } else {
          alert('Copied to clipboard: ' + text);
        }
      });
    }
  }

  // Pre-loaded evidence dataset for interactive gallery and lightbox
  const evidenceData = @json($evidenceItems);
  let currentLightboxIndex = 0;
  let activeCategory = 'all';

  function getFilteredEvidenceIndices() {
    let indices = [];
    evidenceData.forEach((ev, i) => {
      if (activeCategory === 'all' || ev.category === activeCategory) {
        indices.push(i);
      }
    });
    return indices;
  }

  function openLightbox(index) {
    currentLightboxIndex = index;
    renderLightboxCurrent();
    renderFilmstrip();
    $('#lightboxModal').modal('show');
  }

  function openEvidenceModalByPath(evidencePath) {
    let found = -1;
    for (let i = 0; i < evidenceData.length; i++) {
      if (evidenceData[i].evidence === evidencePath) {
        found = i;
        break;
      }
    }
    if (found !== -1) {
      openLightbox(found);
    } else {
      // Direct open fallback
      let url = "/admin/system-tests/screenshot/{{ $run['id'] }}/" + encodeURIComponent(evidencePath);
      $('#lightboxTitle').text(evidencePath);
      $('#lightboxSubTitle').text('Captured visual screenshot');
      $('#lightboxImg').attr('src', url);
      $('#lightboxDownloadBtn').attr('href', url);
      $('#lightboxCounter').text('1 / 1');
      $('#lightboxFilmstrip').empty();
      $('#lightboxModal').modal('show');
    }
  }

  function renderLightboxCurrent() {
    let ev = evidenceData[currentLightboxIndex];
    if (!ev) return;

    let url = "/admin/system-tests/screenshot/{{ $run['id'] }}/" + encodeURIComponent(ev.evidence);
    $('#lightboxTitle').html('<i class="fas fa-camera text-primary mr-2"></i> ' + ev.name);
    $('#lightboxSubTitle').text('[' + ev.category + '] ' + (ev.message || 'Captured live in Chrome'));
    $('#lightboxImg').attr('src', url);
    $('#lightboxDownloadBtn').attr('href', url);

    let filtered = getFilteredEvidenceIndices();
    let posInFiltered = filtered.indexOf(currentLightboxIndex);
    $('#lightboxCounter').text((posInFiltered + 1) + ' of ' + filtered.length);
  }

  function renderFilmstrip() {
    let container = $('#lightboxFilmstrip');
    container.empty();

    let filtered = getFilteredEvidenceIndices();
    filtered.forEach(idx => {
      let ev = evidenceData[idx];
      let url = "/admin/system-tests/screenshot/{{ $run['id'] }}/" + encodeURIComponent(ev.evidence);
      let item = $('<div class="filmstrip-item ' + (idx === currentLightboxIndex ? 'active' : '') + '"></div>');
      item.html('<img src="' + url + '" alt="' + ev.name + '">');
      item.on('click', function() {
        currentLightboxIndex = idx;
        renderLightboxCurrent();
        $('.filmstrip-item').removeClass('active');
        item.addClass('active');
      });
      container.append(item);
    });
  }

  function nextLightbox() {
    let filtered = getFilteredEvidenceIndices();
    let curPos = filtered.indexOf(currentLightboxIndex);
    if (curPos === -1 || curPos === filtered.length - 1) {
      currentLightboxIndex = filtered[0];
    } else {
      currentLightboxIndex = filtered[curPos + 1];
    }
    renderLightboxCurrent();
    renderFilmstrip();
  }

  function prevLightbox() {
    let filtered = getFilteredEvidenceIndices();
    let curPos = filtered.indexOf(currentLightboxIndex);
    if (curPos === -1 || curPos === 0) {
      currentLightboxIndex = filtered[filtered.length - 1];
    } else {
      currentLightboxIndex = filtered[curPos - 1];
    }
    renderLightboxCurrent();
    renderFilmstrip();
  }

  // Keyboard navigation for Lightbox
  $(document).keydown(function(e) {
    if ($('#lightboxModal').hasClass('show')) {
      if (e.keyCode === 37) { // Left arrow
        prevLightbox();
      } else if (e.keyCode === 39) { // Right arrow
        nextLightbox();
      }
    }
  });

  $(document).ready(function() {
    // Gallery Category Filter Buttons
    $('.gallery-filter-btn').on('click', function() {
      $('.gallery-filter-btn').removeClass('active');
      $(this).addClass('active');

      activeCategory = $(this).data('cat');
      if (activeCategory === 'all') {
        $('.evidence-grid-item').fadeIn(200);
      } else {
        $('.evidence-grid-item').each(function() {
          let cat = $(this).data('category');
          if (cat === activeCategory) {
            $(this).fadeIn(200);
          } else {
            $(this).fadeOut(150);
          }
        });
      }
    });

    // Suite Breakdown Pill Buttons (Clicking suite filters the table below)
    $('.suite-pill-btn').on('click', function() {
      $('.suite-pill-btn').removeClass('active');
      $(this).addClass('active');

      let suite = $(this).data('suite');
      $('#suiteSelectFilter').val(suite);
      applyChecksFilters();
    });

    $('#btnResetSuiteFilter').on('click', function() {
      $('.suite-pill-btn').removeClass('active');
      $('.suite-pill-btn[data-suite="all"]').addClass('active');
      $('#suiteSelectFilter').val('all');
      applyChecksFilters();
    });

    // Table Filters: Suite Dropdown, Status Buttons & Search Box
    $('#suiteSelectFilter').on('change', function() {
      let suite = $(this).val();
      $('.suite-pill-btn').removeClass('active');
      $('.suite-pill-btn[data-suite="' + suite + '"]').addClass('active');
      applyChecksFilters();
    });

    $('.check-status-filter').on('click', function() {
      $('.check-status-filter').removeClass('active');
      $(this).addClass('active');
      applyChecksFilters();
    });

    $('#searchChecksInput').on('keyup', function() {
      applyChecksFilters();
    });

    function applyChecksFilters() {
      let selectedSuite = $('#suiteSelectFilter').val();
      let selectedStatus = $('.check-status-filter.active').data('filter');
      let searchVal = $('#searchChecksInput').val().toLowerCase().trim();

      let visibleCount = 0;
      $('.check-row').each(function() {
        let rowSuite = $(this).data('suite');
        let rowStatus = $(this).data('status');
        let rowSearch = $(this).data('search');

        let matchSuite = (selectedSuite === 'all' || rowSuite === selectedSuite);
        let matchStatus = (selectedStatus === 'all' || rowStatus === selectedStatus);
        let matchSearch = (!searchVal || rowSearch.indexOf(searchVal) > -1);

        if (matchSuite && matchStatus && matchSearch) {
          $(this).show();
          visibleCount++;
        } else {
          $(this).hide();
        }
      });

      $('#checksCounterBadge').text(visibleCount);
    }
  });
</script>
@endsection
