<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Please wait</title></head>
<body style="font-family:Arial,sans-serif;max-width:520px;margin:15vh auto;padding:24px;line-height:1.6">
    <h1>Please wait</h1>
    <p>{{ $message }}</p>
    <p id="retry-status" role="status">Please retry after 30 seconds.</p>
    <button id="retry-button" type="button" disabled onclick="window.location.reload()" style="padding:10px 20px;cursor:pointer">Retry in 30 seconds</button>
    <script>
        let seconds = 30;
        const button = document.getElementById('retry-button');
        const timer = setInterval(() => {
            seconds--;
            if (seconds > 0) {
                button.textContent = `Retry in ${seconds} seconds`;
            } else {
                clearInterval(timer);
                button.disabled = false;
                button.textContent = 'Try again';
                document.getElementById('retry-status').textContent = 'You can now try again.';
            }
        }, 1000);
    </script>
</body>
</html>
