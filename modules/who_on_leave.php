<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once '../config/db.php';

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Only HOD, director, admin, dean, principal can access
if (!in_array($role, ['head_of_department', 'director', 'admin', 'dean', 'principal', 'hr_admin'])) {
    $_SESSION['alert'] = "You don't have permission to access this page.";
    $_SESSION['alert_type'] = "danger";
    header('Location: ../index.php');
    exit;
}

// Get selected date (default to today)
$selected_date = isset($_GET['date']) && !empty($_GET['date']) ? $_GET['date'] : date('Y-m-d');
// Validate date
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date)) {
    $selected_date = date('Y-m-d');
}

// Get department_id for HOD
$hod_dept_id = null;
$department_name = '';
if ($role === 'head_of_department') {
    $dept_check = $conn->prepare("SELECT u.department_id, d.name FROM users u JOIN departments d ON u.department_id = d.id WHERE u.id = :uid");
    $dept_check->bindParam(':uid', $user_id, PDO::PARAM_INT);
    $dept_check->execute();
    $dept_row = $dept_check->fetch();
    if ($dept_row) {
        $hod_dept_id = $dept_row['department_id'];
        $department_name = $dept_row['name'];
    }
}

// -------------------------------------------------------
// Query: Who is on leave on the selected date?
// -------------------------------------------------------
$on_leave_sql = "SELECT 
        u.id as user_id,
        u.first_name, u.last_name, u.employee_id,
        u.staff_type, u.email,
        d.name as department_name,
        lt.name as leave_type,
        la.start_date, la.end_date, la.days, la.is_half_day, la.half_day_period,
        la.id as application_id,
        la.status
    FROM leave_applications la
    JOIN users u ON la.user_id = u.id
    JOIN leave_types lt ON la.leave_type_id = lt.id
    LEFT JOIN departments d ON u.department_id = d.id
    WHERE la.status = 'approved'
      AND :sel_date BETWEEN la.start_date AND la.end_date";

$on_leave_params = [':sel_date' => $selected_date];

if ($role === 'head_of_department' && $hod_dept_id) {
    $on_leave_sql .= " AND u.department_id = :dept_id";
    $on_leave_params[':dept_id'] = $hod_dept_id;
}

$on_leave_sql .= " ORDER BY la.start_date ASC, u.first_name ASC";

$on_leave_stmt = $conn->prepare($on_leave_sql);
foreach ($on_leave_params as $k => $v) {
    if ($k === ':dept_id') {
        $on_leave_stmt->bindValue($k, $v, PDO::PARAM_INT);
    } else {
        $on_leave_stmt->bindValue($k, $v, PDO::PARAM_STR);
    }
}
$on_leave_stmt->execute();
$on_leave_staff = $on_leave_stmt->fetchAll();

// -------------------------------------------------------
// Calendar month: get all approved leaves for current month
// -------------------------------------------------------
$cal_year  = date('Y', strtotime($selected_date));
$cal_month = date('m', strtotime($selected_date));
$month_start = "$cal_year-$cal_month-01";
$month_end   = date('Y-m-t', strtotime($selected_date)); // last day of month

$cal_sql = "SELECT 
        la.start_date, la.end_date,
        u.first_name, u.last_name
    FROM leave_applications la
    JOIN users u ON la.user_id = u.id
    WHERE la.status = 'approved'
      AND la.start_date <= :month_end
      AND la.end_date   >= :month_start";

$cal_params = [':month_start' => $month_start, ':month_end' => $month_end];

if ($role === 'head_of_department' && $hod_dept_id) {
    $cal_sql .= " AND u.department_id = :dept_id";
    $cal_params[':dept_id'] = $hod_dept_id;
}

$cal_stmt = $conn->prepare($cal_sql);
foreach ($cal_params as $k => $v) {
    if ($k === ':dept_id') {
        $cal_stmt->bindValue($k, $v, PDO::PARAM_INT);
    } else {
        $cal_stmt->bindValue($k, $v, PDO::PARAM_STR);
    }
}
$cal_stmt->execute();
$cal_leaves = $cal_stmt->fetchAll();

