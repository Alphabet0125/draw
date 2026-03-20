// Drawing Editor Variables
let canvas, ctx, tempCanvas, tempCtx;
let isDrawing = false;
let currentTool = 'brush';
let currentColor = '#ff6b35';
let brushSize = 5;
let opacity = 1;
let history = [];
let historyStep = -1;
let startX, startY;
let currentFileId = null;
let currentFileName = '';
let originalImage = null;
let imageOffsetX = 0;
let imageOffsetY = 0;

// ตัวแปรสำหรับ Padding (เปลี่ยนเป็น 50px)
let paddingSize = 50;  // เปลี่ยนจาก 100 เป็น 50px
let originalImageWidth = 0;
let originalImageHeight = 0;

// เปิด Drawing Editor
function openDrawingEditor(fileId, fileName) {
    currentFileId = fileId;
    currentFileName = fileName;
    paddingSize = 50;
    
    const modal = document.getElementById('drawingModal');
    document.getElementById('currentFileName').textContent = fileName;
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
    
    // แสดงปุ่มเพิ่มพื้นที่สำหรับรูปภาพ (เพิ่มใหม่)
    const paddingSection = document.getElementById('paddingControlSection');
    if (paddingSection) paddingSection.style.display = 'block';
    
    setTimeout(() => {
        initializeCanvas(fileId);
    }, 100);
}

// ปิด Drawing Editor
function closeDrawingModal() {
    if (history.length > 1) {
        if (!confirm('คุณต้องการปิดหน้าต่างนี้หรือไม่? การเปลี่ยนแปลงที่ยังไม่ได้บันทึกจะหายไป')) {
            return;
        }
    }
    
    const modal = document.getElementById('drawingModal');
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';
    
    // ลบปุ่มเปลี่ยนหน้า PDF (ถ้ามี)
    const pageNav = document.getElementById('pdfPageNav');
    if (pageNav) {
        pageNav.remove();
    }
    
    // รีเซ็ตซูม
    if (typeof resetZoom === 'function') {
        resetZoom();
    }
    
    history = [];
    historyStep = -1;
    originalImage = null;
    imageOffsetX = 0;
    imageOffsetY = 0;
    originalImageWidth = 0;
    originalImageHeight = 0;
    currentFileId = null;
    currentFileName = '';
    paddingSize = 50;  // รีเซ็ตเป็น 50px
}

// Initialize Canvas
function initializeCanvas(fileId) {
    canvas = document.getElementById('drawingCanvas');
    ctx = canvas.getContext('2d');
    
    tempCanvas = document.createElement('canvas');
    tempCtx = tempCanvas.getContext('2d');
    
    const img = new Image();
    img.crossOrigin = 'anonymous';
    img.src = `preview.php?id=${fileId}&t=${new Date().getTime()}`;
    
    img.onload = function() {
        originalImage = img;
        
        // เก็บขนาดต้นฉบับ
        originalImageWidth = img.width;
        originalImageHeight = img.height;
        
        // ใช้ตัวแปร paddingSize (50px)
        const PADDING = 0;
        
        // คำนวณ offset เพื่อวาดรูปตรงกลาง
        imageOffsetX = PADDING;
        imageOffsetY = PADDING;
        
        // ตั้งค่าขนาด canvas = ขนาดจริง + padding
        const canvasWidth = originalImageWidth + (PADDING * 2);
        const canvasHeight = originalImageHeight + (PADDING * 2);
        
        canvas.width = canvasWidth;
        canvas.height = canvasHeight;
        tempCanvas.width = canvasWidth;
        tempCanvas.height = canvasHeight;
        
        // ล้างพื้นหลัง
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        
        // วาดกรอบรูปภาพแบบเส้นประ
        ctx.strokeStyle = '#cccccc';
        ctx.lineWidth = 2;
        ctx.setLineDash([10, 5]);
        ctx.strokeRect(imageOffsetX, imageOffsetY, originalImageWidth, originalImageHeight);
        ctx.setLineDash([]);
        
        // วาดรูปภาพต้นฉบับขนาดจริง 100%
        ctx.drawImage(img, imageOffsetX, imageOffsetY, originalImageWidth, originalImageHeight);
        
        updateCanvasInfo(originalImageWidth, originalImageHeight, canvasWidth, canvasHeight);
        
        initializeEventListeners();
        initializeToolbar();
        
        console.log(`✅ Original Size: ${originalImageWidth}x${originalImageHeight}px | Canvas: ${canvasWidth}x${canvasHeight}px | Padding: ${PADDING}px`);
    };
    
    img.onerror = function() {
        alert('ไม่สามารถโหลดรูปภาพได้');
        closeDrawingModal();
    };
}

