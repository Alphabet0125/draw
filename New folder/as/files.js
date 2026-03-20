// Preview File Modal
function openPreview(fileId, fileType) {
    const modal = document.getElementById('previewModal');
    const previewBody = document.getElementById('previewBody');
    
    // แสดง loading
    previewBody.innerHTML = `
        <div style="text-align: center; padding: 60px;">
            <i class="fas fa-spinner fa-spin" style="font-size: 3rem; color: var(--primary-color);"></i>
            <p style="margin-top: 20px; color: var(--text-color);">กำลังโหลดตัวอย่าง...</p>
        </div>
    `;
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
    
    // แสดงตัวอย่างตามประเภทไฟล์
    setTimeout(() => {
        if (fileType.includes('image')) {
            previewBody.innerHTML = `
                <div class="preview-container">
                    <h2 style="color: var(--primary-color); margin-bottom: 20px; text-align: center;">
                        <i class="fas fa-image"></i> ตัวอย่างรูปภาพ
                    </h2>
                    <div class="image-preview-large">
                        <img src="preview.php?id=${fileId}" alt="Preview" onload="this.style.opacity=1">
                    </div>
                    <div class="preview-actions">
                        <a href="download.php?id=${fileId}" class="btn-download-preview">
                            <i class="fas fa-download"></i> ดาวน์โหลด
                        </a>
                        <button onclick="closePreviewModal()" class="btn-close-preview">
                            <i class="fas fa-times"></i> ปิด
                        </button>
                    </div>
                </div>
            `;
        } else if (fileType.includes('pdf')) {
            previewBody.innerHTML = `
                <div class="preview-container">
                    <h2 style="color: var(--primary-color); margin-bottom: 20px; text-align: center;">
                        <i class="fas fa-file-pdf"></i> ตัวอย่าง PDF
                    </h2>
                    <div class="pdf-preview-container">
                        <iframe src="preview.php?id=${fileId}" frameborder="0"></iframe>
                    </div>
                    <div class="preview-actions">
                        <a href="download.php?id=${fileId}" class="btn-download-preview">
                            <i class="fas fa-download"></i> ดาวน์โหลด
                        </a>
                        <button onclick="closePreviewModal()" class="btn-close-preview">
                            <i class="fas fa-times"></i> ปิด
                        </button>
                    </div>
                </div>
            `;
        }
    }, 300);
}

function closePreviewModal() {
    const modal = document.getElementById('previewModal');
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('previewModal');
    if (event.target == modal) {
        closePreviewModal();
    }
}

// Close modal with ESC key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closePreviewModal();
    }
});

// File card animation on load
document.addEventListener('DOMContentLoaded', () => {
    const fileCards = document.querySelectorAll('.file-card');
    fileCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        setTimeout(() => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 50);
    });
});

// Lazy loading for images
if ('IntersectionObserver' in window) {
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src || img.src;
                img.classList.add('loaded');
                observer.unobserve(img);
            }
        });
    });

    document.querySelectorAll('.image-preview img').forEach(img => {
        imageObserver.observe(img);
    });
}