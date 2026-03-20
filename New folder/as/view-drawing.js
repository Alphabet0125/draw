// Drawing Editor Variables
let canvas, ctx;
let isDrawing = false;
let currentTool = 'brush';
let currentColor = '#ff6b35';
let brushSize = 5;
let opacity = 1;
let history = [];
let historyStep = -1;
let startX, startY;
let savedImageData = null;

// รับข้อมูลไฟล์
const fileId = document.getElementById('fileId').value;
const fileName = document.getElementById('fileName').value;

// Initialize เมื่อโหลดหน้า
document.addEventListener('DOMContentLoaded', function() {
    initializeCanvas();
});

// Initialize Canvas
function initializeCanvas() {
    canvas = document.getElementById('drawingCanvas');
    ctx = canvas.getContext('2d');
    
    const canvasWrapper = document.getElementById('canvasWrapper');
    
    // โหลดรูปภาพ
    const img = new Image();
    img.crossOrigin = 'anonymous';
    img.src = `preview.php?id=${fileId}&t=${new Date().getTime()}`;
    
    img.onload = function() {
        // ใช้ขนาดจริงของรูปภาพ
        const originalWidth = img.width;
        const originalHeight = img.height;
        
        canvas.width = originalWidth;
        canvas.height = originalHeight;
        
        // วาดรูปภาพต้นฉบับ
        ctx.drawImage(img, 0, 0, originalWidth, originalHeight);
        
        // บันทึก state แรก
        saveState();
        
        // เริ่มต้น event listeners และ toolbar
        initializeEventListeners();
        initializeToolbar();
        
        // แสดงข้อมูลขนาดรูปภาพ
        updateCanvasInfo(originalWidth, originalHeight);
    };
    
    img.onerror = function() {
        alert('ไม่สามารถโหลดรูปภาพได้');
        window.location.href = 'files.php';
    };
}

// อัปเดตข้อมูลขนาดรูปภาพ
function updateCanvasInfo(width, height) {
    const infoElement = document.getElementById('canvasInfo');
    const sizeInfoElement = document.getElementById('canvasSizeInfo');
    
    if (infoElement) {
        infoElement.innerHTML = `
            <i class="fas fa-image"></i> 
            ขนาดรูปภาพ: ${width} × ${height} px
        `;
    }
    
    if (sizeInfoElement) {
        sizeInfoElement.textContent = `Canvas: ${width} × ${height} px`;
    }
}

// Initialize Event Listeners
function initializeEventListeners() {
    canvas.addEventListener('mousedown', startDrawing);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', stopDrawing);
    canvas.addEventListener('mouseout', stopDrawing);
    
    // Touch events
    canvas.addEventListener('touchstart', handleTouch);
    canvas.addEventListener('touchmove', handleTouch);
    canvas.addEventListener('touchend', stopDrawing);
}

// Initialize Toolbar
function initializeToolbar() {
    // Tool buttons
    document.querySelectorAll('.tool-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.tool-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentTool = this.dataset.tool;
        });
    });
    
    // Color picker
    const colorPicker = document.getElementById('colorPicker');
    colorPicker.addEventListener('input', function() {
        currentColor = this.value;
        updateColorPresets();
    });
    
    // Preset colors
    document.querySelectorAll('.color-preset').forEach(btn => {
        btn.addEventListener('click', function() {
            currentColor = this.dataset.color;
            colorPicker.value = currentColor;
            document.querySelectorAll('.color-preset').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
        });
    });
    
    // Brush size
    const brushSizeSlider = document.getElementById('brushSize');
    const brushSizeValue = document.getElementById('brushSizeValue');
    const sizePreviewDot = document.getElementById('sizePreviewDot');
    
    brushSizeSlider.addEventListener('input', function() {
        brushSize = parseInt(this.value);
        brushSizeValue.textContent = brushSize + 'px';
        sizePreviewDot.style.width = brushSize + 'px';
        sizePreviewDot.style.height = brushSize + 'px';
    });
    
    // Opacity
    const opacitySlider = document.getElementById('opacitySlider');
    const opacityValue = document.getElementById('opacityValue');
    
    opacitySlider.addEventListener('input', function() {
        opacity = parseInt(this.value) / 100;
        opacityValue.textContent = this.value + '%';
    });
    
    // Action buttons
    document.getElementById('undoBtn').addEventListener('click', undo);
    document.getElementById('redoBtn').addEventListener('click', redo);
    document.getElementById('clearBtn').addEventListener('click', clearCanvas);
    document.getElementById('saveDrawingBtn').addEventListener('click', saveDrawing);
}