// ฟังก์ชันเพิ่มพื้นที่รอบๆ
function increasePadding() {
    if (!originalImage) {
        alert('❌ ไม่พบรูปภาพต้นฉบับ');
        return;
    }
    
    // เพิ่ม padding 100px
    paddingSize += 0;
    
    console.log(`📏 Increasing padding to ${paddingSize}px...`);
    
    // วาดรูปใหม่ตาม padding ใหม่
    redrawWithPadding();
    
    // อัปเดตข้อมูล
    const canvasWidth = originalImageWidth + (paddingSize * 2);
    const canvasHeight = originalImageHeight + (paddingSize * 2);
    updateCanvasInfo(originalImageWidth, originalImageHeight, canvasWidth, canvasHeight);
    
    // อัปเดตปุ่���
    updatePaddingButton();
    
    console.log(`✅ Padding increased to ${paddingSize}px | Canvas: ${canvasWidth}x${canvasHeight}px`);
}

// ฟังก์ชันวาดรูปใหม่ตาม padding ใหม่ (แก้ไขให้รูปอยู่ตรงกลางเสมอ)
function redrawWithPadding() {
    if (!originalImage) return;
    
    // คำนวณ padding เก่า
    const oldPadding = paddingSize - 100;
    
    // บันทึก canvas ปัจจุบัน
    const oldCanvas = document.createElement('canvas');
    const oldCtx = oldCanvas.getContext('2d');
    oldCanvas.width = canvas.width;
    oldCanvas.height = canvas.height;
    oldCtx.drawImage(canvas, 0, 0);
    
    // คำนวณขนาดและตำแหน่งใหม่
    imageOffsetX = paddingSize;  // ตำแหน่งใหม่ = padding ใหม่
    imageOffsetY = paddingSize;
    
    const newCanvasWidth = originalImageWidth + (paddingSize * 2);
    const newCanvasHeight = originalImageHeight + (paddingSize * 2);
    
    // ปรับขนาด canvas ใหม่
    canvas.width = newCanvasWidth;
    canvas.height = newCanvasHeight;
    tempCanvas.width = newCanvasWidth;
    tempCanvas.height = newCanvasHeight;
    
    // ล้าง canvas
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    
    // คำนวณค่าเลื่อน offset (รูปจะอยู่���รงกลางเสมอ)
    const offsetDiff = paddingSize - oldPadding;
    
    // วาด canvas เดิมที่ตำแหน่งใหม่ (เลื่อนตาม offset)
    ctx.drawImage(oldCanvas, offsetDiff, offsetDiff);
    
    // บันทึก state ใหม่
    saveState();
    
    console.log(`🎯 Canvas repositioned: offset moved by ${offsetDiff}px, image centered at (${imageOffsetX}, ${imageOffsetY})`);
}

// อัปเดตปุ่มเพิ่มพื้นที่
function updatePaddingButton() {
    const paddingSizeDisplay = document.getElementById('paddingSizeDisplay');
    if (paddingSizeDisplay) {
        paddingSizeDisplay.textContent = `${paddingSize}px`;
    }
}

