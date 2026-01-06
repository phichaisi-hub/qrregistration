// ตัวอย่าง Hook เมื่อมีการส่งฟอร์ม (อ้างอิง WPForms)
add_action( 'wpforms_process_complete', 'save_to_custom_table', 10, 4 );
function save_to_custom_table( $fields, $entry, $form_data, $entry_id ) {
    global $wpdb;
    
    $reg_id = 'REG-' . time(); // สร้าง ID สุ่ม
    
    $wpdb->insert('registrations', array(
        'registration_id' => $reg_id,
        'timestamp' => current_time('mysql'),
        'company_name' => $fields[1]['value'], // เปลี่ยนเลข ID ตามฟิลด์ในฟอร์ม
        'contact_name' => $fields[2]['value'],
        'email' => $fields[3]['value'],
        'attendance' => null // เว้นว่างไว้รอสแกน
    ));

    // ส่ง Email พร้อม QR Code (ใช้ API เช่น QuickChart หรือ Plugin)
    $qr_url = "https://quickchart.io/qr?text=" . $reg_id;
    wp_mail($fields[3]['value'], "QR Code เข้างานของคุณ", "รหัสของคุณคือ: $reg_id <br> <img src='$qr_url'>");
}