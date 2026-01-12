<?php
// 1. โหลด PHPMailer แบบ Manual
require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 2. เชื่อมต่อ DB (MariaDB)
$host = 'db'; $db = 'event_db'; $user = 'root'; $pass = 'rootpassword';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (Exception $e) { die("DB Connection Error: " . $e->getMessage()); }

$message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $reg_id = 'REG-' . date('ymd') . '-' . strtoupper(substr(uniqid(), -4));
    
    try {
        // บันทึกข้อมูลลง Database
        $sql = "INSERT INTO registrations (registration_id, company_name, event_name, booth_number, purpose, entry_date, ticket_count, contact_name, email, phone) VALUES (?,?,?,?,?,?,?,?,?,?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $reg_id, $_POST['company_name'], $_POST['event_name'], $_POST['booth_number'], 
            $_POST['purpose'], $_POST['entry_date'], $_POST['ticket_count'], 
            $_POST['contact_name'], $_POST['email'], $_POST['phone']
        ]);

        // 📨 ส่งอีเมลด้วย PHPMailer (เนื่องจาก mail() ใน Docker รันไม่ได้)
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'mailhb.impact.co.th';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'phichais@impact.co.th';
            $mail->Password   = 'Gano.2466'; 
            $mail->SMTPSecure = ''; 
            $mail->Port       = 25;
            $mail->CharSet    = 'UTF-8';

            // ตั้งค่า SSL เพื่อหลีกเลี่ยงปัญหาใน Docker
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );

            $mail->setFrom('phichais@impact.co.th', 'Event Admin');
            $mail->addAddress($_POST['email']);

            $qr_url = "https://quickchart.io/qr?text=" . urlencode($reg_id) . "&size=250";
            $mail->isHTML(true);
            $mail->Subject = "=?UTF-8?B?".base64_encode("QR Code สำหรับเข้างานของคุณ")."?=";
            $mail->Body    = "
                <div style='font-family: sans-serif; border: 1px solid #ddd; padding: 20px; border-radius: 10px;'>
                    <h3 style='color: #007bff;'>ลงทะเบียนสำเร็จ!</h3>
                    <p>เรียนคุณ <b>{$_POST['contact_name']}</b></p>
                    <p>รหัสลงทะเบียน: <b>$reg_id</b></p>
                    <div style='text-align: center; margin-top: 20px;'>
                        <img src='$qr_url' alt='QR Code'>
                    </div>
                </div>";

            $mail->send();
            $message = "<div class='alert alert-success'>✅ ลงทะเบียนและส่ง QR Code สำเร็จ!</div>";
        } catch (Exception $e) { 
            $message = "<div class='alert alert-warning'>⚠️ บันทึกข้อมูลสำเร็จ แต่ส่งอีเมลไม่ได้: {$mail->ErrorInfo}</div>";
        }

    } catch (Exception $e) { 
        $message = "<div class='alert alert-danger'>❌ Error: " . $e->getMessage() . "</div>"; 
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลงทะเบียนเข้างาน - Event System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f7f9; font-family: 'Segoe UI', Tahoma, sans-serif; }
        .reg-card { max-width: 700px; margin: 50px auto; background: white; padding: 40px; border-radius: 20px; box-shadow: 0 15px 35px rgba(0,0,0,0.1); }
        .form-label { font-weight: 600; color: #495057; }
        .btn-primary { padding: 12px; font-weight: bold; border-radius: 10px; transition: 0.3s; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,123,255,0.3); }
        .required { color: #dc3545; }
        hr { opacity: 0.1; }
    </style>
</head>
<body>

<div class="container">
    <div class="reg-card">
        <h2 class="text-center mb-4 text-primary">ลงทะเบียนเข้าปฏิบัติงาน</h2>
        <p class="text-center text-muted mb-4">กรุณากรอกข้อมูลให้ครบถ้วนเพื่อรับ QR Code ผ่านอีเมล</p>
        
        <?php echo $message; ?>

        <form method="POST" class="needs-validation">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">ชื่อบริษัท/หน่วยงาน <span class="required">*</span></label>
                    <input type="text" name="company_name" class="form-control" placeholder="บริษัท อิมแพ็ค จำกัด" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label">เลือกชื่องาน <span class="required">*</span></label>
                    <select name="event_name" class="form-select" required>
                        <option value="">-- เลือกรายการงาน --</option>
                        <?php
                        try {
                            $stmt = $pdo->query("SELECT event_name FROM events ORDER BY id DESC");
                            while ($row = $stmt->fetch()) {
                                echo "<option value='".htmlspecialchars($row['event_name'])."'>".htmlspecialchars($row['event_name'])."</option>";
                            }
                        } catch(Exception $e) { echo "<option disabled>ไม่พบข้อมูลงานในระบบ</option>"; }
                        ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">หมายเลขบูธ <span class="required">*</span></label>
                    <input type="text" name="booth_number" class="form-control" placeholder="เช่น A1, B15" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label">วัตถุประสงค์ <span class="required">*</span></label>
                    <select name="purpose" class="form-select" required>
                        <option value="ติดตั้ง">ติดตั้ง (Setup)</option>
                        <option value="รื้อถอน">รื้อถอน (Tear down)</option>
                        <option value="ซ่อมแซม">ซ่อมแซม (Repair)</option>
                        <option value="ส่งของ">ส่งของ (Delivery)</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">จำนวนทีมงาน (ท่าน) <span class="required">*</span></label>
                    <input type="number" name="ticket_count" class="form-control" value="1" min="1" required>
                </div>

                <div class="col-12">
                    <label class="form-label">วันที่เข้าปฏิบัติงาน <span class="required">*</span></label>
                    <input type="date" name="entry_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div class="col-12 mt-4"><hr></div>

                <div class="col-12">
                    <label class="form-label">ชื่อ-นามสกุล ผู้ติดต่อหน้างาน <span class="required">*</span></label>
                    <input type="text" name="contact_name" class="form-control" placeholder="นายสมชาย ใจดี" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label">อีเมล (เพื่อรับ QR Code) <span class="required">*</span></label>
                    <input type="email" name="email" class="form-control" placeholder="name@example.com" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label">เบอร์โทรศัพท์ <span class="required">*</span></label>
                    <input type="tel" name="phone" class="form-control" placeholder="0812345678" required>
                </div>

                <div class="col-12 mt-4">
                    <div class="form-check p-3 bg-light rounded border">
                        <input type="checkbox" name="consent" class="form-check-input ms-0 me-2" id="checkConsent" required>
                        <label class="form-check-label" for="checkConsent">
                            ฉันยอมรับเงื่อนไขการเข้าพื้นที่และตกลงให้จัดเก็บข้อมูลตามนโยบายความเป็นส่วนตัว <span class="required">*</span>
                        </label>
                    </div>
                </div>

                <div class="col-12 mt-4">
                    <button type="submit" class="btn btn-primary btn-lg w-100 shadow">ลงทะเบียนและรับ QR Code</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
