<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auto Payment</title>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
        function trigger_btn(){
            document.getElementById('checkoutdiv').style.display='none';
            document.getElementById('sbt').click();
        }
    </script>
</head>
<body onload='trigger_btn()'>

<?php
// Database connection (assuming $con is your connection object)
$con = mysqli_connect("localhost", "root", "", "hbwebsite"); // Replace with your database details

if (!$con) {
    die("Connection failed: " . mysqli_connect_error());
}

// Start the session to get user data from session
session_start();

// Check if the user is logged in
if (!isset($_SESSION['login']) || $_SESSION['login'] != true) {
    die("Error: User not logged in.");
}

// Fetch the user_id from the session
$user_id = $_SESSION['uId'];  // This should be set during login

// Extract data from the POST array
$api_key = 'rzp_test_s3H7nkFPt9cUx6'; // Replace with your Razorpay API Key
$user_name = isset($_POST['name']) ? htmlspecialchars($_POST['name']) : 'Unknown User';
$phonenum = isset($_POST['phonenum']) ? htmlspecialchars($_POST['phonenum']) : 'Unknown';
$address = isset($_POST['address']) ? htmlspecialchars($_POST['address']) : '';

$check_in = isset($_POST['checkin']) ? htmlspecialchars($_POST['checkin']) : '';
$check_out = isset($_POST['checkout']) ? htmlspecialchars($_POST['checkout']) : '';

// Validate date input
if (empty($check_in) || empty($check_out)) {
    die("Error: Check-in and check-out dates are required.");
}

$check_in_date = new DateTime($check_in);
$check_out_date = new DateTime($check_out);
$interval = $check_in_date->diff($check_out_date);
$duration = $interval->days;

$room_id = isset($_POST['room_id']) ? $_POST['room_id'] : null;
if (!$room_id) {
    die("Error: Room data is missing. Please select a room first.");
}

// Fetch room details
$room_result = mysqli_query($con, "SELECT * FROM `rooms` WHERE `id` = '$room_id' LIMIT 1");
if (!$room_result || mysqli_num_rows($room_result) == 0) {
    die("Error: Room data not found.");
}

$room_data = mysqli_fetch_assoc($room_result);
$room_name = $room_data['name'];
$price_per_night = $room_data['price'];
$room_no = $room_data['id'];

$total_pay = $price_per_night * $duration;

$booking_status = 'booked';
$order_id = uniqid('ORD');
$trans_id = '';
$trans_amt = $total_pay;
$trans_status = 'pending';

// Insert into booking_order with the correct user_id
$insert_booking_order = "INSERT INTO `booking_order` (`user_id`, `room_id`, `check_in`, `check_out`, `arrival`, `refund`, `booking_status`, `order_id`, `trans_id`, `trans_amt`, `trans_status`, `datentime`) 
VALUES ('$user_id', '$room_id', '$check_in', '$check_out', '0', NULL, '$booking_status', '$order_id', '$trans_id', '$trans_amt', '$trans_status', current_timestamp())";

if (!mysqli_query($con, $insert_booking_order)) {
    die("Error: Could not insert booking order. " . mysqli_error($con));
}

$booking_id = mysqli_insert_id($con);

// Insert into booking_details table
$insert_booking_details = "INSERT INTO `booking_details` (`booking_id`, `room_name`, `price`, `total_pay`, `room_no`, `user_name`, `phonenum`, `address`) 
VALUES ('$booking_id', '$room_name', '$price_per_night', '$total_pay', '$room_no', '$user_name', '$phonenum', '$address')";

if (!mysqli_query($con, $insert_booking_details)) {
    die("Error: Could not insert booking details. " . mysqli_error($con));
}
?>

<div id='checkoutdiv'>
    <form action="thanks.php" method="POST">
        <script
            src="https://checkout.razorpay.com/v1/checkout.js"
            data-key="<?php echo $api_key;?>" 
            data-amount="<?php echo $total_pay*100;?>" 
            data-currency="INR"
            data-name="SSS Software Systems"
            data-description="Room Booking Payment"
            data-image="https://cdn.razorpay.com/logos/GhRQcyean79PqE_medium.png"
            data-prefill.name="<?php echo $user_name;?>"
            data-prefill.email="<?php echo $_POST['email'];?>"
            data-notes.shopping_order_id="3456"
            data-id="12345">
        </script>
        <input type="submit" id="sbt" value="Proceed to Payment">
        <input type="hidden" name="Customer_name" value="<?php echo $user_name;?>">
        <input type="hidden" name="Customer_email" value="<?php echo $_POST['email'];?>">
        <input type="hidden" name="totalamount" value="<?php echo $total_pay;?>">
    </form>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function () {
        // Razorpay configuration
        const options = {
            key: "<?php echo $api_key; ?>", // Razorpay API key
            amount: "<?php echo $total_pay * 100; ?>", // Amount in paise
            currency: "INR",
            name: "<?php echo $user_name; ?>",
            description: "Room Booking Payment",
            prefill: {
                name: "<?php echo $user_name; ?>",
                contact: "<?php echo $phonenum; ?>",
            },
            notes: {
                address: "<?php echo $address; ?>",
                checkin_date: "<?php echo $check_in; ?>",
                checkout_date: "<?php echo $check_out; ?>",
            },
            handler: function (response) {
                // Submit payment details to the server
                const paymentForm = document.createElement('form');
                paymentForm.action = 'thanks.php'; // Redirect to your thank-you page
                paymentForm.method = 'POST';

                // Add payment details as hidden inputs
                paymentForm.innerHTML = `
                    <input type="hidden" name="payment_id" value="${response.razorpay_payment_id}">
                    <input type="hidden" name="customer_name" value="<?php echo $user_name; ?>">
                    <input type="hidden" name="customer_phone" value="<?php echo $phonenum; ?>">
                    <input type="hidden" name="customer_address" value="<?php echo $address; ?>">
                    <input type="hidden" name="checkin_date" value="<?php echo $check_in; ?>">
                    <input type="hidden" name="checkout_date" value="<?php echo $check_out; ?>">
                    <input type="hidden" name="total_amount" value="<?php echo $total_pay; ?>">
                `;
                document.body.appendChild(paymentForm);
                paymentForm.submit();
            },
            theme: {
                color: "#3399cc",
            },
        };

        // Open Razorpay modal automatically
        const rzp = new Razorpay(options);
        rzp.open();

        // Handle close event if user exits the Razorpay modal
        rzp.on('payment.failed', function (response) {
            alert("Payment failed. Reason: " + response.error.description);
        });
    });
</script>

</body>
</html>
