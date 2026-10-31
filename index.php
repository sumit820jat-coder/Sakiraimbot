<?php
// Koi bhi PHP error ya path reveal nahi hoga
http_response_code(404);
header('Content-Type: text/html; charset=utf-8');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header_remove('X-Powered-By');
header_remove('Server');
exit('
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 Not Found</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            background: #f4f4f4;
            color: #333;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            text-align: center;
            padding: 20px;
        }
        .container { max-width: 500px; }
        h1 {
            font-size: 120px;
            font-weight: 700;
            color: #ccc;
            line-height: 1;
            margin-bottom: 10px;
        }
        h2 {
            font-size: 22px;
            font-weight: 500;
            color: #666;
            margin-bottom: 15px;
        }
        p {
            font-size: 15px;
            color: #999;
            line-height: 1.6;
        }
        a {
            display: inline-block;
            margin-top: 25px;
            padding: 10px 25px;
            background: #333;
            color: #fff;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
            transition: 0.2s;
        }
        a:hover { background: #555; }
    </style>
</head>
<body>
    <div class="container">
        <h1>404</h1>
        <h2>Page Not Found</h2>
        <p>The page you are looking for might have been removed,<br>had its name changed, or is temporarily unavailable.</p>
        <a href="/">Go Home</a>
    </div>
</body>
</html>
');
?>