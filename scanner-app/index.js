const express = require('express');
const mysql = require('mysql2/promise');
const path = require('path');
const app = express();

app.use(express.static('public'));
app.use(express.json());

let db;

// ฟังก์ชันสำหรับเชื่อมต่อฐานข้อมูล
async function initDatabase() {
    try {
        db = await mysql.createConnection({
            host: 'db',
            user: 'root',
            password: 'rootpassword',
            database: 'event_db',
            charset: 'utf8mb4'
        
        });
        console.log('Connected to MySQL Database');
    } catch (error) {
        console.error('Failed to connect to MySQL:', error);
        process.exit(1); // ปิดแอปถ้าเชื่อมต่อฐานข้อมูลไม่ได้
    }
}

// เรียกใช้ฟังก์ชันเชื่อมต่อก่อนรัน Server
initDatabase();

// API สำหรับยืนยันการสแกน
app.post('/scan', async (req, res) => {
    const { registration_id } = req.body;

    // ตรวจสอบว่า db พร้อมใช้งานหรือไม่
    if (!db) {
        return res.status(500).json({ status: "error", message: "Database not connected" });
    }

    try {
        const result = await db.execute(
            'SELECT * FROM registrations WHERE registration_id = ?', 
            [registration_id]
        );

        if (!Array.isArray(result) || result[0].length === 0) {
            return res.json({ status: "not_found", message: "ไม่พบ ID ในระบบ" });
        }

        const rows = result[0];
        const user = rows[0];

        // 1. ตรวจสอบการสแกนซ้ำ
        if (user.attendance !== null) {
            return res.json({ 
                status: "already_scanned",
                message: "สแกนเข้างานแล้ว",
                company_name: user.company_name,
                attendance_time: user.attendance 
            });
        }

        // 2. บันทึกเวลาสแกนสำเร็จ
        const now = new Date();
        const formattedDate = now.toISOString().slice(0, 19).replace('T', ' ');

        await db.execute(
            'UPDATE registrations SET attendance = ? WHERE registration_id = ?',
            [formattedDate, registration_id]
        );

        res.json({
            status: "success",
            message: "สแกนสำเร็จ!",
            company_name: user.company_name,
            contact_name: user.contact_name,
            scan_time: formattedDate
        });

    } catch (error) {
        console.error("Database Error:", error);
        res.status(500).json({ status: "error", message: "ระบบฐานข้อมูลขัดข้อง" });
    }
});

// เปลี่ยน port เป็น 3000 หรือ 3001 ตามที่ตั้งค่าใน Docker-compose
app.listen(3001, () => console.log('Scanner App running on port 3001'));