// อัปเดตข้อมูลขนาดรูป
function updateCanvasInfo(imageW, imageH, canvasW, canvasH) {
    const canvasInfo = document.getElementById('canvasInfo');
    if (canvasInfo) {
        canvasInfo.innerHTML = `
            <i class="fas fa-image"></i>
            <strong>ขนาดรูปภาพ:</strong><br>
            ${imageW} × ${imageH}px<br>
            <strong style="color: var(--primary-color);">100% ขนาดจริง</strong><br>
            <strong>Canvas รวม:</strong><br>
            ${canvasW} × ${canvasH}px<br>
            <strong>พื้นที่รอบๆ:</strong><br>
            <span style="color: var(--primary-color); font-size: 1.2rem;">${paddingSize}px</span><br>
            <span style="font-size: 0.75rem; color: var(--text-secondary);">
                <i class="fas fa-info-circle"></i> พื้นที่วาด ${paddingSize}px รอบๆ
            </span>
        `;
    }
}

// Initialize Event Listeners
function initializeEventListeners() {
    canvas.addEventListener('mousedown', startDrawing);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', stopDrawing);
    canvas.addEventListener('mouseout', stopDrawing);
    
    canvas.addEventListener('touchstart', handleTouch);
    canvas.addEventListener('touchmove', handleTouch);
    canvas.addEventListener('touchend', handleTouchEnd);
}

// Initialize Toolbar
function initializeToolbar() {
    document.querySelectorAll('.tool-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.tool-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentTool = this.dataset.tool;
            updateCursor();
        });
    });
    
    const colorPicker = document.getElementById('colorPicker');
    colorPicker.addEventListener('input', function() {
        currentColor = this.value;
        updateColorPresets();
        updateSizePreview();
    });
    
    document.querySelectorAll('.color-preset').forEach(btn => {
        btn.addEventListener('click', function() {
            currentColor = this.dataset.color;
            colorPicker.value = currentColor;
            document.querySelectorAll('.color-preset').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            updateSizePreview();
        });
    });
    
    const brushSizeSlider = document.getElementById('brushSize');
    const brushSizeValue = document.getElementById('brushSizeValue');
    
    brushSizeSlider.addEventListener('input', function() {
        brushSize = parseInt(this.value);
        brushSizeValue.textContent = brushSize + 'px';
        updateSizePreview();
    });
    
    const opacitySlider = document.getElementById('opacitySlider');
    const opacityValue = document.getElementById('opacityValue');
    
    opacitySlider.addEventListener('input', function() {
        opacity = parseInt(this.value) / 100;
        opacityValue.textContent = this.value + '%';
        updateSizePreview();
    });
    
    document.getElementById('undoBtn').addEventListener('click', undo);
    document.getElementById('redoBtn').addEventListener('click', redo);
    document.getElementById('clearBtn').addEventListener('click', clearCanvas);
    document.getElementById('saveDrawingBtn').addEventListener('click', saveDrawing);
    
    // เพิ่ม event listener สำหรับปุ่มเพิ่มพื้นที่
    const increasePaddingBtn = document.getElementById('increasePaddingBtn');
    if (increasePaddingBtn) {
        increasePaddingBtn.addEventListener('click', increasePadding);
    }
    
    updateSizePreview();
    updateCursor();
    updatePaddingButton();
}

function updateColorPresets() {
    document.querySelectorAll('.color-preset').forEach(btn => {
        btn.classList.remove('active');
        if (btn.dataset.color === currentColor) {
            btn.classList.add('active');
        }
    });
}

function updateSizePreview() {
    const sizePreviewDot = document.getElementById('sizePreviewDot');
    sizePreviewDot.style.width = brushSize + 'px';
    sizePreviewDot.style.height = brushSize + 'px';
    sizePreviewDot.style.background = currentColor;
    sizePreviewDot.style.opacity = opacity;
}

