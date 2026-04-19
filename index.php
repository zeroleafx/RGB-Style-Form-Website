<?php
session_start();
require_once "db.php";

$guild_stats = [
    'level' => (int)($_SESSION['level'] ?? 1),
    'exp' => (int)($_SESSION['exp'] ?? 0),
    'completed_quests' => 0,
    'active_quests' => 0,
    'total_quests' => 0,
    'adventurer_reward_total' => 0,
    'client_published_commissions' => 0,
    'site_total_accepted' => 0,
];
$leaderboard = [];

if (isset($_SESSION['user_id'])) {
    $user_id = (int)$_SESSION['user_id'];

    $user_columns = [];
    $col_result = mysqli_query($conn, "SHOW COLUMNS FROM users");
    if ($col_result) {
        while ($col = mysqli_fetch_assoc($col_result)) {
            $user_columns[] = $col['Field'];
        }
    }

    $select_fields = [];
    if (in_array('level', $user_columns, true)) {
        $select_fields[] = 'level';
    }
    if (in_array('exp', $user_columns, true)) {
        $select_fields[] = 'exp';
    }

    if (!empty($select_fields)) {
        $sql_user = "SELECT " . implode(', ', $select_fields) . " FROM users WHERE id = ? LIMIT 1";
        $stmt_user = mysqli_prepare($conn, $sql_user);
        if ($stmt_user) {
            mysqli_stmt_bind_param($stmt_user, "i", $user_id);
            mysqli_stmt_execute($stmt_user);
            $user_result = mysqli_stmt_get_result($stmt_user);
            if ($user_result && mysqli_num_rows($user_result) === 1) {
                $user_row = mysqli_fetch_assoc($user_result);
                if (isset($user_row['level'])) {
                    $guild_stats['level'] = (int)$user_row['level'];
                }
                if (isset($user_row['exp'])) {
                    $guild_stats['exp'] = (int)$user_row['exp'];
                }
            }
            mysqli_stmt_close($stmt_user);
        }
    }

    $sql_progress = "SELECT
                        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_quests,
                        SUM(CASE WHEN status IN ('pending', 'approved', 'in_progress') THEN 1 ELSE 0 END) AS active_quests,
                        COUNT(*) AS total_quests
                     FROM quest_responses
                     WHERE user_id = ?";
    $stmt_progress = mysqli_prepare($conn, $sql_progress);
    if ($stmt_progress) {
        mysqli_stmt_bind_param($stmt_progress, "i", $user_id);
        mysqli_stmt_execute($stmt_progress);
        $progress_result = mysqli_stmt_get_result($stmt_progress);
        if ($progress_result) {
            $progress_row = mysqli_fetch_assoc($progress_result);
            $guild_stats['completed_quests'] = (int)($progress_row['completed_quests'] ?? 0);
            $guild_stats['active_quests'] = (int)($progress_row['active_quests'] ?? 0);
            $guild_stats['total_quests'] = (int)($progress_row['total_quests'] ?? 0);
        }
        mysqli_stmt_close($stmt_progress);
    }

    $is_admin = (($_SESSION['role'] ?? '') === 'admin');
    $member_group = $_SESSION['member_group'] ?? '';

    if ($member_group === 'adventurer' || $is_admin) {
        $sql_reward = "SELECT
                        COUNT(*) AS accepted_count,
                        SUM(COALESCE(reward_earned, 0)) AS reward_total
                       FROM quest_responses
                       WHERE user_id = ?";
        $stmt_reward = mysqli_prepare($conn, $sql_reward);
        if ($stmt_reward) {
            mysqli_stmt_bind_param($stmt_reward, "i", $user_id);
            mysqli_stmt_execute($stmt_reward);
            $reward_result = mysqli_stmt_get_result($stmt_reward);
            if ($reward_result) {
                $reward_row = mysqli_fetch_assoc($reward_result);
                $guild_stats['total_quests'] = (int)($reward_row['accepted_count'] ?? $guild_stats['total_quests']);
                $guild_stats['adventurer_reward_total'] = (int)($reward_row['reward_total'] ?? 0);
            }
            mysqli_stmt_close($stmt_reward);
        }
    }

    if ($member_group === 'client' || $is_admin) {
        $sql_client_published = "SELECT COUNT(*) AS published_commissions
                                 FROM quests
                                 WHERE created_by = ?";
        $stmt_client_published = mysqli_prepare($conn, $sql_client_published);
        if ($stmt_client_published) {
            mysqli_stmt_bind_param($stmt_client_published, "i", $user_id);
            mysqli_stmt_execute($stmt_client_published);
            $client_published_result = mysqli_stmt_get_result($stmt_client_published);
            if ($client_published_result) {
                $client_published_row = mysqli_fetch_assoc($client_published_result);
                $guild_stats['client_published_commissions'] = (int)($client_published_row['published_commissions'] ?? 0);
            }
            mysqli_stmt_close($stmt_client_published);
        }

        // Count approved quests from client's posted quests
        $sql_approved = "SELECT COUNT(*) AS approved_count
                        FROM quest_responses qr
                        INNER JOIN quests q ON qr.quest_id = q.id
                        WHERE q.created_by = ? AND qr.status = 'approved'";
        $stmt_approved = mysqli_prepare($conn, $sql_approved);
        if ($stmt_approved) {
            mysqli_stmt_bind_param($stmt_approved, "i", $user_id);
            mysqli_stmt_execute($stmt_approved);
            $approved_result = mysqli_stmt_get_result($stmt_approved);
            if ($approved_result) {
                $approved_row = mysqli_fetch_assoc($approved_result);
                $guild_stats['client_approved_quests'] = (int)($approved_row['approved_count'] ?? 0);
            }
            mysqli_stmt_close($stmt_approved);
        }

        // Count completed quests from client's posted quests
        $sql_completed = "SELECT COUNT(*) AS completed_count
                         FROM quest_responses qr
                         INNER JOIN quests q ON qr.quest_id = q.id
                         WHERE q.created_by = ? AND qr.status = 'completed'";
        $stmt_completed = mysqli_prepare($conn, $sql_completed);
        if ($stmt_completed) {
            mysqli_stmt_bind_param($stmt_completed, "i", $user_id);
            mysqli_stmt_execute($stmt_completed);
            $completed_result = mysqli_stmt_get_result($stmt_completed);
            if ($completed_result) {
                $completed_row = mysqli_fetch_assoc($completed_result);
                $guild_stats['client_completed_quests'] = (int)($completed_row['completed_count'] ?? 0);
            }
            mysqli_stmt_close($stmt_completed);
        }
    }

    if ($is_admin) {
        $sql_site_total = "SELECT COUNT(*) AS total_accepted FROM quest_responses";
        $site_total_result = mysqli_query($conn, $sql_site_total);
        if ($site_total_result) {
            $site_total_row = mysqli_fetch_assoc($site_total_result);
            $guild_stats['site_total_accepted'] = (int)($site_total_row['total_accepted'] ?? 0);
        }
    }
}