// Build a map: date => count of people on leave
$leave_count_by_day = [];
foreach ($cal_leaves as $cl) {
    $start = new DateTime($cl['start_date']);
    $end   = new DateTime($cl['end_date']);
    $end->modify('+1 day'); // inclusive
    $interval = new DateInterval('P1D');
    $period = new DatePeriod($start, $interval, $end);
    foreach ($period as $dt) {
        $dayKey = $dt->format('Y-m-d');
        if ($dayKey >= $month_start && $dayKey <= $month_end) {
            if (!isset($leave_count_by_day[$dayKey])) $leave_count_by_day[$dayKey] = 0;
            $leave_count_by_day[$dayKey]++;
        }
    }
}

// Department list for admin/director switching
$dept_list = [];
if (in_array($role, ['admin', 'director', 'hr_admin', 'dean', 'principal'])) {
    $dept_stmt = $conn->prepare("SELECT id, name FROM departments ORDER BY name");
    $dept_stmt->execute();
    $dept_list = $dept_stmt->fetchAll();
}

$filter_dept = isset($_GET['dept_id']) ? (int)$_GET['dept_id'] : 0;

include_once '../includes/header.php';
?>

<style>
/* ── Who's on Leave – Premium Styles ── */
:root {
    --wol-primary:   #4f46e5;
    --wol-accent:    #7c3aed;
    --wol-success:   #059669;
    --wol-danger:    #dc2626;
    --wol-warn:      #d97706;
    --wol-bg:        #f8fafc;
    --wol-card:      #ffffff;
    --wol-border:    #e2e8f0;
    --wol-text:      #1e293b;
    --wol-muted:     #64748b;
    --wol-heat-low:  #dbeafe;
    --wol-heat-mid:  #93c5fd;
    --wol-heat-high: #2563eb;
}

.wol-page-header {
    background: linear-gradient(135deg, var(--wol-primary) 0%, var(--wol-accent) 100%);
    border-radius: 16px;
    padding: 28px 32px;
    margin-bottom: 28px;
    color: #fff;
    position: relative;
    overflow: hidden;
}
.wol-page-header::after {
    content: '\f073';
    font-family: 'Font Awesome 6 Free';
    font-weight: 900;
    position: absolute;
    right: 28px; top: 50%; transform: translateY(-50%);
    font-size: 5rem;
    opacity: 0.12;
}
.wol-page-header h2 { font-weight: 700; font-size: 1.75rem; margin: 0 0 4px; }
.wol-page-header p  { margin: 0; opacity: .8; font-size: .95rem; }

/* Date picker card */
.wol-picker-card {
    background: var(--wol-card);
    border: 1px solid var(--wol-border);
    border-radius: 12px;
    padding: 20px 24px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
}
.wol-picker-card label { font-weight: 600; color: var(--wol-text); margin: 0; white-space: nowrap; }
.wol-date-input {
    border: 2px solid var(--wol-border);
    border-radius: 8px;
    padding: 8px 14px;
    font-size: .95rem;
    transition: border-color .2s;
    min-width: 180px;
}
.wol-date-input:focus { outline: none; border-color: var(--wol-primary); }
.wol-btn-go {
    background: var(--wol-primary);
    color: #fff;
    border: none;
    border-radius: 8px;
    padding: 9px 22px;
    font-weight: 600;
    cursor: pointer;
    transition: background .2s, transform .15s;
}
.wol-btn-go:hover { background: var(--wol-accent); transform: translateY(-1px); }

/* Summary badge */
.wol-summary-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, var(--wol-primary), var(--wol-accent));
    color: #fff;
    border-radius: 50px;
    padding: 6px 18px;
    font-weight: 700;
    font-size: 1rem;
    margin-left: auto;
}

