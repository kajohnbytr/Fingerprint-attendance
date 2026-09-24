<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Register Fingerprint</title>
</head>
<body>
    <h1>Register Fingerprint</h1>
    <p id="message">Press the button when the sensor is ready.</p>
    <button id="startButton">Register my fingerprint</button>

    <script>
        const button = document.getElementById('startButton');
        const message = document.getElementById('message');

        button.addEventListener('click', async () => {
            button.disabled = true;
            message.textContent = 'Starting registration...';

            try {
                const response = await fetch('/fingerprint/enroll', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector(
                            'meta[name="csrf-token"]'
                        ).content,
                        'Accept': 'application/json'
                    }
                });

                const data = await response.json();

                if (!response.ok) {
                    const validationMessage =
                        Object.values(data.errors || {})[0]?.[0];
                    throw new Error(
                        validationMessage || data.message || 'Cannot start registration.'
                    );
                }

                message.textContent =
                    'Scan your finger, remove it, then scan the same finger again.';

                const timer = setInterval(async () => {
                    try {
                        const check = await fetch(
                            `/fingerprint/enroll/${data.id}/status`,
                            { headers: { 'Accept': 'application/json' } }
                        );

                        if (!check.ok) throw new Error('Status request failed.');

                        const result = await check.json();

                        if (result.status === 'completed') {
                            clearInterval(timer);
                            message.textContent =
                                'Fingerprint registered successfully!';
                        } else if (result.status === 'failed') {
                            clearInterval(timer);
                            message.textContent =
                                result.error || 'Fingerprint registration failed.';
                            button.disabled = false;
                        }
                    } catch (error) {
                        clearInterval(timer);
                        message.textContent = error.message;
                        button.disabled = false;
                    }
                }, 2000);
            } catch (error) {
                message.textContent = error.message;
                button.disabled = false;
            }
        });
    </script>
</body>
</html>