$sql_leaderboard = "SELECT
                        u.username,
                        u.level,
                        u.exp,
                        COUNT(qr.id) AS completed_count
                    FROM users u
                    LEFT JOIN quest_responses qr
                        ON qr.user_id = u.id
                        AND qr.status = 'completed'
                    WHERE COALESCE(u.role, 'user') <> 'admin'
                      AND COALESCE(u.member_group, '') = 'adventurer'
                    GROUP BY u.id, u.username, u.level, u.exp
                    ORDER BY completed_count DESC, u.exp DESC, u.level DESC, u.username ASC
                    LIMIT 8";
$leaderboard_result = mysqli_query($conn, $sql_leaderboard);
if ($leaderboard_result) {
    while ($row = mysqli_fetch_assoc($leaderboard_result)) {
        $leaderboard[] = [
            'username' => $row['username'] ?? 'Unknown',
            'level' => (int)($row['level'] ?? 1),
            'exp' => (int)($row['exp'] ?? 0),
            'completed_count' => (int)($row['completed_count'] ?? 0),
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="" />
        <meta name="author" content="" />
        <title>Guild</title>
        <!-- Favicon-->
        <link rel="icon" type="image/x-icon" href="assets/favicon.ico" />
        <!-- Font Awesome icons (free version)-->
        <script src="https://use.fontawesome.com/releases/v6.5.0/js/all.js" crossorigin="anonymous"></script>
        <!-- Google fonts-->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
        <!-- Core theme CSS (includes Bootstrap)-->
        <link href="css/styles.css" rel="stylesheet" />
    </head>
    <body id="page-top" class="home-themed">
        <!-- Navigation-->
        <nav class="navbar navbar-expand-lg navbar-dark fixed-top" id="mainNav">
            <div class="container">
                <a class="navbar-brand" href="#page-top">Guild System</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarResponsive" aria-controls="navbarResponsive" aria-expanded="false" aria-label="Toggle navigation">
                    Menu
                    <i class="fas fa-bars ms-1"></i>
                </button>
                <div class="collapse navbar-collapse" id="navbarResponsive">
                    <ul class="navbar-nav text-uppercase ms-auto py-4 py-lg-0">
                        <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                        <li class="nav-item"><a class="nav-link" href="#services">Service</a></li>
            <?php if (!isset($_SESSION['user_id'])): ?>
                <li class="nav-item">
                    <a class="nav-link" href="#register" onclick="document.getElementById('id02').style.display='block', document.getElementById('id01').style.display='none';">Register</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#login" onclick="document.getElementById('id01').style.display='block', document.getElementById('id02').style.display='none';">Login</a>
                </li>

            <?php else: ?>

                <?php if ($_SESSION['member_group'] === 'client' || ($_SESSION['member_group'] ?? '') === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="create_quest.php">New Quest</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="my_quests.php">My Quests</a>
                    </li>
                <?php endif; ?>

                <?php if ($_SESSION['member_group'] === 'adventurer'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="quest_list.php">Quest List</a>
                    </li>
                <?php endif; ?>

                <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_announcements.php">Announcements</a>
                    </li>
                <?php endif; ?>

                <li class="nav-item">
                    <a class="nav-link" href="logout.php">Logout</a>
                </li>
            <?php endif; ?>

                        <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
                    </ul>
                </div>
            </div>
        </nav>
    <!--Login-->
                
    <!-- The Modal -->
    <div id="id01" class="modal">

    <!-- Modal Content -->
    <form class="modal-content animate" id="loginForm">
        <div class="contain">
            <label for="login_username"><b>Username</b></label>
            <input type="text" placeholder="Enter Username" name="username" id="login_username" required>

            <label for="login_password"><b>Password</b></label>
            <input type="password" placeholder="Enter Password" name="password" id="login_password" required>

            <button type="submit" class="mBtn">Login</button>
            <label>
                <input type="checkbox" checked="checked" name="remember"> Remember me
            </label>

            <div id="loginMessage" style="margin-top:10px; color:red;"></div>
        </div>

        <div class="contain" style="background-color:#f1f1f1">
            <button type="button" onclick="document.getElementById('id01').style.display='none'" class="cancelbtn">Cancel</button>
            <span class="reg">
                <a onclick="document.getElementById('id01').style.display='none'; document.getElementById('id02').style.display='block'; return false;" href="#register">Not Registered?</a>
            </span>
        </div>
    </form>
    </div>


    <!-- Register -->

    <!-- The Modal -->
    <div id="id02" class="modal">

    <!-- Modal Content -->
    <form class="modal-content animate" id="registerForm">
        <div class="contain">
            <label><b>Group</b></label>

            <div class="radio-group">
                <label class="radio-item">
                    <input type="radio" name="member_group" value="adventurer" required class="identity">
                    <span><img src="assets/img/select_arrow.png" class="arrow">Adventurer</span>
                </label>

                <label class="radio-item">
                    <input type="radio" name="member_group" value="client" required class="identity">
                    <span><img src="assets/img/select_arrow.png" class="arrow">Client</span>
                </label>
            </div>
            <label for="register_username"><b>Username</b></label>
            <input type="text" placeholder="Enter Username" name="username" id="register_username" required>

            <label for="register_password"><b>Password</b></label>
            <input type="password" placeholder="Enter Password" name="password" id="register_password" required>

            <label for="register_confirm_password"><b>Re-enter Password</b></label>
            <input type="password" placeholder="Re-enter Password" name="confirm_password" id="register_confirm_password" required>

            <label>
                <input type="checkbox" name="agree_terms" value="1" style="margin-top:10px; margin-bottom:10px" required> I agree to the <a href="">terms and conditions</a>
            </label>

            <button type="submit" class="mBtn">Register</button>

            <div id="registerMessage" style="margin-top:10px; color:red;"></div>
        </div>

        <div class="contain" style="background-color:#f1f1f1">
            <button type="button" onclick="document.getElementById('id02').style.display='none'" class="cancelbtn">Cancel</button>
            <span class="reg">
                <a onclick="document.getElementById('id02').style.display='none'; document.getElementById('id01').style.display='block'; return false;" href="#login">Have an account?</a>
            </span>
        </div>
    </form>
    </div>

        <!-- Masthead-->
        <header class="masthead">
            <div class="container home-hero-wrap">
                <div class="home-hero-panel">
                    <div class="masthead-subheading top-only-hero" style="text-shadow: 2px 2px 5px grey;">Welcome to the</div>
                    <div class="masthead-heading text-uppercase top-only-hero" style="text-shadow: 2px 2px 5px grey;">Guild</div>
                    <p class="home-hero-text">Accept quests, publish quests, and enjoy yourself in the guild.</p>
                    <div class="home-hero-actions">
                        <?php if (!isset($_SESSION['user_id'])): ?>
                            <a class="btn btn-primary btn-xl text-uppercase home-pixel-btn" href="quest_list.php">Guild Board</a>
                            <a class="btn btn-primary btn-xl text-uppercase home-pixel-btn ghost" href="#login" onclick="document.getElementById('id02').style.display='block', document.getElementById('id01').style.display='none';">Join Us</a>
                        <?php else: ?>
                            <a class="btn btn-primary btn-xl text-uppercase home-pixel-btn" href="quest_list.php">Guild Board</a>
                            <?php if (($_SESSION['member_group'] ?? '') === 'client'): ?>
                                <a class="btn btn-primary btn-xl text-uppercase home-pixel-btn ghost" href="create_quest.php">Post Quest</a>
                            <?php elseif (($_SESSION['member_group'] ?? '') === 'admin'): ?>
                                <a class="btn btn-primary btn-xl text-uppercase home-pixel-btn ghost" href="manage_users.php">Manage User</a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="home-status-panel">
                    <h3>Guild Status</h3>
                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <p><span>Player:</span> Guest</p>
                        <p><span>Rank:</span> Unregistered</p>
                        <p><span>Access:</span> Browse only</p>
                        <p><span>Level:</span> --</p>
                        <p><span>Completed:</span> --</p>
                    <?php else: ?>
                        <p><span>Player:</span> <?php echo htmlspecialchars($_SESSION['username'] ?? 'Unknown'); ?></p>
                        <p><span>Role:</span> <?php echo htmlspecialchars($_SESSION['member_group'] ?? 'member'); ?></p>
                        <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                            <p><span>Access:</span> Unlimited</p>
                            <p><span>Level:</span> <?php echo (int)$guild_stats['level']; ?></p>
                        <?php elseif (($_SESSION['member_group'] ?? '') === 'adventurer'): ?>
                            <p><span>Access:</span> Accept Only</p>
                            <p><span>Level:</span> <?php echo (int)$guild_stats['level']; ?></p>
                            <p><span>EXP:</span> <?php echo (int)$guild_stats['exp']; ?></p>
                        <?php elseif (($_SESSION['member_group'] ?? '') === 'client'): ?>
                            <p><span>Access:</span> Create Only</p>
                        <?php endif; ?>
                        <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                            <p><span>Posted Quests:</span> <?php echo (int)$guild_stats['client_published_commissions']; ?></p>
                            <p><span>Approved Quests:</span> <?php echo (int)($guild_stats['client_approved_quests'] ?? 0); ?></p>
                            <p><span>Completed Quests:</span> <?php echo (int)($guild_stats['client_completed_quests'] ?? 0); ?></p>
                        <?php elseif (($_SESSION['member_group'] ?? '') === 'adventurer'): ?>
                            <p><span>Rewards:</span> <?php echo (int)$guild_stats['adventurer_reward_total']; ?></p>
                            <p><span>Applied:</span> <?php echo (int)$guild_stats['total_quests']; ?></p>
                            <p><span>Completed Quests:</span> <?php echo (int)$guild_stats['completed_quests']; ?></p>
                        <?php elseif (($_SESSION['member_group'] ?? '') === 'client'): ?>
                            <p><span>Posted Quests:</span> <?php echo (int)$guild_stats['client_published_commissions']; ?></p>
                            <p><span>Approved Quests:</span> <?php echo (int)($guild_stats['client_approved_quests'] ?? 0); ?></p>
                            <p><span>Completed Quests:</span> <?php echo (int)($guild_stats['client_completed_quests'] ?? 0); ?></p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <section class="page-section home-leaderboard-section">
            <div class="container">
                <div class="home-leaderboard-board">
                    <h2 class="section-heading text-uppercase">Leaderboard</h2>
                    <h3 class="section-subheading text-muted">Ranked by completed quests.</h3>

                    <?php if (!empty($leaderboard)): ?>
                        <ol class="home-leaderboard-list">
                            <?php foreach ($leaderboard as $rank => $player): ?>
                                <li>
                                    <strong>#<?php echo $rank + 1; ?> <?php echo htmlspecialchars($player['username']); ?></strong>
                                    <span>
                                        Completed <?php echo (int)$player['completed_count']; ?> |
                                        Lv <?php echo (int)$player['level']; ?> |
                                        EXP <?php echo (int)$player['exp']; ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php else: ?>
                        <p class="home-leaderboard-empty">No completed quest records yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Services-->
        <section class="page-section" id="services">
            <div class="container">
                <div class="text-center">
                    <h2 class="section-heading text-uppercase">Quest Board Features</h2>
                    <h3 class="section-subheading text-muted">Everything you need to run a classic guild.</h3>
                </div>
                <div class="row text-center">
                    <div class="col-md-4">
                        <div class="home-feature-card">
                            <span class="fa-stack fa-4x">
                                <i class="fas fa-circle fa-stack-2x text-primary"></i>
                                <i class="fas fa-lock fa-stack-1x fa-inverse"></i>
                            </span>
                            <h4 class="my-3">Guild Verification</h4>
                            <p class="text-muted">Adventurers and clients are validated before they can act.</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="home-feature-card">
                            <span class="fa-stack fa-4x">
                                <i class="fas fa-circle fa-stack-2x text-primary"></i>
                                <i class="fas fa-laptop fa-stack-1x fa-inverse"></i>
                            </span>
                            <h4 class="my-3">Custom Quest Builder</h4>
                            <p class="text-muted">Create quest forms with your own fields and requirements.</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="home-feature-card">
                            <span class="fa-stack fa-4x">
                                <i class="fas fa-circle fa-stack-2x text-primary"></i>
                                <i class="fas fa-magnifying-glass fa-stack-1x fa-inverse"></i>
                            </span>
                            <h4 class="my-3">Board Management</h4>
                            <p class="text-muted">Search, sort, and handle quests fast like a pro guild clerk.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <!-- Contact-->
        <section class="page-section" id="contact">
            <div class="container">
                <div class="text-center">
                    <h2 class="section-heading text-uppercase">Guild Messenger</h2>
                    <h3 class="section-subheading text-muted">Send a raven to the guild staff.</h3>
                </div>
                <!-- * * * * * * * * * * * * * * *-->
                <!-- * * SB Forms Contact Form * *-->
                <!-- * * * * * * * * * * * * * * *-->
                <!-- This form is pre-integrated with SB Forms.-->
                <!-- To make this form functional, sign up at-->
                <!-- https://startbootstrap.com/solution/contact-forms-->
                <!-- to get an API token!-->
                <form id="contactForm" data-sb-form-api-token="API_TOKEN">
                    <div class="row align-items-stretch mb-5">
                        <div class="col-md-6">
                            <div class="form-group">
                                <!-- Name input-->
                                <input class="form-control" id="name" type="text" placeholder="Your Name *" data-sb-validations="required" />
                                <div class="invalid-feedback" data-sb-feedback="name:required">A name is required.</div>
                            </div>
                            <div class="form-group">
                                <!-- Email address input-->
                                <input class="form-control" id="email" type="email" placeholder="Your Email *" data-sb-validations="required,email" />
                                <div class="invalid-feedback" data-sb-feedback="email:required">An email is required.</div>
                                <div class="invalid-feedback" data-sb-feedback="email:email">Email is not valid.</div>
                            </div>
                            <div class="form-group mb-md-0">
                                <!-- Phone number input-->
                                <input class="form-control" id="phone" type="tel" placeholder="Your Phone *" data-sb-validations="required" />
                                <div class="invalid-feedback" data-sb-feedback="phone:required">A phone number is required.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group form-group-textarea mb-md-0">
                                <!-- Message input-->
                                <textarea class="form-control" id="message" placeholder="Your Message *" data-sb-validations="required"></textarea>
                                <div class="invalid-feedback" data-sb-feedback="message:required">A message is required.</div>
                            </div>
                        </div>
                    </div>
                    <!-- Submit success message-->
                    <!---->
                    <!-- This is what your users will see when the form-->
                    <!-- has successfully submitted-->
                    <div class="d-none" id="submitSuccessMessage">
                        <div class="text-center text-white mb-3">
                            <div class="fw-bolder">Form submission successful!</div>
                            To activate this form, sign up at
                            <br />
                            <a href="https://startbootstrap.com/solution/contact-forms">https://startbootstrap.com/solution/contact-forms</a>
                        </div>
                    </div>
                    <!-- Submit error message-->
                    <!---->
                    <!-- This is what your users will see when there is-->
                    <!-- an error submitting the form-->
                    <div class="d-none" id="submitErrorMessage"><div class="text-center text-danger mb-3">Error sending message!</div></div>
                    <!-- Submit Button-->
                    <div class="text-center"><button class="btn btn-primary btn-xl text-uppercase disabled" id="submitButton" type="submit">Send</button></div>
                </form>
            </div>
        </section>
        <!-- Footer-->
        <footer class="footer py-4">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-lg-4 text-lg-start">S1354052 Jesse Yu</div>
                    <div class="col-lg-4 my-3 my-lg-0">
                    </div>
                    <div class="col-lg-4 text-lg-end">
                        <a class="link-dark text-decoration-none me-3" href="#!">Privacy Policy</a>
                        <a class="link-dark text-decoration-none" href="#!">Terms of Use</a>
                    </div>
                </div>
            </div>
        </footer>
        <!-- Bootstrap core JS-->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
        <!-- Core theme JS-->
        <script src="js/scripts.js"></script>
        <!-- * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *-->
        <!-- * *                               SB Forms JS                               * *-->
        <!-- * * Activate your form at https://startbootstrap.com/solution/contact-forms * *-->
        <!-- * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *-->
        <script src="https://cdn.startbootstrap.com/sb-forms-latest.js"></script>
    
        <!-- Ajax -->
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="js/Ajax.js"></script>

        <!-- Announcement System -->
        <script src="js/announcements.js"></script>

    </body>
</html>