/* Staff cards */
.wol-staff-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
    margin-top: 8px;
}
.wol-staff-card {
    background: var(--wol-card);
    border: 1px solid var(--wol-border);
    border-radius: 12px;
    padding: 18px 20px;
    position: relative;
    transition: box-shadow .2s, transform .2s;
    overflow: hidden;
}
.wol-staff-card:hover {
    box-shadow: 0 8px 24px rgba(79,70,229,.12);
    transform: translateY(-2px);
}
.wol-staff-card::before {
    content: '';
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: 4px;
    background: linear-gradient(180deg, var(--wol-primary), var(--wol-accent));
    border-radius: 4px 0 0 4px;
}
.wol-staff-avatar {
    width: 46px; height: 46px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--wol-primary), var(--wol-accent));
    color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 1.1rem;
    flex-shrink: 0;
}
.wol-staff-name { font-weight: 700; font-size: 1rem; color: var(--wol-text); }
.wol-staff-emp  { font-size: .78rem; color: var(--wol-muted); }
.wol-leave-badge {
    display: inline-block;
    background: rgba(79,70,229,.1);
    color: var(--wol-primary);
    border-radius: 6px;
    padding: 3px 10px;
    font-size: .78rem;
    font-weight: 600;
    margin-top: 6px;
}
.wol-date-range { font-size: .82rem; color: var(--wol-muted); margin-top: 4px; }
.wol-half-day-pill {
    background: #fef3c7; color: #92400e;
    border-radius: 20px; padding: 2px 10px;
    font-size: .72rem; font-weight: 600;
}