function updateCursor() {
    if (currentTool === 'eraser') {
        canvas.style.cursor = 'url("data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'24\' height=\'24\'><circle cx=\'12\' cy=\'12\' r=\'10\' fill=\'white\' stroke=\'black\'/></svg>") 12 12, auto';
    } else if (['line', 'rectangle', 'circle', 'arrow'].includes(currentTool)) {
        canvas.style.cursor = 'crosshair';
    } else if (currentTool === 'text') {
        canvas.style.cursor = 'text';
    } else {
        canvas.style.cursor = 'crosshair';
    }
}

// Drawing Functions (ไม่แก้ไข - ใช้ต่อจากเดิม)
function startDrawing(e) {
    isDrawing = true;
    const rect = canvas.getBoundingClientRect();
    const scaleX = canvas.width / rect.width;
    const scaleY = canvas.height / rect.height;
    
    startX = (e.clientX - rect.left) * scaleX;
    startY = (e.clientY - rect.top) * scaleY;
    
    if (currentTool === 'text') {
        addText(startX, startY);
        isDrawing = false;
    } else if (['line', 'rectangle', 'circle', 'arrow'].includes(currentTool)) {
        tempCtx.clearRect(0, 0, tempCanvas.width, tempCanvas.height);
    } else {
        ctx.beginPath();
        ctx.moveTo(startX, startY);
    }
}

function draw(e) {
    if (!isDrawing) return;
    
    const rect = canvas.getBoundingClientRect();
    const scaleX = canvas.width / rect.width;
    const scaleY = canvas.height / rect.height;
    
    const x = (e.clientX - rect.left) * scaleX;
    const y = (e.clientY - rect.top) * scaleY;
    
    if (['line', 'rectangle', 'circle', 'arrow'].includes(currentTool)) {
        tempCtx.clearRect(0, 0, tempCanvas.width, tempCanvas.height);
        
        tempCtx.globalAlpha = opacity;
        tempCtx.strokeStyle = currentColor;
        tempCtx.fillStyle = currentColor;
        tempCtx.lineWidth = brushSize;
        tempCtx.lineCap = 'round';
        tempCtx.lineJoin = 'round';
        
        if (currentTool === 'line') {
            drawLine(tempCtx, startX, startY, x, y);
        } else if (currentTool === 'rectangle') {
            drawRectangle(tempCtx, startX, startY, x, y);
        } else if (currentTool === 'circle') {
            drawCircle(tempCtx, startX, startY, x, y);
        } else if (currentTool === 'arrow') {
            drawArrow(tempCtx, startX, startY, x, y);
        }
        
        redrawCanvas();
    } else {
        ctx.globalAlpha = opacity;
        ctx.strokeStyle = currentColor;
        ctx.fillStyle = currentColor;
        ctx.lineWidth = brushSize;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        
        if (currentTool === 'brush') {
            ctx.lineTo(x, y);
            ctx.stroke();
        } else if (currentTool === 'pencil') {
            ctx.lineWidth = Math.max(1, brushSize / 2);
            ctx.lineTo(x, y);
            ctx.stroke();
        } else if (currentTool === 'marker') {
            // แก้ไข: ป้องกันการวาดซ้ำ
            ctx.globalAlpha = opacity * 0.3;
            ctx.lineWidth = brushSize * 2;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            
            // วาดแบบวงกลมต่อเนื่องแทนการใช้ lineTo
            ctx.beginPath();
            ctx.arc(x, y, brushSize, 0, Math.PI * 2);
            ctx.fill();
            
            // รีเ��็ต globalAlpha
            ctx.globalAlpha = opacity;
        } else if (currentTool === 'eraser') {
            ctx.globalCompositeOperation = 'destination-out';
            ctx.arc(x, y, brushSize, 0, Math.PI * 2);
            ctx.fill();
            ctx.beginPath();
            ctx.moveTo(x, y);
            ctx.globalCompositeOperation = 'source-over';
        }
    }
}

