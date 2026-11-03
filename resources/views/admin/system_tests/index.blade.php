@extends($layout ?? (isset($adminTheme) && !empty($adminTheme) ? $adminTheme : (config('system-test.dashboard.layout') ?? 'system-test::standalone')))

@section('title', 'Autonomous System Tests Quality Control Panel')

@section('breadcrumb')
<h6 class="h3 text-white d-inline-block mb-0">Autonomous System Tests</h6>
<nav aria-label="breadcrumb" class="d-none d-md-inline-block ml-md-4">
  <ol class="breadcrumb breadcrumb-links breadcrumb-dark">
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="fas fa-home"></i></a></li>
    <li class="breadcrumb-item active" aria-current="page">System Tests Quality Control</li>
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
  .qa-pulse-warning { background: var(--qa-warning); box-shadow: 0 0 0 rgba(251, 99, 64, 0.4); }

  @keyframes qaPulse {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(45, 206, 137, 0.7); }
    70% { transform: scale(1.1); box-shadow: 0 0 0 8px rgba(45, 206, 137, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(45, 206, 137, 0); }
  }

  /* Live Terminal Console */
  .qa-terminal-box {
    background: #0f172a;
    border-radius: 8px;
    font-family: 'SFMono-Regular', Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;
    font-size: 0.82rem;
    color: #cbd5e1;
    border: 1px solid #1e293b;
    overflow: hidden;
  }
  .qa-terminal-header {
    background: #1e293b;
    padding: 8px 14px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid #334155;
  }
  .qa-terminal-body {
    max-height: 240px;
    overflow-y: auto;
    padding: 12px 16px;
    line-height: 1.6;
    scroll-behavior: smooth;
  }
  .qa-terminal-line {
    margin-bottom: 4px;
    white-space: pre-wrap;
    word-break: break-word;
  }
  .qa-terminal-line .time {
    color: #64748b;
    margin-right: 8px;
  }
  .qa-terminal-line .pass {
    color: #34d399;
  }
  .qa-terminal-line .fail {
    color: #f87171;
  }
  .qa-terminal-line .info {
    color: #38bdf8;
  }

  /* Micro badges and tags */
  .badge-jira {
    background-color: #0052cc;
    color: #fff;
    font-weight: 600;
  }
  .badge-jira:hover {
    background-color: #0747a6;
    color: #fff;
  }

  .copy-btn {
    cursor: pointer;
    opacity: 0.6;
    transition: opacity 0.15s ease;
  }
  .copy-btn:hover {
    opacity: 1;
    color: var(--qa-primary);
  }

  .filter-pill {
    cursor: pointer;
    transition: all 0.2s ease;
  }
  .filter-pill.active {
    background-color: #5e72e4 !important;
    color: #fff !important;
  }
</style>
@endsection