/* Empty state */
.wol-empty {
    text-align: center;
    padding: 60px 20px;
    color: var(--wol-muted);
}
.wol-empty i { font-size: 3.5rem; margin-bottom: 16px; color: var(--wol-border); display: block; }
.wol-empty h5 { font-weight: 700; color: #94a3b8; }

/* Calendar */
.wol-calendar-card {
    background: var(--wol-card);
    border: 1px solid var(--wol-border);
    border-radius: 12px;
    overflow: hidden;
}
.wol-cal-header {
    background: linear-gradient(135deg, var(--wol-primary), var(--wol-accent));
    color: #fff;
    padding: 14px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.wol-cal-header h6 { margin: 0; font-weight: 700; font-size: 1rem; }
.wol-cal-nav a {
    color: rgba(255,255,255,.8);
    text-decoration: none;
    font-size: 1.1rem;
    padding: 2px 8px;
    border-radius: 6px;
    transition: background .2s;
}
.wol-cal-nav a:hover { background: rgba(255,255,255,.2); color: #fff; }

.wol-cal-grid { padding: 12px; }
.wol-cal-days-header {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    text-align: center;
    font-size: .72rem;
    font-weight: 700;
    color: var(--wol-muted);
    text-transform: uppercase;
    letter-spacing: .05em;
    margin-bottom: 6px;
}
.wol-cal-days {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 3px;
}
.wol-cal-day {
    aspect-ratio: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    font-size: .82rem;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    color: var(--wol-text);
    position: relative;
    transition: background .15s;
    padding: 2px;
}
.wol-cal-day:hover { background: #f1f5f9; }
.wol-cal-day.empty { cursor: default; }
.wol-cal-day.today { background: #ede9fe; color: var(--wol-primary); }
.wol-cal-day.selected { background: var(--wol-primary) !important; color: #fff !important; }
.wol-cal-dot {
    width: 6px; height: 6px;
    border-radius: 50%;
    margin-top: 2px;
    flex-shrink: 0;
}
.wol-cal-day.heat-low  .wol-cal-dot { background: var(--wol-heat-mid); }
.wol-cal-day.heat-mid  .wol-cal-dot { background: #60a5fa; }
.wol-cal-day.heat-high .wol-cal-dot { background: #1d4ed8; }
.wol-cal-count {
    font-size: .6rem;
    font-weight: 700;
    color: var(--wol-primary);
    line-height: 1;
}
.wol-cal-day.selected .wol-cal-count { color: rgba(255,255,255,.85); }

/* Legend */
.wol-legend { display: flex; gap: 14px; flex-wrap: wrap; margin-top: 10px; padding: 0 12px 12px; }
.wol-legend-item { display: flex; align-items: center; gap: 5px; font-size: .74rem; color: var(--wol-muted); }
.wol-legend-dot { width: 8px; height: 8px; border-radius: 50%; }

/* Quick nav buttons */
.wol-quick-nav { display: flex; gap: 8px; flex-wrap: wrap; }
.wol-qbtn {
    border: 1.5px solid var(--wol-border);
    border-radius: 8px;
    padding: 6px 14px;
    font-size: .82rem;
    font-weight: 600;
    cursor: pointer;
    background: #fff;
    color: var(--wol-text);
    text-decoration: none;
    transition: all .2s;
}
.wol-qbtn:hover, .wol-qbtn.active {
    background: var(--wol-primary);
    border-color: var(--wol-primary);
    color: #fff;
}

@media (max-width: 768px) {
    .wol-staff-grid { grid-template-columns: 1fr; }
    .wol-page-header { padding: 20px; }
    .wol-page-header::after { display: none; }
    .wol-picker-card { flex-direction: column; align-items: flex-start; }
}
</style>

<div class="container-fluid">

    <!-- Page Header -->
    <div class="wol-page-header">
        <h2><i class="fas fa-calendar-check me-2"></i>Who's on Leave</h2>
        <p>
            <?php if ($role === 'head_of_department'): ?>
                Department: <strong><?php echo htmlspecialchars($department_name ?: 'Your Department'); ?></strong> &nbsp;·&nbsp;
            <?php endif; ?>
            See which staff are on approved leave for any date
        </p>
    </div>

    <!-- Date Picker & Filters -->
    <form method="GET" action="" id="wolForm">
        <div class="wol-picker-card">
            <label for="wol_date"><i class="fas fa-calendar-day me-1 text-primary"></i> Select Date:</label>
            <input type="date" id="wol_date" name="date" class="wol-date-input"
                   value="<?php echo htmlspecialchars($selected_date); ?>"
                   onchange="this.form.submit()">

            <?php if (in_array($role, ['admin', 'director', 'hr_admin', 'dean', 'principal']) && count($dept_list) > 0): ?>
                <label for="wol_dept" class="ms-2"><i class="fas fa-building me-1 text-primary"></i> Department:</label>
                <select id="wol_dept" name="dept_id" class="wol-date-input" onchange="this.form.submit()">
                    <option value="0">All Departments</option>
                    <?php foreach ($dept_list as $dept): ?>
                        <option value="<?php echo $dept['id']; ?>" <?php echo $filter_dept == $dept['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dept['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>

            <!-- Quick nav buttons -->
            <div class="wol-quick-nav ms-auto">
                <?php
                $yesterday = date('Y-m-d', strtotime('-1 day'));
                $today     = date('Y-m-d');
                $tomorrow  = date('Y-m-d', strtotime('+1 day'));
                ?>
                <a href="?date=<?php echo $yesterday; ?><?php echo $filter_dept ? '&dept_id='.$filter_dept : ''; ?>"
                   class="wol-qbtn <?php echo $selected_date === $yesterday ? 'active' : ''; ?>">Yesterday</a>
                <a href="?date=<?php echo $today; ?><?php echo $filter_dept ? '&dept_id='.$filter_dept : ''; ?>"
                   class="wol-qbtn <?php echo $selected_date === $today ? 'active' : ''; ?>">Today</a>
                <a href="?date=<?php echo $tomorrow; ?><?php echo $filter_dept ? '&dept_id='.$filter_dept : ''; ?>"
                   class="wol-qbtn <?php echo $selected_date === $tomorrow ? 'active' : ''; ?>">Tomorrow</a>
            </div>
        </div>
    </form>

    <div class="row g-4">

        <!-- ── Left: Staff on Leave ── -->
        <div class="col-lg-8">
            <!-- Section header -->
            <div class="d-flex align-items-center mb-3 flex-wrap gap-2">
                <h5 class="mb-0 fw-bold">
                    <i class="fas fa-user-clock me-2 text-primary"></i>
                    Staff on Leave —
                    <span class="text-primary"><?php echo date('D, d M Y', strtotime($selected_date)); ?></span>
                </h5>
                <div class="wol-summary-badge">
                    <i class="fas fa-users"></i>
                    <?php echo count($on_leave_staff); ?> on leave
                </div>
            </div>

            <?php if (count($on_leave_staff) > 0): ?>
                <div class="wol-staff-grid">
                    <?php foreach ($on_leave_staff as $staff): ?>
                        <?php
                        $initials = strtoupper(substr($staff['first_name'],0,1) . substr($staff['last_name'],0,1));
                        $start_dt = new DateTime($staff['start_date']);
                        $end_dt   = new DateTime($staff['end_date']);
                        $is_today = $staff['start_date'] === $staff['end_date'];
                        ?>
                        <div class="wol-staff-card">
                            <div class="d-flex align-items-center gap-3 mb-2">
                                <div class="wol-staff-avatar"><?php echo $initials; ?></div>
                                <div class="flex-grow-1 min-w-0">
                                    <div class="wol-staff-name"><?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?></div>
                                    <div class="wol-staff-emp">
                                        <i class="fas fa-id-badge me-1"></i><?php echo htmlspecialchars($staff['employee_id']); ?>
                                        <?php if ($staff['staff_type']): ?>
                                            &nbsp;·&nbsp; <?php echo ucwords(str_replace('_',' ',$staff['staff_type'])); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="wol-leave-badge">
                                <i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($staff['leave_type']); ?>
                            </div>

                            <div class="wol-date-range mt-2">
                                <i class="fas fa-calendar-alt me-1"></i>
                                <?php
                                if ($is_today) {
                                    echo $start_dt->format('d M Y');
                                } else {
                                    echo $start_dt->format('d M') . ' – ' . $end_dt->format('d M Y');
                                }
                                ?>
                                &nbsp;
                                <span class="badge bg-light text-dark border" style="font-size:.72rem">
                                    <?php echo number_format($staff['days'],1); ?> day<?php echo $staff['days'] != 1 ? 's' : ''; ?>
                                </span>
                                <?php if ($staff['is_half_day']): ?>
                                    <span class="wol-half-day-pill ms-1">
                                        <?php echo $staff['half_day_period'] === 'first_half' ? '1st Half' : '2nd Half'; ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($staff['department_name']) && $role !== 'head_of_department'): ?>
                                <div class="wol-date-range mt-1">
                                    <i class="fas fa-building me-1"></i><?php echo htmlspecialchars($staff['department_name']); ?>
                                </div>
                            <?php endif; ?>

                            <div class="mt-2">
                                <a href="view_application.php?id=<?php echo $staff['application_id']; ?>"
                                   class="btn btn-outline-primary btn-sm" target="_blank">
                                    <i class="fas fa-eye me-1"></i>View Leave
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php else: ?>
                <div class="wol-empty">
                    <i class="fas fa-check-circle text-success" style="font-size:3.5rem;margin-bottom:16px;display:block;"></i>
                    <h5>No one is on leave on this date</h5>
                    <p class="text-muted">All staff are expected to be present on <strong><?php echo date('d M Y', strtotime($selected_date)); ?></strong>.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- ── Right: Mini Calendar ── -->
        <div class="col-lg-4">
            <div class="wol-calendar-card">
                <!-- Calendar header with prev/next navigation -->
                <?php
                $prev_month = date('Y-m-d', strtotime("$selected_date -1 month"));
                $prev_first = date('Y-m-01', strtotime($prev_month));
                $next_month = date('Y-m-d', strtotime("$selected_date +1 month"));
                $next_first = date('Y-m-01', strtotime($next_month));
                ?>
                <div class="wol-cal-header">
                    <div class="wol-cal-nav">
                        <a href="?date=<?php echo $prev_first; ?><?php echo $filter_dept ? '&dept_id='.$filter_dept : ''; ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </div>
                    <h6><?php echo date('F Y', strtotime($selected_date)); ?></h6>
                    <div class="wol-cal-nav">
                        <a href="?date=<?php echo $next_first; ?><?php echo $filter_dept ? '&dept_id='.$filter_dept : ''; ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </div>
                </div>

                <div class="wol-cal-grid">
                    <div class="wol-cal-days-header">
                        <div>Sun</div><div>Mon</div><div>Tue</div>
                        <div>Wed</div><div>Thu</div><div>Fri</div><div>Sat</div>
                    </div>

                    <div class="wol-cal-days">
                        <?php
                        $first_day_of_month = new DateTime("$cal_year-$cal_month-01");
                        $days_in_month      = (int)$first_day_of_month->format('t');
                        $start_weekday      = (int)$first_day_of_month->format('w'); // 0=Sun
                        $today_date         = date('Y-m-d');

                        // Empty cells before month start
                        for ($e = 0; $e < $start_weekday; $e++) {
                            echo '<div class="wol-cal-day empty"></div>';
                        }

                        for ($d = 1; $d <= $days_in_month; $d++) {
                            $day_str  = sprintf('%04d-%02d-%02d', $cal_year, $cal_month, $d);
                            $count    = $leave_count_by_day[$day_str] ?? 0;
                            $is_selected = ($day_str === $selected_date);
                            $is_today_d  = ($day_str === $today_date);

                            $heat_class = '';
                            if ($count >= 1 && $count <= 2) $heat_class = 'heat-low';
                            elseif ($count >= 3 && $count <= 5) $heat_class = 'heat-mid';
                            elseif ($count > 5) $heat_class = 'heat-high';

                            $classes = 'wol-cal-day';
                            if ($is_selected) $classes .= ' selected';
                            elseif ($is_today_d) $classes .= ' today';
                            if ($heat_class) $classes .= ' ' . $heat_class;

                            $link_url = '?date=' . $day_str . ($filter_dept ? '&dept_id='.$filter_dept : '');
                            echo '<a href="' . $link_url . '" class="' . $classes . '" title="' . ($count > 0 ? $count . ' on leave' : 'No leave') . '">';
                            echo '<span>' . $d . '</span>';
                            if ($count > 0) {
                                echo '<div class="wol-cal-dot"></div>';
                                echo '<div class="wol-cal-count">' . $count . '</div>';
                            }
                            echo '</a>';
                        }
                        ?>
                    </div>
                </div>

                <!-- Legend -->
                <div class="wol-legend">
                    <div class="wol-legend-item">
                        <div class="wol-legend-dot" style="background:#bfdbfe;"></div> 1–2 on leave
                    </div>
                    <div class="wol-legend-item">
                        <div class="wol-legend-dot" style="background:#60a5fa;"></div> 3–5 on leave
                    </div>
                    <div class="wol-legend-item">
                        <div class="wol-legend-dot" style="background:#1d4ed8;"></div> 6+ on leave
                    </div>
                    <div class="wol-legend-item">
                        <div class="wol-legend-dot" style="background:#ede9fe;border:1px solid #7c3aed;"></div> Today
                    </div>
                </div>
            </div>

            <!-- Stats for current month -->
            <div class="card mt-3" style="border-radius:12px;border:1px solid #e2e8f0;">
                <div class="card-body">
                    <h6 class="fw-bold mb-3">
                        <i class="fas fa-chart-bar me-2 text-primary"></i>
                        <?php echo date('F Y', strtotime($selected_date)); ?> Summary
                    </h6>
                    <?php
                    $peak_day   = array_keys($leave_count_by_day, max($leave_count_by_day ?: [0]));
                    $peak_count = max($leave_count_by_day ?: [0]);
                    $days_with_leave = count(array_filter($leave_count_by_day));
                    $total_leave_days = array_sum($leave_count_by_day);
                    ?>
                    <div class="row text-center g-2">
                        <div class="col-4">
                            <div class="p-2 rounded-3" style="background:#f0fdf4;">
                                <div class="fw-bold text-success fs-5"><?php echo $days_with_leave; ?></div>
                                <div class="text-muted" style="font-size:.72rem;">Days with Leave</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 rounded-3" style="background:#eff6ff;">
                                <div class="fw-bold text-primary fs-5"><?php echo $total_leave_days; ?></div>
                                <div class="text-muted" style="font-size:.72rem;">Total Leave Days</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 rounded-3" style="background:#fff7ed;">
                                <div class="fw-bold text-warning fs-5"><?php echo $peak_count; ?></div>
                                <div class="text-muted" style="font-size:.72rem;">Peak Day Count</div>
                            </div>
                        </div>
                    </div>
                    <?php if ($peak_count > 0 && isset($peak_day[0])): ?>
                        <p class="text-muted mt-2 mb-0" style="font-size:.8rem;">
                            <i class="fas fa-info-circle me-1 text-primary"></i>
                            Busiest day: <strong><?php echo date('d M', strtotime($peak_day[0])); ?></strong>
                            (<?php echo $peak_count; ?> on leave)
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div><!-- /row -->
</div><!-- /container -->

<?php include_once '../includes/footer.php'; ?>
