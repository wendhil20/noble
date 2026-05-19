<?php
// main-send-reply-page-1-A.php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";

header('Content-Type: application/json');

try {
    // Check if it's multipart form data (with files) or JSON
    $isMultipart = strpos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data') !== false;
    
    if ($isMultipart) {
        // Handle form data with files
        $request_id = intval($_POST['request_id'] ?? 0);
        $email = trim($_POST['email'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $message = trim($_POST['message'] ?? '');
    } else {
        // Handle JSON data (legacy)
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
        if (!$data) {
            throw new Exception('Invalid JSON or no data received');
        }
        
        $request_id = intval($data['request_id'] ?? 0);
        $email = trim($data['email'] ?? '');
        $full_name = trim($data['full_name'] ?? '');
        $message = trim($data['message'] ?? '');
    }
    
    // Validation
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
    
    
    $admin_id = intval($_SESSION['noble_user_id'] ?? $_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
    
    if (!$admin_id) {
        throw new Exception('Admin not logged in');
    }
    
    // Handle file uploads
    $uploadedFiles = [];
    $fileError = null;
    $filesJson = null;
    
    if (!empty($_FILES['reply_files'])) {
        $uploadDir = ROOT_PATH . '/uploads/replies/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $maxSize = 10 * 1024 * 1024; // 10MB
        $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'gif', 'zip'];
        
        foreach ($_FILES['reply_files']['tmp_name'] as $key => $tmpName) {
            if ($_FILES['reply_files']['error'][$key] !== UPLOAD_ERR_OK) {
                continue;
            }
            
            $fileName = $_FILES['reply_files']['name'][$key];
            $fileSize = $_FILES['reply_files']['size'][$key];
            
            // Validate file size
            if ($fileSize > $maxSize) {
                continue;
            }
            
            // Validate file extension
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExtensions)) {
                continue;
            }
            
            // Generate unique filename
            $newFileName = time() . '_' . uniqid() . '_' . basename($fileName);
            $filePath = $uploadDir . $newFileName;
            
            // Move uploaded file
            if (move_uploaded_file($tmpName, $filePath)) {
                $uploadedFiles[] = [
                    'original_name' => $fileName,
                    'stored_name' => $newFileName,
                    'path' => $filePath
                ];
            }
        }
        
        if (!empty($uploadedFiles)) {
            $filesJson = json_encode($uploadedFiles);
        }
    }
    
    // Insert reply into database
    $query = "INSERT INTO custom_quote_replies (request_id, admin_id, message, files, created_at) VALUES (?, ?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    $stmt->bind_param("iiss", $request_id, $admin_id, $message, $filesJson);
    
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
        
        // Build email body with attachments info
        $attachmentsInfo = '';
        if (!empty($uploadedFiles)) {
            $attachmentsInfo = '
            <div style="background-color: #f0fdf4; padding: 12px; border-radius: 6px; margin: 15px 0; border-left: 4px solid #22c55e;">
                <strong style="color: #15803d;">📎 Attached Files:</strong>
                <ul style="margin: 8px 0 0 20px; color: #166534;">
            ';
            foreach ($uploadedFiles as $file) {
                $attachmentsInfo .= '<li>' . htmlspecialchars($file['original_name']) . '</li>';
            }
            $attachmentsInfo .= '
                </ul>
            </div>
            ';
        }
        
        $mail->Body = "
        <html>
            <body style='font-family: Arial, sans-serif; color: #333;'>
                <h2 style='color: #f97316;'>Response to Your Request</h2>
                <p>Hi " . htmlspecialchars($full_name) . ",</p>
                <p>Thank you for your customize request. Here's our response:</p>
                <div style='background-color: #f9fafb; padding: 16px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #f97316;'>
                    " . nl2br(htmlspecialchars($message)) . "
                </div>
                " . $attachmentsInfo . "
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
        
        // Attach files to email
        foreach ($uploadedFiles as $file) {
            if (file_exists($file['path'])) {
                $mail->addAttachment($file['path'], $file['original_name']);
            }
        }
        
        $mail->send();
        $emailSent = true;
        
    } catch (Exception $e) {
        // Email failed but reply was saved
        error_log('Email error: ' . $e->getMessage());
    }
    
    echo json_encode([
        'success' => true,
        'message' => $emailSent ? 'Reply sent successfully' . (!empty($uploadedFiles) ? ' with ' . count($uploadedFiles) . ' file(s)' : '') . '!' : 'Reply saved but email not sent',
        'emailSent' => $emailSent,
        'filesUploaded' => count($uploadedFiles)
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