function stopDrawing(e) {
    if (!isDrawing) return;
    
    if (['line', 'rectangle', 'circle', 'arrow'].includes(currentTool)) {
        const rect = canvas.getBoundingClientRect();
        const scaleX = canvas.width / rect.width;
        const scaleY = canvas.height / rect.height;
        
        const x = (e.clientX - rect.left) * scaleX;
        const y = (e.clientY - rect.top) * scaleY;
        
        ctx.globalAlpha = opacity;
        ctx.strokeStyle = currentColor;
        ctx.fillStyle = currentColor;
        ctx.lineWidth = brushSize;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        
        if (currentTool === 'line') {
            drawLine(ctx, startX, startY, x, y);
        } else if (currentTool === 'rectangle') {
            drawRectangle(ctx, startX, startY, x, y);
        } else if (currentTool === 'circle') {
            drawCircle(ctx, startX, startY, x, y);
        } else if (currentTool === 'arrow') {
            drawArrow(ctx, startX, startY, x, y);
        }
        
        tempCtx.clearRect(0, 0, tempCanvas.width, tempCanvas.height);
    }
    
    isDrawing = false;
    ctx.globalCompositeOperation = 'source-over';
    ctx.globalAlpha = 1;
    saveState();
}

function redrawCanvas() {
    if (history.length > 0) {
        const img = new Image();
        img.src = history[historyStep];
        img.onload = function() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.drawImage(img, 0, 0);
            ctx.drawImage(tempCanvas, 0, 0);
        };
    } else {
        ctx.drawImage(tempCanvas, 0, 0);
    }
}

function drawLine(context, x1, y1, x2, y2) {
    context.beginPath();
    context.moveTo(x1, y1);
    context.lineTo(x2, y2);
    context.stroke();
}

function drawRectangle(context, x1, y1, x2, y2) {
    context.strokeRect(x1, y1, x2 - x1, y2 - y1);
}

function drawCircle(context, x1, y1, x2, y2) {
    const radius = Math.sqrt(Math.pow(x2 - x1, 2) + Math.pow(y2 - y1, 2));
    context.beginPath();
    context.arc(x1, y1, radius, 0, Math.PI * 2);
    context.stroke();
}

function drawArrow(context, x1, y1, x2, y2) {
    const headlen = 20 * (brushSize / 5);
    const angle = Math.atan2(y2 - y1, x2 - x1);
    
    context.beginPath();
    context.moveTo(x1, y1);
    context.lineTo(x2, y2);
    context.stroke();
    
    context.beginPath();
    context.moveTo(x2, y2);
    context.lineTo(x2 - headlen * Math.cos(angle - Math.PI / 6), y2 - headlen * Math.sin(angle - Math.PI / 6));
    context.lineTo(x2 - headlen * Math.cos(angle + Math.PI / 6), y2 - headlen * Math.sin(angle + Math.PI / 6));
    context.closePath();
    context.fill();
}

function addText(x, y) {
    const text = prompt('กรอกข้อความ:');
    if (text) {
        ctx.globalAlpha = opacity;
        ctx.fillStyle = currentColor;
        ctx.font = `${brushSize * 4}px Arial`;
        ctx.fillText(text, x, y);
        saveState();
    }
}

function handleTouch(e) {
    e.preventDefault();
    const touch = e.touches[0];
    const mouseEvent = new MouseEvent(e.type === 'touchstart' ? 'mousedown' : 'mousemove', {
        clientX: touch.clientX,
        clientY: touch.clientY
    });
    canvas.dispatchEvent(mouseEvent);
}

function handleTouchEnd(e) {
    e.preventDefault();
    const mouseEvent = new MouseEvent('mouseup', {
        clientX: 0,
        clientY: 0
    });
    canvas.dispatchEvent(mouseEvent);
}

function saveState() {
    historyStep++;
    if (historyStep < history.length) {
        history.length = historyStep;
    }
    history.push(canvas.toDataURL('image/png'));
    
    if (history.length > 20) {
        history.shift();
        historyStep--;
    }
    
    updateHistoryButtons();
}

