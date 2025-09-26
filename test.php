<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>PayMongo Checkout Sample</title>
</head>
<body>
  <h2>PayMongo Checkout</h2>
  <input type="number" id="amount" placeholder="Enter Amount (₱)">
  <button id="payBtn">Pay Now</button>

  <script>
    document.getElementById('payBtn').addEventListener('click', async () => {
      const amount = document.getElementById('amount').value;

      const res = await fetch("paymongo-create-session.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ amount })
      });

      const data = await res.json();
      console.log(data);

      if (data.data && data.data.attributes.checkout_url) {
        window.location.href = data.data.attributes.checkout_url;
      } else {
        alert("Failed to create checkout session.");
      }
    });
  </script>
</body>
</html>
