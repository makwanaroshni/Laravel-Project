
<?php
// Horizon Bank Core System
// Run using: php bank.php

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host = "localhost";
$username = "root";
$password = "ROSHNI11"; // Change this to your MySQL password
$database = "horizonbank";

try {
    // Connect to MySQL server
    $conn = new mysqli($host, $username, $password);
    $conn->set_charset("utf8mb4");

    // Create database if it does not exist
    $conn->query("CREATE DATABASE IF NOT EXISTS `$database`");
    $conn->select_db($database);

    // Create customers table
    $conn->query("
        CREATE TABLE IF NOT EXISTS customers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            account_number VARCHAR(30) NOT NULL UNIQUE,
            email VARCHAR(150) NOT NULL,
            balance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Create transaction history table
    $conn->query("
        CREATE TABLE IF NOT EXISTS transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            account_number VARCHAR(30) NOT NULL,
            transaction_type ENUM('CREDIT', 'DEBIT') NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

} catch (mysqli_sql_exception $e) {
    die("Database error: " . $e->getMessage() .
        "\nCheck your MySQL username and password.\n");
}

// Take input safely from terminal
function input($message) {
    echo $message;
    return trim(fgets(STDIN));
}

// Find customer by account number
function findCustomer($conn, $accountNumber) {
    $stmt = $conn->prepare(
        "SELECT * FROM customers WHERE account_number = ?"
    );
    $stmt->bind_param("s", $accountNumber);
    $stmt->execute();

    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Add a new customer
function addCustomer($conn) {
    echo "\n--- Add New Customer ---\n";

    $name = input("Enter customer name: ");
    $account = input("Enter account number: ");
    $email = input("Enter email: ");
    $balanceInput = input("Enter initial balance: ");

    if ($name === "" || $account === "" || $email === "") {
        echo "Error: All customer fields are required.\n";
        return;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "Error: Enter a valid email address.\n";
        return;
    }

    if (!is_numeric($balanceInput) ||
        !is_finite((float)$balanceInput) ||
        (float)$balanceInput < 0 ||
        (float)$balanceInput > 9999999999.99) {
        echo "Error: Enter a valid non-negative balance.\n";
        return;
    }

    $balance = (float)$balanceInput;

    try {
        $stmt = $conn->prepare(
            "INSERT INTO customers
             (name, account_number, email, balance)
             VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param("sssd", $name, $account, $email, $balance);
        $stmt->execute();

        echo "Customer added successfully!\n";
    } catch (mysqli_sql_exception $e) {
        if ($e->getCode() == 1062) {
            echo "Error: This account number already exists.\n";
        } else {
            echo "Could not add customer: " . $e->getMessage() . "\n";
        }
    }
}

// Display all customers
function showAllCustomers($conn) {
    echo "\n--- All Horizon Bank Customers ---\n";

    $result = $conn->query(
        "SELECT * FROM customers ORDER BY id"
    );

    if ($result->num_rows === 0) {
        echo "No customers found.\n";
        return;
    }

    while ($row = $result->fetch_assoc()) {
        echo "----------------------------------\n";
        echo "ID: " . $row["id"] . "\n";
        echo "Name: " . $row["name"] . "\n";
        echo "Account Number: " . $row["account_number"] . "\n";
        echo "Email: " . $row["email"] . "\n";
        echo "Balance: Rs. " . number_format((float)$row["balance"], 2) . "\n";
        echo "Created At: " . $row["created_at"] . "\n";
    }
    echo "----------------------------------\n";
}

// Display one customer's details
function showCustomer($conn) {
    $account = input("Enter account number: ");
    $customer = findCustomer($conn, $account);

    if (!$customer) {
        echo "Customer not found.\n";
        return;
    }

    echo "\n--- Customer Details ---\n";
    echo "ID: " . $customer["id"] . "\n";
    echo "Name: " . $customer["name"] . "\n";
    echo "Account Number: " . $customer["account_number"] . "\n";
    echo "Email: " . $customer["email"] . "\n";
    echo "Current Balance: Rs. " .
        number_format((float)$customer["balance"], 2) . "\n";
    echo "Created At: " . $customer["created_at"] . "\n";
}

// Deposit or withdraw money
function performTransaction($conn, $type) {
    $account = input("Enter account number: ");
    $amountInput = input("Enter amount: ");

    if (!is_numeric($amountInput) ||
        !is_finite((float)$amountInput) ||
        (float)$amountInput <= 0 ||
        (float)$amountInput > 9999999999.99) {
        echo "Error: Enter a valid positive amount.\n";
        return;
    }

    // Store amounts as integer paise to avoid floating-point calculations.
    $amountPaise = (int)round((float)$amountInput * 100);

    if ($amountPaise <= 0) {
        echo "Error: Amount must be at least Rs. 0.01.\n";
        return;
    }

    try {
        $conn->begin_transaction();

        $stmt = $conn->prepare(
            "SELECT balance FROM customers
             WHERE account_number = ? FOR UPDATE"
        );
        $stmt->bind_param("s", $account);
        $stmt->execute();
        $customer = $stmt->get_result()->fetch_assoc();

        if (!$customer) {
            $conn->rollback();
            echo "Customer not found.\n";
            return;
        }

        $balancePaise = (int)round((float)$customer["balance"] * 100);

        if ($type === "DEBIT" && $amountPaise > $balancePaise) {
            $conn->rollback();
            echo "Transaction failed: Insufficient balance.\n";
            return;
        }

        $newBalancePaise = ($type === "CREDIT")
            ? $balancePaise + $amountPaise
            : $balancePaise - $amountPaise;

        $newBalance = $newBalancePaise / 100;
        $amount = $amountPaise / 100;

        $stmt = $conn->prepare(
            "UPDATE customers SET balance = ?
             WHERE account_number = ?"
        );
        $stmt->bind_param("ds", $newBalance, $account);
        $stmt->execute();

        $stmt = $conn->prepare(
            "INSERT INTO transactions
             (account_number, transaction_type, amount)
             VALUES (?, ?, ?)"
        );
        $stmt->bind_param("ssd", $account, $type, $amount);
        $stmt->execute();

        $conn->commit();

        echo "\nTransaction successful!\n";
        echo "Type: " . $type . "\n";
        echo "Amount: Rs. " . number_format($amount, 2) . "\n";
        echo "Updated Balance: Rs. " .
            number_format($newBalance, 2) . "\n";

    } catch (Throwable $e) {
        try {
            $conn->rollback();
        } catch (Throwable $ignored) {
            // Transaction may already have ended.
        }
        echo "Transaction failed. Please check the database and try again.\n";
    }
}

// Display transaction history
function showTransactions($conn) {
    $account = input("Enter account number: ");

    if (!findCustomer($conn, $account)) {
        echo "Customer not found.\n";
        return;
    }

    $stmt = $conn->prepare(
        "SELECT * FROM transactions
         WHERE account_number = ?
         ORDER BY id DESC"
    );
    $stmt->bind_param("s", $account);
    $stmt->execute();
    $result = $stmt->get_result();

    echo "\n--- Transaction History ---\n";

    if ($result->num_rows === 0) {
        echo "No transactions found.\n";
        return;
    }

    while ($row = $result->fetch_assoc()) {
        echo "Type: " . $row["transaction_type"] .
             " | Amount: Rs. " .
             number_format((float)$row["amount"], 2) .
             " | Date: " . $row["created_at"] . "\n";
    }
}

// Main menu
while (true) {
    echo "\n====================================\n";
    echo "       HORIZON BANK CORE SYSTEM\n";
    echo "====================================\n";
    echo "1. Add New Customer\n";
    echo "2. Show All Customers\n";
    echo "3. Show Specific Customer & Balance\n";
    echo "4. Credit / Deposit Money\n";
    echo "5. Debit / Withdraw Money\n";
    echo "6. Show Transaction History\n";
    echo "7. Exit\n";
    echo "====================================\n";

    $choice = input("Enter your choice (1-7): ");

    switch ($choice) {
        case "1":
            addCustomer($conn);
            break;

        case "2":
            showAllCustomers($conn);
            break;

        case "3":
            showCustomer($conn);
            break;

        case "4":
            performTransaction($conn, "CREDIT");
            break;

        case "5":
            performTransaction($conn, "DEBIT");
            break;

        case "6":
            showTransactions($conn);
            break;

        case "7":
            echo "Thank you for using Horizon Bank!\n";
            $conn->close();
            exit;

        default:
            echo "Invalid choice. Please enter a number from 1 to 7.\n";
    }
}
