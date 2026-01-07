const express = require('express');
const mysql = require('mysql2/promise');
const path = require('path');
const app = express();

const db = await mysql.createConnection({
    host: 'db',
    user: 'root',
    password: 'rootpassword',
    database: 'event_db'
});

app.use(express.static('public'));
app.use(express.json());

// API สำหรับยืนยันการสแกน
app.post('/scan', async (req, res) => {
    const { registration_id } = req.body;

    try {
        // ตรวจสอบว่า db พร้อมใช้งาน และใส่ await ให้เรียบร้อย
        const result = await db.execute(
            'SELECT * FROM registrations WHERE registration_id = ?', 
            [registration_id]
        );

        // ตรวจสอบว่า result เป็น Array หรือไม่ก่อนจะดึง rows ออกมา
        if (!Array.isArray(result)) {
            console.error("Database did not return an array:", result);
            return res.status(500).json({ message: "Internal Server Error" });
        }

        const rows = result[0]; // rows คือข้อมูลที่ได้จากฐานข้อมูล

        if (rows.length === 0) {
            return res.json({ status: "not_found", message: "ไม่พบ ID ในระบบ" });
        }

        const user = rows[0];

        // 2. ตรวจสอบว่าเคยสแกนไปหรือยัง (attendance ไม่เป็น NULL)
        // กรณีที่ 2: สแกนซ้ำ
        if (user.attendance !== null) {
            return res.json({ 
                status: "already_scanned",
                message: "สแกนเข้างานแล้ว",
                company_name: user.company_name,
                attendance_time: user.attendance 
            });
        }

        // 3. หากมีตัวตนและยังไม่เคยสแกน ให้บันทึกเวลาสแกนสำเร็จ
        // กรณีที่ 3: สแกนสำเร็จครั้งแรก
        const now = new Date();
        // ปรับเวลาให้เป็นรูปแบบ MySQL (YYYY-MM-DD HH:mm:ss)
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

app.listen(3001, () => console.log('Scanner App running on port 3001'));



