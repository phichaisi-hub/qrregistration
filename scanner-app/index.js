const express = require('express');
const mysql = require('mysql2/promise');
const path = require('path');
const app = express();

app.use(express.static('public'));
app.use(express.json());

let db;

/**
 * ฟังก์ชันสำหรับเชื่อมต่อฐานข้อมูล
 * มีระบบ Retry หากเชื่อมต่อไม่ได้ (ป้องกันแอปพังช่วงที่ DB กำลัง Boot)
 */
async function initDatabase() {
    try {
        db = await mysql.createConnection({
            host: 'db',
            user: 'root',
            password: 'rootpassword',
            database: 'event_db',
            charset: 'utf8mb4' // บังคับรองรับภาษาไทย
        });
        console.log('✅ Connected to MySQL Database (Thai Language Supported)');
    } catch (error) {
        console.error('❌ Failed to connect to MySQL. Retrying in 5 seconds...', error.message);
        setTimeout(initDatabase, 5000); 
    }
}

// เริ่มต้นเชื่อมต่อฐานข้อมูล
initDatabase();

/**
 * API สำหรับยืนยันการสแกนเข้างาน
 */
app.post('/scan', async (req, res) => {
    const { registration_id } = req.body;

    // ตรวจสอบความพร้อมของฐานข้อมูล
    if (!db) {
        return res.status(500).json({ status: "error", message: "ระบบฐานข้อมูลยังไม่พร้อม กรุณารอสักครู่" });
    }

    try {
        // 1. ตรวจสอบว่า ID นี้มีอยู่ในระบบหรือไม่
        const [rows] = await db.execute(
            'SELECT * FROM registrations WHERE registration_id = ?', 
            [registration_id]
        );

        if (rows.length === 0) {
            return res.json({ status: "not_found", message: "ไม่พบ ID ในระบบ" });
        }

        const user = rows[0];

        // 2. ตรวจสอบว่าเคยสแกนไปหรือยัง (attendance ไม่เป็น NULL)
        if (user.attendance !== null) {
            return res.json({ 
                status: "already_scanned",
                message: "สแกนเข้างานแล้ว",
                company_name: user.company_name,
                attendance_time: user.attendance 
            });
        }

        // 3. บันทึกเวลาสแกนสำเร็จ (ใช้ NOW() ของ Database)
        await db.execute(
    'UPDATE registrations SET attendance = NOW() WHERE registration_id = ?',
    [registration_id]
);

// ดึงเวลาที่เพิ่งบันทึกไปกลับมาเพื่อแสดงผล (เพื่อความแม่นยำของหน้าจอ)
const [updatedRows] = await db.execute(
    'SELECT attendance FROM registrations WHERE registration_id = ?',
    [registration_id]
);
const finalScanTime = updatedRows[0].attendance;

// 4. ส่งผลลัพธ์กลับไปยังหน้าจอ Scanner
res.json({
    status: "success",
    message: "สแกนสำเร็จ!",
    company_name: user.company_name,
    contact_name: user.contact_name,
    scan_time: finalScanTime // ใช้เวลาจาก DB โดยตรง
});

        // 4. ส่งผลลัพธ์กลับไปยังหน้าจอ Scanner
        res.json({
            status: "success",
            message: "สแกนสำเร็จ!",
            company_name: user.company_name,
            contact_name: user.contact_name,
            scan_time: formattedDate
        });

        console.log(`📌 Scanned Successfully: ${registration_id} at ${formattedDate}`);

    } catch (error) {
        console.error("Database Error:", error);
        res.status(500).json({ status: "error", message: "ระบบฐานข้อมูลขัดข้อง" });
    }
});

/**
 * รัน Server ที่พอร์ต 3001
 * (อย่าลืมตรวจสอบ Docker Compose ว่า Map พอร์ตตรงกัน)
 */
const PORT = 3001;
app.listen(PORT, () => {
    console.log(`🚀 Scanner App running on port ${PORT}`);
    console.log(`🕒 Server Timezone: ${Intl.DateTimeFormat().resolvedOptions().timeZone}`);
});