function updateColorPresets() {
    document.querySelectorAll('.color-preset').forEach(btn => {
        btn.classList.remove('active');
        if (btn.dataset.color === currentColor) {
            btn.classList.add('active');
        }
    });
}

// Drawing Functions
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
        savedImageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
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
        ctx.globalAlpha = 0.3;
        ctx.lineWidth = brushSize * 2;
        ctx.lineTo(x, y);
        ctx.stroke();
    } else if (currentTool === 'eraser') {
        ctx.globalCompositeOperation = 'destination-out';
        ctx.arc(x, y, brushSize, 0, Math.PI * 2);
        ctx.fill();
        ctx.beginPath();
        ctx.arc(x, y, brushSize, 0, Math.PI * 2);
        ctx.fill();
        ctx.globalCompositeOperation = 'source-over';
    } else if (['line', 'rectangle', 'circle', 'arrow'].includes(currentTool)) {
        ctx.putImageData(savedImageData, 0, 0);
        
        if (currentTool === 'line') {
            drawLine(startX, startY, x, y);
        } else if (currentTool === 'rectangle') {
            drawRectangle(startX, startY, x, y);
        } else if (currentTool === 'circle') {
            drawCircle(startX, startY, x, y);
        } else if (currentTool === 'arrow') {
            drawArrow(startX, startY, x, y);
        }
    }
}

function stopDrawing(e) {
    if (!isDrawing) return;
    
    if (['line', 'rectangle', 'circle', 'arrow'].includes(currentTool) && e && e.clientX) {
        const rect = canvas.getBoundingClientRect();
        const scaleX = canvas.width / rect.width;
        const scaleY = canvas.height / rect.height;
        const x = (e.clientX - rect.left) * scaleX;
        const y = (e.clientY - rect.top) * scaleY;
        
        ctx.putImageData(savedImageData, 0, 0);
        
        if (currentTool === 'line') {
            drawLine(startX, startY, x, y);
        } else if (currentTool === 'rectangle') {
            drawRectangle(startX, startY, x, y);
        } else if (currentTool === 'circle') {
            drawCircle(startX, startY, x, y);
        } else if (currentTool === 'arrow') {
            drawArrow(startX, startY, x, y);
        }
        
        savedImageData = null;
    }
    
    isDrawing = false;
    ctx.globalCompositeOperation = 'source-over';
    ctx.globalAlpha = 1;
    saveState();
}

// Shape Drawing Functions
function drawLine(x1, y1, x2, y2) {
    ctx.globalAlpha = opacity;
    ctx.strokeStyle = currentColor;
    ctx.lineWidth = brushSize;
    ctx.lineCap = 'round';
    ctx.beginPath();
    ctx.moveTo(x1, y1);
    ctx.lineTo(x2, y2);
    ctx.stroke();
}

function drawRectangle(x1, y1, x2, y2) {
    ctx.globalAlpha = opacity;
    ctx.strokeStyle = currentColor;
    ctx.lineWidth = brushSize;
    ctx.lineJoin = 'round';
    ctx.strokeRect(x1, y1, x2 - x1, y2 - y1);
}

function drawCircle(x1, y1, x2, y2) {
    ctx.globalAlpha = opacity;
    ctx.strokeStyle = currentColor;
    ctx.lineWidth = brushSize;
    const radius = Math.sqrt(Math.pow(x2 - x1, 2) + Math.pow(y2 - y1, 2));
    ctx.beginPath();
    ctx.arc(x1, y1, radius, 0, Math.PI * 2);
    ctx.stroke();
}

function drawArrow(x1, y1, x2, y2) {
    const headlen = 20 + (brushSize * 2);
    const angle = Math.atan2(y2 - y1, x2 - x1);
    
    ctx.globalAlpha = opacity;
    ctx.strokeStyle = currentColor;
    ctx.fillStyle = currentColor;
    ctx.lineWidth = brushSize;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    
    ctx.beginPath();
    ctx.moveTo(x1, y1);
    ctx.lineTo(x2, y2);
    ctx.stroke();
    
    ctx.beginPath();
    ctx.moveTo(x2, y2);
    ctx.lineTo(x2 - headlen * Math.cos(angle - Math.PI / 6), y2 - headlen * Math.sin(angle - Math.PI / 6));
    ctx.lineTo(x2 - headlen * Math.cos(angle + Math.PI / 6), y2 - headlen * Math.sin(angle + Math.PI / 6));
    ctx.closePath();
    ctx.fill();
}

