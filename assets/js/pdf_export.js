/**
 * PDF Export functionality using jsPDF
 */

function exportToPDF(title, data, filename = 'report.pdf') {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    
    let yPos = 20;
    
    // Header
    doc.setFontSize(20);
    doc.setTextColor(14, 165, 233); // Blue color #0ea5e9
    doc.text(title, 105, yPos, { align: 'center' });
    
    yPos += 15;
    
    // Date
    doc.setFontSize(10);
    doc.setTextColor(100, 100, 100);
    doc.text('Generated: ' + new Date().toLocaleString(), 105, yPos, { align: 'center' });
    
    yPos += 15;
    
    // Data
    doc.setFontSize(12);
    doc.setTextColor(0, 0, 0);
    
    if (Array.isArray(data)) {
        data.forEach((row, index) => {
            if (yPos > 270) {
                doc.addPage();
                yPos = 20;
            }
            
            if (typeof row === 'object') {
                let xPos = 20;
                Object.values(row).forEach(value => {
                    doc.text(String(value), xPos, yPos);
                    xPos += 40;
                });
            } else {
                doc.text(String(row), 20, yPos);
            }
            yPos += 8;
        });
    }
    
    doc.save(filename);
}

function exportTableToPDF(tableId, title, filename) {
    const table = document.getElementById(tableId);
    if (!table) {
        console.error('Table not found:', tableId);
        return;
    }
    
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    
    let yPos = 20;
    
    // Header
    doc.setFontSize(18);
    doc.setTextColor(14, 165, 233);
    doc.text(title, 105, yPos, { align: 'center' });
    
    yPos += 10;
    doc.setFontSize(10);
    doc.setTextColor(100, 100, 100);
    doc.text('Generated: ' + new Date().toLocaleString(), 105, yPos, { align: 'center' });
    
    yPos += 15;
    
    // Get headers
    const headers = [];
    const headerCells = table.querySelectorAll('thead th');
    headerCells.forEach(th => {
        headers.push(th.textContent.trim());
    });
    
    // Get rows
    const rows = [];
    const bodyCells = table.querySelectorAll('tbody tr');
    bodyCells.forEach(tr => {
        const row = [];
        tr.querySelectorAll('td').forEach(td => {
            row.push(td.textContent.trim());
        });
        if (row.length > 0) rows.push(row);
    });
    
    // Create table
    doc.autoTable({
        head: [headers],
        body: rows,
        startY: yPos,
        theme: 'grid',
        headStyles: {
            fillColor: [14, 165, 233],
            textColor: 255,
            fontSize: 10
        },
        bodyStyles: {
            fontSize: 9
        },
        alternateRowStyles: {
            fillColor: [240, 247, 255]
        }
    });
    
    doc.save(filename || 'report.pdf');
}

function exportChartToPDF(canvasId, title, filename) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) {
        console.error('Canvas not found:', canvasId);
        return;
    }
    
    const imgData = canvas.toDataURL('image/png');
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    
    // Header
    doc.setFontSize(18);
    doc.setTextColor(14, 165, 233);
    doc.text(title, 105, 20, { align: 'center' });
    
    doc.setFontSize(10);
    doc.setTextColor(100, 100, 100);
    doc.text('Generated: ' + new Date().toLocaleString(), 105, 30, { align: 'center' });
    
    // Add image
    const imgWidth = 170;
    const imgHeight = (canvas.height * imgWidth) / canvas.width;
    doc.addImage(imgData, 'PNG', 20, 40, imgWidth, imgHeight);
    
    doc.save(filename || 'chart.pdf');
}

function createPDFButton(clickHandler, text = 'Export PDF', icon = 'fa-file-pdf') {
    const btn = document.createElement('button');
    btn.innerHTML = `<i class="fas ${icon}"></i> ${text}`;
    btn.onclick = clickHandler;
    btn.style.cssText = `
        padding: 8px 15px;
        background: #0ea5e9;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    `;
    return btn;
}
