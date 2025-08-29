<?php
include '../../connection/connect.php';

// check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// fetch all orders
$sql = "SELECT * FROM orders";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo "<table border='1' cellpadding='8'>";
    echo "<tr>
            <th>ID</th>
            <th>Customer</th>
            <th>Email</th>
            <th>Mobile</th>
            <th>Status</th>
            <th>Total</th>
            <th>Payment Screenshot</th>
          </tr>";

    while ($row = $result->fetch_assoc()) {
        // build correct screenshot path
        $screenshot = "../../uploads/payment_screenshots/" . basename($row['payment_screenshot']);

        echo "<tr>
                <td>{$row['id']}</td>
                <td>{$row['customer_name']}</td>
                <td>{$row['email']}</td>
                <td>{$row['mobile']}</td>
                <td>{$row['status']}</td>
                <td>{$row['final_total']}</td>
                <td>";

        // check if screenshot exists
        if (!empty($row['payment_screenshot'])) {
            echo "<img src='$screenshot' width='120' alt='screenshot'>";
        } else {
            echo "No file";
        }

        echo "</td></tr>";
    }
    echo "</table>";
} else {
    echo "No orders found.";
}

$conn->close();
?>
