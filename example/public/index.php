<?php

require '../../vendor/autoload.php';

use webnarmin\AmphpWS\Simple\SimpleAuthenticator;

$userId = time();
$authenticator = new SimpleAuthenticator('control-http-auth-token', 'websocket-signing-secret');
$websocketToken = $authenticator->issueWebSocketToken($userId);

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WebSocket Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
        #messages { height: 300px; overflow-y: scroll; border: 1px solid #ccc; padding: 10px; margin-bottom: 10px; }
        button { margin-right: 10px; }
        input { margin-right: 10px; }
    </style>
</head>
<body>
    <h1>WebSocket Test</h1>
    <div>
        <button id="connect">Connect</button>
        <button id="disconnect" disabled>Disconnect</button>
    </div>
    <div id="messages"></div>
    <div>
        <input type="text" id="message" placeholder="Type a message">
        <button id="sendEcho">Send Echo</button>
        <button id="sendSum">Send Sum</button>
        <button id="sendAll">Send All</button>
    </div>

    <script>
        let socket;
        const connectBtn = document.getElementById('connect');
        const disconnectBtn = document.getElementById('disconnect');
        const messagesDiv = document.getElementById('messages');
        const messageInput = document.getElementById('message');
        const sendEchoBtn = document.getElementById('sendEcho');
        const sendSumBtn = document.getElementById('sendSum');
        const sendAllBtn = document.getElementById('sendAll');

        function addMessage(message) {
            const messageElement = document.createElement('div');
            messageElement.textContent = message;
            messagesDiv.appendChild(messageElement);
            messagesDiv.scrollTop = messagesDiv.scrollHeight;
        }

        connectBtn.addEventListener('click', () => {
            socket = new WebSocket('ws://127.0.0.1:1337/ws?token=<?= $websocketToken ?>');

            socket.onopen = () => {
                addMessage('Connected to server');
                connectBtn.disabled = true;
                disconnectBtn.disabled = false;
            };

            socket.onmessage = (event) => {
                addMessage(`Received: ${event.data}`);
            };

            socket.onclose = () => {
                addMessage('Disconnected from server');
                connectBtn.disabled = false;
                disconnectBtn.disabled = true;
            };

            socket.onerror = (error) => {
                addMessage(`Error: ${error.message}`);
            };
        });

        disconnectBtn.addEventListener('click', () => {
            socket.close();
        });

        sendEchoBtn.addEventListener('click', () => {
            if (socket && socket.readyState === WebSocket.OPEN) {
                const message = messageInput.value;
                socket.send(JSON.stringify({
                    action: 'echo',
                    payload: {message},
                    requestId: crypto.randomUUID(),
                    metadata: {source: 'browser'}
                }));
                addMessage(`Sent echo: ${message}`);
                messageInput.value = '';
            } else {
                addMessage('Not connected to server');
            }
        });

        sendSumBtn.addEventListener('click', () => {
            if (socket && socket.readyState === WebSocket.OPEN) {
                const numbers = messageInput.value.split(',').map(Number);
                socket.send(JSON.stringify({
                    action: 'sum',
                    payload: {numbers},
                    requestId: crypto.randomUUID(),
                    metadata: {source: 'browser'}
                }));
                addMessage(`Sent sum request: ${numbers.join(', ')}`);
                messageInput.value = '';
            } else {
                addMessage('Not connected to server');
            }
        });

        sendAllBtn.addEventListener('click', () => {
            if (socket && socket.readyState === WebSocket.OPEN) {
                const message = messageInput.value;
                socket.send(JSON.stringify({
                    action: 'broadcast',
                    payload: {message},
                    requestId: crypto.randomUUID(),
                    metadata: {source: 'browser'}
                }));
                addMessage(`Sent all: ${message}`);
                messageInput.value = '';
            } else {
                addMessage('Not connected to server');
            }
        });
    </script>
</body>
</html>