// Text Function
function addText(x, y) {
    const text = prompt('กรอกข้อความ:');
    if (text) {
        ctx.globalAlpha = opacity;
        ctx.fillStyle = currentColor;
        ctx.font = `${brushSize * 4}px Arial`;
        ctx.textBaseline = 'top';
        ctx.fillText(text, x, y);
        saveState();
    }
}

// Touch Events
function handleTouch(e) {
    e.preventDefault();
    const touch = e.touches[0];
    const mouseEvent = new MouseEvent(
        e.type === 'touchstart' ? 'mousedown' : e.type === 'touchmove' ? 'mousemove' : 'mouseup',
        {
            clientX: touch.clientX,
            clientY: touch.clientY
        }
    );
    canvas.dispatchEvent(mouseEvent);
}

// History Functions
function saveState() {
    historyStep++;
    if (historyStep < history.length) {
        history.length = historyStep;
    }
    history.push(canvas.toDataURL());
    
    if (history.length > 50) {
        history.shift();
        historyStep--;
    }
}

function undo() {
    if (historyStep > 0) {
        historyStep--;
        restoreState();
    } else {
        alert('ไม่สามารถย้อนกลับได้อีก');
    }
}

function redo() {
    if (historyStep < history.length - 1) {
        historyStep++;
        restoreState();
    } else {
        alert('ไม่สามารถทำซ้ำได้อีก');
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

function clearCanvas() {
    if (confirm('คุณต้องการล้างภาพวาดทั้งหมดหรือไม่?')) {
        const img = new Image();
        img.src = `preview.php?id=${fileId}&t=${new Date().getTime()}`;
        img.onload = function() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
            
            history = [];
            historyStep = -1;
            saveState();
        };
    }
}

// Save Drawing
function saveDrawing() {
    const saveBtn = document.getElementById('saveDrawingBtn');
    const originalText = saveBtn.innerHTML;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังบันทึก...';
    saveBtn.disabled = true;
    
    const imageData = canvas.toDataURL('image/png', 1.0);
    
    fetch('save_edited.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            fileId: fileId,
            imageData: imageData,
            fileName: fileName
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ บันทึกรูปภาพสำเร็จ!\n\n📁 ไฟล์: ' + fileName + '\n📊 ขนาด: ' + data.fileSize);
            window.location.href = 'files.php';
        } else {
            alert('❌ เกิดข้อผิดพลาด: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ เกิดข้อผิดพลาดในการบันทึก: ' + error);
    })
    .finally(() => {
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
    });
}

// Keyboard Shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl+Z = Undo
    if (e.ctrlKey && e.key === 'z' && !e.shiftKey) {
        e.preventDefault();
        undo();
    }
    // Ctrl+Shift+Z or Ctrl+Y = Redo
    if ((e.ctrlKey && e.shiftKey && e.key === 'Z') || (e.ctrlKey && e.key === 'y')) {
        e.preventDefault();
        redo();
    }
    // Ctrl+S = Save
    if (e.ctrlKey && e.key === 's') {
        e.preventDefault();
        saveDrawing();
    }
    // Keyboard shortcuts for tools
    if (!e.ctrlKey && !e.altKey) {
        const toolMap = {
            'b': 'brush',
            'p': 'pencil',
            'm': 'marker',
            'e': 'eraser',
            'l': 'line',
            'r': 'rectangle',
            'c': 'circle',
            'a': 'arrow',
            't': 'text'
        };
        
        if (toolMap[e.key.toLowerCase()]) {
            e.preventDefault();
            const toolBtn = document.querySelector(`[data-tool="${toolMap[e.key.toLowerCase()]}"]`);
            if (toolBtn) toolBtn.click();
        }
    }
});

// ป้องกัน scroll ขณะใช้ touch
document.addEventListener('touchmove', function(e) {
    if (e.target === canvas) {
        e.preventDefault();
    }
}, { passive: false });

// แจ้งเตือนก่อนออกจากหน้า
window.addEventListener('beforeunload', function(e) {
    if (history.length > 1) {
        e.preventDefault();
        e.returnValue = '';
        return '';
    }
});