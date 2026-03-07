<?php
require_once '../includes/header.php';
requireRole('admin', '../auth/login.php');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export Reports - CHMEWS</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.29/jspdf.plugin.autotable.min.js"></script>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f7fa;
            margin: 0;
            padding: 0;
        }
        .page-header {
            background: linear-gradient(135deg, #0077cc, #005599);
            color: white;
            padding: 30px;
            margin-bottom: 30px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 0 20px;
        }
        .card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        h1 { margin: 0 0 10px; font-size: 1.8rem; }
        h2 { margin: 0 0 20px; color: #333; font-size: 1.3rem; }
        
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        .form-group select, .form-group input[type="date"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }
        
        .report-types {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .report-type {
            padding: 15px;
            border: 2px solid #eee;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .report-type:hover {
            border-color: #0077cc;
        }
        .report-type.selected {
            border-color: #0077cc;
            background: #e8f4ff;
        }
        .report-type h3 {
            margin: 0 0 5px;
            color: #0077cc;
        }
        .report-type p {
            margin: 0;
            color: #666;
            font-size: 0.9rem;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background: #0077cc;
            color: white;
        }
        .btn-primary:hover {
            background: #005599;
        }
        .btn-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .format-options {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }
        .format-option {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .quick-exports {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            margin-top: 20px;
        }
        .quick-export {
            padding: 15px;
            background: #f5f5f5;
            border-radius: 8px;
            text-align: center;
            text-decoration: none;
            color: #0077cc;
            font-weight: 500;
            transition: background 0.3s;
        }
        .quick-export:hover {
            background: #e8f4ff;
        }
        .quick-export i {
            margin-right: 5px;
        }
    </style>
</head>
<body>
    <div class="page-header">
        <h1><i class="fas fa-file-export"></i> Export Reports</h1>
    </div>
    
    <div class="container">
        <div class="card">
            <h2><i class="fas fa-filter"></i> Custom Export</h2>
            
            <form id="exportForm">
                <div class="form-group">
                    <label>Report Type</label>
                    <div class="report-types">
                        <div class="report-type selected" data-value="reports">
                            <h3><i class="fas fa-file-medical"></i> Reports</h3>
                            <p>All health reports</p>
                        </div>
                        <div class="report-type" data-value="analyses">
                            <h3><i class="fas fa-microscope"></i> Analyses</h3>
                            <p>Health worker analyses</p>
                        </div>
                        <div class="report-type" data-value="recommendations">
                            <h3><i class="fas fa-lightbulb"></i> Recommendations</h3>
                            <p>Health recommendations</p>
                        </div>
                        <div class="report-type" data-value="summary">
                            <h3><i class="fas fa-chart-bar"></i> Summary</h3>
                            <p>Statistical summary</p>
                        </div>
                    </div>
                    <input type="hidden" name="type" id="reportType" value="reports">
                </div>
                
                <div class="form-group">
                    <label>Date Range</label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <input type="date" name="date_from" id="dateFrom" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>">
                        <input type="date" name="date_to" id="dateTo" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Export Format</label>
                    <div class="format-options">
                        <label class="format-option">
                            <input type="radio" name="format" value="csv" checked>
                            <span>CSV (Excel)</span>
                        </label>
                        <label class="format-option">
                            <input type="radio" name="format" value="json">
                            <span>JSON</span>
                        </label>
                        <label class="format-option">
                            <input type="radio" name="format" value="pdf">
                            <span>PDF</span>
                        </label>
                    </div>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-download"></i> Download Report
                    </button>
                </div>
            </form>
        </div>
        
        <div class="card">
            <h2><i class="fas fa-bolt"></i> Quick Exports</h2>
            <p style="color: #666; margin-bottom: 15px;">Download commonly used reports instantly</p>
            
            <div class="quick-exports">
                <a href="export_reports.php?type=reports&date_from=<?php echo date('Y-m-d', strtotime('-7 days')); ?>&date_to=<?php echo date('Y-m-d'); ?>&format=csv" class="quick-export">
                    Last 7 Days (CSV)
                </a>
                <a href="export_reports.php?type=reports&date_from=<?php echo date('Y-m-d', strtotime('-30 days')); ?>&date_to=<?php echo date('Y-m-d'); ?>&format=csv" class="quick-export">
                    Last 30 Days (CSV)
                </a>
                <a href="export_reports.php?type=reports&date_from=<?php echo date('Y-m-d', strtotime('-90 days')); ?>&date_to=<?php echo date('Y-m-d'); ?>&format=csv" class="quick-export">
                    Last 90 Days (CSV)
                </a>
                <a href="export_reports.php?type=reports&date_from=<?php echo date('Y-01-01'); ?>&date_to=<?php echo date('Y-m-d'); ?>&format=csv" class="quick-export">
                    This Year (CSV)
                </a>
                <a href="export_reports.php?type=summary&date_from=<?php echo date('Y-m-d', strtotime('-30 days')); ?>&date_to=<?php echo date('Y-m-d'); ?>&format=json" class="quick-export">
                    JSON Summary
                </a>
                <button onclick="exportPDF()" class="quick-export" style="border: none; cursor: pointer; width: 100%;">
                    <i class="fas fa-file-pdf"></i> PDF Report
                </button>
            </div>
        </div>
    </div>
    
    <script>
        
        document.querySelectorAll('.report-type').forEach(el => {
            el.addEventListener('click', () => {
                document.querySelectorAll('.report-type').forEach(e => e.classList.remove('selected'));
                el.classList.add('selected');
                document.getElementById('reportType').value = el.dataset.value;
            });
        });
        
        
        document.getElementById('exportForm').addEventListener('submit', (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const format = formData.get('format');
            
            if (format === 'pdf') {
                generatePDF();
            } else {
                const params = new URLSearchParams(formData);
                window.location.href = '../api/export_reports.php?' + params.toString();
            }
        });
        
        async function generatePDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            
        
            doc.setFontSize(20);
            doc.setTextColor(14, 165, 233);
            doc.text('CHMEWS Report', 105, 20, { align: 'center' });
            
            doc.setFontSize(12);
            doc.setTextColor(100, 100, 100);
            doc.text('Generated: ' + new Date().toLocaleString(), 105, 30, { align: 'center' });
            
            
            const reportType = document.getElementById('reportType').value;
            const dateFrom = document.getElementById('dateFrom').value;
            const dateTo = document.getElementById('dateTo').value;
            
            doc.setFontSize(14);
            doc.setTextColor(0, 0, 0);
            doc.text(`Report Type: ${reportType}`, 20, 45);
            doc.text(`Date Range: ${dateFrom} to ${dateTo}`, 20, 55);
            
            
            try {
                const response = await fetch(`../api/export_reports.php?type=${reportType}&date_from=${dateFrom}&date_to=${dateTo}&format=json`);
                const data = await response.json();
                
                if (data.length > 0) {
                    const headers = Object.keys(data[0]);
                    const rows = data.map(item => headers.map(h => String(item[h] || '')));
                    
                    doc.autoTable({
                        head: [headers],
                        body: rows,
                        startY: 65,
                        theme: 'grid',
                        headStyles: {
                            fillColor: [14, 165, 233],
                            textColor: 255,
                            fontSize: 10
                        },
                        bodyStyles: {
                            fontSize: 8
                        },
                        alternateRowStyles: {
                            fillColor: [240, 247, 255]
                        }
                    });
                } else {
                    doc.setFontSize(12);
                    doc.text('No data available for the selected criteria.', 20, 80);
                }
            } catch (error) {
                doc.setFontSize(12);
                doc.setTextColor(220, 38, 38);
                doc.text('Error loading data: ' + error.message, 20, 80);
            }
            
            doc.save(`chmews_${reportType}_${dateFrom}_${dateTo}.pdf`);
        }
        
        async function exportPDF() {
            document.getElementById('reportType').value = 'reports';
            document.getElementById('dateFrom').value = '<?php echo date('Y-m-d', strtotime('-30 days')); ?>';
            document.getElementById('dateTo').value = '<?php echo date('Y-m-d'); ?>';
            generatePDF();
        }
    </script>
</body>
</html>

<?php require_once '../includes/footer.php'; ?>
