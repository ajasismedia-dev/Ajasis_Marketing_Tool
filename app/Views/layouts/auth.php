<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajasis Marketing Tool</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/public/assets/css/style.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body {
            background: #0d0d0d;
            font-family: 'Inter', sans-serif;
            color: #fff;
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        
        .auth-container {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            box-sizing: border-box;
        }

        .auth-box {
            width: 100%;
            max-width: 440px;
            padding: 2.5rem;
            background: rgba(26, 26, 26, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            box-shadow: 0 24px 48px rgba(0, 0, 0, 0.5);
            box-sizing: border-box;
        }

        .auth-box .brand {
            text-align: center;
            font-size: 1.75rem;
            font-weight: 700;
            letter-spacing: -0.5px;
            margin-bottom: 0.5rem;
            color: #fff;
            display: block;
            border: none;
            padding: 0;
        }
        
        .auth-box .brand span {
            color: #c1ff00;
        }
        
        .auth-subtitle {
            text-align: center;
            color: #a0a0a0;
            font-size: 0.95rem;
            margin-bottom: 2rem;
            font-weight: 400;
        }

        .form-group {
            margin-bottom: 1.25rem;
            text-align: left;
        }

        .form-label {
            display: block;
            font-size: 0.85rem;
            font-weight: 500;
            color: #d4d4d4;
            margin-bottom: 0.5rem;
        }

        .form-input {
            width: 100%;
            padding: 0.875rem 1rem;
            background: rgba(20, 20, 20, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            color: #fff;
            font-size: 0.95rem;
            font-family: inherit;
            transition: all 0.2s ease;
            box-sizing: border-box;
        }

        .form-input:focus {
            outline: none;
            border-color: #c1ff00;
            box-shadow: 0 0 0 3px rgba(193, 255, 0, 0.15);
            background: rgba(30, 30, 30, 0.8);
        }

        .btn-primary {
            width: 100%;
            padding: 0.875rem;
            background: #c1ff00;
            color: #000;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-top: 0.5rem;
        }

        .btn-primary:hover {
            background: #d4ff4d;
            transform: translateY(-1px);
        }
        
        .btn-primary:active {
            transform: translateY(0);
        }

        .alert-error {
            background: rgba(255, 77, 79, 0.1);
            border: 1px solid rgba(255, 77, 79, 0.2);
            color: #ff4d4f;
            padding: 0.875rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            text-align: center;
        }
    </style>
</head>
<body>

<div class="auth-container">
    <?= $content ?>
</div>

<script>
    lucide.createIcons();
</script>
</body>
</html>
