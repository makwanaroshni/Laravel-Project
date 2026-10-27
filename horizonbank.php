
<?php
// Database connection
$conn = new mysqli("localhost", "root", "ROSHNI11", "horizonbank");

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error . "\n");
}else {
    echo"successfully connected to database";
}
class User
{
    private $conn;
    private $name;
    private $accountNumber;
    private $email;
    private $balance;
    // Parameterized constructor
    public function __construct($conn, $name, $accountNumber, $email, $balance)
    {
        $this->conn = $conn;
        $this->name = $name;
        $this->accountNumber = $accountNumber;
        $this->email = $email;
        $this->balance = $balance;
    }
    // Add customer
    public function createCustomer()
    {
        $sql = "INSERT INTO customers (name, account_number, email, balance)
                VALUES (?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("sssd", $this->name, $this->accountNumber, $this->email, $this->balance);
        if ($stmt->execute()) {
            echo "\nCustomer added successfully!\n";
        } else {
            echo "\nError: " . $stmt->error . "\n";
        }
        $stmt->close();
    }
    // Display all customers
    public function displayAllCustomers()
    {
        $result = $this->conn->query("SELECT * FROM customers WHERE id");
        echo "\n========== ALL CUSTOMERS ==========\n";
        if ($result->num_rows == 0) {
            echo "No customers found.\n";
            return;
        }
        while ($row = $result->fetch_assoc()) {
            echo  $row['id'] . "\n";
            echo  $row['name'] . "\n";
            echo  $row['account_number'] . "\n";
            echo  $row['email'] . "\n";
            echo  $row['balance'] . "\n";
            echo "----------------------------------\n";
        }
    }
    // Display specific customer
    public function displayCustomer()
    {
        $sql = "SELECT * FROM customers WHERE account_number = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("s", $this->accountNumber);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            echo "\n========== CUSTOMER DETAILS ==========\n";
            echo $row['id'] . "\n";
            echo $row['name'] . "\n";
            echo $row['account_number'] . "\n";
            echo $row['email'] . "\n";
            echo "Current Balance: Rs. " . $row['balance'] . "\n";
        } else {
            echo "\nCustomer not found!\n";
        }
        $stmt->close();
    }
    // Update customer details
    public function updateCustomer()
    {
        $sql = "UPDATE customers SET name = ?, email = ? WHERE account_number = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("sss", $this->name, $this->email, $this->accountNumber);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            echo "\nCustomer details updated successfully!\n";
        } else {
            echo "\nNo changes made, or customer not found.\n";
        }
        $stmt->close();
    }
    // Delete customer
    public function deleteCustomer()
    {
        $sql = "DELETE FROM customers WHERE account_number = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("s", $this->accountNumber);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            echo "\nCustomer deleted successfully!\n";
        } else {
            echo "\nCustomer not found!\n";
        }
        $stmt->close();
    }
    // Credit amount
    public function credit($amount)
    {
        if ($amount <= 0) {
            echo "\nAmount must be greater than zero.\n";
            return;
        }
        $sql = "UPDATE customers SET balance = balance + ? WHERE account_number = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ds", $amount, $this->accountNumber);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            echo "\nAmount credited successfully!\n";
            $this->displayCustomer();
        } else {
            echo "\nCustomer not found!\n";
        }
        $stmt->close();
    }
    // Debit amount
    public function debit($amount)
    {
        if ($amount <= 0) {
            echo "\nAmount must be greater than zero.\n";
            return;
        }
        $sql = "UPDATE customers
                SET balance = balance - ?
                WHERE account_number = ? AND balance >= ?";

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("dsd", $amount, $this->accountNumber, $amount);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            echo "\nAmount debited successfully!\n";
            $this->displayCustomer();
        } else {
            echo "\nTransaction failed: customer not found or insufficient balance.\n";
        }
        $stmt->close();
    }
}
// Take input from terminal
function getInput($message)
{
    echo $message;
    return trim($message);
}
echo "       WELCOME TO HORIZON BANK      \n";
do {
    echo "\n1. Add Customer";
    echo "\n2. Display All Customers";
    echo "\n3. Display Specific Customer";
    echo "\n4. Update Customer";
    echo "\n5. Delete Customer";
    echo "\n6. Credit Amount";
    echo "\n7. Debit Amount";
    echo "\n8. Exit\n";
    $choice = getInput("\nEnter your choice: ");
    switch ($choice) {
        case "1":
            $name = getInput("Enter customer name: ");
            $account = getInput("Enter account number: ");
            $email = getInput("Enter email: ");
            $balance = getInput("Enter opening balance: ");

            if (!is_numeric($balance) || $balance < 0) {
                echo "\nEnter a valid, non-negative balance.\n";
                break;
            }
            $user = new User($conn, $name, $account, $email, $balance);
            $user->createCustomer();
            break;
        case "2":
            $user = new User($conn, "", "", "", 0);
            $user->displayAllCustomers();
            break;
        case "3":
            $account = getInput("Enter account number: ");
            $user = new User($conn, "", $account, "", 0);
            $user->displayCustomer();
            break;
        case "4":
            $account = getInput("Enter account number: ");
            $name = getInput("Enter updated name: ");
            $email = getInput("Enter updated email: ");
            $user = new User($conn, $name, $account, $email, 0);
            $user->updateCustomer();
            break;
        case "5":
            $account = getInput("Enter account number: ");
            $user = new User($conn, "", $account, "", 0);
            $user->deleteCustomer();
            break;
        case "6":
            $account = getInput("Enter account number: ");
            $amount = getInput("Enter amount to credit: ");
            if (!is_numeric($amount)) {
                echo "\nEnter a valid amount.\n";
                break;
            }
            $user = new User($conn, "", $account, "", 0);
            $user->credit((float)$amount);
            break;
        case "7":
            $account = getInput("Enter account number: ");
            $amount = getInput("Enter amount to debit: ");
            if (!is_numeric($amount)) {
                echo "\nEnter a valid amount.\n";
                break;
            }
            $user = new User($conn, "", $account, "", 0);
            $user->debit((float)$amount);
            break;
        case "8":
            echo "\nThank you for using Horizon Bank!\n";
            break;
        default:
            echo "\nInvalid choice. Please try again.\n";
    }
} while ($choice != "8");
$conn->close();

?>