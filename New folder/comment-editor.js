// Initialize Image Editor
function initImageEditor(fileId, fileName) {
    console.log('🖼️ Initializing Image Editor...');
    console.log('File ID:', fileId);
    console.log('File Name:', fileName);
    
    // โหลดรูปภาพ
    initializeCanvas(fileId);
}

// Initialize PDF Editor
function initPDFEditor(fileId, fileName) {
    console.log('📄 Initializing PDF Editor...');
    console.log('File ID:', fileId);
    console.log('File Name:', fileName);
    
    // โหลด PDF
    loadPDFDocument(fileId);
}

// Override saveDrawing เพื่อ redirect กลับหลังบันทึก
const originalSaveDrawing = saveDrawing;
saveDrawing = function() {
    const saveBtn = document.getElementById('saveDrawingBtn');
    const originalText = saveBtn.innerHTML;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังบันทึก...';
    saveBtn.disabled = true;
    
    const imageData = canvas.toDataURL('image/png');
    
    fetch('save_edited.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            fileId: currentFileId,
            imageData: imageData,
            fileName: currentFileName
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ บันทึกรูปภาพสำเร็จ!\n\n📁 ไฟล์: ' + currentFileName + '\n📊 ขนาด: ' + data.fileSize);
            // Redirect กลับไปหน้า files.php
            window.location.href = 'files.php';
        } else {
            alert('❌ เกิดข้อผิดพลาด: ' + data.message);
        }
    })
    .catch(error => {
        alert('❌ เกิดข้อผิดพลาดในการบันทึก: ' + error);
    })
    .finally(() => {
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
    });
};