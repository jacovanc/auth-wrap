<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Submission Form</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
        form {
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            width: 300px;
            max-width: 100%;
            box-sizing: border-box;
        }
        p {
            font-size: 1em;
            color: #333;
            margin-top: 0;
        }
        .error {
            color: red;
            font-size: 1em;
            text-align: center;
            margin-bottom: 10px;
        }
        input[type=email] {
            width: calc(100% - 22px);
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        input[type=submit] {
            background-color: #0056b3;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
            width: 100%;
        }
        input[type=submit]:hover {
            background-color: #003d82;
        }
        .success {
            color: green;
            font-size: 1em;
            text-align: center;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <h1>Staging Site</h1>

    <form method="post">
        <?php if (isset($success)): ?>
            <p class="success"><?=$success?></p>
        <?php else: ?>
                <p>Enter your email to access this staging site:</p>
                <input type="email" name="email" required>
                <input type="hidden" name="redirect" value="<?=$redirect?>">
                <input type="hidden" name="csrf_token" value="<?=$_SESSION['csrf_token']?>">
                <div style="display:none;">
                    <input type="text" name="hp">
                </div>
                <?php if (isset($error)): ?>
                    <p class="error"><?=$error?></p>
                <?php endif; ?>
                <input type="submit" value="Submit">
        <?php endif; ?>
    </form>
</body>
</html>
