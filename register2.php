<?php
require_once('../wp-load.php');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ตั้งค่าการเชื่อมต่อฐานข้อมูล
$host = 'db';
$db   = 'event_db';
$user = 'root';
$pass = 'rootpassword';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     die("Connection failed: " . $e->getMessage());
}

$message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. สร้าง Registration ID
    $reg_id = 'REG-' . date('ymd') . '-' . strtoupper(substr(uniqid(), -4));
    
    // 2. คำสั่ง SQL (ตรวจสอบชื่อคอลัมน์ entry_date/entry_datetime ใน DB ให้ดี)
    // ผมใช้ entry_date ตามที่คุณเขียนใน FORM ล่าสุด
    $sql = "INSERT INTO registrations 
            (registration_id, company_name, event_name, booth_number, purpose, entry_date, ticket_count, contact_name, email, phone, consent_status, email_sent_status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Sent')";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $reg_id,
            $_POST['company_name'],
            $_POST['event_name'],
            $_POST['booth_number'],
            $_POST['purpose'],
            $_POST['entry_date'],
            $_POST['ticket_count'],
            $_POST['contact_name'],
            $_POST['email'],
            $_POST['phone'],
            isset($_POST['consent']) ? 1 : 0
        ]);

        // --- หากบันทึกสำเร็จค่อยทำส่วนนี้ ---
        
        // เตรียมข้อมูลส่งเมล
        $to      = $_POST['email'];
        $subject = "QR Code สำหรับเข้างานของคุณ";
        $qr_url  = "https://quickchart.io/qr?text=" . urlencode($reg_id) . "&size=250";
        
        $mail_content = "<h3>ลงทะเบียนสำเร็จ</h3>";
        $mail_content .= "<p>รหัสของคุณคือ: <b>$reg_id</b></p>";
        $mail_content .= "<img src='$qr_url' alt='QR Code'>";

        $headers = array('Content-Type: text/html; charset=UTF-8');

        // ส่งเมลผ่าน WordPress
        $mail_sent = wp_mail($to, $subject, $mail_content, $headers);

        // แสดงข้อความสำเร็จที่หน้าจอ
        $message = "<div style='color:green; padding:10px; border:1px solid green; margin-bottom:20px;'>";
        $message .= "✅ ลงทะเบียนสำเร็จ! ID ของคุณคือ: <b>$reg_id</b><br>";
        $message .= ($mail_sent) ? "📧 ส่ง QR Code ไปที่อีเมลเรียบร้อยแล้ว" : "⚠️ บันทึกข้อมูลแล้ว แต่ส่งอีเมลไม่สำเร็จ (โปรดเช็ค SMTP)";
        $message .= "</div>";

    } catch (Exception $e) {
        // หากบันทึกไม่สำเร็จ จะโชว์ Error ที่นี่
        $message = "<div style='color:red; padding:10px; border:1px solid red;'>❌ เกิดข้อผิดพลาด: " . $e->getMessage() . "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>แบบฟอร์มลงทะเบียนเข้างาน</title>
    <style>
        body { font-family: 'Tahoma', sans-serif; background: #f4f4f4; padding: 20px; }
        .container { max-width: 600px; background: white; padding: 30px; border-radius: 10px; margin: auto; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        input, select, textarea { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; }
        button { background: #28a745; color: white; border: none; padding: 15px; width: 100%; border-radius: 5px; cursor: pointer; font-size: 16px; }
        label { font-weight: bold; }
    </style>
</head>
<body>

<div class="container">
    <h2>ลงทะเบียนเข้าทำงาน</h2>
    <?php echo $message; ?>
    <form method="POST">
        <label>ชื่อบริษัท</label>
        <input type="text" name="company_name" required>

        <label>ชื่องานแสดงที่เข้าทำงาน</label>
        <select name="event_name" required>
            <option value="Event 1">Event 1</option>
            <option value="Event 2">Event 2</option>
            <option value="Event 3">Event 3</option>
        </select>

        <label>หมายเลขบูธ</label>
        <input type="text" name="booth_number" placeholder="เช่น A01">

        <label>วัตถุประสงค์</label>
       
        <select name="purpose" required>
            <option value="Event 1">ติดตั้ง</option>
            <option value="Event 2">รื้อถอน</option>
            <option value="Event 3">ซ่อมแซม</option>
        </select>

        <label>วัน เวลา เข้างาน</label>
        <input type="date" name="entry_date" required>

        <label>จำนวนบัตรที่ต้องการ</label>
        <input type="number" name="ticket_count" value="1" min="1">

        <label>ชื่อ-นามสกุลผู้ติดต่อ</label>
        <input type="text" name="contact_name" required>

        <label>Email</label>
        <input type="email" name="email" required>

        <label>เบอร์ติดต่อ</label>
        <input type="tel" name="phone" required>

        <label>
            <input type="checkbox" name="consent" required style="width: auto;"> ยินยอมให้เก็บข้อมูลส่วนบุคคล
        </label>

        <button type="submit">ลงทะเบียนและรับ QR Code</button>
    </form>
</div>

</body>

</html>
