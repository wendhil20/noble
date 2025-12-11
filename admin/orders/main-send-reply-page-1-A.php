<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_name("nobleadmin");
session_start();

header('Content-Type: application/json');

try {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (!$data) {
        throw new Exception('Invalid JSON or no data received');
    }
    
    $request_id = intval($data['request_id'] ?? 0);
    $email = trim($data['email'] ?? '');
    $full_name = trim($data['full_name'] ?? '');
    $message = trim($data['message'] ?? '');
    
    if (!$request_id) {
        throw new Exception('Invalid request ID');
    }
    
    if (empty($email)) {
        throw new Exception('Email is empty');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }
    
    if (empty($message)) {
        throw new Exception('Message is empty');
    }
    
    if (empty($full_name)) {
        throw new Exception('Full name is empty');
    }
    
    if (!file_exists('../../connection/connect.php')) {
        throw new Exception('Connection file not found');
    }
    
    include '../../connection/connect.php';
    
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception('Database connection error');
    }
    
    $admin_id = intval($_SESSION['noble_user_id'] ?? $_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
    
    if (!$admin_id) {
        throw new Exception('Admin not logged in');
    }
    
    $query = "INSERT INTO custom_quote_replies (request_id, admin_id, message, created_at) VALUES (?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    $stmt->bind_param("iis", $request_id, $admin_id, $message);
    
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }
    
    $stmt->close();
    
    $emailSent = false;
    try {
        require '../../vendor/autoload.php';
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'noblehomeconst.ph@gmail.com';
        $mail->Password = 'icup vicc amrv xbxh';
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->Timeout = 10;
        
        $mail->setFrom('noblehomeconst.ph@gmail.com', 'Noble Home');
        $mail->addAddress($email, $full_name);
        $mail->isHTML(true);
        $mail->Subject = 'Re: Your Customize Request - Noble Home';
        $mail->Body = "
        <html>
            <body style='font-family: Arial, sans-serif; color: #333;'>
                <h2 style='color: #f97316;'>Response to Your Request</h2>
                <p>Hi " . htmlspecialchars($full_name) . ",</p>
                <p>Thank you for your customize request. Here's our response:</p>
                <div style='background-color: #f9fafb; padding: 16px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #f97316;'>
                    " . nl2br(htmlspecialchars($message)) . "
                </div>
                <p>If you have any questions, feel free to reply to this email or contact us.</p>
                <p>Best regards,<br><strong>Noble Home Construction Team</strong></p>
                <hr style='border: none; border-top: 1px solid #ddd; margin: 20px 0;'>
                <p style='font-size: 12px; color: #666;'>
                    📞 (02) 8822-1295 | 📱 +63 992-239-4563<br>
                    💬 WhatsApp: +63 992-239-4563 | 📧 noblehomeconst.ph@gmail.com
                </p>
            </body>
        </html>
        ";
        
        $mail->send();
        $emailSent = true;
        
    } catch (Exception $e) {
        // Email failed but reply was saved
    }
    
    echo json_encode([
        'success' => true,
        'message' => $emailSent ? 'Reply sent successfully!' : 'Reply saved but email not sent',
        'emailSent' => $emailSent
    ]);
    exit();

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    exit();
}
?>