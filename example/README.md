# Example PHP Setup

This repository contains example PHP scripts to demonstrate various functionalities. The provided scripts are:

1. `index.php`
2. `broadcast_from_cli.php`
3. `server.php`

## Prerequisites

To run these PHP scripts, ensure you have PHP (version 8.1 or higher) installed.

## Setup Instructions

### 1. Running the `server.php` Script

1. Open your terminal.
2. Navigate to the directory containing your PHP scripts.
3. Execute the `server.php` script using the PHP CLI:
    ```sh
    php server.php
    ```

### 2. Starting the PHP Built-in Server

1. Open a new terminal window or tab.
2. Navigate to the `public` directory where your `index.php` is located.
3. Start the PHP built-in server:
    ```sh
    php -S 127.0.0.1:8000
    ```

### 3. Accessing `index.php`

1. Open your web browser.
2. Navigate to `http://localhost:8000/index.php`.
3. Ensure the WebSocket connection is established.
4. You can interact with the page using the "Send Echo," "Send Sum," and "Send All" buttons. For "Send All," you need two connections. Make sure to fill up the message input field for all commands.

### 4. Running `broadcast_from_cli.php`

1. Open your terminal.
2. Navigate to the directory where `broadcast_from_cli.php` is located.
3. Execute the script using the PHP CLI:
    ```sh
    php broadcast_from_cli.php
    ```

### 5. Additional Configuration

If your scripts require additional configuration such as database connections or specific environment settings, update the scripts accordingly. For example, you might need to set database connection details in `server.php`.

## Conclusion

You should now have the example PHP scripts set up and running on your local environment. Modify and extend the scripts as needed for your specific use case.