@section('content')
<div class="container-fluid mt--6">

  <!-- Executive Action & Control Center -->
  <div class="row">
    <div class="col-12">
      <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-4">
          <div class="d-flex justify-content-between align-items-center flex-wrap">
            <div class="mb-3 mb-xl-0">
              <div class="d-flex align-items-center mb-1">
                <span class="badge badge-pill badge-primary mr-2 px-3 py-1 text-uppercase letter-spacing">
                  <i class="fas fa-shield-alt mr-1"></i> Quality Assurance Engine
                </span>
                <span class="badge badge-pill badge-light border text-muted">
                  <i class="fab fa-php mr-1"></i> PHP {{ PHP_VERSION }} | Laravel {{ app()->version() }}
                </span>
              </div>
              <h2 class="mb-1 text-dark font-weight-bold">Enterprise Autonomous System Test Control Panel</h2>
              <p class="text-muted mb-0 small">
                Multi-tier self-validating framework covering Deploy, Web Matrix (All 5 Roles), REST API, Business Workflows, and Laravel Dusk in real Chrome.
              </p>
            </div>

            <!-- Execution Action Buttons -->
            <div class="d-flex align-items-center flex-wrap" style="gap: 8px;">
              <button id="btnRunFull" class="btn btn-primary btn-md shadow-sm font-weight-bold px-3">
                <i class="fas fa-play mr-1"></i> Run Full (Headless)
              </button>
              <button id="btnRunHeaded" class="btn btn-info btn-md shadow-sm font-weight-bold px-3" title="Launch visible Google Chrome window on desktop">
                <i class="fas fa-desktop mr-1"></i> Run Headed (Visible Chrome)
              </button>
              <button id="btnRunQuick" class="btn btn-outline-primary btn-md shadow-sm font-weight-bold px-3" title="Run 110+ fast backend & API checks without launching browser">
                <i class="fas fa-bolt mr-1"></i> Quick Matrix (No Browser)
              </button>
              <button id="btnCleanup" class="btn btn-outline-danger btn-md shadow-sm" title="Safely purge ZZ_TEST entities and prune old runs">
                <i class="fas fa-broom mr-1"></i> Cleanup Data
              </button>
              <button id="btnRefreshList" class="btn btn-outline-secondary btn-md shadow-sm" title="Reload history">
                <i class="fas fa-sync-alt"></i>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Real-Time Live Execution Console (Dynamic when running) -->
  <div id="liveProgressCard" class="row" style="display: none;">
    <div class="col-12">
      <div class="card bg-gradient-dark text-white shadow border-0 mb-4">
        <div class="card-body p-4">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
              <span class="badge badge-warning badge-pill mr-2 px-3 py-1 font-weight-bold">
                <i class="fas fa-sync fa-spin mr-1"></i> EXECUTING RUN <span id="liveRunId"></span>
              </span>
              <strong class="h4 text-white mb-0" id="liveSuiteName">Initializing...</strong>
            </div>
            <div class="text-right">
              <span class="badge badge-secondary px-3 py-1 mr-2" id="liveCheckCount">0 checks</span>
              <span class="badge badge-light px-3 py-1 font-weight-bold" id="liveElapsedTime"><i class="fas fa-stopwatch mr-1"></i> 0s</span>
            </div>
          </div>

          <!-- Animated Progress Bar -->
          <div class="progress mb-3" style="height: 14px; background: rgba(255,255,255,0.15); border-radius: 7px;">
            <div id="liveProgressBar" class="progress-bar bg-success progress-bar-striped progress-bar-animated" role="progressbar" style="width: 15%"></div>
          </div>

          <div class="d-flex justify-content-between align-items-center mb-3">
            <span class="small text-white-50 font-italic" id="liveCheckDesc">Creating synthetic world and verifying database boundaries...</span>
            <button class="btn btn-sm btn-outline-light py-0 px-2" type="button" data-toggle="collapse" data-target="#liveTerminalCollapse" aria-expanded="true">
              <i class="fas fa-terminal mr-1"></i> <span id="toggleLogBtnText">Toggle Live Console</span>
            </button>
          </div>

          <!-- Live Streaming Terminal -->
          <div class="collapse show" id="liveTerminalCollapse">
            <div class="qa-terminal-box shadow-inner">
              <div class="qa-terminal-header">
                <span class="small font-weight-bold text-white-50"><i class="fas fa-circle text-danger mr-1" style="font-size:8px;"></i><i class="fas fa-circle text-warning mr-1" style="font-size:8px;"></i><i class="fas fa-circle text-success mr-2" style="font-size:8px;"></i> live-system-test.log</span>
                <span class="small text-muted" id="terminalCounter">0 checks logged</span>
              </div>
              <div class="qa-terminal-body" id="liveTerminalBody">
                <div class="qa-terminal-line"><span class="time">[00:00]</span> <span class="info">System Test Runner initialized. Spawning worker thread...</span></div>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>

  <!-- Key Performance & Health Metrics Cards -->
  <div class="row">
    <!-- Total Checks -->
    <div class="col-xl-3 col-md-6 mb-4">
      <div class="card card-stats shadow-sm border-0 h-100 qa-card-hover">
        <div class="card-body">
          <div class="row">
            <div class="col">
              <h6 class="card-title text-uppercase text-muted mb-0 font-weight-bold">Total Checks (Latest)</h6>
              <span class="h2 font-weight-bold mb-0 text-dark">{{ $latest['counts']['total'] ?? 0 }}</span>
            </div>
            <div class="col-auto">
              <div class="icon icon-shape bg-gradient-primary text-white rounded-circle shadow">
                <i class="fas fa-tasks"></i>
              </div>
            </div>
          </div>
          <p class="mt-3 mb-0 text-sm d-flex align-items-center justify-content-between">
            <span class="text-nowrap text-muted">
              Run: <code>{{ $latest['id'] ?? 'None' }}</code>
            </span>
            @if(!empty($latest['id']))
            <a href="{{ route('admin.system-tests.report', $latest['id']) }}" class="small font-weight-bold text-primary">
              View <i class="fas fa-arrow-right"></i>
            </a>
            @endif
          </p>
        </div>
      </div>
    </div>

    <!-- Pass Rate -->
    <div class="col-xl-3 col-md-6 mb-4">
      <div class="card card-stats shadow-sm border-0 h-100 qa-card-hover">
        <div class="card-body">
          <div class="row">
            <div class="col">
              <h6 class="card-title text-uppercase text-muted mb-0 font-weight-bold">Health Pass Rate</h6>
              @php
                $latestTotal = $latest['counts']['total'] ?? 0;
                $latestPassed = $latest['counts']['passed'] ?? 0;
                $rate = $latestTotal > 0 ? round(($latestPassed / $latestTotal) * 100, 1) : 100;
              @endphp
              <span class="h2 font-weight-bold mb-0 {{ $rate == 100 ? 'text-success' : 'text-danger' }}">{{ $rate }}%</span>
            </div>
            <div class="col-auto">
              <div class="icon icon-shape bg-gradient-success text-white rounded-circle shadow">
                <i class="fas fa-check-double"></i>
              </div>
            </div>
          </div>
          <p class="mt-3 mb-0 text-sm">
            <span class="text-success font-weight-bold mr-1">
              <i class="fas fa-check"></i> {{ $latestPassed }}
            </span>
            <span class="text-muted">Passed / {{ $latestTotal }} Checks</span>
          </p>
        </div>
      </div>
    </div>

    <!-- Defect Guard -->
    <div class="col-xl-3 col-md-6 mb-4">
      <div class="card card-stats shadow-sm border-0 h-100 qa-card-hover">
        <div class="card-body">
          <div class="row">
            <div class="col">
              <h6 class="card-title text-uppercase text-muted mb-0 font-weight-bold">Active Defects</h6>
              @php
                $failedCount = $latest['counts']['failed'] ?? 0;
              @endphp
              <span class="h2 font-weight-bold mb-0 {{ $failedCount === 0 ? 'text-success' : 'text-danger' }}">
                {{ $failedCount }}
              </span>
            </div>
            <div class="col-auto">
              <div class="icon icon-shape {{ $failedCount === 0 ? 'bg-gradient-success' : 'bg-gradient-danger' }} text-white rounded-circle shadow">
                <i class="fas {{ $failedCount === 0 ? 'fa-shield-alt' : 'fa-bug' }}"></i>
              </div>
            </div>
          </div>
          <p class="mt-3 mb-0 text-sm">
            @if($failedCount === 0)
              <span class="text-success font-weight-bold"><i class="fas fa-check-circle"></i> Zero Regressions</span>
            @else
              <span class="text-danger font-weight-bold"><i class="fas fa-exclamation-triangle"></i> Regression Detected</span>
            @endif
          </p>
        </div>
      </div>
    </div>

    <!-- Protected Jira Tickets -->
    <div class="col-xl-3 col-md-6 mb-4">
      <div class="card card-stats shadow-sm border-0 h-100 qa-card-hover">
        <div class="card-body">
          <div class="row">
            <div class="col">
              <h6 class="card-title text-uppercase text-muted mb-0 font-weight-bold">Ticket Regressions</h6>
              <span class="h2 font-weight-bold mb-0 text-info">{{ count($ticketSuites) }}</span>
            </div>
            <div class="col-auto">
              <div class="icon icon-shape bg-gradient-info text-white rounded-circle shadow">
                <i class="fab fa-jira"></i>
              </div>
            </div>
          </div>
          <p class="mt-3 mb-0 text-sm text-truncate">
            @forelse($ticketSuites as $ts)
              <span class="badge badge-jira badge-pill mr-1">{{ $ts['key'] }}</span>
            @empty
              <span class="text-muted">No ticket dusk tests found</span>
            @endforelse
          </p>
        </div>
      </div>
    </div>
  </div>

  <!-- Multi-Tier Testing Coverage Blueprint -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="card shadow-sm border-0">
        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
          <div>
            <h4 class="mb-0 text-dark font-weight-bold"><i class="fas fa-cubes text-primary mr-2"></i> Autonomous Test Coverage Matrix</h4>
            <p class="text-muted small mb-0">Every system-test run executes across 6 defensive tiers with automatic customer DB protection.</p>
          </div>
          <span class="badge badge-success badge-pill px-3 py-2"><i class="fas fa-check mr-1"></i> Customer DB Zero-Loss Guard Active</span>
        </div>
        <div class="card-body pt-0">
          <div class="row">
            <!-- Tier 1 -->
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
              <div class="p-3 rounded border bg-light h-100">
                <div class="text-primary font-weight-bold mb-1"><i class="fas fa-server mr-1"></i> 1. Deploy</div>
                <div class="small text-muted">Migrations, Lock file integrity, APP_KEY, Caches</div>
                <span class="badge badge-light border mt-2">4 Checks</span>
              </div>
            </div>
            <!-- Tier 2 -->
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
              <div class="p-3 rounded border bg-light h-100">
                <div class="text-info font-weight-bold mb-1"><i class="fas fa-globe mr-1"></i> 2. Web Matrix</div>
                <div class="small text-muted">45+ Routes crawled across Guest, Admin, Subadmin, User</div>
                <span class="badge badge-light border mt-2">45 Checks</span>
              </div>
            </div>
            <!-- Tier 3 -->
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
              <div class="p-3 rounded border bg-light h-100">
                <div class="text-purple font-weight-bold mb-1"><i class="fas fa-code mr-1"></i> 3. REST API</div>
                <div class="small text-muted">Sanctum auth, route barricades, secret leak prevention</div>
                <span class="badge badge-light border mt-2">30 Checks</span>
              </div>
            </div>
            <!-- Tier 4 -->
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
              <div class="p-3 rounded border bg-light h-100">
                <div class="text-warning font-weight-bold mb-1"><i class="fas fa-project-diagram mr-1"></i> 4. Workflows</div>
                <div class="small text-muted">Business user creation, entity transitions, lifecycles</div>
                <span class="badge badge-light border mt-2">6 Checks</span>
              </div>
            </div>
            <!-- Tier 5 -->
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
              <div class="p-3 rounded border bg-light h-100">
                <div class="text-success font-weight-bold mb-1"><i class="fab fa-chrome mr-1"></i> 5. Chrome Dusk</div>
                <div class="small text-muted">Real browser CMS navigation, modals, zero JS error asserts</div>
                <span class="badge badge-light border mt-2">6 HD Captures</span>
              </div>
            </div>
            <!-- Tier 6 -->
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
              <div class="p-3 rounded border bg-light h-100">
                <div class="text-primary font-weight-bold mb-1"><i class="fab fa-jira mr-1"></i> 6. Jira Regression</div>
                <div class="small text-muted">Automated ticket tests (LSBA-2615, 2616, 2617, etc.)</div>
                <span class="badge badge-light border mt-2">{{ count($ticketSuites) }} Ticket Suites</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Run History Table Section -->
  <div class="row">
    <div class="col-12">
      <div class="card shadow-sm border-0">
        <!-- Table Header & Controls -->
        <div class="card-header border-0 bg-white d-flex justify-content-between align-items-center flex-wrap">
          <div>
            <h3 class="mb-0 text-dark font-weight-bold"><i class="fas fa-history text-primary mr-2"></i> Execution History & Run Archive</h3>
            <p class="text-muted small mb-0">Auditable system-test run records. Click any row to inspect deep logs and browser evidence.</p>
          </div>
          
          <!-- Filters & Search Toolbar -->
          <div class="d-flex align-items-center flex-wrap mt-3 mt-md-0" style="gap: 10px;">
            <div class="btn-group btn-group-sm" role="group">
              <button type="button" class="btn btn-outline-primary filter-pill active" data-filter="all">All ({{ count($runs) }})</button>
              <button type="button" class="btn btn-outline-primary filter-pill" data-filter="passed">Passed ({{ $passedRunsCount }})</button>
              <button type="button" class="btn btn-outline-primary filter-pill" data-filter="failed">Failed ({{ count($runs) - $passedRunsCount }})</button>
            </div>
            <div class="form-group mb-0">
              <div class="input-group input-group-sm input-group-alternative">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fas fa-search"></i></span>
                </div>
                <input class="form-control" id="searchRunsInput" placeholder="Search Run ID / trigger..." type="text">
              </div>
            </div>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table align-items-center table-flush table-hover" id="runsTable">
            <thead class="thead-light">
              <tr>
                <th>Status</th>
                <th>Run ID</th>
                <th>Trigger Mode</th>
                <th>Checks Executed</th>
                <th>Pass Rate</th>
                <th>Duration</th>
                <th>Timestamp</th>
                <th class="text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              @forelse($runs as $r)
              @php
                $status = strtolower($r['status'] ?? 'unknown');
                $totalChecks = $r['counts']['total'] ?? 0;
                $passedChecks = $r['counts']['passed'] ?? 0;
                $failedChecks = $r['counts']['failed'] ?? 0;
                $rate = $totalChecks > 0 ? round(($passedChecks / $totalChecks) * 100, 1) : 0;
                
                // Human duration
                $durSec = $r['duration_seconds'] ?? 0;
                if ($durSec >= 60) {
                    $m = floor($durSec / 60);
                    $s = round($durSec % 60);
                    $durFormatted = "{$m}m {$s}s";
                } else {
                    $durFormatted = round($durSec, 1) . 's';
                }
              @endphp
              <tr class="run-row" data-status="{{ $status }}" data-runid="{{ $r['id'] }}" data-trigger="{{ $r['trigger'] ?? '' }}">
                <!-- Status Badge -->
                <td>
                  @if($status === 'passed')
                    <span class="badge badge-success px-3 py-2 font-weight-bold">
                      <span class="qa-pulse-dot qa-pulse-success"></span> PASSED
                    </span>
                  @elseif($status === 'running')
                    <span class="badge badge-warning px-3 py-2 font-weight-bold">
                      <i class="fas fa-sync fa-spin mr-1"></i> RUNNING
                    </span>
                  @else
                    <span class="badge badge-danger px-3 py-2 font-weight-bold">
                      <span class="qa-pulse-dot qa-pulse-danger"></span> FAILED
                    </span>
                  @endif
                </td>

                <!-- Run ID with copy -->
                <td>
                  <code class="font-weight-bold text-dark">{{ $r['id'] }}</code>
                  <i class="far fa-copy copy-btn ml-1" title="Copy Run ID" onclick="copyToClipboard('{{ $r['id'] }}')"></i>
                </td>

                <!-- Trigger Mode -->
                <td>
                  @php
                    $trig = strtolower($r['trigger'] ?? 'manual');
                  @endphp
                  @if(str_contains($trig, 'headed'))
                    <span class="badge badge-pill badge-info"><i class="fas fa-desktop mr-1"></i> Headed Chrome</span>
                  @elseif(str_contains($trig, 'quick'))
                    <span class="badge badge-pill badge-primary"><i class="fas fa-bolt mr-1"></i> Quick Matrix</span>
                  @elseif(str_contains($trig, 'cli'))
                    <span class="badge badge-pill badge-dark"><i class="fas fa-terminal mr-1"></i> CLI Runner</span>
                  @else
                    <span class="badge badge-pill badge-secondary"><i class="fas fa-play mr-1"></i> Full Web</span>
                  @endif
                </td>

                <!-- Checks breakdown -->
                <td>
                  <strong>{{ $totalChecks }}</strong> checks
                  @if($totalChecks >= 116)
                    <span class="badge badge-pill badge-primary ml-1" title="Includes Browser Chrome HD Evidence">
                      <i class="fas fa-camera"></i> HD
                    </span>
                  @endif
                </td>

                <!-- Pass Rate Progress Meter -->
                <td style="min-width: 140px;">
                  <div class="d-flex align-items-center">
                    <span class="mr-2 font-weight-bold {{ $rate == 100 ? 'text-success' : 'text-danger' }}">{{ $rate }}%</span>
                    <div class="progress w-100" style="height: 6px;">
                      <div class="progress-bar {{ $rate == 100 ? 'bg-success' : 'bg-danger' }}" role="progressbar" style="width: {{ $rate }}%"></div>
                    </div>
                  </div>
                </td>

                <!-- Duration -->
                <td class="text-muted font-weight-bold">{{ $durFormatted }}</td>

                <!-- Timestamp -->
                <td>
                  <span class="small font-weight-bold text-dark d-block">
                    {{ \Carbon\Carbon::parse($r['started_at'])->format('M d, Y H:i:s') }}
                  </span>
                  <span class="small text-muted">
                    {{ \Carbon\Carbon::parse($r['started_at'])->diffForHumans() }}
                  </span>
                </td>

                <!-- Actions -->
                <td class="text-right">
                  <div class="btn-group btn-group-sm">
                    <a href="{{ route('admin.system-tests.report', $r['id']) }}" class="btn btn-outline-primary" title="View Full Report">
                      <i class="fas fa-chart-bar mr-1"></i> Report
                    </a>
                    <a href="{{ route('admin.system-tests.download', $r['id']) }}" class="btn btn-outline-secondary" title="Download Raw JSON">
                      <i class="fas fa-download"></i>
                    </a>
                    <button type="button" class="btn btn-outline-danger" title="Delete Run Record" onclick="deleteRunRecord('{{ $r['id'] }}')">
                      <i class="fas fa-trash-alt"></i>
                    </button>
                  </div>
                </td>
              </tr>
              @empty
              <tr>
                <td colspan="8" class="text-center py-5 text-muted">
                  <div class="my-4">
                    <i class="fas fa-clipboard-check fa-3x text-muted mb-3 d-block" style="opacity: 0.4;"></i>
                    <h5 class="text-muted">No System Test runs recorded yet</h5>
                    <p class="small text-muted mb-3">Click <strong>"Run Full (Headless)"</strong> or <strong>"Quick Matrix"</strong> above to launch your first autonomous verification.</p>
                  </div>
                </td>
              </tr>
              @endforelse
            </tbody>
          </table>
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

  function deleteRunRecord(runId) {
    if (!confirm('Are you sure you want to permanently delete run record ' + runId + '?')) {
      return;
    }

    $.ajax({
      url: "/admin/system-tests/run/" + runId,
      type: "DELETE",
      data: {
        _token: "{{ csrf_token() }}"
      },
      success: function(res) {
        if (typeof toastr !== 'undefined') {
          toastr.success(res.message);
        } else {
          alert(res.message);
        }
        location.reload();
      },
      error: function(err) {
        alert('Could not delete run record.');
      }
    });
  }

  $(document).ready(function() {
    let pollingTimer = null;
    let activeRunId = null;
    let loggedChecksCount = 0;

    // Search and Filter functionality for Runs Table
    $('#searchRunsInput').on('keyup', function() {
      let val = $(this).val().toLowerCase();
      $('.run-row').filter(function() {
        let text = $(this).text().toLowerCase();
        $(this).toggle(text.indexOf(val) > -1);
      });
    });

    $('.filter-pill').on('click', function() {
      $('.filter-pill').removeClass('active');
      $(this).addClass('active');

      let filter = $(this).data('filter');
      if (filter === 'all') {
        $('.run-row').show();
      } else {
        $('.run-row').each(function() {
          let status = $(this).data('status');
          $(this).toggle(status === filter);
        });
      }
    });

    $('#btnRefreshList').on('click', function() {
      location.reload();
    });

    function triggerRun(noBrowser, headed) {
      $('#btnRunFull, #btnRunHeaded, #btnRunQuick, #btnCleanup').prop('disabled', true);
      $('#liveProgressCard').slideDown();
      $('#liveRunId').text('...');
      $('#liveSuiteName').text(headed ? 'Dispatching Headed Chrome Runner...' : (noBrowser ? 'Dispatching Quick Matrix...' : 'Dispatching Autonomous System Test...'));
      $('#liveCheckDesc').text('Starting background artisan runner process via nohup...');
      $('#liveProgressBar').css('width', '8%').removeClass('bg-danger').addClass('bg-success');
      $('#liveTerminalBody').html('<div class="qa-terminal-line"><span class="time">[00:00]</span> <span class="info">Dispatching system test background process...</span></div>');
      loggedChecksCount = 0;

      $.ajax({
        url: "{{ route('admin.system-tests.run') }}",
        type: "POST",
        data: {
          _token: "{{ csrf_token() }}",
          no_browser: noBrowser ? 1 : 0,
          headed: headed ? 1 : 0
        },
        success: function(res) {
          if (res.run_id) {
            activeRunId = res.run_id;
            $('#liveRunId').text('#' + res.run_id);
            appendTerminalLog('Run manifest created: ' + res.run_id + '. Connecting real-time status poller...', 'info');
            startPolling(res.run_id, noBrowser);
          } else {
            alert('Could not start test run. No run ID returned.');
            resetRunButtons();
          }
        },
        error: function(err) {
          alert('Failed to trigger system test. Check logs.');
          resetRunButtons();
        }
      });
    }

    function resetRunButtons() {
      $('#btnRunFull, #btnRunHeaded, #btnRunQuick, #btnCleanup').prop('disabled', false);
      $('#liveProgressCard').slideUp();
    }

    function appendTerminalLog(msg, type) {
      let cssClass = type === 'pass' ? 'pass' : (type === 'fail' ? 'fail' : 'info');
      let now = new Date();
      let timeStr = '[' + ('0' + now.getMinutes()).slice(-2) + ':' + ('0' + now.getSeconds()).slice(-2) + ']';
      let icon = type === 'pass' ? '✓ ' : (type === 'fail' ? '✗ ' : '→ ');

      let line = $('<div class="qa-terminal-line"><span class="time">' + timeStr + '</span> <span class="' + cssClass + '">' + icon + escapeHtml(msg) + '</span></div>');
      let body = $('#liveTerminalBody');
      body.append(line);
      body.scrollTop(body.prop("scrollHeight"));
    }

    function escapeHtml(text) {
      if (!text) return '';
      return $('<div>').text(text).html();
    }

    $('#btnRunFull').on('click', function(e) {
      e.preventDefault();
      triggerRun(false, false);
    });

    $('#btnRunHeaded').on('click', function(e) {
      e.preventDefault();
      triggerRun(false, true);
    });

    $('#btnRunQuick').on('click', function(e) {
      e.preventDefault();
      triggerRun(true, false);
    });

    $('#btnCleanup').on('click', function(e) {
      e.preventDefault();
      if (!confirm('Safely clean up synthetic test entities and purge old runs?')) return;
      $.post("{{ route('admin.system-tests.cleanup') }}", {
        _token: "{{ csrf_token() }}"
      }, function(res) {
        if (typeof toastr !== 'undefined') {
          toastr.success(res.message);
        } else {
          alert(res.message);
        }
        location.reload();
      });
    });

    function startPolling(runId, isQuick) {
      let startSeconds = 0;
      let expectedTotal = isQuick ? 110 : 139;

      pollingTimer = setInterval(function() {
        startSeconds += 2;
        $('#liveElapsedTime').html('<i class="fas fa-stopwatch mr-1"></i> ' + startSeconds + 's');

        $.get("/admin/system-tests/status/" + runId, function(run) {
          if (!run) return;

          $('#liveSuiteName').text(run.current_suite || 'Running...');
          $('#liveCheckDesc').text(run.current_check || 'Processing checks...');
          let totalDone = run.counts ? run.counts.total : 0;
          $('#liveCheckCount').text(totalDone + ' checks executed');

          // Log newly executed checks to live terminal
          if (run.checks && run.checks.length > loggedChecksCount) {
            for (let i = loggedChecksCount; i < run.checks.length; i++) {
              let c = run.checks[i];
              let isPass = (c.status === 'passed');
              appendTerminalLog('[' + (c.suite || 'Check') + '] ' + c.name + ': ' + (c.message || (isPass ? 'OK' : 'FAIL')), isPass ? 'pass' : 'fail');
            }
            loggedChecksCount = run.checks.length;
            $('#terminalCounter').text(loggedChecksCount + ' checks logged');
          }

          let percent = Math.min(95, Math.max(12, Math.round((totalDone / expectedTotal) * 100)));
          $('#liveProgressBar').css('width', percent + '%');

          if (run.status === 'passed' || run.status === 'failed') {
            clearInterval(pollingTimer);
            let isPassed = (run.status === 'passed');

            $('#liveProgressBar').css('width', '100%')
              .removeClass('progress-bar-animated bg-success bg-danger')
              .addClass(isPassed ? 'bg-success' : 'bg-danger');

            appendTerminalLog('Execution finished with status: ' + run.status.toUpperCase() + ' (' + (run.counts.failed || 0) + ' defects). Loading final report...', isPassed ? 'pass' : 'fail');
            $('#liveCheckDesc').html('<strong>Finished!</strong> Loading comprehensive report...');

            setTimeout(function() {
              window.location.href = "/admin/system-tests/report/" + runId;
            }, 1500);
          }
        }).fail(function() {
          // If status endpoint 404s temporarily, ignore
        });
      }, 2000);
    }
  });
</script>
@endsection
