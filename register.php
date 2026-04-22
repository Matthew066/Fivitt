<?php
session_start();
require 'includes/db.php';
require_once 'includes/user_profile.php';

ensure_users_profile_schema($pdo);

if (isset($_SESSION['user_id'])) {
    $role = strtolower(trim((string)($_SESSION['user_role'] ?? 'user')));
    if ($role === 'admin') {
        header('Location: admin/index.php');
    } else {
        header('Location: index.php');
    }
    exit;
}

$error = '';
$name = '';
$email = '';
$department = '';
$gender = '';
$birthDate = '';
$ageGroup = 'adult';
$departmentOptions = ['General', 'HR', 'Finance', 'IT', 'Marketing', 'Operations'];
$genderOptions = get_gender_options();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string)($_POST['name'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = trim((string)($_POST['password'] ?? ''));
    $confirmPassword = trim((string)($_POST['confirm_password'] ?? ''));
    $department = trim((string)($_POST['department'] ?? 'General'));
    $gender = normalize_gender((string)($_POST['gender'] ?? ''));
    $birthDate = trim((string)($_POST['birth_date'] ?? ''));

    if ($birthDate === '') {
        $error = 'Tanggal lahir wajib diisi.';
    } else {
        $derivedAgeGroup = get_age_group_from_birth_date($birthDate);
        if ($derivedAgeGroup === null) {
            $error = 'Tanggal lahir tidak valid.';
        } else {
            $ageGroup = $derivedAgeGroup;
        }
    }

    if ($error === '' && ($name === '' || $email === '' || $password === '' || $confirmPassword === '' || $department === '' || $gender === '')) {
        $error = 'Semua field wajib diisi.';
    } elseif ($error === '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } elseif ($error === '' && strlen($password) < 6) {
        $error = 'Password minimal 6 karakter.';
    } elseif ($error === '' && $password !== $confirmPassword) {
        $error = 'Konfirmasi password tidak cocok.';
    } elseif ($error === '' && !in_array($department, $departmentOptions, true)) {
        $error = 'Department tidak valid.';
    } elseif ($error === '' && !array_key_exists($gender, $genderOptions)) {
        $error = 'Gender tidak valid.';
    } else {
        $check = $pdo->prepare('SELECT id_users FROM users WHERE LOWER(TRIM(email)) = LOWER(TRIM(?)) LIMIT 1');
        $check->execute([$email]);

        if ($check->fetch()) {
            $error = 'Email sudah terdaftar. Silakan login.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $insert = $pdo->prepare(
                "INSERT INTO users (name, email, password_hash, role, department, gender, birth_date, age_group, is_active, created_at)
                 VALUES (?, ?, ?, 'user', ?, ?, ?, ?, 1, NOW())"
            );
            $insert->execute([$name, $email, $hash, $department, $gender, ($birthDate !== '' ? $birthDate : null), $ageGroup]);

            $_SESSION['register_success'] = 'Registrasi berhasil. Silakan login.';
            header('Location: login.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Fivit - Register</title>
    <link rel="icon" href="assets/images/favicon/icon-fivit.png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/all.min.css?v=20260422-register-dropdown">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css?v=20260422-register-dropdown">
    <link rel="stylesheet" href="assets/css/style.css?v=20260422-register-dropdown">
    <link rel="stylesheet" href="assets/css/media-query.css?v=20260422-register-dropdown">
</head>
<body class="auth-screen register-screen">
    <div class="site-content">
        <div class="preloader">
            <img src="assets/images/splashscreen/logofivit.png" alt="Loading Fivit">
        </div>

        <main class="login-main" id="sign-up-main">
            <div class="register-back-wrap">
                <a href="login.php" class="register-back-link">
                    <i class="fa-solid fa-arrow-left"></i>
                    <span>Back to login</span>
                </a>
            </div>

            <div class="login-hero">
                <img src="assets/images/splashscreen/logofivit.png" class="auth-logo" alt="Fivit Logo">
                <h1>CREATE ACCOUNT</h1>
                <p>Register now to start your fitness journey and access personalized features.</p>
            </div>

            <form class="login-form-wrap" method="POST" autocomplete="off">
                <div class="field">
                    <i class="fa-regular fa-user" aria-hidden="true"></i>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" placeholder="Username" class="sign-in-custom-input" required>
                </div>

                <div class="field">
                    <i class="fa-regular fa-envelope" aria-hidden="true"></i>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" placeholder="Email Address" class="sign-in-custom-input" required>
                </div>

                <div class="field select-field" data-placeholder="Select department">
                    <i class="fa-solid fa-building" aria-hidden="true"></i>
                    <select id="department" name="department" class="sign-in-custom-input native-select" required tabindex="-1" aria-hidden="true">
                        <option value="" disabled <?php echo $department === '' ? 'selected' : ''; ?>>Select department</option>
                        <?php foreach ($departmentOptions as $dept): ?>
                            <option value="<?php echo htmlspecialchars($dept, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $department === $dept ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="custom-select-trigger sign-in-custom-input" aria-haspopup="listbox" aria-expanded="false">
                        <span class="custom-select-label"><?php echo $department !== '' ? htmlspecialchars($department, ENT_QUOTES, 'UTF-8') : 'Select department'; ?></span>
                    </button>
                    <i class="fa-solid fa-chevron-down select-caret" aria-hidden="true"></i>
                    <div class="custom-select-panel" role="listbox" aria-label="Department options">
                        <?php foreach ($departmentOptions as $dept): ?>
                            <button
                                type="button"
                                class="custom-select-option<?php echo $department === $dept ? ' is-selected' : ''; ?>"
                                data-value="<?php echo htmlspecialchars($dept, ENT_QUOTES, 'UTF-8'); ?>"
                                role="option"
                                aria-selected="<?php echo $department === $dept ? 'true' : 'false'; ?>"
                            >
                                <span><?php echo htmlspecialchars($dept, ENT_QUOTES, 'UTF-8'); ?></span>
                                <i class="fa-solid fa-check" aria-hidden="true"></i>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="field select-field" data-placeholder="Select gender">
                    <i class="fa-solid fa-venus-mars" aria-hidden="true"></i>
                    <select id="gender" name="gender" class="sign-in-custom-input native-select" required tabindex="-1" aria-hidden="true">
                        <option value="" disabled <?php echo $gender === '' ? 'selected' : ''; ?>>Select gender</option>
                        <?php foreach ($genderOptions as $genderValue => $genderLabel): ?>
                            <option value="<?php echo htmlspecialchars($genderValue, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $gender === $genderValue ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($genderLabel, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="custom-select-trigger sign-in-custom-input" aria-haspopup="listbox" aria-expanded="false">
                        <span class="custom-select-label"><?php echo $gender !== '' && isset($genderOptions[$gender]) ? htmlspecialchars($genderOptions[$gender], ENT_QUOTES, 'UTF-8') : 'Select gender'; ?></span>
                    </button>
                    <i class="fa-solid fa-chevron-down select-caret" aria-hidden="true"></i>
                    <div class="custom-select-panel" role="listbox" aria-label="Gender options">
                        <?php foreach ($genderOptions as $genderValue => $genderLabel): ?>
                            <button
                                type="button"
                                class="custom-select-option<?php echo $gender === $genderValue ? ' is-selected' : ''; ?>"
                                data-value="<?php echo htmlspecialchars($genderValue, ENT_QUOTES, 'UTF-8'); ?>"
                                role="option"
                                aria-selected="<?php echo $gender === $genderValue ? 'true' : 'false'; ?>"
                            >
                                <span><?php echo htmlspecialchars($genderLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                <i class="fa-solid fa-check" aria-hidden="true"></i>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="field">
                    <i class="fa-solid fa-cake-candles" aria-hidden="true"></i>
                    <input type="date" id="birth_date" name="birth_date" value="<?php echo htmlspecialchars($birthDate); ?>" max="<?php echo date('Y-m-d'); ?>" class="sign-in-custom-input" required>
                </div>

                <div class="field">
                    <i class="fa-solid fa-lock" aria-hidden="true"></i>
                    <input type="password" id="password" name="password" placeholder="Password" class="sign-in-custom-input" required>
                    <i class="fas fa-eye-slash toggle-eye" id="eye"></i>
                </div>

                <div class="field">
                    <i class="fa-solid fa-lock" aria-hidden="true"></i>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm Password" class="sign-in-custom-input" required>
                    <i class="fas fa-eye-slash toggle-eye" id="eye1"></i>
                </div>

                <?php if ($error !== ''): ?>
                    <p class="error-msg"><?php echo htmlspecialchars($error); ?></p>
                <?php endif; ?>

                <div class="password-btn">
                    <button type="submit" class="custom-login-btn">Register</button>
                </div>

                <p class="register-now-link">
                    Already have an account?
                    <a href="login.php">Login</a>
                </p>
            </form>
        </main>
    </div>

    <footer class="footer" style="text-align: center; padding: 20px 0; font-size: 14px; color: #64748b;">
        &copy; FIVIT <?= date('Y') ?>
    </footer>

    <script src="assets/js/jquery.min.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/custom.js"></script>
    <script>
        (() => {
            const selectFields = Array.from(document.querySelectorAll('.select-field'));
            if (!selectFields.length) return;

            function closeAllExcept(activeField = null) {
                selectFields.forEach((field) => {
                    const trigger = field.querySelector('.custom-select-trigger');
                    if (field !== activeField) {
                        field.classList.remove('is-open');
                        field.style.setProperty('--field-open-space', '0px');
                        if (trigger) trigger.setAttribute('aria-expanded', 'false');
                    }
                });
            }

            selectFields.forEach((field) => {
                const select = field.querySelector('.native-select');
                const trigger = field.querySelector('.custom-select-trigger');
                const label = field.querySelector('.custom-select-label');
                const panel = field.querySelector('.custom-select-panel');
                const options = Array.from(field.querySelectorAll('.custom-select-option'));
                const placeholder = field.dataset.placeholder || 'Select option';
                if (!select || !trigger || !label || !panel || !options.length) return;

                function syncSelection(selectedValue = select.value) {
                    const selectedOption = options.find((option) => option.dataset.value === selectedValue) || null;
                    label.textContent = selectedOption ? selectedOption.textContent.trim() : placeholder;
                    field.classList.toggle('has-value', Boolean(selectedOption));

                    options.forEach((option) => {
                        const isSelected = option === selectedOption;
                        option.classList.toggle('is-selected', isSelected);
                        option.setAttribute('aria-selected', isSelected ? 'true' : 'false');
                    });
                }

                function setOpen(nextOpen) {
                    if (nextOpen) {
                        closeAllExcept(field);
                        field.classList.add('is-open');
                        const panelHeight = panel.offsetHeight;
                        field.style.setProperty('--field-open-space', `${panelHeight + 14}px`);
                    } else {
                        field.classList.remove('is-open');
                        field.style.setProperty('--field-open-space', '0px');
                    }

                    trigger.setAttribute('aria-expanded', nextOpen ? 'true' : 'false');
                }

                syncSelection();

                trigger.addEventListener('click', (event) => {
                    event.preventDefault();
                    const nextOpen = !field.classList.contains('is-open');
                    setOpen(nextOpen);
                });

                trigger.addEventListener('keydown', (event) => {
                    if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        setOpen(true);
                    } else if (event.key === 'Escape') {
                        setOpen(false);
                    }
                });

                options.forEach((option) => {
                    option.addEventListener('click', () => {
                        const nextValue = option.dataset.value || '';
                        select.value = nextValue;
                        syncSelection(nextValue);
                        setOpen(false);
                        select.dispatchEvent(new Event('change', { bubbles: true }));
                    });
                });

                select.addEventListener('change', () => {
                    syncSelection();
                });
            });

            document.addEventListener('pointerdown', (event) => {
                const target = event.target;
                if (!(target instanceof Element)) return;
                if (!target.closest('.select-field')) closeAllExcept(null);
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') closeAllExcept(null);
            });
        })();
    </script>
</body>
</html>