function undo() {
    if (historyStep > 0) {
        historyStep--;
        restoreState();
        updateHistoryButtons();
    }
}

function redo() {
    if (historyStep < history.length - 1) {
        historyStep++;
        restoreState();
        updateHistoryButtons();
    }
}

function restoreState() {
    const img = new Image();
    img.src = history[historyStep];
    img.onload = function() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(img, 0, 0);
    };
}

function updateHistoryButtons() {
    const undoBtn = document.getElementById('undoBtn');
    const redoBtn = document.getElementById('redoBtn');
    
    undoBtn.disabled = historyStep <= 0;
    redoBtn.disabled = historyStep >= history.length - 1;
    
    undoBtn.style.opacity = historyStep <= 0 ? '0.5' : '1';
    redoBtn.style.opacity = historyStep >= history.length - 1 ? '0.5' : '1';
}

function clearCanvas() {
    if (confirm('คุณต้องการล้างภาพวาดทั้งหมดหรือไม่?')) {
        if (originalImage) {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            
            // วาดกรอบรูปภาพอีกครั้ง
            ctx.strokeStyle = '#cccccc';
            ctx.lineWidth = 2;
            ctx.setLineDash([10, 5]);
            ctx.strokeRect(imageOffsetX, imageOffsetY, originalImageWidth, originalImageHeight);
            ctx.setLineDash([]);
            
            // วาดรูปต้นฉบับขนาดจริง
            ctx.drawImage(originalImage, imageOffsetX, imageOffsetY, originalImageWidth, originalImageHeight);
            saveState();
        }
    }
}

function saveDrawing() {
    const saveBtn = document.getElementById('saveDrawingBtn');
    const originalText = saveBtn.innerHTML;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังบันทึก...';
    saveBtn.disabled = true;
    
    // บันทึก canvas ทั้งหมด (ขนาดจริง + พื้นที่วาดเพิ่ม)
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
            alert('✅ บันทึกรูปภาพสำเร็จ!\n\n📁 ไฟล์: ' + currentFileName + '\n📊 ขนาด: ' + data.fileSize + '\n\n💡 รูปที่บันทึกเป็นขนาดจริง 100% รวมส่วนที่วาดเพิ่ม');
            history = [];
            historyStep = -1;
            closeDrawingModal();
            location.reload();
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
}

document.addEventListener('keydown', function(e) {
    // ตรวจสอบว่า modal เปิดอยู่หรือไม่
    if (document.getElementById('drawingModal').style.display === 'block') {
        // ป้องกันไม่ให้ทำงานถ้ากำลังพิมพ์ใน input/textarea
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
            return;
        }
        
        // Ctrl+Z = Undo (แก้ไข: รองรับทั้ง lowercase และ uppercase)
        if ((e.ctrlKey || e.metaKey) && (e.key === 'z' || e.key === 'Z')) {
            e.preventDefault();
            undo();
            console.log('⏮ Undo');
        }
        
        // Ctrl+Y = Redo (แก้ไข: รองรับทั้ง lowercase และ uppercase)
        if ((e.ctrlKey || e.metaKey) && (e.key === 'y' || e.key === 'Y')) {
            e.preventDefault();
            redo();
            console.log('⏭ Redo');
        }
        
        // Ctrl+Shift+Z = Redo (ทางเลือก)
        if ((e.ctrlKey || e.metaKey) && e.shiftKey && (e.key === 'z' || e.key === 'Z')) {
            e.preventDefault();
            redo();
            console.log('⏭ Redo (Ctrl+Shift+Z)');
        }
        
        // Ctrl+S = Save
        if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S')) {
            e.preventDefault();
            saveDrawing();
            console.log('💾 Save');
        }
        
        // ESC = Close
        if (e.key === 'Escape') {
            e.preventDefault();
            closeDrawingModal();
            console.log('❌ Close');
        }
    }
});