<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Code OTP - FanRadar</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .container {
            background: #f9f9f9;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
        }
        .otp-code {
            background: #007bff;
            color: white;
            font-size: 24px;
            font-weight: bold;
            padding: 15px 30px;
            border-radius: 5px;
            display: inline-block;
            margin: 20px 0;
            letter-spacing: 2px;
        }
        .warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 Code de Vérification OTP</h1>
        
        <p>Bonjour,</p>
        
        <p>Vous avez demandé la réinitialisation de votre mot de passe sur <strong>FanRadar</strong>.</p>
        
        <p>Voici votre code OTP :</p>
        
        <div class="otp-code">{{ $otp }}</div>
        
        <p>Ce code est valide pendant <strong>10 minutes</strong>.</p>
        
        <div class="warning">
            ⚠️ <strong>Important :</strong> Si vous n'avez pas demandé cette réinitialisation, ignorez cet email. Votre mot de passe restera inchangé.
        </div>
        
        <p>Merci,<br>L'équipe FanRadar</p>
    </div>
</body>
</html>
